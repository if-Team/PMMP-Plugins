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
 * @since 2015-04-05 12:01
 * @copyright Apache-v2.0
 */
namespace chalk\choptree;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class ChopTree extends PluginBase implements Listener {
    /**
     * @var null|ChopTree
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $doubleTouchQueue = [];

    const TYPE_BREAK = "break";
    const TYPE_DOUBLE_TOUCH = "doubleTouch";

    /**
     * @return null|ChopTree
     */
    public static function getInstance(){
        return self::$instance;
    }

    public function onLoad(){
        self::$instance = $this;
    }

    public function onEnable(){
        @mkdir($this->getDataFolder());

        $this->saveDefaultConfig();
        $this->config = $this->getConfig()->getAll();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onBlockBreak(BlockBreakEvent $event){
        if($this->chopTree($event->getPlayer(), $event->getBlock(), $event->getItem(), self::TYPE_BREAK)){
            $event->setCancelled(true);
        };
    }

    public function onPlayerInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();

        $key = $player->getName();
        if(isset($this->doubleTouchQueue[$key])){
            if(abs(time() - $this->doubleTouchQueue[$key]["time"]) < 2000){
                /** @var $lastBlock Block */
                $lastBlock = $this->doubleTouchQueue[$key]["block"];
                if($block->getX() === $lastBlock->getX() and $block->getY() === $lastBlock->getY() and $block->getZ() === $lastBlock->getZ()){
                    if($this->chopTree($player, $block, $event->getItem(), self::TYPE_DOUBLE_TOUCH)){
                        $event->setCancelled(true);
                    };
                }
            }
            unset($this->doubleTouchQueue[$key]);
        }else{
            $this->doubleTouchQueue[$key] = ["time" => time(), "block" => $block];
        }
    }

    /**
     * @param Player $player
     * @param Block $stump
     * @param Item $item
     * @param string $type
     * @return bool
     */
    public function chopTree(Player $player, Block $stump, Item $item, $type){
        if($player->hasPermission("ChopTree")){
            return false;
        }

        $treetop = $this->getTreetop($stump);
        if($treetop < 0){
            return false;
        }

        //TODO: Implements ChopTree stuffs
        return true;
    }

    /**
     * @param Block $stump
     * @return int
     */
    public function getTreetop(Block $stump){
        $level = $stump->getLevel();

        $floor = $level->getBlock($stump->getX(), $stump->getY() - 1, $stump->getZ());
        if(!in_array($floor->getId(), $this->config["floors"])) {
            return -1;
        }

        $found = [];
        for($y = $stump->getY(); $y < $this->config["maxWorldHeight"]; $y++){
            $block = $level->getBlock($stump->getX(), $y++, $stump->getZ());
            if(in_array($block->getId(), $this->config["woods"])){
                if(count($found) === 0){
                    $found = [$block->getId(), $block->getDamage()];
                }else if($found[0] !== $block->getId() or $found[1] !== $block->getDamage()){
                    return -1;
                }
            }else if(count($found) !== 0 and in_array($block->getId(), $this->config["leaves"])){
                return $y;
            }else{
                return -1;
            }
        }
        return -1;
    }
}