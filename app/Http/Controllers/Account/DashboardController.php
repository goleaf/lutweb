<?php

namespace App\Http\Controllers\Account;

use App\Enums\DigitalAssetKind;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\User;
use App\Models\WizardProject;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $recentProject = WizardProject::query()
            ->select([
                'id',
                'user_id',
                'name',
                'updated_at',
                'expires_at',
            ])
            ->where('user_id', $user->id)
            ->nonExpired()
            ->latest('updated_at')
            ->first();

        return Inertia::render('Dashboard', [
            'counts' => [
                'ready_made_luts' => Entitlement::query()
                    ->where('user_id', $user->id)
                    ->where('digital_asset_kind', DigitalAssetKind::CatalogProduct->value)
                    ->active()
                    ->count(),
                'purchased_custom_luts' => Entitlement::query()
                    ->where('user_id', $user->id)
                    ->where('digital_asset_kind', DigitalAssetKind::CustomLutBuild->value)
                    ->active()
                    ->count(),
                'active_custom_lut_drafts' => WizardProject::query()
                    ->where('user_id', $user->id)
                    ->nonExpired()
                    ->count(),
            ],
            'recent_custom_lut_project' => $recentProject === null ? null : [
                'name' => $recentProject->name,
                'updated_at' => $recentProject->updated_at?->toISOString(),
                'continue_url' => route('custom-lut.show', $recentProject),
            ],
        ]);
    }
}
