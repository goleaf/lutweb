<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LocalDemoUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public const AdminEmail = 'demo-admin@example.test';

    public const CustomerEmail = 'demo-customer@example.test';

    public const Password = 'local-demo-passphrase';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo users may only be seeded in local or testing environments.');
        }

        foreach ($this->accounts() as $account) {
            $this->upsertDemoUser($account);
        }

        $this->command->warn('Stable local demo accounts are ready.');
        $this->command->line('Customer email: '.self::CustomerEmail);
        $this->command->line('Admin email: '.self::AdminEmail);
        $this->command->line('Password: '.self::Password);
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
    private function upsertDemoUser(array $account): User
    {
        $user = User::query()
            ->where('email', $account['email'])
            ->first();

        $attributes = [
            'name' => $account['name'],
            'email' => $account['email'],
            'country_code' => 'US',
            'password' => Hash::make(self::Password),
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
