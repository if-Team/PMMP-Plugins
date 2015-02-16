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
	public $messages, $db;
	public $economyAPI = null;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->saveResource ( "config.yml", false );
		$this->saveResource ( "marketPrice.yml", false );
		$this->initMessage ();
		
		$this->saveResource ( "en_item_data.yml", false );
		if ($this->messages ["default-language"] != "en") $this->saveResource ( $this->messages ["default-language"] . "_item_data.yml", false );
		
		$this->saveDefaultConfig ();
		$this->reloadConfig ();
		
		$this->db = (new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML ))->getAll ();
		
		if ($this->checkEconomyAPI ()) {
			$this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( $this->get ( "there-are-no-economyapi" ) );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->registerCommand ( $this->get ( "commands-buy" ), "ce", "creativeeconomy.commands.buy", $this->get ( "commands-buy-usage" ) );
		$this->registerCommand ( $this->get ( "commands-sell" ), "ce", "creativeeconomy.commands.sell", $this->get ( "commands-sell-usage" ) );
		$this->registerCommand ( $this->get ( "commands-itemlist" ), "ce", "creativeeconomy.commands.buylist", $this->get ( "commands-itemlist-usage" ) );
		$this->registerCommand ( $this->get ( "commands-ce" ), "ce", "creativeeconomy.commands.ce", $this->get ( "commands-ce-usage" ) );
		
		if (self::$instance == null) self::$instance = $this;
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "marketDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onTouch(PlayerInteractEvent $event) {
		if ($event->getBlock ()->getId () != Block::SIGN_POST and $event->getBlock ()->getId () != Block::WALL_SIGN)
			;
		$block = $event->getBlock ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] )) {
			if ($event->getPlayer ()->hasPermission ( "creativeeconomy.shop.use" )) {
				$this->alert ( $player, $this->get ( "ur-not-use-market" ) );
				return;
			}
			// TODO 가격-타입-아이디값 받아서 구매/판매 처리
			$event->setCancelled ();
		}
	}
	public function onSign(SignChangeEvent $event) {
		if ($event->getLine ( 0 ) == $this->get ( "set-sign-buy" )) {
			if ($event->getLine ( 1 ) == null or $event->getLine ( 2 ) == null) {
				$this->alert ( $player, $this->get ( "ko-set-sign-help1" ) );
				$this->alert ( $player, $this->get ( "ko-set-sign-help2-buy" ) );
				$this->alert ( $player, $this->get ( "ko-set-sign-help3" ) );
				$this->alert ( $player, $this->get ( "ko-set-sign-help4" ) );
				return;
			}
			// TODO 잘못된 아이템 코드설정일 경우에 예외
			// TODO 지원하지안는 아이템일경우 예외 (가격이 0일때나 아예선언이 없을때)
			// TODO 가격-> 숫자가 아니면 예외
			// TODO DB에 저장 [signMarket][위치][price]= 가격
			// TODO DB에 저장 [signMarket][위치][id]= 아이디:값
			// TODO DB에 저장 [signMarket][위치][type]= "buy"
			// TODO 울타리 양식에 맞게 setLine 처리
			// TODO 성공적으로 설정했다는 알림출력
		} else if ($event->getLine ( 0 ) == $this->get ( "set-sign-sell" )) {
			if ($event->getLine ( 1 ) == null or $event->getLine ( 2 ) == null) {
				$this->alert ( $player, $this->get ( "ko-set-sign-help1" ) );
				$this->alert ( $player, $this->get ( "ko-set-sign-help2-sell" ) );
				$this->alert ( $player, $this->get ( "ko-set-sign-help3" ) );
				$this->alert ( $player, $this->get ( "ko-set-sign-help4" ) );
				return;
			}
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		if ($event->getBlock ()->getId () != Block::SIGN_POST and $event->getBlock ()->getId () != Block::WALL_SIGN)
			;
		if ($event->getPlayer ()->hasPermission ( "creativeeconomy.shop.break" )) return;
		$block = $event->getBlock ();
		if (isset ( $this->db ["signMarket"] ["{$block->x}:{$block->y}:{$block->z}"] )) {
			$this->alert ( $player, $this->get ( "ko-market-cannot-break" ) );
			$event->setCancelled ();
		}
		// TODO 별도 세팅이 추가될경우 확인 후 파괴방지
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
			case $this->get ( "commands-itemlist" ) :
				(isset ( $args [0] )) ? $this->CECanBuyListCommand ( $player, $args [0] ) : $this->CECanBuyListCommand ( $player );
				break;
			case $this->get ( "commands-makeholo" ) :
				(isset ( $args [0] )) ? $this->CEMakeHologramCommand ( $player, $args [0] ) : $this->CEMakeHologramCommand ( $player );
				break;
			case $this->get ( "commands-ce" ) :
				switch ($args [0]) {
					case $this->get ( "sub-commands-create" ) :
						(isset ( $args [1] )) ? $this->CreativeEconomy ( $player, $args [1] ) : $this->CreativeEconomy ( $player );
						break;
					case $this->get ( "sub-commands-change" ) :
						(isset ( $args [1] )) ? $this->ChangeMarketPrice ( $player, $args [1] ) : $this->ChangeMarketPrice ( $player );
						break;
					case $this->get ( "sub-commands-lock" ) :
						$this->AllFreezeMarket ( $player );
						break;
					case $this->get ( "sub-commands-allowcreate" ) :
						(isset ( $args [1] )) ? $this->AllowMarketCreate ( $player, $args [1] ) : $this->AllowMarketCreate ( $player );
						break;
					case $this->get ( "sub-commands-allowcommand" ) :
						(isset ( $args [1] )) ? $this->AllowMarketCommand ( $player, $args [1] ) : $this->AllowMarketCommand ( $player );
						break;
					default :
						$this->get ( "ko-commands-ce-help1" );
						$this->get ( "ko-commands-ce-help2" );
						$this->get ( "ko-commands-ce-help3" );
						$this->get ( "ko-commands-ce-help4" );
						$this->get ( "ko-commands-ce-help5" );
						break;
				}
				break;
		}
		return true;
	}
	public function CEBuyCommand(Player $player, $item = null) {
		// TODO 명령받는 아이템에 따라 구매처리
	}
	public function CESellCommand(Player $player, $item = null) {
		// TODO 명령받는 아이템에 따라 판매처리
	}
	public function CECanBuyListCommand(Player $player, $index = null) {
		// TODO 구매가능한 아이템리스트 출력
	}
	public function CEMakeHologramCommand(Player $player, $item = null) {
		// TODO 주울 수 없는 아이템 홀로그램 생성/세팅저장
	}
	public function CreativeEconomy(Player $player, $item = null) {
		// TODO 터치한 위치에 자동 쇼케이스 작업기능
	}
	public function ChangeMarketPrice(Player $player, $item = null) {
		// TODO 기본가격시세를 입력된 값으로 설정
	}
	public function AllFreezeMarket() {
		// TODO 상점매매를 모두 허용/차단
	}
	public function AllowMarketCreate() {
		// TODO 상점개설 허용/차단
	}
	public function AllowMarketCommand() {
		// TODO 상점명령어 허용/차단
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