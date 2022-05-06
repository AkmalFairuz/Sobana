<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AkmalFairuz\Sobana\encoding\PacketDecoder;
use AkmalFairuz\Sobana\encoding\PacketEncoder;
use AkmalFairuz\Sobana\utils\Signal;
use AkmalFairuz\Sobana\utils\SobanaException;
use AkmalFairuz\Sobana\utils\Utils;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;
use function explode;
use function fwrite;
use function is_subclass_of;

class ServerManager{

    /** @var resource */
    private $mainIPC;
    /** @var resource */
    private $threadIPC;
    /** @var ServerThread */
    private ServerThread $thread;
    private bool $running = false;
    /** @var ServerSession[] */
    private array $sessions = [];

    public function __construct(
        string $ip,
        int $port,
        private ?string $sessionClass = null,
        ?string $encoderClass = null,
        ?string $decoderClass = null,
    ) {
        if($sessionClass !== null && !is_subclass_of($sessionClass, ServerSession::class)) {
            throw new SobanaException("$sessionClass must extend with " . ServerSession::class . " class");
        }
        if($encoderClass !== null && !is_subclass_of($encoderClass, PacketEncoder::class)) {
            throw new SobanaException("$encoderClass must extend with " . PacketEncoder::class . " class");
        }
        if($decoderClass !== null && !is_subclass_of($decoderClass, PacketDecoder::class)) {
            throw new SobanaException("$decoderClass must extend with " . PacketDecoder::class . " class");
        }
        $sleeper = Server::getInstance()->getTickSleeper();
        $notifier = new SleeperNotifier();
        $sleeper->addNotifier($notifier, function() : void {
            $this->readInternal();
        });
        [$this->mainIPC, $this->threadIPC] = Utils::createIPCSocket();
        $this->thread = new ServerThread(Server::getInstance()->getLogger(), $this->threadIPC, $ip, $port, $notifier, $encoderClass, $decoderClass);
    }

    public function start() : void{
        if($this->running) {
            return;
        }
        $this->running = true;
        $this->thread->start();
    }

    private function notify() : void{
        if(fwrite($this->mainIPC, "\x00") === false) { // trigger socket_select
            throw new SobanaException("Could not notify main IPC socket");
        }
    }

    private function readInternal() : void{
        while(($buf = $this->thread->readInternal()) !== null) {
            $stream = new BinaryStream($buf);
            switch($stream->getByte()) {
                case Signal::OPEN:
                    $client = $stream->getInt();
                    $name = explode(":", $stream->getRemaining());
                    $ip = $name[0];
                    $port = (int) $name[1];
                    $this->openSession($client, $ip, $port);
                    break;
                case Signal::CLOSE:
                    $client = $stream->getInt();
                    $this->closeSession($client, true);
                    break;
                case Signal::READ:
                    $client = $stream->getInt();
                    $packet = $stream->getRemaining();
                    $this->handlePacket($client, $packet);
                    break;
            }
        }
    }

    public function writeExternal(string $buffer) : void{
        $this->thread->writeExternal($buffer);
        $this->notify();
    }

    private function openSession(int $id, string $ip, int $port) : void{
        $sc = $this->sessionClass ?? ServerSession::class;
        $this->sessions[$id] = new $sc($this, $id, $ip, $port);
    }

    public function closeSession(int $id, bool $closedByThread = false) : void{
        if(!isset($this->sessions[$id])) {
            return;
        }
        if(!$closedByThread) {
            $this->writeExternal(Binary::writeByte(Signal::CLOSE) . Binary::writeInt($id));
        }
        $session = $this->sessions[$id];
        $session->close($closedByThread);
        unset($this->sessions[$id]);
    }

    /**
     * @return ServerSession[]
     */
    public function getSessions(): array{
        return $this->sessions;
    }

    private function handlePacket(int $client, string $packet) : void{
        if(!isset($this->sessions[$client])) {
            return;
        }
        $session = $this->sessions[$client];
        $session->handlePacket($packet);
    }

    public function shutdown() : void{
        $this->thread->shutdown();
    }
}