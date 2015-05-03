<?php

namespace ifteam\EDGE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;

class EDGE extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $messages, $db; // 메시지
	public $economyAPI = null; // 이코노미 API
	public $m_version = 1; // 메시지 버전 변수
	public $packet = [ ]; // 전역 패킷 변수
	public $packetQueue = [ ]; // 패킷 큐
	public $specialLineQueue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ "Format" => "%info%\n%online%\n%mymoney%" ] ))->getAll ();
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		if (self::$instance == null) self::$instance = $this;
		
		$this->specialLineQueue ["all"] = [ ];
		
		$this->packet ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packet ["AddPlayerPacket"]->clientID = 0;
		$this->packet ["AddPlayerPacket"]->yaw = 0;
		$this->packet ["AddPlayerPacket"]->pitch = 0;
		$this->packet ["AddPlayerPacket"]->item = 0;
		$this->packet ["AddPlayerPacket"]->meta = 0;
		$this->packet ["AddPlayerPacket"]->slim =\false;
		$this->packet ["AddPlayerPacket"]->skin =\str_repeat ( "\x00", 64 * 32 * 4 );
		$this->packet ["AddPlayerPacket"]->metadata = [ Entity::DATA_FLAGS => [ Entity::DATA_TYPE_BYTE,1 << Entity::DATA_FLAG_INVISIBLE ],Entity::DATA_AIR => [ Entity::DATA_TYPE_SHORT,300 ],Entity::DATA_SHOW_NAMETAG => [ Entity::DATA_TYPE_BYTE,1 ],Entity::DATA_NO_AI => [ Entity::DATA_TYPE_BYTE,1 ] ];
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new EDGETask ( $this ), 20 );
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
	// ----------------------------------------------------------------------------------
	public static function getInstance() {
		return static::$instance;
	}
	public function getFormat() {
		// 포멧을 가져옵니다
		return $this->db ["Format"];
	}
	public function setFormat($input) {
		// 포멧을 설정합니다
		return $this->db ["Format"] = $input;
	}
	public function getSpecialLines(Player $player = null) {
		// 추가된 라인들을 가져옵니다
		if ($player == null) {
			// 모든유저들에게 추가된 스페셜라인
			if (isset ( $this->specialLineQueue ["all"] )) {
				return $this->specialLineQueue ["all"];
			} else {
				return false;
			}
		} else {
			// 해당유저가 착용중인 스페셜라인
			if (isset ( $this->specialLineQueue ["player"] [$player->getName ()] )) {
				return [ $this->specialLineQueue ["all"],$this->specialLineQueue ["player"] [$player->getName ()] ];
			} else {
				return false;
			}
		}
	}
	public function addSpecialLine(Player $player = null, $text) {
		// 라인을 추가합니다
		if ($player == null) {
			// 모든유저들에게 스페셜라인 추가
			$this->specialLineQueue ["all"] [] = $text;
		} else {
			// 해당유저에게만 스페셜라인 추가
			$this->specialLineQueue ["player"] [$player->getName ()] [] = $text;
		}
	}
	public function deleteSpecialLine(Player $player = null, $text) {
		// 스페셜 라인을 가져옵니다
		if ($player == null) {
			// 모든유저들에게 스페셜라인 삭제
			foreach ( $this->specialLineQueue ["all"] as $index => $queue )
				if ($queue == $text) {
					unset ( $this->specialLineQueue ["all"] [$index] );
					break;
				}
		} else {
			// 해당유저에게만 스페셜라인 삭제
			foreach ( $this->specialLineQueue ["player"] [$player->getName ()] as $index => $queue )
				if ($queue == $text) {
					unset ( $this->specialLineQueue ["player"] [$player->getName ()] [$index] );
					break;
				}
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		$this->specialLineQueue ["player"] [$event->getPlayer ()->getName ()] = [ ];
	}
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->specialLineQueue ["player"] [$event->getPlayer ()->getName ()] )) unset ( $this->specialLineQueue ["player"] [$event->getPlayer ()->getName ()] );
		if (isset ( $this->packetQueue [$event->getPlayer ()->getName ()] )) unset ( $this->packetQueue [$event->getPlayer ()->getName ()] );
	}
	// ----------------------------------------------------------------------------------
	public function EDGE() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $OnlinePlayer ) {
			if (! $OnlinePlayer->hasPermission ( "edge.showingnametag" )) continue;
			$px = round ( $OnlinePlayer->x );
			$py = round ( $OnlinePlayer->y );
			$pz = round ( $OnlinePlayer->z );
			$down1 = $OnlinePlayer->getLevel ()->getBlockIdAt ( $px, $py - 1, $pz );
			$down2 = $OnlinePlayer->getLevel ()->getBlockIdAt ( $px, $py - 2, $pz );
			if ($down1 == Block::AIR or $down1 == Block::GLASS or $down1 == Block::WATER) continue;
			if ($down2 == Block::AIR or $down2 == Block::GLASS or $down2 == Block::WATER) continue;
			
			if (isset ( $this->packetQueue [$OnlinePlayer->getName ()] ["eid"] )) {
				// TODO 이전 네임택제거부
				$this->packet ["RemovePlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
				$OnlinePlayer->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
			}
			
			$this->packetQueue [$OnlinePlayer->getName ()] ["x"] = round ( $px );
			$this->packetQueue [$OnlinePlayer->getName ()] ["y"] = round ( $py );
			$this->packetQueue [$OnlinePlayer->getName ()] ["z"] = round ( $pz );
			$this->packetQueue [$OnlinePlayer->getName ()] ["eid"] = Entity::$entityCount ++;
			
			$format = str_replace ( "%info%", $this->get ( "serverinfo" ), $this->db ["Format"] );
			$format = str_replace ( "%online%", $this->get ( "usercount" ) . count ( $this->getServer ()->getOnlinePlayers () ), $format );
			$format = str_replace ( "%mymoney%", $this->get ( "mymoney" ) . $this->economyAPI->myMoney ( $OnlinePlayer ), $format );
			
			foreach ( $this->specialLineQueue ["all"] as $queue )
				$format .= "\n" . $queue;
			
			if (isset ( $this->specialLineQueue ["player"] [$OnlinePlayer->getName ()] )) {
				foreach ( $this->specialLineQueue ["player"] [$OnlinePlayer->getName ()] as $queue )
					$format .= "\n" . $queue;
			}
			
			$this->packet ["AddPlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
			$this->packet ["AddPlayerPacket"]->username = $format;
			$this->packet ["AddPlayerPacket"]->x = $px;
			$this->packet ["AddPlayerPacket"]->y = $py - 3.2;
			$this->packet ["AddPlayerPacket"]->z = $pz + 0.4;
			$OnlinePlayer->dataPacket ( $this->packet ["AddPlayerPacket"] );
		}
	}
}
?>