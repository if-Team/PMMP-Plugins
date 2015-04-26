<?php

namespace Chatty;

use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\network\protocol\MessagePacket;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\entity\Entity;
use pocketmine\utils\TextWrapper;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerChatEvent;

class Chatty extends PluginBase implements Listener {
	public $packet = [ ]; // 전역 패킷 변수
	public $packetQueue = [ ]; // 패킷 큐
	public $messages, $db; // 메시지 변수, DB 변수
	public $messageStack = [ ]; // 메시지 스택
	public $nameTag = "";
	public $m_version = 1; // 현재 메시지 버전
	const LOCAL_CHAT_DISTANCE = 50;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB 생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		$this->packet ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packet ["AddPlayerPacket"]->clientID = 0;
		$this->packet ["AddPlayerPacket"]->yaw = 0;
		$this->packet ["AddPlayerPacket"]->pitch = 0;
		$this->packet ["AddPlayerPacket"]->metadata = [ Entity::DATA_FLAGS => [ Entity::DATA_TYPE_BYTE,1 << Entity::DATA_FLAG_INVISIBLE ],Entity::DATA_AIR => [ Entity::DATA_TYPE_SHORT,300 ],Entity::DATA_SHOW_NAMETAG => [ Entity::DATA_TYPE_BYTE,1 ],Entity::DATA_NO_AI => [ Entity::DATA_TYPE_BYTE,1 ] ];
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( $this->get ( "Chatty" ), $this->get ( "Chatty" ), "Chatty.commands", $this->get ( "Chatty-command-help" ), "/" . $this->get ( "Chatty" ) );
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		for($i = 0; $i <= 35; $i ++)
			$this->nameTag .= "\n";
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new ChattyTask($this), 2 );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	public function onLogin(PlayerLoginEvent $event) {
		$name = $event->getPlayer ()->getName ();
		
		$this->messageStack [$name] = [ ];
		if (! isset ( $this->db [$name] )) $this->db [$name] = [ ];
		$this->db [$name] ["CHAT"] = true;
		$this->db [$name] ["localCHAT"] = false;
	}
	public function onQuit(PlayerQuitEvent $event) {
		unset ( $this->messageStack [$event->getPlayer ()->getName ()] );
		if (isset ( $this->packetQueue [$event->getPlayer ()->getName ()] )) unset ( $this->packetQueue [$event->getPlayer ()->getName ()] );
	}
	public function putStack($name, $message) {
		if (mb_strlen ( $message, "UTF-8" ) > 50) $message = mb_substr ( $message, 0, 50, 'utf8' );
		
		array_push ( $this->messageStack [$name], $message );
		if (count ( $this->messageStack [$name] ) > 4) array_shift ( $this->messageStack [$name] );
	}
	public function prePlayerCommand(PlayerCommandPreprocessEvent $event) {
		if (strpos ( $event->getMessage (), "/" ) === 0) {return;}
		
		$event->setCancelled ( true );
		$sender = $event->getPlayer ();
		$message = $event->getMessage ();
		
		$this->getServer ()->getPluginManager ()->callEvent ( $myEvent = new PlayerChatEvent ( $sender, $message ) );
		
		if (! $myEvent->isCancelled ()) {
			$format = $myEvent->getFormat ();
			
			$this->getLogger ()->info ( $format );
			foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
				if (isset ( $this->db [$player->getName ()] ["localCHAT"] ) and $this->db [$player->getName ()] ["localCHAT"] === true) {
					if ($sender->distance ( $player ) > self::LOCAL_CHAT_DISTANCE) {
						continue;
					}
				}
				$player->sendMessage ( $format );
			}
		}
	}
	public function onDataPacket(DataPacketSendEvent $event) {
		if ($event->getPacket () instanceof MessagePacket) {
			if ($event->getPacket ()->pid () != 0x85) return;
			if ($event->isCancelled ()) return;
			if (isset ( $this->db [$event->getPlayer ()->getName ()] ["CHAT"] )) {
				if ($this->db [$event->getPlayer ()->getName ()] ["CHAT"] == false) {
					$event->setCancelled ();
					return;
				}
			}
			
			if (isset ( $this->db [$event->getPlayer ()->getName ()] ["NameTAG"] )) {
				if ($this->db [$event->getPlayer ()->getName ()] ["NameTAG"] == true) {
					$event->setCancelled ();
					$this->putStack ( $event->getPlayer ()->getName (), $event->getPacket ()->message );
					return;
				}
			}
		}
	}
	public function Chatty() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $OnlinePlayer ) {
			if (! isset ( $this->db [$OnlinePlayer->getName ()] ["NameTAG"] )) continue;
			if (isset ( $this->db [$OnlinePlayer->getName ()] ["NameTAG"] )) {
				if ($this->db [$OnlinePlayer->getName ()] ["NameTAG"] == false) continue;
			}
			$px = round ( $OnlinePlayer->x );
			$py = round ( $OnlinePlayer->y );
			$pz = round ( $OnlinePlayer->z );
			
			if (isset ( $this->packetQueue [$OnlinePlayer->getName ()] ["eid"] )) {
				$this->packet ["RemovePlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
				$OnlinePlayer->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
			}
			if (($OnlinePlayer->pitch / 180 * M_PI) < - 0.2) continue; // 하늘을 볼경우 패킷보내지않음
			
			$allMessages = "";
			if (! isset ( $this->messageStack [$OnlinePlayer->getName ()] )) continue;
			foreach ( $this->messageStack [$OnlinePlayer->getName ()] as $message )
				$allMessages .= TextWrapper::wrap ( TextFormat::clean ( $message ) ) . "\n"; // 색상표시시 \n이 작동안됨
			
			$this->packetQueue [$OnlinePlayer->getName ()] ["x"] = round ( $px );
			$this->packetQueue [$OnlinePlayer->getName ()] ["y"] = round ( $py );
			$this->packetQueue [$OnlinePlayer->getName ()] ["z"] = round ( $pz );
			$this->packetQueue [$OnlinePlayer->getName ()] ["eid"] = Entity::$entityCount ++;
			
			$this->packet ["AddPlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
			$this->packet ["AddPlayerPacket"]->username = $this->nameTag . $allMessages;
			$this->packet ["AddPlayerPacket"]->x = $px + (- \sin ( ($OnlinePlayer->yaw / 180 * M_PI) - 0.4 )) * 7;
			$this->packet ["AddPlayerPacket"]->y = $py + 10;
			$this->packet ["AddPlayerPacket"]->z = $pz + (\cos ( ($OnlinePlayer->yaw / 180 * M_PI) - 0.4 )) * 7; // *\cos ( $OnlinePlayer->pitch / 180 * M_PI )
			$OnlinePlayer->dataPacket ( $this->packet ["AddPlayerPacket"] );
		}
	}
	public function message(CommandSender $player, $text, $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $this->get ( $text ) );
	}
	public function alert(CommandSender $player, $text, $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $this->get ( $text ) );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () ) != $this->get ( "Chatty" )) return true;
		if (! isset ( $args [0] )) {
			helpPage:
			$this->message ( $player, "help-on" );
			$this->message ( $player, "help-off" );
			$this->message ( $player, "help-local-CHAT" );
			$this->message ( $player, "help-NameTAG-CHAT" );
			return true;
		}
		
		if (! $player instanceof Player) {
			$this->alert ( $player, "onlyinGame" );
			return true;
		}
		switch ($args [0]) {
			case $this->get ( "on" ) :
				$this->db [$player->getName ()] ["CHAT"] = true;
				$this->message ( $player, "CHAT-ENABLED" );
				break;
			case $this->get ( "off" ) :
				$this->db [$player->getName ()] ["CHAT"] = false;
				$this->message ( $player, "CHAT-DISABLED" );
				break;
			case $this->get ( "local-CHAT" ) :
				if (isset ( $this->db [$player->getName ()] ["localCHAT"] )) {
					if ($this->db [$player->getName ()] ["localCHAT"] == true) {
						$this->db [$player->getName ()] ["localCHAT"] = false;
						$this->message ( $player, "localCHAT-DISABLED" );
					} else {
						$this->db [$player->getName ()] ["localCHAT"] = true;
						$this->message ( $player, "localCHAT-ENABLED" );
					}
				} else {
					$this->db [$player->getName ()] ["localCHAT"] = true;
					$this->message ( $player, "localCHAT-ENABLED" );
				}
				break;
			case $this->get ( "NameTAG-CHAT" ) :
				if (isset ( $this->db [$player->getName ()] ["NameTAG"] )) {
					if ($this->db [$player->getName ()] ["NameTAG"] == true) {
						$this->db [$player->getName ()] ["NameTAG"] = false;
						$this->message ( $player, "NameTAG-DISABLED" );
					} else {
						$this->db [$player->getName ()] ["NameTAG"] = true;
						$this->message ( $player, "NameTAG-ENABLED" );
					}
				} else {
					$packet = new MessagePacket ();
					$packet->message = "\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
					$player->directDataPacket ( $packet );
					$this->db [$player->getName ()] ["NameTAG"] = true;
					$this->message ( $player, "NameTAG-ENABLED" );
				}
				break;
			default :
				goto helpPage;
				break;
		}
		return true;
	}
}

?>