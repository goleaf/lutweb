<?php

use App\Enums\WizardPhotoStatus;
use App\Enums\WizardVariationMode;
use App\Jobs\ProcessWizardProjectPhoto;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Models\WizardProjectVariant;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('private');
});

test('fresh generation creates four safe variants and selection copies one to the project', function () {
    $style = WizardStyle::factory()->active()->create();
    $project = WizardProject::factory()->create();

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.style.store', $project), [
            'expected_revision' => 1,
            'mutation_id' => (string) Str::uuid(),
            'style_id' => $style->id,
        ])
        ->assertOk();

    $project->refresh();

    $response = $this->actingAs($project->user)
        ->postJson(route('custom-lut.variations.store', $project), [
            'expected_revision' => $project->revision,
            'mutation_id' => (string) Str::uuid(),
            'mode' => WizardVariationMode::Fresh->value,
        ])
        ->assertOk()
        ->json();

    expect($response['variants'])->toHaveCount(4)
        ->and(collect($response['variants'])->pluck('parameters_hash')->unique())->toHaveCount(4)
        ->and(collect($response['variants'])->pluck('position')->all())->toBe([1, 2, 3, 4]);

    $project->refresh();
    $variant = $project->variants()->orderBy('position')->firstOrFail();

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.variations.select', [$project, $variant]), [
            'expected_revision' => $project->revision,
            'mutation_id' => (string) Str::uuid(),
        ])
        ->assertOk()
        ->assertJsonPath('project.parameters_hash', $variant->parameters_hash);

    $project->refresh();

    expect($project->parameters)->toBe($variant->parameters)
        ->and($variant->refresh()->selected_at)->not->toBeNull();
});

test('another user cannot generate or select variants for the project', function () {
    $project = WizardProject::factory()->create();
    $variant = WizardProjectVariant::factory()->for($project)->create([
        'generation' => $project->variation_generation,
    ]);
    $other = User::factory()->verified()->create();

    $this->actingAs($other)
        ->postJson(route('custom-lut.variations.store', $project), [
            'expected_revision' => $project->revision,
            'mutation_id' => (string) Str::uuid(),
            'mode' => WizardVariationMode::Fresh->value,
        ])
        ->assertNotFound();

    $this->actingAs($other)
        ->postJson(route('custom-lut.variations.select', [$project, $variant]), [
            'expected_revision' => $project->revision,
            'mutation_id' => (string) Str::uuid(),
        ])
        ->assertNotFound();
});

test('photo upload stores raw privately without original filename and dispatches processing', function () {
    Queue::fake();
    $project = WizardProject::factory()->create();
    $file = UploadedFile::fake()->image('../../portrait.jpg', 640, 640)->size(512);

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.photos.store', $project), [
            'photo' => $file,
        ])
        ->assertCreated()
        ->assertJsonPath('photo.status', WizardPhotoStatus::Queued->value);

    $photo = WizardProjectPhoto::query()->firstOrFail();

    expect($photo->disk)->toBe('private')
        ->and($photo->raw_path)->toStartWith('custom-lut-projects/'.$project->user_id.'/'.$project->id.'/photos/'.$photo->id.'/raw/')
        ->and($photo->raw_path)->not->toContain('portrait')
        ->and($photo->expires_at->diffInMinutes(now()->addHour()))->toBeLessThanOrEqual(1);

    Storage::disk('private')->assertExists($photo->raw_path);
    Queue::assertPushed(ProcessWizardProjectPhoto::class);
});

test('signed private preview serves only ready owner photos with private headers', function () {
    $project = WizardProject::factory()->create();
    $path = 'custom-lut-projects/'.$project->user_id.'/'.$project->id.'/photos/photo-1/preview.webp';
    Storage::disk('private')->put($path, 'webp-preview');
    $photo = WizardProjectPhoto::factory()->for($project)->ready()->create([
        'preview_path' => $path,
        'expires_at' => now()->addMinutes(20),
    ]);
    $url = URL::temporarySignedRoute('custom-lut.photos.preview', now()->addMinutes(10), [$project, $photo]);

    $this->get($url)->assertRedirect(route('login'));

    $response = $this->actingAs($project->user)
        ->get($url)
        ->assertOk();

    $response->assertHeader('Content-Type', 'image/webp');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');

    $other = User::factory()->verified()->create();
    $this->actingAs($other)->get($url)->assertNotFound();
});

test('editor props do not expose private photo paths or seeds', function () {
    $project = WizardProject::factory()->create();
    $photo = WizardProjectPhoto::factory()->for($project)->ready()->create([
        'raw_path' => 'custom-lut-projects/'.$project->user_id.'/'.$project->id.'/photos/photo/raw/input.jpg',
        'preview_path' => 'custom-lut-projects/'.$project->user_id.'/'.$project->id.'/photos/photo/preview.webp',
    ]);
    WizardProjectVariant::factory()->for($project)->create();

    Storage::disk('private')->put($photo->preview_path, 'preview');

    $response = $this->actingAs($project->user)
        ->get(route('custom-lut.show', $project))
        ->assertOk();

    $props = json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR);

    expect($props)->not->toContain('raw_path')
        ->not->toContain('preview_path')
        ->not->toContain('project_seed')
        ->not->toContain('variant seed')
        ->not->toContain('checkout_url')
        ->not->toContain('PayPal');
});

test('prune removes expired photos while preserving project parameters and supports dry run', function () {
    $project = WizardProject::factory()->create([
        'parameters' => LutTransformParameters::neutral()->withChanges(['contrast' => 100])->toArray(),
    ]);
    $previewPath = 'custom-lut-projects/'.$project->user_id.'/'.$project->id.'/photos/photo/preview.webp';
    Storage::disk('private')->put($previewPath, 'preview');
    $photo = WizardProjectPhoto::factory()->for($project)->ready()->create([
        'preview_path' => $previewPath,
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('lut-wizard:prune --dry-run')->assertSuccessful();
    expect(WizardProjectPhoto::query()->whereKey($photo->id)->exists())->toBeTrue();
    Storage::disk('private')->assertExists($previewPath);

    $this->artisan('lut-wizard:prune')->assertSuccessful();

    expect(WizardProject::query()->whereKey($project->id)->exists())->toBeTrue()
        ->and(WizardProjectPhoto::query()->whereKey($photo->id)->exists())->toBeFalse()
        ->and(WizardProject::query()->findOrFail($project->id)->parameters['contrast'])->toBe(100);
    Storage::disk('private')->assertMissing($previewPath);
});
