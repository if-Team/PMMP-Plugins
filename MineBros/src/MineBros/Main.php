<?php

namespace MineBros;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use MineBros\character\CharacterLoader;

class Main extends PluginBase {

    public $characterLoader;
    private $status = false;

    public function onEnable(){
        $this->characterLoader = new CharacterLoader($this);
        $this->getServer()->getPluginManager()->registerEvents($this->characterLoader, $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function isStarted(){
        return $this->status;
    }

    public function onJoin(PlayerJoinEvent $ev){
        $this->characterLoader->nameDict[$ev->getPlayer()->getName] = NULL;
    }

    public function onQuit(PlayerQuitEvent $ev){
        unset($name = $this->characterLoader->nameDict[$ev->getPlayer()->getName]);
    }

    public function startGame(){

    }

    public function endGame(){
        
    }

}