<?php

namespace ifteam\OverTheServer;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\network\Network;

class OverTheServer extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 1; // 현재 메시지 버전
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		// 플러그인의 인스턴스 정의
		if (self::$instance == null) self::$instance = $this;
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( $this->get ( "overtheserver" ), "overtheserver.control", $this->get ( "overtheserver-desc" ), $this->get ( "overtheserver-help" ) );
		$this->registerCommand ( $this->get ( "serverconnect" ), "overtheserver.serverconnect", $this->get ( "serverconnect-desc" ), $this->get ( "serverconnect-help" ) );
		$this->registerCommand ( $this->get ( "serverlist" ), "overtheserver.serverlist", $this->get ( "serverlist-desc" ), $this->get ( "serverlist-help" ) );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "overtheserver" ) :
				if (! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "overtheserver-help" ) );
					return true;
				}
				switch (strtolower ( $args [0] )) {
					case $this->get ( "add" ) :
						if (! isset ( $args [2] )) {
							$this->message ( $player, $this->get ( "overtheserver-help" ) );
							return true;
						}
						$this->db ["list"] [$args [1]] = $args [2];
						$this->message ( $player, $this->get ( "add-success" ) );
						break;
					case $this->get ( "del" ) :
						if (! isset ( $args [1] )) {
							$this->message ( $player, $this->get ( "overtheserver-help" ) );
							return true;
						}
						if (isset ( $this->db ["list"] [$args [1]] )) unset ( $this->db ["list"] [$args [1]] );
						$this->message ( $player, $this->get ( "del-success" ) );
						break;
				}
				break;
			case $this->get ( "serverconnect" ) :
				if (! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "serverconnect-help" ) );
					return true;
				}
				if (! isset ( $this->db ["list"] [$args [0]] )) {
					$this->message ( $player, $this->get ( "server-doesnt-exist" ) );
					return true;
				}
				if (! $player instanceof Player) {
					$this->message ( $player, $this->get ( "ingame-only" ) );
					return true;
				}
				$data = explode ( ":", $this->db ["list"] [$args [0]] );
				$player->dataPacket ( (new StrangePacket ( $data [0], $data [1] ))->setChannel ( Network::CHANNEL_ENTITY_SPAWNING ) );
				break;
			case $this->get ( "serverlist" ) :
				$serverList = "";
				if (isset ( $this->db ["list"] )) foreach ( $this->db ["list"] as $index => $data )
					$serverList .= "[ " . $index . " ] ";
				$this->message ( $player, $this->get ( "print-all-server-list" ) );
				$this->message ( $player, $this->get ( "can-access-serverconnect" ) );
				$this->message ( $player, $serverList, "" );
				break;
		}
		return true;
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
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
	public function message(CommandSender $player, $text = "", $mark = null) {
		if ($mark === null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(CommandSender $player, $text = "", $mark = null) {
		if ($mark === null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
}

?>