<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\PSYCHOPASS;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\IPlayer;
use pocketmine\scheduler\CallbackTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use hm\PSYCHOPASS\Event\CrimeCoefficient\getCrimeCoefficientEvent;
use hm\PSYCHOPASS\Event\CrimeCoefficient\setCrimeCoefficientEvent;
use hm\PSYCHOPASS\Event\CrimeCoefficient\clearCrimeCoefficientEvent;
use hm\PSYCHOPASS\Event\CrimeCoefficient\addCrimeCoefficientEvent;
use hm\PSYCHOPASS\Event\CrimeCoefficient\reduceCrimeCoefficientEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\tile\Sign;

class PSYCHOPASS_API extends PluginBase implements Listener {
	/*
	 * @var PSYCHOPASS_API
	 */
	private static $instance = null;
	/*
	 * @var YML
	 */
	private $crime_con_file, $crime_con;
	/*
	 * @var Queue Array
	 */
	private $placeQueue = [ ];
	public function onEnable() {
		if (! self::$instance instanceof PSYCHOPASS_API)
			self::$instance = $this;
		@mkdir ( $this->getDataFolder () );
		$this->loadCrimeCoefficient ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->initialize_schedule_repeat ( $this, "saveCrimeCoefficient", 2000, [ ] );
	}
	public function onDisable() {
		$this->saveCrimeCoefficient ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->crime_con [$player->getName ()] )) {
			$this->crime_con [$player->getName ()] = [ 
					"crime_coefficient" => 0,
					"color" => "A" 
			];
		}
	}
	public function onSignChange(SignChangeEvent $event) {
		$player = $event->getPlayer ();
		if ($event->isCancelled ())
			return;
		if ($event->getLine ( 0 ) == "색상체크" or $event->getLine ( 0 ) == "colorcheck") {
			$event->setLine ( 0, "[색상체크]" );
			$event->setLine ( 1, "표지판을 터치시" );
			$event->setLine ( 2, "본인의 위험지수와" );
			$event->setLine ( 3, "색상체크가 뜹니다" );
			$player->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS-API] 색상체크 표지판을 생성했습니다" );
		}
	}
	public function onPlayerTouch(PlayerInteractEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		$item = $event->getItem ()->getID ();
		
		if (! ($block->getID () == 323 or $block->getID () == 63 or $block->getID () == 68))
			return;
		$sign = $event->getPlayer ()->getLevel ()->getTile ( $block );
		if (! ($sign instanceof Sign))
			return;
		$sign = $sign->getText ();
		if (! ($sign [0] == "[색상체크]"))
			return;
		$player->sendMessage ( $this->getColorMessage ( $player ) );
		$this->placeQueue [$player->getName ()] = true;
	}
	public function onBlockPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		if ($event->isCancelled ())
			return;
		if (isset ( $this->placeQueue [$player->getName ()] )) {
			$event->setCancelled ();
			unset ( $this->placeQueue [$player->getName ()] );
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if ((strtolower ( $command->getName () ) == "colorcheck") or ($command->getName () == "색상체크")) {
			if (isset ( $args [0] )) {
				if (isset ( $this->crime_con [$args [0]] )) {
					$message = $this->getColorMessage ( $args [0] );
					if ($message == false)
						$message = TextFormat::DARK_AQUA . "[PSYCHOPASS-API] 해당되는 정보를 찾지 못했습니다.";
					$sender->sendMessage ( $message );
					return true;
				} else {
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS-API] 해당되는 정보를 찾지 못했습니다." );
					return true;
				}
			} else {
				if ($sender->getName () == "CONSOLE") {
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS-API] /색상체크 <유저명>" );
					return true;
				}
				if (isset ( $this->crime_con [$sender->getName ()] )) {
					$message = $this->getColorMessage ( $sender );
					if ($message == false)
						$message = TextFormat::DARK_AQUA . "[PSYCHOPASS-API] 해당되는 정보를 찾지 못했습니다.";
					$sender->sendMessage ( $message );
					return true;
				}
			}
		}
		if ((strtolower ( $command->getName () ) == "crime") or ($command->getName () == "범죄계수")) {
			if (isset ( $args [0] )) {
				switch (strtolower ( $args [0] )) {
					case "get" :
					case "얻기" :
						if (isset ( $args [1] )) {
							$target = $sender->getServer ()->getOfflinePlayer ( $args [1] );
							if ($target instanceof IPlayer) {
								if (isset ( $this->crime_con [$target->getName ()] )) {
									$get = $this->getCrimeCoefficient ( $target, $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $get );
								} else {
									$this->setCrimeCoefficient ( $target, 0, $sender );
									$get = $this->getCrimeCoefficient ( $target, $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $get );
								}
							} else {
								if (! $this->setCrimeCoefficient ( $args [1], 0, $sender ))
									$sender->sendMessage ( TextFormat::DARK_AQUA . "'" . $args [1] . "' 해당자를 찾지못했습니다." );
								return true;
							}
							return true;
						}
						break;
					case "set" :
					case "설정" :
						if (isset ( $args [1] ) and isset ( $args [2] ) and is_numeric ( $args [2] )) {
							$target = $sender->getServer ()->getOfflinePlayer ( $args [1] );
							if ($target instanceof IPlayer) {
								if (isset ( $this->crime_con [$target->getName ()] )) {
									$this->setCrimeCoefficient ( $target, $args [2], $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								} else {
									$this->setCrimeCoefficient ( $target, $args [2], $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								}
							} else {
								if (! $this->setCrimeCoefficient ( $args [1], $args [2], $sender ))
									$sender->sendMessage ( TextFormat::DARK_AQUA . "'" . $args [1] . "' 해당자를 찾지못했습니다." );
								return true;
							}
							return true;
						}
						break;
					case "add" :
					case "가산" :
						if (isset ( $args [1] ) and isset ( $args [2] ) and is_numeric ( $args [2] )) {
							$target = $sender->getServer ()->getOfflinePlayer ( $args [1] );
							if ($target instanceof IPlayer) {
								if (isset ( $this->crime_con [$target->getName ()] )) {
									$this->addCrimeCoefficient ( $target, $args [2], $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								} else {
									$this->setCrimeCoefficient ( $target, 0, $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								}
							} else {
								if (! $this->setCrimeCoefficient ( $args [1], $args [2], $sender ))
									$sender->sendMessage ( TextFormat::DARK_AQUA . "'" . $args [1] . "' 해당자를 찾지못했습니다." );
								return true;
							}
							return true;
						}
						break;
					case "reduce" :
					case "감산" :
						if (isset ( $args [1] ) and isset ( $args [2] ) and is_numeric ( $args [2] )) {
							$target = $sender->getServer ()->getOfflinePlayer ( $args [1] );
							if ($target instanceof IPlayer) {
								if (isset ( $this->crime_con [$target->getName ()] )) {
									$this->reduceCrimeCoefficient ( $target, $args [2], $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								} else {
									$this->setCrimeCoefficient ( $target, 0, $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								}
							} else {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "'" . $args [1] . "' 해당자를 찾지못했습니다." );
								return true;
							}
							return true;
						}
						break;
					case "clear" :
					case "초기화" :
						if (isset ( $args [1] )) {
							$target = $sender->getServer ()->getOfflinePlayer ( $args [1] );
							if ($target instanceof IPlayer) {
								if (isset ( $this->crime_con [$target->getName ()] )) {
									$this->clearCrimeCoefficient ( $target, $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								} else {
									$this->clearCrimeCoefficient ( $target, $sender );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "해당자 -" . $target->getName () . " 범죄계수 - " . $args [2] );
								}
							} else {
								if (! $this->setCrimeCoefficient ( $args [1], $args [2], $sender ))
									$sender->sendMessage ( TextFormat::DARK_AQUA . "'" . $args [1] . "' 해당자를 찾지못했습니다." );
								return true;
							}
							return true;
						}
						break;
				}
			}
			$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS] /범죄계수 얻기 <유저명>" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS] /범죄계수 설정 <유저명> <값>" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS] /범죄계수 가산 <유저명> <값>" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS] /범죄계수 감산 <유저명> <값>" );
			$sender->sendMessage ( TextFormat::DARK_AQUA . "[PSYCHOPASS] /범죄계수 초기화 <유저명>" );
			return true;
		}
	}
	public function loadCrimeCoefficient() {
		$this->crime_con_file = $this->initializeYML ( "crime_coefficient.yml", [ ] );
		$this->crime_con = $this->crime_con_file->getAll ();
	}
	public function saveCrimeCoefficient() {
		$this->crime_con_file->setAll ( $this->crime_con );
		$this->crime_con_file->save ();
	}
	public function checkColor($player) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] )) {
			if (isset ( $this->crime_con [$name] ["color"] )) {
				$analyze_color = [ ];
				$final_color;
				$color_array = [ 
						"A",
						"B",
						"C",
						"D",
						"E",
						"F",
						"G" 
				];
				foreach ( $this->crime_con [$name] ["color"] as $getcolor ) {
					foreach ( $color_array as $checkcolor ) {
						if ($getcolor == $checkcolor) {
							if (isset ( $analyze_color [$checkcolor] )) {
								$analyze_color [$checkcolor] ++;
								break;
							} else {
								$analyze_color [$checkcolor] = 1;
								break;
							}
						}
					}
				}
				foreach ( $color_array as $checkcolor ) {
					if (isset ( $analyze_color [$checkcolor] )) {
						if (! isset ( $final_color )) {
							$final_color = $checkcolor . ":" . $analyze_color [$checkcolor];
						} else {
							$e = explode ( ":", $final_color );
							if ($e [1] == $analyze_color [$checkcolor]) {
								$final_color = "A" . ":" . $analyze_color [$checkcolor];
								break;
							}
							if ($e [1] < $analyze_color [$checkcolor]) {
								$final_color = $checkcolor . ":" . $analyze_color [$checkcolor];
								break;
							}
						}
					}
				}
				$e = explode ( ":", $final_color );
				return $e [0];
			} else {
				$this->crime_con [$name] ["color"] = [ 
						"A" 
				];
				return "A";
			}
		} else {
			return false;
		}
	}
	public function getColorMessage($player) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		$checkcolor = $this->checkColor ( $name );
		if (! isset ( $this->crime_con [$name] )) // or $checkcolor == false
			return false;
		$crime_coefficient = $this->crime_con [$name] ["crime_coefficient"];
		$message = TextFormat::WHITE . "색상체크: ";
		switch ($checkcolor) {
			case "A" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::GRAY . "회색-색상 (GRAY-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::DARK_GRAY . "짙은회색-색상 (DARKGRAY-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(흰색계열은 색상이 판별되지않는 유저일때 표시됩니다.)";
				break;
			case "B" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::RED . "적색-색상 (RED-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::DARK_RED . "짙은적색-색상 (DARKRED-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(적색계열은 허용되지않는 공격이 많을 시 표시됩니다.)";
				break;
			case "C" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::YELLOW . "황색-색상 (YELLOW-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::GOLD . "짙은황색-색상 (GOLD-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(황색계열은 허용되지않는 채팅(도배)이 많을 시 표시됩니다.)";
				break;
			case "D" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::GREEN . "녹색-색상 (GREEN-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::DARK_GREEN . "짙은녹색-색상 (DARKGREEN-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(녹색계열은 허용되지않는 채팅(비속어)이 많을 시 표시됩니다.)";
				break;
			case "E" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::AQUA . "청록-색상 (AQUA-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::DARK_AQUA . "짙은청록-색상 (DARKAQUA-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(청록계열은 허용되지않는 블럭설치가 많을 시 표시됩니다.)";
				break;
			case "F" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::BLUE . "남색-색상 (BLUE-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::DARK_BLUE . "짙은남색-색상 (DARKBLUE-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(남색계열은 허용되지않는 블럭파괴가 많을 시 표시됩니다.)";
				break;
			case "G" :
				if ($crime_coefficient < 25)
					$message .= TextFormat::WHITE . "흰색-색상 (CLEAR-COLOR)";
				else if ($crime_coefficient < 50)
					$message .= TextFormat::LIGHT_PURPLE . "자색-색상 (LIGHTPURPLE-COLOR)";
				else if ($crime_coefficient < 100)
					$message .= TextFormat::DARK_PURPLE . "짙은자색-색상 (DARKPURPLE-COLOR)";
				else if ($crime_coefficient >= 100)
					$message .= TextFormat::BLACK . "흑색-색상 (BLACK-COLOR)";
				$color_descripts = "(보라계열은 투표를 통한 밴 이력이 있을 시 표시됩니다.)";
				break;
		}
		$message .= TextFormat::WHITE . " 위험지수: " . $crime_coefficient . "\n";
		$message .= TextFormat::WHITE . "(서버에서 받은 경고 지수를 색상으로 나타냅니다.)\n";
		$message .= TextFormat::WHITE . $color_descripts;
		return $message;
	}
	public function getColorGraph($player) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] ["color"] )) {
			return $this->crime_con [$name] ["color"];
		} else {
			return false;
		}
	}
	public function addColorGraph($player, $color) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		$this->crime_con [$name] ["color"] [] = $color;
		return true;
	}
	public function clearColorGraph($player) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		$this->crime_con [$name] ["color"] = [ 
				"A" 
		];
		return true;
	}
	public function getAllCrimeCoefficient() {
		return $this->crime_con;
	}
	public function getCrimeCoefficient($player, $issuer = null) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] )) {
			$event = new getCrimeCoefficientEvent ( $this, $player, $this->crime_con [$name] ["crime_coefficient"], $issuer );
			$this->getServer ()->getPluginManager ()->callEvent ( $event );
			if ($event->isCancelled ())
				return false;
			return $this->crime_con [$name] ["crime_coefficient"];
		} else {
			return false;
		}
	}
	public function setCrimeCoefficient($player, $crimecoefficient, $issuer = null) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] )) {
			$event = new setCrimeCoefficientEvent ( $this, $player, $this->crime_con [$name] ["crime_coefficient"], $issuer );
			$this->getServer ()->getPluginManager ()->callEvent ( $event );
			if ($event->isCancelled ())
				return false;
			$this->crime_con [$name] ["crime_coefficient"] = $crimecoefficient;
			return true;
		} else {
			return false;
		}
	}
	public function clearCrimeCoefficient($player, $issuer = null) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] )) {
			$event = new clearCrimeCoefficientEvent ( $this, $player, $issuer );
			$this->getServer ()->getPluginManager ()->callEvent ( $event );
			if ($event->isCancelled ())
				return false;
			$this->crime_con [$name] ["crime_coefficient"] = 0;
			return true;
		} else {
			return false;
		}
	}
	public function addCrimeCoefficient($player, $crimecoefficient, $issuer = null) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] )) {
			$event = new addCrimeCoefficientEvent ( $this, $player, $this->crime_con [$name] ["crime_coefficient"], $issuer );
			$this->getServer ()->getPluginManager ()->callEvent ( $event );
			if ($event->isCancelled ())
				return false;
			$this->crime_con [$name] ["crime_coefficient"] += $crimecoefficient;
			if ($this->crime_con [$name] ["crime_coefficient"] < 0)
				$this->crime_con [$name] ["crime_coefficient"] = 0;
			return true;
		} else {
			return false;
		}
	}
	public function reduceCrimeCoefficient($player, $crimecoefficient, $issuer = null) {
		if ($player instanceof IPlayer) {
			$name = $player->getName ();
		} else {
			$name = $player;
		}
		if (isset ( $this->crime_con [$name] )) {
			$event = new reduceCrimeCoefficientEvent ( $this, $player, $this->crime_con [$name] ["crime_coefficient"], $issuer );
			$this->getServer ()->getPluginManager ()->callEvent ( $event );
			if ($event->isCancelled ())
				return false;
			$this->crime_con [$name] ["crime_coefficient"] -= $crimecoefficient;
			if ($this->crime_con [$name] ["crime_coefficient"] < 0)
				$this->crime_con [$name] ["crime_coefficient"] = 0;
			return true;
		} else {
			return false;
		}
	}
	public function initializeYML($path, $array) {
		return new Config ( $this->getDataFolder () . $path, Config::YAML, $array );
	}
	public function initialize_schedule_repeat($class, $method, $second, $param) {
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$class,
				$method 
		], [ 
				$param 
		] ), $second );
	}
	public function initialize_schedule_delay($class, $method, $second, $param) {
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
				$class,
				$method 
		], [ 
				$param 
		] ), $second );
	}
}
?>