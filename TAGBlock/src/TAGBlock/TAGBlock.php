<?php

namespace TAGBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerQuitEvent;

class TAGBlock extends PluginBase implements Listener {
	public $messages, $db, $temp;
	public $packet = [ ]; // 전역 패킷 변수
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->db = (new Config ( $this->getDataFolder () . "TAG_DB.yml", Config::YAML ))->getAll ();
		
		$this->packet ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packet ["AddPlayerPacket"]->clientID = 0;
		$this->packet ["AddPlayerPacket"]->yaw = 0;
		$this->packet ["AddPlayerPacket"]->pitch = 0;
		$this->packet ["AddPlayerPacket"]->item = 0;
		$this->packet ["AddPlayerPacket"]->meta = 0;
		$this->packet ["AddPlayerPacket"]->metadata = [ 0 => [ "type" => 0,"value" => 0 ],1 => [ "type" => 1,"value" => 0 ],16 => [ "type" => 0,"value" => 0 ],17 => [ "type" => 6,"value" => [ 0,0,0 ] ] ];
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,"TAGBlock" ] ), 20 );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "TAG_DB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->temp [$event->getPlayer ()->getName ()] )) unset ( $this->temp [$event->getPlayer ()->getName ()] );
	}
	public function SignChange(SignChangeEvent $event) {
		if (! $event->getPlayer ()->isOp ()) return;
		if (strtolower ( $event->getLine ( 0 ) ) != $this->get ( "TAGBlock-line0" )) return;
		
		if ($event->getLine ( 1 ) != null) $message = $event->getLine ( 1 );
		if ($event->getLine ( 2 ) != null) $message .= "\n" . $event->getLine ( 2 );
		if ($event->getLine ( 3 ) != null) $message .= "\n" . $event->getLine ( 3 );
		
		$block = $event->getBlock ()->getSide ( 0 );
		$blockPos = "{$block->x}.{$block->y}.{$block->z}";
		
		$this->db ["TAGBlock"] [$block->level->getFolderName ()] [$blockPos] = $message;
		$block->level->setBlock ( $block->getSide ( 1 ), Block::get ( Block::AIR ) );
		
		$this->message ( $event->getPlayer (), $this->get ( "TAGBlock-added" ) );
	}
	public function BlockBreak(BlockBreakEvent $event) {
		if (! $event->getPlayer ()->isOp ()) return;
		
		$block = $event->getBlock ();
		$blockPos = "{$block->x}.{$block->y}.{$block->z}";
		
		if (! isset ( $this->db ["TAGBlock"] [$block->level->getFolderName ()] [$blockPos] )) return;
		
		if (isset ( $this->temp [$event->getPlayer ()->getName ()] ["nametag"] [$blockPos] )) {
			$this->packet ["RemovePlayerPacket"]->eid = $this->temp [$event->getPlayer ()->getName ()] ["nametag"] [$blockPos];
			$event->getPlayer ()->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
		}
		
		unset ( $this->db ["TAGBlock"] [$block->level->getFolderName ()] [$blockPos] );
		$this->message ( $event->getPlayer (), $this->get ( "TAGBlock-deleted" ) );
	}
	public function TAGBlock() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if (! isset ( $this->db ["TAGBlock"] [$player->level->getFolderName ()] )) continue;
			foreach ( $this->db ["TAGBlock"] [$player->level->getFolderName ()] as $tagPos => $message ) {
				$explodePos = explode ( ".", $tagPos );
				if (! isset ( $explodePos [2] )) continue;
				
				$dx = abs ( $explodePos [0] - $player->x );
				$dy = abs ( $explodePos [1] - $player->y );
				$dz = abs ( $explodePos [2] - $player->z );
				
				if (! ($dx <= 25 and $dy <= 25 and $dz <= 25)) {
					// 반경 25블럭을 넘어갔을경우 생성해제 패킷 전송후 생성패킷큐를 제거
					if (isset ( $this->temp [$player->getName ()] ["nametag"] [$tagPos] )) {
						$this->packet ["RemovePlayerPacket"]->eid = $this->temp [$player->getName ()] ["nametag"] [$tagPos];
						$player->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
						unset ( $this->temp [$player->getName ()] ["nametag"] [$tagPos] );
					}
				} else {
					// 반경 25블럭 내일경우 생성패킷 전송 후 생성패킷큐에 추가
					if (isset ( $this->temp [$player->getName ()] ["nametag"] [$tagPos] )) continue;
					
					// 유저 패킷을 상점밑에 보내서 네임택 출력
					$this->temp [$player->getName ()] ["nametag"] [$tagPos] = Entity::$entityCount ++;
					$this->packet ["AddPlayerPacket"]->eid = $this->temp [$player->getName ()] ["nametag"] [$tagPos];
					$this->packet ["AddPlayerPacket"]->username = $message;
					$this->packet ["AddPlayerPacket"]->x = $explodePos [0] + 0.4;
					$this->packet ["AddPlayerPacket"]->y = $explodePos [1] - 1.2;
					$this->packet ["AddPlayerPacket"]->z = $explodePos [2] + 0.4;
					$player->dataPacket ( $this->packet ["AddPlayerPacket"] );
				}
			}
		}
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>