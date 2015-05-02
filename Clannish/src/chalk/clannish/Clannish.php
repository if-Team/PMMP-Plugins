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
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-04-07 21:53
 * @copyright Apache-v2.0
 */
namespace chalk\clannish;

use chalk\clannish\clan\Clan;
use chalk\clannish\command\ChattingRoomCommand;
use chalk\clannish\command\CreateClanCommand;
use chalk\utils\Messages;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Clannish extends PluginBase implements Listener {
    /** @var Clannish|null */
    private static $instance = null;

    /** @var Clan[] */
    private $clans = [];

    /** @var Messages */
    private $messages = [];

    /**
     * @return Clannish|null
     */
    public static function getInstance(){
        return self::$instance;
    }

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable(){
        $this->loadConfig();
        $this->loadMessages();
        $this->registerAllCommands();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function loadConfig(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $clansConfig = new Config($this->getDataFolder() . "clans.yml", Config::YAML);
        $this->clans = [];

        foreach($clansConfig->getAll() as $array){
            $this->clans[] = Clan::createFromArray($array);
        }
    }

    public function saveConfig(){
        $clansConfig = new Config($this->getDataFolder() . "clans.yml", Config::YAML);
        $clans = [];

        foreach($this->getClans() as $clan){
            $clans[] = $clan->toArray();
        }
        $clansConfig->setAll($clans);
        $clansConfig->save();
    }

    /**
     * @param bool $override
     */
    public function loadMessages($override = false){
        $this->saveResource("messages.yml", $override);

        $messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->messages = new Messages($messagesConfig->getAll());
    }

    public function registerAllCommands(){
        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("Clannish", new CreateClanCommand(
            $this,
            $this->getMessages()->getMessage("create-clan-command-name"),
            $this->getMessages()->getMessage("create-clan-command-description"),
            $this->getMessages()->getMessage("create-clan-command-usage")
        ));
    }

    public function onPlayerChat(PlayerChatEvent $event){
        $sender = $event->getPlayer();
        if(!$sender->hasPermission("Clannish.activity")){
            return;
        }

        //TODO: Implement this method
    }

    /**
     * @return Clan[]
     */
    public function getClans(){
        return $this->clans;
    }

    /**
     * @return Messages
     */
    public function getMessages(){
        return $this->messages;
    }
}