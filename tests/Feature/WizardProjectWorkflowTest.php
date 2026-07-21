<?php

use App\Enums\LutTransformVersion;
use App\Enums\WizardProjectStatus;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectVariant;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('wizard landing requires a verified active user and does not create a project', function () {
    $this->get(route('custom-lut.create'))->assertRedirect(route('login'));

    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)
        ->get(route('custom-lut.create'))
        ->assertRedirect(route('verification.notice'));

    $suspended = User::factory()->verified()->suspended()->create();
    $this->actingAs($suspended)
        ->get(route('custom-lut.create'))
        ->assertForbidden();

    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->get(route('custom-lut.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('CustomLut/Create'));

    expect(WizardProject::query()->count())->toBe(0);
});

test('verified users can create up to ten active neutral projects', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->post(route('custom-lut.store'))
        ->assertRedirect();

    $project = WizardProject::query()->firstOrFail();

    expect($project->id)->toBeString()
        ->and(Str::isUlid($project->id))->toBeTrue()
        ->and($project->user_id)->toBe($user->id)
        ->and($project->transform_version)->toBe(LutTransformVersion::V1)
        ->and($project->parameters)->toBe(LutTransformParameters::neutral()->toArray())
        ->and($project->parameters_hash)->toBe(LutTransformParameters::neutral()->hash())
        ->and($project->project_seed)->toHaveLength(64)
        ->and(abs($project->expires_at->diffInSeconds(now()->addDays(30))))->toBeLessThanOrEqual(1);

    WizardProject::factory()->count(9)->for($user)->create();

    $this->actingAs($user)
        ->post(route('custom-lut.store'))
        ->assertSessionHasErrors('project');

    WizardProject::factory()->expired()->for($user)->create();

    expect(WizardProject::query()->where('user_id', $user->id)->count())->toBe(11);
});

test('another user and normal administrators cannot view a customer project', function () {
    $owner = User::factory()->verified()->create();
    $project = WizardProject::factory()->for($owner)->create();

    $this->actingAs(User::factory()->verified()->create())
        ->get(route('custom-lut.show', $project))
        ->assertNotFound();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('custom-lut.show', $project))
        ->assertNotFound();
});

test('autosave updates name and parameters with revision and mutation id safety', function () {
    $user = User::factory()->verified()->create();
    $project = WizardProject::factory()->for($user)->create([
        'expires_at' => now()->addDay(),
    ]);
    $mutationId = (string) Str::uuid();
    $parameters = LutTransformParameters::neutral()->withChanges(['contrast' => 120]);

    $response = $this->actingAs($user)
        ->patchJson(route('custom-lut.update', $project), [
            'expected_revision' => 1,
            'mutation_id' => $mutationId,
            'name' => 'Portrait Pack',
            'parameters' => $parameters->toArray(),
            'transform_version' => 'malicious',
            'project_seed' => str_repeat('0', 64),
            'expires_at' => now()->addYears(2)->toISOString(),
            'status' => WizardProjectStatus::Expired->value,
        ])
        ->assertOk()
        ->json('project');

    expect($response['name'])->toBe('Portrait Pack')
        ->and($response['revision'])->toBe(2)
        ->and($response['parameters'])->toBe($parameters->toArray())
        ->and($response['parameters_hash'])->toBe($parameters->hash());

    $project->refresh();

    expect($project->transform_version)->toBe(LutTransformVersion::V1)
        ->and($project->project_seed)->not->toBe(str_repeat('0', 64))
        ->and($project->status)->toBe(WizardProjectStatus::Draft)
        ->and($project->expires_at->greaterThan(now()->addDays(29)))->toBeTrue();

    $this->actingAs($user)
        ->patchJson(route('custom-lut.update', $project), [
            'expected_revision' => 1,
            'mutation_id' => (string) Str::uuid(),
            'parameters' => LutTransformParameters::neutral()->toArray(),
        ])
        ->assertConflict();

    $this->actingAs($user)
        ->patchJson(route('custom-lut.update', $project), [
            'expected_revision' => 1,
            'mutation_id' => $mutationId,
            'parameters' => LutTransformParameters::neutral()->toArray(),
        ])
        ->assertOk()
        ->assertJsonPath('project.revision', 2);
});

test('style selection snapshots active styles resets parameters and clears variants', function () {
    $style = WizardStyle::factory()->active()->create([
        'name' => 'Snapshot Style',
        'base_parameters' => LutTransformParameters::neutral()->withChanges(['contrast' => 180])->toArray(),
    ]);
    $project = WizardProject::factory()->create([
        'parameters' => LutTransformParameters::neutral()->withChanges(['contrast' => -100])->toArray(),
    ]);
    WizardProjectVariant::factory()->for($project)->create();

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.style.store', $project), [
            'expected_revision' => 1,
            'mutation_id' => (string) Str::uuid(),
            'style_id' => $style->id,
        ])
        ->assertOk()
        ->assertJsonPath('project.parameters.contrast', 180)
        ->assertJsonPath('project.selected_style.name', 'Snapshot Style');

    $project->refresh();

    expect($project->wizard_style_id)->toBe($style->id)
        ->and($project->style_name_snapshot)->toBe('Snapshot Style')
        ->and($project->style_snapshot['base_parameters']['contrast'])->toBe(180)
        ->and($project->variants()->count())->toBe(0);
});
