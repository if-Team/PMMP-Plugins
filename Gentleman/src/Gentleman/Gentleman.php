<?php

namespace Gentleman;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerLoginEvent;

class Gentleman extends PluginBase implements Listener {
	public $list, $messages;
	public $badqueue = [ ];
	public $oldChat = [ ];
	public $banPoint;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		// $this->parseXE_DB_to_YML (); //*CONVERT ONLY*
	}
	public function onChat(PlayerChatEvent $event) {
		$find = $this->checkSwearWord ( $event->getMessage () );
		if ($find != null) {
			$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "some-badwords-found" ) . ": " . $find );
			$event->setCancelled ();
			$this->updatePoint ( $event->getPlayer ()->getAddress () );
			return;
		}
		if (isset ( $oldChat )) {
			$find = $this->checkSwearWord ( $this->oldChat [$event->getPlayer ()->getName ()] . $event->getMessage () );
			if ($find != null) {
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "some-badwords-found" ) . ": " . $find );
				$event->setCancelled ();
				$this->updatePoint ( $event->getPlayer ()->getAddress () );
				return;
			}
		}
		$this->oldChat [$event->getPlayer ()->getName ()] = $event->getMessage ();
	}
	public function userCommand(PlayerCommandPreprocessEvent $event) {
		$find = $this->checkSwearWord ( $event->getMessage () );
		if ($find != null) {
			$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "some-badwords-found" ) . ": " . $find );
			$event->setCancelled ();
			$this->updatePoint ( $event->getPlayer ()->getAddress () );
		}
	}
	public function onLogin(PlayerLoginEvent $event) {
		$find = $this->checkSwearWord ( $event->getPlayer ()->getName () );
		if ($find != null) {
			$event->setCancelled ();
			$event->setKickMessage ( $this->get ( "badwords-nickname" ) );
			$this->updatePoint ( $event->getPlayer ()->getAddress () );
		}
	}
	public function updatePoint($address) {
		if (isset ( $this->banPoint [$address] )) {
			$this->banPoint [$address] ++;
			if ($this->banPoint [$address] >= 3) {
				foreach ( $this->getServer ()->getOnlinePlayers () as $player )
					$player->kick ( $this->get ( "too-much-use-badwords" ) );
				$this->getServer ()->blockAddress ( $address, 1800 );
				unset ( $this->banPoint [$address] );
				return;
			}
			foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "be-careful-about-badwords" ) );
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "if-point-over-3-to-ban" ) );
			}
		} else {
			$this->banPoint [$address] = 1;
			foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "be-careful-about-badwords" ) );
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "if-point-over-3-to-ban" ) );
			}
		}
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->saveResource ( "badwords.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		$this->list = (new Config ( $this->getDataFolder () . "badwords.yml", Config::YAML ))->getAll ();
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
			$this->badqueue [] = $this->cutWords ( $badword );
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
	public function parseXE_DB_to_YML() {
		$parseBadwords = file_get_contents ( $this->getDataFolder () . "badwords.txt" );
		$parseBadwords = mb_convert_encoding ( $parseBadwords, "UTF-8", "CP949" );
		$parseBadwords = explode ( ' ', $parseBadwords );
		
		$list = [ 
				"badwords" => [ ] ];
		foreach ( $parseBadwords as $badword )
			$list ["badwords"] [] = $badword;
		
		$this->list = new Config ( $this->getDataFolder () . "badwords.yml", Config::YAML, $list );
		$this->list->save ();
	}
	public function checkSwearWord($word) {
		$word = $this->cutWords ( $word );
		foreach ( $this->badqueue as $queue ) { // 비속어단어별 [바,보]
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