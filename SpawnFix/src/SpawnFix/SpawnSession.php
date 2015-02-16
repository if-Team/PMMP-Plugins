<?php

namespace SpawnFix;

use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\level\Level;

class SpawnSession{
    private $x, $y, $z;

    public function LocationSave(Player $player){
        //XYZ값 삽입
        if ($player instanceof Player) {
        	$this->x = (int) $player->x;
        	$this->y = (int) $player->y;
        	$this->z = (int) $player->z;
        }
    }
    public function ReSpawn(Player $player){
    	$pos = new Position($this->x, $this->y+3, $this->z, $player->getLevel());
        $player->teleport($pos);
    }
}