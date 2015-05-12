<?php

namespace ifteam\LoadBalancer;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\server\ServerCommandEvent;
use ifteam\LoadBalancer\task\LoadBalancerTask;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\Network;

class LoadBalancer extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 1; // 현재 메시지 버전
	public $updateList = [ ];
	public $callback;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage ();
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		if (self::$instance == null) self::$instance = $this;
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) === null) {
			$this->getServer ()->getLogger ()->critical ( "[CustomPacket Example] CustomPacket plugin was not found. This plugin will be disabled." );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return;
		}
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		if (! isset ( $this->db ["mode"] )) {
			$this->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->get ( "please-choose-mode" ) );
		} else {
			$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new LoadBalancerTask ( $this ), 20 );
		}
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function tick() {
		if ($this->db ["mode"] == "master") {
			$allPlayerList = [ ];
			$allMax = 0;
			foreach ( $this->updateList as $ipport => $data ) {
				// CHECK TIMEOUT
				$progress = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) ) - $this->updateList [$ipport] ["lastcontact"];
				if ($progress > 4) {
					unset ( $this->updateList [$ipport] );
					continue;
				}
				// RECALCULATING PLAYER LIST
				foreach ( $this->updateList [$ipport] ["list"] as $player ) {
					$allPlayerList [] = $player;
				}
				// RECALCULATING MAX LIST
				$allMax += $this->updateList [$ipport] ["max"];
			}
			// TODO REALLY PREVIEW PLAYER LIST
			$motd = $this->getServer ()->getConfigString ( "motd", "Minecraft: PE Server" );
			$this->getServer ()->getNetwork ()->setName ( $motd . " (" . count ( $allPlayerList ) . "/" . $allMax . ")" );
		} else if ($this->db ["mode"] == "slave") {
			$playerlist = [ ];
			foreach ( $this->getServer ()->getOnlinePlayers () as $player )
				$playerlist [] = $player->getName ();
			CPAPI::sendPacket ( new DataPacket ( $this->db ["master-ip"], $this->db ["master-port"], json_encode ( [ $this->db ["passcode"],$playerlist,$this->getServer ()->getMaxPlayers (),$this->getServer ()->getPort () ] ) ) );
		}
	}
	public function onDataPacketReceived(DataPacketReceiveEvent $event) {
		if (isset ( $this->db ["mode"] )) if ($this->db ["mode"] == "master") {
			if ($event->getPacket ()->pid () == 0x82) {
				$priority = [ "list" => - 1 ];
				foreach ( $this->updateList as $ipport => $data ) {
					if ($priority ["list"] < $data ["list"]) {
						if (count ( $data ["list"] ) >= $data ["max"]) {
							echo "THAT SERVER IS OVERFLOW\n";
							continue;
						}
						$priority ["ip"] = explode ( ":", $ipport )[0];
						$priority ["port"] = $this->updateList [$ipport] ["port"];
						$priority ["list"] = $this->updateList [$ipport] ["list"];
						echo "SERVER FOUND!";
					}
				}
				if ($priority ["list"] == - 1) {
					// NO EXTRA SERVER
					echo "NO EXTRA SERVER\n";
					$event->setCancelled ();
					return true;
				}
				echo "전송테스트시작 * IP:" . $priority ["ip"] . " PORT:" . $priority ["port"] . "\n";
				$event->getPlayer ()->dataPacket ( (new StrangePacket ( $priority ["ip"], $priority ["port"] ))->setChannel ( Network::CHANNEL_ENTITY_SPAWNING ) );
				$event->setCancelled ();
				return true;
			}
		}
		return false;
	}
	public function serverCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		$sender = $event->getSender ();
		if (! isset ( $this->db ["mode"] )) { // 서버모드 선택
			switch (strtolower ( $command )) {
				case "master" : // master
					$this->db ["mode"] = $command;
					$this->message ( $sender, $this->get ( "master-mode-selected" ) );
					$this->message ( $sender, $this->get ( "please-choose-passcode" ) );
					break;
				case "slave" : // slave
					$this->db ["mode"] = $command;
					$this->message ( $sender, $this->get ( "slave-mode-selected" ) );
					$this->message ( $sender, $this->get ( "please-choose-passcode" ) );
					break;
				default :
					$this->message ( $sender, $this->get ( "please-choose-mode" ) );
					break;
			}
			$event->setCancelled ();
			return;
		}
		if (! isset ( $this->db ["passcode"] )) { // 통신보안 암호 입력
			if (mb_strlen ( $command, "UTF-8" ) < 8) {
				$this->message ( $sender, $this->get ( "too-short-passcode" ) );
				$this->message ( $sender, $this->get ( "please-choose-passcode" ) );
				$event->setCancelled ();
				return;
			}
			$this->db ["passcode"] = $command;
			$this->message ( $sender, $this->get ( "passcode-selected" ) );
			$this->message ( $sender, $this->get ( "please-choose-port" ) );
			$event->setCancelled ();
			return;
		}
		if (! isset ( $this->db ["port"] )) { // 서버 통신 포트 설정
			if (! is_numeric ( $command ) or $command <= 30 or $command >= 65535) {
				$this->message ( $sender, $this->get ( "wrong-port" ) );
				$event->setCancelled ();
				return;
			}
			$this->db ["port"] = $command;
			$this->message ( $sender, $this->get ( "port-selected" ) );
			if ($this->db ["mode"] == "slave") {
				$this->message ( $sender, $this->get ( "please-type-master-ip" ) );
			} else {
				$this->message ( $sender, $this->get ( "all-setup-complete" ) );
				$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new LoadBalancerTask ( $this ), 20 );
			}
			$event->setCancelled ();
			return;
		}
		if ($this->db ["mode"] == "slave") { // 슬레이브 모드일 경우
			if (! isset ( $this->db ["master-ip"] )) { // 마스터서버 아이피 입력
				$ip = explode ( ".", $command );
				if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
					$this->message ( $sender, $this->get ( "wrong-ip" ) );
					$this->message ( $sender, $this->get ( "please-type-master-ip" ) );
					$event->setCancelled ();
					return;
				}
				$this->db ["master-ip"] = $command;
				$this->message ( $sender, $this->get ( "master-ip-selected" ) );
				$this->message ( $sender, $this->get ( "please-type-master-port" ) );
				$event->setCancelled ();
				return;
			}
			if (! isset ( $this->db ["master-port"] )) { // 마스터서버 포트 입력
				if (! is_numeric ( $command ) or $command <= 30 or $command >= 65535) {
					$this->message ( $sender, $this->get ( "wrong-port" ) );
					$this->message ( $sender, $this->get ( "please-type-master-port" ) );
					$event->setCancelled ();
					return;
				}
				$this->db ["master-port"] = $command;
				$this->message ( $sender, $this->get ( "master-port-selected" ) );
				$this->message ( $sender, $this->get ( "all-setup-complete" ) );
				$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new LoadBalancerTask ( $this ), 20 );
				$event->setCancelled ();
				return;
			}
		}
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
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	// ----------------------------------------------------------------------------------
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = json_decode ( $ev->getPacket ()->data );
		if (! is_array ( $data )) {
			echo "[테스트] 어레이가 아닌 정보 전달됨\n";
			return;
		}
		if ($data [0] != $this->db ["passcode"]) {
			echo "[테스트] 패스코드가 다른 정보 전달됨\n";
			return;
		}
		if ($this->db ["mode"] == "master") {
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["list"] = $data [1];
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["max"] = $data [2];
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["port"] = $data [3];
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["lastcontact"] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
		}
	}
	public function makeTimestamp($date) {
		$yy = substr ( $date, 0, 4 );
		$mm = substr ( $date, 5, 2 );
		$dd = substr ( $date, 8, 2 );
		$hh = substr ( $date, 11, 2 );
		$ii = substr ( $date, 14, 2 );
		$ss = substr ( $date, 17, 2 );
		return mktime ( $hh, $ii, $ss, $mm, $dd, $yy );
	}
}

?>