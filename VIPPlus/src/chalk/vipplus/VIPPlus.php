<?php

/*
 * Copyright 2015 ChalkPE
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @author ChalkPE
 * @since 2015-04-18 17:17
 * @license Apache-2.0
 */

namespace chalk\vipplus;

use chalk\utils\Messages;
use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
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

    /** @var Item[] */
    private $armorContents = [];

    /** @var string */
    private $prefix = "";

    /* ====================================================================================================================== *
     *                         Below methods are plugin implementation part. Please do not call them!                         *
     * ====================================================================================================================== */

    public function onLoad(){
        VIPPlus::$instance = $this;
    }

    public function onEnable(){
        $this->loadConfig();
        $this->loadVips();
        $this->loadMessages();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(){
        $this->saveVips();
    }

    public function loadConfig(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->armorContents = [];
        foreach($this->getConfig()->get("vip-armor-contents", []) as $index => $itemId){
            $this->armorContents[$index] = Item::get($itemId);
        }

        $this->prefix = $this->getConfig()->get("vip-prefix", "");
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

    /**
     * @return bool
     */
    public function saveVips(){
        $vipsConfig = new Config($this->getDataFolder() . "vips.yml", Config::YAML);
        $vipsConfig->setAll($this->getVips());
        return $vipsConfig->save();
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

    /* ====================================================================================================================== *
     *                                     Below methods are API part. You can call them!                                     *
     * ====================================================================================================================== */

    /**
     * @return VIPPlus
     */
    public static function getInstance(){
        return VIPPlus::$instance;
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
    private function indexOfVip($name){
        $name = strToLower($name);

        foreach($this->getVips() as $index => $vip){
            if($name === $vip->getName()){
                return $index;
            }
        }
        return -1;
    }

    public function getVip($name){
        $name = strToLower($name);

        $index = $this->indexOfVip($name);
        if($index < 0){
            return null;
        }else{
            return $this->getVips()[$index];
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public function isVip($name){
        $name = strToLower($name);

        return $this->getVip($name) !== 0;
    }

    /**
     * @param string $name
     * @return null|string
     */
    public function addVip($name){
        $name = strToLower($name);

        if($this->isVip($name)){
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

        $vip = $this->getVip($name);
        if($vip === null){
            return $this->getMessages()->getMessage("vip-not-vip", [$name]);
        }
        unset($vip);
        $this->saveVips();

        return $this->getMessages()->getMessage("vip-removed", [$name]);
    }

    /* ====================================================================================================================== *
     *                                Below methods are non-API part. Please do not call them!                                *
     * ====================================================================================================================== */

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

    public function onPlayerJoin(PlayerJoinEvent $event){
        $vip = $this->getVip($event->getPlayer()->getName());
        if($vip === null){
            return;
        }

        $vip->setArmor($this->armorContents);
        $vip->setPrefix($this->prefix);
    }
}