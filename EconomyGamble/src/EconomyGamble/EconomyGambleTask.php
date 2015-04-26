<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:09
 */

namespace EconomyGamble;

use pocketmine\scheduler\PluginTask;

class EconomyGambleTask extends PluginTask {
    function __construct(EconomyGamble $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner EconomyGamble */
        $owner = $this->getOwner();
        $owner->EconomyGamble();
    }
}