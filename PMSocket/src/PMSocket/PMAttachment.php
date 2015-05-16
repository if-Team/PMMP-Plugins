<?php

namespace PMSocket;

use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;


class PMAttachment extends \ThreadedLoggerAttachment {
    private $adr = null, $port = null;

    public function LogIn($adr, $port) {
        $this->adr = $adr;
        $this->port = $port;
    }

    public function LogOut($adr, $port) {

    }

    public final function call($level, $message) {
        $this->log($level, $message);
        if ($this->attachment instanceof \ThreadedLoggerAttachment) {
            $this->attachment->call($level, $message);
        }
        if (!($this->adr == null && $this->port == null)) CPAPI::sendPacket(new DataPacket($this->adr, $this->port, $message));
    }
}