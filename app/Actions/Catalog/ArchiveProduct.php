<?php

namespace App\Actions\Catalog;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

class ArchiveProduct
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Product $product): Product
    {
        $product->forceFill([
            'status' => ProductStatus::Archived,
        ])->save();

        $product = $product->refresh();
        $actor = request()->user();
        $this->audit->handle('product.archived', actor: $actor instanceof User ? $actor : null, auditable: $product);

        return $product;
    }
}
