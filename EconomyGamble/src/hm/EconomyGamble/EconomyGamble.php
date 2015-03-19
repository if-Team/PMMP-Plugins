<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\EconomyGamble;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\tile\Sign;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerJoinEvent;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\block\SignChangeEvent;

class EconomyGamble extends PluginBase implements Listener {
	/**
	 *
	 * @var economyAPI
	 */
	private $api;
	
	/**
	 *
	 * @var YAML config variable
	 */
	public $lotto, $probability, $language, $signform;
	/**
	 *
	 * @var Queue variable
	 */
	public $tap = [ ];
	public $placeQueue = [ ];
	public function onEnable() {
		if (! file_exists ( $this->getDataFolder () )) mkdir ( $this->getDataFolder () );
		$this->api = EconomyAPI::getInstance ();
		$this->initYML ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "lotto.yml", Config::YAML );
		$save->setAll ( $this->lotto );
		$save->save ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $params) {
		if ($command->getName () == "gamblehow") {
			if (! isset ( $params [0] )) {
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-a" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-b" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-c" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow gamble" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow itemgamble" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow lottery" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow random" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
				return true;
			}
			switch ($params [0]) {
				case "gamble" :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-d" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-Gamble-a" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-Gamble-b" ) );
					break;
				case "itemgamble" :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-d" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-ItemGamble" ) );
					break;
				case "lottery" :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-d" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-Lottery" ) );
					break;
				case "random" :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-d" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-Random" ) );
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-a" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-b" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->getMessage ( "Gamblehow-c" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow gamble" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow itemgamble" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow lottery" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/gamblehow random" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
					break;
			}
			return true;
		}
	}
	public function onSignChange(SignChangeEvent $event) {
		$player = $event->getPlayer ();
		if ($event->getLine ( 0 ) == "gamble") {
			if (! is_numeric ( $event->getLine ( 1 ) )) {
				$player->sendMessage ( $this->getMessage ( "mustnumberic" ) );
				return;
			}
			$event->setLine ( 0, $this->getSignForm ( "a1" ) );
			$event->setLine ( 1, $this->getSignForm ( "a2" ) . '$' . $event->getLine ( 1 ) );
			$event->setLine ( 2, $this->getSignForm ( "a3" ) );
			$event->setLine ( 3, $this->getSignForm ( "a4" ) );
			$player->sendMessage ( $this->getMessage ( "GambleSetupFinish" ) );
		}
		if ($event->getLine ( 0 ) == "itemgamble") {
			$event->setLine ( 0, $this->getSignForm ( "b1" ) );
			$event->setLine ( 1, $this->getSignForm ( "b2" ) );
			$event->setLine ( 2, $this->getSignForm ( "b3" ) );
			$event->setLine ( 3, $this->getSignForm ( "b4" ) );
			$player->sendMessage ( $this->getMessage ( "ItemGambleSetupFinish" ) );
		}
		if ($event->getLine ( 0 ) == "lottery") {
			$event->setLine ( 0, $this->getSignForm ( "c1" ) );
			$event->setLine ( 1, $this->getSignForm ( "c2" ) . '$' . $this->probability ["LotteryPrice"] );
			$event->setLine ( 2, $this->getSignForm ( "c3" ) );
			$event->setLine ( 3, $this->getSignForm ( "c4" ) . '$' . $this->probability ["LotteryCompensation"] );
			$player->sendMessage ( $this->getMessage ( "LotterySetupFinish" ) );
		}
		if ($event->getLine ( 0 ) == "randomgamble") {
			$event->setLine ( 0, $this->getSignForm ( "d1" ) );
			$event->setLine ( 1, $this->getSignForm ( "d2" ) );
			$event->setLine ( 2, $this->getSignForm ( "d3" ) );
			$event->setLine ( 3, $this->getSignForm ( "d4" ) );
			$player->sendMessage ( $this->getMessage ( "RandomGambleSetupFinish" ) );
		}
	}
	public function playerBlockTouch(PlayerInteractEvent $event) {
		$sender = $event->getPlayer ();
		$block = $event->getBlock ();
		$itemid = $event->getItem ()->getID ();
		$itemdamage = $event->getItem ()->getDamage ();
		
		if (isset ( $this->tap [$sender->getName ()] ) and $this->tap [$sender->getName ()] [0] === $block->x . ":" . $block->y . ":" . $block->z and (time () - $this->tap [$sender->getName ()] [1]) <= 2) {
			if ($block->getID () == 323 or $block->getID () == 63 or $block->getID () == 68) {
				$sign = $event->getPlayer ()->getLevel ()->getTile ( $block );
				if ($sign instanceof Sign) {
					$sign = $sign->getText ();
					if ($sign [0] == $this->getSignForm ( "a1" )) {
						if (isset ( $sign [1] )) {
							$e = explode ( "$", $sign [1] );
							$money = $e [1];
							$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
							if (! is_numeric ( $money ) or $money == 0) {
								$sender->sendMessage ( $this->getMessage ( "WrongFormat-Sign" ) );
								if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
								return 0;
							}
							if ($mymoney < $money) {
								$sender->sendMessage ( $this->getMessage ( "LackOfhMoney" ) );
								if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
								return 0;
							}
							$rand = rand ( 1, $this->getProbability ( "GambleProbability", 1 ) );
							if ($rand <= $this->getProbability ( "GambleProbability", 0 )) {
								$this->api->addMoney ( $sender, $money );
								$sender->sendMessage ( $this->getMessage ( "SuccessGamble" ) );
							} else {
								$this->api->reduceMoney ( $sender, $money );
								$sender->sendMessage ( $this->getMessage ( "FailGamble" ) );
							}
						}
						if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
					}
					if ($sign [0] == $this->getSignForm ( "b1" )) {
						if ($itemid == 0) {
							$sender->sendMessage ( $this->getMessage ( "noitem" ) );
							if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
							return;
						}
						$rand = rand ( 1, $this->getProbability ( "ItemGambleProbability", 1 ) );
						if ($rand <= $this->getProbability ( "ItemGambleProbability", 0 )) {
							$sender->getInventory ()->addItem ( Item::get ( $itemid, $itemdamage, 1 ) );
							$sender->sendMessage ( $this->getMessage ( "SuccessitemGamble" ) );
						} else {
							$this->removeItem ( $sender, Item::get ( $itemid, $itemdamage, 1 ) );
							$sender->sendMessage ( $this->getMessage ( "FailitemGamble" ) );
						}
						if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
					}
					if ($sign [0] == $this->getSignForm ( "c1" )) {
						$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
						if ($mymoney < $this->probability ["LotteryPrice"]) {
							$sender->sendMessage ( $this->getMessage ( "LackOfhMoney" ) );
							if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
							return;
						}
						$lotto_c = 0;
						if (isset ( $this->lotto [$sender->getName ()] )) {
							$get = $this->lotto [$sender->getName ()];
							$lotto_c = $get ["count"];
						}
						$this->lotto [$sender->getName ()] = array ("count" => ++ $lotto_c,"day" => date ( "d" ) );
						
						$this->api->reduceMoney ( $sender, $this->getProbability ( "LotteryPrice", 0 ) );
						$sender->sendMessage ( $this->getMessage ( "LotteryPayment" ) . $this->lotto [$sender->getName ()] ["count"] );
						$sender->sendMessage ( $this->getMessage ( "LotteryPayment-a" ) );
						if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
					}
					if ($sign [0] == $this->getSignForm ( "d1" )) {
						$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
						if ($mymoney == 0) {
							$sender->sendMessage ( $this->getMessage ( "LackOfhMoney" ) );
							if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
							return 0;
						}
						$pay = rand ( 1, $mymoney );
						$rand = rand ( 1, $this->getProbability ( "RandomGambleProbability", 1 ) );
						if ($rand <= $this->getProbability ( "RandomGambleProbability", 0 )) {
							$this->api->addMoney ( $sender, $pay );
							$sender->sendMessage ( $this->getMessage ( "SuccessGamble" ) . " $" . $pay . $this->getMessage ( "SuccessGamble-a" ) );
						} else {
							$this->api->reduceMoney ( $sender, $pay );
							$sender->sendMessage ( $this->getMessage ( "FailGamble" ) . " $" . $pay . $this->getMessage ( "FailGamble-a" ) );
						}
						if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
					}
					unset ( $this->tap [$sender->getName ()] );
				}
			}
		} else if ($block->getID () == 323 or $block->getID () == 63 or $block->getID () == 68) {
			$sign = $event->getPlayer ()->getLevel ()->getTile ( $block );
			if ($sign instanceof Sign) {
				$sign = $sign->getText ();
				if ($sign [0] === $this->getSignForm ( "a1" ) or $sign [0] === $this->getSignForm ( "b1" ) or $sign [0] === $this->getSignForm ( "c1" ) or $sign [0] === $this->getSignForm ( "d1" )) {
					$this->tap [$sender->getName ()] = array ($block->x . ":" . $block->y . ":" . $block->z,time () );
					$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
					$sender->sendMessage ( $this->getMessage ( "AskAgain" ) . $mymoney . "]" );
					if ($event->getItem ()->isPlaceable ()) $this->placeQueue [$sender->getName ()] = true;
				}
			}
		}
	}
	public function getMessage($var) {
		return $this->language [$this->language ["setlanguage"] . "-" . $var];
	}
	public function getSignForm($var) {
		return $this->signform [$this->language ["setlanguage"] . "-" . $var];
	}
	public function getProbability($var, $num) {
		$e = explode ( "/", $this->probability [$var] );
		return $e [$num];
	}
	public function onPlace(BlockPlaceEvent $event) {
		$username = $event->getPlayer ()->getName ();
		if (isset ( $this->placeQueue [$username] )) {
			$event->setCancelled ( true );
			unset ( $this->placeQueue [$username] );
		}
	}
	public function lottocheck(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		if (isset ( $this->lotto [$player->getName ()] )) {
			$get = $this->lotto [$player->getName ()];
			$day = $get ["day"];
			$count = $get ["count"];
			
			if ($count != 0 and date ( "d" ) != $day) {
				$player->sendMessage ( $this->getMessage ( "LotteryCheck" ) );
				$resultCount = $count;
				for($i = 1; $i <= $count; $i ++) {
					$rand = rand ( 1, $this->getProbability ( "LotteryProbability", 1 ) );
					if (! ($rand <= $this->getProbability ( "LotteryProbability", 0 ))) -- $resultCount;
				}
				if ($resultCount == 0) {
					$player->sendMessage ( $this->getMessage ( "LotteryCheck-a" ) . " " . $count . $this->getMessage ( "LotteryCheck-b" ) );
					$player->sendMessage ( $this->getMessage ( "FailGamble" ) );
				} else {
					$this->api->addMoney ( $player, $resultCount * $this->getProbability ( "LotteryCompensation", 0 ) );
					foreach ( $this->getServer ()->getOnlinePlayers () as $p ) {
						$p->sendMessage ( $player->getName () . $this->getMessage ( "SuccessLottery" ) );
						$p->sendMessage ( $this->getMessage ( "SuccessLottery-a" ) . $count . $this->getMessage ( "SuccessLottery-b" ) . $resultCount * $this->getProbability ( "LotteryCompensation", 0 ) . $this->getMessage ( "SuccessLottery-c" ) );
					}
				}
				unset ( $this->lotto [$player->getName ()] );
			}
		}
	}
	public function removeItem($sender, $getitem) {
		$getcount = $getitem->getCount ();
		if ($getcount <= 0) return;
		for($index = 0; $index < $sender->getInventory ()->getSize (); $index ++) {
			$setitem = $sender->getInventory ()->getItem ( $index );
			if ($getitem->getID () == $setitem->getID () and $getitem->getDamage () == $setitem->getDamage ()) {
				if ($getcount >= $setitem->getCount ()) {
					$getcount -= $setitem->getCount ();
					$sender->getInventory ()->setItem ( $index, Item::get ( Item::AIR, 0, 1 ) );
				} else if ($getcount < $setitem->getCount ()) {
					$sender->getInventory ()->setItem ( $index, Item::get ( $getitem->getID (), 0, $setitem->getCount () - $getcount ) );
					break;
				}
			}
		}
	}
	public function initYML() {
		$this->saveResource ( "lotto.yml", false );
		$this->saveResource ( "probability.yml", false );
		$this->saveResource ( "language.yml", false );
		$this->saveResource ( "signform.yml", false );
		$this->lotto = (new Config ( $this->getDataFolder () . "lotto.yml", Config::YAML ))->getAll ();
		$this->probability = (new Config ( $this->getDataFolder () . "probability.yml", Config::YAML ))->getAll ();
		$this->language = (new Config ( $this->getDataFolder () . "language.yml", Config::YAML ))->getAll ();
		$this->signform = (new Config ( $this->getDataFolder () . "signform.yml", Config::YAML ))->getAll ();
	}
}