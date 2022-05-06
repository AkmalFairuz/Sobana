<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\encoding;

/**
 * NOTE: This class is executed in another thread.
 */

abstract class PacketDecoder{

    protected string $buffer = "";

    /**
     * Used to handle incomplete packet
     * @param string $buffer
     * @return void
     */
    protected function saveBuffer(string $buffer) {
        $this->buffer .= $buffer;
    }

    /**
     * @param string $buffer
     * @return string
     * @internal
     */
    public function doDecode(string $buffer): string{
        $in = $this->buffer . $buffer;
        $this->buffer = "";
        return $this->decode($in);
    }

    abstract protected function decode(string $buffer) : string;
}