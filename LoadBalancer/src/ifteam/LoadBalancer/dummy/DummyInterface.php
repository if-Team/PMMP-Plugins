<?php

namespace ifteam\LoadBalancer\dummy;

use pocketmine\network\SourceInterface;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\network\protocol\DataPacket;

class DummyInterface implements SourceInterface {
	private $server;
	private $sessions;
	private $ackStore;
	private $replyStore;
	public function __construct(Server $server) {
		$this->server = $server;
		$this->sessions = new \SplObjectStorage ();
		$this->ackStore = [ ];
		$this->replyStore = [ ];
	}
	public function close(Player $player, $reason = "unknown reason") {
		$this->sessions->detach ( $player );
		unset ( $this->ackStore [$player->getName ()] );
		unset ( $this->replyStore [$player->getName ()] );
	}
	public function openSession($username, $address = "LOADBALANCER", $port = 0) {
		if (! isset ( $this->replyStore [$username] )) {
			$player = new DummyPlayer ( $this, null, $address, $port );
			$player->setName ( $username );
			$this->sessions->attach ( $player, $username );
			$this->ackStore [$username] = [ ];
			$this->replyStore [$username] = [ ];
			$this->server->addPlayer ( $username, $player );
			return $player;
		} else {
			return false;
		}
	}
	public function putPacket(Player $player, DataPacket $packet, $needACK = \false, $immediate = \true) {
		return true;
	}
	public function process() {
		return true;
	}
	public function setName($name) {
		return true;
	}
	public function shutdown() {
		return true;
	}
	public function emergencyShutdown() {
		return true;
	}
}

?>