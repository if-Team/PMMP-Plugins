<?php

namespace chalk\goawayanna;

use chalk\utils\Messages;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\Network;
use pocketmine\utils\Config;

class GoAwayAnna extends PluginBase implements Listener {
    /** @var Messages */
    private $messages = [];

    /** @var string */
    private $ip;

    /** @var int */
    private $port;

    public function onEnable(){
        $this->loadConfig();
        $this->loadMessages();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info($this->getMessages()->getMessage("current-address", ["ip" => $this->getIp(), "port" => $this->getPort()]));
    }

    public function onDisable(){
        $this->saveConfig();
    }

    public function loadConfig(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->ip = $this->getConfig()->get("ip", "127.0.0.1");
        $this->port = $this->getConfig()->get("port", 19132);
    }

    public function saveConfig(){
        $this->getConfig()->set("ip", $this->getIp());
        $this->getConfig()->set("port", $this->getPort());
        parent::saveConfig();
    }

    /**
     * @param bool $override
     */
    public function loadMessages($override = false){
        $this->saveResource("messages.yml", $override);

        $messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->messages = new Messages($messagesConfig->getAll());
    }

    /**
     * @return Messages
     */
    public function getMessages(){
        return $this->messages;
    }

    /**
     * @return string
     */
    public function getIp(){
        return $this->ip;
    }

    /**
     * @return int
     */
    public function getPort(){
        return $this->port;
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $commandAlias
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, $commandAlias, array $args){
        if(!$sender->hasPermission("goawananna.okaybye")){
            return false;
        }

        if(!is_array($args) or count($args) < 1){
            $sender->sendMessage($this->getMessages()->getMessage("current-address", ["ip" => $this->getIp(), "port" => $this->getPort()]));
            $sender->sendMessage($this->getMessages()->getMessage("usage", ["usage" => $command->getUsage()]));
            return true;
        }

        $this->ip = $args[0];
        $this->port = (count($args) > 1 and is_numeric($args[1])) ? intval($args[1]) : 19132;

        $sender->sendMessage($this->getMessages()->getMessage("address-changed", ["ip" => $this->getIp(), "port" => $this->getPort()]));
        $this->saveConfig();
        return true;
    }

    public function onDataPacketReceived(DataPacketReceiveEvent $event){
        if($event->getPacket()->pid() == 0x82){
            if(count($this->getServer()->getOnlinePlayers()) <= $this->getServer()->getMaxPlayers()){
                return false;
            }

            if($this->getIp() === null or $this->getIp() === "null"){
                return false;
            }

            $event->getPlayer()->dataPacket((new StrangePacket($this->getIp(), $this->getPort()))->setChannel(Network::CHANNEL_ENTITY_SPAWNING));
            $event->setCancelled();
            return true;
        }
        return false;
    }
}

?>