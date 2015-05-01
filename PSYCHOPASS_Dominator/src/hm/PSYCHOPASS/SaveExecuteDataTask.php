<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:36
 */

namespace hm\PSYCHOPASS;

use pocketmine\scheduler\PluginTask;

class SaveExecuteDataTask extends PluginTask {
    function __construct(PSYCHOPASS_Dominator $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner PSYCHOPASS_Dominator */
        $owner = $this->getOwner();
        $owner->saveExecuteData();
    }
}