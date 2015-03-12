<?php

namespace HungerGames;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\Player;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;

class HungerGames extends PluginBase implements Listener {
	public $config, $configData;
	public $m_version = 1;
	public $hungerItem = [ ], $hungerItemName = [ ];
	public $armorItem = [ ], $armorItemName = [ ];
	public $touchedQueue = [ ];
	public $updatePk;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->messagesUpdate ();
		
		$this->config = new Config ( $this->getDataFolder () . "settings.yml", Config::YAML, [ ] );
		$this->configData = $this->config->getAll ();
		
		$this->updatePk = new UpdateBlockPacket ();
		$this->updatePk->meta = 0;
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->configData );
		$this->config->save ();
	}
	public function onTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		$blockPos = "{$block->x}.{$block->y}.{$block->z}";
		
		if ($block->getID () == Item::DIAMOND_BLOCK) {
			$event->setCancelled ();
			if (isset ( $this->touchedQueue [$player->getName ()] [$blockPos] )) {
				$this->alert ( $player, $this->get ( "already-touched" ) );
				return;
			}
			$index = rand ( 0, count ( $this->hungerItem ) - 1 );
			$this->touchedQueue [$player->getName ()] [$blockPos] = 0;
			$this->setGlowObsidian ( $player, $block->x, $block->y, $block->z );
			
			$armorRand = rand ( 1, 5 );
			if ($armorRand == 1) {
				$armorContents = $player->getInventory ()->getArmorContents ();
				if ($armorContents [0] == Item::get ( Item::AIR )) {
					$armorRand = rand ( 0, 4 );
					$player->getInventory ()->setArmorContents ( $this->armorItem [$armorRand] );
					$player->getInventory ()->sendArmorContents ( $player );
					$this->message ( $player, $this->get ( "successfully-get-armor" ) . " : " . $this->armorItemName [$armorRand] );
					return;
				}
			}
			
			$player->getInventory ()->addItem ( $this->hungerItem [$index] );
			$this->message ( $player, $this->get ( "successfully-get-item" ) . " [ " . $this->hungerItemName [$index] . " ]" );
		}
		
		if ($block->getID () == Item::CAKE_BLOCK) {
			$event->setCancelled ();
			if (isset ( $this->touchedQueue [$player->getName ()] [$blockPos] )) {
				$this->alert ( $player, $this->get ( "already-touched" ) );
				return;
			}
			$player->setHealth ( $player->getHealth () + 2 );
			$this->message ( $player, $this->get ( "successfully-cared" ) );
		}
		
		// TODO FIRE
	}
	public function setGlowObsidian(Player $player, $x, $y, $z) {
		$this->updatePk->x = $x;
		$this->updatePk->y = $y;
		$this->updatePk->z = $z;
		$this->updatePk->block = Block::GLOWING_OBSIDIAN;
		$player->dataPacket ( $this->updatePk );
	}
	public function setDiamondBlock(Player $player, $x, $y, $z) {
		$this->updatePk->x = $x;
		$this->updatePk->y = $y;
		$this->updatePk->z = $z;
		$this->updatePk->block = Block::DIAMOND_BLOCK;
		$player->dataPacket ( $this->updatePk );
	}
	public function setFireBlock(Player $player, $x, $y, $z) {
		$this->updatePk->x = $x;
		$this->updatePk->y = $y;
		$this->updatePk->z = $z;
		$this->updatePk->block = Block::FIRE;
		$player->dataPacket ( $this->updatePk );
	}
	public function restoreBlock(Player $player, $x, $y, $z, $block, $meta) {
		$this->updatePk->x = $x;
		$this->updatePk->y = $y;
		$this->updatePk->z = $z;
		$this->updatePk->block = $block;
		$this->updatePk->meta = $meta;
		$player->dataPacket ( $this->updatePk );
	}
	public function onRespawn(PlayerRespawnEvent $event) {
		$player = $event->getPlayer ();
		
		foreach ( $this->hungerItem as $hungerItem )
			$player->getInventory ()->remove ( $hungerItem );
		
		$air = new Item ( 0 );
		$player->getInventory ()->setArmorContents ( [ 
				$air,
				$air,
				$air,
				$air ] );
		$player->getInventory ()->sendArmorContents ( $player );
	}
	public function onDeath(PlayerDeathEvent $event) {
		$event->setDrops ( [ ] );
		if (count ( $this->touchedQueue [$player->getName ()] ) <= 25) foreach ( $this->touchedQueue [$player->getName ()] as $pos ) {
			$pos = explode ( ".", $pos );
			$this->setDiamondBlock ( $player, $pos [0], $pos [1], $pos [2] );
		}
		unset ( $this->touchedQueue [$player->getName ()] );
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		
		$this->hungerItem = [ 
				Item::get ( Item::IRON_SWORD ),
				Item::get ( Item::WOODEN_SWORD ),
				Item::get ( Item::STONE_SWORD ),
				Item::get ( Item::DIAMOND_SWORD ),
				Item::get ( Item::GOLD_SWORD ),
				Item::get ( Item::WOODEN_AXE ),
				Item::get ( Item::STONE_AXE ),
				Item::get ( Item::DIAMOND_AXE ),
				Item::get ( Item::GOLD_AXE ),
				Item::get ( Item::ARROW, 0, 15 ),
				Item::get ( Item::BOW ),
				Item::get ( Item::APPLE, 0, 3 ),
				Item::get ( Item::COOKED_PORKCHOP, 0, 2 ),
				Item::get ( Item::COOKED_CHICKEN, 0, 2 ) ];
		$this->hungerItemName = [ 
				$this->get ( "item-iron-sword" ),
				$this->get ( "item-wooden-sword" ),
				$this->get ( "item-stone-sword" ),
				$this->get ( "item-diamond-sword" ),
				$this->get ( "item-gold-sword" ),
				$this->get ( "item-wooden-axe" ),
				$this->get ( "item-stone-axe" ),
				$this->get ( "item-diamond-axe" ),
				$this->get ( "item-gold-axe" ),
				$this->get ( "item-arrow" ),
				$this->get ( "item-bow" ),
				$this->get ( "item-apple" ),
				$this->get ( "item-cooked_porkchop" ),
				$this->get ( "item-cooked_chicken" ) ];
		$this->armorItem = [ 
				[ 
						Item::get ( Item::LEATHER_CAP ),
						Item::get ( Item::LEATHER_TUNIC ),
						Item::get ( Item::LEATHER_PANTS ),
						Item::get ( Item::LEATHER_BOOTS ) ],
				[ 
						Item::get ( Item::CHAIN_HELMET ),
						Item::get ( Item::CHAIN_CHESTPLATE ),
						Item::get ( Item::CHAIN_LEGGINGS ),
						Item::get ( Item::CHAIN_BOOTS ) ],
				[ 
						Item::get ( Item::IRON_HELMET ),
						Item::get ( Item::IRON_CHESTPLATE ),
						Item::get ( Item::IRON_LEGGINGS ),
						Item::get ( item::IRON_BOOTS ) ],
				[ 
						Item::get ( Item::DIAMOND_HELMET ),
						Item::get ( Item::DIAMOND_CHESTPLATE ),
						Item::get ( Item::DIAMOND_LEGGINGS ),
						Item::get ( Item::DIAMOND_BOOTS ) ],
				[ 
						Item::get ( Item::GOLD_HELMET ),
						Item::get ( Item::GOLD_CHESTPLATE ),
						Item::get ( Item::GOLD_LEGGINGS ),
						Item::get ( Item::GOLD_BOOTS ) ] ];
		$this->armorItemName = [ 
				$this->get ( "armor-leather-set" ),
				$this->get ( "armor-chain-set" ),
				$this->get ( "armor-iron-set" ),
				$this->get ( "armor-diamond-set" ),
				$this->get ( "armor-gold-set" ) ];
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function messagesUpdate() {
		if (! isset ( $this->messages ["default-language"] ["m_version"] )) {
			$this->saveResource ( "messages.yml", true );
			$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		} else {
			if ($this->messages ["default-language"] ["m_version"] < $this->m_version) {
				$this->saveResource ( "messages.yml", true );
				$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
			}
		}
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! isset ( $args [0] )) {
			$this->helpPage ( $player );
			return true;
		}
		switch ($args [0]) {
			//
		}
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}
?>