<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AkmalFairuz\Sobana\utils\Signal;
use pocketmine\utils\Binary;

class ServerSession{

    public function __construct(
        private ServerManager $serverManager,
        private int $id,
        private string $ip,
        private int $port
    ) {
    }

    /**
     * @return int
     */
    public function getId(): int{
        return $this->id;
    }

    /**
     * @return string
     */
    public function getIp(): string{
        return $this->ip;
    }

    /**
     * @return int
     */
    public function getPort(): int{
        return $this->port;
    }

    public function write(string $buffer) : void{
        $this->serverManager->writeExternal(Binary::writeByte(Signal::WRITE) . Binary::writeInt($this->id) . $buffer);
    }

    final public function close(bool $closedByThread = false) : void{
        $this->onClose();
        if(!$closedByThread) {
            $this->serverManager->closeSession($this->id);
            return;
        }
    }

    public function onClose() : void{}

    public function handlePacket(string $packet) : void{
    }
}