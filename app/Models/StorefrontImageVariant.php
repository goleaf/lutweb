<?php

namespace App\Models;

use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageVariantRole;
use Database\Factories\StorefrontImageVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property string $id
 * @property string $imageable_type
 * @property int $imageable_id
 * @property StorefrontImageVariantRole $role
 * @property StorefrontImageFormat $format
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property int|null $quality
 * @property int $size_bytes
 * @property string $sha256
 * @property Carbon $generated_at
 */
#[Fillable([
    'imageable_type',
    'imageable_id',
    'role',
    'format',
    'disk',
    'path',
    'mime_type',
    'width',
    'height',
    'quality',
    'size_bytes',
    'sha256',
    'generated_at',
])]
#[Hidden(['disk', 'path', 'sha256', 'created_at', 'updated_at'])]
class StorefrontImageVariant extends Model
{
    /** @use HasFactory<StorefrontImageVariantFactory> */
    use HasFactory, HasUlids;

    /**
     * @return MorphTo<Model, $this>
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublicDerivative(): bool
    {
        return $this->disk === (string) config('storefront-media.public_disk', 'public')
            && str_starts_with($this->path, trim((string) config('storefront-media.public_prefix', 'storefront'), '/').'/');
    }

    public function publicUrl(): string
    {
        if (! $this->isPublicDerivative()) {
            throw new RuntimeException('Storefront image variant is not a public derivative.');
        }

        return Storage::disk((string) config('storefront-media.public_disk', 'public'))->url($this->path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => StorefrontImageVariantRole::class,
            'format' => StorefrontImageFormat::class,
            'width' => 'integer',
            'height' => 'integer',
            'quality' => 'integer',
            'size_bytes' => 'integer',
            'generated_at' => 'datetime',
        ];
    }
}
