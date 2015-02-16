<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\automaticDoor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\CallbackTask;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\network\protocol\LevelEventPacket;
use pocketmine\Server;
use pocketmine\block\Block;

class automaticDoor extends PluginBase implements Listener {
	public $opendoor = [ ];
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"automaticDoor" 
		] ), 10 );
	}
	public function automaticDoor() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $p ) {
			for($i1 = - 1; $i1 <= 1; $i1 ++)
				for($b1 = - 1; $b1 <= 1; $b1 ++) {
					$pos = new Vector3 ( $p->x + $i1, $p->y, $p->z + $b1 );
					$block = $this->getServer ()->getDefaultLevel ()->getBlock ( $pos );
					if ($block->getID () == Item::DOOR_BLOCK) {
						if (! isset ( $this->opendoor [$block->x . "." . $block->y . "." . $block->z] )) {
							$meta = $block->getDamage ();
							if (($meta & 0x08) === 0x08) {
								$meta = $block->getDamage () ^ 0x04;
							} else {
								$meta ^= 0x04;
							}
							$this->getServer ()->getDefaultLevel ()->setBlock ( $block, Block::get ( Item::DOOR_BLOCK, $meta ), \true );
							$this->doorSound ( $block );
							$this->opendoor [$block->x . "." . $block->y . "." . $block->z] ['door'] = $block;
							$this->opendoor [$block->x . "." . $block->y . "." . $block->z] ['player'] = $p;
						}
					}
				}
		}
		$poslist = array_keys ( $this->opendoor );
		foreach ( $poslist as $p ) {
			$mx = abs ( $this->opendoor [$p] ['door']->x - $this->opendoor [$p] ['player']->x );
			$mz = abs ( $this->opendoor [$p] ['door']->z - $this->opendoor [$p] ['player']->z );
			if ($mx >= 2 or $mz >= 2) {
				$block = $this->getServer ()->getDefaultLevel ()->getBlock ( $this->opendoor [$p] ['door'] );
				$meta = $block->getDamage ();
				if (($meta & 0x08) === 0x08) {
					$meta = $block->getDamage () ^ 0x04;
				} else {
					$meta ^= 0x04;
				}
				$this->getServer ()->getDefaultLevel ()->setBlock ( $this->opendoor [$p] ['door'], Block::get ( Item::DOOR_BLOCK, $meta ), \true );
				$this->doorSound ( $this->opendoor [$p] ['door'] );
				unset ( $this->opendoor [$p] );
			}
		}
	}
	public function doorSound(Vector3 $pos) {
		$pk = new LevelEventPacket ();
		$pk->x = $pos->x;
		$pk->y = $pos->y;
		$pk->z = $pos->z;
		$pk->evid = 1003;
		$pk->data = 0;
		Server::broadcastPacket ( $this->getServer ()->getOnlinePlayers (), $pk );
	}
}
?>