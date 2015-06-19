<?php

namespace MineBros;

use \pocketmine\Player;
use \pocketmine\event\entity\EntityRegainHealthEvent;
use \pocketmine\event\entity\EntityDamageByEntityEvent;
use \pocketmine\scheduler\PluginTask;
use MineBros\character\BaseCharacter;

class ProgressiveExecutionTask extends PluginTask {
    
    private $htask = array();
    private $timer = array();
    private $odd = false;

    public function __construct(Main $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        foreach($this->htask as $key => &$h){
            if($h[4]) $h[2]->heal($h[1], new EntityRegainHealthEvent($h[2], $h[1], BaseCharacter::EV_CAUSE_TASK));
                else $h[2]->attack($h[1], new EntityDamageByEntityEvent($h[3], $h[2], BaseCharacter::EV_CAUSE_TASK, $h[1], $h[5]));
                if(--$h[0] <= 0) unset($this->htask[$key]);
        }
        foreach($this->timer as &$t){
            if(--$t[0] <= 0) $t[1]->$t[2]($t[3]);
        }
        if($this->odd){
            foreach($owner->characterLoader->cooldown as $key => &$c){
                if(--$c <= 0) unset($owner->characterLoader->cooldown[$c]);
            }
            $this->odd = !$this->odd;
        }
    }

    public function addHealTask($ticks, $amount, Player $who, $cause = NULL, $heal = true, $knockback = 0){
        $this->htask[] = $heal ? array($ticks, $amount, $who, $cause, $heal) : array($ticks, $amount, $who, $cause, $heal, $knockback);
    }

    public function addTimer(BaseCharacter $obj, $callback, $time, $args){
        $this->timer[] = array($time*2, $obj, $callback, $args);
    }

}
