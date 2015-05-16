<?php

namespace PMSocket;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\DiamondAxe;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\TextFormat;

class PMSocket extends PluginBase implements Listener {
	public $attachment, $resender;
	public function onEnable() {
		$this->resender = new PMResender ();
		$this->attachment = new PMAttachment ( $this->resender );
		$this->getServer ()->getLogger ()->addAttachment ( $this->attachment );
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) === null) {
			$this->getServer ()->getLogger ()->critical ( "[CustomPacket Example] CustomPacket plugin was not found. This plugin will be disabled." );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return;
		}
		$this->getServer ()->getPluginManager ()->registerEvents ( $this->resender, $this );
		$this->getLogger ()->info ( TextFormat::GOLD . "[PMSocket] Enabled" );
	}
}