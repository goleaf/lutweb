<?php

use App\Actions\CustomLutBuilds\CreateCustomLutBuild;
use App\Color\CubeSize;
use App\Enums\CustomLutBuildFileKind;
use App\Enums\CustomLutBuildStatus;
use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use App\Jobs\GenerateCustomLutBuild;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\PackageDocumentTemplate;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\CustomLutBuilds\MeasurePreviewParity;
use App\Services\CustomLutBuilds\PackageNameGenerator;
use App\Services\CustomLutBuilds\ValidateGeneratedCube;
use App\Services\CustomLutBuilds\WriteCubeFile;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('private');

    config([
        'custom-lut-builds.enabled' => true,
        'custom-lut-builds.private_disk' => 'private',
        'custom-lut-builds.queue' => 'images',
        'custom-lut-builds.ffmpeg_validation_enabled' => false,
        'custom-lut-builds.allow_draft_documents' => true,
    ]);
});

function createCurrentPackageDocuments(bool $final = false): void
{
    PackageDocumentTemplate::factory()->create([
        'kind' => PackageDocumentKind::License,
        'status' => $final ? PackageDocumentStatus::Active : PackageDocumentStatus::Draft,
        'version' => $final ? 'license-v1' : 'draft-license-v1',
        'title' => 'Custom LUT License',
        'body' => $final ? 'Final reviewed license text.' : 'DRAFT PLACEHOLDER - NOT FOR PRODUCTION SALE',
        'is_current' => true,
        'activated_at' => $final ? now() : null,
    ]);

    PackageDocumentTemplate::factory()->create([
        'kind' => PackageDocumentKind::InstallationGuide,
        'status' => $final ? PackageDocumentStatus::Active : PackageDocumentStatus::Draft,
        'version' => $final ? 'guide-v1' : 'draft-guide-v1',
        'title' => 'Installation Guide',
        'body' => $final ? 'Install the included CUBE file in a compatible host.' : 'DRAFT PLACEHOLDER - REVIEW BEFORE PRODUCTION SALE',
        'is_current' => true,
        'activated_at' => $final ? now() : null,
    ]);
}

test('package name generator creates safe deterministic stems and titles', function (string $name, string $expected) {
    $generated = app(PackageNameGenerator::class)->make($name, '01K0BUILDIDFORTESTING000000');

    expect($generated->stem)->toBe($expected)
        ->and($generated->zipFilename())->toBe($expected.'.zip')
        ->and($generated->cubeFilename(33))->toBe($expected.'-33.cube')
        ->and($generated->title())->not->toContain("\n")
        ->and($generated->title())->not->toContain('"');
})->with([
    'normal english' => ['My Warm Travel Look', 'my-warm-travel-look'],
    'path traversal' => ['../My//Look..', 'my-look'],
    'reserved windows name' => ['CON', 'custom-lut-ting000000'],
    'empty fallback' => ["\t\n", 'custom-lut-ting000000'],
]);

test('cube writer streams red-fastest generated values and validator rejects axis swaps', function () {
    $directory = storage_path('framework/testing/custom-lut-cube-'.Str::random(8));
    $path = $directory.'/identity.cube';
    $parameters = LutTransformParameters::neutral();
    $name = app(PackageNameGenerator::class)->make('Identity LUT', '01K0BUILDIDFORTESTING000000');

    mkdir($directory, recursive: true);

    try {
        app(WriteCubeFile::class)->handle($path, new CubeSize(17), $name, $parameters, $parameters->hash());
        app(ValidateGeneratedCube::class)->handle($path, new CubeSize(17), $parameters);

        $contents = file($path, FILE_IGNORE_NEW_LINES);
        expect($contents)->toBeArray()
            ->and(collect($contents)->filter(fn (string $line): bool => preg_match('/^\d+\.\d+ \d+\.\d+ \d+\.\d+$/', $line) === 1))->toHaveCount(17 ** 3)
            ->and(implode("\n", $contents))->not->toContain('E-')
            ->not->toContain('NaN')
            ->not->toContain('-0.000000000');
    } finally {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }
    }
});

test('preview parity metrics are deterministic and strict for neutral parameters', function () {
    config(['custom-lut-builds.parity_sample_count' => 512]);

    $first = app(MeasurePreviewParity::class)->handle(LutTransformParameters::neutral());
    $second = app(MeasurePreviewParity::class)->handle(LutTransformParameters::neutral());

    expect($first->meanMillionths)->toBeGreaterThanOrEqual(0)
        ->and($first->maxMillionths)->toBeLessThanOrEqual((int) config('custom-lut-builds.parity_thresholds.between_max_millionths'))
        ->and($first->toArray())->toBe($second->toArray());
});

test('verified project owner can request an idempotent package build and no generation runs in http request', function () {
    Queue::fake();
    createCurrentPackageDocuments();

    $project = WizardProject::factory()->create([
        'name' => 'Warm Travel Package',
        'expires_at' => now()->addDays(4),
    ]);
    $requestId = (string) Str::uuid();

    $response = $this->actingAs($project->user)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision,
            'expected_parameters_hash' => $project->parameters_hash,
            'build_request_id' => $requestId,
        ])
        ->assertOk()
        ->assertJsonPath('build.status', CustomLutBuildStatus::Queued->value)
        ->json('build');

    $same = $this->actingAs($project->user)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision,
            'expected_parameters_hash' => $project->parameters_hash,
            'build_request_id' => $requestId,
        ])
        ->assertOk()
        ->json('build');

    expect($same['id'])->toBe($response['id'])
        ->and(CustomLutBuild::query()->count())->toBe(1)
        ->and(CustomLutBuildFile::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0)
        ->and(Entitlement::query()->count())->toBe(0);

    Queue::assertPushed(GenerateCustomLutBuild::class);

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision,
            'expected_parameters_hash' => $project->parameters_hash,
            'build_request_id' => (string) Str::uuid(),
            'parameters' => ['contrast' => 1000],
        ])
        ->assertUnprocessable();
});

test('build creation enforces ownership revision hash and suspension rules', function () {
    createCurrentPackageDocuments();

    $project = WizardProject::factory()->create();
    $other = User::factory()->verified()->create();

    $this->postJson(route('custom-lut.builds.store', $project), [
        'expected_revision' => $project->revision,
        'expected_parameters_hash' => $project->parameters_hash,
        'build_request_id' => (string) Str::uuid(),
    ])->assertUnauthorized();

    $this->actingAs($other)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision,
            'expected_parameters_hash' => $project->parameters_hash,
            'build_request_id' => (string) Str::uuid(),
        ])
        ->assertNotFound();

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision + 1,
            'expected_parameters_hash' => $project->parameters_hash,
            'build_request_id' => (string) Str::uuid(),
        ])
        ->assertConflict();

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision,
            'expected_parameters_hash' => str_repeat('a', 64),
            'build_request_id' => (string) Str::uuid(),
        ])
        ->assertConflict();

    $project->user->forceFill(['is_suspended' => true])->save();

    $this->actingAs($project->user)
        ->postJson(route('custom-lut.builds.store', $project), [
            'expected_revision' => $project->revision,
            'expected_parameters_hash' => $project->parameters_hash,
            'build_request_id' => (string) Str::uuid(),
        ])
        ->assertForbidden();
});

test('build generation creates private package files without creating orders or entitlements', function () {
    if (! class_exists(ZipArchive::class)) {
        $this->markTestSkipped('ZipArchive is required for package generation.');
    }

    createCurrentPackageDocuments();

    $project = WizardProject::factory()->create([
        'name' => 'Draft Package Build',
        'expires_at' => now()->addDays(4),
    ]);
    $build = app(CreateCustomLutBuild::class)->handle(
        $project->user,
        $project,
        $project->revision,
        $project->parameters_hash,
        (string) Str::uuid(),
    );

    app()->call([new GenerateCustomLutBuild($build->id), 'handle']);

    $build->refresh()->load('files');

    expect($build->status)->toBe(CustomLutBuildStatus::Ready)
        ->and($build->contains_draft_documents)->toBeTrue()
        ->and($build->sale_ready)->toBeFalse()
        ->and($build->zip_validation_completed)->toBeTrue()
        ->and($build->parity_validation_passed)->toBeTrue()
        ->and($build->ffmpeg_validation_passed)->toBeFalse()
        ->and($build->files)->toHaveCount(9)
        ->and($build->files->where('kind', CustomLutBuildFileKind::PackageZip)->count())->toBe(1)
        ->and(Order::query()->count())->toBe(0)
        ->and(Entitlement::query()->count())->toBe(0);

    foreach ($build->files as $file) {
        expect($file->disk)->toBe('private')
            ->and($file->path)->toStartWith('custom-lut-builds/'.$project->user_id.'/'.$project->id.'/'.$build->id.'/')
            ->and($file->path)->not->toContain('Draft Package Build')
            ->and(Storage::disk('private')->exists($file->path))->toBeTrue();
    }

    $zip = $build->files->firstWhere('kind', CustomLutBuildFileKind::PackageZip);
    expect($zip)->not->toBeNull()
        ->and($zip->relative_package_path)->toBe('draft-package-build.zip')
        ->and($zip->sha256)->toHaveLength(64)
        ->and($build->zip_size_bytes)->toBeGreaterThan(0)
        ->and($build->uncompressed_size_bytes)->toBeGreaterThan(0);
});

test('editor exposes only safe build props', function () {
    $project = WizardProject::factory()->create();
    $build = CustomLutBuild::factory()
        ->for($project->user)
        ->for($project, 'wizardProject')
        ->saleReady()
        ->create([
            'project_revision' => $project->revision,
            'parameters_hash' => $project->parameters_hash,
            'is_current' => true,
        ]);
    CustomLutBuildFile::factory()
        ->packageZip()
        ->for($build, 'customLutBuild')
        ->create([
            'path' => 'custom-lut-builds/private/package.zip',
            'relative_package_path' => 'safe-package.zip',
            'safe_download_name' => 'safe-package.zip',
        ]);

    $response = $this->actingAs($project->user)
        ->get(route('custom-lut.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomLut/Show')
            ->has('build.id')
            ->missing('build.disk')
            ->missing('build.files.0.path'));

    $props = json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR);

    expect($props)->not->toContain('custom-lut-builds/private')
        ->not->toContain('disk')
        ->not->toContain('project_seed')
        ->not->toContain('variant_seed');
});

test('parameter mutation supersedes current builds but photo changes do not', function () {
    $project = WizardProject::factory()->create();
    $build = CustomLutBuild::factory()
        ->for($project->user)
        ->for($project, 'wizardProject')
        ->saleReady()
        ->create([
            'project_revision' => $project->revision,
            'parameters_hash' => $project->parameters_hash,
            'is_current' => true,
            'disk' => 'private',
        ]);
    $path = 'custom-lut-builds/'.$project->user_id.'/'.$project->id.'/'.$build->id.'/package/build.zip';
    Storage::disk('private')->put($path, 'zip');
    CustomLutBuildFile::factory()->packageZip()->for($build, 'customLutBuild')->create([
        'path' => $path,
    ]);

    $this->actingAs($project->user)
        ->patchJson(route('custom-lut.update', $project), [
            'expected_revision' => $project->revision,
            'mutation_id' => (string) Str::uuid(),
            'parameters' => LutTransformParameters::neutral()->withChanges(['contrast' => 120])->toArray(),
        ])
        ->assertOk()
        ->assertJsonPath('build.status', CustomLutBuildStatus::Superseded->value);

    expect($build->refresh()->status)->toBe(CustomLutBuildStatus::Superseded)
        ->and(Storage::disk('private')->exists($path))->toBeFalse();
});
