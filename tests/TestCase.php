<?php

namespace OpenCompany\Chatogrator\Tests;

use OpenCompany\Chatogrator\ChatServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ChatServiceProvider::class,
        ];
    }
}
