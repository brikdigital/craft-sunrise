<?php

namespace brikdigital\sunrise\models;

use craft\base\Model;

class Settings extends Model
{
    public ?string $apiUrl = null;
    public ?string $apiKey = null;
    public ?string $merchantId = null;
    public ?string $channelId = null;

    protected function defineRules(): array
    {
        return [
            [['apiUrl', 'apiKey', 'merchantId', 'channelId'], 'required'],
        ];
    }
}
