<?php

namespace ifteam\TAGBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Level;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerQuitEvent;
use ifteam\TAGBlock\task\TAGBlockTask;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;

class TAGBlock extends PluginBase implements Listener {
	public $messages, $db, $temp;
	public $packet = [ ]; // 전역 패킷 변수
	public $m_version = 3; // 현재 메시지 버전
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
		$this->packet ["AddPlayerPacket"]->slim =\false;
		$this->packet ["AddPlayerPacket"]->skin =\str_repeat ( "\x00", 64 * 32 * 4 );
		$this->packet ["AddPlayerPacket"]->metadata = [ Entity::DATA_FLAGS => [ Entity::DATA_TYPE_BYTE,1 << Entity::DATA_FLAG_INVISIBLE ] ]; // [ Entity::DATA_FLAGS => [ Entity::DATA_TYPE_BYTE,1 << Entity::DATA_FLAG_INVISIBLE ],Entity::DATA_AIR => [ Entity::DATA_TYPE_SHORT,300 ],Entity::DATA_SHOW_NAMETAG => [ Entity::DATA_TYPE_BYTE,1 ],Entity::DATA_NO_AI => [ Entity::DATA_TYPE_BYTE,1 ] ];
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( "tagblock", "tagblock.add", $this->get ( "TAGBlock-description" ), $this->get ( "TAGBlock-command-help" ) );
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new TAGBlockTask ( $this ), 60 );
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
		if (! $event->getPlayer ()->hasPermission ( "tagblock.add" )) return;
		if (strtolower ( $event->getLine ( 0 ) ) != $this->get ( "TAGBlock-line0" )) return;
		
		if ($event->getLine ( 1 ) != null) $message = $event->getLine ( 1 );
		if ($event->getLine ( 2 ) != null) $message .= "\n" . $event->getLine ( 2 );
		if ($event->getLine ( 3 ) != null) $message .= "\n" . $event->getLine ( 3 );
		
		$block = $event->getBlock ()->getSide ( 0 );
		$blockPos = "{$block->x}.{$block->y}.{$block->z}";
		
		$this->db ["TAGBlock"] [$block->level->getFolderName ()] [$blockPos] = $message;
		$this->message ( $event->getPlayer (), $this->get ( "TAGBlock-added" ) );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case "tagblock" :
				if (! isset ( $args [4] ) or ! is_numeric ( $args [1] ) or ! is_numeric ( $args [2] ) or ! is_numeric ( $args [3] )) {
					$this->message ( $player, $this->get ( "TAGBlock-command-help" ) );
					return true;
				}
				$level = $this->getServer ()->getLevelByName ( $args [0] );
				if (! $level instanceof Level) {
					$this->message ( $player, $this->get ( "TAGBlock-level-doesnt-exist" ) );
					return true;
				}
				$blockPos = "{$args [1]}.{$args [2]}.{$args [3]}";
				
				$message = $args;
				array_shift ( $message );
				array_shift ( $message );
				array_shift ( $message );
				array_shift ( $message );
				$message = implode ( " ", $message );
				
				$lines = explode ( "\\n", $message );
				$message = "";
				foreach ( $lines as $line )
					$message .= $line . "\n";
				
				$this->db ["TAGBlock"] [$level->getFolderName ()] [$blockPos] = $message;
				$this->message ( $player, $this->get ( "TAGBlock-added" ) );
				break;
		}
		return true;
	}
	public function BlockBreak(BlockBreakEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "tagblock.add" )) return;
		
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
						$this->packet ["RemovePlayerPacket"]->clientID = $this->temp [$player->getName ()] ["nametag"] [$tagPos];
						$player->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
						unset ( $this->temp [$player->getName ()] ["nametag"] [$tagPos] );
					}
				} else {
					// 반경 25블럭 내일경우 생성패킷 전송 후 생성패킷큐에 추가
					if (isset ( $this->temp [$player->getName ()] ["nametag"] [$tagPos] )) continue;
					
					// 유저 패킷을 상점밑에 보내서 네임택 출력
					$this->temp [$player->getName ()] ["nametag"] [$tagPos] = Entity::$entityCount ++;
					$this->packet ["AddPlayerPacket"]->eid = $this->temp [$player->getName ()] ["nametag"] [$tagPos];
					$this->packet ["AddPlayerPacket"]->clientID = $this->temp [$player->getName ()] ["nametag"] [$tagPos];
					$this->packet ["AddPlayerPacket"]->username = $message;
					$this->packet ["AddPlayerPacket"]->x = $explodePos [0] + 0.4;
					$this->packet ["AddPlayerPacket"]->y = $explodePos [1] - 1.6;
					$this->packet ["AddPlayerPacket"]->z = $explodePos [2] + 0.4;
					$player->dataPacket ( $this->packet ["AddPlayerPacket"] );
				}
			}
		}
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		if (isset ( $this->messages [$lang . "-" . $var] )) {
			return $this->messages [$lang . "-" . $var];
		} else {
			return $lang . "-" . $var;
		}
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
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
	public function message(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>