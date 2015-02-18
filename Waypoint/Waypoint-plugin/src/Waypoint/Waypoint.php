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
	public $waypointList;
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
				"waypoints" => [ ] ] );
		$this->configData = $this->config->getAll ();
		$this->refreshList ();
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
						$this->refreshList ();
						break;
					case $this->get ( "waypoint-del" ) :
						if (! isset ( $args [1] )) {
							$player->sendMessage ( TextFormat::RED . $this->get ( "waypoint-delete-help" ) );
							break;
						}
						if (! is_numeric ( $args [1] )) {
							$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "waypoint-delete-must-number" ) );
							break;
						}
						if (isset ( $this->configData ["waypoints"] [$args [1]] )) {
							unset ( $this->configData ["waypoints"] [$args [1]] );
							ksort ( $this->configData ["waypoints"] );
							$match_new = array ();
							$keys = array_keys ( $this->configData ["waypoints"] );
							while ( $aaa = each ( $keys ) )
								$match_new [] = $this->configData ["waypoints"] [$aaa [1]];
							$this->configData ["waypoints"] = $match_new;
							unset ( $match_new );
							$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "waypoint-delete-complete" ) );
						}
						$this->refreshList ();
						break;
					case $this->get ( "waypoint-list" ) :
						if (isset ( $args [1] ) and is_numeric ( $args [1] ) and $index != 0) {
							$this->WaypointList ( $player, $args [1] );
						} else {
							$this->WaypointList ( $player );
						}
						break;
					case $this->get ( "waypoint-clear" ) :
						$this->configData ["waypoints"] = [ ];
						$player->sendMessage ( $this->get ( "waypoint-clear-complete" ) );
						$this->refreshList ();
						break;
					case $this->get ( "waypoint-onoff" ) :
						if ($this->configData ["enable"] == true) {
							$this->configData ["enable"] = false;
							$player->sendMessage ( $this->get ( "waypoint-disabled" ) );
						} else {
							$this->configData ["enable"] = true;
							$player->sendMessage ( $this->get ( "waypoint-enabled" ) );
						}
						break;
				}
				break;
		}
		return true;
	}
	public function refreshList() {
		$list = "";
		foreach ( $this->configData ["waypoints"] as $index => $message )
			$list = "L" . $index . " M" . $message . "\\n";
		$this->waypointList = $list;
	}
	public function WaypointList(CommandSender $player, $index = 1) {
		$once_print = 5;
		$target = $this->configData ["waypoints"];
		
		$index_count = count ( $target );
		$index_key = array_keys ( $target );
		$full_index = floor ( $index_count / $once_print );
		
		if ($index_count > $full_index * $once_print) $full_index ++;
		
		if ($index <= $full_index) {
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "now-list-show" ) . " ({$index}/{$full_index}) " . $this->get ( "index_count" ) . ": {$index_count}" );
			$message = null;
			for($for_i = $once_print; $for_i >= 1; $for_i --) {
				$now_index = $index * $once_print - $for_i;
				if (! isset ( $index_key [$now_index] )) break;
				$now_key = $index_key [$now_index];
				$message .= TextFormat::DARK_AQUA . "[" . $now_key . "] : " . $target [$now_key] . "\n";
			}
			$player->sendMessage ( $message );
		} else {
			$player->sendMessage ( TextFormat::RED . $this->get ( "there-is-no-list" ) );
			return;
		}
	}
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		// $ev->getPacket ()->printDump ();
		if ($this->configData ["enable"] == false) return;
		if (count ( $this->configData ["waypoints"] ) == 0) return;
		if (! isset ( explode ( "#Waypoint ", $ev->getPacket ()->data )[1] )) return;
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