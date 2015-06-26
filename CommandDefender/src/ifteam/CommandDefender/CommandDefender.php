<?php

namespace ifteam\CommandDefender;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use ifteam\CommandDefender\task\RemoveQueueTask;
use ifteam\CommandDefender\task\PlayerChatEventTask;
use ifteam\CommandDefender\task\PlayerCommandPreprocessEventTask;

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
	public function onChat(PlayerChatEvent $event) {
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new PlayerChatEventTask ( $this, $event ), 2 );
	}
	public function onPreCommand(PlayerCommandPreprocessEvent $event) {
		if (\substr ( $event->getMessage(), 0, 1 ) != "/") return;
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new PlayerCommandPreprocessEventTask ( $this, $event ), 2 );
	}
	public function chatCheck($event) {
		if (! $event instanceof PlayerChatEvent)
			return;
		if ($event->isCancelled ())
			return;
		if($event->getPlayer()->closed) return;
		if (! isset ( $this->queue [$event->getPlayer ()->getAddress ()] ))
			$this->queue [$event->getPlayer ()->getAddress ()] = 1;
		$this->queue [$event->getPlayer ()->getAddress ()] ++;
		
		if ($this->queue [$event->getPlayer ()->getAddress ()] >= 4) {
			$event->getPlayer ()->kick ( "채팅도배" );
			$this->getServer ()->blockAddress ( $event->getPlayer ()->getAddress (), 20 );
		}
	}
	public function commandCheck($event) {
		if (! $event instanceof PlayerCommandPreprocessEvent)
			return;
		if ($event->isCancelled ())
			return;
		if($event->getPlayer()->closed) return;
		if (! isset ( $this->queue [$event->getPlayer ()->getAddress ()] ))
			$this->queue [$event->getPlayer ()->getAddress ()] = 1;
		$this->queue [$event->getPlayer ()->getAddress ()] ++;
		
		if ($this->queue [$event->getPlayer ()->getAddress ()] >= 5) { // 2초에 5번명령어 사용시 20초간 킥
			$event->getPlayer ()->kick ( "명령어도배" );
			$this->getServer ()->blockAddress ( $event->getPlayer ()->getAddress (), 20 );
		}
	}
}

?>