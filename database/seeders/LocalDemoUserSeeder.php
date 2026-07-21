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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo users may only be seeded in local or testing environments.');
        }

        $temporaryPassword = Str::password(length: 28);

        $user = User::factory()
            ->verified()
            ->create([
                'name' => 'Temporary Local Demo Admin',
                'email' => 'demo-admin-'.Str::lower(Str::random(8)).'@example.test',
                'password' => Hash::make($temporaryPassword),
                'is_admin' => true,
            ]);

        $this->command->warn('Temporary local demo administrator created.');
        $this->command->line('Email: '.$user->email);
        $this->command->line('Password: '.$temporaryPassword);
    }
}
