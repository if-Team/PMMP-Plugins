<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\illuminate;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\item\Item;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Player;
use pocketmine\block\Block;

class illuminate extends PluginBase implements Listener {
	public $pk;
	public function onEnable() {
		$this->pk = new UpdateBlockPacket ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"illuminate_schedule" 
		] ), 40 );
	}
	public function UserJoin(PlayerJoinEvent $event) {
		$this->illuminate ( $event->getPlayer () );
	}
	public function illuminate_schedule() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if ($player == null)
				return;
			$this->illuminate ( $player );
		}
	}
	public function illuminate(Player $player) {
		if ($player == null)
			return;
		
		if ($player->getLevel ()->getBlockIdAt ( $player->x, $player->y + 1, $player->z ) == Block::AIR) {
			$this->pk->x = $player->x;
			$this->pk->y = $player->y + 1;
			$this->pk->z = $player->z;
			$this->pk->block = Item::TORCH;
			$this->pk->meta = 0;
			$player->dataPacket ( $this->pk );
			$this->pk->block = Item::AIR;
			$player->dataPacket ( $this->pk );
		}
	}
}
?>