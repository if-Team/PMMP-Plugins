<?php

namespace MineBros;

use pocketmine\scheduler\PluginTask;

class PassiveSkillTask extends PluginTask {

    public function __construct(Main $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        //if(!$this->owner->status) return;
        $this->owner->characterLoader->onPassiveTick($currentTick);
    }
}
