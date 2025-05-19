<?php

namespace App\Services;

class Chatgpt
{
    public static $client;

    public static function client()
    {
        if (!self::$client) {
            self::$client = \OpenAI::factory()->withApiKey('aa-1SJFUC4oMqtykK767qbRUY0SdLJGEpNBfvzZKItDJOxWsbwR')->withBaseUri('https://api.avalai.ir/v1')->make();
        }
        return self::$client;
    }

}
