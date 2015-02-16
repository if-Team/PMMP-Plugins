<?php

namespace SignCommand;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\BlockBreakEvent;

class SignCommand extends PluginBase implements Listener {
	public $config, $configData;
	public function onEnable() {
		$this->initMessage ();
		
		$this->config = new Config ( $this->getDataFolder () . "config.yml", Config::YAML );
		$this->configData = $this->config->getAll ();
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->configData );
		$this->config->save ();
	}
	public function SignPlace(SignChangeEvent $event) {
		if ($event->getLine ( 0 ) != $this->get ( "sign-set-message" ) and $event->getLine ( 0 ) != $this->get ( "sign-message" )) return;
		$block = $event->getBlock ();
		if ($event->getLine ( 1 ) == null) {
			$event->getPlayer ()->sendMessage ( TextFormat::RED . $this->get ( "command-zero" ) );
			return;
		}
		if (isset ( explode ( "/", $event->getLine ( 1 ), 2 )[1] )) {
			$this->configData ["{$block->x}:{$block->y}:{$block->z}"] = explode ( "/", $event->getLine ( 1 ), 2 )[1];
			$event->setLine ( 0, $this->get ( "sign-message" ) );
		} else {
			$this->configData ["{$block->x}:{$block->y}:{$block->z}"] = $event->getLine ( 1 );
			$event->setLine ( 0, $this->get ( "sign-message" ) );
		}
	}
	public function SignBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->configData ["{$block->x}:{$block->y}:{$block->z}"] )) unset ( $this->configData ["{$block->x}:{$block->y}:{$block->z}"] );
	}
	public function onTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->configData ["{$block->x}:{$block->y}:{$block->z}"] )) $this->getServer ()->getCommandMap ()->dispatch ( $event->getPlayer (), $this->configData ["{$block->x}:{$block->y}:{$block->z}"] );
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
}

?>