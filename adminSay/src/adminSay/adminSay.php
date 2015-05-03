<?php

namespace adminSay;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\utils\ServerException;
use pocketmine\Player;

class adminSay extends PluginBase implements Listener {
	public $config, $configData;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->config = new Config ( $this->getDataFolder () . "settings.yml", Config::YAML, [ 
				"default-prefix" => "[ 서버 ]" 
		] );
		$this->configData = $this->config->getAll ();
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () ) != "adminsay")
			return;
		if ($player->hasPermission ( "adminSay" )) {
			$this->setAdminSay ( $player );
		}
	}
	public function setAdminSay(CommandSender $player) {
		if (! isset ( $this->configData [$player->getName ()] )) {
			$this->configData [$player->getName ()] = 1;
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->configData ["default-prefix"] . " adminSay가 설정 되었습니다." );
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->configData ["default-prefix"] . " ( 한번 더 입력시 해제됩니다. )" );
		} else {
			unset ( $this->configData [$player->getName ()] );
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->configData ["default-prefix"] . " adminSay 설정이 해제되었습니다." );
		}
	}
	public function onChat(PlayerChatEvent $event) {
		if (! isset ( $this->configData [$event->getPlayer ()->getName ()] ))
			return;
		$this->getServer ()->broadcastMessage ( TextFormat::LIGHT_PURPLE . $this->configData ["default-prefix"] . " " . $event->getMessage () );
		$event->setCancelled ();
	}
	public function onConsole(ServerCommandEvent $event) {
		if (! ($event->getSender () instanceof CommandSender)) {
			throw new ServerException ( "CommandSender is not valid" );
		}
		if (! $event->getSender () instanceof Player)
			$event->setCancelled ();
		if (! $this->getServer ()->getCommandMap ()->dispatch ( $event->getSender (), $event->getCommand () )) {
			$this->getServer ()->broadcastMessage ( TextFormat::LIGHT_PURPLE . $this->configData ["default-prefix"] . " " . $event->getCommand () );
		}
	}
}

?>