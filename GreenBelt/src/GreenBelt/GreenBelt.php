<?php

namespace GreenBelt;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\block\Block;
use pocketmine\utils\Random;
use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\level\ChunkManager;
use pocketmine\block\Sapling;
use pocketmine\math\Vector3;
use pocketmine\scheduler\CallbackTask;
use pocketmine\level\generator\object\Tree;

class GreenBelt extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->pk = new UpdateBlockPacket ();
	}
	public function onSapling(BlockUpdateEvent $event) {
		if ($event->isCancelled ()) return;
		if ($event->getBlock () instanceof Sapling) {
			Tree::growTree ( $event->getBlock ()->getLevel (), $event->getBlock ()->x, $event->getBlock ()->y, $event->getBlock ()->z, new Random (\mt_rand () ), $event->getBlock ()->getDamage () & 0x07 );
		}
	}
}
?>