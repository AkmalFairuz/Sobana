<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana;

use AkmalFairuz\Sobana\server\ServerManager;

class Sobana{

    public static function createServer(string $ip, int $port, string $sessionClass = null, string $encoderClass = null, string $decoderClass = null): ServerManager{
        return new ServerManager($ip, $port, $sessionClass, $encoderClass, $decoderClass);
    }
}