<?php

namespace Waypoint;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class Waypoint extends PluginBase implements Listener {
	public $config, $configData;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) === null) {
			$this->getServer ()->getLogger ()->critical ( $this->get ( "custompacket-not-exist" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return;
		}
		$this->registerCommand ( $this->get ( "waypoint-command" ), $this->get ( "waypoint-command" ), "waypoint.command", $this->get ( "waypoint-command-desc" ), $this->get ( "waypoint-command-usage" ) );
		$this->config = new Config ( $this->getDataFolder () . "announce.yml", Config::YAML, [ 
				"enable" => true,
				"waypoints" => [ ] 
		] );
		$this->configData = $this->config->getAll ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->configData );
		$this->config->save ();
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "waypoint-command" ) :
				switch ($args [0]) {
					case $this->get ( "waypoint-add" ) :
						array_shift ( $args );
						$text = implode ( " ", $args );
						if (! $player instanceof Player) {
							$player->sendMessage ( $this->get ( "in-game-command" ) );
							return;
						}
						if ($text == "" or $text == " ") {
							$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "waypoint-add-help" ) );
							return;
						}
						$point = ( int ) round ( $player->x ) . ":" . ( int ) round ( $player->y ) . ":" . ( int ) round ( $player->z );
						if (isset ( $this->configData ["waypoints"] [$point] )) {
							$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "waypoint-already-exist" ) );
							return;
						}
						$this->configData ["waypoints"] [$point] = $text;
						$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "waypoint-add-complete" ) );
						break;
					case $this->get ( "waypoint-del" ) :
						if (! $player instanceof Player) {
							$player->sendMessage ( $this->get ( "in-game-command" ) );
							return;
						}
						break;
					case $this->get ( "waypoint-list" ) :
						break;
					case $this->get ( "waypoint-clear" ) :
						break;
					case $this->get ( "waypoint-onoff" ) :
						break;
					default :
						break;
				}
				break;
		}
		return true;
	}
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		// $ev->getPacket ()->printDump ();
		if (! isset ( explode ( "#Waypoint ", $ev->getPacket ()->data )[1] ))
			return;
		$data = explode ( "#Waypoint ", $ev->getPacket ()->data )[1];
		switch ($data) {
			case "getlist" :
				$pk = new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, "You sent me " . $ev->getPacket ()->data . " and this example plugin is returning packet" );
				CPAPI::sendPacket ( $pk );
				break;
		}
	}
}
?>