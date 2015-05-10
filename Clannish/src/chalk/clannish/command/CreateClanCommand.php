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
 * @since 2015-04-08 21:19
 * @copyright Apache-v2.0
 */
namespace chalk\clannish\command;

use chalk\clannish\clan\Clan;
use chalk\clannish\clan\ClanMember;
use chalk\clannish\Clannish;
use pocketmine\command\CommandSender;

class CreateClanCommand extends ManagementCommand implements InGameCommand {
    /**
     * @param Clannish $plugin
     * @param string $name
     * @param string $description
     * @param string $usage
     */
    public function __construct(Clannish $plugin, $name, $description = "", $usage = ""){
        parent::__construct($plugin, $name, $description, $usage);
    }

    /**
     * @param CommandSender $sender
     * @param string[] $args
     * @return bool
     */
    public function exec(CommandSender $sender, array $args){
        if(count($args) < 1 or !is_string($args[0])){
            return false;
        }

        $clanName = Clannish::validateName($args[0], true);
        $leaderName = Clannish::validateName($sender->getName());

        if(Clannish::getInstance()->isClan($clanName)){
            $this->sendMessage($sender, "clan-already-exists", ["name" => $clanName]);
            return true;
        }

        $owningClans = Clannish::getInstance()->getOwningClans($leaderName);
        if(count($owningClans) > Clannish::getInstance()->getMaximumOwningClansCount()){
            $this->sendMessage($sender, "clan-maximum-owning-count-exceed", ["count" => Clannish::getInstance()->getMaximumOwningClansCount()]);
            return true;
        }

        Clannish::getInstance()->getClans()[] = new Clan($clanName, [new ClanMember($leaderName, ["grade" => ClanMember::GRADE_LEADER])]);
        $this->sendMessage($sender, "clan-created", ["name" => $clanName]);
        return true;
    }
}