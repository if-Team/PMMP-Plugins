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

class InfiniteBlock extends PluginBase implements Listener {
	public $config, $config_Data, $index;
	public $make_Queue = [ ];
	public $messages;
	public $mineSettings;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		$this->config = new Config ( $this->getDataFolder () . "blocks-data.yml", Config::YAML );
		$this->config_Data = $this->config->getAll ();
		
		$this->mineSort ();
		$this->index = count($this->config_Data);
		
		$this->registerCommand ( $this->get ( "infinite" ), "InfiniteBlock", $this->get ( "infinite-desc" ), $this->get ( "infinite-help" ), $this->messages ["en-infinite"] );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
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
		$this->mineSettings ["mine-probability"] = $sortedIndex;
	}
	public function randomMine() {
		$index = array_keys ( $this->mineSettings ["mine-probability"] );
		foreach ( $index as $item ) {
			$rand = rand ( 1, $this->mineSettings ["mine-probability"] [$item] );
			if ($rand == 1) {return $item;}
		}
		return 1;
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_Data );
		$this->config->save ();
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (isset ( $this->make_Queue [$event->getPlayer ()->getName ()] )) {
			if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] = $event->getBlock ()->getSide ( 0 );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos1" ) );
				return;
			} else if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] = $event->getBlock ()->getSide ( 0 );
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
			
			$checkOverapArea = $this->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
			
			if ($checkOverapArea != false) {
				if (! isset ( $this->make_Queue [$player->getName ()] ["overrap"] )) {
					$this->message ( $player, $this->get ( "infinite-overlap-area-exist" ) . " ( ID: " . $checkOverapArea ["ID"] . ")" );
					$this->message ( $player, $this->get ( "have-you-need-overlap-clear" ) );
					$this->message ( $player, $this->get ( "infinite-make-or-cancel" ) );
					$this->make_Queue [$player->getName ()] ["overrap"] = true;
					return;
				} else {
					while ( 1 ) {
						$checkOverapArea = $this->chechhkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
						if ($checkOverapArea == false) break;
						
						$this->removeAreaById ( $checkOverapArea ["ID"] );
						$this->message ( $player, ( int ) $checkOverapArea ["ID"] . $this->get ( "infinite-overlap-area-deleted" ) );
					}
				}
			}
			
			$check = $this->addArea ( $pos [0], $pos [1], $pos [2], $pos [3], $ismine );
			
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
	public function onBreak(BlockBreakEvent $event) {
		$area = $this->getArea ( $event->getBlock ()->x, $event->getBlock ()->z );
		if ($area != false) {
			if ($area ["is-mine"] == true) {
				$event->setCancelled ();
				$drops = $event->getBlock ()->getDrops ( $event->getItem () );
				foreach ( $drops as $drop )
					if ($drop [2] > 0) $event->getPlayer ()->getInventory ()->addItem ( Item::get ( ...$drop ) );
				$event->getBlock ()->getLevel ()->setBlock ( $event->getBlock (), Block::get ( $this->randomMine () ) );
			} else {
				$event->setCancelled ();
				$drops = $event->getBlock ()->getDrops ( $event->getItem () );
				foreach ( $drops as $drop )
					if ($drop [2] > 0) $event->getPlayer ()->getInventory ()->addItem ( Item::get ( ...$drop ) );
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
		$this->mineSettings = (new Config ( $this->getDataFolder () . "mine-settings.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
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
					$this->message ( $player, $this->get ( "infinite-del-help" ) );
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
			default :
				$this->message ( $player, $this->get ( "help-page-add" ) );
				$this->message ( $player, $this->get ( "help-page-mine" ) );
				$this->message ( $player, $this->get ( "help-page-del" ) );
				$this->message ( $player, $this->get ( "help-page-list" ) );
				$this->message ( $player, $this->get ( "help-page-clear" ) );
				break;
		}
		return true;
	}
	public function areaPosCast(Position $pos1, Position $pos2) {
		$startX = ( int ) $pos1->getX ();
		$startZ = ( int ) $pos1->getZ ();
		$endX = ( int ) $pos2->getX ();
		$endZ = ( int ) $pos2->getZ ();
		if ($startX > $endX) {
			$backup = $startX;
			$startX = $endX;
			$endX = $backup;
		}
		if ($startZ > $endZ) {
			$backup = $startZ;
			$startZ = $endZ;
			$endZ = $backup;
		}
		return [ 
				$startX,
				$endX,
				$startZ,
				$endZ ];
	}
	public function checkOverlap($startX, $endX, $startZ, $endZ) {
		foreach ( $this->config_Data as $area ) {
			if (isset ( $area ["startX"] )) if ((($area ["startX"] < $startX and $area ["endX"] > $startX) or ($area ["startX"] < $endX and $area ["endX"] > $endX)) and (($area ["startZ"] < $startZ and $area ["endZ"] > $startZ) or ($area ["endZ"] < $endZ and $area ["endZ"] > $endZ))) return $area;
		}
		return false;
	}
	public function addArea($startX, $endX, $startZ, $endZ, $ismine = false) {
		if ($this->checkOverlap ( $startX, $endX, $startZ, $endZ ) != false) return false;
		
		$this->config_Data [$this->index] = [ 
				"ID" => $this->index,
				"is-mine" => $ismine,
				"startX" => $startX,
				"endX" => $endX,
				"startZ" => $startZ,
				"endZ" => $endZ ];
		return $this->index ++;
	}
	public function deleteArea(Player $player, $areanumber) {
		if ($this->getAreaById ( $id ) != false) {
			$this->removeAreaById ( $id );
			$this->message ( $player, $this->get ( "area-delete-complete" ) );
			return true;
		}
		$area = $this->getArea ( $player->x, $player->z );
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
	public function getArea($x, $z) {
		foreach ( $this->config_Data as $area )
			if (isset ( $area ["startX"] )) if ($area ["startX"] <= $x and $area ["endX"] >= $x and $area ["startZ"] <= $z and $area ["endZ"] >= $z) return $area;
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