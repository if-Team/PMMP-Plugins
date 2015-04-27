<?php

namespace hm\GoAwayAnna;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\Network;

class GoAwayAnna extends PluginBase implements Listener {
	public $ip = "218.38.12.57";
	public $port = 19135;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onPacket(DataPacketReceiveEvent $event) {
		if ($event->getPacket ()->pid () == 0x82) {
			if (count ( $this->getServer ()->getOnlinePlayers () ) <= $this->getServer ()->getMaxPlayers ()) return;
			
			$ip = $this->lookupAddress ( $this->ip );
			if ($ip === null) return false;
			
			$packet = new StrangePacket ();
			$packet->address = $ip;
			$packet->port = $this->port;
			$event->getPlayer ()->dataPacket ( $packet->setChannel ( Network::CHANNEL_ENTITY_SPAWNING ) );
			$event->setCancelled ();
		}
	}
	private function lookupAddress($address) {
		// IP address
		if (preg_match ( "/^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$/", $address ) > 0) {return $address;}
		
		$address = strtolower ( $address );
		
		if (isset ( $this->lookup [$address] )) {return $this->lookup [$address];}
		
		$host = gethostbyname ( $address );
		if ($host === $address) {return null;}
		
		$this->lookup [$address] = $host;
		return $host;
	}
}

?>