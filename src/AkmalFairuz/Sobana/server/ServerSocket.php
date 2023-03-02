<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AkmalFairuz\Sobana\utils\Signal;
use AkmalFairuz\Sobana\utils\SobanaException;
use AttachableThreadedLogger;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\BinaryStream;
use function fclose;
use function fread;
use function gc_collect_cycles;
use function gc_enable;
use function gc_mem_caches;
use function socket_last_error;
use function socket_strerror;
use function stream_select;
use function stream_set_blocking;
use function stream_socket_accept;
use function stream_socket_server;
use function stream_socket_shutdown;
use const STREAM_SHUT_RDWR;

class ServerSocket{

    /** @var resource */
    private $socket;
    private int $nextClientId = 1;
    /** @var ServerSocketClient[] */
    private array $clients = [];
    /** @var resource[] */
    private array $clientSockets = [];

    /**
     * @param AttachableThreadedLogger $logger
     * @param ServerThread $thread
     * @param string $ip
     * @param int $port
     * @param resource $ipc
     * @param SleeperNotifier $notifier
     * @param string|null $encoderClass
     * @param string|null $decoderClass
     */
    public function __construct(
        private AttachableThreadedLogger $logger,
        private ServerThread $thread,
        private string $ip,
        private int $port,
        private $ipc,
        private SleeperNotifier $notifier,
        private ?string $encoderClass = null,
        private ?string $decoderClass = null,
    ){
        if(($socket = stream_socket_server("tcp://$ip:$port")) === false) {
            throw new SobanaException("Could not create server socket $ip:$port. Reason:" . socket_strerror(socket_last_error()));
        }
        stream_set_blocking($socket, false);
        $this->socket = $socket;
        $this->logger->info("Created Sobana Server $ip:$port");
    }

    public function tick() : void{
        $this->readExternal();

        $read = $this->clientSockets;
        $read[0] = $this->socket;
        $read[-1] = $this->ipc;
        $write = null;
        $except = null;
        if(@stream_select($read, $write, $except, 10, 0) > 0) {
            foreach($read as $k => $socket){
                switch($k) {
                    case -1: // IPC
                        @fread($socket, 65535);
                        break;
                    case 0: // Server
                        if(($client = @stream_socket_accept($socket)) !== false) {
                            $this->addClient($client);
                        }
                        break;
                    default: // Client
                        $client = $this->clients[$k];
                        $packet = $client->read();
                        if($packet === null) {
                            $this->closeClient($k, true);
                            break;
                        }
                        if($packet === "") { // decoder: incomplete packet
                            break;
                        }
                        $this->writeInternal(chr(Signal::READ) . pack("N", $k) . $packet);
                        break;
                }
            }
        }
    }

    /**
     * @param resource $client
     * @return void
     */
    private function addClient($client) : void{
        stream_set_blocking($client, false);
        $this->nextClientId++;

        if(($ec = $this->encoderClass) !== null) {
            $encoder = new $ec();
        }else{
            $encoder = null;
        }
        if(($dc = $this->decoderClass) !== null) {
            $decoder = new $dc();
        }else{
            $decoder = null;
        }

        $this->clientSockets[$this->nextClientId] = $client;
        $this->clients[$this->nextClientId] = $c = new ServerSocketClient($this, $this->nextClientId, $client, $encoder, $decoder);
        $this->logger->debug("New connection " . $c);
        $this->writeInternal(chr(Signal::OPEN) . pack("N", $c->getId()) . $c->getIp() . ":" . $c->getPort());
    }

    public function closeClient(int $id, bool $closedByThread = false) : void{
        if(!isset($this->clients[$id])) {
            return;
        }
        if($closedByThread){
            $this->writeInternal(chr(Signal::CLOSE) . pack("N", $id));
        }
        $client = $this->clients[$id];
        $this->logger->debug("Closed connection " . $client);
        $client->close();
        unset($this->clientSockets[$id]);
        unset($this->clients[$id]);
    }

    private function readExternal() : void{
        while(($buf = $this->thread->readExternal()) !== null) {
            $stream = new BinaryStream($buf);
            switch($stream->getByte()) {
                case Signal::WRITE:
                    $id = $stream->getInt();
                    $packet = $stream->getRemaining();
                    if(isset($this->clients[$id])){
                        $this->clients[$id]->write($packet);
                    }
                    break;
                case Signal::CLOSE:
                    $id = $stream->getInt();
                    $this->closeClient($id);
                    break;
                case Signal::GARBAGE_COLLECTOR:
                    gc_enable();
                    gc_collect_cycles();
                    gc_mem_caches();
                    break;
            }
        }
    }

    private function writeInternal(string $buffer) : void{
        $this->thread->writeInternal($buffer);
        $this->notifier->wakeupSleeper();
    }

    public function close() {
        foreach($this->clients as $client) {
            $socket = $client->getSocket();
            if($socket !== null) {
                @fclose($client->getSocket());
            }
        }
        $this->clients = [];
        if($this->socket !== null) {
            @stream_set_blocking($this->socket, true);
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            @fclose($this->socket);
        }
        unset($this->ipc);
        unset($this->socket);
    }

    /**
     * @return AttachableThreadedLogger
     */
    public function getLogger(): AttachableThreadedLogger{
        return $this->logger;
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
}