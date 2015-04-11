<?php

namespace poolTax;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;

class poolTax extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $economyAPI = null;
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 2; // 현재 메시지 버전
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ "taxPrice" => 1 ] ))->getAll ();
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( $this->get ( "taxControl" ), $this->get ( "taxControl" ), "taxControl", $this->get ( "taxHelp" ), $this->get ( "taxControl-help" ) );
		
		// 이코노미 API 이용
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		// 서버이벤트를 받아오게끔 플러그인 리스너를 서버에 등록
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,"poolTax" ] ), 1200 );
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
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function message($player, $text = "", $mark = null) {
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "taxControl" ) :
				if (! isset ( $args [0] ) or $args [0] == null or ! is_numeric ( $args [0] )) {
					$this->alert ( $player, $this->get ( "taxControl-help" ) );
					return true;
				}
				$this->db ["taxPrice"] = $args [0];
				$this->message ( $player, $this->get ( "taxChanged" ) );
				break;
		}
		return true;
	}
	public function poolTax() {
		if ($this->db ["taxPrice"] == 0) return;
		$paid = 0;
		foreach ( $this->economyAPI->getAllMoney ()["money"] as $player => $money ) {
			if ($this->db ["taxPrice"] < 10000) return;
			$this->economyAPI->reduceMoney ( $player, $this->db ["taxPrice"] );
			$paid ++;
		}
		$this->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->get ( "prefix" ) . " " . $paid . $this->get ( "paid" ) );
	}
}

?>