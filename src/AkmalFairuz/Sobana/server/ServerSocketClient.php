<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AkmalFairuz\Sobana\encoding\PacketDecoder;
use AkmalFairuz\Sobana\encoding\PacketEncoder;
use Throwable;
use function base64_encode;
use function explode;
use function fclose;
use function fread;
use function fwrite;
use function min;
use function stream_socket_get_name;
use function strlen;
use function substr;

class ServerSocketClient{

    private string $ip;
    private int $port;
    private bool $closed = false;

    /**
     * @param ServerSocket $serverSocket
     * @param int $id
     * @param resource $socket
     * @param PacketEncoder|null $encoder
     * @param PacketDecoder|null $decoder
     */
    public function __construct(
        private ServerSocket $serverSocket,
        private int $id,
        private $socket,
        private ?PacketEncoder $encoder = null,
        private ?PacketDecoder $decoder = null,
    ) {
        $name = explode(":", stream_socket_get_name($socket, true));
        $this->ip = $name[0];
        $this->port = (int) $name[1];
    }

    /**
     * @return int
     */
    public function getId(): int{
        return $this->id;
    }

    /**
     * @return resource
     */
    public function getSocket(){
        return $this->socket;
    }

    /**
     * @return string
     */
    public function getIp(): string{
        return $this->ip;
    }

    /**
     * @return int|string
     */
    public function getPort(): int|string{
        return $this->port;
    }

    public function write(string $buffer) {
        if($this->encoder !== null) {
            try{
                $buffer = $this->encoder->doEncode($buffer);
            }catch(Throwable $t) {
                $logger = $this->serverSocket->getLogger();
                $b64Buffer = base64_encode($buffer);
                $logger->error("Error when encoding packet: " . substr($b64Buffer, 0, min(strlen($b64Buffer), 3000)) . " from " . $this);
                $logger->logException($t);
                $this->serverSocket->closeClient($this->id);
                return;
            }
        }
        @fwrite($this->socket, $buffer);
    }

    public function read(): ?string{
        $ret = @fread($this->socket, 1024 * 1024 * 2);
        if($ret === false || $ret === "") { // client sent empty packet when disconnected.
            return null;
        }
        if($this->decoder !== null){
            try{
                $decoded = $this->decoder->doDecode($ret);
            }catch(Throwable $t) {
                $logger = $this->serverSocket->getLogger();
                $b64Buffer = base64_encode($ret);
                $logger->error("Error when decoding packet: " . substr($b64Buffer, 0, min(strlen($b64Buffer), 3000)) . " from " . $this);
                $logger->logException($t);
                $this->serverSocket->closeClient($this->id);
                return null;
            }
            return $decoded;
        }
        return $ret;
    }

    public function close() {
        if($this->closed) {
            return;
        }
        @fclose($this->socket);
        $this->closed = true;
    }

    /**
     * @return PacketEncoder|null
     */
    public function getEncoder(): ?PacketEncoder{
        return $this->encoder;
    }

    /**
     * @return PacketDecoder|null
     */
    public function getDecoder(): ?PacketDecoder{
        return $this->decoder;
    }

    public function __toString(): string{
        return "#{$this->id} $this->ip:$this->port";
    }
}