<?php

use App\Actions\CustomLutBuilds\ActivatePackageDocumentTemplate;
use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use App\Models\PackageDocumentTemplate;
use App\Models\User;
use Database\Seeders\PackageDocumentTemplateSeeder;
use Illuminate\Validation\ValidationException;

test('package document seeder is idempotent and creates draft placeholders', function () {
    $this->seed(PackageDocumentTemplateSeeder::class);
    $this->seed(PackageDocumentTemplateSeeder::class);

    $templates = PackageDocumentTemplate::query()->orderBy('kind')->get();

    expect($templates)->toHaveCount(2)
        ->and($templates->every(fn (PackageDocumentTemplate $template): bool => $template->isDraft()))->toBeTrue()
        ->and($templates->every(fn (PackageDocumentTemplate $template): bool => str_contains($template->body, 'DRAFT PLACEHOLDER')))->toBeTrue()
        ->and($templates->every(fn (PackageDocumentTemplate $template): bool => $template->mayBeUsedForReviewBuild()))->toBeTrue()
        ->and($templates->every(fn (PackageDocumentTemplate $template): bool => ! $template->mayBeUsedForSaleBuild()))->toBeTrue();
});

test('activating package document template leaves exactly one current template per kind', function () {
    $old = PackageDocumentTemplate::factory()->active()->current()->create([
        'kind' => PackageDocumentKind::License,
        'version' => 'license-v1',
    ]);
    $new = PackageDocumentTemplate::factory()->create([
        'kind' => PackageDocumentKind::License,
        'status' => PackageDocumentStatus::Draft,
        'version' => 'license-v2',
        'is_current' => false,
    ]);

    app(ActivatePackageDocumentTemplate::class)->handle($new);

    expect($old->refresh()->is_current)->toBeFalse()
        ->and($new->refresh()->is_current)->toBeTrue()
        ->and($new->status)->toBe(PackageDocumentStatus::Active)
        ->and($new->activated_at)->not->toBeNull()
        ->and(PackageDocumentTemplate::query()->where('kind', PackageDocumentKind::License->value)->where('is_current', true)->count())->toBe(1);
});

test('soft deleted package document template cannot be activated', function () {
    $template = PackageDocumentTemplate::factory()->create();
    $template->delete();

    expect(fn () => app(ActivatePackageDocumentTemplate::class)->handle($template->refresh()))
        ->toThrow(ValidationException::class);
});

test('admin can access package document resource and customers cannot', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->verified()->create();

    $this->actingAs($customer)
        ->get('/admin/package-document-templates')
        ->assertForbidden();

    $this->actingAs($admin)
        ->get('/admin/package-document-templates')
        ->assertOk();
});
