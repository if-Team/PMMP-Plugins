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
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Cameraman extends PluginBase implements Listener {
    /** @var Cameraman */
    private static $instance = null;

    const TICKS_PER_SECOND = 10;

    /** @var Location[][] */
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
     * @return Location[][]
     */
    public function getWaypoints(){
        return $this->waypoints;
    }

    /**
     * @param Player $player
     * @return Location[]
     */
    public function getWaypoint(Player $player){
        $key = strToLower($player->getName());
        return isset($this->getWaypoints()[$key]) ? $this->getWaypoints()[$key] : null;
    }

    /**
     * @return Camera[]
     */
    public function getCameras(){
        return $this->cameras;
    }

    /**
     * @param Player $player
     * @return Camera|null
     */
    public function getCamera(Player $player){
        $key = strToLower($player->getName());
        return isset($this->getCameras()[$key]) ? $this->getCameras()[$key] : null;
    }

    /**
     * @param Location[] $waypoints
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
     * @param string $message
     * @param string $color
     * @return boolean
     */
    public function sendMessage(CommandSender $sender, $message, $color = TextFormat::GREEN){
        $sender->sendMessage(TextFormat::BOLD . TextFormat::DARK_GREEN . "[Cameraman] " . TextFormat::RESET . $color . $message);

        return true;
    }

    /**
     * @param CommandSender $sender
     * @param string $command
     * @return boolean
     */
    public function sendHelpMessages(CommandSender $sender, $command = ""){
        $command = strToLower($command);

        if(!$command or $command === "help"){
            $this->sendMessage($sender, "/cam help", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Shows the help menu of commands");
        }

        if(!$command or $command === "p"){
            $this->sendMessage($sender, "/cam p [index]", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Adds a waypoint at the current position");
        }

        if(!$command or $command === "start"){
            $this->sendMessage($sender, "/cam start <slowness>", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Travels the path in the given slowness");
        }

        if(!$command or $command === "stop"){
            $this->sendMessage($sender, "/cam stop", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Interrupts travelling");
        }

        if(!$command or $command === "stat"){
            $this->sendMessage($sender, "/cam info [index]", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Shows the information of current waypoints");
        }

        if(!$command or $command === "goto"){
            $this->sendMessage($sender, "/cam goto <index>", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Teleports to the specified waypoint");
        }

        if(!$command or $command === "clear"){
            $this->sendMessage($sender, "/cam clear [index]", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Removes all or specific waypoints");
        }
        
        return true;
    }

    /**
     * @param CommandSender $sender
     * @param Vector3 $waypoint
     * @param int index
     * @return boolean
     */
    public function sendWaypointMessage(CommandSender $sender, Vector3 $waypoint, $index){
        $this->sendMessage($sender, "Waypoint #" . $index . " - [" . $waypoint->getFloorX() . ", " . $waypoint->getFloorY() . ", " . $waypoint->getFloorZ() . "]");

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
            $this->sendMessage($sender, "Please issue this command in-game!", TextFormat::RED);
            return true;
        }

        if($commandAlias === "p"){ //=> shortcut for /cam p
            $args = ["p"] + $args;
        }else if(!is_array($args) or count($args) < 1 or !is_string($args[0])){
            return $this->sendHelpMessages($sender);
        }

        $key = strToLower($sender->getName());

        switch(strToLower($args[0])){
            default:
                return $this->sendMessage($sender, "Unknown command. Try " . TextFormat::UNDERLINE . "/cam help" . TextFormat::RESET . TextFormat::RED . " for a list of commands", TextFormat::RED);

            case "help":
                return $this->sendHelpMessages($sender);

            case "p":
                if(!isset($this->waypoints[$key])){
                    $this->waypoints[$key] = [];
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    $index = intval($args[1]);
                    if($index < 1 or $index > count($this->waypoints[$key])){
                        $this->sendMessage($sender, "The index is out of bounds! (total: " . count($this->waypoints[$key]) . ")", TextFormat::RED);
                        return true;
                    }

                    $this->waypoints[$key][$index - 1] = $sender->getLocation();
                    $this->sendMessage($sender, "Reset Waypoint #" . $index . " (total: " . count($this->waypoints[$key]) . ")");
                }else{
                    $this->waypoints[$key][] = $sender->getLocation();
                    $this->sendMessage($sender, "Added Waypoint #" . count($this->waypoints[$key]));
                }
                break;

            case "start":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(!isset($this->waypoints[$key]) or count($this->waypoints[$key]) < 2){
                    $this->sendMessage($sender, "You should set at least two waypoints!", TextFormat::RED);
                    return $this->sendHelpMessages($sender, "p");
                }

                if(isset($this->cameras[$key]) and $this->cameras[$key]->isRunning()){
                    $this->cameras[$key]->stop();
                    $this->sendMessage($sender, "Interrupting current travels...", TextFormat::GRAY);
                }

                $slowness = doubleval($args[1]);
                if($slowness < 0.0000001){
                    $this->sendMessage($sender, "The slowness must be positive! (current: " . $slowness . ")", TextFormat::RED);
                    return true;
                }

                if($sender->getGamemode() !== 1){
                    $this->sendMessage($sender, "You should set your gamemode to creative!", TextFormat::RED);
                    return true;
                }

                $this->cameras[$key] = new Camera($sender, Cameraman::createStraightMovements($this->waypoints[$key]), $slowness);
                $this->cameras[$key]->start();
                $this->sendMessage($sender, "Travelling started! (make sure you are flying)");
                break;

            case "stop":
                if(!isset($this->cameras[$key]) or !$this->cameras[$key]->isRunning()){
                    $this->sendMessage($sender, "Travels are already interrupted!", TextFormat::RED);
                    return true;
                }

                $this->cameras[$key]->stop();
                unset($this->cameras[$key]);

                $this->sendMessage($sender, "Travelling has been interrupted!");
                break;

            case "info":
                if(!isset($this->waypoints[$key])){
                    $this->sendMessage($sender, "There are no waypoints to show!", TextFormat::RED);
                    return true;
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    $index = intval($args[1]);
                    if($index < 1 or $index > count($this->waypoints[$key])){
                        $this->sendMessage($sender, "The index is out of bounds! (total: " . count($this->waypoints[$key]) . ")", TextFormat::RED);
                        return true;
                    }

                    $this->sendWaypointMessage($sender, $this->waypoints[$key][$index - 1], $index);
                }else{
                    foreach($this->waypoints[$key] as $index => $waypoint){
                        $this->sendWaypointMessage($sender, $waypoint, $index + 1);
                    }
                }
                break;

            case "goto":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(!isset($this->waypoints[$key])){
                    $this->sendMessage($sender, "There are no waypoints to teleport!", TextFormat::RED);
                    return true;
                }

                $index = intval($args[1]);
                if($index < 1 or $index > count($this->waypoints[$key])){
                    $this->sendMessage($sender, "The index is out of bounds! (total: " . count($this->waypoints[$key]) . ")", TextFormat::RED);
                    return true;
                }

                $sender->teleport($this->waypoints[$key][$index - 1]);
                $this->sendMessage($sender, "Teleported to Waypoint #" . $index . "!");
                break;

            case "clear":
                if(!isset($this->waypoints[$key])){
                    $this->sendMessage($sender, "There are no waypoints to remove!", TextFormat::RED);
                    return true;
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    $index = intval($args[1]);
                    if($index < 1 or $index > count($this->waypoints[$key])){
                        $this->sendMessage($sender, "The index is out of bounds! (total: " . count($this->waypoints[$key]) . ")", TextFormat::RED);
                        return true;
                    }

                    array_splice($this->waypoints[$key], $index - 1, 1);
                    $this->sendMessage($sender, "Waypoint #" . $index . " has been removed! (total: " . count($this->waypoints[$key]) . ")");
                }else{
                    unset($this->waypoints[$key]);
                    $this->sendMessage($sender, "All waypoints has been removed!");
                }
                break;
        }
        return true;
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event){
        if($event->getPacket() instanceof MovePlayerPacket){
            $camera = $this->getCamera($event->getPlayer());
            if($camera !== null and $camera->isRunning()){
               $event->setCancelled(true);
            }
        }
    }
}