<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:39
 */

namespace hm\PSYCHOPASS;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class KickExecuteTask extends PluginTask {
    /** @var Player */
    private $player = null;

    function __construct(PSYCHOPASS_Dominator $owner, Player $player){
        parent::__construct($owner);
        $this->player = $player;
    }

    public function onRun($currentTick){
        /** @var $owner PSYCHOPASS_Dominator */
        $owner = $this->getOwner();
        $owner->KickExecute($this->player);
    }
}