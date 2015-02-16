<?php

namespace OnlyASCII;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\CommandExecutor;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\Config;

class OnlyASCII extends PluginBase implements CommandExecutor, Listener {
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->config = (new Config ( $this->getDataFolder () . "config.yml", Config::YAML, array (
				"Enable" => "true",
				"Announce1" => "This server only support ASCII Code",
				"Announce2" => "Please using english or special letters" 
		) ))->getAll ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getLogger ()->info ( "Loaded" );
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $params) {
		switch ($command->getName ()) {
			case "OnlyASCII" :
				$sub = array_shift ( $params );
				
				if (trim ( $sub ) === "") {
					usage:
					$sender->sendMessage ( "Usage: /OnlyASCII <enable | disable>" );
					return true;
				}
				switch ($sub) {
					case "enable" :
					case "Enable" :
						$this->config ["Enable"] = "true";
						$sender->sendMessage ( "[OnlyASCII] OnlyASCII Enabled!" );
						break;
					case "disable" :
					case "Disable" :
						$this->config ["Enable"] = "false";
						$sender->sendMessage ( "[OnlyASCII] OnlyASCII Disabled!" );
						break;
						break;
					default :
						goto usage;
				}
				return true;
		}
	}
	public function onChatEvent(PlayerChatEvent $event) {
		$message = $event->getMessage ();
		$sender = $event->getPlayer ();
		
		if ($this->config ["Enable"] == "true") {
			$n = 0;
			while ( 1 ) {
				$slice = ord ( substr ( $message, $n, 1 ) );
				if ($slice == 0)
					break;
				if (($slice <= 126) == 0) { // $slice >= 33 &&
					$sender->sendMessage ( $this->config ["Announce1"] );
					$sender->sendMessage ( $this->config ["Announce2"] );
					$event->setCancelled ( true );
					break;
				}
				$n ++;
			}
		} else
			$event->setMessage ( $message );
	}
	public function onDisable() {
		$config = new Config ( $this->getDataFolder () . "config.yml", Config::YAML ));
		$config->setAll ( $this->config );
		$config->save ();
	}
}

