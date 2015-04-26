<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:54
 */

namespace EDGE;

use pocketmine\scheduler\PluginTask;

class EDGETask extends PluginTask {
    function __construct(EDGE $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner EDGE */
        $owner = $this->getOwner();
        $owner->EDGE();
    }
}