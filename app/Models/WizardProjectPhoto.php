<?php

namespace App\Models;

use App\Enums\WizardPhotoStatus;
use Database\Factories\WizardProjectPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $wizard_project_id
 * @property WizardPhotoStatus $status
 * @property string $disk
 * @property string|null $raw_path
 * @property string|null $preview_path
 * @property string $original_name
 * @property string $original_mime_type
 * @property int $original_size_bytes
 * @property int $original_width
 * @property int $original_height
 * @property string|null $preview_mime_type
 * @property int|null $preview_width
 * @property int|null $preview_height
 * @property int $sort_order
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id',
    'wizard_project_id',
    'status',
    'disk',
    'raw_path',
    'preview_path',
    'original_name',
    'original_mime_type',
    'original_size_bytes',
    'original_width',
    'original_height',
    'preview_mime_type',
    'preview_width',
    'preview_height',
    'sort_order',
    'failure_code',
    'failure_message',
    'expires_at',
    'completed_at',
])]
#[Hidden([
    'disk',
    'raw_path',
    'preview_path',
])]
class WizardProjectPhoto extends Model
{
    /** @use HasFactory<WizardProjectPhotoFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'disk' => 'private',
    ];

    /**
     * @return BelongsTo<WizardProject, $this>
     */
    public function wizardProject(): BelongsTo
    {
        return $this->belongsTo(WizardProject::class);
    }

    public function isReady(): bool
    {
        return $this->status === WizardPhotoStatus::Ready && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === WizardPhotoStatus::Expired || $this->expires_at->lessThanOrEqualTo(now());
    }

    public function mayBeViewedBy(User $user): bool
    {
        $project = $this->wizardProject;

        return $project instanceof WizardProject
            && $project->belongsToUser($user)
            && ! $user->is_suspended
            && $this->isReady();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WizardPhotoStatus::class,
            'original_size_bytes' => 'integer',
            'original_width' => 'integer',
            'original_height' => 'integer',
            'preview_width' => 'integer',
            'preview_height' => 'integer',
            'sort_order' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
