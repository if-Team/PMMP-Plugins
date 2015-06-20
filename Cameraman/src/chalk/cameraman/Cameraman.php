<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:04
 */

namespace chalk\cameraman;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

class Cameraman extends PluginBase implements Listener {
    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
}