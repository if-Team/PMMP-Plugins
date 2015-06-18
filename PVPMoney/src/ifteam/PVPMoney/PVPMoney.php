<?php

namespace ifteam\PVPMoney;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;

class PVPMoney extends PluginBase implements Listener {
	public $economyAPI = null;
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 1; // 현재 메시지 버전
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ 
				"payback" => 0 
		] ))->getAll ();
		
		// 이코노미 API 이용
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( $this->get ( "commands-pvpmoney" ), "PVPMoney.commands", $this->get ( "help-pvpmoney" ), $this->get ( "help-pvpmoney" ) );
		
		// 서버이벤트를 받아오게끔 플러그인 리스너를 서버에 등록
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDamage(EntityDamageEvent $event) {
		if (! $event instanceof EntityDamageByEntityEvent)
			return;
		
		if (! $event->getDamager () instanceof Player)
			return;
		if (! $event->getEntity () instanceof Player)
			return;
		
		$amount = $this->db ["payback"];
		$this->economyAPI->addMoney ( $event->getDamager (), $amount );
		
		$message = str_replace ( "%money%", $amount, $this->get ( "pvpmoney-paid" ) );
		$this->message($event->getDamager(), $message);
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-pvpmoney" ) :
				if (! isset ( $args [0] ) or is_numeric ( $args [0] )) {
					$this->alert ( $player, $this->get ( "help-pvpmoney" ) );
					return true;
					$this->db ["payback"] = $args [0];
					$this->message ( $player, $this->get ( "pvpmoney-is-changed" ) );
				}
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
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
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
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
}

?>