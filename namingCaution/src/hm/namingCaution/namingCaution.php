<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\namingCaution;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Player;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\utils\Config;
use pocketmine\network\protocol\ChatPacket;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class namingCaution extends PluginBase implements Listener {
	public $chatpk;
	public $listyml, $list;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->chatpk = new ChatPacket ();
		$this->listyml = new Config ( $this->getDataFolder () . "list.yml", Config::YAML, array (
				"message" => "[CAUTION] You can't use this nickname\nKick processed automatically\nWhen the nickname changes, You can normally use",
				"names" => array () 
		) );
		$this->list = $this->listyml->getAll ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $params) {
		if ($command->getName () == "nc") {
			if (! isset ( $params [0] )) {
				$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc add" . TextFormat::WHITE . " nickname" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc del" . TextFormat::WHITE . " nickname" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc clear" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc list" );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
				return true;
			}
			switch ($params [0]) {
				case "add" :
					if (isset ( $params [1] )) {
						$key = array_search ( $params [1], $this->list ["names"] );
						if ($key != false) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . $params [1] . " name already has been banned.." );
							break;
						}
						$this->list ["names"] [] = $params [1];
						$sender->sendMessage ( TextFormat::DARK_AQUA . $params [1] . "  name has been banned." );
					} else {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc add" . TextFormat::WHITE . " nickname" );
					}
					break;
				case "del" :
					if (isset ( $params [1] )) {
						$key = array_search ( $params [1], $this->list ["names"] );
						if ($key != false) {
							unset ( $this->list ["names"] [$key] );
							$sender->sendMessage ( TextFormat::DARK_AQUA . $params [1] . " name has been pardoned." );
						}
					} else {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "is not exist ban list." );
					}
					break;
				case "clear" :
					$this->list ["names"] = [ ];
					$sender->sendMessage ( TextFormat::DARK_AQUA . "All names has been pardoned." );
					break;
				case "list" :
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[namingCaution] Preview Name Ban List." );
					$list = TextFormat::DARK_AQUA;
					foreach ( $this->list ["names"] as $l )
						$list .= $l . " ";
					$sender->sendMessage ( $list );
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc add" . TextFormat::WHITE . " nickname" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc del" . TextFormat::WHITE . " nickname" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc clear" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/nc list" );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "*-------------------*" );
					break;
			}
			return true;
		}
	}
	public function onDisable() {
		$this->listyml->setAll ( $this->list );
		$this->listyml->save ();
	} // stripos($message, $m)
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		foreach ( $this->list ["names"] as $l ) {
			if (stripos ( $player->getName (), $l ) !== false) {
				$event->setJoinMessage ( "" );
				$this->chatpk->message = $this->list ["message"];
				$player->dataPacket ( $this->chatpk );
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"Kick" 
				], [ 
						$player 
				] ), 120 );
				break;
			}
		}
	}
	public function onKick(PlayerKickEvent $event) {
		$player = $event->getPlayer ();
		foreach ( $this->list ["names"] as $l ) {
			if (stripos ( $player->getName (), $l ) !== false) {
				$event->setQuitMessage ( "" );
				break;
			}
		}
	}
	public function Kick(Player $player) {
		$player->kick ( "has been banned nickname" );
	}
}

?>