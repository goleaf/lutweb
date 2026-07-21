<?php

namespace App\Services\CustomLutBuilds;

use RuntimeException;
use ZipArchive;

class CreateCustomLutPackageZip
{
    /**
     * @param  list<LocalPackageFile>  $files
     */
    public function handle(string $zipPath, array $files): void
    {
        $this->assertZipAvailable();

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create Custom LUT ZIP package.');
        }

        try {
            $seen = [];

            foreach ($files as $file) {
                $this->assertSafeEntry($file->relativePackagePath, $seen);

                if (is_link($file->localPath) || ! is_file($file->localPath)) {
                    throw new RuntimeException('Refusing to add an unsafe file to the ZIP package.');
                }

                if (! $zip->addFile($file->localPath, $file->relativePackagePath)) {
                    throw new RuntimeException('Unable to add file to ZIP package.');
                }

                if (! $zip->setMtimeName($file->relativePackagePath, 1_704_067_200)) {
                    throw new RuntimeException('Unable to set deterministic ZIP entry timestamp.');
                }
            }
        } finally {
            $zip->close();
        }

        $this->validate($zipPath, $files);
    }

    /**
     * @param  list<LocalPackageFile>  $files
     */
    public function validate(string $zipPath, array $files): int
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('Generated ZIP package is missing.');
        }

        $zipSize = filesize($zipPath);

        if ($zipSize === false || $zipSize <= 0 || $zipSize > (int) config('custom-lut-builds.maximum_package_size_bytes', 104_857_600)) {
            throw new RuntimeException('Generated ZIP package size is invalid.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Generated ZIP package cannot be reopened.');
        }

        $uncompressedSize = 0;

        try {
            $expected = array_map(static fn (LocalPackageFile $file): string => $file->relativePackagePath, $files);
            sort($expected);
            $actual = [];
            $caseFolded = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name)) {
                    throw new RuntimeException('Generated ZIP contains an unreadable entry.');
                }

                $this->assertSafeEntry($name, $caseFolded);
                $actual[] = $name;
                $stat = $zip->statName($name);
                $uncompressedSize += is_array($stat) ? (int) $stat['size'] : 0;
            }

            sort($actual);

            if ($expected !== $actual) {
                throw new RuntimeException('Generated ZIP package contains unexpected entries.');
            }

            if ($uncompressedSize > (int) config('custom-lut-builds.maximum_uncompressed_zip_size_bytes', 157_286_400)) {
                throw new RuntimeException('Generated ZIP package is too large when uncompressed.');
            }

            foreach ($files as $file) {
                $stream = $zip->getStream($file->relativePackagePath);

                if (! is_resource($stream)) {
                    throw new RuntimeException('Generated ZIP entry cannot be read.');
                }

                $context = hash_init('sha256');

                try {
                    while (! feof($stream)) {
                        hash_update($context, (string) fread($stream, 8192));
                    }
                } finally {
                    fclose($stream);
                }

                if (hash_final($context) !== $file->sha256()) {
                    throw new RuntimeException('Generated ZIP entry hash does not match metadata.');
                }
            }
        } finally {
            $zip->close();
        }

        return $uncompressedSize;
    }

    private function assertZipAvailable(): void
    {
        if (! extension_loaded('zip') || ! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required to generate Custom LUT packages.');
        }
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function assertSafeEntry(string $entry, array &$seen): void
    {
        if ($entry === '' || str_starts_with($entry, '/') || str_contains($entry, '..') || str_contains($entry, '\\')) {
            throw new RuntimeException('Generated ZIP entry path is unsafe.');
        }

        $folded = mb_strtolower($entry);

        if (isset($seen[$folded])) {
            throw new RuntimeException('Generated ZIP contains duplicate entries.');
        }

        $seen[$folded] = true;
    }
}
