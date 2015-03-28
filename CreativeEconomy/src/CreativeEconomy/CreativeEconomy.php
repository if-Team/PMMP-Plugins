<?php

namespace CreativeEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\block\Block;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\scheduler\CallbackTask;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;

class CreativeEconomy extends PluginBase implements Listener {
	private static $instance = null;
	public $messages, $db;
	public $m_version = 5;
	public $marketCount, $marketPrice, $itemName;
	public $economyAPI = null;
	public $purchaseQueue = [ ]; // 상점결제 큐
	public $createQueue = [ ]; // 상점제작시 POS백업큐
	public $autoCreateQueue = [ ]; // 자동 상점제작시 POS백업큐
	public $packetQueue = [ ]; // 아이템 패킷 큐
	public $packet = [ ]; // 전역 패킷 변수
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		$this->saveResource ( "marketPrice.yml", false );
		$this->marketPrice = (new Config ( $this->getDataFolder () . "marketPrice.yml", Config::YAML ))->getAll ();
		
		$this->saveResource ( "marketCount.yml", false );
		$this->saveResource ( $this->messages ["default-language"] . "_item_data.yml", false );
		
		$this->messagesUpdate ( "marketPrice.yml" );
		$marketPrice_new = (new Config ( $this->getDataFolder () . "marketPrice.yml", Config::YAML ))->getAll ();
		foreach ( $this->marketPrice as $index => $data ) {
			if ($index == "m_version") continue;
			if (! isset ( $marketPrice_new [$index] )) continue;
			$marketPrice_new [$index] = $data;
			$this->marketPrice = $marketPrice_new;
		} // IF BLOCK ID OR DAMAGES LIST UPDATED, OLD DATA WILL RESTORED
		
		$this->messagesUpdate ( "marketCount.yml" );
		$this->messagesUpdate ( "messages.yml" );
		$this->messagesUpdate ( $this->messages ["default-language"] . "_item_data.yml" );
		
		$this->db = (new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML, [ "allow-purchase" => true ] ))->getAll ();
		$this->marketCount = (new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML ))->getAll ();
		$this->itemName = (new Config ( $this->getDataFolder () . $this->messages ["default-language"] . "_item_data.yml", Config::YAML ))->getAll ();
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->registerCommand ( $this->get ( "commands-buy" ), "ce", "creativeeconomy.commands.buy", $this->get ( "commands-buy-usage" ) );
		$this->registerCommand ( $this->get ( "commands-sell" ), "ce", "creativeeconomy.commands.sell", $this->get ( "commands-sell-usage" ) );
		$this->registerCommand ( $this->get ( "commands-ce" ), "ce", "creativeeconomy.commands.ce", $this->get ( "commands-ce-usage" ) );
		
		if (self::$instance == null) self::$instance = $this;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		$this->packet ["AddItemEntityPacket"] = new AddItemEntityPacket ();
		$this->packet ["AddItemEntityPacket"]->yaw = 0;
		$this->packet ["AddItemEntityPacket"]->pitch = 0;
		$this->packet ["AddItemEntityPacket"]->roll = 0;
		
		$this->packet ["RemoveEntityPacket"] = new RemoveEntityPacket ();
		
		$this->packet ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packet ["AddPlayerPacket"]->clientID = 0;
		$this->packet ["AddPlayerPacket"]->yaw = 0;
		$this->packet ["AddPlayerPacket"]->pitch = 0;
		$this->packet ["AddPlayerPacket"]->item = 0;
		$this->packet ["AddPlayerPacket"]->meta = 0;
		$this->packet ["AddPlayerPacket"]->metadata = [ 0 => [ "type" => 0,"value" => 0 ],1 => [ "type" => 1,"value" => 0 ],16 => [ "type" => 0,"value" => 0 ],17 => [ "type" => 6,"value" => [ 0,0,0 ] ] ];
		
		$this->packet ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packet ["RemovePlayerPacket"]->clientID = 0;
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,"CreativeEconomy" ] ), 20 );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
		
		$save = new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML );
		$save->setAll ( $this->marketCount );
		$save->save ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		if (isset ( $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"] )) {
			if (! $player->hasPermission ( "creativeeconomy.shop.use" )) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			if ($this->db ["allow-purchase"] == false) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			if (isset ( $this->marketPrice [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]] )) {
				$price = $this->marketPrice [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]];
			} else {
				$explode = explode ( ".", $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"] );
				if (isset ( $this->marketPrice [$explode [0]] )) {
					$price = $this->marketPrice [$explode [0]];
				} else {
					$this->alert ( $player, $this->get ( "not-found-item-data" ) );
					return;
				}
			}
			//
			if (isset ( $this->itemName [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]] )) {
				$itemName = $this->itemName [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]];
			} else {
				$explode = explode ( ".", $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"] );
				if (isset ( $this->itemName [$explode [0]] )) {
					$itemName = $this->itemName [$explode [0]];
				} else {
					$this->alert ( $player, $this->get ( "not-found-item-data" ) );
					return;
				}
			}
			if ($price == 0) {
				$this->alert ( $player, $this->get ( "this-item-doesnt-sell" ) );
				return;
			}
			$check = explode ( ".", $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"] );
			if (! isset ( $check [1] )) $check [1] = 0;
			$haveItem = 0;
			foreach ( $player->getInventory ()->getContents () as $inven ) {
				if (! $inven instanceof Item) continue;
				if ($inven->getID () == $check [0] and $inven->getDamage () == $check [1]) {
					$haveItem += $inven->getCount (); // 가진아이템 수 확인
				}
			}
			$this->purchaseQueue [$player->getName ()] ["id"] = $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"];
			$this->message ( $player, "' " . $itemName . " '" . $this->get ( "you-can-buy-or-sell" ) . " ( " . $this->get ( "my-item-count" ) . " : " . $haveItem . " )" );
			$this->message ( $player, $this->get ( "buy-or-sell-help-command" ) . " ( " . $this->get ( "price" ) . " : " . $price . " )" );
			$event->setCancelled ();
		}
		if (isset ( $this->createQueue [$player->getName ()] )) {
			$event->setCancelled ();
			$pos = $block->getSide ( 1 );
			$item = $this->createQueue [$player->getName ()];
			$this->db ["showCase"] ["{$pos->x}.{$pos->y}.{$pos->z}"] = $item;
			$block->getLevel ()->setBlock ( $pos, Block::get ( Item::GLASS ), true );
			unset ( $this->createQueue [$player->getName ()] );
			$this->message ( $player, $this->get ( "market-completely-created" ) );
		}
		if (isset ( $this->autoCreateQueue [$player->getName ()] ) and $this->autoCreateQueue [$player->getName ()] ["pos1"] === null) {
			$event->setCancelled ();
			$this->autoCreateQueue [$player->getName ()] ["pos1"] = $block->getSide ( 1 );
			// POS1 지정완료
			$this->message ( $player, $this->get ( "pos1-is-selected" ) );
			return;
		}
		if (isset ( $this->autoCreateQueue [$player->getName ()] ) and $this->autoCreateQueue [$player->getName ()] ["pos2"] === null) {
			$event->setCancelled ();
			$this->autoCreateQueue [$player->getName ()] ["pos2"] = $block->getSide ( 1 );
			// POS2 지정완료
			$this->message ( $player, $this->get ( "pos2-is-selected" ) );
			$this->message ( $player, $this->get ( "are-you-want-continue" ) );
			$this->message ( $player, $this->get ( "you-can-possible-to-cancel" ) );
			return;
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		$player = $event->getPlayer ();
		if (isset ( $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"] )) {
			if (! $player->hasPermission ( "creativeeconomy.shop.break" )) {
				$this->alert ( $player, $this->get ( "market-cannot-break" ) );
				$event->setCancelled ();
				return;
			}
			if (isset ( $this->marketCount [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]] )) {
				if ($this->marketCount [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]] > 0) {
					$this->marketCount [$this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"]] --;
				}
			}
			unset ( $this->db ["showCase"] ["{$block->x}.{$block->y}.{$block->z}"] );
			if (isset ( $this->packetQueue [$player->getName ()] ["nametag"] ["{$block->x}.{$block->y}.{$block->z}"] )) {
				$this->packet ["RemovePlayerPacket"]->eid = $this->packetQueue [$player->getName ()] ["nametag"] ["{$block->x}.{$block->y}.{$block->z}"];
				$player->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 제거패킷 전송
			}
			if (isset ( $this->packetQueue [$player->getName ()] ["{$block->x}.{$block->y}.{$block->z}"] )) {
				$this->packet ["RemoveEntityPacket"]->eid = $this->packetQueue [$player->getName ()] ["{$block->x}.{$block->y}.{$block->z}"];
				$player->dataPacket ( $this->packet ["RemoveEntityPacket"] ); // 아이템 제거패킷 전송
				unset ( $this->packetQueue [$player->getName ()] ["{$block->x}.{$block->y}.{$block->z}"] );
			}
			$this->message ( $player, $this->get ( "market-completely-destroyed" ) );
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->purchaseQueue [$event->getPlayer ()->getName ()] )) unset ( $this->purchaseQueue [$event->getPlayer ()->getName ()] );
		if (isset ( $this->createQueue [$event->getPlayer ()->getName ()] )) unset ( $this->createQueue [$event->getPlayer ()->getName ()] );
		if (isset ( $this->autoCreateQueue [$event->getPlayer ()->getName ()] )) unset ( $this->autoCreateQueue [$event->getPlayer ()->getName ()] );
		if (isset ( $this->packetQueue [$event->getPlayer ()->getName ()] )) unset ( $this->packetQueue [$event->getPlayer ()->getName ()] );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-buy" ) :
				if (! $player instanceof Player) {
					$this->alert ( $player, $this->get ( "only-in-game" ) );
					return true;
				}
				(isset ( $args [0] )) ? $this->CEBuyCommand ( $player, $args [0] ) : $this->CEBuyCommand ( $player );
				break;
			case $this->get ( "commands-sell" ) :
				if (! $player instanceof Player) {
					$this->alert ( $player, $this->get ( "only-in-game" ) );
					return true;
				}
				(isset ( $args [0] )) ? $this->CESellCommand ( $player, $args [0] ) : $this->CESellCommand ( $player );
				break;
			case $this->get ( "commands-ce" ) :
				if (! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "commands-ce-help1" ) );
					$this->message ( $player, $this->get ( "commands-ce-help2" ) );
					$this->message ( $player, $this->get ( "commands-ce-help3" ) );
					$this->message ( $player, $this->get ( "commands-ce-help4" ) );
					break;
				}
				switch ($args [0]) {
					case $this->get ( "sub-commands-create" ) :
						if (! $player instanceof Player) {
							$this->alert ( $player, $this->get ( "only-in-game" ) );
							return true;
						}
						(isset ( $args [1] )) ? $this->CECreateQueue ( $player, $args [1] ) : $this->CECreateQueue ( $player );
						break;
					case $this->get ( "sub-commands-autocreate" ) :
						if (! $player instanceof Player) {
							$this->alert ( $player, $this->get ( "only-in-game" ) );
							return true;
						}
						$this->CEAutoSet ( $player );
						break;
					case $this->get ( "sub-commands-change" ) :
						(isset ( $args [1] ) and isset ( $args [2] )) ? $this->ChangeMarketPrice ( $player, $args [1], $args [2] ) : $this->ChangeMarketPrice ( $player );
						break;
					case $this->get ( "sub-commands-cancel" ) :
						if (! $player instanceof Player) {
							$this->alert ( $player, $this->get ( "only-in-game" ) );
							return true;
						}
						// ( 모든 큐를 초기화 )
						if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
						if (isset ( $this->createQueue [$player->getName ()] )) unset ( $this->createQueue [$player->getName ()] );
						if (isset ( $this->autoCreateQueue [$player->getName ()] )) unset ( $this->autoCreateQueue [$player->getName ()] );
						
						$this->message ( $player, $this->get ( "all-processing-is-stopped" ) );
						break;
					case $this->get ( "sub-commands-lock" ) :
						$this->AllFreezeMarket ( $player );
						break;
					case $this->get ( "nametag" ) :
						if (isset ( $this->db ["settings"] ["nametagEnable"] )) {
							if ($this->db ["settings"] ["nametagEnable"]) {
								$this->db ["settings"] ["nametagEnable"] = false;
								$this->message ( $player, $this->get ( "nametag-disabled" ) );
							} else {
								$this->db ["settings"] ["nametagEnable"] = true;
								$this->message ( $player, $this->get ( "nametag-enabled" ) );
							}
						} else {
							$this->db ["settings"] ["nametagEnable"] = false;
							$this->message ( $player, $this->get ( "nametag-disabled" ) );
						}
						break;
					case $this->get ( "sub-commands-seepurchase" ) :
						if (isset ( $this->db ["settings"] ["seePurchase"] )) {
							if ($this->db ["settings"] ["seePurchase"]) {
								$this->db ["settings"] ["seePurchase"] = false;
								$this->message ( $player, $this->get ( "seepurchase-disabled" ) );
							} else {
								$this->db ["settings"] ["seePurchase"] = true;
								$this->message ( $player, $this->get ( "seepurchase-enabled" ) );
							}
						} else {
							$this->db ["settings"] ["seePurchase"] = true;
							$this->message ( $player, $this->get ( "seepurchase-enabled" ) );
						}
						break;
					case $this->get ( "pricefix" ) :
						$this->rendezvous ();
						$this->message ( $player, $this->get ( "pricefix-complete" ) );
						break;
					default :
						$this->message ( $player, $this->get ( "commands-ce-help1" ) );
						$this->message ( $player, $this->get ( "commands-ce-help2" ) );
						$this->message ( $player, $this->get ( "commands-ce-help3" ) );
						$this->message ( $player, $this->get ( "commands-ce-help4" ) );
						break;
				}
				break;
		}
		return true;
	}
	public function rendezvous() {
		$this->marketPrice [5] = $this->marketPrice [17] / 4;
		$this->marketPrice [336] = $this->marketPrice [337];
		$this->marketPrice [280] = $this->marketPrice [5] / 2;
		$this->marketPrice [263] = $this->marketPrice [17];
		$this->marketPrice [263.1] = $this->marketPrice [17];
		$this->marketPrice [14] = $this->marketPrice [266];
		$this->marketPrice [15] = $this->marketPrice [265];
		$this->marketPrice [16] = $this->marketPrice [263];
		$this->marketPrice [20] = $this->marketPrice [12];
		$this->marketPrice [26] = ($this->marketPrice [35] * 3) + ($this->marketPrice [5] * 3);
		$this->marketPrice [30] = $this->marketPrice [287];
		$this->marketPrice [35] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.1] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.2] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.3] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.4] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.5] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.6] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.7] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.8] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.9] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.10] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.11] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.12] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.13] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.14] = $this->marketPrice [287] * 4;
		$this->marketPrice [35.15] = $this->marketPrice [287] * 4;
		$this->marketPrice [41] = $this->marketPrice [266] * 9;
		$this->marketPrice [42] = $this->marketPrice [265] * 9;
		$this->marketPrice [45] = $this->marketPrice [336] * 4;
		$this->marketPrice [47] = ($this->marketPrice [5] * 6) + ($this->marketPrice [340] * 3);
		$this->marketPrice [48] = $this->marketPrice [4] + $this->marketPrice [106];
		$this->marketPrice [50] = $this->marketPrice [263] + $this->marketPrice [280];
		$this->marketPrice [53] = $this->marketPrice [5] * 1.5;
		$this->marketPrice [54] = $this->marketPrice [5] * 8;
		$this->marketPrice [56] = $this->marketPrice [264];
		$this->marketPrice [58] = $this->marketPrice [5] * 4;
		$this->marketPrice [61] = $this->marketPrice [4] * 8;
		$this->marketPrice [63] = $this->marketPrice [5] * 6 + $this->marketPrice [280];
		$this->marketPrice [64] = $this->marketPrice [5] * 6;
		$this->marketPrice [65] = $this->marketPrice [280] * 7;
		$this->marketPrice [67] = $this->marketPrice [4] * 1.5;
		$this->marketPrice [68] = $this->marketPrice [63];
		$this->marketPrice [71] = $this->marketPrice [265] * 6;
		$this->marketPrice [73] = $this->marketPrice [331];
		$this->marketPrice [74] = $this->marketPrice [331];
		$this->marketPrice [78] = $this->marketPrice [80] / 2;
		$this->marketPrice [80] = $this->marketPrice [332] * 4;
		$this->marketPrice [81] = $this->marketPrice [351];
		$this->marketPrice [82] = $this->marketPrice [337] * 4;
		$this->marketPrice [83] = $this->marketPrice [338];
		$this->marketPrice [85] = ($this->marketPrice [5] * 4) + ($this->marketPrice [280] * 2);
		$this->marketPrice [86] = $this->marketPrice [361] * 4;
		$this->marketPrice [87] = $this->marketPrice [405];
		$this->marketPrice [89] = $this->marketPrice [348] * 4;
		$this->marketPrice [91] = $this->marketPrice [86] + $this->marketPrice [50];
		$this->marketPrice [92] = (2 * 3) + ($this->marketPrice [296] * 3) + ($this->marketPrice [353] * 2) + $this->marketPrice [344];
		$this->marketPrice [96] = $this->marketPrice [5] * 6;
		$this->marketPrice [98] = $this->marketPrice [1] * 4;
		$this->marketPrice [103] = $this->marketPrice [360] * 9;
		$this->marketPrice [107] = ($this->marketPrice [5] * 2) + ($this->marketPrice [280] * 4);
		$this->marketPrice [108] = $this->marketPrice [45] * 1.5;
		$this->marketPrice [109] = $this->marketPrice [98] * 1.5;
		$this->marketPrice [110] = $this->marketPrice [2] * 2;
		$this->marketPrice [112] = $this->marketPrice [405] * 4;
		$this->marketPrice [114] = $this->marketPrice [112] * 1.5;
		$this->marketPrice [127] = $this->marketPrice [351] * 3;
		$this->marketPrice [128] = $this->marketPrice [24] * 1.5;
		$this->marketPrice [129] = $this->marketPrice [388];
		$this->marketPrice [133] = $this->marketPrice [388] * 9;
		$this->marketPrice [134] = $this->marketPrice [5] * 1.5;
		$this->marketPrice [135] = $this->marketPrice [5] * 1.5;
		$this->marketPrice [136] = $this->marketPrice [5] * 1.5;
		$this->marketPrice [139] = $this->marketPrice [1];
		$this->marketPrice [139.1] = $this->marketPrice [48];
		$this->marketPrice [152] = $this->marketPrice [331] * 9;
		$this->marketPrice [155] = $this->marketPrice [406] * 4;
		$this->marketPrice [155.1] = $this->marketPrice [155];
		$this->marketPrice [155.2] = $this->marketPrice [155] * 2;
		$this->marketPrice [156] = $this->marketPrice [155] * 1.5;
		$this->marketPrice [163] = $this->marketPrice [5] * 1.5;
		$this->marketPrice [164] = $this->marketPrice [5] * 1.5;
		$this->marketPrice [170] = $this->marketPrice [296] * 9;
		$this->marketPrice [172] = $this->marketPrice [82];
		$this->marketPrice [173] = $this->marketPrice [263] * 9;
		$this->marketPrice [245] = $this->marketPrice [4] * 4;
		$this->marketPrice [246] = $this->marketPrice [49];
		$this->marketPrice [247] = ($this->marketPrice [265] * 6) + ($this->marketPrice [264] * 3);
		$this->marketPrice [281] = $this->marketPrice [5] * 0.75;
		$this->marketPrice [282] = $this->marketPrice [39] + $this->marketPrice [40] + $this->marketPrice [291];
		$this->marketPrice [297] = $this->marketPrice [296] * 3;
		$this->marketPrice [320] = $this->marketPrice [319];
		$this->marketPrice [321] = ($this->marketPrice [280] * 8) + $this->marketPrice [35];
		$this->marketPrice [323] = $this->marketPrice [5] * 3 + $this->marketPrice [280];
		$this->marketPrice [324] = $this->marketPrice [5] * 6;
		$this->marketPrice [325] = $this->marketPrice [265] * 3;
		$this->marketPrice [328] = $this->marketPrice [265] * 5;
		$this->marketPrice [330] = $this->marketPrice [265] * 6;
		$this->marketPrice [339] = $this->marketPrice [338];
		$this->marketPrice [340] = $this->marketPrice [339] * 3 + $this->marketPrice [334];
		$this->marketPrice [352] = $this->marketPrice [351] * 3;
		$this->marketPrice [353] = $this->marketPrice [338];
		$this->marketPrice [354] = $this->marketPrice [92];
		$this->marketPrice [355] = $this->marketPrice [26];
		$this->marketPrice [360] = $this->marketPrice [362];
		$this->marketPrice [357] = ($this->marketPrice [296] * 2) + $this->marketPrice [351];
		$this->marketPrice [359] = $this->marketPrice [265] * 2;
		$this->marketPrice [362] = $this->marketPrice [360];
		$this->marketPrice [459] = ($this->marketPrice [457] * 6) + $this->marketPrice [281];
		$this->marketPrice [44] = $this->marketPrice [1] / 2;
		$this->marketPrice [44.1] = $this->marketPrice [24] / 2;
		$this->marketPrice [44.2] = $this->marketPrice [5] / 2;
		$this->marketPrice [44.3] = $this->marketPrice [4] / 2;
		$this->marketPrice [44.4] = $this->marketPrice [45] / 2;
		$this->marketPrice [44.5] = $this->marketPrice [98] / 2;
		$this->marketPrice [44.6] = $this->marketPrice [112] / 2;
		$this->marketPrice [44.7] = $this->marketPrice [155] / 2;
		$this->marketPrice [157] = $this->marketPrice [5];
		$this->marketPrice [158] = $this->marketPrice [5] / 2;
		$this->marketPrice [43] = $this->marketPrice [44] * 2;
		$this->marketPrice [43.1] = $this->marketPrice [44.1] * 2;
		$this->marketPrice [43.2] = $this->marketPrice [44.2] * 2;
		$this->marketPrice [43.3] = $this->marketPrice [44.3] * 2;
		$this->marketPrice [43.4] = $this->marketPrice [44.4] * 2;
		$this->marketPrice [43.5] = $this->marketPrice [44.5] * 2;
		$this->marketPrice [43.6] = $this->marketPrice [44.6] * 2;
		$this->marketPrice [43.7] = $this->marketPrice [44.7] * 2;
		$this->marketPrice [27] = 0;
		$this->marketPrice [51] = 0;
		$this->marketPrice [52] = 0;
		$this->marketPrice [59] = 0;
		$this->marketPrice [62] = 0;
		$this->marketPrice [66] = 0;
		$this->marketPrice [95] = 0;
		$this->marketPrice [101] = 0;
		$this->marketPrice [102] = 0;
		$this->marketPrice [104] = 0;
		$this->marketPrice [105] = 0;
		$this->marketPrice [141] = 0;
		$this->marketPrice [142] = 0;
		$this->marketPrice [171] = 0;
		$this->marketPrice [244] = 0;
		$this->marketPrice [256] = 0;
		$this->marketPrice [257] = 0;
		$this->marketPrice [258] = 0;
		$this->marketPrice [259] = 0;
		$this->marketPrice [261] = 0;
		$this->marketPrice [262] = 0;
		$this->marketPrice [267] = 0;
		$this->marketPrice [268] = 0;
		$this->marketPrice [269] = 0;
		$this->marketPrice [270] = 0;
		$this->marketPrice [271] = 0;
		$this->marketPrice [272] = 0;
		$this->marketPrice [273] = 0;
		$this->marketPrice [274] = 0;
		$this->marketPrice [275] = 0;
		$this->marketPrice [276] = 0;
		$this->marketPrice [278] = 0;
		$this->marketPrice [279] = 0;
		$this->marketPrice [283] = 0;
		$this->marketPrice [284] = 0;
		$this->marketPrice [285] = 0;
		$this->marketPrice [286] = 0;
		$this->marketPrice [290] = 0;
		$this->marketPrice [291] = 0;
		$this->marketPrice [292] = 0;
		$this->marketPrice [293] = 0;
		$this->marketPrice [294] = 0;
		$this->marketPrice [298] = 0;
		$this->marketPrice [299] = 0;
		$this->marketPrice [300] = 0;
		$this->marketPrice [301] = 0;
		$this->marketPrice [302] = 0;
		$this->marketPrice [303] = 0;
		$this->marketPrice [304] = 0;
		$this->marketPrice [305] = 0;
		$this->marketPrice [306] = 0;
		$this->marketPrice [307] = 0;
		$this->marketPrice [308] = 0;
		$this->marketPrice [309] = 0;
		$this->marketPrice [310] = 0;
		$this->marketPrice [312] = 0;
		$this->marketPrice [313] = 0;
		$this->marketPrice [314] = 0;
		$this->marketPrice [315] = 0;
		$this->marketPrice [316] = 0;
		$this->marketPrice [317] = 0;
		$this->marketPrice [260] = 0;
	}
	public function CEBuyCommand(Player $player, $count = 1, $item = null) {
		if ($this->db ["allow-purchase"] == false) {
			$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
			return;
		}
		if (! isset ( $this->purchaseQueue [$player->getName ()] ) and $item == null) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		} else {
			if ($item == null) $item = $this->purchaseQueue [$player->getName ()] ["id"];
			$check = explode ( ".", $item );
			// 가격시세가 있는지 체크, 없다면 데미지0번 시세가 있는지 체크후 있으면 따르고 없으면 리턴
			if (isset ( $this->marketPrice [$item] )) {
				$price = $this->marketPrice [$item] * $count;
			} else {
				if (isset ( $this->marketPrice [$check [0]] )) {
					$price = $this->marketPrice [$check [0]] * $count;
				} else {
					$this->alert ( $player, $this->get ( "not-found-item-data" ) . " (" . $item . ")" );
					return;
				}
			}
			if (! is_numeric ( $count )) {
				$this->alert ( $player, $this->get ( "buy-or-sell-help-command" ) );
				return;
			}
			if (! isset ( $check [1] )) $check [1] = 0; // 데미지값이 없으면 0으로
			$money = $this->economyAPI->myMoney ( $player );
			if ($money < $price) {
				$this->alert ( $player, $this->get ( "not-enough-money-to-purchase" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
				return;
			}
			$this->economyAPI->reduceMoney ( $player, $price );
			$player->getInventory ()->addItem ( Item::get ( $check [0], $check [1], $count ) );
			
			if (! isset ( $this->itemName [$item] )) {
				$explodeItem = explode ( ".", $item );
				if (isset ( $this->itemName [$explodeItem [0]] )) {
					$itemName = $this->itemName [$explodeItem [0]];
				} else {
					$itemName = "undefied";
				}
			} else {
				$itemName = $this->itemName [$item];
			}
			$this->message ( $player, $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-buyed" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
			if (isset ( $this->db ["settings"] ["seePurchase"] ) and $this->db ["settings"] ["seePurchase"]) {
				foreach ( $this->getServer ()->getOnlinePlayers () as $op ) {
					if ($op->isOp ()) {
						$this->message ( $op, $player->getName () . " : " . $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-buyed" ) );
					}
				}
				$this->getLogger ()->info ( $player->getName () . " : " . $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-buyed" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
			}
			if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
			return;
		}
	}
	public function CESellCommand(Player $player, $count = 1, $item = null) {
		if ($this->db ["allow-purchase"] == false) {
			$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
			return;
		}
		if ($count == $this->get ( "allitem" )) {
			// 전체판매 옵션
			foreach ( $player->getInventory ()->getContents () as $allItem ) {
				$count = $allItem->getCount ();
				$check [0] = $allItem->getId ();
				$check [1] = $allItem->getDamage ();
				// 가격시세가 있는지 체크, 없다면 데미지0번 시세가 있는지 체크후 있으면 따르고 없으면 리턴
				if (isset ( $this->marketPrice [$item] )) {
					$price = $this->marketPrice [$item] * $count;
				} else {
					if (isset ( $this->marketPrice [$check [0]] )) {
						$price = $this->marketPrice [$check [0]] * $count;
					} else {
						continue;
					}
				}
				if ($price == 0) continue;
				$this->economyAPI->addMoney ( $player, $price );
				$money = $this->economyAPI->myMoney ( $player );
				$player->getInventory ()->removeItem ( Item::get ( $check [0], $check [1], $count ) );
			}
			$this->message ( $player, $this->get ( "allitem-selled" ) );
			return;
		}
		if (! isset ( $this->purchaseQueue [$player->getName ()] ) and $item == null) {
			$inhand = $player->getInventory ()->getItemInHand ();
			$count = $inhand->getCount ();
			$item = $inhand->getId ();
			if ($inhand->getDamage () != 0) $item = $item . "." . $inhand->getDamage ();
			// $this->message ( $player, $this->get ( "please-choose-item" ) );
			// return;
		}
		if (isset ( $this->purchaseQueue [$player->getName ()] ["id"] ) and $item == null) {
			$item = $this->purchaseQueue [$player->getName ()] ["id"];
		}
		if ($item == null) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		}
		$check = explode ( ".", $item );
		// 가격시세가 있는지 체크, 없다면 데미지0번 시세가 있는지 체크후 있으면 따르고 없으면 리턴
		if (isset ( $this->marketPrice [$item] )) {
			$price = $this->marketPrice [$item] * $count;
		} else {
			if (isset ( $this->marketPrice [$check [0]] )) {
				$price = $this->marketPrice [$check [0]] * $count;
			} else {
				$this->alert ( $player, $this->get ( "not-found-item-data" ) . " (" . $item . ")" );
				return;
			}
		}
		if (! is_numeric ( $count )) {
			$this->alert ( $player, $this->get ( "buy-or-sell-help-command" ) );
			return;
		}
		if (! isset ( $check [1] )) $check [1] = 0; // 데미지값이 없으면 0으로
		$haveItem = 0;
		foreach ( $player->getInventory ()->getContents () as $inven ) {
			if (! $inven instanceof Item) continue;
			if ($inven->getID () == $check [0] and $inven->getDamage () == $check [1]) {
				$haveItem += $inven->getCount (); // 가진아이템 수 확인
			}
		}
		if ($haveItem < $count) {
			$this->alert ( $player, $this->get ( "not-enough-item" ) . " (" . $this->get ( "my-item-count" ) . " : " . $haveItem . " )" );
			return;
		}
		$this->economyAPI->addMoney ( $player, $price );
		$money = $this->economyAPI->myMoney ( $player );
		$player->getInventory ()->removeItem ( Item::get ( $check [0], $check [1], $count ) );
		
		if (! isset ( $this->itemName [$item] )) {
			$explodeItem = explode ( ".", $item );
			if (isset ( $this->itemName [$explodeItem [0]] )) {
				$itemName = $this->itemName [$explodeItem [0]];
			} else {
				$itemName = "undefied";
			}
		} else {
			$itemName = $this->itemName [$item];
		}
		$this->message ( $player, $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-selled" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
		if (isset ( $this->db ["settings"] ["seePurchase"] ) and $this->db ["settings"] ["seePurchase"]) {
			foreach ( $this->getServer ()->getOnlinePlayers () as $op ) {
				if ($op->isOp ()) {
					$this->message ( $op, $player->getName () . " : " . $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-selled" ) );
				}
			}
			$this->getLogger ()->info ( $player->getName () . " : " . $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-selled" ) . " ( " . $this->get ( "my-money" ) . " : " . $money . " )" );
		}
		if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
		return;
	}
	public function CECreateQueue(Player $player, $item = null) {
		if ($item == null or ! is_numeric ( $item )) {
			$explode = explode ( ":", $item );
			if (isset ( $explode [0] ) and isset ( $explode [1] ) and is_numeric ( $explode [0] ) and is_numeric ( $explode [1] )) {
				$item = $explode [0] . "." . $explode [1];
			} else {
				$this->alert ( $player, $this->get ( "commands-ce-help1" ) );
				return;
			}
		}
		if (! isset ( $this->marketCount [$item] )) {
			$this->alert ( $player, $this->get ( "not-found-item-data" ) . " ( " . $item . " )" );
			return;
		}
		$this->message ( $player, $this->get ( "which-you-want-place-choose-pos" ) );
		$this->createQueue [$player->getName ()] = $item;
	}
	public function CreativeEconomy() {
		// 스케쥴로 매번 위치확인하면서 생성작업시작
		if (! isset ( $this->db ["showCase"] )) return;
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			foreach ( $this->db ["showCase"] as $marketPos => $item ) {
				$explode = explode ( ".", $marketPos );
				if (! isset ( $explode [2] )) continue; // 좌표가 아닐경우 컨티뉴
				$dx = abs ( $explode [0] - $player->x );
				$dy = abs ( $explode [1] - $player->y );
				$dz = abs ( $explode [2] - $player->z ); // XYZ 좌표차이 계산
				                                         
				// 반경 25블럭을 넘어갔을경우 생성해제 패킷 전송후 생성패킷큐를 제거
				if (! ($dx <= 25 and $dy <= 25 and $dz <= 25)) {
					if (! isset ( $this->packetQueue [$player->getName ()] [$marketPos] )) continue;
					
					if (isset ( $this->packetQueue [$player->getName ()] [$marketPos] )) {
						$this->packet ["RemoveEntityPacket"]->eid = $this->packetQueue [$player->getName ()] [$marketPos];
						$player->dataPacket ( $this->packet ["RemoveEntityPacket"] ); // 아이템 제거패킷 전송
						unset ( $this->packetQueue [$player->getName ()] [$marketPos] );
					}
					if (isset ( $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos] )) {
						$this->packet ["RemovePlayerPacket"]->eid = $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos];
						$player->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
						unset ( $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos] );
					}
					continue;
				} else {
					if (! isset ( $this->packetQueue [$player->getName ()] [$marketPos] )) {
						$itemCheck = explode ( ".", $item );
						if (isset ( $itemCheck [1] )) {
							$itemClass = Item::get ( $itemCheck [0], $itemCheck [1] );
						} else {
							$itemClass = Item::get ( $item );
						}
						// 반경 25블럭 내일경우 생성패킷 전송 후 생성패킷큐에 추가
						$this->packetQueue [$player->getName ()] [$marketPos] = Entity::$entityCount ++;
						$this->packet ["AddItemEntityPacket"]->eid = $this->packetQueue [$player->getName ()] [$marketPos];
						$this->packet ["AddItemEntityPacket"]->item = $itemClass;
						$this->packet ["AddItemEntityPacket"]->x = $explode [0] + 0.5;
						$this->packet ["AddItemEntityPacket"]->y = $explode [1];
						$this->packet ["AddItemEntityPacket"]->z = $explode [2] + 0.5;
						$player->dataPacket ( $this->packet ["AddItemEntityPacket"] );
					}
					// 네임택 비활성화 상태이면 네임택비출력
					if (isset ( $this->db ["settings"] ["nametagEnable"] )) if (! $this->db ["settings"] ["nametagEnable"]) continue;
					
					if (! isset ( $this->marketPrice [$item] )) {
						$explodeItem = explode ( ".", $item );
						if (isset ( $this->marketPrice [$explodeItem [0]] )) {
							$marketprice = $this->marketPrice [$explodeItem [0]];
						} else {
							continue;
						}
					} else {
						$marketprice = $this->marketPrice [$item];
					}
					if (! isset ( $this->itemName [$item] )) {
						$explodeItem = explode ( ".", $item );
						if (isset ( $this->itemName [$explodeItem [0]] )) {
							$itemName = $this->itemName [$explodeItem [0]];
						} else {
							continue;
						}
					} else {
						$itemName = $this->itemName [$item];
					}
					
					// 반경 3블럭 내일경우 유저 패킷을 상점밑에 보내서 네임택 출력
					if ($dx <= 4 and $dy <= 4 and $dz <= 4) {
						if (isset ( $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos] )) continue;
						$nameTag = $itemName . "\n" . $this->get ( "price" ) . " : " . $marketprice;
						$this->packetQueue [$player->getName ()] ["nametag"] [$marketPos] = Entity::$entityCount ++;
						$this->packet ["AddPlayerPacket"]->eid = $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos];
						$this->packet ["AddPlayerPacket"]->username = $nameTag;
						$this->packet ["AddPlayerPacket"]->x = $explode [0] + 0.4;
						$this->packet ["AddPlayerPacket"]->y = $explode [1] - 3.2;
						$this->packet ["AddPlayerPacket"]->z = $explode [2] + 0.4;
						$player->dataPacket ( $this->packet ["AddPlayerPacket"] );
					} else if (isset ( $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos] )) {
						$this->packet ["RemovePlayerPacket"]->eid = $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos];
						$player->dataPacket ( $this->packet ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
						unset ( $this->packetQueue [$player->getName ()] ["nametag"] [$marketPos] );
					}
				}
			}
		}
	}
	public function CEAutoSet(Player $player) {
		// 1줄 전자동 상점설치
		if (! isset ( $this->autoCreateQueue [$player->getName ()] )) {
			$this->autoCreateQueue [$player->getName ()] ["pos1"] = null;
			$this->autoCreateQueue [$player->getName ()] ["pos2"] = null;
			$this->message ( $player, $this->get ( "which-you-want-place-choose-pos" ) );
		} else {
			// 포스 1&2 지정되지 않았으면 리턴
			if ($this->autoCreateQueue [$player->getName ()] ["pos1"] == null) {
				$this->alert ( $player, $this->get ( "please-select-pos1" ) );
				return;
			}
			if ($this->autoCreateQueue [$player->getName ()] ["pos2"] == null) {
				$this->alert ( $player, $this->get ( "please-select-pos2" ) );
				return;
			}
			// POS 지정 완료 후 전자동 생성시작
			$pos1 = $this->autoCreateQueue [$player->getName ()] ["pos1"];
			$pos2 = $this->autoCreateQueue [$player->getName ()] ["pos2"];
			
			// 한줄이 아닐경우 리턴
			$dx = $pos1->x - $pos2->x;
			$dy = $pos1->y - $pos2->y;
			$dz = $pos1->z - $pos2->z;
			if (($dx != 0 and $dz != 0) or $dy != 0) {
				$this->alert ( $player, $this->get ( "must-be-a-one-line" ) );
				unset ( $this->autoCreateQueue [$player->getName ()] );
				return;
			}
			
			// 대상 어레이 정리 부분
			// 한줄이 아닌 값을 X Z중에서 찾아 선택
			// 한줄이 아닌 값 중에서 가장작은값을 소트
			if ($dz == 0) {
				// x가 대상일때
				if ($pos1->x > $pos2->x) {
					$max = $pos1->x;
					$min = $pos2->x;
				} else {
					$max = $pos2->x;
					$min = $pos1->x;
				}
			} else {
				// z가 대상일때
				if ($pos1->z > $pos2->z) {
					$max = $pos1->z;
					$min = $pos2->z;
				} else {
					$max = $pos2->z;
					$min = $pos1->z;
				}
			}
			
			// 마켓카운트에서 존재하지않는 마켓 불러오기
			foreach ( $this->marketCount as $item => $marketCount ) {
				if ($marketCount != 0) continue;
				if ($dz == 0) {
					// x가 대상일때
					$pos = new Position ( $min, $pos1->y, $pos1->z, $player->level );
				} else {
					// z가 대상일때
					$pos = new Position ( $pos1->x, $pos1->y, $min, $player->level );
				}
				$this->db ["showCase"] ["{$pos->x}.{$pos->y}.{$pos->z}"] = $item;
				$player->level->setBlock ( $pos, Block::get ( Item::GLASS ), true );
				$this->marketCount [$item] ++;
				// 각 부분마다 위 주석코드 실행
				if (++ $min > $max) {
					unset ( $this->autoCreateQueue [$player->getName ()] );
					$this->message ( $player, $this->get ( "market-completely-created" ) . " (" . $this->get ( "count" ) . " : " . $min - 1 . " )" );
					return; // 모든 블럭에 배치가 끝나면 리턴
				}
			}
		}
	}
	public function ChangeMarketPrice(Player $player, $item = null, $price = null) {
		// 기본가격시세를 입력된 값으로 설정
		if ($item == null) {
			$this->alert ( $player, $this->get ( "commands-ce-help3" ) );
			return;
		}
		if (! is_numeric ( $item )) {
			$explode = explode ( ":", $item );
			if (isset ( $explode [1] ) and is_numeric ( $explode [0] ) and is_numeric ( $explode [1] )) {
				$item = $explode [0] . "." . $explode [1];
			} else {
				$this->alert ( $player, $this->get ( "commands-ce-help3" ) );
				return;
			}
		}
		if ($price == null or ! is_numeric ( $price )) {
			$this->alert ( $player, $this->get ( "commands-ce-help3" ) );
			return;
		}
		if (! isset ( $this->marketPrice [$item] )) {
			$this->alert ( $player, $this->get ( "not-found-item-data" ) . " (" . $item . " )" );
			return;
		}
		$oldPrice = $this->marketPrice [$item];
		$this->marketPrice [$item] = $price;
		$this->message ( $player, $this->get ( "successfully-changed-price" ) );
		$this->message ( $player, "( " . $this->get ( "old-price" ) . " : " . $oldPrice . " / " . $this->get ( "new-price" ) . " : " . $price . " )" );
		
		$save = new Config ( $this->getDataFolder () . "marketPrice.yml", Config::YAML );
		$save->setAll ( $this->marketPrice );
		$save->save ();
	}
	public function AllFreezeMarket(Player $player) {
		if ($this->db ["allow-purchase"] == true) {
			$this->db ["allow-purchase"] = false;
			$this->message ( $player, $this->get ( "market-disabled" ) );
		} else {
			$this->db ["allow-purchase"] = true;
			$this->message ( $player, $this->get ( "market-enabled" ) );
		}
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
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