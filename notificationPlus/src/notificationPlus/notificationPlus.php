<?php

namespace notificationPlus;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class notificationPlus extends PluginBase implements Listener {
	public $messages, $db;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		$this->db = (new Config ( $this->getDataFolder () . "notificationDB.yml", Config::YAML ))->getAll ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "notificationDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	// ---------------------------------------------------------------------------//
	public function userCommand(PlayerCommandPreprocessEvent $event) {
		$command = $event->getMessage ();
		$sender = $event->getPlayer ();
		
		if (! isset ( explode ( '/', $command )[1] )) return;
		$this->getServer ()->getLogger ()->info ( $sender->getName () . " : " . $command );
		foreach ( $this->getServer ()->getOnlinePlayers () as $player )
			if ($player->isOp ()) $this->message ( $player, $sender->getName () . " : " . $command );
	}
	public function signChange(SignChangeEvent $event) {
		$message = "";
		foreach ( $event->getLines () as $index => $line )
			if ($line != null) $message .= " (" . $index . " : " . $line . ") ";
		if ($message == null) return;
		$message = $this->get ( "sign-set" ) . " : " . $event->getPlayer ()->getName () . "  : " . $message;
		$this->getServer ()->getLogger ()->info ( $message );
		foreach ( $this->getServer ()->getOnlinePlayers () as $player )
			if ($player->isOp ()) $this->message ( $player, $message );
	}
	public function onLogin(PlayerLoginEvent $event) {
		$isUsed = false;
		if (isset ( $this->db [$event->getPlayer ()->getAddress ()] )) foreach ( $this->db [$event->getPlayer ()->getAddress ()] as $nicname )
			if ($nicname == $event->getPlayer ()->getName ()) $isUsed = true;
		if ($isUsed == false) $this->db [$event->getPlayer ()->getAddress ()] [] = $event->getPlayer ()->getName ();
		
		if (count ( $this->db [$event->getPlayer ()->getAddress ()] ) > 1) {
			$message = "";
			foreach ( $this->db [$event->getPlayer ()->getAddress ()] as $nicname )
				$message .= " (" . $nicname . ") ";
			$this->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->get ( "used-nickname" ) . " : " . $message );
		}
	}
}

?>