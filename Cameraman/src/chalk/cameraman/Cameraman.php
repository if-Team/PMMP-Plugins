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
    private $waypointMap = [];

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
    public function getWaypointMap(){
        return $this->waypointMap;
    }

    /**
     * @param Location[][] $waypointMap
     * @return Location[][]
     */
    public function setWaypointMap(array $waypointMap){
        $this->waypointMap = $waypointMap;
        return $waypointMap;
    }

    /**
     * @param Player $player
     * @return Location[]
     */
    public function getWaypoints(Player $player){
        return isset($this->getWaypointMap()[$player->getName()]) ? $this->getWaypointMap()[$player->getName()] : null;
    }

    /**
     * @param Player $player
     * @param Location[] $waypoints
     * @return Location[]
     */
    public function setWaypoints(Player $player, array $waypoints){
        $this->getWaypointMap()[$player->getName()] = $waypoints;
        return $waypoints;
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
        return isset($this->getCameras()[$player->getName()]) ? $this->getCameras()[$player->getName()] : null;
    }

    /**
     * @param Player $player
     * @param Camera $camera
     * @return Camera
     */
    public function setCamera(Player $player, Camera $camera){
        $this->getCameras()[$player->getName()] = $camera;
        return $camera;
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
     * @return bool
     */
    public function sendMessage(CommandSender $sender, $message, $color = TextFormat::GREEN){
        $sender->sendMessage(TextFormat::BOLD . TextFormat::DARK_GREEN . "[Cameraman] " . TextFormat::RESET . $color . $message);

        return true;
    }

    /**
     * @param CommandSender $sender
     * @return bool
     */
    public function sendAboutMessages(CommandSender $sender){
        $sender->sendMessage(TextFormat::BOLD . TextFormat::DARK_GREEN . "[Cameraman v" . $this->getDescription()->getVersion() . "]");
        $sender->sendMessage(TextFormat::GREEN . "Author: " . $this->getDescription()->getAuthors()[0]);
        $sender->sendMessage(TextFormat::GREEN . "Website: " . $this->getDescription()->getWebsite());

        return true;
    }

    /**
     * @param CommandSender $sender
     * @param string $command
     * @return bool
     */
    public function sendHelpMessages(CommandSender $sender, $command = ""){
        $command = strToLower($command);

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

        if(!$command or $command === "help"){
            $this->sendMessage($sender, "/cam help", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Shows the help menu of commands");
        }

        if(!$command or $command === "about"){
            $this->sendMessage($sender, "/cam about", TextFormat::DARK_GREEN);
            $this->sendMessage($sender, "Shows the information of this plugin");
        }
        
        return true;
    }

    /**
     * @param CommandSender $sender
     * @param Vector3 $waypoint
     * @param int index
     * @return bool
     */
    public function sendWaypointMessage(CommandSender $sender, Vector3 $waypoint, $index){
        $this->sendMessage($sender, "Waypoint #" . $index . " - [" . $waypoint->getFloorX() . ", " . $waypoint->getFloorY() . ", " . $waypoint->getFloorZ() . "]");

        return true;
    }

    /**
     * @param int $index
     * @param array $array
     * @param CommandSender $sender
     * @return bool
     */
    public function isIndexOutOfBounds($index, array $array, CommandSender $sender = null){
        if($index < 1 or $index > count($array)){
            if($sender !== null){
                $this->sendMessage($sender, "The index is out of bounds! (total: " . count($array) . ")", TextFormat::RED);
            }
            return true;
        }
        return false;
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $commandAlias
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, $commandAlias, array $args){
        if(!$sender instanceof Player){
            $this->sendMessage($sender, "Please issue this command in-game!", TextFormat::RED);
            return true;
        }

        if($commandAlias === "p"){
            //shortcut for /cam p
            array_unshift($args, "p");
        }

        if(count($args) < 1){
            return $this->sendHelpMessages($sender);
        }

        switch(strToLower($args[0])){
            default:
                $this->sendMessage($sender, "Unknown command!", TextFormat::RED);
                $this->sendMessage($sender, "Try " . TextFormat::BOLD . "/cam help" . TextFormat::RESET . TextFormat::RED . " for a list of commands", TextFormat::RED);
                break;

            case "help":
                return $this->sendHelpMessages($sender);

            case "about":
                return $this->sendAboutMessages($sender);

            case "p":
                if(($waypoints = $this->getWaypoints($sender)) === null){
                    $waypoints = $this->setWaypoints($sender, []);
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    if($this->isIndexOutOfBounds($index = intval($args[1]), $waypoints, $sender)){
                        return true;
                    }

                    $waypoints[$index - 1] = $sender->getLocation();
                    $this->sendMessage($sender, "Reset Waypoint #" . $index . " (total: " . count($waypoints) . ")");
                }else{
                    $waypoints[] = $sender->getLocation();
                    $this->sendMessage($sender, "Added Waypoint #" . count($waypoints));
                }
                break;

            case "start":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) < 2){
                    $this->sendMessage($sender, "You should set at least two waypoints!", TextFormat::RED);
                    return $this->sendHelpMessages($sender, "p");
                }

                if(($camera = $this->getCamera($sender)) !== null and $camera->isRunning()){
                    $camera->stop();
                    $this->sendMessage($sender, "Interrupting current travels...", TextFormat::GRAY);
                }

                if(($slowness = doubleval($args[1])) < 0.0000001){
                    return $this->sendMessage($sender, "The slowness must be positive! (current: " . $slowness . ")", TextFormat::RED);
                }

                /*if($sender->getGamemode() !== 1){
                    $this->sendMessage($sender, "You should set your gamemode to creative!", TextFormat::RED);
                    return true;
                }*/

                $this->setCamera($sender, new Camera($sender, Cameraman::createStraightMovements($waypoints), $slowness))->start();
                $this->sendMessage($sender, "Travelling started! (make sure you are flying)");
                break;

            case "stop":
                if(($camera = $this->getCamera($sender)) === null or !$camera->isRunning()){
                    return $this->sendMessage($sender, "Travels are already interrupted!", TextFormat::RED);
                }

                $camera->stop(); unset($camera);
                $this->sendMessage($sender, "Travelling has been interrupted!");
                break;

            case "info":
                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) === 0){
                    return $this->sendMessage($sender, "There are no waypoints to show!", TextFormat::RED);
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    if($this->isIndexOutOfBounds($index = intval($args[1]), $waypoints, $sender)){
                        return true;
                    }

                    $this->sendWaypointMessage($sender, $waypoints[$index - 1], $index);
                }else{
                    foreach($waypoints as $index => $waypoint){
                        $this->sendWaypointMessage($sender, $waypoint, $index + 1);
                    }
                }
                break;

            case "goto":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) === 0){
                    return $this->sendMessage($sender, "There are no waypoints to teleport!", TextFormat::RED);
                }

                if($this->isIndexOutOfBounds($index = intval($args[1]), $waypoints, $sender)){
                    return true;
                }

                $sender->teleport($waypoints[$index - 1]);
                $this->sendMessage($sender, "Teleported to Waypoint #" . $index . "!");
                break;

            case "clear":
                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) === 0){
                    return $this->sendMessage($sender, "There are no waypoints to remove!", TextFormat::RED);
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    if($this->isIndexOutOfBounds($index = intval($args[1]), $waypoints, $sender)){
                        return true;
                    }

                    array_splice($waypoints, $index - 1, 1);
                    $this->sendMessage($sender, "Waypoint #" . $index . " has been removed! (total: " . count($waypoints) . ")");
                }else{
                    unset($waypoints);
                    $this->sendMessage($sender, "All waypoints has been removed!");
                }
                break;
        }
        return true;
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event){
        if($event->getPacket() instanceof MovePlayerPacket and ($camera = $this->getCamera($event->getPlayer())) !== null and $camera->isRunning()){
            $event->setCancelled(true);
        }
    }

    /**
     * @param Player $player
     * @return bool|int
     */
    public static function sendMovePlayerPacket(Player $player){
        $packet = new MovePlayerPacket();
        $packet->eid = 0;
        $packet->x = $player->getX();
        $packet->y = $player->getY();
        $packet->z = $player->getZ();
        $packet->yaw = $player->getYaw();
        $packet->bodyYaw = $player->getYaw();
        $packet->pitch = $player->getPitch();
        $packet->onGround = false;

        return $player->dataPacket($packet);
    }
}