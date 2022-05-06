<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\server;

use AkmalFairuz\Sobana\encoding\PacketDecoder;
use AkmalFairuz\Sobana\encoding\PacketEncoder;
use function explode;
use function fclose;
use function fread;
use function fwrite;
use function stream_socket_get_name;

class ServerSocketClient{

    private string $ip;
    private int $port;

    /**
     * @param int $id
     * @param resource $socket
     * @param PacketEncoder|null $encoder
     * @param PacketDecoder|null $decoder
     */
    public function __construct(
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
        $buffer = $this->encoder->doEncode($buffer);
        @fwrite($this->socket, $buffer);
    }

    public function read(): ?string{
        $ret = @fread($this->socket, 65535);
        if($ret === false || $ret === "") { // client sent empty packet when disconnected.
            return null;
        }
        return $this->decoder->doDecode($ret);
    }

    public function close() {
        @fclose($this->socket);
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
}