<?php

namespace EDGE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;

class EDGE extends PluginBase implements Listener {
	public $messages; // 메시지
	public $economyAPI = null; // 이코노미 API
	public $m_version = 1; // 메시지 버전 변수
	public $packet = [ ]; // 전역 패킷 변수
	public $packetQueue = [ ]; // 패킷 큐
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		$this->packet ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packet ["AddPlayerPacket"]->clientID = 0;
		$this->packet ["AddPlayerPacket"]->yaw = 0;
		$this->packet ["AddPlayerPacket"]->pitch = 0;
		$this->packet ["AddPlayerPacket"]->metadata = [ 0 => [ "type" => 0,"value" => 0 ],1 => [ "type" => 1,"value" => 0 ],16 => [ "type" => 0,"value" => 0 ],17 => [ "type" => 6,"value" => [ 0,0,0 ] ] ];
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,"EDGE" ] ), 20 );
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
	public function EDGE() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $OnlinePlayer ) {
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
			
			$nameTag = $this->get ( "serverinfo" ) . "\n" . $this->get ( "usercount" ) . count ( $this->getServer ()->getOnlinePlayers () ) . "\n" . $this->get ( "mymoney" ) . $this->economyAPI->myMoney ( $OnlinePlayer );
			$this->packet ["AddPlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
			$this->packet ["AddPlayerPacket"]->username = $nameTag;
			$this->packet ["AddPlayerPacket"]->x = $px;
			$this->packet ["AddPlayerPacket"]->y = $py - 3.2;
			$this->packet ["AddPlayerPacket"]->z = $pz + 0.4;
			$OnlinePlayer->dataPacket ( $this->packet ["AddPlayerPacket"] );
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->packetQueue [$event->getPlayer ()->getName ()] )) unset ( $this->packetQueue [$event->getPlayer ()->getName ()] );
	}
}
?>