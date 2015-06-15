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

    private $bitmask, $name, $description;

    public function __construct(){

    }

    public function getOptions(){
        return $this->bitmask;
    }

    public function getName(){
        return $this->name;
    }

    public function getDescription(){
        return $this->description;
    }

    public function onTouchAnything(Player $who, $targetIsPlayer = false, Vector3 $pos, $targetPlayer = NULL){

    }

    public function onHarmPlayer(Player $victim, Player $damager){

    }

    public function onPassiveTick(Player $who, $currentTick){

    }

}
