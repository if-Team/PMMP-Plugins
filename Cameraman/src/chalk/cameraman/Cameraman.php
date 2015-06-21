<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:04
 */

namespace chalk\cameraman;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;

class Cameraman extends PluginBase implements Listener {
    /** @var Cameraman */
    private static $instance = null;

    const TICKS_PER_SECOND = 10;

    /**
     * @return Cameraman
     */
    public static function getInstance(){
        return self::$instance;
    }

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @param CommandSender $sender
     * @return boolean
     */
    public function sendHelpMessages(CommandSender $sender){
        $sender->sendMessage("/cam p - Adds a waypoint at the current position");
        $sender->sendMessage("/cam start <slowness> - Travels the path in the given slowness. e.g. /cam start 10");
        return true;
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $commandAlias
     * @param array $args
     * @return boolean
     */
    public function onCommand(CommandSender $sender, Command $command, $commandAlias, array $args){
        if(!is_array($args) or count($args) < 1 or !is_string($args[0])){
            return $this->sendHelpMessages($sender);
        }

        return true;
    }

}