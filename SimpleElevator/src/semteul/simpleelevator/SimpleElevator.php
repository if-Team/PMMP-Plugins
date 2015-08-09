<?php

namespace semteul\simpleelevator;

use semteul\simpleelevator\task\SimpleElevatorTask;
use semteul\simpleelevator\Floor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\String;
use pocketmine\nbt\tag\Int;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Cancellable;


class SimpleElevator extends PluginBase implements Listener {
	const VERSION = "1.0";
	const MESSAGE_VERSION = 1;
	
	/** @var array*/
	public $elevateQueue = [];
	
	/** @var number*/
	const SIGN_UP = 0;
	const SIGN_DOWN = 1;
	
	/** @var number*/
	const MODE_MOVE = 0;
	const MODE_ONGROUND = 1;
	
	/** @var number*/
	const TICK_MODE = 0;
	const TICK_TYPE = 1;
	const TICK_PLAYER = 2;
	const TICK_START_POS = 3;
	const TICK_END_POS = 4;
	const TICK_BLOCKS = 5;
	const TICK_FALL_TIME = 6;
	const TICK_LEVEL = 7;
	
	/** @var number*/
	const TYPE_UP = 0;
	const TYPE_DOWN = 1;
	
	/** @var string*/
	const COLOR_IRON = TextFormat::GRAY;
	const COLOR_GOLD = TextFormat::GOLD;
	const COLOR_DIAMOND = TextFormat::AQUA;
	const COLOR_EMERALD = TextFormat::GREEN;
	
	/** @var array*/
	static $blocks = [];
	
	/** @var array*/
	private $lang = [];
	
	
	
	public function onEnable() {
		$this->loadMessages();
		$this->registerBlock();
		$this->commands();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new SimpleElevatorTask($this), 1);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	
	
	public function onDisable() {
		$this->powerOffImmediately();
	}
	
	
	
	private function loadMessages() {
		@mkdir($this->getDataFolder());
		
		$this->updateMessages("messages.yml");
		$this->lang = (new Config($this->getDataFolder () . "messages.yml", Config::YAML))->getAll();
	}
	
	
	
	private function updateMessages($filename = "messages.yml") {
		
		$this->saveResource($filename, false);
		
		$lang = (new Config($this->getDataFolder () . $filename, Config::YAML))->getAll();
		
		if(!isset($lang["message-version"])) {
			$this->saveResource($filename, true);
		}else if($lang["message-version"] != self::MESSAGE_VERSION) {
			$this->saveResource($filename, true);
		}
	}
	
	
	
	private function registerBlock() {
		
		if(isset($this->lang["block-simple-slow"]) && isset($this->lang["block-damage-simple-slow"])) {
			self::$blocks["TYPE-SIMPLE-SLOW"] = Block::get($this->lang["block-simple-slow"], $this->lang["block-damage-simple-slow"]);
		}else {
			self::$blocks["TYPE-SIMPLE-SLOW"] = Block::get(Block::IRON_BLOCK);
		}
		
		if(isset($this->lang["block-simple-fast"]) && isset($this->lang["block-damage-simple-fast"])) {
			self::$blocks["TYPE-SIMPLE-FAST"] = Block::get($this->lang["block-simple-fast"], $this->lang["block-damage-simple-fast"]);
		}else {
			self::$blocks["TYPE-SIMPLE-FAST"] = Block::get(Block::GOLD_BLOCK);
		}
		
		if(isset($this->lang["block-advance-slow"]) && isset($this->lang["block-damage-advance-slow"])) {
			self::$blocks["TYPE-ADVANCE-SLOW"] = Block::get($this->lang["block-advance-slow"], $this->lang["block-damage-advance-slow"]);
		}else {
			self::$blocks["TYPE-ADVANCE-SLOW"] = Block::get(Block::EMERALD_BLOCK);
		}
		
		if(isset($this->lang["block-advance-fast"]) && isset($this->lang["block-damage-advance-fast"])) {
			self::$blocks["TYPE-ADVANCE-FAST"] = Block::get($this->lang["block-advance-fast"], $this->lang["block-damage-advance-fast"]);
		}else {
			self::$blocks["TYPE-ADVANCE-FAST"] = Block::get(Block::DIAMOND_BLOCK);
		}
		
		if(isset($this->lang["block-extension-floor"]) && isset($this->lang["block-damage-extension-floor"])) {
			self::$blocks["TYPE-EXTENSION-FLOOR"] = Block::get($this->lang["block-extension-floor"], $this->lang["block-damage-extension-floor"]);
		}else {
			self::$blocks["TYPE-EXTENSION-FLOOR"] = Block::get(Block::GLASS);
		}
	}
	
	
	
	public static function getBlock($str) {
		if(isset(self::$blocks[$str])) {
			return self::$blocks[$str];
		}else {
			return Block::get(Block::AIR);
		}
	}
	
	
	
	public function msg($msg) {
		if(isset($this->lang[$msg])) {
			if(isset($this->lang[$msg][$this->lang["default-language"]])) {
			return $this->lang[$msg][$this->lang["default-language"]];
			}else if(isset($this->lang[$msg]["eng"])) {
				return $this->lang[$msg]["eng"];
			}
			return $msg;
		}
	}
	
	
	
	public function tipMsg($step) {
		$messages = [];
		for($max = 0; isset($this->lang["help-" . $step . $max][$this->lang["default-language"]]); $max++) {
			$messages[] = $this->lang["help-" . $step . $max][$this->lang["default-language"]];
		}
		return $messages;
	}
	
	
	
	public function getMaxTipMsg() {
		$step = 0;
		while(isset($this->lang["help-" . $step + 1 . "0"][$this->lang["default-language"]])) {
			$step++;
			if($step > 100) {
				//something wrong...
				return -1;
			}
		}
		return $step - 1;
	}
	
	
	
	public function helpPage($index, $player) {
		if($index == 0) {
			$text = [];
			$text[] = TextFormat::GREEN . "--- " . $this->msg("plugin-name") . " commands ---";
			$text[] = TextFormat::DARK_GREEN . $this->msg("command-sub-help-usage") . " " . TextFormat::GREEN . $this->msg("command-sub-help-description");
			$text[] = TextFormat::DARK_GREEN . $this->msg("command-sub-debug-usage") . " " . TextFormat::GREEN . $this->msg("command-sub-debug-description");
			foreach($text as $msg) {
				$player->sendMessage($msg);
			}
			$player->sendMessage(TextFormat::DARK_GREEN . $this->msg("command-elevator-usage") . " info " . TextFormat::GREEN . "Plugin info");
		}else {
			$text = [];
			$text[] = TextFormat::GREEN . "--- " . $this->msg("plugin-name") . " help page(" . $index . "/ " . $this->getMaxTipMsg() . ") ---";
			$msgs = $this->tipMsg($index);
			foreach($msgs as $msg) {
				$text[] = TextFormat::YELLOW . $msg;
			}
			foreach($text as $msg) {
				$player->sendMessage($msg);
			}
		}
	}
	
	
	
	private function commands() {
		$this->registerCommand($this->msg("command-elevator"), "Elevator", "elevator", $this->msg("command-elevator-description"), $this->msg("command-elevator-usage"));
	}
	
	
	
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer()->getCommandMap();
		$command = new PluginCommand($name, $this);
		$command->setDescription ($description);
		$command->setPermission ($permission);
		$command->setUsage ($usage);
		$commandMap->register ($fallback, $command);
	}
	
	
	
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if(!(isset($args[0]))) {
			$this->helpPage(0, $player);
			return true;
		}else {
			switch($args[0]) {
				case $this->msg("command-sub-help"):
					if(isset($args[1])) {
						(Int) $index = $args[1];
					}else {
						$index = 1;
					}
					$this->helpPage($index, $player);
					return true;
				case $this->msg("command-sub-debug"):
					if($player->getName() !== "CONSOLE") {
						$player->sendMessage(TextFormat::DARK_RED . $this->msg("plugin-prefix") . " You are not CONSOLE!");
						return true;
					}
					$this->getServer()->getLogger()->setLogDebug($args[1]);
					return true;
				case "info":
					$player->sendMessage(TextFormat::GREEN . "Plugin name: SimpleElevator");
					$player->sendMessage(TextFormat::GREEN . "Version: " . self::VERSION);
					$player->sendMessage(TextFormat::GREEN . "Author: " . "SemTeul");
					return true;
			}
			return false;
		}
	}
	
	
	
		public function onTouch(PlayerInteractEvent $event) {
		
		//onTouch
		if($event->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			
			$block = $event->getBlock();
			$this->log("touch - " . $block);
			switch($block->getId()) {
				
				case Block::WALL_SIGN:
					$tile = $event->getPlayer()->getLevel()->getTile($block);
					if(!($tile instanceof Sign)) {
						return;
					}
					
					$two = $event->getPlayer()->getLevel()->getBlock(new Vector3($block->getX(), $block->getY() - 2, $block->getZ()));
					$one = $event->getPlayer()->getLevel()->getBlock(new Vector3($block->getX(), $block->getY() - 1, $block->getZ()));
					
					$this->log("bottom2: " . $two . " bottom1: " . $one);
			
					if(Floor::isElevatorBlock($two) || Floor::isExtensionFloorBlock($two)) {
						
						$this->log("onTouch-buttonUp run");
						$this->buttonUp($two, $event->getPlayer(), $event);
						
					}else if(Floor::isElevatorBlock($one) || Floor::isExtensionFloorBlock($one)){
						
						$this->log("onTouch-buttonDown run");
						$this->buttonDown($one, $event->getPlayer(), $event);
						
					}
					break;
					
				default:
					if(Floor::isBlockEqual($block, $this->getBlock("TYPE-ADVANCE-SLOW")) || Floor::isBlockEqual($block, $this->getBlock("TYPE-ADVANCE-FAST")) || Floor::isBlockEqual($block, $this->getBlock("TYPE-EXTENSION-FLOOR"))) {
						
						$this->log("onTouch-advanceMove run");
						$this->advanceMove($event->getPlayer(), $block, $event);
						
					}
			}
		}
	}
	
	
	
	public function preventFallenDamage(EntityDamageEvent $event) {
		
		if($event->getCause() == EntityDamageEvent::CAUSE_FALL) {
			
			if(!$event->getEntity() instanceof Player) {
				return;
			}
			
			if(isset($this->elevateQueue[$event->getEntity()->getName()])) {
				$this->log("preventFallenDamage run");
				$event->setCancelled();
			}
		}
	}
	
	
	
	public function preventFlyKick(PlayerKickEvent $event) {
		if(isset($this->elevateQueue[$event->getPlayer()->getName()])) {
			if($event->getReason() == "Flying is not enabled on this server") {
				$this->log("preventFlyKick run");
				$event->setCancelled();
			}
		}
	}
	
	
	
	public function boarderDie(PlayerDeathEvent $event) {
		if(isset($this->elevateQueue[$event->getPlayer()->getName()])) {
			$this->log("boarderDie run");
			$this->emergencyStop($event->getEntity()->getName(), false);
		}
	}
	
	
	
	public function boarderQuit(PlayerQuitEvent $event) {
		if(isset($this->elevateQueue[$event->getPlayer()->getName()])) {
			$this->log("boarderQuit run");
			$this->emergencyStop($event->getEntity()->getName(), true);
		}
	}
	
	
	
	public function boarderInAnothorPlace($level, $pos, $player) {
		
		if(isset($this->elevatorQueue[$player->getName()])) {
			
			if($level !== $player->getLevel()) {
				$this->emergencyStop($event->getEntity()->getName(), false);
			}
			
			if($pos->getX() + 10 < $player->getX() || $pos->getX() - 10 > $player->getX() || $pos->getZ() + 10 < $player->getZ() || $pos->getZ() - 10 > $player->getZ()) {
				$this->emergencyStop($event->getEntity()->getName(), false);
			}
			
		}
	}
	
	
	
	public function log($str) {
		$this->getServer()->getLogger()->debug($str);
	}
	
	
	
	public function ElevatorTickTask() {
		
		$keys = array_keys($this->elevateQueue);
		
		foreach($keys as $e) {
			$mode = $this->elevateQueue[$e][self::TICK_MODE];
			$type = $this->elevateQueue[$e][self::TICK_TYPE];
			$player = $this->elevateQueue[$e][self::TICK_PLAYER];
			$startPos = $this->elevateQueue[$e][self::TICK_START_POS];
			$endPos = $this->elevateQueue[$e][self::TICK_END_POS];
			$blocks = $this->elevateQueue[$e][self::TICK_BLOCKS];
			$fallTime = $this->elevateQueue[$e][self::TICK_FALL_TIME];
			$level = $this->elevateQueue[$e][self::TICK_LEVEL];
			
			switch($mode) {
				
				case self::MODE_MOVE:
				
					if(!($player->isOnline())) {
						$keys = array_keys($blocks);
						
						foreach($keys as $k) {
							$player->getLevel()->setBlock(new Vector3($blocks[$k]["x"], $blocks[$k]["y"], $blocks[$k]["z"]), Block::get($blocks[$k]["id"], $blocks[$k]["data"]), true, false);
						}
						unset($this->elevateQueue[$e]);
						continue;
					}
					
					$player->resetFallDistance();
					$this->boarderInAnothorPlace($level, $endPos, $player);
					
					if($endPos->getX() + 0.3 > $player->getPosition()->getX()) {
						$motionX = 0.1;
					}else if($endPos->getX() + 0.7 < $player->getPosition()->getX()) {
						$motionX = -0.1;
					}else {
						$motionX = 0;
					}
					
					if($endPos->getZ() + 0.3 > $player->getPosition()->getZ()) {
						$motionZ = 0.1;
					}else if($endPos->getZ() + 0.7 < $player->getPosition()->getZ()) {
						$motionZ = -0.1;
					}else {
						$motionZ = 0;
					}
						
					//slow
					if(Floor::isBlockEqual($type, $this->getBlock("TYPE-SIMPLE-SLOW")) || Floor::isBlockEqual($type, $this->getBlock("TYPE-ADVANCE-SLOW"))) {
						$speed = 0.2;
						
					//fast
					}else if(Floor::isBlockEqual($type, $this->getBlock("TYPE-SIMPLE-FAST")) || Floor::isBlockEqual($type, $this->getBlock("TYPE-ADVANCE-FAST"))) {
						$speed = 0.6;
					}
				
					//UP
					if($startPos->getY() < $endPos->getY()) {
						if($player->getPosition()->getY() - 1 < $endPos->getY()) {
							
							$player->setMotion(new Vector3($motionX, $speed, $motionZ));
							
						//arrive
						}else {
							$player->setMotion(new Vector3(0, 0, 0));
							$player->teleport(new Vector3($endPos->getX() + 0.5, $endPos->getY() + 1.63, $endPos->getZ() + 0.5));
							$this->elevateQueue[$e][self::TICK_MODE] = self::MODE_ONGROUND;
						}
						
					//Down
					}else {
						
						if($player->getPosition()->getY() - 2 > $endPos->getY()) {
							
							$player->setMotion(new Vector3($motionX, -1 * $speed, $motionZ));
							
						//arrive
						}else {
							$player->setMotion(new Vector3(0, 0, 0));
							$player->teleport(new Vector3($endPos->getX() + 0.5, $endPos->getY() + 1.63, $endPos->getZ() + 0.5));
							$this->elevateQueue[$e][self::TICK_MODE] = self::MODE_ONGROUND;
						}
					}
					break;
				
				case self::MODE_ONGROUND:
				
					if(count($blocks) > 0) {
						$keys = array_keys($blocks);
						foreach($keys as $k) {
							$player->getLevel()->setBlock(new Vector3($blocks[$k]["x"], $blocks[$k]["y"], $blocks[$k]["z"]), Block::get($blocks[$k]["id"], $blocks[$k]["data"]), true, false);
						}
						
						$this->elevateQueue[$e][self::TICK_BLOCKS] = [];
					}
					
					if(--$this->elevateQueue[$e][self::TICK_FALL_TIME] < 1) {
						unset($this->elevateQueue[$e]);
						continue;
					}
					break;
			}
		}
	}
	
	
	
	public function powerOffImmediately() {
		$keys = array_keys($this->elevateQueue);
		
		foreach($keys as $e) {
			$this->emergencyStop($e);
		}
	}
	
	
	
	public function emergencyStop($elevatorIndex, $playerMove = true) {
		if(!isset($this->elevateQueue[$elevatorIndex])) {
			$this->log("emergencyStop-elevatorIndex($elevatorIndex) is not exists");
			return;
		}
		$blocks = $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS];
		$player = $this->elevateQueue[$elevatorIndex][self::TICK_PLAYER];
		
		//Block recover
		$keys = array_keys($blocks);
		foreach($keys as $k) {
			$player->getLevel()->setBlock(new Vector3($blocks[$k]["x"], $blocks[$k]["y"], $blocks[$k]["z"]), Block::get($blocks[$k]["id"], $blocks[$k]["data"]), true, false);
		}
		
		if($playerMove) {
			$player->teleportImmediate($this->elevateQueue[$elevatorIndex][self::TICK_START_POS], null, null);
		}
		$player->sendTip(TextFormat::RED . $this->msg("warning-emergency-stop"));
		
		unset($this->elevateQueue[$elevatorIndex]);
	}
	
	
	
	private function buttonUp($block, $player, $event) {
		$floor = new Floor($player->getLevel(), $block);
		
		$base = $floor->getBaseFloorBlock();
		
		if($base === false) {
			$this->log("buttonUp-base is not Block");
			return;
		}
		
		$baseType = $floor->getBaseType();
		
		if($baseType === 0 || $baseType === 1) {
			if($this->simpleMode(self::TYPE_UP, $player, $floor)) {
				if($event instanceof Cancellable) {
					if(!($event->isCancelled())) {
						$event->setCancelled();
					}
				}
				$this->log("buttonUp-simpleMode run");
				return;
			}
			
		}else if($baseType === 2 || $baseType === 3) {
			if($this->advanceMode(self::TYPE_UP, $player, $floor)) {
				if($event instanceof Cancellable) {
					if(!($event->isCancelled())) {
						$event->setCancelled();
					}
				}
				$this->log("buttonUp-advanceMode run");
				return;
			}
		}
		
		if($this->createNewButton($floor)) {
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			$this->log("buttonUp-createNewButton created");
			return;
		}
		
		if($this->createSubButton($floor)) {
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			$this->log("buttonUp-createSubButton created");
			return;
		}
	}
	
	
	
	private function buttonDown($block, $player, $event) {
		$floor = new Floor($player->getLevel(), $block);
		
		$base = $floor->getBaseFloorBlock();
		
		if($base === false) {
			$this->log("buttonDown-base is not Block");
			return;
		}
		
		$baseType = $floor->getBaseType();
		
		if($baseType === 0 || $baseType === 1) {
			if($this->simpleMode(self::TYPE_DOWN, $player, $floor)) {
				if($event instanceof Cancellable) {
					if(!($event->isCancelled())) {
						$event->setCancelled();
					}
				}
				$this->log("buttonDown-simpleMode run");
				return;
			}
			
		}else if($baseType === 2 || $baseType === 3) {
			if($this->advanceMode(self::TYPE_DOWN, $player, $floor)) {
				if($event instanceof Cancellable) {
					if(!($event->isCancelled())) {
						$event->setCancelled();
					}
				}
				$this->log("buttonDown-advanceMode run");
				return;
			}
		}
		
		if($this->createNewButton($floor)) {
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			$this->log("buttonDown-createNewButton created");
			return;
		}
		
		if($this->createSubButton($floor)) {
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			$this->log("buttonDown-createSubButton created");
			return;
		}
	}
	
	
	
	private function simpleMode($type, $player, $floor) {
		$sign = $floor->getUpButton();
		
		if($sign === false) {
			return false;
		}
		
		$text = $sign->getText();
		
		if(mb_substr($text[0], 2) !== $this->msg("sign-output-title")) {
			return false;
		}
		
		if($type === self::TYPE_UP) {
			$next = $floor->getNextFloorBlock();
			
			if($next === false) {
				$player->sendTip(TextFormat::YELLOW . $this->msg("warning-no-next-floor"));
				return true;
			}
			
			$this->log("simpleMode-moveFloor TYPE_UP");
			$this->moveFloor($player, $floor->getBaseFloorBlock(), $floor->getPosition(), $next);
			return true;
		}else if($type === self::TYPE_DOWN) {
			$prev = $floor->getPrevFloorBlock();
			
			if($prev === false) {
				$player->sendTip(TextFormat::YELLOW . $this->msg("warning-no-prev-floor"));
				return true;
			}
			
			$this->log("simpleMode-moveFloor TYPE_DOWN");
			$this->moveFloor($player, $floor->getBaseFloorBlock(), $floor->getPosition(), $prev);
			return true;
		}
		return false;
	}
	
	
	
	private function advanceMode($type, $player, $floor) {
		$sign1 = $floor->getUpButton();
		$sign2 = $floor->getDownButton();
		
		if($sign1 === false || $sign2 === false) {
			$this->log("advanceMode-sign1, sign2 is not instanceof Sign");
			return false;
		}
		
		$text1 = $sign1->getText();
		$text2 = $sign2->getText();
		
		if(mb_substr($text1[0], 2) !== $this->msg("sign-output-title")) {
			$this->log("advanceMode-text1 is not instanceof Elevator");
			return false;
		}
		
		$textColor = mb_substr($text1[0], 0, 2);
		
		if($type === self::TYPE_UP) {
			
			$index = $this->parseTargetFloor($text1[3]);
			$last = $floor->getLastFloorIndex();
			
			if($last === false) {
				$this->log("advanceMode-TYPE_UP-last can't find base Block");
				return false;
			}
			$this->log("advanveMode-TYPE_UP-last $last");
			$this->log("advanceMode-TYPE_UP-index $index");
			
			if($index >= $last) {
				$this->log("advanceMode-TYPE_UP-index this is last index");
				$player->sendTip(TextFormat::YELLOW . $this->msg("warning-no-next-cursor"));
				return true;
			}
			
			$index++;
			
			if($index == $last) {
				$color = TextFormat::BOLD . TextFormat::RED;
			}else {
				$color = TextFormat::BOLD . TextFormat::BLUE;
			}
			
			$text1[1] = $color . $this->msg("sign-output-up");
			$text1[3] = $textColor . $this->msg("sign-output-target-floor") . ": " . $this->stringifyIndex($index);
			
			$sign1->setText($text1[0], $text1[1], $text1[2], $text1[3]);
			$sign1->saveNBT();
			
			$text2[0] = TextFormat::BOLD . TextFormat::BLUE . $this->msg("sign-output-down");
			
			$sign2 ->setText($text2[0], $text2[1], $text2[2], $text2[3]);
			$sign2->saveNBT();
			return true;
			
		}else if($type === self::TYPE_DOWN) {
			
			$index = $this->parseTargetFloor($text1[3]);
			$first = $floor->getFirstFloorIndex();
			
			if($first === false) {
				$this->log("advanceMode-TYPE_DOWN-first can't find base Block");
				return false;
			}
			$this->log("advanveMode-TYPE_DOWN-first $first");
			$this->log("advanceMode-TYPE_DOWN-index $index");
			
			if($index <= $first) {
				$this->log("advanceMode-TYPE_UP-index this is first index");
				$player->sendTip(TextFormat::YELLOW . $this->msg("warning-no-prev-cursor"));
				return true;
			}
			
			$index--;
			
			if($index == $first) {
				$color = TextFormat::BOLD . TextFormat::RED;
			}else {
				$color = TextFormat::BOLD . TextFormat::BLUE;
			}
			
			$text1[1] = TextFormat::BOLD . TextFormat::BLUE . $this->msg("sign-output-up");
			$text1[3] = $textColor . $this->msg("sign-output-target-floor") . ": " . $this->stringifyIndex($index);
			
			$sign1->setText($text1[0], $text1[1], $text1[2], $text1[3]);
			$sign1->saveNBT();
			
			$text2[0] = $color . $this->msg("sign-output-down");
			
			$sign2 ->setText($text2[0], $text2[1], $text2[2], $text2[3]);
			$sign2->saveNBT();
			return true;
			
		}
		return false;
	}
	
	
	
	private function advanceMove($player, $block, $event) {
		
		$floor = new Floor($player->getLevel(), $block);
		
		$sign = $floor->getUpButton();
		
		if($sign === false) {
			$this->log("advanceMove-sign is not instance of Sign");
			return;
		}
		
		$text = $sign->getText();
		
		if(mb_substr($text[0], 2) !== $this->msg("sign-output-title")) {
			$this->log("advanceMove-text this is not instance of Elevator");
			return;
		}
		
		$index = $this->parseTargetFloor($text[3]);
		
		$target = $floor->getFloorBlockByIndex($index);
		
		if($target === false) {
			$this->log("advanceMove-target can't find target floor (index: $index) (text3: $text[3])");
			$player->sendTip(TextFormat::YELLOW . $this->msg("warning-elevator-crash"));
			return;
		}
		
		if($event instanceof Cancellable) {
			if(!($event->isCancelled())) {
				$event->setCancelled();
			}
		}
		
		$this->moveFloor($player, $floor->getBaseFloorBlock(), $floor->getPosition(), $target);
	}
	
	
	
	public function createNewButton($floor) {
		
		if($floor->getType() > 3 || $floor->getType() < 0) {
			return false;
		}
		
		$type = $floor->getBaseType();
		$color = $floor->getColor();
		$level = $floor->getLevel();
		$pos = $floor->getPosition();
		$next = $floor->getNextFloorBlock();
		$prev = $floor->getPrevFloorBlock();
		$index = $floor->getCurrentFloorIndex();
		$indexString = $this->stringifyIndex($index);
		
		$button1 = $level->getTile(new Vector3($pos->getX(), $pos->getY() + 2, $pos->getZ()));
		$button2 = $level->getTile(new Vector3($pos->getX(), $pos->getY() + 1, $pos->getZ()));
		
		if($button1 instanceof Sign) {
			$text1 = $button1->getText();
			
			if($text1 == [$this->msg("sign-input-register-elevator"), "", "", ""]) {
				
				$text1[0] = $color . $this->msg("sign-output-title");
				$text1[1] = TextFormat::BOLD . ($next !== false ? TextFormat::BLUE : TextFormat::RED) . $this->msg("sign-output-up");
				$text1[2] = $color . $this->msg("sign-output-current-floor") . ": " . $indexString;
				if($type === 2 || $type === 3) {
					$text1[3] = $color . $this->msg("sign-output-target-floor") . ": " . $this->msg("sign-output-lobby");
				}
				$button1->setText($text1[0], $text1[1], $text1[2], $text1[3]);
				$button1->saveNBT();
				
			}else {
				return false;
			}
		}else {
			return false;
		}
		
		$this->createSubButton($floor);
		return true;
	}
	
	
	
	public function createSubButton($floor) {
		
		$base = $floor->getBaseFloorBlock();
		
		$baseSign = $floor->getLevel()->getTile(new Vector3($base->getX(), $base->getY() + 2, $base->getZ()));
		
		if(!($baseSign instanceof Sign)) {
			return false;
		}
		
		$baseTexts = $baseSign->getText();
		
		if(mb_substr($baseTexts[0], 2) !== $this->msg("sign-output-title")) {
			return false;
		}
		
		if($floor->getColor() != mb_substr($baseTexts[0], 0, 2)) {
			return false;
		}
		
		$type = $floor->getBaseType();
		$color = $floor->getColor();
		$level = $floor->getLevel();
		$pos = $floor->getPosition();
		$next = $floor->getNextFloorBlock();
		$prev = $floor->getPrevFloorBlock();
		$index = $floor->getCurrentFloorIndex();
		$indexString = $this->stringifyIndex($index);
		
		$button1 = $level->getTile(new Vector3($pos->getX(), $pos->getY() + 2, $pos->getZ()));
		$button2 = $level->getTile(new Vector3($pos->getX(), $pos->getY() + 1, $pos->getZ()));
		
		$nothing = false;
		
		if($button1 instanceof Sign) {
			$text1 = $button1->getText();
			
			if($text1 == ["", "", "", ""]) {
				
				$text1[0] = $color . $this->msg("sign-output-title");
				$text1[1] = TextFormat::BOLD . ($next !== false ? TextFormat::BLUE : TextFormat::RED) . $this->msg("sign-output-up");
				$text1[2] = $color . $this->msg("sign-output-current-floor") . ": " . $indexString;
				if($type === 2 || $type === 3) {
					$text1[3] = $color . $this->msg("sign-output-target-floor") . ": " . $this->msg("sign-output-lobby");
				}
				
				$button1->setText($text1[0], $text1[1], $text1[2], $text1[3]);
				$button1->saveNBT();
				
			}else if(mb_substr($text1[0], 2) !== $this->msg("sign-output-title")) {
				return false;
			}else {
				$nothing = true;
			}
		}else {
			return false;
		}
		
		if($button2 instanceof Sign) {
			$text2 = $button2->getText();
			
			if($text2 == ["", "", "", ""]) {
				$text2[0] = TextFormat::BOLD . ($prev !== false ? TextFormat::BLUE : TextFormat::RED) . $this->msg("sign-output-down");
				
				$button2->setText($text2[0], $text2[1], $text2[2], $text2[3]);
				$button2->saveNBT();
				
				$nothing = false;
				
			}else if(mb_substr($text2[0], 2) != $this->msg("sign-output-down")) {
				return false;
			}
		}
		if($nothing) {
			return false;
		}else {
			return true;
		}
	}
	
	
	
	public function stringifyIndex($index) {
		if($index == 0) {
			$string = $this->msg("sign-output-lobby");
		}else if($index < 0) {
			$string = $this->msg("sign-output-basement") . abs($index);
		}else {
			$string = $index . "";
		}
		return $string;
	}
	
	
	
	public function parseIndex($string) {
		
		$len = mb_strlen($this->msg("sign-output-basement"));
		
		$b = mb_substr($string, 0, $len);
		$absFloor = mb_substr($string, $len);
		
		if($string === $this->msg("sign-output-lobby")) {
			$floor = 0;
		}else if($b === $this->msg("sign-output-basement")) {
			$floor = (-1 * $absFloor);
		}else {
			$floor = floor($string);
		}
		
		return $floor;
	}
	
	
	
	public function parseTargetFloor($string) {
		
		$split = explode(": ", $string);
		
		if(mb_substr($split[0], 2) === $this->msg("sign-output-target-floor")) {
			return $this->parseIndex($split[1]);
		}
		
		return null;
	}
	
	
	
	private function moveFloor($player, $type, $currentPos, $targetPos) {
		if(isset($this->elevateQueue[$player->getName()])) {
			return;
		}
		
		$blocks = [];
		$range = $targetPos->getY() - $currentPos->getY();
		
		//clearBlockOnTop
		if($range > 0) {
			for($e = 0; $e <= $range; $e++) {
				
				$block = $player->getLevel()->getBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()));
				
				if(Floor::isElevatorBlock($block) || Floor::isExtensionFloorBlock($block)) {
					
					$blocks[] = [
						"x" => $currentPos->getX(),
						"y" => $currentPos->getY() + $e,
						"z" => $currentPos->getZ(),
						"id" => $block->getId(),
						"data" => $block->getDamage()
					];
					
					$player->getLevel()->setBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()), Block::get(0, 0), true, false);
				}
			}
		//clearBlockOnBottom
		}else {
			for($e = 0; $e >= $range; $e--) {
				
				$block = $player->getLevel()->getBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()));
				
				if(Floor::isElevatorBlock($block) || Floor::isExtensionFloorBlock($block)) {
					
					$blocks[] = [
						"x" => $currentPos->getX(),
						"y" => $currentPos->getY() + $e,
						"z" => $currentPos->getZ(),
						"id" => $block->getId(),
						"data" => $block->getDamage()
					];
					
					$player->getLevel()->setBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()), Block::get(0, 0), true, false);
				}
			}
		}
		$this->elevateQueue[$player->getName()] = [
			self::TICK_MODE => self::MODE_MOVE,
			self::TICK_TYPE => $type,
			self::TICK_START_POS => $currentPos,
			self::TICK_END_POS => $targetPos,
			self::TICK_PLAYER => $player,
			self::TICK_FALL_TIME => 20,
			self::TICK_BLOCKS => $blocks,
			self::TICK_LEVEL => $player->getLevel()
		];
	}
}
?>