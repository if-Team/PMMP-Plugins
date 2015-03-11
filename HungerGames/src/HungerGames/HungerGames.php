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

class HungerGames extends PluginBase implements Listener {
	public $config, $configData;
	public $m_version = 1;
	public $hungerItem = [ ];
	public $hungerItemName = [ ];
	public $touchedQueue = [ ];
	public $updatePk;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->messagesUpdate ();
		
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
				Item::get ( Item::ARROW ),
				Item::get ( Item::BOW ),
				Item::get ( Item::APPLE ),
				Item::get ( Item::COOKED_PORKCHOP ),
				Item::get ( Item::COOKED_CHICKEN ) ];
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
		// DIA
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
			$player->getInventory ()->addItem ( $this->hungerItem [$index] );
			$this->setGlowObsidian ( $player, $block->x, $block->y, $block->z );
			$this->message ( $player, $this->get ( "successfully-get-item" ) . " [ " . $this->hungerItemName [$index] . " ]" );
		}
		// CAKE
		// FIRE
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
	public function onRespawn() {
		// TODO 스마트 인벤세이브 추가
	}
	public function onDeath(PlayerDeathEvent $event) {
		$event->setDrops ( [ ] );
		// TODO 인벤세이브 작동여부 추가
		if (count ( $this->touchedQueue [$player->getName ()] ) <= 25) foreach ( $this->touchedQueue [$player->getName ()] as $pos ) {
			$pos = explode ( ".", $pos );
			$this->setDiamondBlock ( $player, $pos [0], $pos [1], $pos [2] );
		}
		unset ( $this->touchedQueue [$player->getName ()] );
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
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