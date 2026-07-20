<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->users() as $attributes) {
            $user = User::query()->firstOrNew([
                'email' => $attributes['email'],
            ]);

            $user->forceFill([
                'name' => $attributes['name'],
                'country_code' => 'US',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
                'terms_version' => config('legal.terms_version'),
                'privacy_version' => config('legal.privacy_version'),
                'is_admin' => $attributes['is_admin'],
            ])->save();
        }
    }

    /**
     * @return list<array{name: string, email: string, is_admin: bool}>
     */
    private function users(): array
    {
        return [
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'is_admin' => true,
            ],
            [
                'name' => 'Client',
                'email' => 'user@example.com',
                'is_admin' => false,
            ],
        ];
    }
}
