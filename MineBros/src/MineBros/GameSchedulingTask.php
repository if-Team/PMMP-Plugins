<?php

namespace MineBros;

use pocketmine\scheduler\PluginTask;

class GameSchedulingTask extends PluginTask {

    public function __construct(Main $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        $this->owner->minuteSchedule();
    }

}
