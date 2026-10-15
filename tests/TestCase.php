<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views call @vite; tests must not depend on a built manifest (public/build is git-ignored).
        $this->withoutVite();
    }
}
