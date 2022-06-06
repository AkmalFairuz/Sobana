<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AkmalFairuz\Sobana\utils\Signal;
use function chr;
use function count;
use function pack;

class ServerSession{

    protected bool $closed = false;
    /** @var string[] */
    private array $writeBuffer = [];

    public function __construct(
        protected ServerManager $serverManager,
        private int $id,
        private string $ip,
        private int $port
    ) {
        $this->onConnect();
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

    public function onConnect() : void{}

    /**
     * @return int
     */
    public function getPort(): int{
        return $this->port;
    }

    public function write(string $buffer) : void{
        $this->writeBuffer[] = $buffer;
    }

    public function writeAndFlush(string $buffer) {
        $this->write($buffer);
        $this->flush();
    }

    public function flush() {
        if(count($this->writeBuffer) > 0){
            foreach($this->writeBuffer as $buffer){
                $this->serverManager->writeExternal(chr(Signal::WRITE) . pack("N", $this->id) . $buffer, false);
            }
            $this->serverManager->notify();
            $this->writeBuffer = [];
        }
    }

    final public function close(bool $closedByThread = false) : void{
        if($this->closed) {
            return;
        }
        $this->closed = true;
        $this->onClose();
        if(!$closedByThread) {
            $this->serverManager->closeSession($this->id);
        }
    }

    public function onClose() : void{}

    public function handlePacket(string $packet) : void{
    }
}