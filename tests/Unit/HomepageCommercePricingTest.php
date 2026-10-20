<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HomepageCommercePricingTest extends TestCase
{
    public function test_platform_funded_deal_is_separated_from_seller_discount(): void
    {
        $deal = (object) [
            'status' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ];
        $dealProduct = (object) [
            'flash_deal_id' => 9,
            'discount' => 20,
            'discount_type' => 'percent',
            'funding_type' => 'platform',
            'flashDeal' => $deal,
        ];

        $pricing = getProductPricingBreakdown([
            'unit_price' => 1000,
            'discount' => 0,
            'discount_type' => 'percent',
            'flashDealProducts' => [$dealProduct],
        ]);

        $this->assertSame(200.0, $pricing['platform_discount']);
        $this->assertSame(0.0, $pricing['seller_discount']);
        $this->assertSame(800.0, $pricing['current_price']);
        $this->assertSame('platform', $pricing['flash_deal_funding']);
    }

    public function test_deal_discount_is_capped_at_the_remaining_price(): void
    {
        $deal = (object) [
            'status' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ];
        $dealProduct = (object) [
            'flash_deal_id' => 10,
            'discount' => 5000,
            'discount_type' => 'flat',
            'funding_type' => 'seller',
            'flashDeal' => $deal,
        ];

        $pricing = getProductPricingBreakdown([
            'unit_price' => 1000,
            'discount' => 0,
            'discount_type' => 'percent',
            'flashDealProducts' => [$dealProduct],
        ]);

        $this->assertSame(1000.0, $pricing['total_discount']);
        $this->assertEquals(0.0, $pricing['current_price']);
    }
}
