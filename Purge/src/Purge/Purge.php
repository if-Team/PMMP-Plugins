<?php

namespace Purge;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;
use pocketmine\entity\Creature;
use pocketmine\scheduler\CallbackTask;

class Purge extends PluginBase implements Listener {
	public $purgeStarted = false;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"purgeSchedule" ] ), 100 );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function purgeSchedule() {
		// 0~13999 - DAY
		// 14000-22999 - NIGHT
		// 23000-24000 - DAY
		$time = $this->getServer ()->getDefaultLevel ()->getTime ();
		if ($this->purgeStarted) {
			if ((Level::TIME_DAY <= $time and $time < Level::TIME_NIGHT) or (Level::TIME_SUNRISE <= $time and $time <= Level::TIME_FULL)) $this->purgeStop ();
		} else {
			if (Level::TIME_NIGHT <= $time and $time < Level::TIME_SUNRISE) $this->purgeStart ();
		}
	}
	public function purgeStart() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$this->alert ( $player, $this->get ( "purge-is-started" ) );
			$this->purgeStarted = true;
		}
	}
	public function purgeStop() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$this->alert ( $player, $this->get ( "purge-is-stopped" ) );
			$this->purgeStarted = false;
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if (! $event instanceof EntityDamageByEntityEvent) return;
		
		if ($this->purgeStarted) {
			if ($event->getEntity () instanceof Player) {
				$event->setCancelled ();
			} else if ($event->getDamager () instanceof Player) {
				$this->alert ( $event->getDamager (), $this->get ( "cant-pvp-in-night" ) );
				$event->setCancelled ();
			}
		}
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}
?>