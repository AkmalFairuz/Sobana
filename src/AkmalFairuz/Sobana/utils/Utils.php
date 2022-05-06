<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\utils;

use pocketmine\utils\Utils as PMUtils;
use function stream_set_blocking;
use function stream_socket_pair;
use const STREAM_IPPROTO_IP;
use const STREAM_PF_INET;
use const STREAM_PF_UNIX;
use const STREAM_SOCK_STREAM;

class Utils{

    public static function createIPCSocket() : array {
        if(($sockets = stream_socket_pair(PMUtils::getOS() === PMUtils::OS_WINDOWS ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            throw new SobanaException("Could not create IPC socket. Reason: ".socket_strerror(socket_last_error()));
        }
        foreach($sockets as $socket) {
            stream_set_blocking($socket, false);
        }
        return $sockets;
    }
}