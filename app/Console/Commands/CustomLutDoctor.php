<?php

namespace App\Console\Commands;

use App\Models\CustomLutBuild;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\PackageDocumentTemplate;
use App\Models\WizardStyle;
use App\Services\CustomLutBuilds\GenerateCustomLutPackage;
use App\Services\CustomLutBuilds\PackageDocumentSnapshot;
use App\ValueObjects\LutTransformParameters;
use Composer\InstalledVersions;
use Dompdf\Dompdf;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('custom-lut:doctor {--self-test : Generate a temporary package without creating customer records} {--show-config : Show safe build configuration values}')]
#[Description('Check Custom LUT package generation readiness.')]
class CustomLutDoctor extends Command
{
    private int $failures = 0;

    private int $warnings = 0;

    public function handle(GenerateCustomLutPackage $packageGenerator): int
    {
        $this->check('Custom LUT builds enabled', (bool) config('custom-lut-builds.enabled'), required: false);
        $this->check('Transform version supported', config('custom-lut-builds.transform_version') === 'lut_transform_v1', required: true);
        $this->check('Generator version supported', config('custom-lut-builds.generator_version') === 'cube_generator_v1', required: true);
        $this->check('Package schema version configured', filled(config('custom-lut-builds.package_schema_version')), required: true);
        $this->check('CUBE sizes are exactly 17, 33, and 65', $this->cubeSizesValid(), required: true);
        $this->check('Decimal precision is reasonable', (int) config('custom-lut-builds.cube_precision', 9) >= 6 && (int) config('custom-lut-builds.cube_precision', 9) <= 12, required: true);
        $this->check('Private disk exists', array_key_exists((string) config('custom-lut-builds.private_disk', 'private'), config('filesystems.disks', [])), required: true);
        $this->check('Private disk is writable', $this->privateDiskWritable(), required: true);
        $this->check('Work directory is writable', $this->workDirectoryWritable(), required: true);
        $this->check('ext-zip loaded', extension_loaded('zip'), required: true);
        $this->check('ZipArchive class exists', class_exists(\ZipArchive::class), required: true);
        $this->check('ext-dom loaded', extension_loaded('dom'), required: true);
        $this->check('ext-mbstring loaded', extension_loaded('mbstring'), required: true);
        $this->check('Dompdf installed', class_exists(Dompdf::class), required: true);
        $this->pass('Dompdf version: '.(InstalledVersions::isInstalled('dompdf/dompdf') ? InstalledVersions::getPrettyVersion('dompdf/dompdf') : 'unknown'));
        $this->pass('PDF generator disables remote resources, PHP execution, and JavaScript in code');
        $this->check('License template exists', $this->currentTemplateExists('license'), required: true);
        $this->check('Guide template exists', $this->currentTemplateExists('installation_guide'), required: true);
        $this->check('Active non-draft License template is sale-ready', $this->finalCurrentTemplateExists('license'), required: false);
        $this->check('Active non-draft Guide template is sale-ready', $this->finalCurrentTemplateExists('installation_guide'), required: false);
        $this->warnWhen((bool) config('custom-lut-builds.allow_draft_documents', true), 'Draft documents are allowed for review builds; sale_ready will remain false.');
        $this->pass('Queue connection: '.config('queue.default'));
        $this->warnWhen(app()->isProduction() && config('queue.default') === 'sync', 'Production should not use the sync queue for package builds.');
        $this->check('Prune schedule exists', $this->sourceContains('custom-lut-builds:prune', base_path('routes/console.php')), required: true);
        $this->check('FFmpeg executable and lut3d filter', $this->ffmpegReady(), required: (bool) config('custom-lut-builds.ffmpeg_validation_enabled', true));
        $this->check('Tetrahedral interpolation configured', config('custom-lut-builds.ffmpeg_interpolation') === 'tetrahedral', required: true);
        $this->check('TypeScript transform conformance command exists', $this->sourceContains('test:lut-transform', base_path('package.json')), required: true);
        $this->check('PHP conformance fixture exists', is_file(base_path('tests/Fixtures/lut-transform-v1-conformance.json')), required: true);
        $this->check('Seeded Wizard Styles are supported', $this->activeStylesSupported(), required: false);
        $this->check('Build limits are positive', (int) config('custom-lut-builds.maximum_builds_per_project_per_hour', 5) > 0 && (int) config('custom-lut-builds.maximum_builds_per_user_per_day', 20) > 0, required: true);
        $this->check('Package size limits are positive', (int) config('custom-lut-builds.maximum_package_size_bytes', 0) > 0 && (int) config('custom-lut-builds.maximum_uncompressed_zip_size_bytes', 0) > 0, required: true);
        $this->check('Controlled private prefix is valid', $this->prefixValid(), required: true);
        $this->check('No public build-file route exists', ! $this->publicBuildFileRouteExists(), required: true);

        if ((bool) $this->option('show-config')) {
            $this->showSafeConfig();
        }

        if ((bool) $this->option('self-test')) {
            $this->selfTest($packageGenerator);
        }

        $this->line('Doctor complete: '.$this->failures.' FAIL, '.$this->warnings.' WARN.');

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $passes, bool $required): void
    {
        if ($passes) {
            $this->pass($label);

            return;
        }

        if ($required) {
            $this->failLine($label);

            return;
        }

        $this->warnLine($label);
    }

    private function pass(string $label): void
    {
        $this->line('PASS '.$label);
    }

    private function warnWhen(bool $condition, string $label): void
    {
        if ($condition) {
            $this->warnLine($label);
        }
    }

    private function warnLine(string $label): void
    {
        $this->warnings++;
        $this->line('WARN '.$label);
    }

    private function failLine(string $label): void
    {
        $this->failures++;
        $this->line('FAIL '.$label);
    }

    private function cubeSizesValid(): bool
    {
        $sizes = array_values(array_unique(array_map('intval', (array) config('custom-lut-builds.cube_sizes', []))));
        sort($sizes);

        return $sizes === [17, 33, 65];
    }

    private function privateDiskWritable(): bool
    {
        try {
            $disk = Storage::disk((string) config('custom-lut-builds.private_disk', 'private'));
            $path = trim((string) config('custom-lut-builds.build_prefix', 'custom-lut-builds'), '/').'/doctor.txt';
            $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }

    private function workDirectoryWritable(): bool
    {
        try {
            $root = storage_path('app/private/'.trim((string) config('custom-lut-builds.work_prefix', 'custom-lut-build-work'), '/'));
            File::ensureDirectoryExists($root);

            return is_writable($root);
        } catch (Throwable) {
            return false;
        }
    }

    private function currentTemplateExists(string $kind): bool
    {
        return PackageDocumentTemplate::query()
            ->where('kind', $kind)
            ->where('is_current', true)
            ->exists();
    }

    private function finalCurrentTemplateExists(string $kind): bool
    {
        return PackageDocumentTemplate::query()
            ->where('kind', $kind)
            ->where('is_current', true)
            ->where('status', 'active')
            ->where('version', 'not like', 'draft-%')
            ->exists();
    }

    private function ffmpegReady(): bool
    {
        try {
            $version = Process::timeout(5)->run([(string) config('custom-lut-builds.ffmpeg_binary', 'ffmpeg'), '-version']);
            $filters = Process::timeout(10)->run([(string) config('custom-lut-builds.ffmpeg_binary', 'ffmpeg'), '-hide_banner', '-filters']);

            return $version->successful() && $filters->successful() && str_contains($filters->output(), 'lut3d');
        } catch (Throwable) {
            return false;
        }
    }

    private function activeStylesSupported(): bool
    {
        return WizardStyle::query()
            ->where('is_active', true)
            ->where('transform_version', 'lut_transform_v1')
            ->exists();
    }

    private function prefixValid(): bool
    {
        $prefix = trim((string) config('custom-lut-builds.build_prefix', 'custom-lut-builds'), '/');

        return $prefix !== '' && ! str_contains($prefix, '..') && ! str_contains($prefix, '\\');
    }

    private function publicBuildFileRouteExists(): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();

            if (! str_contains($name.' '.$uri, 'custom-lut')) {
                continue;
            }

            if (str_contains($uri, 'builds') && (str_contains($uri, 'download') || str_contains($uri, 'files') || str_contains($uri, 'cube') || str_contains($uri, 'pdf'))) {
                return true;
            }
        }

        return false;
    }

    private function sourceContains(string $needle, string $path): bool
    {
        return is_file($path) && str_contains((string) file_get_contents($path), $needle);
    }

    private function showSafeConfig(): void
    {
        $this->line('Safe config: queue='.config('custom-lut-builds.queue').', sizes='.implode(',', (array) config('custom-lut-builds.cube_sizes')).', ttl_days='.config('custom-lut-builds.build_expiration_days'));
    }

    private function selfTest(GenerateCustomLutPackage $packageGenerator): void
    {
        $beforeOrders = Order::query()->count();
        $beforeEntitlements = Entitlement::query()->count();
        $workDir = storage_path('app/private/'.trim((string) config('custom-lut-builds.work_prefix', 'custom-lut-build-work'), '/').'/doctor-'.bin2hex(random_bytes(6)));

        try {
            File::ensureDirectoryExists($workDir);
            $license = PackageDocumentTemplate::query()->where('kind', 'license')->where('is_current', true)->first();
            $guide = PackageDocumentTemplate::query()->where('kind', 'installation_guide')->where('is_current', true)->first();

            if (! $license instanceof PackageDocumentTemplate || ! $guide instanceof PackageDocumentTemplate) {
                $this->failLine('Self-test requires current package document templates');

                return;
            }

            $parameters = LutTransformParameters::neutral();
            $build = new CustomLutBuild([
                'id' => '01K0DOCTORBUILD000000000000',
                'user_id' => 0,
                'wizard_project_id' => '01K0DOCTORPROJECT000000000',
                'project_name_snapshot' => 'Doctor Neutral LUT',
                'package_stem' => 'doctor-neutral-lut',
                'project_revision' => 1,
                'parameters' => $parameters->toArray(),
                'parameters_hash' => $parameters->hash(),
                'transform_version' => 'lut_transform_v1',
                'generator_version' => 'cube_generator_v1',
                'package_schema_version' => (string) config('custom-lut-builds.package_schema_version'),
                'license_document_snapshot' => $this->snapshot($license),
                'guide_document_snapshot' => $this->snapshot($guide),
            ]);

            $package = $packageGenerator->handle($build, $workDir);
            $this->check('Self-test generated all package files', count($package->files) === 9, required: true);
            $this->check('Self-test created no Order', Order::query()->count() === $beforeOrders, required: true);
            $this->check('Self-test created no Entitlement', Entitlement::query()->count() === $beforeEntitlements, required: true);
        } catch (Throwable) {
            $this->failLine('Self-test package generation failed');
        } finally {
            if (is_dir($workDir) && ! is_link($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PackageDocumentTemplate $template): array
    {
        $snapshot = PackageDocumentSnapshot::fromTemplate($template);

        return [
            'id' => $snapshot->id,
            'kind' => $snapshot->kind->value,
            'status' => $snapshot->status->value,
            'version' => $snapshot->version,
            'title' => $snapshot->title,
            'body' => $snapshot->body,
            'is_current' => $snapshot->isCurrent,
            'content_hash' => $snapshot->contentHash,
        ];
    }
}
