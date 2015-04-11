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

use chalk\clannish\Clannish;
use chalk\clannish\ClannishCommand;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class ChattingRoomCommand extends ClannishCommand {
    /**
     * @param Clannish $clannish
     * @param array $resources
     */
    public function __construct(Clannish $clannish, $resources){
        parent::__construct($clannish, $resources);

    }

    /**
     * @param CommandSender $sender
     * @param string $currentAlias
     * @param string[] $args
     * @return bool
     */
    public function execute(CommandSender $sender, $currentAlias, array $args){
        if(!$this->getPlugin()->isEnabled() or !$this->testPermission($sender)){
            return false;
        }

        if(!$sender instanceof Player){
            $sender->sendMessage($this->getPlugin()->getResource("messages.error.inGameCommand"));
            return true;
        }

        return true;
    }
}