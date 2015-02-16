<?php

namespace hm\FastFurnace;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\utils\Config;

class FastFurnace extends PluginBase implements Listener {
	public $ymlfile, $coal_cache;
	public $recipes = array (
			4 => 1,
			17 => 263,
			12 => 20,
			14 => 266,
			15 => 265,
			16 => 263,
			21 => 22,
			56 => 264,
			73 => 331,
			74 => 331,
			337 => 336,
			319 => 320,
			363 => 364,
			365 => 366 
	);
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->ymlfile = new Config ( $this->getDataFolder () . "cache.yml", Config::YAML, [ ] );
		$this->coal_cache = $this->ymlfile->getAll ();
	}
	public function onDisable() {
		$this->ymlfile->setAll ( $this->coal_cache );
		$this->ymlfile->save ();
	}
	public function FurnaceTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		$item = $event->getItem ()->getID ();
		
		if ($block->getID () == Item::FURNACE or $block->getID () == Item::BURNING_FURNACE) {
			$event->setCancelled ( true );
			if (isset ( $this->recipes [$item] )) {
				$coal = 0;
				
				foreach ( $player->getInventory ()->getContents () as $inven ) {
					if (! $inven instanceof Item)
						return;
					if ($inven->getID () == Item::COAL) {
						$coal = $inven->getCount ();
						break;
					}
				}
				if ($coal == Item::AIR and $item != Item::WOOD and ! isset ( $this->coal_cache [$player->getName ()] )) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "석탄이 없습니다 ! ( 원목으로 목탄을 만드세요 ! )" );
				} else {
					$player->getInventory ()->addItem ( Item::get ( $this->recipes [$item] ) );
					$player->getInventory ()->removeItem ( Item::get ( $item, $event->getItem ()->getDamage () ) );
					if ($item != Item::WOOD) {
						if (! isset ( $this->coal_cache [$player->getName ()] )) {
							$player->getInventory ()->removeItem ( Item::get ( Item::COAL ) );
							$this->coal_cache [$player->getName ()] = 3;
						} else {
							$this->coal_cache [$player->getName ()] --;
							if ($this->coal_cache [$player->getName ()] == 0)
								unset ( $this->coal_cache [$player->getName ()] );
						}
					}
					$player->sendMessage ( TextFormat::DARK_AQUA . "성공적으로 구워졌습니다. (인벤토리 확인)" );
				}
			} else {
				$player->sendMessage ( TextFormat::DARK_AQUA . "조합할 물건으로 터치시 조합 가능합니다 !" );
			}
			if ($event->getItem ()->isPlaceable ()) {
				$this->PlacePrevent [$player->getName ()] = true;
			}
		}
	}
	public function onPlace(BlockPlaceEvent $event) {
		$username = $event->getPlayer ()->getName ();
		if (isset ( $this->PlacePrevent [$username] )) {
			$event->setCancelled ( true );
			unset ( $this->PlacePrevent [$username] );
		}
	}
}