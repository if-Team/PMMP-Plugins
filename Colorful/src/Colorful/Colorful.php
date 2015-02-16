<?php

namespace ColorFul;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

class ColorFul extends PluginBase implements Listener {
	public function onEnable() {
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
	public function helpColor(Player $player) {
		$player->sendMessage ( TextFormat::DARK_AQUA . "*사용가능한 색상표를 출력합니다" );
		$player->sendMessage ( "§f&0:검정 §f&1:§1짙은파랑 §f&2:§2짙은초록 §f&3:§3짙은청록");
		$player->sendMessage ( "§f&4:§4짙은빨강 §f&5:§5짙은보라§f&6:§6금색 §f&7:§7회색");
		$player->sendMessage ( "§f&8:§8검은회색 §f&9:§9파랑 §f&a:§a초록 §f&b:§b청록");
		$player->sendMessage ( "§f&c:§c빨강 §f&d:§d보라 §f&e:§e노랑 §f&f:§f흰색\n\n" );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		$this->helpColor ( $player );
		return true;
	}
}

?>