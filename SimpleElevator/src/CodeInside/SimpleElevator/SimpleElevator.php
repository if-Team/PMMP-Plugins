<?php

namespace codeinside\simpleelevator;

use codeinside\simpleelevator\task\SimpleElevatorTask;

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
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Cancellable;


class SimpleElevator extends PluginBase implements Listener {
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
	
	/** @var string*/
	const COLOR_IRON = TextFormat::GRAY;
	const COLOR_GOLD = TextFormat::GOLD;
	const COLOR_DIAMOND = TextFormat::AQUA;
	const COLOR_EMERALD = TextFormat::GREEN;
	
	/** @var number*/
	const TYPE_IRON = Block::IRON_BLOCK;
	const TYPE_GOLD = Block::GOLD_BLOCK;
	const TYPE_DIAMOND = Block::DIAMOND_BLOCK;
	const TYPE_EMERALD = Block::EMERALD_BLOCK;
	const TYPE_GLASS = Block::GLASS;
	
	/** @var array*/
	private $lang = [];
	
	
	
	public function onEnable() {
		$this->loadMessages();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new SimpleElevatorTask($this), 1);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		//debug
		$this->getServer()->getLogger()->setLogDebug(true);
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
		while(isset($this->lang["help-" . $step . "0"][$this->lang["default-language"]])) {
			$step++;
			if($step > 100) {
				//something wrong...
				return -1;
			}
		}
		return $step;
	}
	
	
	
	public function ElevatorTickTask() {
		
		$keys = array_keys($this->elevateQueue);
		
		foreach($keys as $e) {
			
			switch($this->elevateQueue[$e][self::TICK_MODE]) {
				
				case self::MODE_MOVE:
				
					if(!($this->elevateQueue[$e][self::TICK_PLAYER]->isOnline())) {
						
						$this->log("elevator cancell: " .$this->elevateQueue[$e][self::TICK_PLAYER]->getName() . " is Offline");
						$keys = array_keys($this->elevateQueue[$e][self::TICK_BLOCKS]);
						
						foreach($keys as $k) {
							$this->log($k . "index Block place " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["x"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["y"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["z"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["id"]);
							
							$this->elevateQueue[$e][self::TICK_PLAYER]->getLevel()->setBlock(new Vector3($this->elevateQueue[$e][self::TICK_BLOCKS][$k]["x"], $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["y"], $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["z"]), Block::get($this->elevateQueue[$e][self::TICK_BLOCKS][$k]["id"], 0), true, false);
						}
						unset($this->elevateQueue[$e]);
						continue;
					}
					
					//UP
					if($this->elevateQueue[$e][self::TICK_START_POS]->getY() < $this->elevateQueue[$e][self::TICK_END_POS]->getY()) {
						$this->log("goUp");
						if($this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getY() - 1 < $this->elevateQueue[$e][self::TICK_END_POS]->getY()) {
							//NOT WORK
							//$pos = new Vector3($this->elevateQueue[$e][self::TICK_START_POS]->getX() + 0.5, $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getY(), $this->elevateQueue[$e][self::TICK_START_POS]->getZ() + 0.5);
							//$this->elevateQueue[$e][self::TICK_PLAYER]->teleport($pos);
							
							//Replaced method
							if($this->elevateQueue[$e][self::TICK_START_POS]->getX() + 0.3 > $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getX()) {
								$motionX = 0.1;
							}else if($this->elevateQueue[$e][self::TICK_START_POS]->getX() + 0.7 < $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getX()) {
								$motionX = -0.1;
							}else {
								$motionX = 0;
							}
							
							if($this->elevateQueue[$e][self::TICK_START_POS]->getZ() + 0.3 > $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getZ()) {
								$motionZ = 0.1;
							}else if($this->elevateQueue[$e][self::TICK_START_POS]->getZ() + 0.7 < $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getZ()) {
								$motionZ = -0.1;
							}else {
								$motionZ = 0;
							}
							
							//slow
							if($this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_IRON || $this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_EMERALD) {
								$this->elevateQueue[$e][self::TICK_PLAYER]->setMotion(new Vector3($motionX, 0.2, $motionZ));
								
							//fast
							}else if($this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_GOLD || $this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_DIAMOND) {
								$this->elevateQueue[$e][self::TICK_PLAYER]->setMotion(new Vector3($motionX, 0.6, $motionZ));
							}
							
						//arrive
						}else {
							$this->elevateQueue[$e][self::TICK_PLAYER]->setMotion(new Vector3(0, 0, 0));
							
							//Block recover
							$keys = array_keys($this->elevateQueue[$e][self::TICK_BLOCKS]);
							foreach($keys as $k) {
								$this->log($k . "index Block place " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["x"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["y"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["z"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["id"]);
								$this->elevateQueue[$e][self::TICK_PLAYER]->getLevel()->setBlock(new Vector3($this->elevateQueue[$e][self::TICK_BLOCKS][$k]["x"], $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["y"], $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["z"]), Block::get($this->elevateQueue[$e][self::TICK_BLOCKS][$k]["id"], 0), true, false);
							}
							$this->log("elevator arrive");
							$this->elevateQueue[$e][self::TICK_PLAYER]->teleport(new Vector3($this->elevateQueue[$e][self::TICK_END_POS]->getX() + 0.5, $this->elevateQueue[$e][self::TICK_END_POS]->getY() + 1.1, $this->elevateQueue[$e][self::TICK_END_POS]->getZ() + 0.5));
							$this->elevateQueue[$e][self::TICK_MODE] = self::MODE_ONGROUND;
						}
						
					//Down
					}else {
						$this->log("goDown");
						if($this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getY() - 2 > $this->elevateQueue[$e][self::TICK_END_POS]->getY()) {
							//NOT WORK
							//$pos = new Vector3($this->elevateQueue[$e][self::TICK_START_POS]->getX() + 0.5, $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getY(), $this->elevateQueue[$e][self::TICK_START_POS]->getZ() + 0.5);
							//$this->elevateQueue[$e][self::TICK_PLAYER]->teleport($pos);
							
							//Replaced method
							if($this->elevateQueue[$e][self::TICK_START_POS]->getX() + 0.3 > $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getX()) {
								$motionX = 0.1;
							}else if($this->elevateQueue[$e][self::TICK_START_POS]->getX() + 0.7 < $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getX()) {
								$motionX = -0.1;
							}else {
								$motionX = 0;
							}
							
							if($this->elevateQueue[$e][self::TICK_START_POS]->getZ() + 0.3 > $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getZ()) {
								$motionZ = 0.1;
							}else if($this->elevateQueue[$e][self::TICK_START_POS]->getZ() + 0.7 < $this->elevateQueue[$e][self::TICK_PLAYER]->getPosition()->getZ()) {
								$motionZ = -0.1;
							}else {
								$motionZ = 0;
							}
							
							//slow
							if($this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_IRON || $this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_EMERALD) {
								$this->log("slow");
								$this->elevateQueue[$e][self::TICK_PLAYER]->setMotion(new Vector3($motionX, -0.2, $motionZ));
								
							//fast
							}else if($this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_GOLD || $this->elevateQueue[$e][self::TICK_TYPE] === self::TYPE_DIAMOND) {
								$this->log("fast");
								$this->elevateQueue[$e][self::TICK_PLAYER]->setMotion(new Vector3($motionX, -0.6, $motionZ));
							}else {
								$this->log("Error on $this->elevateQueue[$e][self::TICK_TYPE]: " . $this->elevateQueue[$e][self::TICK_TYPE]);
							}
							
						//arrive
						}else {
							$this->elevateQueue[$e][self::TICK_PLAYER]->setMotion(new Vector3(0, 0, 0));
							
							//Block recover
							$keys = array_keys($this->elevateQueue[$e][self::TICK_BLOCKS]);
							foreach($keys as $k) {
								$this->log($k . "index Block place " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["x"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["y"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["z"] . " " . $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["id"]);
								$this->elevateQueue[$e][self::TICK_PLAYER]->getLevel()->setBlock(new Vector3($this->elevateQueue[$e][self::TICK_BLOCKS][$k]["x"], $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["y"], $this->elevateQueue[$e][self::TICK_BLOCKS][$k]["z"]), Block::get($this->elevateQueue[$e][self::TICK_BLOCKS][$k]["id"], 0), true, false);
							}
							$this->log("elevator arrive");
							$this->elevateQueue[$e][self::TICK_PLAYER]->teleport(new Vector3($this->elevateQueue[$e][self::TICK_END_POS]->getX() + 0.5, $this->elevateQueue[$e][self::TICK_END_POS]->getY() + 1.1, $this->elevateQueue[$e][self::TICK_END_POS]->getZ() + 0.5));
							$this->elevateQueue[$e][self::TICK_MODE] = self::MODE_ONGROUND;
						}
					}
					break;
				
				case self::MODE_ONGROUND:
					if(--$this->elevateQueue[$e][self::TICK_FALL_TIME] < 1) {
						$this->log("elevated " . $this->elevateQueue[$e][self::TICK_PLAYER]->getName());
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
	
	
	
	public function emergencyStop($elevatorIndex) {
		//Block recover
		$keys = array_keys($this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS]);
		foreach($keys as $k) {
			$this->log($k . "index Block place " . $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["x"] . " " . $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["y"] . " " . $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["z"] . " " . $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["id"]);
			$this->elevateQueue[$elevatorIndex][self::TICK_PLAYER]->getLevel()->setBlock(new Vector3($this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["x"], $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["y"], $this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["z"]), Block::get($this->elevateQueue[$elevatorIndex][self::TICK_BLOCKS][$k]["id"], 0), true, false);
		}
		$player = $this->elevateQueue[$elevatorIndex][self::TICK_PLAYER];
		$player->teleportImmediate($this->elevateQueue[$elevatorIndex][self::TICK_START_POS], null, null);
	}
	
	
	
	protected function buttonUp($type, $player, $pos, $sign, $event) {
		if(!($sign instanceof Sign)) {
			$this->getServer()->getLogger()->alert("'buttonUp' get wrong '$sign' parameter at X:" . $pos->getX() . " Y:" . $pos->getY() . " Z:" . $pos->getZ());
			return;
		}
		
		$texts = $sign->getText();
		$baseBlock = $this->getBaseFloor($player->getLevel(), $pos);
		
		if($baseBlock === false) {
			return;
		}
		
		$base = $baseBlock->getId();
		$sourcePos = new Vector3($pos->getX(), $pos->getY() - 2, $pos->getZ());
		$tempFloor = $this->getFloorIndex($player->getLevel(), $sourcePos);
		
		if($tempFloor === 0) {
			$floor = $this->msg("sign-output-lobby");
		}else if($tempFloor < 0) {
			$floor = $this->msg("sign-output-basement") . abs($tempFloor);
		}else {
			$floor = $tempFloor;
		}
		
		$color = $this->getColor($base);
		$hasNext = $this->hasNextFloor($player->getLevel(), new Vector3($pos->getX(), $pos->getY() - 2, $pos->getZ()));
		$nextColor = $hasNext ? TextFormat::BOLD . TextFormat::BLUE : TextFormat::BOLD . TextFormat::DARK_RED;
		
		//Create SubButton
		if($texts[0] === "" && $texts[1] === "" && $texts[2] === "" && $texts[3] === "") {
			if($type === self::TYPE_GLASS) {
				
				//Check Elevator
				$baseSign = $player->getLevel()->getTile(new Vector3($baseBlock->getX(), $baseBlock->getY() + 2, $baseBlock ->getZ()));
				if(!($baseSign instanceof Sign)) {
					return;
				}
				$baseTexts = $baseSign->getText();
				if(mb_substr($baseTexts[0], 2) != "[" . $this->msg("sign-output-title") . "]") {
					return;
				}
				
				if($base === self::TYPE_IRON || $base == self::TYPE_GOLD) {
					$sign->setText($color . "[" . $this->msg("sign-output-title") . "]", $nextColor . $this->msg("sign-output-up"), $this->msg("sign-output-current-floor") . " : " . $floor, "");
				}else if($base === self::TYPE_DIAMOND || $base == self::TYPE_EMERALD) {
					$sign->setText($color . "[" . $this->msg("sign-output-title") . "]", $nextColor . $this->msg("sign-output-up"), $this->msg("sign-output-current-floor") . " : " . $floor, $this->msg("sign-output-target-floor") . " : " . $floor);
				}else {
				$this->log("$base type isn't defind");
				return;
				}
				$sign->saveNBT();
				if($event instanceof Cancellable) {
					if(!($event->isCancelled())) {
						$event->setCancelled();
					}
				}
				
				//Auto fill under Sign
				//This method is BROKEN (NEED FIX)
				if($player->getLevel()->getBlockIdAt($pos->getX(), $pos->getY() - 1, $pos->getZ()) === Block::WALL_SIGN) {
					$pos2 = new Vector3($pos->getX(), $pos->getY() - 1, $pos->getZ());
					$tile = $player->getLevel()->getTile($pos2);
					if(!($tile instanceof Sign)) {
						return;
					}
					$texts = $tile->getText();
					if($texts[0] === "" && $texts[1] === "" && $texts[2] === "" && $texts[3] === "") {
					$this->buttonDown($type, $player, $pos2, $tile, $event);
					}
				}
				return;
			}
		}
		
		//Create New Elevator
		switch($type) {
			case Block::IRON_BLOCK:
			case Block::GOLD_BLOCK:
			case Block::DIAMOND_BLOCK:
			case Block::EMERALD_BLOCK:
				$isNew = true;
				break;
			default:
				$isNew = false;
		}
		
		$startColor = $this->getColor($type);
		
		$this->log($this->msg("sign-input-register-elevator") . ($texts[0] == $this->msg("sign-input-register-elevator") ? "yes" : "no"));
		if($isNew && $texts[0] == $this->msg("sign-input-register-elevator") && $texts[1] === "" && $texts[2] === "" && $texts[3] === "") {
			
			if($base === self::TYPE_IRON || $base === self::TYPE_GOLD) {
				$sign->setText($startColor . "[" . $this->msg("sign-output-title") . "]", $nextColor . $this->msg("sign-output-up"), $this->msg("sign-output-current-floor") . " : " . $floor, "");
			}else if($base === self::TYPE_DIAMOND || $base === self::TYPE_EMERALD) {
				$sign->setText($startColor . "[" . $this->msg("sign-output-title") . "]", $nextColor . $this->msg("sign-output-up"), $this->msg("sign-output-current-floor") . " : " . $floor, $this->msg("sign-output-target-floor") . " : " . $floor);
			}else {
				$this->log("$base type isn't defind");
				return;
			}
			$sign->saveNBT();
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			
			//Auto fill buttom Sign
			if($player->getLevel()->getBlockIdAt($pos->getX(), $pos->getY() - 1, $pos->getZ()) === Block::WALL_SIGN) {
				$pos2 = new Vector3($pos->getX(), $pos->getY() - 1, $pos->getZ());
				$tile = $player->getLevel()->getTile($pos2);
				if(!($tile instanceof Sign)) {
					return;
				}
				$texts = $tile->getText();
				if($texts[0] === "" && $texts[1] === "" && $texts[2] === "" && $texts[3] === "") {
					$this->buttonDown($type, $player, $pos2, $tile, $event);
				}
			}
			return;
		}
		
		//Up Button
		if(mb_substr($texts[0], 2) == "[" . $this->msg("sign-output-title") . "]") {
			$this->log("active Up Button");
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			
			//Simple Mode
			if($base === self::TYPE_IRON || $base === self::TYPE_GOLD) {
				$pos2 = $this->getNextFloor($player->getLevel(), $sourcePos);
				if($pos2 === false) {
					$this->log("no NextFloor");
					return;
				}
				$this->log("elevate " . $pos->getY() . "->" . $pos2->getY());
				$this->moveFloor($player, $base, $sourcePos, $pos2);
				
			//Advance Mode
			}else if($base === self::TYPE_DIAMOND || $base === self::TYPE_EMERALD) {
				$floorSign = $sign;
				$floorSubSign = $player->getLevel()->getTile(new Vector3($pos->getX(), $pos->getY() - 1, $pos->getZ()));
				if(!($floorSubSign instanceof Sign)) {
					$player->sendTip(TextFormat::RED . $this->msg("warning-no-under-sign"));
					return;
				}
				$floorTexts = $floorSign->getText();
				$floorSubTexts = $floorSubSign->getText();
				$floorStr = $floorTexts[3];
				$floorSplit = explode(" : ", $floorStr);
				if($floorSplit[0] === $this->msg("sign-output-target-floor")) {
					$floorBasement = mb_substr($floorSplit[1], 0, 1);
					$floorBasement2 = mb_substr($floorSplit[1], 1);
					if($floorSplit[1] === $this->msg("sign-output-lobby")) {
						$floorTarget = 0;
					}else if($floorBasement[0] === $this->msg("sign-output-basement")) {
						$floorTarget = (-1 * $floorBasement2);
					}else {
						$floorTarget = $floorSplit[1];
					}
					$last = $this->getLastFloorIndex($player->getLevel(), $pos);
					if($last === false) {
						$this->log("can't find base block");
						return;
					}
					if($floorTarget >= $last) {
						$this->log("max");
						return;
					}else {
						if($last - ($floorTarget + 1) <= 0) {
							$buttonStr = TextFormat::BOLD . TextFormat::DARK_RED . $this->msg("sign-output-up");
						}else {
							$buttonStr = TextFormat::BOLD . TextFormat::BLUE . $this->msg("sign-output-up");
						}
						if($floorTarget + 1 == 0) {
							$floorTargetNext = $this->msg("sign-output-lobby");
						}else if($floorTarget + 1 < 0) {
							$floorTargetNext = $this->msg("sign-output-basement") . abs($floorTarget + 1);
						}else {
							$floorTargetNext = $floorTarget + 1;
						}
						$floorSign->setText($floorTexts[0], $buttonStr, $floorTexts[2], $this->msg("sign-output-target-floor") . " : " . $floorTargetNext);
						$floorSign->saveNBT();
						
						$floorSubSign->setText(TextFormat::BOLD . TextFormat::BLUE . $this->msg("sign-output-down"), "", "", "");
						$floorSubSign->saveNBT();
					}
				}
			}else {
				$this->log("$base type isn't defind");
				return;
			}
		}
	}
	
	
	
	protected function buttonDown($type, $player, $pos, $sign, $event) {
		
		if(!($sign instanceof Sign)) {
			$this->getServer()->getLogger()->alert("'buttonDown' get wrong '$sign' parameter at X:" . $pos->getX() . " Y:" . $pos->getY() . " Z:" . $pos->getZ());
			return;
		}
		
		$texts = $sign->getText();
		$baseBlock = $this->getBaseFloor($player->getLevel(), $pos);
		
		if($baseBlock === false) {
			return;
		}
		
		$base = $baseBlock->getId();
		$sourcePos = new Vector3($pos->getX(), $pos->getY() - 1, $pos->getZ());
		$tempFloor = $this->getFloorIndex($player->getLevel(), $sourcePos);
		
		if($tempFloor === 0) {
			$floor = $this->msg("sign-output-lobby");
		}else if($tempFloor < 0) {
			$floor = $this->msg("sign-output-basement") . abs($tempFloor);
		}else {
			$floor = $tempFloor;
		}
		
		$color = $this->getColor($base);
		$hasPrev = $this->hasPrevFloor($player->getLevel(), new Vector3($pos->getX(), $pos->getY() - 1, $pos->getZ()));
		$prevColor = $hasPrev ? TextFormat::BOLD . TextFormat::BLUE : TextFormat::BOLD . TextFormat::DARK_RED;
		
		//Create Sub Button
		if($texts[0] === "" && $texts[1] === "" && $texts[2] === "" && $texts[3] === "") {
			$pow2 = new Vector3($pos->getX(), $pos->getY()+1, $pos->getZ());
			$sign2 = $player->getLevel()->getTile($pow2);
			if(!($sign2 instanceof Sign)) {
				$this->log("buttonDown->createSubButton->sign2 is not instanceof Sign");
				return;
			}
			$this->log($sign2->getText() == $color . "[" . $this->msg("sign-output-title") . "]" ? "true" : "false");
			if($sign2->getText()[0] == $color . "[" . $this->msg("sign-output-title") . "]") {
				$sign->setText($prevColor . $this->msg("sign-output-down"), "", "", "");
				$sign->saveNBT();
				if($event instanceof Cancellable) {
					if(!($event->isCancelled())) {
						$event->setCancelled();
					}
				}
				return;
			}
		}
		
		//Down Button
		if(mb_substr($texts[0], 4) == $this->msg("sign-output-down")) {
			$this->log("active goDown");
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			
			//Simple Mode
			if($base === self::TYPE_IRON || $base === self::TYPE_GOLD) {
				$pos2 = $this->getPrevFloor($player->getLevel(), $sourcePos);
				if($pos2 === false) {
					$this->log("no PrevFloor");
					return;
				}
				$this->log("elevate " . $pos->getY() . "->" . $pos2->getY());
				$this->moveFloor($player, $base, $sourcePos, $pos2);
				
			//Advance Mode
			}else if($base === self::TYPE_DIAMOND || $base === self::TYPE_EMERALD) {
				$floorSign = $player->getLevel()->getTile(new Vector3($pos->getX(), $pos->getY() + 1, $pos->getZ()));
				$floorSubSign = $sign;
				if(!($floorSign instanceof Sign)) {
					$player->sendTip(TextFormat::RED . $this->msg("warning-no-above-sign"));
					return;
				}
				$floorTexts = $floorSign->getText();
				$floorSubTexts = $floorSubSign->getText();
				$floorStr = $floorTexts[3];
				$floorSplit = explode(" : ", $floorStr);
				if($floorSplit[0] === $this->msg("sign-output-target-floor")) {
					$floorBasement = mb_substr($floorSplit[1], 0, 1);
					$floorBasement2 = mb_substr($floorSplit[1], 1);
					if($floorSplit[1] === $this->msg("sign-output-loby")) {
						$floorTarget = 0;
					}else if($floorBasement[0] === $this->msg("sign-output-basement")) {
						$floorTarget = (-1 * $floorBasement2);
					}else {
						$floorTarget = $floorSplit[1];
					}
					$first = $this->getFirstFloorIndex($player->getLevel(), $pos);
					if($first === false) {
						$this->log("can't find base block");
						return;
					}
					if($floorTarget <= $first) {
						$this->log("min");
						return;
					}else {
						if(($floorTarget - 1) - $first <= 0) {
							$buttonStr = TextFormat::BOLD . TextFormat::DARK_RED . $this->msg("sign-output-down");
						}else {
							$buttonStr = TextFormat::BOLD . TextFormat::BLUE . $this->msg("sign-output-down");
						}
						if($floorTarget - 1 == 0) {
							$floorTargetNext = $this->msg("sign-output-lobby");
						}else if($floorTarget - 1 < 0) {
							$floorTargetNext = $this->msg("sign-output-basement") . abs($floorTarget - 1);
						}else {
							$floorTargetNext = $floorTarget - 1;
						}
						$floorSign->setText($floorTexts[0], TextFormat::BOLD . TextFormat::BLUE . $this->msg("sign-output-up"), $floorTexts[2], $this->msg("sign-output-target-floor") . " : " . $floorTargetNext);
						$floorSign->saveNBT();
						
						$floorSubSign->setText($buttonStr, "", "", "");
						$floorSubSign->saveNBT();
					}
				}
			}else {
				$this->log("$base type isn't defind");
				return;
			}
		}
	}
	
	
	
	protected function advanceMove($player, $pos, $event) {
		
		$sign = $player->getLevel()->getTile(new Vector3($pos->getX(), $pos->getY() + 2, $pos->getZ()));
		
		if(!($sign instanceof Sign)) {
			$this->log("this is not elevator");
			return;
		}
		
		$base = $this->getBaseFloor($player->getLevel(), $pos);
		
		if($base === false) {
			$this->log("can't find base");
			return;
		}
		
		$type = $base->getId();
		
		if(!($type === self::TYPE_DIAMOND || $type === self::TYPE_EMERALD)) {
			$this->log("this is not advance elevator" . $type);
			return;
		}
		
		$texts = $sign->getText();
		
		if(mb_substr($texts[0], 2) == "[" . $this->msg("sign-output-title") . "]") {
			
			$this->log("active advanceMove");
			
			if($event instanceof Cancellable) {
				if(!($event->isCancelled())) {
					$event->setCancelled();
				}
			}
			
			$split = explode(" : ", $texts[3]);
			
			if($split[0] === $this->msg("sign-output-target-floor")) {
				
				$basement = mb_substr($split[1], 0, 1);
				$basement2 = mb_substr($split[1], 1);
				
				if($split[1] === $this->msg("sign-output-lobby")) {
					$floorTarget = 0;
				}else if($basement === $this->msg("sign-output-basement")) {
					$floorTarget = (-1 * $basement2);
				}else {
					$floorTarget = $split[1];
				}
				
				$pos2 = $this->getFloorByIndex($player->getLevel(), $pos, $floorTarget);
				
				if($pos2 === false) {
					$this->log("can't find floor " . $floorTarget);
					return;
				}
				
				$this->moveFloor($player, $type, $pos, $pos2);
				
			}else {
				$player->sendTip(TextFormat::DARK_RED . $this->msg("warning-elevator-crash"));
			}
		}
	}
	
	public function getColor($type) {
		switch($type) {
			
			case self::TYPE_IRON:
				return self::COLOR_IRON;
				break;
				
			case self::TYPE_GOLD:
				return self::COLOR_GOLD;
				break;
				
			case self::TYPE_DIAMOND:
				return self::COLOR_DIAMOND;
				break;
				
			case self::TYPE_EMERALD:
				return self::COLOR_EMERALD;
				break;
				
			default:
				return "";
		}
	}
	
	private function moveFloor($player, $type, $currentPos, $targetPos) {
		$this->log("moveFloor - " . $currentPos->getY() . "->" . $targetPos->getY());
		
		if(isset($this->elevateQueue[$player->getName()])) {
			return;
		}
		
		$blocks = [];
		$range = $targetPos->getY() - $currentPos->getY();
		
		//clearBlockOnTop
		if($range > 0) {
			for($e = 0; $e <= $range; $e++) {
				
				$id = $player->getLevel()->getBlockIdAt($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ());
				
				if($id === self::TYPE_GLASS || $id === self::TYPE_IRON || $id === self::TYPE_GOLD || $id === self::TYPE_DIAMOND || $id === self::TYPE_EMERALD) {
					
					$blocks[] = [
						"x" => $currentPos->getX(),
						"y" => $currentPos->getY() + $e,
						"z" => $currentPos->getZ(),
						"id" => $id
					];
					
					$player->getLevel()->setBlock(new Vector3($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ()), Block::get(0, 0), true, false);
				}
			}
		//clearBlockOnBottom
		}else {
			for($e = 0; $e >= $range; $e--) {
				
				$id = $player->getLevel()->getBlockIdAt($currentPos->getX(), $currentPos->getY() + $e, $currentPos->getZ());
				
				if($id === self::TYPE_GLASS || $id === self::TYPE_IRON || $id === self::TYPE_GOLD || $id === self::TYPE_DIAMOND || $id === self::TYPE_EMERALD) {
					
					$blocks[] = [
						"x" => $currentPos->getX(),
						"y" => $currentPos->getY() + $e,
						"z" => $currentPos->getZ(),
						"id" => $id
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
			self::TICK_BLOCKS => $blocks
		];
	}
	
	//this method will be EXCLUSION
	public function hasNextFloor($level, $pos) {
		$this->log("hasNextFloor");
		for($e = 1; $e <= 128; $e++) {
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() + $e, $pos->getZ());
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				return true;
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	//this method will be EXCLUSION
	public function hasPrevFloor($level, $pos) {
		$this->log("hasPrevFloor");
		for($e = 1; $e <= 128; $e++) {
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() - $e, $pos->getZ());
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				return true;
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	public function getFloorIndex($level, $pos) {
		$this->log("getFloorIndex -");
		
		$floor = 0;
		
		for($e = $pos->getY(); $e >= 0; $e--) {
			
			$id = $level->getBlockIdAt($pos->getX(), $e, $pos->getZ());
			
			if($id === Block::GLASS) {
				
				$floor++;
				
			}else if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $floor;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		
		$floor = 0;
		
		for($e = $pos->getY(); $e <= 128; $e++) {
			
			$id = $level->getBlockIdAt($pos->getX(), $e, $pos->getZ());
			
			if($id === Block::GLASS) {
				
				$floor--;
				
			}else if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $floor;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		
		$this->getServer()->getLogger()->alert("<function 'getFloor'> can't find current floor at Level:" . $level->getName() . " X:" . $pos->getX() . " Y:" . $pos->getY() . " Z:" . $pos->getZ());
		return 0;
	}
	
	public function getBaseFloor($level, $pos) {
		$this->log("getBaseFloor - ");
		
		for($e = 0; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() - $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		
		for($e = 0; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() + $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		return false;
	}
	
	public function getFloorByIndex($level, $pos, $index) {
		$this->log("getFloorByIndex - " . $index);
		
		$base = $this->getBaseFloor($level, $pos);
		
		if($base === false) {
			return false;
		}
		
		if($index == 0) {
			
			return $base;
			
		}else if($index > 0) {
			
			$floor = 0;
			
			for($e = 1; $e <= 128; $e++) {
				
				$block = $level->getBlock(new Vector3($base->getX(), $base->getY() + $e, $base->getZ()));
				$id = $block->getId();
				
				if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
					
					if(++$floor == $index) {
						return $block;
					}
					
				}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
					break;
				}
			}
			return false;
		}else {
			
			$floor = 0;
			
			for($e = 1; $e <= 128; $e++) {
				
				$block = $level->getBlock(new Vector3($base->getX(), $base->getY() - $e, $base->getZ()));
				$id = $block->getId();
				
				if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
					
					if(--$floor == $index) {
						return $block;
					}
					
				}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
					break;
				}
			}
			return false;
		}
	}
	
	public function getNextFloor($level, $pos) {
		$this->log("getNextFloor -");
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() + $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	public function getPrevFloor($level, $pos) {
		$this->log("getPrevFloor -");
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() - $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $block;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return false;
			}
		}
		return false;
	}
	
	public function getFirstFloorIndex($level, $pos) {
		$this->log("getFirstFloorIndex -");
		
		$base = $this->getBaseFloor($level, $pos);
		
		if($base === false) {
			return false;
		}
		
		$pos = $base;
		$floor = 0;
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() - $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				$floor--;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return $floor;
			}
		}
		return $floor;
	}
	
	public function getLastFloorIndex($level, $pos) {
		$this->log("getLastFloorIndex -");
		
		$base = $this->getBaseFloor($level, $pos);
		
		if($base === false) {
			return false;
		}
		
		$pos = $base;
		$floor = 0;
		
		for($e = 1; $e <= 128; $e++) {
			
			$block = $level->getBlock(new Vector3($pos->getX(), $pos->getY() + $e, $pos->getZ()));
			$id = $block->getId();
			
			if($id === Block::GLASS || $id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				$floor++;
				
			}else if(!($id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				return $floor;
			}
		}
		return $floor;
	}
	
	public function getBaseBlockId($level, $pos) {
		$this->log("getBaseBlockId -");
		
		for($e = 0; $e <= 128; $e++) {
			
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() - $e, $pos->getZ());
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $id;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		for($e = 0; $e <= 128; $e++) {
			
			$id = $level->getBlockIdAt($pos->getX(), $pos->getY() + $e, $pos->getZ());
			
			if($id === Block::IRON_BLOCK || $id === Block::GOLD_BLOCK || $id === Block::DIAMOND_BLOCK || $id === Block::EMERALD_BLOCK) {
				
				return $id;
				
			}else if(!($id === Block::GLASS || $id === Block::AIR || $id === Block::WALL_SIGN || $id === Block::SIGN_POST || $id === Block::TORCH || $id === Block::FIRE || $id === Block::SNOW_LAYER || $id === Block::VINE)) {
				break;
			}
		}
		return -1;
	}
	
	public function onTouch(PlayerInteractEvent $event) {
		
		//onTouch
		if($event->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			
			$pos = new Vector3($event->getBlock()->getX(), $event->getBlock()->getY(), $event->getBlock()->getZ());
			
			switch($event->getBlock()->getId()) {
				
				case Block::WALL_SIGN:
					$tile = $event->getPlayer()->getLevel()->getTile($pos);
					if(!($tile instanceof Sign)) {
						return;
					}
					
					$two = $event->getPlayer()->getLevel()->getBlockIdAt($pos->getX(), $pos->getY() - 2, $pos->getZ());
					$one = $event->getPlayer()->getLevel()->getBlockIdAt($pos->getX(), $pos->getY() - 1, $pos->getZ());
					$this->log("touchEvent BlockId 1:" . $one . " 2:" . $two);
			
					if($two === self::TYPE_IRON || $two === self::TYPE_GOLD || $two === self::TYPE_DIAMOND || $two === self::TYPE_EMERALD || $two === self::TYPE_GLASS) {
						$this->log("run buttonUp");
						
						$this->buttonUp($two, $event->getPlayer(), $pos, $tile, $event);
						
					}else if($one === self::TYPE_IRON || $one === self::TYPE_GOLD || $one === self::TYPE_DIAMOND || $one === self::TYPE_EMERALD || $one === self::TYPE_GLASS){
						$this->log("run buttonDown");
						
						$this->buttonDown($one, $event->getPlayer(), $pos, $tile, $event);
					}
					break;
					
				case Block::DIAMOND_BLOCK:
				case Block::EMERALD_BLOCK:
				case Block::GLASS:
					$this->log("run advanceMove");
					
					$this->advanceMove($event->getPlayer(), $pos, $event);
					break;
			}
		}
	}
	
	public function fallenDamagePrevent(EntityDamageEvent $event) {
		
		if($event->getCause() == EntityDamageEvent::CAUSE_FALL) {
			
			if(!$event->getEntity() instanceof Player) {
				return;
			}
			
			if(isset($this->elevateQueue[$event->getEntity()->getName()])) {
				$event->setCancelled();
			}
		}
	}
	
	public function log($str) {
		$this->getServer()->getLogger()->debug($str);
	}
}
?>