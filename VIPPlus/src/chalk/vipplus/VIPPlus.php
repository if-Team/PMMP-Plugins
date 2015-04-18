<?php

/**
 * @author ChalkPE
 * @since 2015-04-18 17:17
 */

namespace chalk\vipplus;

use chalk\utils\Messages;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class VIPPlus extends PluginBase implements Listener {
    /** @var array */
    private $vips = [];

    /** @var Messages */
    private $messages;

    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $vipsConfig = new Config($this->getDataFolder() . "vips.yml", Config::YAML);
        $this->vips = $vipsConfig->getAll();

        $this->saveResource("messages.yml");
        $messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->messages = new Messages($messagesConfig->getAll());

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(){
        $this->saveVips();
    }

    public function onCommand(CommandSender $sender, Command $command, $commandAlias, array $args){
        if(!$sender->hasPermission("vip") or $sender instanceof Player){
            return false;
        }

        if(!is_array($args) or count($args) < 2 or !is_string($args[1])){
            $sender->sendMessage($this->getCommand("vip")->getUsage());
            return true;
        }

        $player = strToLower($args[1]);

        switch($args[0]){
            default:
                $sender->sendMessage($this->getCommand("vip")->getUsage());
                break;

            case "list":
                $sender->sendMessage($this->getMessages()->getMessage("vip-list-info", [count($this->getOnlineVips()), count($this->getVips()), implode(", ", $this->vips)]));
                break;

            case "add":
                $sender->sendMessage($this->addVip($player));
                break;

            case "remove":
                $sender->sendMessage($this->removeVip($player));
                break;
        }
        return true;
    }

    /**
     * @return array
     */
    public function getVips(){
        return $this->vips;
    }

    /**
     * @return array
     */
    public function getOnlineVips(){
        $vips = $this->getVips();
        $onlineVips = [];

        foreach($this->getServer()->getOnlinePlayers() as $onlinePlayer){
            $player = strToLower($onlinePlayer->getName());
            if(in_array($player, $vips)){
                array_push($onlineVips, $player);
            }
        }
        return $onlineVips;
    }

    /**
     * @param string $player
     * @return null|string
     */
    public function addVip($player){
        if(in_array($player, $this->getVips())){
            return $this->getMessages()->getMessage("vip-already-vip", [$player]);
        }
        array_push($this->getVips(), $player);
        $this->saveVips();

        return $this->getMessages()->getMessage("vip-added", [$player]);
    }

    /**
     * @param string $player
     * @return null|string
     */
    public function removeVip($player){
        if(!in_array($player, $this->getVips())){
            return $this->getMessages()->getMessage("vip-not-vip", [$player]);
        }
        unset($this->getVips()[array_search($player, $this->getVips())]);
        $this->saveVips();

        return $this->getMessages()->getMessage("vip-removed", [$player]);
    }


    public function saveVips(){
        $vipsConfig = new Config($this->getDataFolder() . "vips.yml", Config::YAML);
        $vipsConfig->setAll($this->getVips());
        $vipsConfig->save();
    }

    /**
     * @return Messages
     */
    public function getMessages(){
        return $this->messages;
    }
}