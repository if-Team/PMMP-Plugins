<?php

namespace ifteam\Gentleman;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\Player;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\block\SignChangeEvent;

class Gentleman extends PluginBase implements Listener {
	private static $instance = null;
	public $list, $messages;
	public $badQueue = [ ];
	public $oldChat = [ ], $oldSign = [ ];
	public $banPoint;
	public $m_version = 1;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->messagesUpdate ();
		
		if (self::$instance == null) self::$instance = $this;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		// $this->parseXEDatabaseToJSON (); //*CONVERT ONLY*
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onChat(PlayerChatEvent $event) {
		if ($event->getPlayer ()->isOp ()) return;
		$find = $this->checkSwearWord ( $event->getMessage () );
		if ($find != null) {
			$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "some-badwords-found" ) . ": " . $event->getMessage () . "( " . $this->get ( "doubt" ) . ": " . $find . " ) " );
			$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
			$event->setCancelled ();
			$this->cautionNotice ( $event->getPlayer (), $event->getMessage () . "( " . $find . " ) " );
			return;
		}
		if (isset ( $this->oldChat [$event->getPlayer ()->getName ()] )) {
			$find = $this->checkSwearWord ( $this->oldChat [$event->getPlayer ()->getName ()] . $event->getMessage () );
			if ($find != null) {
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "some-badwords-found" ) . ": " . $event->getMessage () . "( " . $this->get ( "doubt" ) . ": " . $find . " ) " );
				$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
				$event->setCancelled ();
				$this->cautionNotice ( $event->getPlayer (), $event->getMessage () . "( " . $find . " ) " );
				return;
			}
		}
		$this->oldChat [$event->getPlayer ()->getName ()] = $event->getMessage ();
	}
	public function signPlace(SignChangeEvent $event) {
		if ($event->getPlayer ()->isOp ()) return;
		$message = "";
		foreach ( $event->getLines () as $line )
			$message .= $line;
		$find = $this->checkSwearWord ( $message );
		if ($find != null) {
			$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) . ": " . $message . " ( " . $this->get ( "doubt" ) . ": " . $find . " )" );
			$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
			$event->setCancelled ();
			$this->cautionNotice ( $event->getPlayer (), $message . " ( " . $find . " ) " );
			return;
		}
		if (isset ( $this->oldSign [$event->getPlayer ()->getName ()] )) {
			$find = $this->checkSwearWord ( $this->oldSign [$event->getPlayer ()->getName ()] . $message );
			if ($find != null) {
				$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) . ": " . $message . " ( " . $this->get ( "doubt" ) . ": " . $find . " )" );
				$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
				$event->setCancelled ();
				$this->cautionNotice ( $event->getPlayer (), $find );
				return;
			}
		}
		$this->oldSign [$event->getPlayer ()->getName ()] = $message;
	}
	public function userCommand(PlayerCommandPreprocessEvent $event) {
		$command = $event->getMessage ();
		
		if ($event->getPlayer ()->isOp ()) return;
		$command = explode ( ' ', $command );
		if ($command [0] == "/me" or $command [0] == "/tell") {
			$find = $this->checkSwearWord ( $event->getMessage () );
			if ($find != null) {
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "some-badwords-found" ) . ": " . " (" . $this->get ( "doubt" ) . ": " . $find . " )" );
				$event->setCancelled ();
				$this->cautionNotice ( $event->getPlayer (), $event->getMessage () . " ( " . $find . " )" );
			}
		}
	}
	public function onLogin(PlayerLoginEvent $event) {
		if ($event->getPlayer ()->isOp ()) return;
		$find = $this->checkSwearWord ( $event->getPlayer ()->getName () );
		if ($find != null) {
			$event->setCancelled ();
			$event->setKickMessage ( $this->get ( "badwords-nickname" ) );
		}
	}
	public function cautionNotice(Player $player, $word) {
		$this->getLogger ()->alert ( $this->get ( "some-badwords-found" ) );
		$this->getLogger ()->alert ( $player->getName () . "> " . $word );
		foreach ( $this->getServer ()->getOnlinePlayers () as $online ) {
			if (! $online->isOp ()) continue;
			$player->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) );
			$player->sendMessage ( TextFormat::RED . $player->getName () . "> " . $word );
		}
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->saveResource ( "badwords.json", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		$this->list = (new Config ( $this->getDataFolder () . "badwords.json", Config::JSON ))->getAll ();
		$this->banPoint = (new Config ( $this->getDataFolder () . "banpoints.yml", Config::YAML ))->getAll ();
		$this->makeQueue ();
	}
	public function onDisable() {
		$banPoint = new Config ( $this->getDataFolder () . "banpoints.yml", Config::YAML );
		$banPoint->setAll ( $this->banPoint );
		$banPoint->save ();
	}
	public function makeQueue() {
		foreach ( $this->list ["badwords"] as $badword )
			$this->badQueue [] = $this->cutWords ( $badword );
	}
	public function cutWords($str) {
		$cut_array = array ();
		for($i = 0; $i < mb_strlen ( $str, "UTF-8" ); $i ++)
			array_push ( $cut_array, mb_substr ( $str, $i, 1, 'UTF-8' ) );
		return $cut_array;
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function messagesUpdate() {
		if (! isset ( $this->messages ["default-language"] ["m_version"] )) {
			$this->saveResource ( "messages.yml", true );
			$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		} else {
			if ($this->messages ["default-language"] ["m_version"] < $this->m_version) {
				$this->saveResource ( "messages.yml", true );
				$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
			}
		}
	}
	public function parseXEDatabaseToJSON() {
		$parseBadwords = file_get_contents ( $this->getDataFolder () . "badwords.txt" );
		$parseBadwords = mb_convert_encoding ( $parseBadwords, "UTF-8", "CP949" );
		$parseBadwords = explode ( ' ', $parseBadwords );
		
		$list = [ "badwords" => [ ] ];
		foreach ( $parseBadwords as $badword )
			$list ["badwords"] [] = $badword;
		
		$this->list = new Config ( $this->getDataFolder () . "badwords.json", Config::JSON, $list );
		$this->list->save ();
	}
	public function checkSwearWord($word) {
		$word = $this->cutWords ( $word );
		foreach ( $this->badQueue as $queue ) { // 비속어단어별 [바,보]
			$wordLength = count ( $queue );
			$find_count = [ ];
			foreach ( $queue as $match_alpha ) { // 비속어글자별 [바], [보]
				foreach ( $word as $used_alpha ) // 유저글자별 [ 나,는,바,보,다]
					if (strtolower ( $match_alpha ) == strtolower ( $used_alpha )) {
						$find_count [$match_alpha] = 0; // ["바"=>0 "보"=0]
						break;
					}
				if ($wordLength == count ( $find_count )) return implode ( "", $queue );
			}
		}
		return null;
	}
}

?>