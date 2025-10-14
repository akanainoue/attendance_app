<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ja');
    }
}

// protected function setUp(): void
// {
//     parent::setUp();
//     app()->setLocale('ja');
// }