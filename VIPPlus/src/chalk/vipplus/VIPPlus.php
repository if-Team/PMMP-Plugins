<?php

/**
 * @author ChalkPE
 * @since 2015-04-18 17:17
 */

namespace chalk\vipplus;

use chalk\utils\Messages;
use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class VIPPlus extends PluginBase implements Listener {
    /** @var VIPPlus */
    private static $instance = null;

    /** @var VIP[] */
    private $vips = [];

    /** @var Messages */
    private $messages;

    /**
     * @return VIPPlus
     */
    public static function getInstance(){
        return VIPPlus::$instance;
    }

    public function onLoad(){
        VIPPlus::$instance = $this;
    }

    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->loadVips();
        $this->loadMessages();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(){
        $this->saveVips();
    }

    /**
     * @param bool $override
     */
    public function loadVips($override = true){
        $vipsConfig = new Config($this->getDataFolder() . "vips.yml", Config::YAML);

        if($override){
            $this->vips = [];
        }

        foreach($vipsConfig->getAll() as $key => $value){
            array_push($this->vips, new VIP($key, $value));
        }
    }

    public function saveVips(){
        $vipsConfig = new Config($this->getDataFolder() . "vips.yml", Config::YAML);
        $vipsConfig->setAll($this->getVips());
        $vipsConfig->save();
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
     * @param CommandSender $sender
     * @param Command $command
     * @param string $commandAlias
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, $commandAlias, array $args){
        if(!$sender->hasPermission("vip") or $sender instanceof Player){
            return false;
        }

        if(!is_array($args) or count($args) < 2 or !is_string($args[1])){
            $sender->sendMessage($this->getCommand("vip")->getUsage());
            return true;
        }

        $playerName = strToLower($args[1]);

        switch($args[0]){
            default:
                $sender->sendMessage($this->getCommand("vip")->getUsage());
                break;

            case "list":
                $sender->sendMessage($this->getMessages()->getMessage("vip-list-info", [count($this->getOnlineVips()), count($this->getVips()), implode(", ", $this->vips)]));
                break;

            case "add":
                $sender->sendMessage($this->addVip($playerName));
                break;

            case "remove":
                $sender->sendMessage($this->removeVip($playerName));
                break;
        }
        return true;
    }

    /**
     * @return VIP[]
     */
    public function getVips(){
        return $this->vips;
    }

    /**
     * @return VIP[]
     */
    public function getOnlineVips(){
        return array_filter($this->getVips(), function(VIP $vip){
            return $vip->getPlayer() !== null;
        });
    }

    /**
     * @param string $name
     * @return int
     */
    public function indexOfVip($name){
        $name = strToLower($name);

        foreach($this->getVips() as $index => $vip){
            if($name === $vip->getName()){
                return $index;
            }
        }
        return -1;
    }

    /**
     * @param string $name
     * @return null|string
     */
    public function addVip($name){
        $name = strToLower($name);

        $index = $this->indexOfVip($name);
        if($index >= 0){
            return $this->getMessages()->getMessage("vip-already-vip", [$name]);
        }
        array_push($this->getVips(), $name);
        $this->saveVips();

        $gratuityAmount = $this->getConfig()->get("vip-gratuity-amount", 0);
        if($gratuityAmount > 0){
            EconomyAPI::getInstance()->addMoney($name, $gratuityAmount, true, "VIPPlus");
        }

        return $this->getMessages()->getMessage("vip-added", [$name]);
    }

    /**
     * @param string $name
     * @return null|string
     */
    public function removeVip($name){
        $name = strToLower($name);

        $index = $this->indexOfVip($name);
        if($index < 0){
            return $this->getMessages()->getMessage("vip-not-vip", [$name]);
        }
        unset($this->getVips()[$index]);
        $this->saveVips();

        return $this->getMessages()->getMessage("vip-removed", [$name]);
    }
}