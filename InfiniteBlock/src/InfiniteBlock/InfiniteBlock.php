<?php

namespace InfiniteBlock;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\item\Tool;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class InfiniteBlock extends PluginBase implements Listener {
	public $config, $config_Data, $index;
	public $make_Queue = [ ];
	public $breakQueue = [ ];
	public $itemQueue = [ ];
	public $messages;
	public $mineFile, $mineSettings, $sortedSettings;
	public $tictock = [ ];
	public $m_version = 1;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->messagesUpdate ();
		
		$this->config = new Config ( $this->getDataFolder () . "blocks-data.yml", Config::YAML );
		$this->config_Data = $this->config->getAll ();
		
		$this->mineSort ();
		$this->index = count ( $this->config_Data );
		
		$this->registerCommand ( $this->get ( "infinite" ), "InfiniteBlock", $this->get ( "infinite-desc" ), $this->get ( "infinite-help" ), $this->messages ["en-infinite"] );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		new OutEventListner ( $this );
	}
	public function mineSort() {
		$index = array_keys ( $this->mineSettings ["mine-probability"] );
		$sortedIndex = [ ];
		foreach ( $index as $item ) {
			$exploded = explode ( "/", $this->mineSettings ["mine-probability"] [$item] );
			
			if (! isset ( $exploded [1] )) continue;
			if (! is_numeric ( $exploded [0] ) or ! is_numeric ( $exploded [1] )) continue;
			
			$sortedIndex [$item] = ( int ) round ( $exploded [1] / $exploded [0] );
		}
		ksort ( $sortedIndex ); // 확률이 낮은 순서부터 오름차 정렬
		$this->sortedSettings = $sortedIndex;
	}
	public function randomMine() {
		$index = array_keys ( $this->sortedSettings );
		foreach ( $index as $item ) {
			$rand = rand ( 1, $this->sortedSettings [$item] );
			if ($rand == 1) {return $item;}
		}
		return 1;
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_Data );
		$this->config->save ();
		
		$this->mineFile->setAll ( $this->mineSettings );
		$this->mineFile->save ();
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (isset ( $this->make_Queue [$event->getPlayer ()->getName ()] )) {
			if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] = $event->getBlock ();
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos1" ) );
				return;
			} else if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] = $event->getBlock ();
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos2" ) );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos-msg1" ) );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos-msg2" ) );
				return;
			}
		}
	}
	public function infiniteBlock(Player $player, $ismine = false) {
		if (! isset ( $this->make_Queue [$player->getName ()] )) {
			$this->message ( $player, $this->get ( "infinite-sequence-start" ) );
			$this->message ( $player, $this->get ( "infinite-please-set-pos" ) );
			$this->make_Queue [$player->getName ()] ["pos1"] = false;
			$this->make_Queue [$player->getName ()] ["pos2"] = false;
			return;
		} else {
			if (! $this->make_Queue [$player->getName ()] ["pos1"]) {
				$this->message ( $player, $this->get ( "infinite-please-set-pos1" ) );
				$this->message ( $player, $this->get ( "infinite-if-you-stop-infinite-use-cancel" ) );
				return;
			}
			if (! $this->make_Queue [$player->getName ()] ["pos2"]) {
				$this->message ( $player, $this->get ( "infinite-please-set-pos2" ) );
				$this->message ( $player, $this->get ( "infinite-if-you-stop-infinite-use-cancel" ) );
				return;
			}
			
			$pos = $this->areaPosCast ( $this->make_Queue [$player->getName ()] ["pos1"], $this->make_Queue [$player->getName ()] ["pos2"] );
			
			$checkOverapArea = $this->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3], $pos [4], $pos [5] );
			
			if ($checkOverapArea != false) {
				if (! isset ( $this->make_Queue [$player->getName ()] ["overrap"] )) {
					$this->message ( $player, $this->get ( "infinite-overlap-area-exist" ) . " ( ID: " . $checkOverapArea ["ID"] . ")" );
					$this->message ( $player, $this->get ( "have-you-need-overlap-clear" ) );
					$this->message ( $player, $this->get ( "infinite-make-or-cancel" ) );
					$this->make_Queue [$player->getName ()] ["overrap"] = true;
					return;
				} else {
					while ( 1 ) {
						$checkOverapArea = $this->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3], $pos [4], $pos [5] );
						if ($checkOverapArea == false) break;
						
						$this->removeAreaById ( $checkOverapArea ["ID"] );
						$this->message ( $player, ( int ) $checkOverapArea ["ID"] . $this->get ( "infinite-overlap-area-deleted" ) );
					}
				}
			}
			
			$check = $this->addArea ( $pos [0], $pos [1], $pos [2], $pos [3], $pos [4], $pos [5], $ismine );
			
			unset ( $this->make_Queue [$player->getName ()] );
			if ($check === false) {
				$this->message ( $player, $this->get ( "infinite-failed" ) );
				return;
			} else {
				$this->message ( $player, ( int ) $check . $this->get ( "infinite-area-created" ) );
				return;
			}
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		$this->tictock [$event->getPlayer ()->getName ()] = round ( microtime ( true ) * 1000 );
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if (isset ( $this->tictock [$player->getName ()] ))
			unset ( $this->tictock [$player->getName ()] );
	}
	public function onBreak(BlockBreakEvent $event) {
		$area = $this->getArea ( $event->getBlock ()->x, $event->getBlock ()->y, $event->getBlock ()->z );
		if ($area != false) {
			$time = round ( microtime ( true ) * 1000 );
			if (($time - $this->tictock [$event->getPlayer ()->getName ()]) <= 450) {
				$event->setCancelled ();
				return;
			}
			$this->tictock [$event->getPlayer ()->getName ()] = $time;
			$block = $event->getBlock ();
			$x = $block->x + 0.5;
			$y = $block->y + 0.5;
			$z = $block->z + 0.5;
			if ($area ["is-mine"] == true) {
				$drops = $event->getBlock ()->getDrops ( $event->getItem () );
				foreach ( $drops as $drop )
					if ($drop [2] > 0) $event->getPlayer ()->getInventory ()->addItem ( Item::get (...$drop));
				$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] = Block::get ( $this->randomMine () );
				$this->itemQueue ["{$x}:{$y}:{$z}"] = $drops;
			} else {
				$drops = $event->getBlock ()->getDrops ( $event->getItem () );
				foreach ( $drops as $drop )
					if ($drop [2] > 0) $event->getPlayer ()->getInventory ()->addItem ( Item::get (...$drop));
				$this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] = $block;
				$this->itemQueue ["{$x}:{$y}:{$z}"] = $drops;
			}
		}
	}
	public function onAir(BlockUpdateEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] )) if ($block->getId () == Block::AIR) {
			$event->getBlock ()->getLevel ()->setBlock ( $block, $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"], false, true );
			unset ( $this->breakQueue ["{$block->x}:{$block->y}:{$block->z}"] );
		}
	}
	public function onDrops(ItemSpawnEvent $event) {
		$e = $event->getEntity ();
		$vec = "{$e->x}:{$e->y}:{$e->z}";
		if (isset ( $this->itemQueue [$vec] )) {
			unset ( $this->itemQueue [$vec] );
			
			$reflection_class = new \ReflectionClass ( $e );
			
			foreach ( $reflection_class->getProperties () as $properties ) {
				if ($properties->getName () == 'age') {
					$property = $reflection_class->getProperty ( 'age' );
					$property->setAccessible ( true );
					if ($property->getValue ( $event->getEntity () ) == 0) $property->setValue ( $event->getEntity (), 7000 );
				}
			}
		}
	}
	public function arealist($player, $index = 1) {
		$once_print = 20;
		$target = $this->config_Data;
		
		$index_count = count ( $target );
		$index_key = array_keys ( $target );
		$full_index = floor ( $index_count / $once_print );
		
		if ($index_count > $full_index * $once_print) $full_index ++;
		
		if ($index <= $full_index) {
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "now-list-show" ) . " ({$index}/{$full_index}) " . $this->get ( "index_count" ) . ": {$index_count}" );
			$message = null;
			for($for_i = $once_print; $for_i >= 1; $for_i --) {
				$now_index = $index * $once_print - $for_i;
				if (! isset ( $index_key [$now_index] )) break;
				$now_key = $index_key [$now_index];
				$message .= TextFormat::DARK_AQUA . "[" . ( int ) $now_key . $this->get ( "arealist-name" ) . "] ";
			}
			$player->sendMessage ( $message );
		} else {
			$player->sendMessage ( TextFormat::RED . $this->get ( "there-is-no-list" ) );
			return false;
		}
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->saveResource ( "mine-settings.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		$this->mineFile = new Config ( $this->getDataFolder () . "mine-settings.yml", Config::YAML );
		$this->mineSettings = $this->mineFile->getAll ();
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
	public function registerCommand($name, $fallback = "", $description = "", $usage = "", $permission) {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! strtolower ( $command->getName () ) == $this->get ( "infinite" )) return;
		
		if (! isset ( $args [0] )) {
			$this->message ( $player, $this->get ( "help-page-add" ) );
			$this->message ( $player, $this->get ( "help-page-mine" ) );
			$this->message ( $player, $this->get ( "help-page-del" ) );
			$this->message ( $player, $this->get ( "help-page-list" ) );
			$this->message ( $player, $this->get ( "help-page-clear" ) );
			$this->message ( $player, $this->get ( "help-page-mine-add" ) );
			$this->message ( $player, $this->get ( "help-page-mine-del" ) );
			$this->message ( $player, $this->get ( "help-page-mine-list" ) );
			return true;
		}
		switch ($args [0]) {
			case $this->get ( "infinite-add" ) :
				if (! $player instanceof Player) {
					$this->alert ( $player, $this->get ( "only-in-game" ) );
					return true;
				}
				$this->infiniteBlock ( $player );
				break;
			case $this->get ( "infinite-mine" ) :
				if (! $player instanceof Player) {
					$this->alert ( $player, $this->get ( "only-in-game" ) );
					return true;
				}
				$this->infiniteBlock ( $player, true );
				break;
			case $this->get ( "infinite-del" ) :
				if (isset ( $args [1] )) {
					$this->deleteArea ( $player, $args [1] );
				} else {
					$this->deleteArea ( $player );
				}
				break;
			case $this->get ( "infinite-list" ) :
				if (isset ( $args [1] )) {
					$this->arealist ( $player, $args [1] );
				} else {
					$this->arealist ( $player );
				}
				break;
			case $this->get ( "infinite-cancel" ) :
				if (! $player instanceof Player) {
					$this->alert ( $player, $this->get ( "only-in-game" ) );
					return true;
				}
				if (isset ( $this->make_Queue [$player->getName ()] )) {
					unset ( $this->make_Queue [$player->getName ()] );
					$this->message ( $player, $this->get ( "cancel-help" ) );
					return true;
				} else {
					$this->alert ( $player, $this->get ( "cancel-fail" ) );
					return true;
				}
				break;
			case $this->get ( "infinite-clear" ) :
				$this->index = 0;
				$this->config_Data = [ ];
				$this->message ( $player, $this->get ( "infinite-cleared" ) );
				break;
			case $this->get ( "infinite-mine-option-add" ) :
				if (! isset ( $args [1] ) or ! is_numeric ( $args [1] )) {
					$this->message ( $player, $this->get ( "mine-option-add-help" ) );
					$this->message ( $player, $this->get ( "is-must-numeric" ) );
					return;
				}
				if (! isset ( $args [2] )) {
					$this->message ( $player, $this->get ( "mine-option-add-help" ) );
					return;
				}
				$probability = explode ( "/", $args [2] );
				if (! isset ( $probability [1] )) {
					$this->message ( $player, $this->get ( "mine-option-add-help" ) );
					return;
				}
				$this->mineSettings ["mine-probability"] [( int ) $args [1]] = ( int ) round ( $probability [1] / $probability [0] );
				$this->mineSort ();
				$this->message ( $player, $this->get ( "mine-option-add-complete" ) );
				break;
			case $this->get ( "infinite-mine-option-del" ) :
				if (! isset ( $args [1] ) or ! is_numeric ( $args [1] )) {
					$this->message ( $player, $this->get ( "mine-option-del-help" ) );
					$this->message ( $player, $this->get ( "is-must-numeric" ) );
					return;
				}
				unset ( $this->mineSettings ["mine-probability"] );
				$this->mineSort ();
				$this->message ( $player, $this->get ( "mine-option-del-complete" ) );
				break;
			case $this->get ( "infinite-mine-option-list" ) :
				$list = "";
				foreach ( $this->mineSettings ["mine-probability"] as $index => $item )
					$list .= "[ " . $index . $this->get ( "mine-option-item-desc" ) . $item . " ] ";
				$this->message ( $player, $list );
				break;
			default :
				$this->message ( $player, $this->get ( "help-page-add" ) );
				$this->message ( $player, $this->get ( "help-page-mine" ) );
				$this->message ( $player, $this->get ( "help-page-del" ) );
				$this->message ( $player, $this->get ( "help-page-list" ) );
				$this->message ( $player, $this->get ( "help-page-clear" ) );
				$this->message ( $player, $this->get ( "help-page-mine-add" ) );
				$this->message ( $player, $this->get ( "help-page-mine-del" ) );
				$this->message ( $player, $this->get ( "help-page-mine-list" ) );
				break;
		}
		return true;
	}
	public function areaPosCast(Position $pos1, Position $pos2) {
		$startX = ( int ) $pos1->getX ();
		$startY = ( int ) $pos1->getY ();
		$startZ = ( int ) $pos1->getZ ();
		$endX = ( int ) $pos2->getX ();
		$endY = ( int ) $pos2->getY ();
		$endZ = ( int ) $pos2->getZ ();
		if ($startX > $endX) {
			$backup = $startX;
			$startX = $endX;
			$endX = $backup;
		}
		if ($startY > $endY) {
			$backup = $startY;
			$startY = $endY;
			$endY = $backup;
		}
		if ($startZ > $endZ) {
			$backup = $startZ;
			$startZ = $endZ;
			$endZ = $backup;
		}
		return [ $startX,$endX,$startY,$endY,$startZ,$endZ ];
	}
	public function checkOverlap($startX, $endX, $startY, $endY, $startZ, $endZ) {
		foreach ( $this->config_Data as $area ) {
			if (isset ( $area ["startX"] )) if ((($area ["startX"] <= $startX and $area ["endX"] >= $startX) or ($area ["startX"] <= $endX and $area ["endX"] >= $endX)) and (($area ["startY"] < $startY and $area ["endY"] >= $startY) or ($area ["startY"] < $endY and $area ["endY"] > $endY)) and (($area ["startZ"] < $startZ and $area ["endZ"] > $startZ) or ($area ["endZ"] < $endZ and $area ["endZ"] > $endZ))) return $area;
		}
		return false;
	}
	public function addArea($startX, $endX, $startY, $endY, $startZ, $endZ, $ismine = false) {
		if ($this->checkOverlap ( $startX, $endX, $startY, $endY, $startZ, $endZ ) != false) return false;
		
		$this->config_Data [$this->index] = [ "ID" => $this->index,"is-mine" => $ismine,"startX" => $startX,"endX" => $endX,"startY" => $startY,"endY" => $endY,"startZ" => $startZ,"endZ" => $endZ ];
		return $this->index ++;
	}
	public function deleteArea(Player $player, $id = null) {
		if ($id != null) {
			if ($this->getAreaById ( $id ) != false) {
				$this->removeAreaById ( $id );
				$this->message ( $player, $this->get ( "area-delete-complete" ) );
				return true;
			}
		}
		$area = $this->getArea ( $player->x, $player->y, $player->z );
		if ($area == false) {
			$this->alert ( $player, $this->get ( "can-not-find-area" ) );
			return false;
		}
		$this->removeAreaById ( $area ["ID"] );
		$this->message ( $player, $this->get ( "area-delete-complete" ) );
		return true;
	}
	public function removeAreaById($id) {
		if (isset ( $this->config_Data [$id] )) unset ( $this->config_Data [$id] );
	}
	public function getArea($x, $y, $z) {
		foreach ( $this->config_Data as $area )
			if (isset ( $area ["startX"] )) if ($area ["startX"] <= $x and $area ["endX"] >= $x and $area ["startY"] <= $y and $area ["endY"] >= $y and $area ["startZ"] <= $z and $area ["endZ"] >= $z) return $area;
		return false;
	}
	public function getAreaById($id) {
		return isset ( $this->config_Data [$id] ) ? $this->config_Data [$id] : false;
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