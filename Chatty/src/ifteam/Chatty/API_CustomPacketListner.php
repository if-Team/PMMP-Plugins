<?php

namespace ifteam\Chatty;

use pocketmine\event\Listener;
use pocketmine\event\Event;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use ifteam\Chatty\dummy\DummyPlayer;
use pocketmine\Player;

class API_CustomPacketListner implements Listener {
	private $plugin;
	public $customPacketAvailable = false;
	public $dummyPlayer;
	public function __construct(Chatty $plugin) {
		$this->plugin = $plugin;
		$this->customPacketAvailable = $this->plugin->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) !== null;
		$this->dummyPlayer = $this->plugin->dummyInterface->openSession ( "CHATTY" );
		if ($this->customPacketAvailable) {
			$this->plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
		}
	}
	public function onPacketReceive(CustomPacketReceiveEvent $event) {
		if (! $this->plugin->getConfig ()->get ( "echo-enabled", false )) {
			return;
		}
		// $data[0] "passcode" => $passcode,
		// $data[1] "name" => $myName,
		// $data[2] "message" => $message
		$passcode = $this->plugin->getConfig ()->get ( "echo-passcode", null );
		if ($passcode === null) {
			return;
		}
		$data = json_decode ( $event->getPacket ()->data );
		if (! is_array ( $data ) or ! isset ( $data [0] )) {
			return;
		}
		if ($passcode !== $data [0]) {
			return;
		}
		$this->broadcastMessage ( "[" . $data [1] . "] " . $data [2], $data [1], $data [1] );
	}
	public function sendRedistribution(Event $event, $message) {
		if ($this->customPacketAvailable and $this->plugin->getConfig ()->get ( "echo-enabled", false )) {
			if ($event instanceof PlayerCommandPreprocessEvent) {
				if ($event->getPlayer ()->closed)
					return;
			}
			$passcode = $this->plugin->getConfig ()->get ( "echo-passcode", null );
			if ($passcode === null) {
				return;
			}
			
			$myName = $this->plugin->getConfig ()->get ( "echo-my-name", "MAIN" );
			$data = json_encode ( [ 
					$passcode,
					$myName,
					$message 
			] );
			foreach ( $this->plugin->getConfig ()->get ( "echo-recipients", [ ] ) as $recipient ) {
				$address = explode ( ":", $recipient );
				CPAPI::sendPacket ( new DataPacket ( $address [0], $address [1], $data ) );
			}
		}
	}
	public function broadcastMessage($message, $sender = null) {
		// Event Fishing (*like a Gentleman)
		if ($sender != null) {
			if ($this->dummyPlayer != false) {
				$event = new PlayerCommandPreprocessEvent ( $this->dummyPlayer, $message );
				$this->plugin->getServer ()->getPluginManager ()->callEvent ( $event );
				if ($event->isCancelled ()) {
					return;
				}
				$event = new PlayerChatEvent ( $this->dummyPlayer, $message );
				$this->plugin->getServer ()->getPluginManager ()->callEvent ( $event );
				if ($event->isCancelled ()) {
					return;
				}
			}
		}
		$this->plugin->getLogger ()->info ( $message );
		foreach ( $this->plugin->getServer ()->getOnlinePlayers () as $player ) {
			if (isset ( $this->plugin->db [$player->getName ()] ["local-chat"] ) and $this->plugin->db [$player->getName ()] ["local-chat"] == true) {
				if ($sender === null or $sender->distance ( $player ) > self::LOCAL_CHAT_DISTANCE) {
					if (! isset ( explode ( $player->getName (), $message )[1] ))
						continue;
				}
			}
			$player->sendMessage ( $message );
		}
	}
	public function onChat(PlayerChatEvent $event) {
		if ($event->getPlayer () instanceof DummyPlayer) {
			$event->setCancelled ();
			$this->plugin->broadcastMessage ( $event->getMessage () );
		}
	}
}
?>