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

use chalk\clannish\command\ChattingRoomCommand;
use chalk\utils\Messages;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Clannish extends PluginBase implements Listener {
    /** @var Clannish|null */
    private static $instance = null;

    /** @var array */
    private $data = [];

    /** @var Messages */
    private $messages = [];

    /** @var string */
    private $defaultRoomName = "main";

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

        $this->defaultRoomName = $this->getConfig()->get("default-room-name", "main");

        $dataConfig = new Config($this->getDataFolder() . "data.json", Config::JSON);
        $this->data = $dataConfig->getAll();
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
        $commandMap->register("Clannish", new ChattingRoomCommand(
            $this,
            $this->getMessages()->getMessage("chatting-room-command-name"),
            $this->getMessages()->getMessage("chatting-room-command-description"))
        );
    }

    public function onPlayerChat(PlayerChatEvent $event){
        $sender = $event->getPlayer();
        if(!$sender->hasPermission("clannish")){
            return;
        }

        //TODO: Implement this method
    }

    /**
     * @return array
     */
    public function getData(){
        return $this->data;
    }

    /**
     * @return Messages
     */
    public function getMessages(){
        return $this->messages;
    }
}