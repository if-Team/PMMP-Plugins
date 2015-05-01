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
use pocketmine\item\Item;
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

    /** @var Item */
    private $item;

    /** @var string */
    private $type;

    /** @var int */
    private $cost;

    /**
     * @param Plugin $plugin
     * @param Player $player
     * @param Block $block
     * @param Item $item
     * @param string $type
     * @param int $cost
     */
    public function __construct(Plugin $plugin, Player $player, Block $block, Item $item, $type, $cost){
        parent::__construct($plugin);

        $this->player = $player;
        $this->block  = $block;
        $this->item   = $item;
        $this->type   = $type;
        $this->cost   = $cost;
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

    /**
     * @return Item
     */
    public function getItem(){
        return $this->item;
    }

    /**
     * @return string
     */
    public function getType(){
        return $this->type;
    }

    /**
     * @return int
     */
    public function getCost(){
        return $this->cost;
    }
}