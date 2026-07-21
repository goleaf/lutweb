<?php

namespace App\Models;

use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use Carbon\CarbonInterface;
use Database\Factories\PackageDocumentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property PackageDocumentKind $kind
 * @property PackageDocumentStatus $status
 * @property string $version
 * @property string $title
 * @property string $body
 * @property bool $is_current
 * @property CarbonInterface|null $activated_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
#[Fillable([
    'id',
    'kind',
    'status',
    'version',
    'title',
    'body',
    'is_current',
    'activated_at',
])]
class PackageDocumentTemplate extends Model
{
    /** @use HasFactory<PackageDocumentTemplateFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'is_current' => false,
    ];

    public function isDraft(): bool
    {
        return $this->status === PackageDocumentStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === PackageDocumentStatus::Active;
    }

    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    public function contentHash(): string
    {
        return hash('sha256', $this->kind->value."\n".$this->version."\n".$this->title."\n".$this->body);
    }

    public function mayBeUsedForReviewBuild(): bool
    {
        return $this->isCurrent()
            && ($this->isActive() || ((bool) config('custom-lut-builds.allow_draft_documents') && $this->isDraft()))
            && ! $this->trashed();
    }

    public function mayBeUsedForSaleBuild(): bool
    {
        return $this->isCurrent()
            && $this->isActive()
            && ! Str::startsWith($this->version, 'draft-')
            && ! $this->trashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PackageDocumentKind::class,
            'status' => PackageDocumentStatus::class,
            'is_current' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }
}
