<?php

namespace App\Services\CustomLutBuilds;

use Illuminate\Support\Str;

class PackageNameGenerator
{
    /**
     * @var list<string>
     */
    private const WINDOWS_RESERVED_NAMES = [
        'con',
        'prn',
        'aux',
        'nul',
        'com1',
        'com2',
        'com3',
        'com4',
        'com5',
        'com6',
        'com7',
        'com8',
        'com9',
        'lpt1',
        'lpt2',
        'lpt3',
        'lpt4',
        'lpt5',
        'lpt6',
        'lpt7',
        'lpt8',
        'lpt9',
    ];

    public function make(string $displayName, string $buildId): PackageName
    {
        $stem = Str::of($displayName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[\x00-\x1F\x7F\/\\\\:*\?"<>\|]+/', ' ')
            ->replaceMatches('/(^|[\/\\\\])\.+($|[\/\\\\])/', ' ')
            ->slug('-')
            ->trim('-_. ')
            ->limit(60, '')
            ->trim('-_. ')
            ->toString();

        if ($stem === '' || in_array($stem, self::WINDOWS_RESERVED_NAMES, true)) {
            $stem = 'custom-lut-'.strtolower(substr($buildId, -10));
        }

        if (in_array($stem, self::WINDOWS_RESERVED_NAMES, true)) {
            $stem = 'custom-lut-'.$stem;
        }

        return new PackageName($displayName, $stem);
    }
}
