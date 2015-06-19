<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 19:27
 */

namespace ifteam\Chatty;

use pocketmine\scheduler\PluginTask;

class ChattyTask extends PluginTask {
    function __construct(Chatty $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner Chatty */
        $owner = $this->getOwner();
        $owner->tick();
    }
}
