<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-26 18:56
 */

namespace emailAuth;

use pocketmine\scheduler\PluginTask;

class AutoSaveTask extends PluginTask {
    function __construct(emailAuth $owner){
        parent::__construct($owner);
    }

    public function onRun($currentTick){
        /** @var $owner emailAuth */
        $owner = $this->getOwner();
        $owner->autoSave();
    }
}