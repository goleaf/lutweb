<?php

namespace Database\Seeders;

use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Models\WizardProjectVariant;
use App\Models\WizardStyle;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalDemoCustomLutSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureLocalOrTesting();

        if (! WizardStyle::query()->exists()) {
            $this->call(WizardStyleSeeder::class);
        }

        $user = $this->demoUser();
        $style = WizardStyle::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();
        $project = $this->project($user, $style);
        $this->photo($project);
        $this->variants($project);
        $build = $this->build($user, $project);
        $this->buildFile($build);
    }

    private function project(User $user, WizardStyle $style): WizardProject
    {
        $project = WizardProject::query()
            ->where('user_id', $user->id)
            ->where('name', 'Demo Custom LUT')
            ->first();

        if ($project instanceof WizardProject) {
            return $project;
        }

        $parameters = LutTransformParameters::fromArray($style->base_parameters);

        return WizardProject::factory()
            ->for($user)
            ->for($style, 'wizardStyle')
            ->create([
                'name' => 'Demo Custom LUT',
                'style_name_snapshot' => $style->name,
                'style_snapshot' => $style->snapshot(),
                'parameters' => $parameters->toArray(),
                'parameters_hash' => $parameters->hash(),
                'last_autosaved_at' => now(),
            ]);
    }

    private function photo(WizardProject $project): WizardProjectPhoto
    {
        $photo = WizardProjectPhoto::query()
            ->where('wizard_project_id', $project->id)
            ->where('sort_order', 1)
            ->first();

        if ($photo instanceof WizardProjectPhoto) {
            return $photo;
        }

        return WizardProjectPhoto::factory()
            ->ready()
            ->for($project)
            ->create([
                'raw_path' => 'custom-lut-projects/demo/'.$project->id.'/raw/photo.jpg',
                'preview_path' => 'custom-lut-projects/demo/'.$project->id.'/previews/photo.webp',
            ]);
    }

    private function variants(WizardProject $project): void
    {
        if (WizardProjectVariant::query()->where('wizard_project_id', $project->id)->exists()) {
            return;
        }

        $baseParameters = LutTransformParameters::fromArray($project->parameters);
        $variants = [
            $baseParameters->withChanges(['contrast' => -120]),
            $baseParameters->withChanges(['contrast' => 80, 'temperature' => 40]),
            $baseParameters->withChanges(['contrast' => 160, 'saturation' => 60]),
        ];

        foreach ($variants as $index => $parameters) {
            WizardProjectVariant::factory()
                ->for($project)
                ->create([
                    'position' => $index + 1,
                    'parameters' => $parameters->toArray(),
                    'parameters_hash' => $parameters->hash(),
                    'seed' => hash('sha256', 'local-demo-custom-lut-'.$project->id.'-variant-'.($index + 1)),
                ]);
        }
    }

    private function build(User $user, WizardProject $project): CustomLutBuild
    {
        $build = CustomLutBuild::query()
            ->where('wizard_project_id', $project->id)
            ->where('is_current', true)
            ->first();

        if ($build instanceof CustomLutBuild) {
            return $build;
        }

        return CustomLutBuild::factory()
            ->saleReady()
            ->for($user)
            ->for($project, 'wizardProject')
            ->create([
                'project_name_snapshot' => $project->name,
                'style_name_snapshot' => $project->style_name_snapshot,
                'parameters_hash' => $project->parameters_hash,
                'package_stem' => 'demo-custom-lut-'.$project->id,
            ]);
    }

    private function buildFile(CustomLutBuild $build): CustomLutBuildFile
    {
        $file = CustomLutBuildFile::query()
            ->where('custom_lut_build_id', $build->id)
            ->where('kind', 'package_zip')
            ->first();

        if ($file instanceof CustomLutBuildFile) {
            return $file;
        }

        return CustomLutBuildFile::factory()
            ->packageZip()
            ->for($build, 'customLutBuild')
            ->create([
                'path' => 'custom-lut-builds/demo/'.$build->id.'/package.zip',
                'sha256' => hash('sha256', 'demo-custom-lut-package-'.$build->id),
            ]);
    }

    private function demoUser(): User
    {
        $user = User::query()->where('email', LocalDemoUserSeeder::CustomerEmail)->first();

        if ($user instanceof User) {
            return $user;
        }

        $this->call(LocalDemoUserSeeder::class);

        return User::query()
            ->where('email', LocalDemoUserSeeder::CustomerEmail)
            ->firstOrFail();
    }

    private function ensureLocalOrTesting(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo Custom LUT data may only be seeded in local or testing environments.');
        }
    }
}
