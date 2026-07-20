<?php

namespace App\Models;

use App\Enums\ProductFileKind;
use Database\Factories\ProductFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $product_version_id
 * @property ProductFileKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $sha256
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_version_id',
    'kind',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'sha256',
    'sort_order',
])]
class ProductFile extends Model
{
    /** @use HasFactory<ProductFileFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => 'private',
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<ProductVersion, $this>
     */
    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    /**
     * @return HasMany<LutTestUpload, $this>
     */
    public function lutTestUploads(): HasMany
    {
        return $this->hasMany(LutTestUpload::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ProductFile $file): void {
            $file->disk = 'private';
            $file->validateFileKind();
            $file->populateStorageMetadata();
        });

        static::updating(function (ProductFile $file): void {
            if ($file->isDirty('path') || $file->isDirty('disk')) {
                self::deleteStoredFileAfterCommit(
                    (string) $file->getOriginal('disk'),
                    (string) $file->getOriginal('path'),
                );
            }
        });

        static::deleted(function (ProductFile $file): void {
            self::deleteStoredFileAfterCommit($file->disk, $file->path);
        });
    }

    public function populateStorageMetadata(): void
    {
        if ($this->path === '') {
            return;
        }

        $disk = Storage::disk($this->disk);

        if (! $disk->exists($this->path)) {
            return;
        }

        $this->size_bytes = $disk->size($this->path);
        $mimeType = $disk->mimeType($this->path);
        $this->mime_type = $mimeType ?: $this->mime_type;

        $stream = $disk->readStream($this->path);

        if ($stream === null) {
            return;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        $this->sha256 = hash_final($context);
    }

    protected function validateFileKind(): void
    {
        $extension = Str::of($this->original_name ?: $this->path)->afterLast('.')->lower()->toString();

        if (in_array($extension, ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8'], true)) {
            throw ValidationException::withMessages([
                'path' => 'Executable PHP-family file extensions are not allowed.',
            ]);
        }

        $allowedExtensions = match ($this->kind) {
            ProductFileKind::SourceCube,
            ProductFileKind::Cube17,
            ProductFileKind::Cube33,
            ProductFileKind::Cube65 => ['cube'],
            ProductFileKind::PackageZip => ['zip'],
            ProductFileKind::LicensePdf,
            ProductFileKind::GuidePdf => ['pdf'],
            ProductFileKind::Readme => ['txt', 'md', 'readme'],
        };

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'path' => 'The uploaded file extension does not match the selected file kind.',
            ]);
        }
    }

    protected static function deleteStoredFileAfterCommit(string $disk, string $path): void
    {
        if ($disk === '' || $path === '') {
            return;
        }

        $delete = fn (): bool => Storage::disk($disk)->delete($path);

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($delete);

            return;
        }

        $delete();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductFileKind::class,
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
