<?php

namespace ifteam\F3;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\entity\Entity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\utils\Utils;

class F3 extends PluginBase implements Listener {
	public $packet = [ ]; // 전역 패킷 변수
	public $packetQueue = [ ]; // 패킷 큐
	public $messages, $db; // 메시지 변수, DB 변수
	public $m_version = 2; // 현재 메시지 버전
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB 생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		$this->packet ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packet ["AddPlayerPacket"]->clientID = 0;
		$this->packet ["AddPlayerPacket"]->yaw = 0;
		$this->packet ["AddPlayerPacket"]->pitch = 0;
		$this->packet ["AddPlayerPacket"]->metadata = [ 
				Entity::DATA_FLAGS => [ 
						Entity::DATA_TYPE_BYTE,
						1 << Entity::DATA_FLAG_INVISIBLE 
				],
				Entity::DATA_AIR => [ 
						Entity::DATA_TYPE_SHORT,
						20 
				],
				Entity::DATA_SHOW_NAMETAG => [ 
						Entity::DATA_TYPE_BYTE,
						1 
				] 
		];
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( $this->get ( "F3" ), $this->get ( "F3" ), "F3.commands", $this->get ( "F3-command-help" ), "/" . $this->get ( "F3" ) );
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new F3Task ( $this ), 5 );
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
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
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
	public function tick() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $OnlinePlayer ) {
			if (isset ( $this->packetQueue [$OnlinePlayer->getName ()] ["eid"] )) {
				$this->packet ["RemovePlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
				$this->packet ["RemovePlayerPacket"]->clientID = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
				$OnlinePlayer->directDataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
			}
			if (! isset ( $this->db [$OnlinePlayer->getName ()] ["NameTAG"] ))
				continue;
			if (isset ( $this->db [$OnlinePlayer->getName ()] ["NameTAG"] )) {
				if ($this->db [$OnlinePlayer->getName ()] ["NameTAG"] == false)
					continue;
			}
			$px = round ( $OnlinePlayer->x );
			$py = round ( $OnlinePlayer->y );
			$pz = round ( $OnlinePlayer->z );
			
			$mUsage = Utils::getMemoryUsage ( \true );
			$allMessages = TextFormat::WHITE . "Minecraft " . $this->getServer ()->getVersion () . "\n";
			$allMessages .= "TPS: " . $this->getServer ()->getTicksPerSecond () . " (" . $this->getServer ()->getTickUsage () . "%)\n";
			$allMessages .= "U: " . round ( $this->getServer ()->getNetwork ()->getUpload () / 1024, 2 ) . " kB/s ";
			$allMessages .= "D: " . round ( $this->getServer ()->getNetwork ()->getUpload () / 1024, 2 ) . " kB/s ";
			$allMessages .= "M: " . number_format ( round ( ($mUsage [0] / 1024) / 1024, 2 ) ) . " MB." . "\n";
			$allMessages .= "Current TPS: " . $this->getServer ()->getTicksPerSecond () . " (" . $this->getServer ()->getTickUsage () . "%)" . "\n\n";
			$allMessages .= "x:" . $OnlinePlayer->getX () . "\n";
			$allMessages .= "y:" . $OnlinePlayer->getY () . " (feet, " . $OnlinePlayer->getEyeHeight () . "eyes) " . "\n";
			$allMessages .= "z:" . $OnlinePlayer->getZ () . "\n";
			
			$this->packetQueue [$OnlinePlayer->getName ()] ["x"] = round ( $px );
			$this->packetQueue [$OnlinePlayer->getName ()] ["y"] = round ( $py );
			$this->packetQueue [$OnlinePlayer->getName ()] ["z"] = round ( $pz );
			$this->packetQueue [$OnlinePlayer->getName ()] ["eid"] = Entity::$entityCount ++;
			
			$this->packet ["AddPlayerPacket"]->eid = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
			$this->packet ["AddPlayerPacket"]->clientID = $this->packetQueue [$OnlinePlayer->getName ()] ["eid"];
			$this->packet ["AddPlayerPacket"]->username = $allMessages;
			$this->packet ["AddPlayerPacket"]->x = $px + (-\sin ( ($OnlinePlayer->yaw / 180 * M_PI) + 0.6 )) * 7;
			$this->packet ["AddPlayerPacket"]->y = $py + 1.7 + (- \sin ( $OnlinePlayer->pitch / 180 * M_PI )) * 7;
			$this->packet ["AddPlayerPacket"]->z = $pz + (\cos ( ($OnlinePlayer->yaw / 180 * M_PI) + 0.6 )) * 7; // *\cos ( $OnlinePlayer->pitch / 180 * M_PI )
			$OnlinePlayer->dataPacket ( $this->packet ["AddPlayerPacket"] );
		}
	}
	public function message(CommandSender $player, $text, $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $this->get ( $text ) );
	}
	public function alert(CommandSender $player, $text, $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $this->get ( $text ) );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () ) != $this->get ( "F3" ))
			return true;
		if (! $player instanceof Player) {
			$this->alert ( $player, "onlyinGame" );
			return true;
		}
		if (isset ( $this->db [$player->getName ()] ["NameTAG"] )) {
			if ($this->db [$player->getName ()] ["NameTAG"] == true) {
				$this->db [$player->getName ()] ["NameTAG"] = false;
				$this->message ( $player, "NameTAG-DISABLED" );
			} else {
				$this->db [$player->getName ()] ["NameTAG"] = true;
				$this->message ( $player, "NameTAG-ENABLED" );
			}
		} else {
			$this->db [$player->getName ()] ["NameTAG"] = true;
			$this->message ( $player, "NameTAG-ENABLED" );
		}
		return true;
	}
}

?>
