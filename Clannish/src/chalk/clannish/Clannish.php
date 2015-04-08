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

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Clannish extends PluginBase implements Listener {
    /** @var array */
    private $data = [];

    /** @var string */
    private $language = "en";

    /** @var array */
    private $resources = [];

    public function onEnable(){
        @mkdir($this->getDataFolder());

        $dataConfig = new Config($this->getDataFolder() . "data.json", Config::JSON);
        $dataConfig->save();
        $this->data = $dataConfig->getAll();

        $this->saveResource("resources.json", false);
        $resourcesConfig = new Config($this->getDataFolder() . "resources.json", Config::JSON);
        $resourcesConfig->save();
        $this->resources = $resourcesConfig->getAll();

        $this->saveDefaultConfig();
        $this->language = $this->getConfig()->get("current-language", $this->getConfig()->get("default-language"));

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->registerCommands();
    }

    public function registerCommands(){
        $map = [
            "ChattingRoomCommand" => "chalk\\clannish\\command\\ChattingRoomCommand"
        ];

        foreach($this->getResource("commands") as $command){
            $this->getServer()->getCommandMap()->register("clannish", new $map[$command["type"]]($this, $command));
        }
    }

    public function onPlayerChat(PlayerChatEvent $event){
        $sender = $event->getPlayer();
        if(!$sender->hasPermission("Clannish")){
            return;
        }
        
        $key = strToLower($sender->getName());
        if(isset($this->data[$key]) and $this->data[$key]["room"] !== "main"){
            $event->setCancelled(true);
            foreach($this->data[$key]["roomMembers"] as $member){
                //TODO: Implements this stuff
            }
        }
    }

    /**
     * @return array
     */
    public function getData(){
        return $this->data;
    }

    /**
     * @return string
     */
    public function getLanguage(){
        return $this->language;
    }

    /**
     * @return array
     */
    public function getResources(){
        return $this->resources;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getResource($key){
        $resource = $this->getResources()[$this->getLanguage()];
        if(!isset($resource)){
            $resource = $this->getResources()[$this->getConfig()->get("default-language")];
        }

        foreach(explode(".", $key) as $k){
            $resource = $resource[$k];
        }
        return $resource;
    }
}