<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AttachableThreadedLogger;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\Thread;
use Threaded;
use function gc_enable;

class ServerThread extends Thread{

    private bool $shutdown = false;
    private Threaded $internal;
    private Threaded $external;

    /**
     * @param AttachableThreadedLogger $logger
     * @param resource $ipc
     * @param string $ip
     * @param int $port
     * @param SleeperNotifier $notifier
     * @param string $encoderClass
     * @param string $decoderClass
     */
    public function __construct(
        private AttachableThreadedLogger $logger,
        private $ipc,
        private string $ip,
        private int $port,
        private SleeperNotifier $notifier,
        private string $encoderClass,
        private string $decoderClass
    ){
        $this->internal = new Threaded();
        $this->external = new Threaded();
    }

    public function onRun(): void{
        gc_enable();
        $socket = new ServerSocket($this->logger, $this, $this->ip, $this->port, $this->ipc, $this->notifier, $this->encoderClass, $this->decoderClass);
        while(!$this->shutdown) {
            $socket->tick();
        }
        $socket->close();
    }

    public function writeInternal(string $buffer) : void{
        $this->synchronized(function() use ($buffer) : void {
            $this->internal[] = $buffer;
        });
    }

    public function readInternal() : ?string {
        return $this->synchronized(function() : ?string {
            return $this->internal->shift();
        });
    }

    public function writeExternal(string $buffer) : void{
        $this->synchronized(function() use ($buffer) : void {
            $this->external[] = $buffer;
        });
    }

    public function readExternal() : ?string {
        return $this->synchronized(function() : ?string {
            return $this->external->shift();
        });
    }

    public function shutdown() : void{
        $this->shutdown = true;
        @fwrite($this->ipc, "\x00");
    }

    public function quit(): void{
        $this->shutdown();
        parent::quit();
    }

    public function getThreadName(): string{
        return "Sobana";
    }
}