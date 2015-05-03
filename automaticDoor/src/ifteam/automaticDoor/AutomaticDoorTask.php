<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 17:45
 */

namespace ifteam\automaticDoor;

use pocketmine\scheduler\PluginTask;

class AutomaticDoorTask extends PluginTask {
    function __construct(automaticDoor $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner automaticDoor */
        $owner = $this->getOwner();
        $owner->automaticDoor();
    }
}