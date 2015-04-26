<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:31
 */

namespace hm\motdOnline;

use pocketmine\scheduler\PluginTask;

class UpdateServerNameTask extends PluginTask {
    function __construct(motdOnline $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner motdOnline */
        $owner = $this->getOwner();
        $owner->updateServerName();
    }
}