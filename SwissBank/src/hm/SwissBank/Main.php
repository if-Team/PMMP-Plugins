<?php

namespace hm\SwissBank;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\PluginCommand;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

class Main extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $economyAPI = null;
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
		// 이코노미 API 이용
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		// 플러그인의 인스턴스 정의
		if (self::$instance == null) self::$instance = $this;
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( $this->get ( "commands-create" ), "SwissBank.create", $this->get ( "commands-create-desc" ), $this->get ( "commands-create-usage" ) );
		$this->registerCommand ( $this->get ( "commands-use" ), "SwissBank.use", $this->get ( "commands-use-desc" ), $this->get ( "commands-use-usage" ) );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		if (! isset ( $this->db ["mode"] )) $this->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->get ( "please-choose-mode" ) );
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
		if (! isset ( $this->db ["port"] )) { // 서버 은행 포트 설정
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
				$event->setCancelled ();
				return;
			}
		}
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-create" ) : // 수표발행 <암호코드> <가격>
				if (! isset ( $args [0] )) {
					$this->alert ( $player, $this->get ( "commands-create-usage" ) );
					break;
				}
				// if ($args [0] < 8) {
				// $this->alert ( $player, $this->get ( "too-short-passcode" ) );
				// break;
				// }
				if ($this->db ["mode"] == "master") {
					if (! isset ( $args [1] ) or ! is_numeric ( $args [1] )) {
						$this->alert ( $player, $this->get ( "commands-create-usage" ) );
						break;
					}
					if (isset ( $this->db ["bank"] [$args [0]] )) {
						$this->alert ( $player, $this->get ( "wrong-passcode" ) );
						break;
					}
					$this->db ["bank"] [$args [0]] ["price"] = $args [1];
					$this->db ["bank"] [$args [0]] ["username"] = $player->getName ();
					$this->message ( $player, $this->get ( "passcode-created" ) );
				} else {
					if (! isset ( $args [1] ) or ! is_numeric ( $args [1] )) {
						$this->alert ( $player, $this->get ( "commands-create-usage" ) );
						break;
					}
					// 계좌 개설시도 (@커스텀패킷 전송)
					CPAPI::sendPacket ( new DataPacket ( $this->db ["master-ip"], $this->db ["master-port"], [ $this->db ["passcode"],"createBank",$args [0],$player->getName (),$args [1] ] ) );
					$this->message ( $player, $this->get ( "try-createBank" ) );
				}
				break;
			case $this->get ( "commands-use" ) : // 수표사용 <암호코드>
				if ($this->db ["mode"] == "master") {
					if (! isset ( $args [0] )) {
						$this->alert ( $player, $this->get ( "commands-use-usage" ) );
						break;
					}
					if (! isset ( $this->db ["bank"] [$args [0]] )) {
						$this->alert ( $player, $this->get ( "wrong-passcode" ) );
						break;
					}
					$price = $this->db ["bank"] [$args [0]] ["price"];
					$playerMoney = $this->economyAPI->myMoney ( $this->db ["bank"] [$args [0]] ["username"] );
					
					if ($playerMoney >= $price) {
						// 해당계좌 돈뺏기
						$this->economyAPI->reduceMoney ( $this->db ["bank"] [$args [0]] ["username"], $price );
						// 해당 돈 유저에게 지급하기
						$this->economyAPI->addMoney ( $player, $price );
						$this->message ( $player, $this->get ( "success" ) . " ($" . $price . ")" );
					} else {
						$this->message ( $player, $this->get ( "failed" ) );
					}
					unset ( $this->db ["bank"] [$args [0]] );
					// 수표 브루트포싱 방지필요
				} else {
					if (! isset ( $args [0] )) {
						$this->alert ( $player, $this->get ( "commands-use-usage" ) );
						break;
					}
					// 계좌 개설시도 (@커스텀패킷 전송)
					CPAPI::sendPacket ( new DataPacket ( $this->db ["master-ip"], $this->db ["master-port"], [ $this->db ["passcode"],"useBank",$args [0],$player->getName () ] ) );
					$this->message ( $player, $this->get ( "try-useBank" ) );
				}
				break;
		}
		return true;
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
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	// ----------------------------------------------------------------------------------
	// createBank
	// slave->master = [패스코드, createBank, 계좌명, username, 비용]
	// master->slave = [패스코드, createBank, 계좌명, username, true:success|false:failed]
	// useBank
	// slave->master = [패스코드, useBank, 계좌명, username]
	// master->slave = [패스코드, useBank, 계좌명, username, true:success|false:failed]
	// ----------------------------------------------------------------------------------
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = $ev->getPacket ()->data; // json 화 필요할 수도 있음
		if (! is_array ( $data )) {
			echo "[테스트] 어레이가 아닌 정보 전달됨";
			return;
		}
		if ($data [0] != $this->db ["passcode"]) {
			echo "[테스트] 패스코드가 다른 정보 전달됨";
			return;
		}
		if ($this->db ["mode"] == "master") {
			// 마스터 서버 코딩
			switch ($data [1]) {
				case "createBank" :
					// slave->master = [0패스코드, 1createBank, 2계좌명, 3username, 4비용]
					// master->slave = [0패스코드, 1createBank, 2계좌명, 3username, 4true:success|false:failed]
					if (isset ( $this->db ["bank"] [$data [2]] )) {
						$data [4] = false;
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
						break;
					}
					$this->db ["bank"] [$data [2]] ["price"] = $data [4];
					$this->db ["bank"] [$data [2]] ["username"] = $data [3];
					$data [4] = true;
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
					break;
				case "useBank" :
					// slave->master = [0패스코드, 1useBank, 2계좌명, 3username]
					// master->slave = [0패스코드, 1useBank, 2계좌명, 3username, 4true:success|false:failed]
					
					if (! isset ( $this->db ["bank"] [$data [2]] )) {
						$data [4] = false;
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
						break;
					}
					
					$price = $this->db ["bank"] [$data [2]] ["price"];
					$playerMoney = $this->economyAPI->myMoney ( $this->db ["bank"] [$data [2]] ["username"] );
					
					if ($playerMoney >= $price) {
						// 해당계좌 돈뺏기
						$this->economyAPI->reduceMoney ( $this->db ["bank"] [$data [2]] ["username"], $price );
						// 해당 돈 유저에게 지급하기
						$this->economyAPI->addMoney ( $data [3], $price );
						$data [4] = true;
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
						break;
					} else {
						$data [4] = false;
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
						break;
					}
					unset ( $this->db ["bank"] [$data [2]] );
					break;
			}
		} else {
			// 슬레이브 서버 코딩
			switch ($data [1]) {
				case "createBank" :
					// slave->master = [0패스코드, 1createBank, 2계좌명, 3username, 4비용]
					// master->slave = [0패스코드, 1createBank, 2계좌명, 3username, 4true:success|false:failed]
					
					break;
				case "useBank" :
					// slave->master = [0패스코드, 1useBank, 2계좌명, 3username]
					// master->slave = [0패스코드, 1useBank, 2계좌명, 3username, 4true:success|false:failed]
					break;
			}
		}
	}
}

?>