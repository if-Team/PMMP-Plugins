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
            case "list":
                $sender->sendMessage($this->getMessages()->getMessage("vip-list-info", [count($this->getOnlineVips()), count($this->getVips()), implode(", ", $this->vips)]));
                break;

            case "add":
                if(in_array($player, $this->getVips())){
                    $sender->sendMessage($this->getMessages()->getMessage("vip-already-vip", [$player]));
                    return true;
                }
                array_push($this->getVips(), $player);
                $this->saveVips();

                $sender->sendMessage($this->getMessages()->getMessage("vip-added", [$player]));
                break;

            case "remove":
                if(!in_array($player, $this->getVips())){
                    $sender->sendMessage($this->getMessages()->getMessage("vip-not-vip", [$player]));
                    return true;
                }
                unset($this->getVips()[array_search($player, $this->getVips())]);
                $this->saveVips();

                $sender->sendMessage($this->getMessages()->getMessage("vip-removed", [$player]));
                break;

            default:
                $sender->sendMessage($this->getCommand("vip")->getUsage());
                return true;
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