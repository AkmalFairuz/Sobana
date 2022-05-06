<?php

declare(strict_types=1);

namespace AkmalFairuz\Sobana\encoding;

abstract class PacketEncoder{

    /**
     * @param string $buffer
     * @return string
     * @internal
     */
    public function doEncode(string $buffer)  : string{
        return $this->encode($buffer);
    }

    abstract protected function encode(string $buffer) : string;
}