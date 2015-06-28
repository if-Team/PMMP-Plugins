<?php

namespace ifteam\EmailAuth\api;

use pocketmine\event\Listener;
use onebone\economyapi\event\money\MoneyChangedEvent;
use ifteam\EmailAuth\EmailAuth;

class API_EconomyAPIListner implements Listener {
	public $owner; /* API_CustomPacketListner */
	public $plugin; /* EmailAuth */
	public function __construct(API_CustomPacketListner $owner, EmailAuth $plugin) {
		$this->owner = $owner;
		$this->plugin = $plugin;
		
		if ($this->plugin->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) !== null) {
			$this->plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $this->plugin );
		}
	}
	public function onMoneyChangeEvent(MoneyChangedEvent $event) {
		if ($event->isCancelled ())
			return;
		$this->owner->onMoneyChangeEvent ( $event->getUsername (), $event->getMoney () );
	}
}

?>