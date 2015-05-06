<?php

namespace ifteam\tutorialMode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;

class tutorialMode extends PluginBase implements Listener {
	private static $instance = null; // 인스턴스 변수
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 1; // 현재 메시지 버전
	public $continue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		// 플러그인의 인스턴스 정의
		if (self::$instance == null) self::$instance = $this;
		
		// 서버이벤트를 받아오게끔 플러그인 리스너를 서버에 등록
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onJoin(PlayerJoinEvent $event) {
		if (! isset ( $this->db ["finished"] [$event->getPlayer ()->getName ()] )) {
			$this->continue = [ "mine" => false,"shop" => false,"notice" => false,"wild" => false ];
			$this->message ( $event->getPlayer (), $this->get ( "start-tutorial" ) );
		}
	}
	public function onSignPlace(SignChangeEvent $event) {
		if (! $event->getPlayer ()->isOp ()) return;
		if ($event->getLine ( 0 ) != $this->get ( "tutorial" )) return;
		
		switch (strtolower ( $event->getLine ( 0 ) )) {
			case $this->get ( "skip" ) :
				$this->setSkipSign ( $event->getBlock () );
				// TODO 형식교체- 튜토리얼 패스를 원할시 터치해주세요
				break;
			case $this->get ( "restart" ) :
				$this->setRestartSign ( $event->getBlock () );
				// TODO 형식교체- 튜토리얼 재시작을 원할시 터치해주세요
				break;
			case $this->get ( "mine" ) :
				$this->setMine ( $event->getPlayer ()->getPosition () );
				// TODO 형식교체- 안내를 확인 했으면 터치해주세요
				break;
			case $this->get ( "shop" ) :
				$this->setShop ( $event->getPlayer ()->getPosition () );
				// TODO 형식교체- 안내를 확인 했으면 터치해주세요
				break;
			case $this->get ( "notice" ) :
				$this->setNotice ( $event->getPlayer ()->getPosition () );
				// TODO 형식교체- 안내를 확인 했으면 터치해주세요
				break;
			case $this->get ( "wild" ) :
				$this->setWild ( $event->getPlayer ()->getPosition () );
				// TODO 형식교체- 안내를 확인 했으면 터치해주세요
				break;
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		// TODO 터치시 해당 이벤트 완료처리
		// TODO foreach 로 잡아내고 안잡아지면 전체 완료후 완료명단에 추가
	}
	public function onMove(PlayerMoveEvent $event) {
		if (! isset ( $this->db ["finished"] [$event->getPlayer ()->getName ()] )) {
			// TODO 진행중인 튜토리얼 공간에서 너무 멀어졌을경우 해당위치로 재워프
			// TODO 메시지 : 튜토리얼을 완료 후 이동이 가능합니다 !
		}
	}
	public function setSkipSign(Position $pos) {
		$this->db ["skipSign"] = "{$pos->x}.{$pos->y}.{$pos->z}.{$pos->getLevel()->getFolderName()}";
	}
	public function setRestartSign(Position $pos) {
		$this->db ["restartSign"] = "{$pos->x}.{$pos->y}.{$pos->z}.{$pos->getLevel()->getFolderName()}";
	}
	public function setMine(Position $pos) {
		$this->db ["mine"] = "{$pos->x}.{$pos->y}.{$pos->z}.{$pos->getLevel()->getFolderName()}";
	}
	public function setShop(Position $pos) {
		$this->db ["shop"] = "{$pos->x}.{$pos->y}.{$pos->z}.{$pos->getLevel()->getFolderName()}";
	}
	public function setNotice(Position $pos) {
		$this->db ["notice"] = "{$pos->x}.{$pos->y}.{$pos->z}.{$pos->getLevel()->getFolderName()}";
	}
	public function setWild(Position $pos) {
		$this->db ["wild"] = "{$pos->x}.{$pos->y}.{$pos->z}.{$pos->getLevel()->getFolderName()}";
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
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
}

?>