<?php

namespace EchoChat;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerChatEvent;

class EchoChat extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 1; // 현재 메시지 버전
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		// 커스텀 패킷 이용
		// 마스터모드시 커스텀패킷 없어도 사용가능하게끔
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) === null) {
			$this->getServer ()->getLogger ()->critical ( "[CustomPacket Example] CustomPacket plugin was not found. This plugin will be disabled." );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return;
		}
		$this->registerCommand ( $this->get ( "commands-use" ), "EchoChat.commands", $this->get ( "commands-use-desc" ), $this->get ( "commands-use-usage" ) );
		
		// 플러그인의 인스턴스 정의
		if (self::$instance == null) self::$instance = $this;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public static function getInstance() {
		return static::$instance;
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
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! isset ( $args [0] )) {
			$this->message ( $player, $this->get ( "help1" ) );
			$this->message ( $player, $this->get ( "help2" ) );
			$this->message ( $player, $this->get ( "help3" ) );
			$this->message ( $player, $this->get ( "help4" ) );
			return true;
		}
		switch ($args [0]) {
			case "add" : // 1 아이피 2 포트명
				if (! isset ( $args [2] )) {
					$this->message ( $player, $this->get ( "help1" ) );
					$this->message ( $player, $this->get ( "help2" ) );
					$this->message ( $player, $this->get ( "help3" ) );
					$this->message ( $player, $this->get ( "help4" ) );
					return true;
				}
				$ip = explode ( ".", $args [1] );
				if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
					$this->message ( $sender, $this->get ( "wrong-ip" ) );
					return;
				}
				if (! is_numeric ( $args [2] ) or $args [2] <= 30 or $args [2] >= 65535) {
					$this->message ( $sender, $this->get ( "wrong-port" ) );
					return;
				}
				
				$this->db ["echoserver"] [$args [1] . ":" . $args [2]] = 0;
				$this->message ( $player, $this->get ( "complete" ) );
				break;
			case "del" : // 1 아이피 2 포트명
				if (! isset ( $args [2] )) {
					$this->message ( $player, $this->get ( "help1" ) );
					$this->message ( $player, $this->get ( "help2" ) );
					$this->message ( $player, $this->get ( "help3" ) );
					$this->message ( $player, $this->get ( "help4" ) );
					return true;
				}
				$ip = explode ( ".", $args [1] );
				if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
					$this->message ( $sender, $this->get ( "wrong-ip" ) );
					return;
				}
				if (! is_numeric ( $args [2] ) or $args [2] <= 30 or $args [2] >= 65535) {
					$this->message ( $sender, $this->get ( "wrong-port" ) );
					return;
				}
				if (isset ( $this->db ["echoserver"] [$args [1] . ":" . $args [2]] )) {
					unset ( $this->db ["echoserver"] [$args [1] . ":" . $args [2]] );
					$this->message ( $player, $this->get ( "complete" ) );
				} else {
					$this->message ( $player, $this->get ( "not-exist" ) );
				}
				break;
			case "setname" : // 임플로드 후 이름처리
				if (! isset ( $args [1] )) {
					$this->message ( $player, $this->get ( "help1" ) );
					$this->message ( $player, $this->get ( "help2" ) );
					$this->message ( $player, $this->get ( "help3" ) );
					$this->message ( $player, $this->get ( "help4" ) );
					return true;
				}
				array_shift ( $args );
				$this->db ["name"] = implode ( " ", $args );
				$this->message ( $player, $this->get ( "complete" ) );
				break;
			case "setpass" : // 임플로드 후 암호처리
				if (! isset ( $args [1] )) {
					$this->message ( $player, $this->get ( "help1" ) );
					$this->message ( $player, $this->get ( "help2" ) );
					$this->message ( $player, $this->get ( "help3" ) );
					$this->message ( $player, $this->get ( "help4" ) );
					return true;
				}
				array_shift ( $args );
				$this->db ["pass"] = implode ( " ", $args );
				$this->message ( $player, $this->get ( "complete" ) );
				break;
		}
		return true;
	}
	public function onChat(PlayerChatEvent $event) {
		if ($event->isCancelled ()) return;
		if (! isset ( $this->db ["name"] )) return;
		if (! isset ( $this->db ["pass"] )) return;
		
		$message = $this->getServer ()->getLanguage ()->translateString ( $event->getFormat (), [ $event->getPlayer ()->getDisplayName (),$event->getMessage () ] );
		$send = [ $this->db ["pass"],TextFormat::GOLD . "[ " . $this->db ["name"] . " ] " . TextFormat::WHITE . $message ]; // 0-pass, 1-chat
		if (isset ( $this->db ["echoserver"] )) foreach ( $this->db ["echoserver"] as $index => $data ) {
			//echo $index . "로 패킷전송을 시도합니다 (" . $send [1] . ")\n";
			$address = explode ( ":", $index ); // 0-ip, 1-port
			CPAPI::sendPacket ( new DataPacket ( $address [0], $address [1], json_encode ( $send ) ) );
		}
	}
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		//echo "패킷받음\n";
		if (! isset ( $this->db ["name"] )) return;
		if (! isset ( $this->db ["pass"] )) return;
		$data = json_decode ( $ev->getPacket ()->data );
		if (! is_array ( $data )) {
			//echo "[테스트] 어레이가 아닌 정보 전달됨\n";
			$ev->getPacket ()->printDump ();
			return;
		}
		if ($data [0] != $this->db ["pass"]) {
			//echo "[테스트] 패스코드가 다른 정보 전달됨\n";
			var_dump ( $data [0] );
			return;
		}
		$this->getServer ()->broadcastMessage ( $data [1] );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
}

?>