<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\GiveMeTheMoney;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\tile\Sign;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use onebone\economyapi\EconomyAPI;
use pocketmine\event\block\SignChangeEvent;

class GiveMeTheMoney extends PluginBase implements Listener {
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->api = EconomyAPI::getInstance ();
		$this->listyml = new Config ( $this->getDataFolder () . "list.yml", Config::YAML );
		$this->list = $this->listyml->getAll ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->listyml->setAll ( $this->list );
		$this->listyml->save ();
	}
	public function onSignChange(SignChangeEvent $event) {
		$player = $event->getPlayer ();
		if ($event->getLine ( 0 ) == "givemoney") {
			if (! $player->isOp ()) {
				$player->sendMessage ( TextFormat::RED . "OP만 사용가능합니다" );
				return;
			}
			if (! is_numeric ( $event->getLine ( 1 ) )) {
				$player->sendMessage ( TextFormat::RED . "두번째는 반드시 숫자로해야합....설치Fail" );
				return;
			}
			$event->setLine ( 0, "[터치시 돈 획득]" );
			$event->setLine ( 1, "보상: " . "$" . $event->getLine ( 1 ) );
			$event->setLine ( 2, "주의 ! 하루에" );
			$event->setLine ( 3, "한번씩만 가능합니다 !" );
			$player->sendMessage ( TextFormat::DARK_AQUA . "세팅완료 ! ㅇㅁㅇ! " );
		}
	}
	public function playerBlockTouch(PlayerInteractEvent $event) {
		$sender = $event->getPlayer ();
		$block = $event->getBlock ();
		$item = $event->getItem ()->getID ();
		
		$xyz = $block->x . ":" . $block->y . ":" . $block->z;
		
		if (! ($event->getBlock ()->getID () == 323 or $event->getBlock ()->getID () == 63 or $event->getBlock ()->getID () == 68))
			return;
		$sign = $event->getPlayer ()->getLevel ()->getTile ( $block );
		if (! ($sign instanceof Sign))
			return;
		$sign = $sign->getText ();
		if (! ($sign [0] == "[터치시 돈 획득]"))
			return;
		
		if (isset ( $sign [1] )) {
			if (! isset ( $this->list [$sender->getName ()] [$xyz] ["time"] )) {
				$e = explode ( "$", $sign [1] );
				$money = $e [1];
				$this->api->addMoney ( $sender, $money );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "[안내] $" . $money . "를 획득하셨습니다 !" );
				$this->list [$sender->getName ()] [$xyz] ["time"] = date ( "d" );
				return;
			} else {
				if ($this->list [$sender->getName ()] [$xyz] ["time"] != date ( "d" )) {
					$e = explode ( "$", $sign [1] );
					$money = $e [1];
					$this->api->addMoney ( $sender, $money );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[안내] $" . $money . "를 획득하셨습니다 !" );
					$this->list [$sender->getName ()] [$xyz] ["time"] = date ( "d" );
					return;
				} else {
					$sender->sendMessage ( TextFormat::RED . "[안내] 보상은 하루 한번만 가능합니다 !" );
					return;
				}
			}
		} else {
			$sender->sendMessage ( TextFormat::RED . "[안내] 잘못된 표시판 ! 작동되지않습니다 !" );
			return;
		}
	}
}

?>