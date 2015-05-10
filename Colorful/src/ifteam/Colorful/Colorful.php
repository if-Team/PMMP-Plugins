<?php

namespace ifteam\ColorFul;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\command\PluginCommand;
use pocketmine\utils\Config;

class ColorFul extends PluginBase implements Listener {
	public $m_version = 1;
	public $messages;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->registerCommand ( $this->get ( "colorTable" ), "colorful", $this->get ( "colorTable-desc" ), "/" . $this->get ( "colorTable" ) );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onChat(PlayerChatEvent $event) {
		$event->setFormat ( $this->replaceColor ( $event->getFormat () ) );
		$event->setMessage ( $this->replaceColor ( $event->getMessage () ) );
	}
	public function onSign(SignChangeEvent $event) {
		$event->setLine ( 0, $this->replaceColor ( $event->getLine ( 0 ) ) );
		$event->setLine ( 1, $this->replaceColor ( $event->getLine ( 1 ) ) );
		$event->setLine ( 2, $this->replaceColor ( $event->getLine ( 2 ) ) );
		$event->setLine ( 3, $this->replaceColor ( $event->getLine ( 3 ) ) );
	}
	public function onJoin(PlayerJoinEvent $event) {
		$event->getPlayer ()->setRemoveFormat ( false );
	}
	public function replaceColor($text) {
		for($i = 0; $i <= 9; $i ++)
			$text = str_replace ( "&" . $i, "§" . $i, $text );
		for($i = 'a'; $i <= 'f'; $i ++)
			$text = str_replace ( "&" . $i, "§" . $i, $text );
		return $text;
	}
	public function helpColor(CommandSender $player) {
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "color-print" ) );
		$player->sendMessage ( $this->get ( "colorlist-1" ) );
		$player->sendMessage ( $this->get ( "colorlist-2" ) );
		$player->sendMessage ( $this->get ( "colorlist-3" ) );
		$player->sendMessage ( $this->get ( "colorlist-4" ) );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		$this->helpColor ( $player );
		return true;
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
}

?>