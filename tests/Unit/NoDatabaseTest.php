<?php

namespace Dominservice\Conversations\Tests\Unit;

use PHPUnit\Framework\TestCase;

class NoDatabaseTest extends TestCase
{
    /** @test */
    public function it_can_run_a_test_without_database()
    {
        $this->assertTrue(true);
    }
}