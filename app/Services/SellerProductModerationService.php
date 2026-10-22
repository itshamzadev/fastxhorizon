<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;

class SellerProductModerationService
{
    public function canSell(?Seller $seller): bool
    {
        return $seller !== null && $seller->status === 'approved';
    }

    public function shouldAutoApprove(?Seller $seller): bool
    {
        return $this->canSell($seller) && (bool) $seller->auto_approve_products;
    }

    public function creationStatus(?Seller $seller): array
    {
        return [
            'status' => 0,
            'request_status' => $this->shouldAutoApprove($seller)
                ? 1
                : (getWebConfig(name: 'new_product_approval') == 1 ? 0 : 1),
        ];
    }

    public function applyCreationStatus(array $data, ?Seller $seller): array
    {
        return array_replace($data, $this->creationStatus($seller));
    }

    public function applySellerEditStatus(array $data, Product $product, ?Seller $seller): array
    {
        if ($this->shouldAutoApprove($seller) && $product->request_status === 2) {
            $data['request_status'] = 1;
        }

        return $data;
    }
}
