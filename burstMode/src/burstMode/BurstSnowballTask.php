<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:44
 */

namespace burstMode;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class BurstSnowballTask extends PluginTask {
    /** @var Player */
    private $player = null;

    function __construct(burstMode $owner, $player){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner burstMode */
        $owner = $this->getOwner();
        $owner->burstSnowball($this->player);
    }
}