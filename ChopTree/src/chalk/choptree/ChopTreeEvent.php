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
 * @since 2015-04-29 14:52
 * @copyright Apache-v2.0
 */

namespace chalk\choptree;

use pocketmine\block\Block;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

/**
 * Class ChopTreeEvent
 * @package chalk\choptree
 */
class ChopTreeEvent extends PluginEvent implements Cancellable {
    /** @var Player */
    private $player;

    /** @var Block */
    private $block;

    /**
     * @param Plugin $plugin
     * @param Player $player
     * @param Block $block
     */
    public function __construct(Plugin $plugin, Player $player, Block $block){
        parent::__construct($plugin);
        $this->player = $player;
        $this->block = $block;
    }

    /**
     * @return Player
     */
    public function getPlayer(){
        return $this->player;
    }

    /**
     * @return Block
     */
    public function getBlock(){
        return $this->block;
    }
}