<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PartnerProgramContractTest extends TestCase
{
    public function test_both_partner_programs_have_safe_configuration_defaults(): void
    {
        $programs = config('fastxhorizon.partner_programs');
        $this->assertSame(['reseller', 'affiliate'], array_keys($programs));
        $this->assertFalse($programs['reseller']['enabled']);
        $this->assertFalse($programs['affiliate']['enabled']);
        $this->assertSame('percentage', $programs['reseller']['commission_type']);
    }

    public function test_partner_bootstrap_command_defaults_to_a_non_mutating_dry_run(): void
    {
        $this->assertSame(0, Artisan::call('fastxhorizon:sync-partner-programs'));
        $this->assertStringContainsString('Dry run only', Artisan::output());
    }
}
