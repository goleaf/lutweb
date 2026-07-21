<?php

namespace App\Models;

use App\Enums\CustomLutBuildFileKind;
use Database\Factories\CustomLutBuildFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $custom_lut_build_id
 * @property CustomLutBuildFileKind $kind
 * @property string $disk
 * @property string $path
 * @property string|null $relative_package_path
 * @property string|null $safe_download_name
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property string|null $sha256
 * @property int $sort_order
 */
#[Fillable([
    'id',
    'custom_lut_build_id',
    'kind',
    'disk',
    'path',
    'relative_package_path',
    'safe_download_name',
    'original_name',
    'mime_type',
    'size_bytes',
    'sha256',
    'sort_order',
])]
#[Hidden([
    'disk',
    'path',
])]
class CustomLutBuildFile extends Model
{
    /** @use HasFactory<CustomLutBuildFileFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<CustomLutBuild, $this>
     */
    public function customLutBuild(): BelongsTo
    {
        return $this->belongsTo(CustomLutBuild::class);
    }

    public function isPackageZip(): bool
    {
        return $this->kind === CustomLutBuildFileKind::PackageZip;
    }

    public function isPackage(): bool
    {
        return $this->isPackageZip();
    }

    public function existsOnPrivateStorage(): bool
    {
        return $this->disk === (string) config('custom-lut-builds.private_disk', 'private')
            && Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CustomLutBuildFileKind::class,
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
