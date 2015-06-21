<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-20 17:04
 */

namespace chalk\cameraman;

use chalk\cameraman\movement\StraightMovement;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Cameraman extends PluginBase implements Listener {
    /** @var Cameraman */
    private static $instance = null;

    const TICKS_PER_SECOND = 20;

    /** @var Vector3[][] */
    private $waypoints = [];

    /** @var Camera[] */
    private $cameras = [];

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
     * @param Vector3[] $waypoints
     * @return Movement[]
     */
    public static function createStraightMovements(array $waypoints){
        $lastWaypoint = null;

        $movements = [];
        foreach($waypoints as $waypoint){
            if($lastWaypoint !== null and !$waypoint->equals($lastWaypoint)){
                $movements[] = new StraightMovement($lastWaypoint, $waypoint);
            }
            $lastWaypoint = $waypoint;
        }
        return $movements;
    }

    /**
     * @param CommandSender $sender
     * @param string $command
     * @return boolean
     */
    public function sendHelpMessages(CommandSender $sender, $command = ""){
        $command = strToLower($command);

        if(!$command or $command === "p"){
            $sender->sendMessage("/cam p - Adds a waypoint at the current position");
        }

        if(!$command or $command === "start"){
            $sender->sendMessage("/cam start <slowness> - Travels the path in the given slowness. e.g. /cam start 10");
        }

        if(!$command or $command === "stop"){
            $sender->sendMessage("/cam stop - Interrupts travelling");
        }

        if(!$command or $command === "goto"){
            $sender->sendMessage("/cam goto <index> - Teleports to the specified waypoint");
        }

        if(!$command or $command === "clear"){
            $sender->sendMessage("/cam clear - Removes all waypoints");
        }
        
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
        if(!$sender instanceof Player){
            $sender->sendMessage("Please issue this command in-game!");
            return true;
        }

        if(!is_array($args) or count($args) < 1 or !is_string($args[0])){
            return $this->sendHelpMessages($sender);
        }

        $key = strToLower($sender->getName());

        switch(strToLower($args[0])){
            default:
                return $this->sendHelpMessages($sender);

            case "p":
                if(!isset($this->waypoints[$key])){
                    $this->waypoints[$key] = [];
                }

                $this->waypoints[$key][] = $sender->getPosition()->floor()->add(0.5, 0, 0.5);
                $sender->sendMessage("Added Waypoint #" . count($this->waypoints[$key]));
                break;

            case "start":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(isset($this->cameras[$key]) and $this->cameras[$key]->isRunning()){
                    $this->cameras[$key]->stop();
                    $sender->sendMessage("Interrupting current travels...");
                }

                $slowness = doubleval($args[1]);
                if($slowness <= 0){
                    $sender->sendMessage("The value of slowness cannot be zero or negative!");
                    return true;
                }

                $this->cameras[$key] = new Camera($sender, Cameraman::createStraightMovements($this->waypoints[$key]), $slowness);
                $this->cameras[$key]->start();
                $sender->sendMessage("Travelling started!");
                break;

            case "stop":
                if(!isset($this->cameras[$key]) or !$this->cameras[$key]->isRunning()){
                    $sender->sendMessage("Travels are already interrupted!");
                    return true;
                }

                $this->cameras[$key]->stop();
                unset($this->cameras[$key]);

                $sender->sendMessage("Travelling has been interrupted!");
                break;

            case "goto":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(!isset($this->waypoints[$key])){
                    $sender->sendMessage("There are no waypoints to teleport!");
                    return true;
                }

                $index = intval($args[1]);
                if(count($this->waypoints[$key]) < $index){
                    $sender->sendMessage("The index is out of bounds!");
                    return true;
                }

                $sender->setPosition($this->waypoints[$key][$index - 1]);
                $sender->sendMessage("Teleported to Waypoint #" . $index . "!");
                break;

            case "clear":
                unset($this->waypoints[$key]);
                $sender->sendMessage("All waypoints has been removed!");
                break;
        }
        return true;
    }
}