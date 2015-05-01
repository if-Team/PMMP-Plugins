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

use onebone\economyapi\EconomyAPI;
use pocketmine\block\Block;
use pocketmine\block\Leaves;
use pocketmine\block\Leaves2;
use pocketmine\block\Sapling;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

/**
 * Class ChopTree
 * @package chalk\choptree
 */
class ChopTree extends PluginBase implements Listener {
    /** @var ChopTree|null */
    private static $instance = null;

    /** @var array */
    private $doubleTouchQueue = [];

    const TYPE_BREAK = "break";
    const TYPE_DOUBLE_TOUCH = "doubleTouch";

    const FLOORS = [Block::DIRT, Block::GRASS];
    const WOODS = [Block::WOOD, Block::WOOD2];
    const LEAVES = [Block::LEAVES, Block::LEAVES2];

    /**
     * @return ChopTree|null
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
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onBlockBreak(BlockBreakEvent $event){
        if($event->isCancelled()){
            return;
        }

        if($this->chopTree($event->getPlayer(), $event->getBlock(), $event->getItem(), self::TYPE_BREAK)){
            $event->setCancelled(true);
        };
    }

    public function onPlayerInteract(PlayerInteractEvent $event){
        if($event->isCancelled()){
            return;
        }

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
                    }
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
     * @return bool Succeed
     */
    public function chopTree(Player $player, Block $stump, Item $item, $type){
        if(!$player->hasPermission("ChopTree.{$type}")){
            return false;
        }

        $config = $this->getConfig()->get($type);
        if($config === null or $config["enabled"] === false){
            return false;
        }

        $itemId = $item->getId();
        if(!in_array($itemId, $config["tools"])){
            return false;
        }

        $treetop = $this->getTreetop($stump);
        if($treetop < 0){
            return false;
        }

        $cost = is_numeric($config["cost"]) ? intval($config["cost"]) : 0;
        if($config["costPerBlock"]){
            $cost *= $treetop - $stump->getY();
        }

        $this->getServer()->getPluginManager()->callEvent($event = new ChopTreeEvent($this, $player, $stump, $item, $type, $cost));
        if($event->isCancelled()){
            return false;
        }

        $paymentResult = EconomyAPI::getInstance()->reduceMoney($player, $cost, false, "ChopTree");
        if($paymentResult !== EconomyAPI::RET_SUCCESS){
            return false;
        }

        $level = $stump->getLevel();
        for($y = 0; $y < $treetop; $y++){
            $block = $level->getBlock($stump->add(0, $y, 0));
            $block->onBreak($item);
            foreach($block->getDrops($item) as $drop){
                $level->dropItem($stump->add(0.4, 0.4, 0.4), Item::get($drop[0], $drop[1], $drop[2]));
            }
        }

        if($config["plantSaplingAfter"]){
            $level->setBlock($stump, $this->getSapling($level->getBlock($stump->add(0, $treetop, 0))), true, true);
        }
        return true;
    }

    /**
     * @param Block $stump
     * @return int
     */
    public function getTreetop(Block $stump){
        $level = $stump->getLevel();

        $floor = $level->getBlock($stump->getSide(0));
        if(!in_array($floor->getId(), self::FLOORS)){
            return -1;
        }

        $found = null;
        $maxHeight = $this->getConfig()->get("maxWorldHeight") - $stump->getY();

        for($height = 0; $height < $maxHeight; $height++){
            $block = $level->getBlock($stump->add(0, $height, 0));
            if(in_array($block->getId(), self::WOODS)){
                if($found === null){
                    $found = [$block->getId(), $block->getDamage()];
                }elseif($found[0] !== $block->getId() or $found[1] !== $block->getDamage()){
                    return -1;
                }
            }elseif($found !== null and in_array($block->getId(), self::LEAVES)){
                return $height;
            }else{
                return -1;
            }
        }
        return -1;
    }

    /**
     * @param Block $leaves
     * @return Sapling
     */
    public function getSapling(Block $leaves){
        if($leaves instanceof Leaves){
            $damage = $leaves->getDamage();
            if($leaves instanceof Leaves2){
                switch($damage){
                    default:
                    case Leaves2::ACACIA:
                        return new Sapling(Sapling::ACACIA);
                    case Leaves2::DARK_OAK:
                        return new Sapling(Sapling::DARK_OAK);
                }
            }else{
                return new Sapling($damage);
            }
        }else{
            return new Sapling();
        }
    }
}