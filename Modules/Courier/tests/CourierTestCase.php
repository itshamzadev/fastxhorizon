<?php

namespace Modules\Courier\Tests;

use Tests\TestCase;

class CourierTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!addon_published_status('Courier')) {
            $this->markTestSkipped('Courier addon is not published — activate it (Modules/Courier/Addon/info.php → is_published = 1) to run this suite.');
        }
    }
}
