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
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Sign;
use pocketmine\Player;

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
		if (! isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
			$this->continue [strtolower ( $event->getPlayer ()->getName () )] = [ "mine" => false,"shop" => false,"notice" => false,"wild" => false ];
			$this->message ( $event->getPlayer (), $this->get ( "start-tutorial" ) );
		}
	}
	public function onSignPlace(SignChangeEvent $event) {
		if (! $event->getPlayer ()->isOp ()) return;
		if ($event->getLine ( 0 ) != $this->get ( "tutorial" )) return;
		
		switch (strtolower ( $event->getLine ( 0 ) )) {
			case $this->get ( "skip" ) :
				$event->setLine ( 0, TextFormat::WHITE . $this->get ( "sign-tutorial-skip1" ) );
				$event->setLine ( 1, TextFormat::WHITE . $this->get ( "sign-tutorial-skip2" ) );
				$event->setLine ( 2, TextFormat::WHITE . $this->get ( "sign-tutorial-skip3" ) );
				$this->setSkipSign ( $event->getBlock () );
				break;
			case $this->get ( "restart" ) :
				$event->setLine ( 0, TextFormat::WHITE . $this->get ( "sign-tutorial-restart1" ) );
				$event->setLine ( 1, TextFormat::WHITE . $this->get ( "sign-tutorial-restart2" ) );
				$event->setLine ( 2, TextFormat::WHITE . $this->get ( "sign-tutorial-restart3" ) );
				$this->setRestartSign ( $event->getBlock () );
				break;
			case $this->get ( "mine" ) :
				$event->setLine ( 0, TextFormat::WHITE . $this->get ( "sign-tutorial-pass1" ) );
				$event->setLine ( 1, TextFormat::WHITE . $this->get ( "sign-tutorial-pass2" ) );
				$event->setLine ( 2, TextFormat::WHITE . $this->get ( "sign-tutorial-pass3" ) );
				$this->setMine ( $event->getBlock (), $event->getPlayer ()->getPosition () );
				break;
			case $this->get ( "shop" ) :
				$event->setLine ( 0, TextFormat::WHITE . $this->get ( "sign-tutorial-pass1" ) );
				$event->setLine ( 1, TextFormat::WHITE . $this->get ( "sign-tutorial-pass2" ) );
				$event->setLine ( 2, TextFormat::WHITE . $this->get ( "sign-tutorial-pass3" ) );
				$this->setShop ( $event->getBlock (), $event->getPlayer ()->getPosition () );
				break;
			case $this->get ( "notice" ) :
				$event->setLine ( 0, TextFormat::WHITE . $this->get ( "sign-tutorial-pass1" ) );
				$event->setLine ( 1, TextFormat::WHITE . $this->get ( "sign-tutorial-pass2" ) );
				$event->setLine ( 2, TextFormat::WHITE . $this->get ( "sign-tutorial-pass3" ) );
				$this->setNotice ( $event->getBlock (), $event->getPlayer ()->getPosition () );
				break;
			case $this->get ( "wild" ) :
				$event->setLine ( 0, TextFormat::WHITE . $this->get ( "sign-tutorial-pass1" ) );
				$event->setLine ( 1, TextFormat::WHITE . $this->get ( "sign-tutorial-pass2" ) );
				$event->setLine ( 2, TextFormat::WHITE . $this->get ( "sign-tutorial-pass3" ) );
				$this->setWild ( $event->getBlock (), $event->getPlayer ()->getPosition () );
				break;
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (! $event->getBlock () instanceof Sign) break;
		if (isset ( $this->db ["sign"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"] )) {
			switch ($this->db ["sign"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"]) {
				case "skipSign" :
					if (isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
						$this->message ( $event->getPlayer (), $this->get ( "already-clread-all-tutorial" ) );
						return;
					} else {
						$this->message ( $event->getPlayer (), $this->get ( "all-tutorial-skipped" ) );
						$this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] = true;
						if (isset ( $this->continue [strtolower ( $event->getPlayer ()->getName () )] )) {
							unset ( $this->continue [strtolower ( $event->getPlayer ()->getName () )] );
						}
					}
					break;
				case "restartSign" :
					$this->message ( $event->getPlayer (), $this->get ( "now-tutorial-restarted" ) );
					if (isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
						unset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] );
					}
					$this->continue [strtolower ( $event->getPlayer ()->getName () )] = [ "mine" => false,"shop" => false,"notice" => false,"wild" => false ];
					break;
				case "mine" :
					if (isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
						$this->message ( $this->get ( "already-clread-all-tutorial" ) );
						return;
					}
					$this->message ( $event->getPlayer (), $this->get ( "mine" ) . " " . $this->get ( "tutorial-cleared" ) );
					$this->continue [strtolower ( $event->getPlayer ()->getName () )] ["mine"] = true;
					$this->tutorialClear ( $event->getPlayer () );
					break;
				case "shop" :
					if (isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
						$this->message ( $this->get ( "already-clread-all-tutorial" ) );
						return;
					}
					$this->message ( $event->getPlayer (), $this->get ( "shop" ) . " " . $this->get ( "tutorial-cleared" ) );
					$this->continue [strtolower ( $event->getPlayer ()->getName () )] ["shop"] = true;
					$this->tutorialClear ( $event->getPlayer () );
					break;
				case "notice" :
					if (isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
						$this->message ( $this->get ( "already-clread-all-tutorial" ) );
						return;
					}
					$this->message ( $event->getPlayer (), $this->get ( "notice" ) . " " . $this->get ( "tutorial-cleared" ) );
					$this->continue [strtolower ( $event->getPlayer ()->getName () )] ["notice"] = true;
					$this->tutorialClear ( $event->getPlayer () );
					break;
				case "wild" :
					if (isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
						$this->message ( $this->get ( "already-clread-all-tutorial" ) );
						return;
					}
					$this->message ( $event->getPlayer (), $this->get ( "wild" ) . " " . $this->get ( "tutorial-cleared" ) );
					$this->continue [strtolower ( $event->getPlayer ()->getName () )] ["wild"] = true;
					$this->tutorialClear ( $event->getPlayer () );
					break;
			}
		}
	}
	public function tutorialClear(Player $player) {
		foreach ( $this->continue [strtolower ( $player->getName () )] as $stage => $check ) {
			if (! $check) {
				if (! isset ( $this->db [$stage] )) continue; // STAGEDATA EXCEPTION
				
				$data = explode ( ".", $this->db [$stage] );
				if (! isset ( $data [3] )) continue; // POSDATA EXCEPTION
				
				$level = $this->getServer ()->getLevelByName ( $data [3] );
				if (! $level instanceof Level) continue; // LEVEL EXCEPTION
				
				$this->message ( $player, $this->get ( $stage ) . " " . $this->get ( "tutorial-start" ) );
				$player->teleport ( new Position ( $data [0], $data [1], $data [2], $level ) );
				return;
			}
		}
		$this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] = true;
		$this->message ( $player, $this->get ( "all-tutorial-cleared" ) );
		$this->message ( $player, $this->get ( "you-can-move-free" ) );
	}
	public function onMove(PlayerMoveEvent $event) {
		if (! isset ( $this->db ["finished"] [strtolower ( $event->getPlayer ()->getName () )] )) {
			foreach ( $this->continue [strtolower ( $player->getName () )] as $stage => $check ) {
				if (! $check) {
					
					$data = explode ( ".", $this->db [$stage] );
					if (! isset ( $data [3] )) continue; // POSDATA EXCEPTION
					
					$level = $this->getServer ()->getLevelByName ( $data [3] );
					if (! $level instanceof Level) continue; // LEVEL EXCEPTION
					/*
					 * //new Position ( $data [0], $data [1], $data [2], $level 
					 * // TODO 진행중인 튜토리얼 공간에서 너무 멀어졌을경우 해당위치로 재워프
					 */
					$this->message ( $event->getPlayer (), $this->get ( "you-need-pass-tutorial" ) );
				}
			}
		}
	}
	public function setSkipSign(Position $sign) {
		$this->db ["sign"] [$sign->getLevel ()->getFolderName ()] ["{$sign->x}.{$sign->y}.{$sign->z}"] = "skipSign";
	}
	public function setRestartSign(Position $sign) {
		$this->db ["sign"] [$sign->getLevel ()->getFolderName ()] ["{$sign->x}.{$sign->y}.{$sign->z}"] = "restartSign";
	}
	public function setMine(Position $sign, Position $player) {
		$this->db ["sign"] [$sign->getLevel ()->getFolderName ()] ["{$sign->x}.{$sign->y}.{$sign->z}"] = "mine";
		$this->db ["mine"] = "{$player->x}.{$player->y}.{$player->z}.{$player->getLevel()->getFolderName()}";
	}
	public function setShop(Position $sign, Position $player) {
		$this->db ["sign"] [$sign->getLevel ()->getFolderName ()] ["{$sign->x}.{$sign->y}.{$sign->z}"] = "shop";
		$this->db ["shop"] = "{$player->x}.{$player->y}.{$player->z}.{$player->getLevel()->getFolderName()}";
	}
	public function setNotice(Position $sign, Position $player) {
		$this->db ["sign"] [$sign->getLevel ()->getFolderName ()] ["{$sign->x}.{$sign->y}.{$sign->z}"] = "notice";
		$this->db ["notice"] = "{$player->x}.{$player->y}.{$$player->z}.{$player->getLevel()->getFolderName()}";
	}
	public function setWild(Position $sign, Position $player) {
		$this->db ["sign"] [$sign->getLevel ()->getFolderName ()] ["{$sign->x}.{$sign->y}.{$sign->z}"] = "wild";
		$this->db ["wild"] = "{$player->x}.{$player->y}.{$player->z}.{$player->getLevel()->getFolderName()}";
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		if (! $event->getBlock () instanceof Sign) break;
		if (isset ( $this->db ["sign"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"] )) {
			if (! $event->getPlayer ()->isOp ()) {
				$event->setCancelled ();
				return;
			}
			switch ($this->db ["sign"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"]) {
				case "mine" :
					if (isset ( $this->db ["mine"] )) unset ( $this->db ["mine"] );
					break;
				case "shop" :
					if (isset ( $this->db ["shop"] )) unset ( $this->db ["shop"] );
					break;
				case "notice" :
					if (isset ( $this->db ["notice"] )) unset ( $this->db ["notice"] );
					break;
				case "wild" :
					if (isset ( $this->db ["wild"] )) unset ( $this->db ["wild"] );
					break;
			}
			unset ( $this->db ["sign"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"] );
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