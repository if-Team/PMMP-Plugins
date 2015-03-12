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
use pocketmine\network\protocol\MoveEntityPacket;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\item\Item;

class CreativeEconomy extends PluginBase implements Listener {
	private static $instance = null;
	public $messages, $db;
	public $marketCount, $marketPrice, $itemName;
	public $economyAPI = null;
	public $purchaseQueue = [ ];
	public $createQueue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		$this->saveResource ( "marketPrice.yml", false );
		$this->saveResource ( "marketCount.yml", false );
		$this->saveResource ( $this->messages ["default-language"] . "_item_data.yml", false );
		
		$this->db = (new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML, [ 
				"allow-purchase" => true ] ))->getAll ();
		$this->marketCount = (new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML ))->getAll ();
		$this->marketPrice = (new Config ( $this->getDataFolder () . "marketPrice.yml", Config::YAML ))->getAll ();
		$this->itemName = (new Config ( $this->getDataFolder () . $this->messages ["default-language"] . "_item_data.yml", Config::YAML ))->getAll ();
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) === null) {
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
		if (isset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] )) {
			if ($event->getPlayer ()->hasPermission ( "creativeeconomy.shop.use" )) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			if ($this->db ["allow-purchase"] == false) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			$this->purchaseQueue [$event->getPlayer ()->getName ()] ["id"] = $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"];
			$this->message ( $event->getPlayer (), $this->get ( "you-can-buy-or-sell" ) );
			$this->message ( $event->getPlayer (), $this->get ( "buy-or-sell-help-command" ) );
			$event->setCancelled ();
		}
		if (isset ( $this->createQueue [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$pos = $block->getSide ( 0 );
			$item = $this->createQueue [$event->getPlayer ()->getName ()];
			$this->db ["signMarket"] ["{$pos->x}:{$pos->y}:{$pos->z}"] = $item;
			$block->getLevel ()->setBlock ( $pos, Block::get ( Item::GLASS ), true );
			unset ( $this->createQueue [$event->getPlayer ()->getName ()] );
			$this->message ( $player, $this->get ( "market-completely-created" ) );
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] )) {
			$player = $event->getPlayer ();
			if (! $player->hasPermission ( "creativeeconomy.shop.break" )) {
				$this->alert ( $player, $this->get ( "ko-market-cannot-break" ) );
				$event->setCancelled ();
			}
			unset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] );
			$this->message ( $player, "ko-market-completely-destroyed" );
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, $args) {
		if (! $sender instanceof Player) {
			$this->alert ( $player, $this->get ( "only-in-game" ) );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-buy" ) :
				(isset ( $args [0] )) ? $this->CEBuyCommand ( $player, $args [0] ) : $this->CEBuyCommand ( $player );
				break;
			case $this->get ( "commands-sell" ) :
				(isset ( $args [0] )) ? $this->CESellCommand ( $player, $args [0] ) : $this->CESellCommand ( $player );
				break;
			case $this->get ( "commands-ce" ) :
				if (! isset ( $args [0] )) {
					$this->get ( "ko-commands-ce-help1" );
					$this->get ( "ko-commands-ce-help2" );
					$this->get ( "ko-commands-ce-help3" );
					$this->get ( "ko-commands-ce-help4" );
					break;
				}
				switch ($args [0]) {
					case $this->get ( "sub-commands-create" ) :
						(isset ( $args [1] )) ? $this->CECreateQueue ( $player, $args [1] ) : $this->CECreateQueue ( $player );
						break;
					case $this->get ( "sub-commands-autocreate" ) :
						$this->CEAutoSet ( $player );
						break;
					case $this->get ( "sub-commands-change" ) :
						(isset ( $args [1] )) ? $this->ChangeMarketPrice ( $player, $args [1] ) : $this->ChangeMarketPrice ( $player );
						break;
					case $this->get ( "sub-commands-lock" ) :
						$this->AllFreezeMarket ( $player );
						break;
					default :
						$this->get ( "ko-commands-ce-help1" );
						$this->get ( "ko-commands-ce-help2" );
						$this->get ( "ko-commands-ce-help3" );
						$this->get ( "ko-commands-ce-help4" );
						break;
				}
				break;
		}
		return true;
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
			if ($item == null) $item = $this->purchaseQueue [$player->getName ()];
			if (! isset ( $this->marketPrice [$item] )) {
				$this->alert ( $player, $this->get ( "not-found-item-data" ) );
				return;
			}
			$price = $this->marketPrice [$item] * $count;
			$check = explode ( ".", $item );
			if (! is_numeric ( $count ) and ! isset ( $check [1] )) {
				$this->alert ( $player, $this->get ( "buy-or-sell-help-command" ) );
				return;
			}
			if (! isset ( $check [1] )) $check [1] = 0;
			$money = $this->economyAPI->myMoney ( $player );
			if ($money < $price) {
				$this->alert ( $player, $this->get ( "not-enough-money-to-purchase" ) );
				return;
			}
			$this->economyAPI->reduceMoney ( $player, $price );
			$player->getInventory ()->addItem ( Item::get ( $check [0], $check [1], $count ) );
			(! isset ( $this->itemName [$item] )) ? $itemName = "undefied" : $itemName = $this->itemName [$item];
			$this->message ( $player, $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-buyed" ) );
			if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
			return;
		}
	}
	public function CESellCommand(Player $player, $count = 1) {
		if ($this->db ["allow-purchase"] == false) {
			$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
			return;
		}
		if (! isset ( $this->purchaseQueue [$player->getName ()] ) and $item == null) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		} else {
			if ($item == null) $item = $this->purchaseQueue [$player->getName ()];
			if (! isset ( $this->marketPrice [$item] )) {
				$this->alert ( $player, $this->get ( "not-found-item-data" ) );
				return;
			}
			$price = $this->marketPrice [$item] * $count;
			$check = explode ( ".", $item );
			if (! is_numeric ( $count ) and ! isset ( $check [1] )) {
				$this->alert ( $player, $this->get ( "buy-or-sell-help-command" ) );
				return;
			}
			if (! isset ( $check [1] )) $check [1] = 0;
			$haveItem = 0;
			foreach ( $player->getInventory ()->getContents () as $inven ) {
				if (! $inven instanceof Item) return;
				if ($inven->getID () == $check [0] and $inven->getDamage () == $check [1]) {
					$haveItem = $inven->getCount ();
				}
			}
			if ($haveItem < $count) {
				$this->alert ( $player, $this->get ( "not-enough-item" ) );
				return;
			}
			$this->economyAPI->addMoney ( $player, $price );
			$player->getInventory ()->addItem ( Item::get ( $check [0], $check [1], $count ) );
			(! isset ( $this->itemName [$item] )) ? $itemName = "undefied" : $itemName = $this->itemName [$item];
			$this->message ( $player, $itemName . "({$item})({$count}) " . $this->get ( "is-successfully-selled" ) );
			if (isset ( $this->purchaseQueue [$player->getName ()] )) unset ( $this->purchaseQueue [$player->getName ()] );
			return;
		}
	}
	public function CECreateQueue(Player $player, $item = null) {
		if ($item == null or ! is_numeric ( $item )) {
			$this->alert ( $player, $this->get ( "commands-ce-help1" ) );
			return;
		}
		$this->message ( $player, $this->get ( "which-you-want-place-choose-pos" ) );
		$this->createQueue [$player->getName ()] = $item;
	}
	public function CreativeEconomy() {
		// 스케쥴로 매번 위치확인하면서 생성작업시작
		// 아예 별도로 생성확인변수도 만들어서
		// 위치에 맞게 생성패킷을 보냈는지 체크 후
		// 생성되지않았을경우(새로생기거나 유저가 이동했거나)
		// 해당 생성패킷을 보내고 =1 처리하도록 처리
		// 만약에 멀어진 경우 생성해제 패킷을 보내고 =0처리
	}
	public function CEAutoSet() {
		// TODO 1줄 전자동 상점설치
	}
	public function ChangeMarketPrice(Player $player, $item = null) {
		// TODO 기본가격시세를 입력된 값으로 설정
	}
	public function AllFreezeMarket() {
		if ($this->db ["allow-purchase"] == true) {
			$this->db ["allow-purchase"] = false;
			$this->message ( $player, $this->get ( "market-enabled" ) );
		} else {
			$this->db ["allow-purchase"] = true;
			$this->message ( $player, $this->get ( "market-disabled" ) );
		}
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function message(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>