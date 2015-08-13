<?php

namespace semteul\directauction;

use semteul\directauction\task\DirectAuctionTask;

use chalk\utils\Messages;
use onebone\economyapi\EconomyAPI;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;
use pocketmine\item\Item;



class DirectAuction extends PluginBase implements Listener {
	private $TAG = "[DirectAuction] ";
	const VERSION = "Indev0.1";
	const MESSAGE_VERSION = 1;
	
	private $task = null;
	private $msg;
	private $enable, $configData;
	
	
	
	public function onEnable() {
		$this->getServer()->getLogger()->setLogDebug(true);
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->configData = $this->getConfig()->getAll();
		$this->enable = $this->configData["enable"] ? true : false;
		$this->loadMessages();
		$this->TAG = "[" . $this->msg->getMessage("plugin-prefix") . " " . self::VERSION . "] ";
		$this->commands();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new DirectAuctionTask($this), 20);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		//
		$this->saveResource("chalk-messages-lib-LICENSE", true);
	}
	
	
	
	public function onDisable() {
		$this->forceStop();
		$this->saveConfig();
	}
	
	
	
	private function loadMessages() {
		$this->updateMessages("messages.yml");
		$config = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
		$this->msg = new Messages($config->getAll());
	}
	
	
	
	private function updateMessages($filename = "messages.yml") {
		
		$this->saveResource($filename, false);
		
		$lang = (new Config($this->getDataFolder () . $filename, Config::YAML))->getAll();
		
		if(!isset($lang["message-version"])) {
			$this->saveResource($filename, true);
		}else if($lang["message-version"] != self::MESSAGE_VERSION) {
			$this->saveResource($filename, true);
		}
	}
	
	
	/* NOT USE
	public function msg($msg) {
		if(isset($this->lang[$msg])) {
			if(isset($this->lang[$msg][$this->lang["default-language"]])) {
			return $this->lang[$msg][$this->lang["default-language"]];
			}else if(isset($this->lang[$msg]["eng"])) {
				return $this->lang[$msg]["eng"];
			}
			return $msg;
		}
	}
	*/
	
	
	
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer()->getCommandMap();
		$command = new PluginCommand($name, $this);
		$command->setDescription ($description);
		$command->setPermission ($permission);
		$command->setUsage ($usage);
		$commandMap->register ($fallback, $command);
	}
	
	
	
	private function commands() {
		$this->registerCommand($this->msg->getMessage("command-main"), "DirectAuction", "directauction", $this->msg->getMessage("command-main-description"), $this->msg->getMessage("command-main-usage"));
	}
	
	
	
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if(!$this->enable) {
			return;
		}
		if(!(isset($args[0]))) {
			$text = [];
			$text[] = TextFormat::GREEN . "--- " . $this->msg->getMessage("plugin-name") . " commands ---";
			$text[] = TextFormat::DARK_GREEN . "/" . $this->msg->getMessage("command-main") . " " . $this->msg->getMessage("command-sub-start") . " " . TextFormat::GREEN . $this->msg->getMessage("command-sub-start-description");
			$text[] = TextFormat::DARK_GREEN . "/" . $this->msg->getMessage("command-main") . " " . $this->msg->getMessage("command-sub-bid") . " " . TextFormat::GREEN . $this->msg->getMessage("command-sub-bid-description");
			$text[] = TextFormat::DARK_GREEN . "/" . $this->msg->getMessage("command-main") . " " . $this->msg->getMessage("command-sub-stop") . " " . TextFormat::GREEN . $this->msg->getMessage("command-sub-stop-description");
			$text[] = TextFormat::DARK_GREEN . "/" . $this->msg->getMessage("command-main") . " " . $this->msg->getMessage("command-sub-info") . " " . TextFormat::GREEN . $this->msg->getMessage("command-sub-info-description");
			$text[] = TextFormat::DARK_GREEN . "/" . $this->msg->getMessage("command-main") . " " . "about" . " " . TextFormat::GREEN . "Show plugin infomation";
			foreach($text as $msg) {
				$player->sendMessage($msg);
			}
			return true;
			
		}else {
			switch($args[0]) {
				case $this->msg->getMessage("command-sub-start"):
				
					if(!isset($args[1]) || !isset($args[2]) || !isset($args[3]) || !is_numeric($args[1]) || !is_numeric($args[2]) || !is_numeric($args[3])) {
						$player->sendMessage(TextFormat::GREEN . $this->TAG . "usage: " . $this->msg->getMessage("command-sub-start-usage"));
						return true;
					}
					
					$this->registerAuction($player, floor($args[1]), floor($args[2]), floor($args[3]));
					return true;
					
				case $this->msg->getMessage("command-sub-bid"):
					if(!isset($args[1]) || !is_numeric($args[1])) {
						$player->sendMessage(TextFormat::GREEN . $this->TAG . "usage: " . $this->msg->getMessage("command-sub-bid-usage"));
						return true;
					}
					
					$this->bid($player, floor($args[1]) . "");
					return true;
				case $this->msg->getMessage("command-sub-stop"):
					$this->auctionStop($player);
					return true;
				case $this->msg->getMessage("command-sub-info"):
					$this->sendAuctionInfo($player);
				 return true;
				 
				case "about":
					$player->sendMessage(TextFormat::GREEN . "Original plugin name: DirectAuction");
					$player->sendMessage(TextFormat::GREEN . "Version: " . self::VERSION);
					$player->sendMessage(TextFormat::GREEN . "Author: " . "SemTeul");
					return true;
			}
			return false;
		}
	}
	
	public function auctionTick() {
		if(!$this->enable || $this->task === null) {
			return;
		}
		$sec = --$this->task["sec"];
		$seller = $this->task["seller"];
		$buyer = $this->task["buyer"];
		$price = $this->task["price"];
		$item = $this->task["item"];
		
		if($seller instanceof Player && $seller->isOnline()) {
			$sellerPos = $seller->getPosition();
			$sellerLevel = $seller->getLevel();
			$this->task["sellerPos"] = $sellerPos;
			$this->task["sellerLevel"] = $sellerLevel;
		}else if($this->task["sellerPos"] instanceof Vector3) {
			$sellerPos = $this->task["sellerPos"];
			$sellerLevel = $this->task["sellerLevel"];
		}
		
		if($buyer instanceof Player && $seller->isOnline()) {
			$buyerPos = $buyer->getPosition();
			$buyerLevel = $buyer->getLevel();
			$this->task["buyerPos"] = $buyerPos;
			$this->task["buyerLevel"] = $buyerLevel;
		}else if($this->task["buyerPos"] instanceof Vector3) {
			$buyerPos = $this->task["buyerPos"];
			$buyerLevel = $this->task["buyerLevel"];
		}
		
		$priceStr = $this->stringifyPrice($price);
		
		$index = array_search($sec, $this->configData["broadcast-left-time"]);
		
		if($index !== false) {
			$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-left-time", [
				"second" => TextFormat::AQUA . $sec . TextFormat::DARK_AQUA
			]));
		}
		
		if($sec <= 0) {
			if($buyer === null) {
				$this->task = null;
				//return item
				if($seller->isOnline()) {
					//online
					$inventory = new InventoryManager($seller);
					$inventory->addItem(clone $item);
				}else {
					//offline
					$sellerLevel->dropItem($sellerPos, $item);
				}
				
				$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-no-bid"));
				return;
			}
			
			/*$itemNameTag = $this->stringifyItemName($item);
		$itemEnchantmentsTag = $this->stringifyItemEnchantments($item);*/
		
		$itemNameTag = " (Undead Killer)";
		$itemEnchantmentsTag = " (SHARPNESS IV, LOOTING II)";
			
			$result = EconomyAPI::getInstance()->reduceMoney($buyer, $price, false, $seller);
			//is success
			if($result !== EconomyAPI::RET_SUCCESS) {
				$this->getServer()->broadcastMessage(TextFormat::RED . $this->TAG . $this->msg->getMessage("warning-cant-reduce-money", [
					"player" => TextFormat::DARK_RED . $buyer->getName() . TextFormat::RED,
					"price" => TextFormat::DARK_RED . $this->stringifyPrice($price) . TextFormat::RED
				]));
				//return item
				if($seller->isOnline()) {
					//online
					$inventory = new InventoryManager($seller);
					$inventory->addItem(clone $item);
				}else {
					//offline
					$sellerLevel->dropItem($sellerPos, $item);
				}
				
				return;
			}
			
			$result = EconomyAPI::getInstance()->addMoney($seller, $price, false, $buyer);
			//is success
			if($result !== EconomyAPI::RET_SUCCESS) {
				//force addMoney to buyer
				EconomyAPI::getInstance()->addMoney($buyer, $price, true);
				
				$this->getServer()->broadcastMessage(TextFormat::RED . $this->TAG . $this->msg->getMessage("warning-cant-add-money", [
					"player" => TextFormat::DARK_RED . $seller->getName() . TextFormat::RED,
					"price" => TextFormat::DARK_RED . $this->stringifyPrice($price) . TextFormat::RED
				]));
				//return item
				if($seller->isOnline()) {
					//online
					$inventory = new InventoryManager($seller);
					$inventory->addItem(clone $item);
				}else {
					//offline
					$buyerLevel->dropItem($sellerPos, $item);
				}
				
				return;
			}
			
			if($buyer->isOnline()) {
				//online
				$inventory = new InventoryManager($buyer);
				$inventory->addItem(clone $item);
			}else {
				//offline
				$sellerLevel->dropItem($buyerPos, $item);
			}
			
			$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-bid-successfully", [
				"player" => TextFormat::AQUA . $buyer->getName() . TextFormat::DARK_AQUA,
				"money" => TextFormat::GOLD . $priceStr . TextFormat::DARK_AQUA,
				"item" => TextFormat::AQUA . $item->getName() . $this->stringifyId($item) . TextFormat::DARK_AQUA,
				"count" => TextFormat::AQUA . $item->getCount() . TextFormat::DARK_AQUA
			]));
			
			$this->task = null;
			return;
		}
	}
	
	private function registerAuction($player, $count, $minimumPrice, $second) {
		
		if(!($player instanceof Player)) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-not-player"));
			return;
		}
		
		if($this->task !== null) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-already-ongoing"));
			return;
		}
		
		if(!($count > 0)) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-no-fit-form"));
		}
		
		if($second < $this->configData["minimum-auction-time"]) {
			$min = $this->configData["minimum-auction-time"];
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-short-time", ["second" => TextFormat::RED . $min . TextFormat::DARK_AQUA]));
			return;
		}
		
		if($second > $this->configData["maximum-auction-time"]) {
			$max = $this->configData["maximum-auction-time"];
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-long-time", ["second" => TextFormat::RED . $max . TextFormat::DARK_AQUA]));
			return;
		}
		
		if($minimumPrice < 0) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-price-minus"));
			return;
		}
		
		if(!(EconomyAPI::getInstance()->accountExists($player))) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-no-economy-account"));
			return;
		}
		
		$inventory = new InventoryManager($player);
		$item = $inventory->getItemInHand();
		
		//FOR MCPE 0.12
		/*if($item->hasCompoundTag()) {
			if($count > $item->getCount()) {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-tag-item"));
				return;
			}
			$item = Item::get($item->getId(), $item->getDamage(), 1, $item->getCompoundTag());
			
		}else {
			$maxCount = $inventory->getCount($item->getId(), $item->getDamage());
			if($maxCount < $count) {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-not-enough-item", [
					"item" => TextFormat::AQUA . $item->getName() . TextFormat::DARK_AQUA,
					"count" => TextFormat::RED . $this->stringifyPrice($maxCount) . TextFormat::DARK_AQUA
				]));
				return;
			}
			$item = Item::get($item->getId(), $item->getDamage(), $count);
		}
		
		$itemNameTag = $this->stringifyItemName($item);
		$itemEnchantmentsTag = $this->stringifyItemEnchantments($item);*/
		
		$maxCount = $inventory->getCount($item->getId(), $item->getDamage());
		if($maxCount < $count) {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-not-enough-item", [
					"item" => TextFormat::AQUA . $item->getName() . TextFormat::DARK_AQUA,
					"count" => TextFormat::RED . $this->stringifyPrice($maxCount) . TextFormat::DARK_AQUA
				]));
				return;
			} 
		
		$item = Item::get($item->getId(), $item->getDamage(), $count);
		//End of 0.11
		
		if($inventory->deleteItem(clone $item) === false) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-crash"));
			return;
		}
		
		$this->task = [
			"seller" => $player,
			"buyer" => null,
			"sec" => $second,
			"price" => $minimumPrice,
			"item" => $item,
			"sellerPos" => $player->getPosition(),
			"buyerPos" => null,
			"sellerLevel" => $player->getLevel(),
			"buyerLevel" => null
		];
		
		$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-start", [
			"player" => TextFormat::AQUA . $player->getName() . TextFormat::DARK_AQUA,
			"money" => TextFormat::GOLD . $this->stringifyPrice($minimumPrice) . TextFormat::DARK_AQUA,
			"second" => TextFormat::AQUA . $second . TextFormat::DARK_AQUA,
			"item" => TextFormat::AQUA . $item->getName() . $this->stringifyId($item) . TextFormat::DARK_AQUA,
			"count" => TextFormat::AQUA . $item->getCount() . TextFormat::DARK_AQUA
		]));
		$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-how-to-bid"));
	}
	
	
	
	private function bid($player, $price) {
		
		if(!($player instanceof Player)) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-not-player"));
			return;
		}
		
		if($this->task === null) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-idle"));
			return;
		}
		
		if($this->task["seller"]->getName() === $player->getName()) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-self-bid"));
			return;
		}
		
		if(!(EconomyAPI::getInstance()->accountExists($player))) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-no-economy-account"));
			return;
		}
		
		if(EconomyAPI::getInstance()->myMoney($player) < $price) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-not-enough-money", [
				"money" => TextFormat::AQUA . $this->stringifyPrice(EconomyAPI::getInstance()->myMoney($player)) . TextFormat::DARK_AQUA
			]));
			return;
		}
		
		if($this->task["buyer"] === null) {
			if($this->task["price"] > $price) {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-low-price", [
					"money" => TextFormat::RED . $this->stringifyPrice($this->task["price"]) . TextFormat::DARK_AQUA
				]));
				return;
			}
		}else {
			if($this->task["price"] >= $price) {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-low-price", [
					"money" => TextFormat::RED . $this->stringifyPrice($this->task["price"]) . TextFormat::DARK_AQUA
				]));
				return;
			}
		}
		
		$this->task["buyer"] = $player;
		$this->task["buyerPos"] = $player->getPosition();
		$this->task["buyerLevel"] = $player->getLevel();
		$this->task["price"] = $price;
		
		$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-bid", [
			"money" => TextFormat::GOLD . $this->stringifyPrice($price) . TextFormat::DARK_AQUA,
			"player" => TextFormat::AQUA . $player->getName() . TextFormat::DARK_AQUA
		]));
	}
	
	
	
	public function auctionStop($player) {
		if($this->task === null) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-idle"));
			return;
		}
		
		if($this->task["seller"] === $player) {
			$percent = $this->configData["force-stop-fee-percentage"];
			$fee = ceil(($percent / 100) * $this->task["price"]);
			
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-force-stop-fee", [
				"percent" => TextFormat:: AQUA . $percent . TextFormat::DARK_AQUA,
				"money" => TextFormat::AQUA . $this->stringifyPrice($fee) . TextFormat::DARK_AQUA
			]));
			
			if(EconomyAPI::getInstance()->myMoney($player) < $fee) {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-not-enough-money", [
					"money" => TextFormat::RED . EconomyAPI::getInstance()->myMoney($player) . TextFormat::DARK_AQUA
				]));
				return;
			}else {
				EconomyAPI::getInstance()->reduceMoney($player, $fee);
			}
			
			$this->forceStop();
			$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-force-stop", [
				"player" => TextFormat::AQUA . $player->getName() . TextFormat::DARK_AQUA
			]));
			return;
		}else {
			if($player->isOp()) {
				$this->forceStop();
				$this->getServer()->broadcastMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-force-stop", [
					"player" => TextFormat::DARK_RED . $player->getName() . "(OP)" . TextFormat::DARK_AQUA
				]));
			}else {
				$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-no-permission"));
			}
			return;
		}
	}
	
	
	
	public function sendAuctionInfo($player) {
		if($this->task === null) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("warning-idle"));
			return true;
		}
		
		$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-title"));
		
		$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-name", [
			"info" => TextFormat::AQUA . $this->task["item"]->getName() . TextFormat::DARK_AQUA
		]));
		
		$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-durability", [
			"info" => TextFormat::AQUA . $this->task["item"]->getDamage() . TextFormat::DARK_AQUA
		]));
		
		$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-count", [
			"info" => TextFormat::AQUA . $this->task["item"]->getCount() . TextFormat::DARK_AQUA
		]));
		
		/*if($this->task["item"]->hasCustomName()) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-custom-name", [
				"info" => TextFormat::AQUA . $this->task["item"]->getCustomName() . TextFormat::DARK_AQUA
			]));
		}
		
		if($this->task["item"]->hasEnchantments()) {
			$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-enchant", [
				"info" => TextFormat::AQUA . $this->stringifyItemEnchantments($this->task["item"]) . TextFormat::DARK_AQUA
			]));
		}*/
		
		$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-price", [
			"info" => TextFormat::GOLD . $this->stringifyPrice($this->task["price"]) . TextFormat::DARK_AQUA
		]));
		
		$player->sendMessage(TextFormat::DARK_AQUA . $this->TAG . $this->msg->getMessage("auction-info-left-time", [
			"info" => TextFormat::AQUA . $this->task["sec"] . TextFormat::DARK_AQUA
		]));
	}
	
	
	
	public function forceStop() {
		if($this->task === null) {
			return;
		}
		$seller = $this->task["seller"];
		$sellerLevel = $this->task["sellerLevel"];
		$sellerPos = $this->task["sellerPos"];
		$item = $this->task["item"];
		if($seller->isOnline()) {
			//online
			$inventory = new InventoryManager($seller);
			$inventory->addItem(clone $item);
		}else {
			//offline
			$sellerLevel->dropItem($sellerPos, $item, null, 0);
		}
		
		$this->task = null;
	}
	
	
	
	public function stringifyPrice($int) {
		$int = floor($int);
		$str = "";
		$count = mb_strlen($int);
		$count2 = floor(($count - 1) / 3);
		$extra = $count % 3;
		
		if($count2 <= 0) {
			return $int . "";
		}
		
		switch($extra) {
			case 0:
				$str .= mb_substr($int, 0, 3);
				for($e = 0; $e < $count2; $e++) {
					$str .= "," . mb_substr($int, 3 + (3 * $e), 3);
				}
				break;
			case 1:
				$str .= mb_substr($int, 0, 1);
				for($e = 0; $e < $count2; $e++) {
					$str .= "," . mb_substr($int, 1 + (3 * $e), 3);
				}
				break;
			case 2:
				$str .= mb_substr($int, 0, 2);
				for($e = 0; $e < $count2; $e++) {
					$str .= "," . mb_substr($int, 2 + (3 * $e), 3);
				}
				break;
		}
		return $str;
	}
	
	
	
	public function stringifyId($item) {
		if(!($item instanceof Item)) {
			return false;
		}
		return "(" . $item->getId() . ":" . $item->getDamage() . ")";
	}
	
	
	
	/*public function stringifyItemName($item) {
		if($item->hasCustomName()) {
			return $item->getCustomName();
		}else {
			return "";
		}
	}
	
	
	
	public function stringifyItemEnchantments($item) {
		if($item->hasEnchantments()) {
			$effects = $item->getEnchantments();
			$effectStr = "";
			foreach($effects as $effect) {
				if($effectStr !== "") {
					$effectStr .= ", ";
				}
				$effectStr .= $effect->getName() . " " . $effect->getLevel();
			}
			return $effectStr;
		}else {
			return "";
		}
	}*/
}
?>