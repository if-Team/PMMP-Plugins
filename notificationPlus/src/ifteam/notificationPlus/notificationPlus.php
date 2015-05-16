<?php

namespace ifteam\notificationPlus;

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
	public $m_version = 1;
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
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	// ---------------------------------------------------------------------------//
	public function userCommand(PlayerCommandPreprocessEvent $event) {
		$command = $event->getMessage ();
		$sender = $event->getPlayer ();
		
		if (! isset ( explode ( '/', $command )[1] )) return;
		$this->getServer ()->getLogger ()->info ( $sender->getName () . " : " . $command );
	}
	public function signChange(SignChangeEvent $event) {
		$message = "";
		foreach ( $event->getLines () as $index => $line )
			if ($line != null) $message .= " (" . $index . " : " . $line . ") ";
		if ($message == null) return;
		$message = $this->get ( "sign-set" ) . " : " . $event->getPlayer ()->getName () . "  : " . $message;
		$message = $message . " (X:" . $event->getBlock ()->x . " Y:" . $event->getBlock ()->y . " Z: " . $event->getBlock ()->z . ")";
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