<?php

namespace PMSocket;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\DiamondAxe;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;

class PMSocket extends PluginBase implements Listener {
	public $db;
	public $attachment = null, $resender = null;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) === null) {
			$this->getServer ()->getLogger ()->critical ( "[PMSocket] CustomPacket plugin was not found. This plugin will be disabled." );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return;
		}
		$this->getLogger ()->info ( TextFormat::GOLD . "[PMSocket] Enabled" );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		if (isset ( $this->db ["pass"] )) {
			$this->registerAttachment ();
		} else {
			$this->getLogger ()->info ( TextFormat::GOLD . "[PMSocket] Please register your communicate password" );
			$this->getLogger ()->info ( TextFormat::GOLD . "[PMSocket] /pmsocket setpass <password>" );
		}
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public function registerAttachment() {
		$this->resender = new PMResender ( $this->db ["pass"] );
		$this->attachment = new PMAttachment ( $this->resender );
		$this->getServer ()->getLogger ()->addAttachment ( $this->attachment );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this->resender, $this );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! isset ( $args [0] )) {
			$player->sendMessage ( TextFormat::GOLD . "/pmsocket setpass <password>" );
			return true;
		}
		switch (strtolower ( $args [0] )) {
			case "setpass" :
				if (! isset ( $args [1] )) {
					$player->sendMessage ( TextFormat::GOLD . "[PMSocket] /pmsocket setpass <password>" );
					return true;
				}
				array_shift ( $args );
				$this->db ["pass"] = implode ( " ", $args );
				$player->sendMessage ( TextFormat::GOLD . "[PMSocket] The password has been registered!" );
				if ($this->resender == null) $this->registerAttachment ();
				break;
		}
		return true;
	}
}