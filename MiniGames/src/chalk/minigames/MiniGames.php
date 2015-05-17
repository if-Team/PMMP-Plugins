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
 * @since 2015-05-17 12:37
 */

namespace chalk\minigames;

use chalk\utils\Messages;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class MiniGames extends PluginBase implements Listener {
    /** @var MiniGames */
    private static $instance;

    /** @var Messages */
    private $messages;

    /** @var bool */
    private $enabled = true;

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable(){
        $this->loadConfigs();
        $this->loadMessages();
        $this->registerAll();
    }

    public function loadConfigs(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->enabled = $this->getConfig()->get("enabled", true);
    }

    public function registerAll(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
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
        if(!($this->isEnabled() and $sender->hasPermission("minigames.play"))){
            return false;
        }

        return true;
    }

    public function onPlayerDamageByPlayerEvent(EntityDamageByEntityEvent $event){
        $attacker = $event->getDamager();
        $victim = $event->getEntity();

        if(!($this->enabled and $attacker instanceof Player and $victim instanceof Player)){
            return;
        }

        if(!($attacker->hasPermission("minigames.play") and $victim->hasPermission("minigames.play"))){
            return;
        }
    }
}