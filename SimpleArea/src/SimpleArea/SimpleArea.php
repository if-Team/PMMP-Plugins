<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace SimpleArea;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\block\Block;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\level\LevelUnloadEvent;

class SimpleArea extends PluginBase implements Listener {
	private static $instance = null;
	public $config, $config_Data;
	public $db = [ ];
	public $make_Queue = [ ];
	public $delete_Queue = [ ];
	public $rent_Queue = [ ];
	public $player_pos = [ ];
	public $checkMove = [ ];
	public $economyAPI = null;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		if (self::$instance == null) self::$instance = $this;
		
		$this->initMessage ();
		
		$this->config = new Config ( $this->getDataFolder () . "settings.yml", Config::YAML, [ 
				"default-home-size" => 20,
				"maximum-home-limit" => 1,
				"show-prevent-message" => true,
				"show-opland-message" => true,
				"economy-enable" => true,
				"economy-home-price" => 5000,
				"economy-home-reward-price" => 2500,
				"default-prefix" => $this->get ( "default-prefix" ),
				"welcome-prefix" => $this->get ( "welcome-prefix" ),
				"default-wall-type" => 139 ] );
		$this->config_Data = $this->config->getAll ();
		
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()] = new SimpleArea_Database ( $this->getServer ()->getDataPath () . "worlds/" . $level->getFolderName () . "/", $level, $this->config_Data ["default-wall-type"] );
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"autoSave" ] ), 2400 );
		
		if ($this->checkEconomyAPI ()) $this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->config_Data );
		$this->config->save ();
		$this->autoSave ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function autoSave() {
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()]->save ();
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function onLevelLoad(LevelLoadEvent $event) {
		$level = $event->getLevel ();
		$this->db [$level->getFolderName ()] = new SimpleArea_Database ( $this->getServer ()->getDataPath () . "worlds/" . $level->getFolderName () . "/", $level, $this->config_Data ["default-wall-type"] );
	}
	public function onLevelUnload(LevelUnloadEvent $event) {
		$this->db [$event->getLevel ()->getFolderName ()]->save ();
	}
	public function onPlace(BlockPlaceEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] )) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "block-change-denied" ) );
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
					if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "block-active-denied" ) );
					$event->setCancelled ();
				}
			}
		} else {
			if ($this->db [$block->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "whiteworld-change-denied" ) );
				$event->setCancelled ();
				return;
			}
		}
	}
	public function onBreak(BlockBreakEvent $event) {
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if (isset ( $area ["resident"] [0] )) if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] ) == true) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "block-change-denied" ) );
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
					if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "block-active-denied" ) );
					$event->setCancelled ();
				}
			}
			return;
		}
		if ($this->db [$block->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "whiteworld-change-denied" ) );
			$event->setCancelled ();
			return;
		}
	}
	public function onTouch(PlayerInteractEvent $event) {
		if (isset ( $this->make_Queue [$event->getPlayer ()->getName ()] )) {
			if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos1"] = $event->getBlock ()->getSide ( 0 );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos1" ) );
				return;
			} else if ($this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] == false) {
				$event->setCancelled ();
				$this->make_Queue [$event->getPlayer ()->getName ()] ["pos2"] = $event->getBlock ()->getSide ( 0 );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos2" ) );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos-msg1" ) );
				$this->message ( $event->getPlayer (), $this->get ( "complete-pos-msg2" ) );
				return;
			}
		}
		
		$player = $event->getPlayer ();
		$block = $event->getBlock ();
		
		if ($block->getId () == Block::SIGN_POST or $block->getId () == Block::WALL_SIGN) return;
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if (isset ( $area ["resident"] [0] )) if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] ) == true) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
					$event->setCancelled ();
				}
			}
			return;
		}
		if ($this->db [$block->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			$event->setCancelled ();
			return;
		}
	}
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->player_pos [$player->getName ()] )) {
			$this->player_pos [$player->getName ()] ["x"] = ( int ) round ( $player->x );
			$this->player_pos [$player->getName ()] ["z"] = ( int ) round ( $player->z );
		} else {
			$dif = abs ( ( int ) round ( $player->x - $this->player_pos [$player->getName ()] ["x"] ) );
			$dif += abs ( ( int ) round ( $player->z - $this->player_pos [$player->getName ()] ["z"] ) );
			if ($dif > 3) {
				$this->player_pos [$player->getName ()] ["x"] = ( int ) round ( $player->x );
				$this->player_pos [$player->getName ()] ["z"] = ( int ) round ( $player->z );
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if ($area != null) {
					if (! isset ( $this->checkMove [$event->getPlayer ()->getName ()] )) {
						$this->checkMove [$event->getPlayer ()->getName ()] = $area ["ID"];
					} else {
						if ($this->checkMove [$event->getPlayer ()->getName ()] == $area ["ID"]) return;
					}
					if (isset ( $area ["resident"] [0] )) {
						if ($this->getServer ()->getOfflinePlayer ( $area ["resident"] [0] ) == null) return;
						if ($area ["resident"] [0] == $player->getName ()) {
							if ($this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
								$this->message ( $player, $this->get ( "welcome-home-sir" ) );
							} else {
								if ($this->config_Data ["show-opland-message"] == true) $this->message ( $player, $this->get ( "welcome-home-master" ) );
							}
							$welcome = $this->db [$player->getLevel ()->getFolderName ()]->getWelcome ( $area ["ID"] );
							if ($welcome != null) {
								$this->message ( $player, $welcome, $this->config_Data ["welcome-prefix"] );
							} else {
								$this->message ( $player, $this->get ( "please-set-to-welcome-msg" ) );
							}
							return;
						}
						if ($this->getServer ()->getOfflinePlayer ( $area ["resident"] [0] )->isOp ()) {
							if ($this->config_Data ["show-opland-message"] == true) $this->message ( $player, $this->get ( "here-is-op-land" ) . $area ["resident"] [0] );
						} else {
							$this->message ( $player, $this->get ( "here-is" ) . $area ["resident"] [0] . $this->get ( "his-land" ) );
						}
						$welcome = $this->db [$player->getLevel ()->getFolderName ()]->getWelcome ( $area ["ID"] );
						if ($welcome != null) $this->message ( $player, $welcome, $this->config_Data ["welcome-prefix"] );
					} else {
						$this->message ( $player, $this->get ( "you-can-buy-here" ) . $this->config_Data ["economy-home-price"] . " " . $this->get ( "show-buy-command" ) );
					}
					return;
				} else {
					if (isset ( $this->checkMove [$event->getPlayer ()->getName ()] )) unset ( $this->checkMove [$event->getPlayer ()->getName ()] );
					return;
				}
			}
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent) {
			if ($event->getEntity () instanceof Player) {
				$player = $event->getEntity ();
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if ($area != null) if (! $this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) $event->setCancelled ();
			}
			if ($event->getDamager () instanceof Player) {
				$player = $event->getDamager ();
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if ($area != null) if (! $this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) {
					$this->message ( $player, $this->get ( "here-is-pvp-not-allow" ) );
					$event->setCancelled ();
				}
			}
		}
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! $player instanceof Player) {
			$this->alert ( $player, $this->get ( "only-in-game" ) );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "commands-home" ) :
				if (isset ( $args [0] )) {
					$this->goHome ( $player, $args [0] );
				} else {
					$this->printHomeList ( $player );
				}
				break;
			case $this->get ( "commands-sethome" ) :
				if ($this->checkHomeLimit ( $player )) {
					$this->SimpleArea ( $player );
				} else {
					$this->message ( $player, $this->get ( "no-more-buying-home" ) );
				}
				break;
			case $this->get ( "commands-sellhome" ) :
				$this->sellHome ( $player );
				break;
			case $this->get ( "commands-givehome" ) :
				if (isset ( $args [0] )) {
					$this->giveHome ( $player, $args [0] );
				} else {
					$this->giveHome ( $player );
				}
				break;
			case $this->get ( "commands-buyhome" ) :
				$this->buyhome ( $player );
				break;
			case $this->get ( "commands-homelist" ) :
				$this->homelist ( $player );
				break;
			case $this->get ( "commands-rent" ) :
				if (isset ( $args [0] )) {
					$this->rent ( $player, $args [0] );
				} else {
					$this->rent ( $player );
				}
				break;
			case $this->get ( "commands-invite" ) :
				if (isset ( $args [0] )) {
					$this->invite ( $player, $args [0] );
				} else {
					$this->message ( $player, $this->get ( "commands-invite-help" ) );
				}
				break;
			case $this->get ( "commands-inviteout" ) :
				$this->inviteout ( $player );
				break;
			case $this->get ( "commands-inviteclear" ) :
				$this->inviteclear ( $player );
				break;
			case $this->get ( "commands-invitelist" ) :
				$this->invitelist ( $player );
				break;
			case $this->get ( "commands-welcome" ) :
				if (isset ( $args [0] )) {
					$this->welcome ( $player, implode ( " ", $args ) );
				} else {
					$this->message ( $player, $this->get ( "commands-welcome-help" ) );
				}
				break;
			case $this->get ( "commands-sa-yap" ) :
				$this->autoAreaSet ( $player );
				break;
			case $this->get ( "commands-simplearea" ) :
				if (! isset ( $args [0] )) {
					$this->helpPage ( $player );
					return true;
				}
				switch (strtolower ( $args [0] )) {
					case $this->get ( "commands-sa-whiteworld" ) :
						$this->whiteWorld ( $player );
						break;
					case $this->get ( "commands-sa-make" ) :
						$this->protectArea ( $player );
						break;
					case $this->get ( "commands-sa-cancel" ) :
						if (isset ( $this->make_Queue [$player->getName ()] )) {
							unset ( $this->make_Queue [$player->getName ()] );
							$this->message ( $player, $this->get ( "commands-sa-cancel-help" ) );
							return true;
						} else {
							$this->alert ( $player, $this->get ( "commands-sa-cancel-fail" ) );
							return true;
						}
						break;
					case $this->get ( "commands-sa-delete" ) :
						$this->deleteHome ( $player );
						break;
					case $this->get ( "commands-sa-protect" ) :
						$this->protect ( $player );
						break;
					case $this->get ( "commands-sa-pvp" ) :
						$this->pvp ( $player );
						break;
					case $this->get ( "commands-sa-allow" ) :
						if (isset ( $args [1] )) {
							$this->allowBlock ( $player, $args [1] );
						} else {
							$this->alert ( $player, $this->get ( "commands-sa-allow-help" ) );
						}
						break;
					case $this->get ( "commands-sa-forbid" ) :
						if (isset ( $args [1] )) {
							$this->forbidBlock ( $player, $args [1] );
						} else {
							$this->alert ( $player, $this->get ( "commands-sa-forbid-help" ) );
						}
						break;
					case $this->get ( "commands-sa-homelimit" ) :
						if (isset ( $args [1] )) {
							$this->homelimit ( $player, $args [1] );
						} else {
							$this->homelimit ( $player );
						}
						break;
					case $this->get ( "commands-sa-economy" ) :
						$this->enableEonomy ( $player );
						break;
					case $this->get ( "commands-sa-homeprice" ) :
						if (isset ( $args [1] )) {
							$this->homeprice ( $player, $args [1] );
						} else {
							$this->homeprice ( $player );
						}
						break;
					case $this->get ( "commands-sa-landtax" ) :
						// TODO 토지세 기능 활성화
						// TODO 토지세 가격 설정
						break;
					case $this->get ( "commands-sa-fence" ) :
						if (isset ( $args [1] )) {
							$this->setFenceType ( $player, $args [1] );
						} else {
							$this->setFenceType ( $player );
						}
						break;
					
					case $this->get ( "commands-sa-message" ) :
						$this->IhatePreventMessage ( $player );
						break;
					case $this->get ( "commands-sa-help" ) :
						if (isset ( $args [1] )) {
							$this->helpPage ( $player, $args [1] );
						} else {
							$this->helpPage ( $player );
						}
						break;
					default :
						$this->helpPage ( $player );
						break;
				}
				break;
		}
		return true;
	}
	public function setFenceType(Player $player, $fenceType = null) {
		if ($fenceType == null) {
			$this->message ( $player, $this->get ( "commands-sa-fence-help" ) );
		}
		if (! is_numeric ( $fenceType )) {
			$this->alert ( $player, $this->get ( "fence-id-must-numeric" ) );
			return false;
		}
		$this->config_Data ["default-wall-type"] = $fenceType;
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()]->changeWall ( $fenceType );
		$this->message ( $player, $fenceType . $this->get ( "fence-id-changed" ) );
	}
	public function IhatePreventMessage(Player $player) {
		if ($this->config_Data ["show-prevent-message"] == true) {
			$this->config_Data ["show-prevent-message"] = false;
			$this->message ( $player, $this->get ( "prevent-message-disabled" ) );
		} else {
			$this->config_Data ["show-prevent-message"] = true;
			$this->message ( $player, $this->get ( "prevent-message-enabled" ) );
		}
	}
	public function homeprice(Player $player, $price = null) {
		if ($price == null) {
			$this->alert ( $player, $this->get ( "commands-sa-homeprice-help" ) );
			return false;
		}
		if (! is_numeric ( $price )) {
			$this->alert ( $player, $this->get ( "homeprice-must-numeric" ) );
			return false;
		}
		$this->config_Data ["economy-home-price"] = $price;
		$this->config_Data ["economy-home-reward-price"] = $price / 2;
		$this->message ( $player, $count . $this->get ( "homeprice-changed" ) );
		return true;
	}
	public function enableEonomy(Player $player) {
		if ($this->config_Data ["economy-enable"] == true) {
			$this->config_Data ["economy-enable"] = false;
			$this->message ( $player, $this->get ( "economy-enabled" ) );
		} else {
			$this->config_Data ["economy-enable"] = true;
			$this->message ( $player, $this->get ( "economy-disabled" ) );
		}
	}
	public function homelimit(Player $player, $count = null) {
		if ($count == null) {
			$this->alert ( $player, $this->get ( "commands-sa-homelimit-help" ) );
			return false;
		}
		if (! is_numeric ( $count )) {
			$this->alert ( $player, $this->get ( "homelimit-must-numeric" ) );
			return false;
		}
		$this->config_Data ["maximum-home-limit"] = $count;
		$this->message ( $player, $count . $this->get ( "homelimit-changed" ) );
		return true;
	}
	public function giveHome(Player $player, $target = null) {
		if ($target == null) {
			$this->alert ( $player, $this->get ( "commands-givehome-help" ) );
			return false;
		}
		$target = $this->getServer ()->getPlayerExact ( $target );
		if ($target == null) {
			$this->message ( $player, $this->get ( "givehome-target-is-offline" ) );
			$this->message ( $player, $this->get ( "givehome-need-to-online-people" ) );
			return false;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "givehome-home-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "givehome-need-home" ) );
			return false;
		}
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
			$this->alert ( $player, $this->get ( "givehome-here-is-protect-area" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, $this->get ( "givehome-youre-not-owner" ) );
			return false;
		} else {
			if ($area ["resident"] [0] == $target->getName ()) {
				$this->alert ( $player, $this->get ( "givehome-already-youre-home" ) );
				return false;
			}
			$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $player->getName (), $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
					$target ] );
			$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $target->getName (), $area ["ID"] );
			if ($this->checkEconomyAPI ()) $this->economyAPI->addMoney ( $player, $this->config_Data ["economy-reward-price"] );
			$this->message ( $player, $target . $this->get ( "givehome-success" ) );
		}
		return true;
	}
	public function protectArea(Player $player) {
		if (! isset ( $this->make_Queue [$player->getName ()] )) {
			$this->message ( $player, $this->get ( "protect-sequence-start" ) );
			$this->message ( $player, $this->get ( "protect-please-set-pos" ) );
			$this->make_Queue [$player->getName ()] ["pos1"] = false;
			$this->make_Queue [$player->getName ()] ["pos2"] = false;
			return true;
		} else {
			if (! $this->make_Queue [$player->getName ()] ["pos1"]) {
				$this->message ( $player, $this->get ( "protect-please-set-pos1" ) );
				$this->message ( $player, $this->get ( "protect-if-you-stop-protect-use-cancel" ) );
				return true;
			}
			if (! $this->make_Queue [$player->getName ()] ["pos2"]) {
				$this->message ( $player, $this->get ( "protect-please-set-pos2" ) );
				$this->message ( $player, $this->get ( "protect-if-you-stop-protect-use-cancel" ) );
				return true;
			}
			$pos = $this->areaPosCast ( $this->make_Queue [$player->getName ()] ["pos1"], $this->make_Queue [$player->getName ()] ["pos2"] );
			$checkOverapArea = $this->db [$player->getLevel ()->getFolderName ()]->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
			if ($checkOverapArea != false) {
				if (! isset ( $this->make_Queue [$player->getName ()] ["overrap"] )) {
					$this->message ( $player, $this->get ( "protect-overlap-area-exist" ) . " ( ID: " . $checkOverapArea ["ID"] . ")" );
					$this->message ( $player, $this->get ( "have-you-need-overlap-clear" ) );
					$this->message ( $player, $this->get ( "protect-sa-make-or-sa-cancel" ) );
					$this->make_Queue [$player->getName ()] ["overrap"] = true;
					return true;
				} else {
					while ( 1 ) {
						$checkOverapArea = $this->db [$player->getLevel ()->getFolderName ()]->checkOverlap ( $pos [0], $pos [1], $pos [2], $pos [3] );
						if ($checkOverapArea == false) break;
						$this->db [$player->getLevel ()->getFolderName ()]->removeAreaById ( $checkOverapArea ["ID"] );
						$this->message ( $player, $checkOverapArea ["ID"] . $this->get ( "protect-sa-overlap-area-deleted" ) );
					}
				}
			}
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addArea ( $player->getName (), $pos [0], $pos [1], $pos [2], $pos [3] );
			unset ( $this->make_Queue [$player->getName ()] );
			if ($check == false) {
				$this->message ( $player, $this->get ( "protect-failed" ) );
				return true;
			} else {
				$this->message ( $player, $check . $this->get ( "protect-area-created" ) );
				$this->message ( $player, $this->get ( "protect-sa-protect-possible" ) );
				return true;
			}
		}
	}
	public function homelist(Player $player) {
		// TODO 출력방식
		$this->message ( $player, "/home *집번호 로 해당 집으로 워프가능" );
		$this->message ( $player, "/buyhome 집번호 로 해당 집 구매가능" );
		// TODO /home *번호
		// TODO /buyhome 번호
		// TODO /home *번호 퍼미션
	}
	public function whiteWorld(Player $player) {
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isWhiteWorld ()) {
			$this->db [$player->getLevel ()->getFolderName ()]->setWhiteWorld ( true );
			$this->message ( $player, $player->getLevel ()->getFolderName () . $this->get ( "whiteworld-enabled" ) );
		} else {
			$this->db [$player->getLevel ()->getFolderName ()]->setWhiteWorld ( false );
			$this->message ( $player, $player->getLevel ()->getFolderName () . $this->get ( "whiteworld-disabled" ) );
		}
		return true;
	}
	public function buyHome(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 영역을 찾을 수 없습니다." );
			$this->alert ( $player, "영역 안에서만 집구매 명령 사용이 가능 !" );
			return false;
		} else {
			if ($area ["resident"] [0] == null) {
				if ($this->checkEconomyAPI ()) {
					$money = $this->economyAPI->myMoney ( $player );
					if ($money < 5000) {
						$this->message ( $player, "집을 구매하는데 실패했습니다 !" );
						$this->message ( $player, "( 집 구매가격 " . ($this->config_Data ["economy-home-price"] - $money) . "$ 가 더 필요합니다 !" );
						return false;
					}
				}
				$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
						$player->getName () ] );
				$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $player->getName (), $area ["ID"] );
				$this->message ( $player, "성공적으로 집을 구매했습니다 !" );
				if ($this->checkEconomyAPI ()) {
					$this->economyAPI->reduceMoney ( $player, $this->config_Data ["economy-home-price"] );
					$this->message ( $player, "( 집 구매가격 " . $this->config_Data ["economy-home-price"] . "$ 가 지불 되었습니다 !" );
				}
			} else {
				$this->alert ( $player, "해당 집엔 이미 소유자가 있습니다. 구매불가 !" );
				return false;
			}
		}
		return true;
	}
	public function allowBlock(Player $player, $block) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 영역을 찾을 수 없습니다." );
			$this->alert ( $player, "영역 안에서만 수정허용 블럭 설정이 가능 !" );
			return false;
		} else {
			if ($block == "clear") {
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "수정허용 블럭 설정을 초기화했습니다 !" );
				return true;
			}
			if (isset ( explode ( ":", $block )[1] )) {
				if (! is_numeric ( explode ( ":", $block )[0] )) {
					$this->alert ( $player, "블럭 아이디 값은 숫자만 가능합니다 !" );
					return;
				}
				if (! is_numeric ( explode ( ":", $block )[1] )) {
					$this->alert ( $player, "블럭 데미지 값은 숫자만 가능합니다 !" );
					return;
				}
			} else {
				$block = $block . ":0";
			}
			
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addOption ( $area ["ID"], $block );
			if ($check) {
				$this->message ( $player, "수정허용 블럭 설정을 추가했습니다 !" );
				$this->message ( $player, "( /sa allow clear 명령어로 설정 초기화가 가능합니다 !" );
			} else {
				$this->message ( $player, "해당 블럭은 이미 수정허용 되어있습니다 !" );
				$this->message ( $player, "( /sa allow clear 명령어로 설정 초기화가 가능합니다 !" );
			}
		}
	}
	public function forbidBlock(Player $player, $block) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 영역을 찾을 수 없습니다." );
			$this->alert ( $player, "영역 안에서만 수정금지 블럭 설정이 가능 !" );
			return false;
		} else {
			if ($block == "clear") {
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "수정금지 블럭 설정을 초기화했습니다 !" );
				return true;
			}
			if (isset ( explode ( ":", $block )[1] )) {
				if (! is_numeric ( explode ( ":", $block )[0] )) {
					$this->alert ( $player, "블럭 아이디 값은 숫자만 가능합니다 !" );
					return;
				}
				if (! is_numeric ( explode ( ":", $block )[1] )) {
					$this->alert ( $player, "블럭 데미지 값은 숫자만 가능합니다 !" );
					return;
				}
			} else {
				$block = $block . ":0";
			}
			
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addOption ( $area ["ID"], $block );
			if ($check) {
				$this->message ( $player, "수정금지 블럭 설정을 추가했습니다 !" );
				$this->message ( $player, "( /sa forbid clear 명령어로 설정 초기화가 가능합니다 !" );
			} else {
				$this->message ( $player, "해당 블럭은 이미 수정금지 되어있습니다 !" );
				$this->message ( $player, "( /sa forbid clear 명령어로 설정 초기화가 가능합니다 !" );
			}
		}
	}
	public function protect(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 영역을 찾을 수 없습니다." );
			$this->alert ( $player, "영역 안에서만 지형수정 허용유무 설정이 가능 !" );
			return false;
		} else {
			if ($this->db [$player->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] )) {
				$this->db [$player->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], false );
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "지형수정 허용 설정이 완료되었습니다 !" );
				$this->message ( $player, "( / sa forbid 블럭아이디 - 별도로 금지할 블럭설정 가능 )" );
			} else {
				$this->db [$player->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], true );
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, "지형수정 비허용 설정이 완료되었습니다 !" );
				$this->message ( $player, "( / sa allow 블럭아이디 - 별도로 허용할 블럭설정 가능 )" );
			}
		}
	}
	public function pvp(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 영역을 찾을 수 없습니다." );
			$this->alert ( $player, "영역 안에서만 PVP 허용/비허용 설정이가능합니다." );
			return false;
		} else {
			if ($this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) {
				$this->db [$player->getLevel ()->getFolderName ()]->setPvpAllow ( $area ["ID"], false );
				$this->message ( $player, "PVP 비허용 설정이 완료되었습니다!" );
				$this->message ( $player, "( /sa pvp 다시 입력시 허용설정 가능 )" );
			} else {
				$this->db [$player->getLevel ()->getFolderName ()]->setPvpAllow ( $area ["ID"], true );
				$this->message ( $player, "PVP 허용 설정이 완료되었습니다!" );
				$this->message ( $player, "( /sa pvp 다시 입력시 비허용설정 가능 )" );
			}
		}
	}
	public function welcome(Player $player, $text) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 환영메시지 설정이가능합니다." );
			return false;
		} else {
			if ($area ["resident"] [0] != $player->getName () and ! $player->isOp ()) {
				$this->alert ( $player, "본인의 땅이 아닙니다. 환영메시지 설정 불가능." );
				return false;
			}
			$this->db [$player->getLevel ()->getFolderName ()]->setWelcome ( $area ["ID"], $text );
			$this->message ( $player, "환영메시지 설정이 완료되었습니다!" );
		}
	}
	public function sellHome(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 판매 명령어 사용이 가능합니다." );
			return false;
		}
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
			$this->alert ( $player, "이 영역은 집이 아닌 보호구역입니다. 판매 불가능." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName () and ! $player->isOp ()) {
			$this->alert ( $player, "본인의 땅이 아닙니다. 판매 불가능." );
			return false;
		} else {
			$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $player->getName (), $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ ] );
			$this->message ( $player, "해당 집을 판매처리 했습니다 !" );
			if ($this->checkEconomyAPI ()) {
				$this->economyAPI->addMoney ( $player, $this->config_Data ["economy-home-reward-price"] );
				$this->message ( $player, "보상금액 : " . $this->config_Data ["economy-home-reward-price"] . "$ 이 지급되었습니다 !" );
			}
		}
	}
	public function goHome(Player $player, $home_number) {
		if (! is_numeric ( $home_number )) {
			$this->alert ( $player, "집번호는 숫자만 가능합니다 !" );
			return false;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getUserHome ( $player->getName (), $home_number );
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getAreaById ( $area );
		if ($area == false) {
			$this->alert ( $player, "해당 번호의 집은 존재하지않습니다" );
			return false;
		}
		$x = (($area ["startX"]) + 1);
		$z = (($area ["startZ"]) + 1);
		$y = ($player->getLevel ()->getHighestBlockAt ( $x, $z ) + 2);
		$player->teleport ( new Vector3 ( $x, $y, $z ) );
		return true;
	}
	public function printHomeList(Player $player) {
		$homes = $this->db [$player->getLevel ()->getFolderName ()]->getUserHomes ( $player->getName () );
		if ($homes == false) {
			$this->alert ( $player, "영역을 소유하고 있지 않습니다 !" );
			return false;
		}
		$this->message ( $player, "보유 중인 집리스트를 출력합니다. (집번호로 워프)" );
		foreach ( $homes as $index => $home ) {
			$this->message ( $player, $index . "번 " );
		}
		return true;
	}
	public function helpPage(Player $player, $pageNumber = 1) {
		$this->message ( $player, "* 심플오스 설명을 출력합니다 (" . $pageNumber . "/2) *" );
		if ($pageNumber == 1) {
			$this->message ( $player, "/sa whiteworld - 화이트월드 설정", "" );
			$this->message ( $player, "/sa make - 별도 영역보호 설정", "" );
			$this->message ( $player, "/sa delete - 영역보호-홈 삭제", "" );
			$this->message ( $player, "/sa protect - 영역 지형보호여부 설정", "" );
			$this->message ( $player, "/sa allow - 수정 허용시킬 블럭 설정", "" );
			$this->message ( $player, "/sa forbid - 수정 금지시킬 블럭 설정", "" );
			$this->message ( $player, "( /sa help 1|2 - 설명문을 출력합니다 ) " );
		} else {
			$this->message ( $player, "/sa homelimit - 영역보유한계 설정", "" );
			$this->message ( $player, "/sa economy - 이코노미 활성화 설정", "" );
			$this->message ( $player, "/sa homeprice - 집가격 설정", "" );
			$this->message ( $player, "/sa landtax - 토지세 설정", "" );
			$this->message ( $player, "/sa fence - 자동울타리관련 설정", "" );
			$this->message ( $player, "/sa message - 금지메시지표시 설정", "" );
			$this->message ( $player, "( /sa help 1|2 - 설명문을 출력합니다 ) " );
		}
	}
	public function invite(Player $player, $invited) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 공유 명령어 사용이가능합니다." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, "본인의 땅이 아닙니다. 초대 불가능." );
			return false;
		} else {
			if ($area ["resident"] [0] == $invited) {
				$this->alert ( $player, "자기자신에게 집을 공유할 수 없습니다 !" );
				return false;
			}
			foreach ( $area ["resident"] as $resident ) {
				if ($invited == $resident) {
					$this->alert ( $player, $resident . "님은 이미 이집을 공유 받았습니다 !" );
					$this->message ( $player, "( /inviteclear 로 모든 공유 해제가능 )" );
					return false;
				}
			}
			$invite = $this->getServer ()->getPlayerExact ( $invited );
			
			if ($invite != null) {
				$this->db [$player->getLevel ()->getFolderName ()]->addResident ( $area ["ID"], $invite->getName () );
				$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $invite->getName (), $area ["ID"] );
				
				$this->message ( $player, "이 집을 " . $invited . "님과 공유했습니다." );
				$this->message ( $player, "( /inviteclear 로 모든 공유 해제가능 )" );
				$this->message ( $player, "( /invitelist 로 이 집의 공유 내역 확인가능 )" );
				
				$this->message ( $invite, $area ["ID"] . "번 집을 " . $player->getName () . "님이 공유했습니다 !" );
				$this->message ( $invite, "( /inviteout 으로 받은 초대를 해제할 수 있습니다 ! )" );
				$this->message ( $invite, "( /invitelist 로 이 집의 공유 내역 확인가능 )" );
			} else {
				$this->alert ( $player, "해당 유저가 오프라인 입니다 ! ( 초대불가능 )" );
			}
		}
		return true;
	}
	public function inviteout(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 초대해제 명령어 사용이가능합니다." );
			return false;
		}
		if ($area ["resident"] [0] == $player->getName ()) {
			$this->alert ( $player, "본인의 땅입니다, 초대해제 불가능 !" );
			return false;
		} else {
			foreach ( $area ["resident"] as $index => $resident ) {
				if ($player->getName () == $resident) {
					$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $resident, $area ["ID"] );
					$this->db [$player->getLevel ()->getFolderName ()]->removeResident ( $area ["ID"], $resident );
					$this->message ( $player, "정상적으로 초대를 해제했습니다 !" );
					
					$owner = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
					if ($owner != null) $this->message ( $owner, "{$area ["ID"]}번 집에서 {$player->getName()}님이 공유를 해제했습니다" );
					return true;
				}
			}
			$this->alert ( $player, "이 집에 초대받은 이력이 없습니다 ! ( 초대해제 불가능 ! )" );
			return false;
		}
	}
	public function invitelist(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 초대리스트 명령어 사용이가능합니다." );
			return false;
		} else {
			$residents = null;
			foreach ( $area ["resident"] as $index => $resident )
				$residents .= "[{$index}]" . $resident . " ";
			$this->message ( $player, "이 집을 공유 받고 있는 유저를 출력합니다 !\n{$residents}" );
			return true;
		}
	}
	public function printInviteList(CommandSender $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 공유확인 명령어 사용이가능합니다." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, "본인의 땅이 아닙니다. 초대확인 불가능." );
			return false;
		} else {
			$this->message ( $player, "이집을 공유 중인 유저를 출력합니다.(" . count () . ")" );
		}
		return true;
	}
	public function inviteclear(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, "현재 위치에서 집을 찾을 수 없습니다." );
			$this->alert ( $player, "집 안에서만 공유해제 명령어 사용이가능합니다." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, "본인의 땅이 아닙니다. 초대삭제 불가능." );
			return false;
		} else {
			foreach ( $area ["resident"] as $res )
				if ($res != $player->getName ()) $this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $res, $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
					$player->getName () ] );
			$this->message ( $player, "이 집의 공유허용을 모두 해제했습니다." );
		}
		
		return true;
	}
	public function areaPosCast(Position $pos1, Position $pos2) {
		$startX = ( int ) $pos1->getX ();
		$startZ = ( int ) $pos1->getZ ();
		$endX = ( int ) $pos2->getX ();
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
		return [ 
				$startX,
				$endX,
				$startZ,
				$endZ ];
	}
	public function rent(Player $player, $price = null) {
		if ($this->checkEconomyEnable () and $this->checkEconomyAPI ()) {
			$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
			if ($area == false) {
				$this->alert ( $player, "영역을 찾을 수 없습니다." );
				return false;
			}
			if ($area ["resident"] [0] == $player->getName ()) {
				if (isset ( $this->rent_Queue [$player->getName ()] )) {
					$money = $this->economyAPI->myMoney ( $this->rent_Queue [$player->getName ()] ["buyer"] );
					if ($money < $price) {
						$this->alert ( $this->rent_Queue [$player->getName ()] ["buyer"], "지불하려는 임대비가 부족합니다 ! 임대신청 실패 !" );
						$this->alert ( $player, "임대 신청자의 돈이 부족해져서 임대신청이 취소되었습니다" );
						unset ( $this->rent_Queue [$player->getName ()] );
						return false;
					}
					
					$id = &$this->rent_Queue [$player->getName ()] ["ID"];
					$buyer = &$this->rent_Queue [$player->getName ()] ["buyer"];
					$price = &$this->rent_Queue [$player->getName ()] ["price"];
					
					$this->economyAPI->reduceMoney ( $this->rent_Queue [$player->getName ()] ["buyer"], $price );
					$this->economyAPI->addMoney ( $player, $price );
					
					$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $buyer->getName (), $id );
					$this->db [$player->getLevel ()->getFolderName ()]->addResident ( $id, $buyer->getName () );
					$this->message ( $player, "{$id}번 영역을 정상적으로 임대 했습니다 !" );
					$this->message ( $buyer, "{$id}번 영역을 정상적으로 임대 받았습니다 !" );
					
					unset ( $this->rent_Queue [$player->getName ()] );
					return true;
				}
				if ($area ["rent-allow"] == true) {
					$this->db [$player->getLevel ()->getFolderName ()]->setRentAllow ( $area ["ID"], false );
					$this->message ( $player, "이 집에 오는 임대요청을 받지 않게 처리했습니다 !" );
					$this->message ( $player, "( /rent 를 다시한번 쓰면 활성화가능 ! )" );
				} else {
					$this->db [$player->getLevel ()->getFolderName ()]->setRentAllow ( $area ["ID"], true );
					$this->message ( $player, "이 집에 오는 임대요청을 받게끔 처리했습니다 !" );
					$this->message ( $player, "( /rent 를 다시한번 쓰면 비활성화가능 ! )" );
				}
				return false;
			}
			foreach ( $area ["resident"] as $resident ) {
				if ($resident == $player->getName ()) {
					$this->alert ( $player, "이미 해당 영역을 공유받고 있습니다 ! 렌트불가능 !" );
					return false;
				}
			}
			if ($price == null) {
				$this->message ( $player, "/rent <지불할 가격> - 한번만 지불" );
				$this->message ( $player, "요청 시 집주인이 승낙/거절을 하게되며" );
				$this->message ( $player, "10초안에 승낙할 시 구매가 완료 됩니다." );
				return false;
			} else {
				if (! is_numeric ( $price )) {
					$this->alert ( $player, "임대비는 숫자로만 입력 가능합니다 !" );
					return false;
				} else {
					if ($area ["rent-allow"] == false) {
						$this->message ( $player, "이 집의 소유자가 해당 집의 임대요청을 받지않습니다 !" );
						$this->message ( $player, $area ["resident"] [0] . "님에게 임대허용을 요청해보세요 !" );
						return false;
					}
					$money = $this->economyAPI->myMoney ( $player );
					if ($money < $price) {
						$this->alert ( $player, "지불하려는 임대비가 부족합니다 ! 임대신청 실패 !" );
						return false;
					}
					$owner = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
					if ($owner == null) {
						$this->message ( $player, "집주인이 오프라인 상태입니다 ! 구매불가능 !" );
						$this->message ( $player, $area ["resident"] [0] . "님이 로그인 하면 다시시도해보세요 !" );
						return false;
					}
					if (isset ( $this->rent_Queue [$owner->getName ()] )) {
						$this->alert ( $player, "이미 집주인이 다른 임대신청을 받고 있습니다 !" );
						$this->alert ( $player, "10초 후에 다시 임대신청 시도 해주세요!" );
						return false;
					}
					$this->message ( $owner, $player->getName () . "님이 " . $area ["ID"] . "번 땅을 임대받길 원합니다 !" );
					$this->message ( $owner, "임대비로 " . $price . "$ 를 지불 예정이며, 허용시 지급됩니다 !" );
					$this->message ( $owner, "( 10초 안으로 /rent 명령어를 쓰시면 허용처리됩니다. )" );
					$this->rent_Queue [$owner->getName ()] ["ID"] = $area ["ID"];
					$this->rent_Queue [$owner->getName ()] ["buyer"] = $player;
					$this->rent_Queue [$owner->getName ()] ["price"] = $price;
					$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
							$this,
							"rentTimeout" ], [ 
							$owner,
							$player ] ), 200 );
					$this->message ( $player, "집주인에게 해당 요청을 보냈습니다 !" );
					return true;
				}
			}
		} else {
			$this->alert ( $player, "이코노미가 비활성화 되어있습니다 ( 사용불가 )" );
		}
	}
	public function rentTimeout(Player $owner, CommandSender $buyer) {
		if (isset ( $this->rent_Queue [$owner->getName ()] )) {
			$this->alert ( $this->rent_Queue [$owner->getName ()] ["buyer"], "잡주인이 판매를 원하지않습니다 ! ( 10초 타임아웃 )" );
			$this->alert ( $owner, "임대요청을 자동으로 거절했습니다 ! (10초 타임아웃)" );
			unset ( $this->rent_Queue [$owner->getName ()] );
		}
	}
	public function deleteHome(Player $player) {
		if (isset ( $this->delete_Queue [$player->getName ()] )) {
			$this->db [$player->getLevel ()->getFolderName ()]->removeAreaById ( $this->delete_Queue [$player->getName ()] ["ID"] );
			$this->message ( $player, "영역 삭제를 완료했습니다!" );
			unset ( $this->delete_Queue [$player->getName ()] );
			return true;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == false) {
			$this->alert ( $player, "영역을 찾을 수 없습니다." );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			if (! $player->isOp ()) {
				$this->alert ( $player, "영역 소유주가 아닙니다, 삭제불가능." );
				return false;
			} else {
				$this->delete_Queue [$player->getName ()] = $area;
				if ($area ["resident"] [0] != null) $this->message ( $player, $area ["resident"] [0] . "님 영역을 삭제하시겠습니까?." );
				if ($area ["resident"] [0] == null) $this->message ( $player, "소유주가 없는 영역을 삭제하시겠습니까?." );
				$this->message ( $player, "맞을경우 다시한번 명령어를," );
				$this->message ( $player, "아닐 경우 /sa cancel을 써주세요." );
			}
		} else {
			$this->delete_Queue [$player->getName ()] = $area;
			$this->message ( $player, "본인 영역을 삭제하시겠습니까 ?" );
			$this->message ( $player, "맞을경우 다시한번 명령어를," );
			$this->message ( $player, "아닐 경우 /sa cancel을 써주세요." );
		}
		return true;
	}
	public function SimpleArea(Player $player) {
		$size = ( int ) round ( $this->getHomeSize () / 2 );
		$startX = ( int ) round ( $player->x - $size );
		$endX = ( int ) round ( $player->x + $size );
		$startZ = ( int ) round ( $player->z - $size );
		$endZ = ( int ) round ( $player->z + $size );
		
		if ($this->checkEconomyAPI ()) {
			$money = $this->economyAPI->myMoney ( $player );
			if ($money < 5000) {
				$this->message ( $player, "집을 구매하는데 실패했습니다 !" );
				$this->message ( $player, "( 집 구매가격 " . ($this->config_Data ["economy-home-price"] - $money) . "$ 가 더 필요합니다 !" );
				return false;
			}
		}
		
		$area_id = $this->db [$player->level->getFolderName ()]->addArea ( $player->getName (), $startX, $endX, $startZ, $endZ, true );
		
		if ($area_id == false) {
			$this->message ( $player, "다른 유저의 영역과 겹칩니다, 설정불가 !" );
		} else {
			$this->message ( $player, "성공적으로 집을 구매했습니다 !" );
			if ($this->checkEconomyAPI ()) {
				$this->economyAPI->reduceMoney ( $player, $this->config_Data ["economy-home-price"] );
				$this->message ( $player, "집 구매가격 " . $this->config_Data ["economy-home-price"] . "$ 가 지불 되었습니다 !" );
			}
		}
	}
	public function autoAreaSet(Player $player) {
		$size = ( int ) round ( $this->getHomeSize () / 2 );
		$startX = ( int ) round ( $player->x - $size );
		$endX = ( int ) round ( $player->x + $size );
		$startZ = ( int ) round ( $player->z - $size );
		$endZ = ( int ) round ( $player->z + $size );
		
		$area_id = $this->db [$player->level->getFolderName ()]->addArea ( null, $startX, $endX, $startZ, $endZ, true );
		
		if ($area_id == false) {
			$this->message ( $player, "다른 유저의 영역과 겹칩니다, 설정불가 !" );
		} else {
			$this->message ( $player, "성공적으로 집을 생성했습니다 !" );
		}
	}
	public function getHomeSize() {
		return $this->config_Data ["default-home-size"];
	}
	public function checkShowPreventMessage() {
		return ( bool ) $this->config_Data ["show-prevent-message"];
	}
	public function checkEconomyEnable() {
		return ( bool ) $this->config_Data ["economy-enable"];
	}
	public function checkEconomyAPI() {
		return (($this->getServer ()->getLoader ()->findClass ( 'onebone\\economyapi\\EconomyAPI' )) == null) ? false : true;
	}
	public function checkHomeLimit(Player $player) {
		if ($this->config_Data ["maximum-home-limit"] == 0 or $player->isOp ()) return true;
		if (! $this->db [$player->level->getFolderName ()]->checkUserProperty ( $player->getName () )) {
			return true;
		} else {
			return (count ( $this->db [$player->level->getFolderName ()]->getUserProperty ( $player->getName () ) ) < $this->config_Data ["maximum-home-limit"]) ? true : false;
		}
	}
	public function message(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(Player $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>
