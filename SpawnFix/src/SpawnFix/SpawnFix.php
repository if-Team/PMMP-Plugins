<?php

namespace SpawnFix;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;

class SpawnFix extends PluginBase implements Listener{
	public $s;
	public function onEnable(){
		$this->s = [];
		$this->getLogger()->info("Loaded");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	public function QuitLocation(PlayerQuitEvent $event){
        $this->s[$event->getPlayer()->getName()] = new SpawnSession($this);
        $this->s[$event->getPlayer()->getName()]->LocationSave($event->getPlayer());
	}
	public function SpawnFix(PlayerJoinEvent $event){
		if(isset($this->s[$event->getPlayer()->getName()])){
			$this->s[$event->getPlayer()->getName()]->ReSpawn($event->getPlayer());
		}
	}
}