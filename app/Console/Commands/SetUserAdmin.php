<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('users:set-admin {email} {--revoke}')]
#[Description('Grant or revoke administrator access for an existing user.')]
class SetUserAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordAuditEvent $audit): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            $this->error("No user exists for {$email}.");

            return self::FAILURE;
        }

        $user->forceFill([
            'is_admin' => ! $this->option('revoke'),
        ])->save();

        $audit->handle(
            $user->is_admin ? 'user.admin_promoted' : 'user.admin_revoked',
            actor: null,
            auditable: $user,
            targetUser: $user,
            metadata: ['source' => 'users:set-admin'],
            allowedMetadataKeys: ['source'],
        );

        $this->info($user->is_admin
            ? "Administrator access granted for {$email}."
            : "Administrator access revoked for {$email}.");

        return self::SUCCESS;
    }
}
