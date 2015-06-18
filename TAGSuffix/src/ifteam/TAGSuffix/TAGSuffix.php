<?php

namespace ifteam\TAGSuffix;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\utils\Config;

class TAGSuffix extends PluginBase implements Listener {
	public $db;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->db = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ "prefix" => "","suffix" => "" ] );
		$this->db->save ();
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onJoin(PlayerJoinEvent $event) {
		if (! $event->getPlayer () instanceof Player) return;
		if($event->getPlayer()->isOp())
			$event->getPlayer ()->setNameTag ( "[OP] " . $event->getPlayer ()->getNameTag () . $this->db->get ( "suffix", "" ) );
		$event->getPlayer ()->setNameTag ( $this->db->get ( "prefix", "" ) . $event->getPlayer ()->getNameTag () . $this->db->get ( "suffix", "" ) );
	}
}

?>