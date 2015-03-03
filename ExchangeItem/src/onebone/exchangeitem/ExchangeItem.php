<?php

namespace onebone\exchangeitem;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\Listener;

class ExchangeItem extends PluginBase implements Listener{
	private $acception, $request;
	
	public function onEnable(){
		$this->acception = [];
		$this->request = [];
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		switch($command->getName()){
			case "exchange":
			if(!$sender instanceof Player){
				$sender->sendMessage("[ExchangeItem] Please run this command in-game.");
				return true;
			}
			$player = array_shift($params);
			if(trim($player) === ""){
				$sender->sendMessage("[ExchangeItem] Usage: ".$command->getUsage());
				return true;
			}
			
			if(count($params) === 0){
				switch($player){
				case "accept":
					if(isset($this->acception[$sender->getName()])){
						$requester = $this->getServer()->getPlayerExact($this->acception[$sender->getName()]["requester"]);
						if($requester === null){
							$sender->sendMessage("[ExchangeItem] The requester is currently offline.");
						}else{
							$item = $this->acception[$sender->getName()]["item"];
							$exchange = $this->acception[$sender->getName()]["exchange"];
							$i = Item::get($item[0], $item[1], $item[2]);
							$e = Item::get($exchange[0], $exchange[1], $exchange[2]);
							if($e->getId() !== 0 and !$sender->getInventory()->contains($e)){
								$requester->sendMessage("[ExchangeItem] Target player doesn't have enough item. Request cancelled.");
								$sender->sendMessage("[ExchangeItem] You don't have enough item. Request cancelled.");  // TODO cancel request
								return true; 
							}
							
							if($i->getId() !== 0 and !$requester->getInventory()->contains($i)){
								$sender->sendMessage("[ExchangeItem] Requester doesn't have enough item. Request cancelled.");
								$requester->sendMessage("[ExchangeItem] You don't have enough item to exchange. Request cancelled.");
								return true;
							}
							$sender->getInventory()->addItem($i);
							$requester->getInventory()->removeItem($i);
							
							$sender->getInventory()->removeItem($e);
							$requester->getInventory()->addItem($e);
							
							$requester->sendMessage("[ExchangeItem] You have exchanged the item with ".$sender->getName());
							$sender->sendMessage("[ExchangeItem] You have exchanged the item with ".$requester->getName());
						}
					}else{
						$sender->sendMessage("[ExchangeItem] You have no request to accept.");
						return true;
					}
					return true;
				case "decline":
					if(isset($this->acception[$sender->getName()])){
						unset($this->acception[$sender->getName()]);
						unset($this->request[$this->acception[$sender->getName()]["target"]]);
						$sender->sendMessage("[ExchangeItem] Your request have been cancelled.");
					}else{
						$sender->sendMessage("[ExchangeItem] You have no request to decline.");
						return true;
					}
				case "cancel":
					if(isset($this->request[$sender->getName()])){
						unset($this->acception[$this->request[$sender->getName()]]);
						unset($this->request[$sender->getName()]);
						$sender->sendMessage("[ExchangeItem] You have cancelled the request.");
						return true;
					}else{
						$sender->sendMessage("[ExchangeItem] You have no request to cancel.");
						return true;
					}
				case "item":
					if(isset($this->acception[$sender->getName()])){
						$item = $this->acception[$sender->getName()]["item"];
						$exchange = $this->acception[$sender->getName()]["exchange"];
						
						$i = Item::get($item[0], $item[1], $item[2]);
						$e = Item::get($exchange[0], $exchange[1], $exchange[2]);
						
						$sender->sendMessage("You have requested exchange to ".$this->acception[$sender->getName()]["target"]."\nYou have to give ".$i->getCount()." of ".$i->getName()."\nExchanging with ".$e->getCount()." of ".$e->getName());
					}elseif(isset($this->request[$sender->getName()])){
						$item = $this->acception[$sender->getName()]["item"];
						$exchange = $this->acception[$sender->getName()]["exchange"];
						
						$i = Item::get($item[0], $item[1], $item[2]);
						$e = Item::get($exchange[0], $exchange[1], $exchange[2]);
						
						$sender->sendMessage("You have got an request from ".$this->acception[$sender->getName()]["target"]."\nYou have to give : ".$e->getCount()." of ".$e->getName()."\nExchanging with ".$i->getCount()." of ".$i->getName());
					}else{
						$sender->sendMessage("[ExchangeItem] You don't have any request.");
					}
				break;
				default:
					$sender->sendMessage("[ExchangeItem] Usage: ".$command->getUsage());
					return true;
				}
			}
			
			$item = array_shift($params);
			$count = array_shift($params);
			
			if(trim($item) === "" or !is_numeric($count)){
				$sender->sendMessage("[ExchangeItem] Usage: /exchange <player> <item> <count>");
			}			
			
			if(isset($this->request[$sender->getName()])){
				$sender->sendMessage("[ExchangeItem] You already have a request ongoing.");
				return true;
			}
			
			$playerInst = $this->getServer()->getPlayer($player);
			if($playerInst === null){
				$sender->sendMessage("[ExchangeItem] Your requested player is not online.");
				return true;
			}
			
			$exchange = Item::fromString($item);
			if($exchange->getId() == 0){
				$sender->sendMessage("[ExchangeItem] There's no item called ".$item);
				return true;
			}
			$count = (int)$count;
			
			$exchange->setCount($count);
			if(!$playerInst->getInventory()->contains($exchange)){
				$sender->sendMessage($playerInst->getName()." doesn't have requested item.");
				return true;
			}
			
			$this->request[$sender->getName()] = $playerInst->getName();
			
			$item = $sender->getInventory()->getItemInHand();
			$this->acception[$playerInst->getName()] = [
				"item" => [$item->getId(), $item->getDamage(), $item->getCount()],
				"exchange" => [$exchange->getId(), $exchange->getDamage(), $exchange->getCount()],
				"requester" => $sender->getName()
			];
			$sender->sendMessage("[ExchangeItem] You have sent an exchange request to ".$playerInst->getName());
			$playerInst->sendMessage("[ExchangeItem] You have got an item exchange request from ".$sender->getName()."\nItem :".$item->getCount()." of ".$item->getName()."\nExchanging with ".$exchange->getCount()." of ".$exchange->getName());
			
			return true;
		}
	}
	
	public function onQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		if(isset($this->request[$player->getName()])){
			$target = $this->getServer()->getPlayer($this->request[$player->getName()]);
			if($target instanceof Player){
				$target->sendMessage("[ExchangeItem] Your item exchange requester left the server.");
			}
			unset($this->acception[$this->request[$player->getName()]], $this->request[$player->getName()]);
		}elseif(isset($this->acception[$player->getName()])){
			$requester = $this->getServer()->getPlayer($this->acception[$player->getName()]["requester"]);
			if($requester instanceof Player){
				$requester->sendMessage("[ExchangeItem] Your item exchange target left the server.");
			}
			unset($this->acception[$player->getName()], $this->request[$this->acception[$player->getName()]["requester"]]);
		}
	}
}