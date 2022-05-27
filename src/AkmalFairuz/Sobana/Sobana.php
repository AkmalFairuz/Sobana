<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana;

use AkmalFairuz\Sobana\server\ServerManager;
use pocketmine\plugin\PluginBase;

class Sobana{

    public static function createServer(PluginBase $plugin, string $ip, int $port, string $sessionClass = null, string $encoderClass = null, string $decoderClass = null): ServerManager{
        return new ServerManager($plugin, $ip, $port, $sessionClass, $encoderClass, $decoderClass);
    }
}