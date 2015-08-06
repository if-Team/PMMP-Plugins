<?php

namespace Khinenw\SpeedBlock\task;

use Khinenw\SpeedBlock\SpeedBlock;
use pocketmine\scheduler\PluginTask;

class FlyingCheck extends PluginTask{

	public function onRun($currentTick){
		foreach(SpeedBlock::getInstance()->flyingPlayers as $name => $player){
			if($player["player"]->isOnGround()){
				SpeedBlock::getInstance()->flyingPlayers[$name]["lastground"] = time();
			}
		}
	}
}