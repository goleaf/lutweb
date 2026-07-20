<?php

namespace App\Console\Commands;

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
    public function handle(): int
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

        $this->info($user->is_admin
            ? "Administrator access granted for {$email}."
            : "Administrator access revoked for {$email}.");

        return self::SUCCESS;
    }
}
