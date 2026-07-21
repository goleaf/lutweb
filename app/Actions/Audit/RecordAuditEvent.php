<?php

namespace App\Actions\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $allowedMetadataKeys
     */
    public function handle(
        string $action,
        ?User $actor = null,
        ?Model $auditable = null,
        ?User $targetUser = null,
        array $metadata = [],
        array $allowedMetadataKeys = [],
        ?Request $request = null,
    ): AuditEvent {
        if (! in_array($action, $this->allowedActions(), true)) {
            throw new InvalidArgumentException('The audit action is not allowlisted.');
        }

        $request ??= request();
        $safeMetadata = Arr::only($metadata, $allowedMetadataKeys);

        foreach ($safeMetadata as $key => $value) {
            if ($this->looksSensitive($key) || (is_string($value) && $this->looksSensitive($value))) {
                unset($safeMetadata[$key]);
            }
        }

        return AuditEvent::query()->create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey() !== null ? (string) $auditable->getKey() : null,
            'target_user_id' => $targetUser?->id,
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => $safeMetadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedActions(): array
    {
        return [
            'user.admin_promoted',
            'user.admin_revoked',
            'user.suspended',
            'user.restored',
            'product.published',
            'product.unpublished',
            'product.archived',
            'product.price_changed',
            'product.package_zip_changed',
            'custom_lut.price_changed',
            'custom_lut.commerce_toggled',
            'legal.document_activated',
            'entitlement.revoked',
            'entitlement.restored',
            'paypal.payment_rechecked',
            'custom_lut.build_integrity_checked',
            'settings.production_changed',
        ];
    }

    private function looksSensitive(string $value): bool
    {
        return Str::of($value)->lower()->contains([
            'password',
            'secret',
            'token',
            'authorization',
            'cookie',
            'private_path',
            'path',
            'payload',
            'app_key',
            'client_secret',
        ]);
    }
}
