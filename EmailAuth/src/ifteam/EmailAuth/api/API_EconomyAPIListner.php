<?php

namespace ifteam\EmailAuth\api;

use pocketmine\event\Listener;
use onebone\economyapi\event\money\MoneyChangedEvent;
use ifteam\EmailAuth\EmailAuth;
use onebone\economyapi\EconomyAPI;

class API_EconomyAPIListner implements Listener {
	public $owner; /* API_CustomPacketListner */
	public $plugin; /* EmailAuth */
	public $isEnabled; /* Is Enabled */
	public $temp;
	public function __construct(API_CustomPacketListner $owner, EmailAuth $plugin) {
		$this->owner = $owner;
		$this->plugin = $plugin;
		
		if ($this->plugin->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) !== null) {
			$this->plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $this->plugin );
			$this->isEnabled = true;
		} else {
			$this->isEnabled = false;
		}
	}
	public function onMoneyChangeEvent(MoneyChangedEvent $event) {
		if ($event->isCancelled ())
			return;
		if (isset ( $this->temp [$event->getUsername () . " " . $event->getMoney ()] )) {
			unset ( $this->temp [$event->getUsername () . " " . $event->getMoney ()] );
			return;
		}
		$this->owner->onMoneyChangeEvent ( $event->getUsername (), $event->getMoney () );
	}
	public function setMoney($username, $money) {
		if (! $this->isEnabled)
			return;
		EconomyAPI::getInstance ()->setMoney ( $username, $money );
		$this->temp [$username . " " . $money] = true;
	}
}

?>