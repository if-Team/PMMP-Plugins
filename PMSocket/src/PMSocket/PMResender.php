<?php

namespace PMSocket;

use pocketmine\event\Listener;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;

class PMResender implements Listener {
	private $adr = null, $port = null;
	public function __construct() {
		echo "PMResender called\n";
	}
	public function stream($level, $message) {
		echo "PONG! " . $level . $message . "\n";
	}
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = explode ( " ", $ev->getPacket ()->data );
		switch ($data [0]) {
			case "connect" :
				if ($this->adr == null && $this->port == null) {
					$this->adr = $ev->getPacket ()->address;
					$this->port = $ev->getPacket ()->port;
					$this->att->LogIn ( $this->adr, $this->port );
					$this->getLogger ()->info ( "Connected in " . $this->adr . ":" . $this->port );
				} else {
					$this->getLogger ()->info ( "Tried to Connect in " . $this->adr . ":" . $this->port );
					CPAPI::sendPacket ( new DataPacket ( $this->adr, $this->port, "cantconnect" ) );
				}
				break;
		}
	}
}

?>