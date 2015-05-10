<?php

namespace ifteam\CommandDefender;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class CommandDefender extends PluginBase implements Listener {
	public $queue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new RemoveQueueTask ( $this ), 40 );
	}
	public function removeQueue() { // 2초마다 큐제거
		foreach ( $this->queue as $index => $data )
			unset ( $this->queue [$index] );
	}
	public function CommandDefender(PlayerCommandPreprocessEvent $event) {
		if (! isset ( $this->queue [$event->getPlayer ()->getAddress ()] )) $this->queue [$event->getPlayer ()->getAddress ()] = 1;
		$this->queue [$event->getPlayer ()->getAddress ()] ++;
		
		if ($this->queue [$event->getPlayer ()->getAddress ()] >= 5) { // 2초에 5번명령어 사용시 20초간 킥
			$this->getServer ()->blockAddress ( $event->getPlayer ()->getAddress (), 20 );
		}
	}
}

?>