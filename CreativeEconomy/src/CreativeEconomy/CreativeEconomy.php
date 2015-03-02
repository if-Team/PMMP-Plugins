<?php

namespace CreativeEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\block\Block;

class CreativeEconomy extends PluginBase implements Listener {
	private static $instance = null;
	public $messages, $db, $marketCount;
	public $economyAPI = null;
	public $purchaseQueue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		
		$this->saveResource ( "marketPrice.yml", false );
		$this->saveResource ( "marketCount.yml", false );
		$this->saveResource ( "en_item_data.yml", false );
		if ($this->messages ["default-language"] != "en") $this->saveResource ( $this->messages ["default-language"] . "_item_data.yml", false );
		
		$this->saveDefaultConfig ();
		$this->reloadConfig ();
		
		$this->db = (new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML ))->getAll ();
		$this->marketCount = (new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML ))->getAll ();
		
		if ($this->checkEconomyAPI ()) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->registerCommand ( $this->get ( "commands-buy" ), "ce", "creativeeconomy.commands.buy", $this->get ( "commands-buy-usage" ) );
		$this->registerCommand ( $this->get ( "commands-sell" ), "ce", "creativeeconomy.commands.sell", $this->get ( "commands-sell-usage" ) );
		$this->registerCommand ( $this->get ( "commands-ce" ), "ce", "creativeeconomy.commands.ce", $this->get ( "commands-ce-usage" ) );
		
		if (self::$instance == null) self::$instance = $this;
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
		
		$save = new Config ( $this->getDataFolder () . "marketCount.yml", Config::YAML );
		$save->setAll ( $this->marketCount );
		$save->save ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onTouch(PlayerInteractEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] )) {
			if ($event->getPlayer ()->hasPermission ( "creativeeconomy.shop.use" )) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			$this->purchaseQueue [$event->getPlayer ()->getName ()] ["id"] = $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"];
			$this->message ( $event->getPlayer (), $this->get ( "you-can-buy-or-sell" ) );
			$this->message ( $event->getPlayer (), $this->get ( "buy-or-sell-help-command" ) );
			$event->setCancelled ();
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$block = $event->getBlock ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] )) {
			$player = $event->getPlayer ();
			if (! $player->hasPermission ( "creativeeconomy.shop.break" )) {
				$this->alert ( $player, $this->get ( "ko-market-cannot-break" ) );
				$event->setCancelled ();
			}
			unset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] );
			$this->message ( $player, "ko-market-completely-destroyed" );
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, $args) {
		if (! $sender instanceof Player) {
			$this->alert ( $player, $this->get ( "only-in-game" ) );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-buy" ) :
				(isset ( $args [0] )) ? $this->CEBuyCommand ( $player, $args [0] ) : $this->CEBuyCommand ( $player );
				break;
			case $this->get ( "commands-sell" ) :
				(isset ( $args [0] )) ? $this->CESellCommand ( $player, $args [0] ) : $this->CESellCommand ( $player );
				break;
			case $this->get ( "commands-ce" ) :
				if (! isset ( $args [0] )) {
					$this->get ( "ko-commands-ce-help1" );
					$this->get ( "ko-commands-ce-help2" );
					$this->get ( "ko-commands-ce-help3" );
					$this->get ( "ko-commands-ce-help4" );
					break;
				}
				switch ($args [0]) {
					case $this->get ( "sub-commands-create" ) :
						(isset ( $args [1] )) ? $this->CreativeEconomy ( $player, $args [1] ) : $this->CreativeEconomy ( $player );
						break;
					case $this->get ( "sub-commands-autocreate" ) :
						$this->CEAutoSet ( $player );
						break;
					case $this->get ( "sub-commands-change" ) :
						(isset ( $args [1] )) ? $this->ChangeMarketPrice ( $player, $args [1] ) : $this->ChangeMarketPrice ( $player );
						break;
					case $this->get ( "sub-commands-lock" ) :
						$this->AllFreezeMarket ( $player );
						break;
					default :
						$this->get ( "ko-commands-ce-help1" );
						$this->get ( "ko-commands-ce-help2" );
						$this->get ( "ko-commands-ce-help3" );
						$this->get ( "ko-commands-ce-help4" );
						break;
				}
				break;
		}
		return true;
	}
	public function CEBuyCommand(Player $player, $count = 1) {
		if (! isset ( $this->purchaseQueue [$player->getName ()] )) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		} else {
			// TODO 아이템에 따라 구매처리
			return;
		}
	}
	public function CESellCommand(Player $player, $count = 1) {
		if (! isset ( $this->purchaseQueue [$player->getName ()] )) {
			$this->message ( $player, $this->get ( "please-choose-item" ) );
			return;
		} else {
			// TODO 아이템에 따라 판매처리
			return;
		}
	}
	public function CreativeEconomy(Player $player, $item = null) {
		// TODO 터치한 위치에 자동 쇼케이스 작업기능
	}
	public function CEAutoSet() {
		// TODO 1줄 전자동 상점설치
	}
	public function ChangeMarketPrice(Player $player, $item = null) {
		// TODO 기본가격시세를 입력된 값으로 설정
	}
	public function AllFreezeMarket() {
		// TODO 상점매매를 모두 허용/차단
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function checkEconomyAPI() {
		return (($this->getServer ()->getLoader ()->findClass ( 'onebone\\economyapi\\EconomyAPI' )) == null) ? false : true;
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function message(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>