<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Seller;
use App\Services\SellerProductModerationService;
use PHPUnit\Framework\TestCase;

class SellerProductModerationServiceTest extends TestCase
{
    public function test_verification_and_auto_approval_are_independent(): void
    {
        $service = new SellerProductModerationService();
        $seller = new Seller();
        $seller->status = 'approved';
        $seller->is_verified = false;
        $seller->auto_approve_products = true;

        self::assertTrue($service->canSell($seller));
        self::assertTrue($service->shouldAutoApprove($seller));
        self::assertFalse((bool) $seller->is_verified);
    }

    public function test_unapproved_seller_cannot_auto_approve(): void
    {
        $service = new SellerProductModerationService();
        $seller = new Seller();
        $seller->status = 'pending';
        $seller->auto_approve_products = true;

        self::assertFalse($service->canSell($seller));
        self::assertFalse($service->shouldAutoApprove($seller));
    }

    public function test_auto_approval_releases_a_denied_edit_without_changing_ownership(): void
    {
        $service = new SellerProductModerationService();
        $seller = new Seller();
        $seller->status = 'approved';
        $seller->auto_approve_products = true;

        $product = new Product();
        $product->request_status = 2;
        $product->user_id = 42;
        $product->added_by = 'seller';

        $result = $service->applySellerEditStatus([], $product, $seller);

        self::assertSame(1, $result['request_status']);
        self::assertSame(42, $product->user_id);
        self::assertSame('seller', $product->added_by);
    }
}
