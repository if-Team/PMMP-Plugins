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
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\block\BlockBreakEvent;
use hm\PSYCHOPASS\database\AreaPASS_Database;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;

class PSYCHOPASS_AreaPASS extends PluginBase implements Listener {
	/*
	 * @var PSYCHOPASS_AreaPASS
	 */
	private static $instance = null;
	/*
	 * @var make_Queue
	 */
	public $make_Queue = [ ];
	/*
	 * @var Database
	 */
	public $db = [ ];
	/*
	 * @var PSYCHOPASS_API
	*/
	public $api = null;
	public function onEnable() {
		if (! self::$instance instanceof PSYCHOPASS_AreaPASS)
			self::$instance = $this;
		if (! $this->api instanceof PSYCHOPASS_API)
			$this->api = PSYCHOPASS_API::getInstance();
		@mkdir ( $this->getDataFolder () );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()] = AreaPASS_Database ( $this->getDataFolder (), $level->getFolderName () );
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onBreak(BlockBreakEvent $event) {
		if ($event->isCancelled ())
			return;
		if ($event->getPlayer ()->isOp ())
			return;
		$area = $this->db [$event->getBlock ()->getLevel ()->getFolderName ()]->getArea ( $event->getBlock ()->x, $event->getBlock ()->z );
		if ($area != false) {
			if ($area->isProtected ()) {
				if ($area->isOption ( $area ["ID"], $event->getBlock ()->getID () . ":" . $event->getBlock ()->getDamage () ))
					return;
				$event->getPlayer ()->sendMessage ( TextFormat::RED . "[AreaPASS] 이 구역은 지형수정이 금지되어있습니다." );
				$event->setCancelled ();
				return;
			} else {
				if ($area->isOption ( $area ["ID"], $event->getBlock ()->getID () . ":" . $event->getBlock ()->getDamage () )) {
					$event->getPlayer ()->sendMessage ( TextFormat::RED . "[AreaPASS] 이 블록은 사용이 금지되어 있습니다." );
					$event->setCancelled ();
				}
			}
		}
		if ($this->db [$event->getBlock ()->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			$event->getPlayer ()->sendMessage ( TextFormat::RED . "[AreaPASS] 이 월드는 지형수정이 금지되어있습니다." );
			$event->setCancelled ();
			return;
		}
	}
	public function onPlace(BlockPlaceEvent $event) {
		if ($event->isCancelled ())
			return;
		if ($event->getPlayer ()->isOp ())
			return;
		$area = $this->db [$event->getBlock ()->getLevel ()->getFolderName ()]->getArea ( $event->getBlock ()->x, $event->getBlock ()->z );
		if ($area != false) {
			if ($area->isProtected ()) {
				if ($area->isOption ( $area ["ID"], $event->getBlock ()->getID () . ":" . $event->getBlock ()->getDamage () ))
					return;
				$event->getPlayer ()->sendMessage ( TextFormat::RED . "[AreaPASS] 이 구역은 지형수정이 금지되어있습니다." );
				$event->setCancelled ();
				return;
			} else {
				if ($area->isOption ( $area ["ID"], $event->getBlock ()->getID () . ":" . $event->getBlock ()->getDamage () )) {
					$event->getPlayer ()->sendMessage ( TextFormat::RED . "[AreaPASS] 이 블록은 사용이 금지되어 있습니다." );
					$event->setCancelled ();
				}
			}
		}
		if ($this->db [$event->getBlock ()->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			$event->getPlayer ()->sendMessage ( TextFormat::RED . "[AreaPASS] 이 월드는 지형수정이 금지되어있습니다." );
			$event->setCancelled ();
			return;
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		if ($event->isCancelled ())
			return;
		if (isset ( $this->make_Queue [$event->getPlayer ()->getName ()] )) {
			if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] = $event->getBlock ()->getSide ( 0 );
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] pos1이 선택되었습니다." );
			} else if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] = $event->getBlock ()->getSide ( 0 );
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] pos2가 선택되었습니다." );
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 영역을 만드시려면 /AreaPASS make <이름> 을" );
				$event->getPlayer ()->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 작업을 중지하려면 /AreaPASS cancel 을 써주세요." );
			}
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (! strtolower ( $command->getName () ) == "areapass")
			return;
		if (isset ( $args [0] )) {
			switch (strtolower ( $args [0] )) {
				case "whiteworld" :
					if (isset ( $args [1] )) {
						if ($args [1] == "on") {
							$isActivate = true;
						} else if ($args [1] == "off") {
							$isActivate = false;
						}
						if (isset ( $args [2] )) {
							if (isset ( $this->db [$args [2]] )) {
								$target_Level = $args [2];
							} else {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [2] . " 맵을 찾을 수 없습니다." );
								return true;
							}
						} else if ($sender instanceof Player) {
							$target_Level = $sender->getLevel ()->getFolderName ();
						} else {
							$target_Level = $this->getServer ()->getDefaultLevel ()->getFolderName ();
						}
						if (isset ( $isActivate ) and isset ( $target_Level )) {
							$this->db [$target_Level]->setWhiteWorld ( $isActivate );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $target_Level . " 맵을 화이트월드로 설정했습니다." );
							return true;
						}
					}
					break;
				case "make" :
					if (! $sender instanceof Player) {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 인게임 내에서만 가능합니다." );
						return true;
					}
					if (! isset ( $this->make_Queue [$sender->getName ()] )) {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 개별영역 설정을 시작합니다." );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "원하시는 크기만큼 모서리를 각각 터치해주세요." );
						$this->make_Queue [$sender->getName ()] ["pos1"] = false;
						$this->make_Queue [$sender->getName ()] ["pos2"] = false;
						return true;
					} else {
						if (! $this->make_Queue [$sender->getName ()] ["pos1"]) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 첫번쨰 부분이 지정되지않았습니다!" );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 개별영역설정을 중단하려면 (/areapass cancel) !" );
							return true;
						}
						if (! $this->make_Queue [$sender->getName ()] ["pos2"]) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 두번쨰 부분이 지정되지않았습니다!" );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 개별영역설정을 중단하려면 (/areapass cancel) !" );
							return true;
						}
						if (! isset ( $args [1] ) and ! isset ( $this->make_Queue [$sender->getName ()] ["overrap"] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 영역 관리용 이름을 같이 설정해주세요!" );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS make <이름>!" );
							return true;
						}
						if (is_numeric ( $args [1] ) or isset ( $args [2] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 영역이름은 숫자로만 이뤄지거나" );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 공백이 포함될 수 없습니다. (주의해주세요)" );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS make <이름>!" );
							return true;
						}
						$pos = $this->areaPosCast ( $this->make_Queue [$sender->getName ()] ["pos1"], $this->make_Queue [$sender->getName ()] ["pos2"] );
						$checkOverapArea = $this->db [$sender->getLevel ()->getFolderName ()]->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
						if ($checkOverapArea != false) {
							if (! isset ( $this->make_Queue [$sender->getName ()] ["overrap"] )) {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당영역에 중복되는 영역이 감지되었습니다! (" . $checkOverapArea ["name"] . ")" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 겹치는 영역설정들을 삭제하고 이 영역을 생성할까요?" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( 예:/AreaPASS make 아니요: /AreaPASS cancel )" );
								$this->make_Queue [$sender->getName ()] ["overrap"] = true;
								return true;
							} else {
								while ( 1 ) {
									$checkOverapArea = $this->db [$sender->getLevel ()->getFolderName ()]->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
									if ($checkOverapArea == false)
										break;
									$this->db [$sender->getLevel ()->getFolderName ()]->removeAreaById ( $checkOverapArea ["ID"] );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $checkOverapArea ["name"] . " 영역을 삭제했습니다." );
								}
							}
						}
						$check = $this->db [$sender->getLevel ()->getFolderName ()]->addArea ( $args [1], $pos [0], $pos [1], $pos [2], $pos [3], true );
						unset ( $this->make_Queue [$sender->getName ()] );
						if ($check == false) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 처리되지않은 중복영역이 있습니다. <생성실패>" );
							return true;
						} else {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $check ["ID"] . "번, " . $check ["name"] . " 영역을 생성했습니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS]  protect 명령어로 보호여부를 설정해주세요." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS]  /AreaPASS protect <name> <on|off>" );
							return true;
						}
					}
					break;
				case "cancel" :
					if (isset ( $this->make_Queue [$sender->getName ()] )) {
						unset ( $this->make_Queue [$sender->getName ()] );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 설정을 취소했습니다." );
						return true;
					} else {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 진행중인 설정이 없습니다." );
						return true;
					}
					break;
				case "delete" :
					// AreaPASS delete <번호>
					if (isset ( $args [1] )) {
						if (is_numeric ( $args [1] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS delete <name>" );
							return true;
						}
						if ($this->db [$sender->getLevel ()->getFolderName ()]->removeAreaById ( $args [1] ) == true) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [1] . " 번 영역을 삭제했습니다." );
							return true;
						} else {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [1] . " 번 영역은 존재하지 않습니다." );
							return true;
						}
					} else {
						if (! $sender instanceof Player) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS delete <name>" );
							return true;
						}
						$find = $this->db [$sender->getLevel ()->getFolderName ()]->getArea ( $sender->x, $sender->z );
						if ($find != false) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $find ["ID"], "번 " . $find ["name"] . " 영역을 삭제했습니다." );
							$this->db [$sender->getLevel ()->getFolderName ()]->removeAreaById ( $find ["ID"] );
							return true;
						} else {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당위치에서 영역을 찾을 수 없습니다." );
							return true;
						}
					}
					break;
				case "protect" :
					// AreaPASS protect <number|name> <on|off>
					if (isset ( $args [1] ) and isset ( $args [2] )) {
						if (is_numeric ( $args [1] )) {
							$find = $this->db [$sender->getLevel ()->getFolderName ()]->getAreaById ( $args [1] );
							if ($find != false)
								$area = $find;
						} else {
							if (! isset ( $area )) {
								$find = $this->db [$sender->getLevel ()->getFolderName ()]->getAreaByName ( $args [1] );
								if ($find != false)
									$area = $find;
							}
						}
						if (! isset ( $area )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당영역을 찾을 수 없습니다." );
							return true;
						} else {
							if ($args [2] == true) {
								if ($this->db [$sender->getLevel ()->getFolderName ()]->isProtected ()) {
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 이미 해당영역은 지형수정이 금지되어있습니다." );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS allow <name> <id:meta>" );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 로 해당영역에서 특별히 수정가능한 블럭 설정이 가능합니다." );
									return true;
								}
								$this->db [$sender->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], true );
								$this->db [$sender->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당영역에 지형수정을 금지시켰습니다." );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS allow <name> <id:meta>" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 로 해당영역에서 특별히 수정가능한 블럭 설정이 가능합니다." );
								return true;
							} else if ($args [2] == false) {
								if (! $this->db [$sender->getLevel ()->getFolderName ()]->isProtected ()) {
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 이미 해당영역은 지형수정이 허용되어있습니다." );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS allow <name> <id:meta>" );
									$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 로 해당영역에서 특별히 수정불가능한 블럭 설정이 가능합니다." );
									return true;
								}
								$this->db [$sender->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], false );
								$this->db [$sender->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당영역에 지형수정을 허용시켰습니다." );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS forbid <name> <id:meta>" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 로 해당영역에서 특별히 수정불가능한 블럭 설정이 가능합니다." );
								return true;
							}
						}
					}
					break;
				case "allow" :
					// /AreaPASS allow <number|name> <id:meta>
					if (isset ( $args [1] ) and isset ( $args [2] )) {
						if (is_numeric ( $args [1] )) {
							$find = $this->db [$sender->getLevel ()->getFolderName ()]->getAreaById ( $args [1] );
							if ($find != false)
								$area = $find;
						} else {
							if (! isset ( $area )) {
								$find = $this->db [$sender->getLevel ()->getFolderName ()]->getAreaByName ( $args [1] );
								if ($find != false)
									$area = $find;
							}
						}
						if (! isset ( $area )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당영역을 찾을 수 없습니다." );
							return true;
						}
						if (! $this->db [$sender->getLevel ()->getFolderName ()]->isProtected ()) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 이영역은 지형수정이 허용되어있습니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( /AreaPASS forbid <name> <id:meta> 를 사용하세요" );
							return true;
						}
						if (strtolower ( $args [2] ) == "clear") {
							$this->db [$sender->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 수정허용블럭 설정이 초기화 되었습니다." );
							return true;
						}
						$e = explode ( ":", $args [2] );
						if (! is_numeric ( $e [0] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 아이디 값은 숫자만 가능합니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS allow <name> <id:meta>" );
							return true;
						}
						if (! is_numeric ( $e [1] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 메타 값은 숫자만 가능합니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS allow <name> <id:meta>" );
							return true;
						}
						if (isset ( $e [2] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 메타 값은 하나만 가능합니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS allow <name> <id:meta>" );
							return true;
						}
						if (isset ( $area ) and isset ( $block_id )) {
							$check = $this->db [$sender->getLevel ()->getFolderName ()]->addOption ( $area ["ID"], $args [2] );
							if ($check) {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [2] . "를 수정허용블록으로 추가했습니다." );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( /AreaPASS allow <name> <clear> 로" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 수정허용블럭 설정을 초기화 할 수 있습니다. )" );
								return true;
							} else {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [2] . "는 이미 수정허용블록으로 추가되어있습니다." );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( /AreaPASS allow <name> <clear> 로" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 수정허용블럭 설정을 초기화 할 수 있습니다. )" );
								return true;
							}
						}
					}
					break;
				case "forbid" :
					// /AreaPASS forbid <number|name> <id:meta>
					if (isset ( $args [1] ) and isset ( $args [2] )) {
						if (is_numeric ( $args [1] )) {
							$find = $this->db [$sender->getLevel ()->getFolderName ()]->getAreaById ( $args [1] );
							if ($find != false)
								$area = $find;
						} else {
							if (! isset ( $area )) {
								$find = $this->db [$sender->getLevel ()->getFolderName ()]->getAreaByName ( $args [1] );
								if ($find != false)
									$area = $find;
							}
						}
						if (! isset ( $area )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 해당영역을 찾을 수 없습니다." );
							return true;
						}
						if ($this->db [$sender->getLevel ()->getFolderName ()]->isProtected ()) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 이영역은 지형수정이 금지되어있습니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( /AreaPASS allow <name> <id:meta> 를 사용하세요" );
							return true;
						}
						if (strtolower ( $args [2] ) == "clear") {
							$this->db [$sender->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 수정금지블럭 설정이 초기화 되었습니다." );
							return true;
						}
						$e = explode ( ":", $args [2] );
						if (! is_numeric ( $e [0] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 아이디 값은 숫자만 가능합니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS forbid <name> <id:meta>" );
							return true;
						}
						if (! is_numeric ( $e [1] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 메타 값은 숫자만 가능합니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS forbid <name> <id:meta>" );
							return true;
						}
						if (isset ( $e [2] )) {
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 메타 값은 하나만 가능합니다." );
							$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] /AreaPASS forbid <name> <id:meta>" );
							return true;
						}
						if (isset ( $area ) and isset ( $block_id )) {
							$check = $this->db [$sender->getLevel ()->getFolderName ()]->addOption ( $area ["ID"], $args [2] );
							if ($check) {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [2] . "를 수정금지블록으로 추가했습니다." );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( /AreaPASS forbid <name> <clear> 로" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 수정금지블럭 설정을 초기화 할 수 있습니다. )" );
								return true;
							} else {
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] " . $args [2] . "는 이미 수정허용블록으로 추가되어있습니다." );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] ( /AreaPASS forbid <name> <clear> 로" );
								$sender->sendMessage ( TextFormat::DARK_AQUA . "[AreaPASS] 수정금지블럭 설정을 초기화 할 수 있습니다. )" );
								return true;
							}
						}
					}
					break;
			}
		}
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/AreaPASS whiteworld <on|off> <worldname>" );
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/AreaPASS make <name>" );
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/AreaPASS delete <name>" );
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/AreaPASS protect <name> <on|off>" );
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/AreaPASS allow <name> <id:meta>" );
		$sender->sendMessage ( TextFormat::DARK_AQUA . "/AreaPASS forbid <name> <id:meta>" );
		return true;
	}
	public function areaPosCast(Position $pos1, Position $pos2) {
		$startX = ( int ) $pos1->getX ();
		$endX = ( int ) $pos2->getX ();
		$startZ = ( int ) $pos1->getZ ();
		$endZ = ( int ) $pos2->getZ ();
		if ($startX > $endX) {
			$backup = $startX;
			$startX = $endX;
			$endX = $backup;
		}
		if ($startZ > $endZ) {
			$backup = $startZ;
			$startZ = $endZ;
			$endZ = $backup;
		}
		$startX --;
		$endX ++;
		$startZ --;
		$endZ ++;
		return [ 
				$startX,
				$endX,
				$startZ,
				$endZ 
		];
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