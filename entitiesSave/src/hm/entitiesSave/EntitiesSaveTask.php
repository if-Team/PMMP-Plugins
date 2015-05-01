<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:11
 */

namespace hm\entitiesSave;

use pocketmine\scheduler\PluginTask;

class EntitiesSaveTask extends PluginTask {
    function __construct(entitiesSave $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner entitiesSave */
        $owner = $this->getOwner();
        $owner->entitiesSave();
    }
}