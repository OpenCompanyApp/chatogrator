<?php

namespace OpenCompany\Chatogrator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \OpenCompany\Chatogrator\Chat
 */
class Chat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OpenCompany\Chatogrator\Chat::class;
    }
}
