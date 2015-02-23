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
use pocketmine\command\PluginCommand;

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
				"hour-tax-price" => 4,
				"default-prefix" => $this->get ( "default-prefix" ),
				"welcome-prefix" => $this->get ( "welcome-prefix" ),
				"default-wall-type" => 139,
				"enable-setarea" => true ] );
		$this->config_Data = $this->config->getAll ();
		
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->db [$level->getFolderName ()] = new SimpleArea_Database ( $this->getServer ()->getDataPath () . "worlds/" . $level->getFolderName () . "/", $level, $this->config_Data ["default-wall-type"] );
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"autoSave" ] ), 2400 );
		
		foreach ( $this->getServer ()->getLevels () as $level )
			$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
					$this,
					"hourTaxCheck" ] ), 20 * 60 * 60 );
		
		$this->registerCommand ( $this->get ( "commands-area" ), "simplearea.commands.area", $this->get ( "commands-area-desc" ) );
		$this->registerCommand ( $this->get ( "commands-setarea" ), "simplearea.commands.setarea", $this->get ( "commands-setarea-desc" ) );
		$this->registerCommand ( $this->get ( "commands-sellarea" ), "simplearea.commands.sellarea", $this->get ( "commands-sellarea-desc" ) );
		$this->registerCommand ( $this->get ( "commands-givearea" ), "simplearea.commands.givearea", $this->get ( "commands-givearea-desc" ) );
		$this->registerCommand ( $this->get ( "commands-buyarea" ), "simplearea.commands.buyarea", $this->get ( "commands-buyarea-desc" ) );
		$this->registerCommand ( $this->get ( "commands-arealist" ), "simplearea.commands.arealist", $this->get ( "commands-arealist-desc" ) );
		$this->registerCommand ( $this->get ( "commands-rent" ), "simplearea.commands.rent", $this->get ( "commands-rent-desc" ) );
		$this->registerCommand ( $this->get ( "commands-invite" ), "simplearea.commands.invite", $this->get ( "commands-invite-desc" ) );
		$this->registerCommand ( $this->get ( "commands-inviteout" ), "simplearea.commands.inviteout", $this->get ( "commands-inviteout-desc" ) );
		$this->registerCommand ( $this->get ( "commands-inviteclear" ), "simplearea.commands.inviteclear", $this->get ( "commands-inviteclear-desc" ) );
		$this->registerCommand ( $this->get ( "commands-invitelist" ), "simplearea.commands.invitelist", $this->get ( "commands-invitelist-desc" ) );
		$this->registerCommand ( $this->get ( "commands-welcome" ), "simplearea.commands.welcome", $this->get ( "commands-welcome-desc" ) );
		$this->registerCommand ( $this->get ( "commands-simplearea" ), "simplearea.commands.simplearea", $this->get ( "commands-simplearea-desc" ) );
		$this->registerCommand ( $this->get ( "commands-sa-yap" ), "simplearea.commands.yap", $this->get ( "commands-yap-desc" ) );
		
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
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
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
		
		if ($player->isOp ()) return;
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] )) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isAllowOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "block-change-denied" ) );
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isForbidOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
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
		
		if ($player->isOp ()) return;
		
		$area = $this->db [$block->getLevel ()->getFolderName ()]->getArea ( $block->x, $block->z );
		
		if ($area != false) {
			if (isset ( $area ["resident"] [0] )) if ($this->db [$block->getLevel ()->getFolderName ()]->checkResident ( $area ["ID"], $player->getName () )) return;
			if ($this->db [$block->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] ) == true) {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isAllowOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) return;
				if ($this->checkShowPreventMessage ()) $this->alert ( $player, $this->get ( "block-change-denied" ) );
				$event->setCancelled ();
				return;
			} else {
				if ($this->db [$block->getLevel ()->getFolderName ()]->isForbidOption ( $area ["ID"], $block->getID () . ":" . $block->getDamage () )) {
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
	}
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer ();
		if (! isset ( $this->player_pos [$player->getName ()] )) {
			$this->player_pos [$player->getName ()] ["x"] = ( int ) round ( $player->x );
			$this->player_pos [$player->getName ()] ["z"] = ( int ) round ( $player->z );
		} else {
			$dif = abs ( ( int ) round ( $player->x - $this->player_pos [$player->getName ()] ["x"] ) );
			$dif += abs ( ( int ) round ( $player->z - $this->player_pos [$player->getName ()] ["z"] ) );
			if ($dif > 5) {
				$this->player_pos [$player->getName ()] ["x"] = ( int ) round ( $player->x );
				$this->player_pos [$player->getName ()] ["z"] = ( int ) round ( $player->z );
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
				if (! isset ( $area ["is-home"] ) or $area ["is-home"] == false) return;
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
								$this->message ( $player, $this->get ( "welcome-area-sir" ) );
							} else {
								if ($this->config_Data ["show-opland-message"] == true) $this->message ( $player, $this->get ( "welcome-area-master" ) );
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
			case $this->get ( "commands-area" ) :
				if (isset ( $args [0] )) {
					$this->goHome ( $player, $args [0] );
				} else {
					$this->printHomeList ( $player );
				}
				break;
			case $this->get ( "commands-setarea" ) :
				if ($this->config_Data ["enable-setarea"] == false) {
					$this->alert ( $player, $this->get ( "cant-use-setarea" ) );
					break;
				}
				if ($this->checkHomeLimit ( $player )) {
					$this->SimpleArea ( $player );
				} else {
					$this->message ( $player, $this->get ( "no-more-buying-area" ) );
				}
				break;
			case $this->get ( "commands-sellarea" ) :
				$this->sellHome ( $player );
				break;
			case $this->get ( "commands-givearea" ) :
				if (isset ( $args [0] )) {
					$this->giveHome ( $player, $args [0] );
				} else {
					$this->giveHome ( $player );
				}
				break;
			case $this->get ( "commands-buyarea" ) :
				$this->buyhome ( $player );
				break;
			case $this->get ( "commands-arealist" ) :
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
					case $this->get ( "commands-sa-arealimit" ) :
						if (isset ( $args [1] )) {
							$this->homelimit ( $player, $args [1] );
						} else {
							$this->homelimit ( $player );
						}
						break;
					case $this->get ( "commands-sa-economy" ) :
						$this->enableEonomy ( $player );
						break;
					case $this->get ( "commands-sa-areaprice" ) :
						if (isset ( $args [1] )) {
							$this->homeprice ( $player, $args [1] );
						} else {
							$this->homeprice ( $player );
						}
						break;
					case $this->get ( "commands-sa-hourtax" ) :
						if (isset ( $args [1] )) {
							$this->setHourTax ( $player, $args [1] );
						} else {
							$this->setHourTax ( $player );
						}
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
					case $this->get ( "commands-sa-setarea" ) :
						$this->IhateSetMake ( $player );
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
	public function IhateSetMake(Player $player) {
		if ($this->config_Data ["enable-setarea"] == true) {
			$this->config_Data ["enable-setarea"] = false;
			$this->message ( $player, $this->get ( "setarea-disabled" ) );
		} else {
			$this->config_Data ["enable-setarea"] = true;
			$this->message ( $player, $this->get ( "setarea-enabled" ) );
		}
	}
	public function setHourTax(Player $player, $tax = null) {
		if ($tax == null or ! is_numeric ( $tax )) {
			$this->message ( $player, $this->get ( "commands-sa-hourtax-help" ) );
			$this->message ( $player, $this->get ( "commands-sa-hourtax-help-1" ) );
			return;
		}
		$this->config_Data ["hour-tax-price"] = $tax;
		$this->message ( $player, $this->get ( "commands-sa-hourtax-complete" ) );
	}
	public function hourTaxCheck() {
		if ($this->config_Data ["hour-tax-price"] <= 0) return;
		foreach ( $this->getServer ()->getLevels () as $level )
			foreach ( $this->db [$level->getFolderName ()]->getAll () as $area )
				if (isset ( $area ["is-home"] ) and $area ["is-home"] == true and $area ["resident"] [0] != null) {
					if ($this->checkEconomyAPI ()) {
						$money = $this->economyAPI->myMoney ( $area ["resident"] [0] );
						if ($money == false) return;
						if ($money >= $this->config_Data ["hour-tax-price"]) {
							$this->economyAPI->reduceMoney ( $area ["resident"] [0], $this->config_Data ["hour-tax-price"] );
							$player = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
							if ($player != null) $this->message ( $player, $this->get ( "hourtax-is-paid-1" ) . $this->config_Data ["hour-tax-price"] . $this->get ( "hourtax-is-paid-2" ) );
						} else {
							$player = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
							if ($player != null) $this->message ( $player, $this->get ( "sequestrated-1" ) . $area ["ID"] . $this->get ( "sequestrated-2" ) );
							$this->db [$level->getFolderName ()]->setResident ( $area ["ID"], [ ] );
						}
					}
				}
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
			$this->alert ( $player, $this->get ( "commands-sa-areaprice-help" ) );
			return false;
		}
		if (! is_numeric ( $price )) {
			$this->alert ( $player, $this->get ( "areaprice-must-numeric" ) );
			return false;
		}
		$this->config_Data ["economy-home-price"] = $price;
		$this->config_Data ["economy-home-reward-price"] = $price / 2;
		$this->message ( $player, $count . $this->get ( "areaprice-changed" ) );
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
			$this->alert ( $player, $this->get ( "commands-sa-arealimit-help" ) );
			return false;
		}
		if (! is_numeric ( $count )) {
			$this->alert ( $player, $this->get ( "arealimit-must-numeric" ) );
			return false;
		}
		$this->config_Data ["maximum-home-limit"] = $count;
		$this->message ( $player, $count . $this->get ( "arealimit-changed" ) );
		return true;
	}
	public function giveHome(Player $player, $target = null) {
		if ($target == null) {
			$this->alert ( $player, $this->get ( "commands-givearea-help" ) );
			return false;
		}
		$target = $this->getServer ()->getPlayerExact ( $target );
		if ($target == null) {
			$this->message ( $player, $this->get ( "target-is-offline" ) );
			$this->message ( $player, $this->get ( "need-to-online-people" ) );
			return false;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area" ) );
			return false;
		}
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
			$this->alert ( $player, $this->get ( "here-is-protect-area" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, $this->get ( "youre-not-owner" ) );
			return false;
		} else {
			if ($area ["resident"] [0] == $target->getName ()) {
				$this->alert ( $player, $this->get ( "already-youre-area" ) );
				return false;
			}
			$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $player->getName (), $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
					$target ] );
			$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $target->getName (), $area ["ID"] );
			if ($this->checkEconomyAPI ()) $this->economyAPI->addMoney ( $player, $this->config_Data ["economy-reward-price"] );
			$this->message ( $player, $target . $this->get ( "givearea-success" ) );
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
	public function homelist(Player $player, $index = 1) {
		$this->message ( $player, $this->get ( "commands-area-generic-help" ) );
		
		$once_print = 20;
		$target = $this->db [$player->getLevel ()->getFolderName ()]->getHomeList ();
		
		$index_count = count ( $target );
		$index_key = array_keys ( $target );
		$full_index = floor ( $index_count / $once_print );
		
		if ($index_count > $full_index * $once_print) $full_index ++;
		
		if ($index <= $full_index) {
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "now-list-show" ) . " ({$index}/{$full_index}) " . $this->get ( "index_count" ) . ": {$index_count}" );
			$message = null;
			for($for_i = $once_print; $for_i >= 1; $for_i --) {
				$now_index = $index * $once_print - $for_i;
				if (! isset ( $index_key [$now_index] )) break;
				$now_key = $index_key [$now_index];
				$message .= TextFormat::DARK_AQUA . "[" . $now_key . $this->get ( "arealist-name" ) . "] ";
			}
			$player->sendMessage ( $message );
		} else {
			$player->sendMessage ( TextFormat::RED . $this->get ( "there-is-no-list" ) );
			return false;
		}
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
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area" ) );
			return false;
		} else {
			if ($area ["resident"] [0] == null) {
				if ($this->checkEconomyAPI ()) {
					$money = $this->economyAPI->myMoney ( $player );
					if ($money < 5000) {
						$this->message ( $player, $this->get ( "buyarea-failed" ) );
						$this->message ( $player, $this->get ( "not-enough-money-to-buyarea-1" ) . ($this->config_Data ["economy-area-price"] - $money) . $this->get ( "not-enough-money-to-buyarea-2" ) );
						return false;
					}
				}
				$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
						$player->getName () ] );
				$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $player->getName (), $area ["ID"] );
				$this->message ( $player, $this->get ( "buyarea-success" ) );
				if ($this->checkEconomyAPI ()) {
					$this->economyAPI->reduceMoney ( $player, $this->config_Data ["economy-home-price"] );
					$this->message ( $player, $this->get ( "buyarea-paid-1" ) . $this->config_Data ["economy-area-price"] . $this->get ( "buyarea-paid-2" ) );
					
					if ($this->config_Data ["hour-tax-price"] > 0) {
						$this->economyAPI->addMoney ( $player, $this->config_Data ["hour-tax-price"] );
						$this->message ( $player, $this->get ( "one-hourtax-received" ) );
					}
				}
			} else {
				$this->alert ( $player, $this->get ( "already-someone-to-buyarea" ) );
				return false;
			}
		}
		return true;
	}
	public function allowBlock(Player $player, $block) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-allowblock" ) );
			return false;
		} else {
			if ($block == "clear") {
				$this->db [$player->getLevel ()->getFolderName ()]->setAllowOption ( $area ["ID"], [ ] );
				$this->message ( $player, $this->get ( "allowblock-list-cleared" ) );
				return true;
			}
			if (isset ( explode ( ":", $block )[1] )) {
				if (! is_numeric ( explode ( ":", $block )[0] )) {
					$this->alert ( $player, $this->get ( "block-id-must-numeric" ) );
					return;
				}
				if (! is_numeric ( explode ( ":", $block )[1] )) {
					$this->alert ( $player, $this->get ( "block-damage-must-numeric" ) );
					return;
				}
			} else {
				$block = $block . ":0";
			}
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addAllowOption ( $area ["ID"], $block );
			if ($check) {
				$this->message ( $player, $this->get ( "allowblock-list-added" ) );
				$this->message ( $player, $this->get ( "allowblock-list-clear-help" ) );
			} else {
				$this->message ( $player, $this->get ( "already-allowblocked" ) );
				$this->message ( $player, $this->get ( "allowblock-list-clear-help" ) );
			}
		}
	}
	public function forbidBlock(Player $player, $block) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-forbidblock" ) );
			return false;
		} else {
			if ($block == "clear") {
				$this->db [$player->getLevel ()->getFolderName ()]->setForbidOption ( $area ["ID"], [ ] );
				$this->message ( $player, $this->get ( "forbidblock-list-cleared" ) );
				return true;
			}
			if (isset ( explode ( ":", $block )[1] )) {
				if (! is_numeric ( explode ( ":", $block )[0] )) {
					$this->alert ( $player, $this->get ( "block-id-must-numeric" ) );
					return;
				}
				if (! is_numeric ( explode ( ":", $block )[1] )) {
					$this->alert ( $player, $this->get ( "block-damage-must-numeric" ) );
					return;
				}
			} else {
				$block = $block . ":0";
			}
			$check = $this->db [$player->getLevel ()->getFolderName ()]->addForbidOption ( $area ["ID"], $block );
			if ($check) {
				$this->message ( $player, $this->get ( "forbidblock-list-added" ) );
				$this->message ( $player, $this->get ( "forbidblock-list-clear-help" ) );
			} else {
				$this->message ( $player, $this->get ( "already-forbidblocked" ) );
				$this->message ( $player, $this->get ( "forbidblock-list-clear-help" ) );
			}
		}
	}
	public function protect(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-protect" ) );
			return false;
		} else {
			if ($this->db [$player->getLevel ()->getFolderName ()]->isProtected ( $area ["ID"] )) {
				$this->db [$player->getLevel ()->getFolderName ()]->setProtected ( $area ["ID"], false );
				$this->message ( $player, $this->get ( "unprotect-complete" ) );
				$this->message ( $player, $this->get ( "forbidblock-help" ) );
			} else {
				$this->db [$player->getLevel ()->getFolderName ()]->setOption ( $area ["ID"], [ ] );
				$this->message ( $player, $this->get ( "protect-complete" ) );
				$this->message ( $player, $this->get ( "allowblock-help" ) );
			}
		}
	}
	public function pvp(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-pvp" ) );
			return false;
		} else {
			if ($this->db [$player->getLevel ()->getFolderName ()]->isPvpAllow ( $area ["ID"] )) {
				$this->db [$player->getLevel ()->getFolderName ()]->setPvpAllow ( $area ["ID"], false );
				$this->message ( $player, $this->get ( "pvp-forbid-complete" ) );
				$this->message ( $player, $this->get ( "pvp-allow-help" ) );
			} else {
				$this->db [$player->getLevel ()->getFolderName ()]->setPvpAllow ( $area ["ID"], true );
				$this->message ( $player, $this->get ( "pvp-allow-complete" ) );
				$this->message ( $player, $this->get ( "pvp-forbid-help" ) );
			}
		}
	}
	public function welcome(Player $player, $text) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-welcome" ) );
			return false;
		} else {
			if ($area ["resident"] [0] != $player->getName () and ! $player->isOp ()) {
				$this->alert ( $player, $this->get ( "here-is-not-your-area" ) );
				return false;
			}
			$this->db [$player->getLevel ()->getFolderName ()]->setWelcome ( $area ["ID"], $text );
			$this->message ( $player, $this->get ( "set-welcome-complete" ) );
		}
	}
	public function sellHome(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			return false;
		}
		if (! $this->db [$player->getLevel ()->getFolderName ()]->isHome ( $area ["ID"] )) {
			$this->alert ( $player, $this->get ( "here-is-protect-area" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName () and ! $player->isOp ()) {
			$this->alert ( $player, $this->get ( "here-is-not-your-area" ) );
			return false;
		} else {
			$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $player->getName (), $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ ] );
			$this->message ( $player, $this->get ( "sellarea-complete" ) );
			if ($this->checkEconomyAPI ()) {
				$this->economyAPI->addMoney ( $player, $this->config_Data ["economy-home-reward-price"] );
				$this->message ( $player, $this->get ( "sellarea-price-award-1" ) . $this->config_Data ["economy-home-reward-price"] . $this->get ( "sellarea-price-award-2" ) );
			}
		}
	}
	public function goHome(Player $player, $home_number) {
		if (! is_numeric ( $home_number )) {
			if (isset ( explode ( "*", $home_number )[1] )) {
				$generic_number = explode ( "*", $home_number )[1];
				if (! is_numeric ( $generic_number )) {
					$this->alert ( $player, $this->get ( "area-number-must-numeric" ) );
					return false;
				}
				$area = $this->db [$player->getLevel ()->getFolderName ()]->getAreaById ( $generic_number );
				if ($area == false) {
					$this->alert ( $player, $this->get ( "area-number-doesent-exist" ) );
					return false;
				}
				$x = (($area ["startX"]) + 1);
				$z = (($area ["startZ"]) + 1);
				$y = ($player->getLevel ()->getHighestBlockAt ( $x, $z ) + 2);
				$player->teleport ( new Vector3 ( $x, $y, $z ) );
				return true;
			}
			$this->alert ( $player, $this->get ( "area-number-must-numeric" ) );
			return false;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getUserHome ( $player->getName (), $home_number );
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getAreaById ( $area );
		if ($area == false) {
			$this->alert ( $player, $this->get ( "area-number-doesent-exist" ) );
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
			$this->alert ( $player, $this->get ( "you-dont-have-area" ) );
			return false;
		}
		$this->message ( $player, $this->get ( "show-your-area-list" ) );
		foreach ( $homes as $index => $home ) {
			$this->message ( $player, "[ " . $index . $this->get ( "arealist-name" ) . " ]" );
		}
		return true;
	}
	public function helpPage(Player $player, $pageNumber = 1) {
		$this->message ( $player, $this->get ( "help-page-intro" ) . " (" . $pageNumber . "/2) *" );
		if ($pageNumber == 1) {
			$this->message ( $player, $this->get ( "help-page-white" ), "" );
			$this->message ( $player, $this->get ( "help-page-make" ), "" );
			$this->message ( $player, $this->get ( "help-page-delete" ), "" );
			$this->message ( $player, $this->get ( "help-page-protect" ), "" );
			$this->message ( $player, $this->get ( "help-page-allow" ), "" );
			$this->message ( $player, $this->get ( "help-page-forbid" ), "" );
			$this->message ( $player, $this->get ( "help-page-cutpage1" ), "" );
		} else {
			$this->message ( $player, $this->get ( "help-page-arealimit" ), "" );
			$this->message ( $player, $this->get ( "help-page-setarea" ), "" );
			$this->message ( $player, $this->get ( "help-page-economy" ), "" );
			$this->message ( $player, $this->get ( "help-page-areaprice" ), "" );
			$this->message ( $player, $this->get ( "help-page-hourtax" ), "" );
			$this->message ( $player, $this->get ( "help-page-fence" ), "" );
			$this->message ( $player, $this->get ( "help-page-message" ), "" );
			$this->message ( $player, $this->get ( "help-page-cutpage2" ), "" );
		}
	}
	public function invite(Player $player, $invited) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-invite" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, $this->get ( "here-is-not-your-area" ) );
			return false;
		} else {
			if ($area ["resident"] [0] == $invited) {
				$this->alert ( $player, $this->get ( "cannot-invite-self" ) );
				return false;
			}
			foreach ( $area ["resident"] as $resident ) {
				if ($invited == $resident) {
					$this->alert ( $player, $resident . $this->get ( "is-already-invited" ) );
					$this->message ( $player, $this->get ( "inviteclear-help" ) );
					return false;
				}
			}
			$invite = $this->getServer ()->getPlayerExact ( $invited );
			
			if ($invite != null) {
				$this->db [$player->getLevel ()->getFolderName ()]->addResident ( $area ["ID"], $invite->getName () );
				$this->db [$player->getLevel ()->getFolderName ()]->addUserProperty ( $invite->getName (), $area ["ID"] );
				
				$this->message ( $player, $this->get ( "invite-success-1" ) . $invited . $this->get ( "invite-success-2" ) );
				$this->message ( $player, $this->get ( "inviteclear-help" ) );
				$this->message ( $player, $this->get ( "invitelist-help" ) );
				
				$this->message ( $invite, $area ["ID"] . $this->get ( "invited-success-1" ) . $player->getName () . $this->get ( "invited-success-2" ) );
				$this->message ( $invite, $this->get ( "inviteout-help" ) );
				$this->message ( $invite, $this->get ( "invitelist-help" ) );
			} else {
				$this->alert ( $player, $this->get ( "target-is-offline" ) );
			}
		}
		return true;
	}
	public function inviteout(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-inviteout" ) );
			return false;
		}
		if ($area ["resident"] [0] == $player->getName ()) {
			$this->alert ( $player, $this->get ( "youre-owner" ) );
			return false;
		} else {
			foreach ( $area ["resident"] as $index => $resident ) {
				if ($player->getName () == $resident) {
					$this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $resident, $area ["ID"] );
					$this->db [$player->getLevel ()->getFolderName ()]->removeResident ( $area ["ID"], $resident );
					$this->message ( $player, $this->get ( "inviteout-complete" ) );
					
					$owner = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
					if ($owner != null) $this->message ( $owner, $area ["ID"] . $this->get ( "inviteout-success-1" ) . $player->getName () . $this->get ( "inviteout-success-2" ) );
					return true;
				}
			}
			$this->alert ( $player, $this->get ( "your-not-invited" ) );
			return false;
		}
	}
	public function invitelist(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-invitelist" ) );
			return false;
		} else {
			$residents = null;
			foreach ( $area ["resident"] as $index => $resident )
				$residents .= "[{$index}]" . $resident . " ";
			$this->message ( $player, $this->get ( "print-invite-list" ) . "\n{$residents}" );
			return true;
		}
	}
	public function printInviteList(CommandSender $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-invitelist" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, $this->get ( "here-is-not-your-area" ) );
			return false;
		} else {
			$this->message ( $player, $this->get ( "print-invited-list" ) . "(" . count () . ")" );
		}
		return true;
	}
	public function inviteclear(Player $player) {
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == null) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			$this->alert ( $player, $this->get ( "need-area-to-clear" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			$this->alert ( $player, $this->get ( "here-is-not-your-area" ) );
			return false;
		} else {
			foreach ( $area ["resident"] as $res )
				if ($res != $player->getName ()) $this->db [$player->getLevel ()->getFolderName ()]->removeUserProperty ( $res, $area ["ID"] );
			$this->db [$player->getLevel ()->getFolderName ()]->setResident ( $area ["ID"], [ 
					$player->getName () ] );
			$this->message ( $player, $this->get ( "inviteclear-complete" ) );
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
				$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
				return false;
			}
			if ($area ["resident"] [0] == $player->getName ()) {
				if (isset ( $this->rent_Queue [$player->getName ()] )) {
					$money = $this->economyAPI->myMoney ( $this->rent_Queue [$player->getName ()] ["buyer"] );
					if ($money < $price) {
						$this->alert ( $this->rent_Queue [$player->getName ()] ["buyer"], $this->get ( "rent-failed-not-enough-money" ) );
						$this->alert ( $player, $this->get ( "owner-rent-failed-not-enough-money" ) );
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
					$this->message ( $player, $id . $this->get ( "rent-owner-rent-complete" ) );
					$this->message ( $buyer, $id . $this->get ( "rent-buyer-rent-complete" ) );
					
					unset ( $this->rent_Queue [$player->getName ()] );
					return true;
				}
				if ($area ["rent-allow"] == true) {
					$this->db [$player->getLevel ()->getFolderName ()]->setRentAllow ( $area ["ID"], false );
					$this->message ( $player, $this->get ( "rent-all-request-denied" ) );
					$this->message ( $player, $this->get ( "rent-enable-help" ) );
				} else {
					$this->db [$player->getLevel ()->getFolderName ()]->setRentAllow ( $area ["ID"], true );
					$this->message ( $player, $this->get ( "rent-all-request-approved" ) );
					$this->message ( $player, $this->get ( "rent-disable-help" ) );
				}
				return false;
			}
			foreach ( $area ["resident"] as $resident ) {
				if ($resident == $player->getName ()) {
					$this->alert ( $player, $this->get ( "already-rented" ) );
					return false;
				}
			}
			if ($price == null) {
				$this->message ( $player, $this->get ( "commands-rent-help" ) );
				$this->message ( $player, $this->get ( "rent-help-1" ) );
				$this->message ( $player, $this->get ( "rent-help-2" ) );
				return false;
			} else {
				if (! is_numeric ( $price )) {
					$this->alert ( $player, $this->get ( "rent-price-must-be-number" ) );
					return false;
				} else {
					if ($area ["rent-allow"] == false) {
						$this->message ( $player, $this->get ( "rent-request-denied" ) );
						$this->message ( $player, $area ["resident"] [0] . $this->get ( "rent-owner-check" ) );
						return false;
					}
					$money = $this->economyAPI->myMoney ( $player );
					if ($money < $price) {
						$this->alert ( $player, $this->get ( "not-enough-rent-price" ) );
						return false;
					}
					$owner = $this->getServer ()->getPlayerExact ( $area ["resident"] [0] );
					if ($owner == null) {
						$this->message ( $player, $this->get ( "owner-is-offline" ) );
						$this->message ( $player, $area ["resident"] [0] . $this->get ( "please-run-owner-is-online" ) );
						return false;
					}
					if (isset ( $this->rent_Queue [$owner->getName ()] )) {
						$this->alert ( $player, $this->get ( "too-many-request-here" ) );
						$this->alert ( $player, $this->get ( "please-try-rent-10sec-later" ) );
						return false;
					}
					$this->message ( $owner, $player->getName () . $this->get ( "rent-request-received-1" ) . $area ["ID"] . $this->get ( "rent-request-received-2" ) );
					$this->message ( $owner, $this->get ( "rent-request-received-3" ) . $price . $this->get ( "rent-request-received-4" ) );
					$this->message ( $owner, $this->get ( "rent-please-dicision-10sec" ) );
					$this->rent_Queue [$owner->getName ()] ["ID"] = $area ["ID"];
					$this->rent_Queue [$owner->getName ()] ["buyer"] = $player;
					$this->rent_Queue [$owner->getName ()] ["price"] = $price;
					$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
							$this,
							"rentTimeout" ], [ 
							$owner,
							$player ] ), 200 );
					$this->message ( $player, $this->get ( "rent-request-sent" ) );
					return true;
				}
			}
		} else {
			$this->alert ( $player, $this->get ( "economy-is-disabled" ) );
		}
	}
	public function rentTimeout(Player $owner, CommandSender $buyer) {
		if (isset ( $this->rent_Queue [$owner->getName ()] )) {
			$this->alert ( $this->rent_Queue [$owner->getName ()] ["buyer"], $this->get ( "rent-request-denied-buyer" ) );
			$this->alert ( $owner, $this->get ( "rent-request-denied-owner" ) );
			unset ( $this->rent_Queue [$owner->getName ()] );
		}
	}
	public function deleteHome(Player $player) {
		if (isset ( $this->delete_Queue [$player->getName ()] )) {
			$this->db [$player->getLevel ()->getFolderName ()]->removeAreaById ( $this->delete_Queue [$player->getName ()] ["ID"] );
			$this->message ( $player, $this->get ( "delete-area-success" ) );
			unset ( $this->delete_Queue [$player->getName ()] );
			return true;
		}
		$area = $this->db [$player->getLevel ()->getFolderName ()]->getArea ( $player->x, $player->z );
		if ($area == false) {
			$this->alert ( $player, $this->get ( "area-doesent-exist" ) );
			return false;
		}
		if ($area ["resident"] [0] != $player->getName ()) {
			if (! $player->isOp ()) {
				$this->alert ( $player, $this->get ( "youre-not-owner" ) );
				return false;
			} else {
				$this->delete_Queue [$player->getName ()] = $area;
				if ($area ["resident"] [0] != null) $this->message ( $player, $area ["resident"] [0] . $this->get ( "do-you-want-delete-his-area" ) );
				if ($area ["resident"] [0] == null) $this->message ( $player, $this->get ( "do-you-want-delete-non-owned-area" ) );
				$this->message ( $player, $this->get ( "if-you-want-to-delete-please-command-1" ) );
				$this->message ( $player, $this->get ( "if-you-want-to-delete-please-command-2" ) );
			}
		} else {
			$this->delete_Queue [$player->getName ()] = $area;
			$this->message ( $player, $this->get ( "doyou-want-delete-your-area-1" ) );
			$this->message ( $player, $this->get ( "doyou-want-delete-your-area-2" ) );
			$this->message ( $player, $this->get ( "doyou-want-delete-your-area-3" ) );
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
				$this->message ( $player, $this->get ( "buyarea-failed" ) );
				$this->message ( $player, $this->get ( "you-need-more-money-1" ) . ($this->config_Data ["economy-home-price"] - $money) . $this->get ( "you-need-more-money-2" ) );
				return false;
			}
		}
		
		$area_id = $this->db [$player->level->getFolderName ()]->addArea ( $player->getName (), $startX, $endX, $startZ, $endZ, true );
		
		if ($area_id == false) {
			$this->message ( $player, $this->get ( "failed-buyarea-area-is-overlap" ) );
		} else {
			$this->message ( $player, $this->get ( "buyarea-success" ) );
			if ($this->checkEconomyAPI ()) {
				$this->economyAPI->reduceMoney ( $player, $this->config_Data ["economy-home-price"] );
				$this->message ( $player, $this->get ( "buyarea-paid-1" ) . $this->config_Data ["economy-home-price"] . $this->get ( "buyarea-paid-2" ) );
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
			$this->message ( $player, $this->get ( "failed-buyarea-area-is-overlap" ) );
		} else {
			$this->message ( $player, $this->get ( "areaset-success" ) );
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
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->config_Data ["default-prefix"];
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>
