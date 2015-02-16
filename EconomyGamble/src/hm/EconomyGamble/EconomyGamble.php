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
		if (! file_exists ( $this->getDataFolder () ))
			mkdir ( $this->getDataFolder () );
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
			if (! isset ( $params [0] ))
				return;
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
								if ($event->getItem ()->isPlaceable ())
									$this->placeQueue [$sender->getName ()] = true;
								return 0;
							}
							if ($mymoney < $money) {
								$sender->sendMessage ( $this->getMessage ( "LackOfhMoney" ) );
								if ($event->getItem ()->isPlaceable ())
									$this->placeQueue [$sender->getName ()] = true;
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
						if ($event->getItem ()->isPlaceable ())
							$this->placeQueue [$sender->getName ()] = true;
					}
					if ($sign [0] == $this->getSignForm ( "b1" )) {
						if ($itemid == 0) {
							$sender->sendMessage ( $this->getMessage ( "noitem" ) );
							if ($event->getItem ()->isPlaceable ())
								$this->placeQueue [$sender->getName ()] = true;
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
						if ($event->getItem ()->isPlaceable ())
							$this->placeQueue [$sender->getName ()] = true;
					}
					if ($sign [0] == $this->getSignForm ( "c1" )) {
						$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
						if ($mymoney < $this->probability ["LotteryPrice"]) {
							$sender->sendMessage ( $this->getMessage ( "LackOfhMoney" ) );
							if ($event->getItem ()->isPlaceable ())
								$this->placeQueue [$sender->getName ()] = true;
							return;
						}
						$lotto_c = 0;
						if (isset ( $this->lotto [$sender->getName ()] )) {
							$get = $this->lotto [$sender->getName ()];
							$lotto_c = $get ["count"];
						}
						$this->lotto [$sender->getName ()] = array (
								"count" => ++ $lotto_c,
								"day" => date ( "d" ) 
						);
						
						$this->api->reduceMoney ( $sender, $this->getProbability ( "LotteryPrice", 0 ) );
						$sender->sendMessage ( $this->getMessage ( "LotteryPayment" ) . $this->lotto [$sender->getName ()] ["count"] );
						$sender->sendMessage ( $this->getMessage ( "LotteryPayment-a" ) );
						if ($event->getItem ()->isPlaceable ())
							$this->placeQueue [$sender->getName ()] = true;
					}
					if ($sign [0] == $this->getSignForm ( "d1" )) {
						$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
						if ($mymoney == 0) {
							$sender->sendMessage ( $this->getMessage ( "LackOfhMoney" ) );
							if ($event->getItem ()->isPlaceable ())
								$this->placeQueue [$sender->getName ()] = true;
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
						if ($event->getItem ()->isPlaceable ())
							$this->placeQueue [$sender->getName ()] = true;
					}
					unset ( $this->tap [$sender->getName ()] );
				}
			}
		} else if ($block->getID () == 323 or $block->getID () == 63 or $block->getID () == 68) {
			$sign = $event->getPlayer ()->getLevel ()->getTile ( $block );
			if ($sign instanceof Sign) {
				$sign = $sign->getText ();
				if ($sign [0] === $this->getSignForm ( "a1" ) or $sign [0] === $this->getSignForm ( "b1" ) or $sign [0] === $this->getSignForm ( "c1" ) or $sign [0] === $this->getSignForm ( "d1" )) {
					$this->tap [$sender->getName ()] = array (
							$block->x . ":" . $block->y . ":" . $block->z,
							time () 
					);
					$mymoney = EconomyAPI::getInstance ()->myMoney ( $sender );
					$sender->sendMessage ( $this->getMessage ( "AskAgain" ) . $mymoney . "]" );
					if ($event->getItem ()->isPlaceable ())
						$this->placeQueue [$sender->getName ()] = true;
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
				for($i = 1; $i <= $count; $i ++) {
					$rand = rand ( 1, $this->getProbability ( "LotteryProbability", 1 ) );
					if (! ($rand <= $this->getProbability ( "LotteryProbability", 0 )))
						-- $count;
				}
				if ($count == 0) {
					$player->sendMessage ( $this->getMessage ( "LotteryCheck-a" ) . " " . $count . $this->getMessage ( "LotteryCheck-b" ) );
					$player->sendMessage ( $this->getMessage ( "FailGamble" ) );
				} else {
					$this->api->addMoney ( $player, $count * $this->getProbability ( "LotteryCompensation", 0 ) );
					foreach ( $this->getServer ()->getOnlinePlayers () as $p ) {
						$p->sendMessage ( $player->getName () . $this->getMessage ( "SuccessLottery" ) );
						$p->sendMessage ( $this->getMessage ( "SuccessLottery-a" ) . $count . $this->getMessage ( "SuccessLottery-b" ) . $count * $this->getProbability ( "LotteryCompensation", 0 ) . $this->getMessage ( "SuccessLottery-c" ) );
					}
				}
				unset ( $this->lotto [$player->getName ()] );
			}
		}
	}
	public function removeItem($sender, $getitem) {
		$getcount = $getitem->getCount ();
		if ($getcount <= 0)
			return;
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
		$this->lotto = (new Config ( $this->getDataFolder () . "lotto.yml", Config::YAML ))->getAll ();
		$this->probability = (new Config ( $this->getDataFolder () . "probability.yml", Config::YAML, array (
				"GambleProbability" => "44/100",
				"ItemGambleProbability" => "44/100",
				"LotteryProbability" => "1/1000",
				"LotteryCompensation" => "100000",
				"LotteryPrice" => "50",
				"RandomGambleProbability" => "44/100" 
		) ))->getAll ();
		$this->language = (new Config ( $this->getDataFolder () . "language.yml", Config::YAML, array (
				"setlanguage" => "en",
				"en-mustnumberic" => "The second line (any amount) is must be numeric",
				"en-GambleSetupFinish" => "successful in setting gamble !",
				"en-ItemGambleSetupFinish" => "successful in setting item gamble !",
				"en-LotterySetupFinish" => "successful in setting lottery !",
				"en-RandomGambleSetupFinish" => "successful in setting random gamble !",
				"en-WrongFormat-Sign" => "The signs of the wrong type! No Gambling !",
				"en-LacenfhMoney" => "Money is not enough ! No Gambling ! Retention amount:0",
				"en-SuccessGamble" => "Gambling was successful ! (≥∀≤)/",
				"en-SuccessGamble-a" => "acquisition !",
				"en-FailGamble" => "Failed gamble ! (づ_ど)",
				"en-FailGamble-a" => "loss !",
				"en-noitem" => "Please Touch to have an item !",
				"en-SuccessitemGamble" => "Gambling was successful ! (≥∀≤)/ One more item acquisition !",
				"en-FailitemGamble" => "Failed gamble ! (づ_ど) One Item loss !",
				"en-LotteryPayment" => "Purchased a lottery ! (≥∀≤)/ Amount:",
				"en-LotteryPayment-a" => "Connect to the next day  will you know the result !",
				"en-AskAgain" => "Are you sure you want to gamble? To proceed please tap again. [Amount:",
				"en-LotteryCheck" => "Reports the results of last day lottery !",
				"en-LotteryCheck-a" => "lottery",
				"en-LotteryCheck-b" => " lottery failed ! (づ_ど)",
				"en-SuccessLottery" => "won the lottery!(≥∀≤)/",
				"en-SuccessLottery-a" => "Amount",
				"en-SuccessLottery-b" => " more lottery was successful",
				"en-SuccessLottery-c" => "Received !",
				"en-Gamblehow-a" => "When installed according to the form of signs",
				"en-Gamblehow-b" => "You can install Gamble signs",
				"en-Gamblehow-c" => "With the following command check the form",
				"en-Gamblehow-d" => "1.Please install the Sign",
				"en-Gamblehow-Gamble-a" => "2.Put in the first line 'gamble'",
				"en-Gamblehow-Gamble-b" => "3.Put in the first line 'price'",
				"en-Gamblehow-ItemGamble" => "2.Put in the first line 'itemgamble'",
				"en-Gamblehow-Lottery" => "2.Put in the first line [lottery]",
				"en-Gamblehow-Random" => "2.Put in the first line 'randomgamble'",
				
				"ko-mustnumberic" => "2번째줄(원하는금액)은 무조건 숫자여야합니다",
				"ko-GambleSetupFinish" => "도박기 세팅에 성공했습니다 !",
				"ko-ItemGambleSetupFinish" => "아이템도박기 세팅에 성공했습니다 !",
				"ko-LotterySetupFinish" => "로또기기 세팅에 성공했습니다 !",
				"ko-RandomGambleSetupFinish" => "랜덤도박기 세팅에 성공했습니다 !",
				"ko-WrongFormat-Sign" => "잘못된 형식의 표지판입니다! 도박불가 !",
				"ko-LackOfhMoney" => "돈이 부족합니다 ! 도박불가 ! 보유금액:0",
				"ko-SuccessGamble" => "도박에 성공했습니다 ! (≥∀≤)/",
				"ko-SuccessGamble-a" => "획득 !",
				"ko-FailGamble" => "도박에 실패했습니다! (づ_ど)",
				"ko-FailGamble-a" => "손실 !",
				"ko-noitem" => "원하는 아이템을 들고 터치해주세요 !",
				"ko-SuccessitemGamble" => "도박에 성공했습니다 ! (≥∀≤)/ 아이템 하나 더 획득 !",
				"ko-FailitemGamble" => "도박에 실패했습니다 ! (づ_ど) 아이템 하나 손실 !",
				"ko-LotteryPayment" => "로또를 구매했습니다 ! (≥∀≤)/ 총:",
				"ko-LotteryPayment-a" => "다음날에 접속하시면 당첨결과를 알려드립니다 !",
				"ko-AskAgain" => "도박을 하시겠습니까? 진행하려면 다시 탭해주세요. [보유금액:",
				"ko-LotteryCheck" => "지난일 로또 당첨결과를 확인합니다 !",
				"ko-LotteryCheck-a" => "로또",
				"ko-LotteryCheck-b" => "개 모두 꽝 ! (づ_ど)",
				"ko-SuccessLottery" => "님이 로또당첨!(≥∀≤)/",
				"ko-SuccessLottery-a" => "총",
				"ko-SuccessLottery-b" => "개의 로또가 당첨되서",
				"ko-SuccessLottery-c" => "달러를 받으셨습니다 !",
				"ko-Gamblehow-a" => "표지판을 양식에맞춰 설치하면",
				"ko-Gamblehow-b" => "겜블 표지판을 설치할 수 있습니다",
				"ko-Gamblehow-c" => "아래명령어로 양식확인가능",
				"ko-Gamblehow-d" => "1.표지판을 설치하세요",
				"ko-Gamblehow-Gamble-a" => "2.첫번째줄에 'gamble' 을 쓰세요",
				"ko-Gamblehow-Gamble-b" => "3.두번째줄에 '판돈' 을 쓰세요",
				"ko-Gamblehow-ItemGamble" => "2.첫번째줄에 'itemgamble' 을 쓰세요",
				"ko-Gamblehow-Lottery" => "2.첫번째줄에 'lottery' 를 쓰세요",
				"ko-Gamblehow-Random" => "2.첫번째줄에 'randomgamble 을 쓰세요",
				
				"jp-mustnumberic" => "2番目の列(望む価格)は無条件に数字でなければなりません",
				"jp-GambleSetupFinish" => "賭博機設置に成功しました !",
				"jp-ItemGambleSetupFinish" => "アイテム賭博機設置に成功しました !",
				"jp-LotterySetupFinish" => "宝くじ機器設置に成功しました !",
				"jp-RandomGambleSetupFinish" => "ランダム賭博機設置に成功しました !",
				"jp-WrongFormat-Sign" => "誤った形式の表示板です!賭博不可 !",
				"jp-LacjpfhMoney" => "お金が不足です ! 賭博不可 ! 保有金額:0",
				"jp-SuccessGamble" => "賭博に勝ちました ! (≥∀≤)/",
				"jp-SuccessGamble-a" => "ゲット !",
				"jp-FailGamble" => "賭博に負けました ! (づ_ど)",
				"jp-FailGamble-a" => "損失 !",
				"jp-noitem" => "アイテムを持ってタッチしてください !",
				"jp-SuccessitemGamble" => "賭博に勝ちました ! (≥∀≤)/ アイテムもう一つ獲得 !",
				"jp-FailitemGamble" => "賭博に負けました ! (づ_ど) アイテム一つ損失 !",
				"jp-LotteryPayment" => "宝くじを購買しました ! (≥∀≤)/ 本数:",
				"jp-LotteryPayment-a" => "当選の結果は翌日接続時にお知らせします !",
				"jp-AskAgain" => "賭博をしますか? 進めるためには再タッチしてください。[保有金額:",
				"jp-LotteryCheck" => "過ぎたことロットに当たった結果を確認します !",
				"jp-LotteryCheck-a" => "宝くじ",
				"jp-LotteryCheck-b" => "個すべて失敗 ! (づ_ど)",
				"jp-SuccessLottery" => "さんが宝くじに当たった!(≥∀≤)/",
				"jp-SuccessLottery-a" => "全部",
				"jp-SuccessLottery-b" => "つの宝くじが当たって、",
				"jp-SuccessLottery-c" => "ドルを受けました !",
				"jp-Gamblehow-a" => "表示板を様式に合わせてインストールすれば",
				"jp-Gamblehow-b" => "賭博機の表示板を設置することができます",
				"jp-Gamblehow-c" => "下記の命令語で様式確認可能",
				"jp-Gamblehow-d" => "1.の表示板を設置してください",
				"jp-Gamblehow-Gamble-a" => "2.最初の行に 'gamble'を書いてください",
				"jp-Gamblehow-Gamble-b" => "3.2列目に[賭け金の量]を書いてください",
				"jp-Gamblehow-ItemGamble" => "2.最初の行に 'itemgamble'を書いてください",
				"jp-Gamblehow-Lottery" => "2.最初の行に'lottery'を書いてください",
				"jp-Gamblehow-Random" => "2.最初の行に'randomgamble'を書いてください" 
		) ))->getAll ();
		$this->signform = (new Config ( $this->getDataFolder () . "signform.yml", Config::YAML, array (
				"en-a1" => "[gamble]",
				"en-a2" => "price :",
				"en-a3" => "When you touch,",
				"en-a4" => "Gain or loss by Price !",
				"en-b1" => "[itemgamble]",
				"en-b2" => "When you touch",
				"en-b3" => "a item, Gain or",
				"en-b4" => "loss the item !",
				"en-c1" => "[lottery]",
				"en-c2" => "price :",
				"en-c3" => "You can buy a lot !",
				"en-c4" => "prize:", // An item that you holding
				"en-d1" => "[randomgamble]",
				"en-d2" => "When you touch,",
				"en-d3" => "randomly from your",
				"en-d4" => "money Gain or loss !",
				
				"ko-a1" => "[도박]",
				"ko-a2" => "판돈 :",
				"ko-a3" => "터치시 해당금액만큼",
				"ko-a4" => "돈을 얻거나 잃음 !",
				"ko-b1" => "[아이템도박]",
				"ko-b2" => "터치시 해당아이템",
				"ko-b3" => "랜덤하게 1개획득",
				"ko-b4" => "하거나 1개잃음!",
				"ko-c1" => "[로또]",
				"ko-c2" => "한장가격:",
				"ko-c3" => "여러개 구매가능!",
				"ko-c4" => "당첨시",
				"ko-d1" => "[랜덤도박]",
				"ko-d2" => "표지판을 터치",
				"ko-d3" => "랜덤한 금액을",
				"ko-d4" => "얻거나 잃습니다 !",
				
				"jp-a1" => "[gamble]",
				"jp-a2" => "掛け金 :",
				"jp-a3" => "タッチの際は、該当金額ほど",
				"jp-a4" => "お金を得たり失っます !",
				"jp-b1" => "[itemgamble]",
				"jp-b2" => "タッチして当該アイテム",
				"jp-b3" => "ランダムに1個獲得",
				"jp-b4" => "したり、1個失う !",
				"jp-c1" => "[lottery]",
				"jp-c2" => "一枚の価格:",
				"jp-c3" => "いくつか購入可能!",
				"jp-c4" => "成功した時",
				"jp-d1" => "[randomgamble]",
				"jp-d2" => "表示板をタッチ",
				"jp-d3" => "ランダムな金額を",
				"jp-d4" => "得たり失っます !" 
		) ))->getAll ();
	}
}