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
use chalk\clannish\clan\ClanMember;
use chalk\clannish\command\ChattingRoomCommand;
use chalk\clannish\command\CreateClanCommand;
use chalk\utils\Messages;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Clannish extends PluginBase implements Listener {
    /** @var Clannish */
    private static $instance = null;

    /** @var Clan[] */
    private $clans = [];

    /** @var Messages */
    private $messages = [];

    /* ====================================================================================================================== *
     *                         Below methods are plugin implementation part. Please do not call them!                         *
     * ====================================================================================================================== */

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable(){
        $this->loadConfig();
        $this->loadClans();
        $this->loadMessages();

        $this->registerAll();
    }

    public function loadConfig(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
    }

    public function loadClans($override = true){
        if($override){
            $this->clans = [];
        }

        $clansConfig = new Config($this->getDataFolder() . "clans.yml", Config::YAML);
        foreach($clansConfig->getAll() as $index => $array){
            $this->clans[] = Clan::createFromArray($index, $array);
        }
    }

    public function saveClans(){
        $clansConfig = new Config($this->getDataFolder() . "clans.yml", Config::YAML);
        $clans = [];

        foreach($this->getClans() as $clan){
            $clans[$clan->getName()] = $clan->toArray();
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

    /**
     * @return Messages
     */
    public function getMessages(){
        return $this->messages;
    }

    public function registerAll(){
        $this->registerCommand("create-clan", CreateClanCommand::class);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @param string $name
     * @param class $class
     */
    public function registerCommand($name, $class){
        $this->getServer()->getCommandMap()->register("Clannish", new $class(
            $this,
            $this->getMessages()->getMessage($name . "-command-name"),
            $this->getMessages()->getMessage($name . "-command-description"),
            $this->getMessages()->getMessage($name . "-command-usage")
        ));
    }

    /* ====================================================================================================================== *
     *                                     Below methods are API part. You can call them!                                     *
     * ====================================================================================================================== */

    /**
     * @return Clannish
     */
    public static function getInstance(){
        return self::$instance;
    }

    /**
     * @return Clan[]
     */
    public function getClans(){
        return $this->clans;
    }

    /**
     * @param string|Player|ClanMember $name
     * @return array
     */
    public function getJoinedClans($name){
        $name = Clannish::validateName($name);

        $joinedClan = [];
        foreach($this->getClans() as $clan){
            if($clan->isMember($name)){
                $joinedClan[] = $clan;
            }
        }

        return $joinedClan;
    }

    /**
     * @param string|Player|Clan|ClanMember $name
     * @return string
     */
    private static function validateName($name){
        if($name instanceof Player or $name instanceof Clan or $name instanceof ClanMember){
            $name = $name->getName();
        }

        return strToLower($name);
    }

    /**
     * @param string|Clan $name
     * @return int
     */
    private function indexOfClan($name){
        $name = Clannish::validateName($name);

        foreach($this->getClans() as $index => $clan){
            if($name === $clan->getName()){
                return $index;
            }
        }
        return -1;
    }

    /**
     * @param string|Clan $name
     * @return null|Clan
     */
    public function getClan($name){
        $name = Clannish::validateName($name);

        $index = $this->indexOfClan($name);
        if($index < 0){
            return null;
        }else{
            return $this->getClans()[$index];
        }
    }

    /**
     * @param string|Clan $name
     * @return bool
     */
    public function isClan($name){
        $name = Clannish::validateName($name);

        return $this->getClan($name) !== null;
    }

    /* ====================================================================================================================== *
     *                                Below methods are non-API part. Please do not call them!                                *
     * ====================================================================================================================== */

    public function onPlayerChat(PlayerChatEvent $event){
        $sender = $event->getPlayer();
        if(!$sender->hasPermission("Clannish.activity")){
            return;
        }

        //TODO: Implement this method
    }
}