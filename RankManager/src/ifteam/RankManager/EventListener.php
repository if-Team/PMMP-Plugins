<?php

namespace ifteam\RankManager;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

class EventListener implements Listener {
	public $plugin;
	public function __construct(RankManager $plugin) {
		$this->plugin = $plugin;
		$plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->economyAPI = null;
		}
	}
	public function onPlayerJoinEvent(PlayerJoinEvent $event) {
		$this->plugin->users [strtolower ( $event->getPlayer ()->getName () )] = $this->plugin->readPrefixData ( $event->getPlayer () );
	}
	public function onPlayerQuitEvent(PlayerQuitEvent $event) {
		if (isset ( $this->plugin->users [strtolower ( $event->getPlayer ()->getName () )] ))
			unset ( $this->plugin->users [strtolower ( $event->getPlayer ()->getName () )] );
	}
	public function onPlayerKickEvent(PlayerKickEvent $event) {
		if (isset ( $this->plugin->users [strtolower ( $event->getPlayer ()->getName () )] ))
			unset ( $this->plugin->users [strtolower ( $event->getPlayer ()->getName () )] );
	}
	public function onSignChangeEvent(SignChangeEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "rankmanager.rankshop.create" ))
			return;
		switch ($event->getLine ( 0 )) {
			case $this->plugin->get ( "rankshop" ) :
				if ($event->getLine ( 1 ) == null or $event->getLine ( 2 ) === null or ! is_numeric ( $event->getLine ( 2 ) )) {
					$this->plugin->message ( $event->getPlayer (), $this->plugin->get ( "rankshop-help" ) );
					return false;
				}
				$requestedPrefix = $event->getLine ( 1 );
				$requestedPrice = $event->getLine ( 2 );
				
				$levelName = $event->getBlock ()->getLevel ()->getName ();
				$posString = "{$event->getBlock()->getX()}:{$event->getBlock()->getY()}:{$event->getBlock()->getZ()}";
				
				$this->plugin->db ["rankShop"] [$levelName] [$posString] = [ 
						"creator" => $event->getPlayer ()->getName (),
						"prefix" => $requestedPrefix,
						"price" => $requestedPrice 
				];
				
				$prefix = $this->plugin->applyDefaultPrefixFormat ( $requestedPrefix );
				
				$event->setLine ( 0, $this->plugin->get ( "rankshop-format1" ) );
				$event->setLine ( 1, str_replace ( "%prefix%", $prefix, $this->plugin->get ( "rankshop-format2" ) ) );
				$event->setLine ( 2, str_replace ( "%price%", $requestedPrice, $this->plugin->get ( "rankshop-format3" ) ) );
				
				$this->plugin->message ( $event->getPlayer (), $this->plugin->get ( "rankshop-created" ) );
				break;
		}
	}
	public function onPlayerInteractEvent(PlayerInteractEvent $event) {
		if (! $event->getBlock () instanceof Sign)
			return;
		
		$levelName = $event->getBlock ()->getLevel ()->getName ();
		$posString = "{$event->getBlock()->getX()}:{$event->getBlock()->getY()}:{$event->getBlock()->getZ()}";
		
		if (! isset ( $this->plugin->db ["rankShop"] [$levelName] [$posString] ))
			return;
		
		$event->setCancelled ();
		$rankShop = $this->plugin->db ["rankShop"] [$levelName] [$posString];
		
		if (! $event->getPlayer ()->hasPermission ( "rankmanager.rankshop.use" )) {
			$this->plugin->alert ( $event->getPlayer (), $this->plugin->get ( "rankshop-you-cant-buy-rank" ) );
			return;
		}
		
		if ($this->economyAPI !== null) {
			$myMoney = $this->economyAPI->myMoney ( $event->getPlayer () );
			if ($rankShop ["price"] > $myMoney) {
				$this->plugin->message ( $event->getPlayer (), $this->plugin->get ( "rankshop-not-enough-money" ) );
				return;
			} else {
				$this->economyAPI->reduceMoney ( $event->getPlayer (), $rankShop ["price"] );
				$this->plugin->addPrefixData ( $event->getPlayer (), $rankShop ["prefix"] );
				$this->plugin->setNowPrefix ( $event->getPlayer (), $rankShop ["prefix"] );
				$this->plugin->message ( $player, $this->plugin->get ( "prefix-buy-success" ) );
			}
		} else {
			$this->plugin->alert ( $event->getPlayer (), $this->plugin->get ( "there-are-no-economyapi" ) );
			return;
		}
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! $player->hasPermission ( "rankmanager.rank.manage" ))
			return false;
		if(! is_array($args) or ! isset($args[0])){
			
		}
		// 칭호 추가 <유저명> <칭호명> - 해당유저에게 칭호를 줍니다
		// 칭호 삭제 <유저명> <칭호명> - 해당유저에게서 해당 칭호를 삭제합니다.
		// 칭호 확인 <유저명> - 해당유저의 칭호를 확인합니다.
	}
}
?>