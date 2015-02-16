<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\OverPower;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;

class OverPower extends PluginBase implements Listener {
	public $logstxt, $logs;
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->logstxt = new Config ( $this->getDataFolder () . "op_command_log.txt", Config::YAML, array (
				"logs" => array () 
		) );
		$this->logs = $this->logstxt->getAll ();
		$this->loginlogstxt = new Config ( $this->getDataFolder () . "op_login_log.txt", Config::YAML );
		$this->loginlogs = $this->loginlogstxt->getAll ();
	}
	public function onJoin(PlayerLoginEvent $event) {
		$player = $event->getPlayer ();
		if ($player->isOp ()) {
			if (! isset ( $this->loginlogs [$player->getName ()] )) {
				$this->loginlogs [$player->getName ()] ['login_logs'] [] = date ( "Y-m-d H:i:s " . "접속" );
			} else {
				$this->loginlogs [$player->getName ()] ['login_logs'] [] .= date ( "Y-m-d H:i:s " . "접속" );
			}
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		if ($player->isOp ()) {
			if (! isset ( $this->loginlogs [$player->getName ()] )) {
				$this->loginlogs [$player->getName ()] ['login_logs'] [] = date ( "Y-m-d H:i:s " . "종료" );
			} else {
				$this->loginlogs [$player->getName ()] ['login_logs'] [] .= date ( "Y-m-d H:i:s " . "종료" );
			}
		}
	}
	public function onDisable() {
		$this->logstxt->setAll ( $this->logs );
		$this->logstxt->save ();
		$this->loginlogstxt->setAll ( $this->loginlogs );
		$this->loginlogstxt->save ();
	}
	public function UserCommand(PlayerCommandPreprocessEvent $event) {
		$command = $event->getMessage ();
		$player = $event->getPlayer ();
		if (! $event->isCancelled ()) {
			if ($player->isOp ()) {
				$this->logs ["logs"] [] .= date ( "Y-m-d H:i:s " ) . "[" . $player->getName () . "] " . $command;
				if ($command == '/stop') {
					$player->sendMessage ( TextFormat::RED . "해당 명령어는 어드민만 사용가능합니다" );
					$event->setCancelled ();
				}
			}
		}
	}
}
?>