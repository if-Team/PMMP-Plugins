<?php

namespace MineBros\character;

use pocketmine\Player;
use pocketmine\math\Vector3;

abstract class BaseCharacter {

    const CLASS_B     = 0b1;
    const CLASS_A     = 0b10;
    const CLASS_S     = 0b100;
    const CLASS_PLUS  = 0b1000;
    const TRIGR_TOUCH = 0b10000;
    const TRIGR_PONLY = 0b100000;
    const TRIGR_PASIV = 0b1000000;
    const TRIGR_CUSTM = 0b10000000; //Not now, should be implemented in future
    const EV_CAUSE_TASK = 15;

    public static $owner;

    private $description;

    public function init(){

    }

    public function getOptions(){
        return 0;
    }

    public function getName(){
        return get_called_class();
    }

    public function getDescription(){
        return '';
    }

    public function onTouchAnything(Player $who, $targetIsPlayer = false, Vector3 $pos, $targetPlayer = NULL){

    }

    public function onHarmPlayer(Player $victim, Player $damager){

    }

    public function onPassiveTick(Player $who, $currentTick){

    }

    final private function getProgressiveExecutionTask(){
        return \MineBros\Main::$pet;
    }

}
