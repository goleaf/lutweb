<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WizardProject;
use App\Services\LutWizard\WizardProjectPresenter;
use Inertia\Inertia;
use Inertia\Response;

class CustomLutController extends Controller
{
    public function index(WizardProjectPresenter $presenter): Response
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $projects = WizardProject::query()
            ->select([
                'id',
                'user_id',
                'name',
                'status',
                'style_name_snapshot',
                'parameters_hash',
                'expires_at',
                'created_at',
                'updated_at',
            ])
            ->whereBelongsTo($user)
            ->nonExpired()
            ->withCount([
                'photos' => fn ($query) => $query
                    ->where('expires_at', '>', now())
                    ->whereNot('status', 'expired'),
            ])
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Account/CustomLuts/Index', [
            'projects' => $presenter->accountProjects($projects),
        ]);
    }
}
