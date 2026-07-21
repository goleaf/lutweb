<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CustomLutDoctor extends Command
{
    protected $signature = 'custom-lut:doctor {--self-test}';

    protected $description = 'Alias for the Custom LUT Wizard backend doctor.';

    public function handle(): int
    {
        return $this->call('lut-wizard:doctor', [
            '--self-test' => (bool) $this->option('self-test'),
        ]);
    }
}
