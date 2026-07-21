<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\DownloadEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DownloadHistoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $downloads = DownloadEvent::query()
            ->with(['order.item'])
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Account/Downloads/Index', [
            'downloads' => [
                'data' => $downloads->getCollection()->map(fn (DownloadEvent $event): array => [
                    'id' => $event->id,
                    'product_name' => $event->order?->item?->product_name ?? 'Purchased LUT',
                    'order_number' => $event->order?->number,
                    'started_at' => $event->started_at?->toISOString(),
                    'completed_at' => $event->completed_at?->toISOString(),
                    'failed_at' => $event->failed_at?->toISOString(),
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'ip_address' => $this->maskIp($event->ip_address),
                    'device' => Str::limit((string) $event->user_agent, 80, ''),
                ])->values(),
                'meta' => $downloads->toArray(),
            ],
        ]);
    }

    private function maskIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        return Str::limit($ip, 12, '...');
    }
}
