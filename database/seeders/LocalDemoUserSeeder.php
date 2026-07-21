<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class LocalDemoUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public const AdminEmail = 'demo-admin@example.test';

    public const CustomerEmail = 'demo-customer@example.test';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo users may only be seeded in local or testing environments.');
        }

        $temporaryPassword = Str::password(length: 32, spaces: false);

        foreach ($this->accounts() as $account) {
            $this->upsertDemoUser($account, $temporaryPassword);
        }

        $this->command->warn('Temporary local demo accounts are ready. Do not use them for production launch data.');
        $this->command->line('Customer email: '.self::CustomerEmail);
        $this->command->line('Admin email: '.self::AdminEmail);
        $this->command->line('Temporary password: '.$temporaryPassword);
    }

    /**
     * @return array<int, array{name: string, email: string, is_admin: bool}>
     */
    private function accounts(): array
    {
        return [
            [
                'name' => 'Local Demo Customer',
                'email' => self::CustomerEmail,
                'is_admin' => false,
            ],
            [
                'name' => 'Local Demo Admin',
                'email' => self::AdminEmail,
                'is_admin' => true,
            ],
        ];
    }

    /**
     * @param  array{name: string, email: string, is_admin: bool}  $account
     */
    private function upsertDemoUser(array $account, string $temporaryPassword): User
    {
        $user = User::query()
            ->where('email', $account['email'])
            ->first();

        $attributes = [
            'name' => $account['name'],
            'email' => $account['email'],
            'country_code' => 'US',
            'password' => Hash::make($temporaryPassword),
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'terms_version' => config('legal.terms_version'),
            'privacy_version' => config('legal.privacy_version'),
            'is_admin' => $account['is_admin'],
            'is_suspended' => false,
        ];

        if (! $user instanceof User) {
            return User::factory()
                ->verified()
                ->create($attributes);
        }

        $user->fill($attributes)->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user->refresh();
    }
}
