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
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\IPlayer;

class PSYCHOPASS_Dominator extends PluginBase implements Listener {
	/*
	 * @var PSYCHOPASS_Dominator
	 */
	private static $instance = null;
	/*
	 * @var YML
	 */
	public $log_ban, $log_kick, $log_ipban, $log_subban, $log_pardon;
	/*
	 * @var Ban Data
	 */
	public $ban_data, $kick_data, $ipban_data, $subban_data, $pardon_data;
	/*
	 * @var on/off line data
	 */
	public $onlinelist = [ ];
	public $offlinelist = [ ];
	public $offline_iplist = [ ];
	/*
	 * @var Language Data
	 */
	public $language;
	public function onEnable() {
		if (! self::$instance instanceof PSYCHOPASS_Dominator) self::$instance = $this;
		@mkdir ( $this->getDataFolder () );
		$this->loadExecuteData ();
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->initialize_schedule_repeat ( $this, "saveExecuteData", 2000, [ ] );
	}
	public function onDisable() {
		$this->saveExecuteData ();
	}
	public static function getInstance() {
		return self::$instance;
	}
	public function onJoin(PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		
		$player_d_name = explode ( "d", $player->getName () );
		if ((is_numeric ( $player->getName () )) or (isset ( $player_d_name [1] ) and is_numeric ( $player_d_name [1] ))) {
			$event->setJoinMessage ( "" );
			$attachment = $player->addAttachment ( $this );
			$attachment->setPermission ( "pocketmine", false );
			$player->sendMessage ( $this->getMessage ( "index_name_caution1" ) );
			$player->sendMessage ( $this->getMessage ( "index_name_caution2" ) );
			$player->sendMessage ( $this->getMessage ( "index_name_caution3" ) );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$player ] );
			return;
		}
		if (isset ( $this->ipban_data [$player->getAddress ()] )) {
			$event->setJoinMessage ( "" );
			$attachment = $player->addAttachment ( $this );
			$attachment->setPermission ( "pocketmine", false );
			$player->sendMessage ( $this->getMessage ( "warning_ipbanned" ) . "(" . $player->getAddress () . ")" );
			$player->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			$player->sendMessage ( $this->getMessage ( "contact-admin" ) );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$player ] );
			return;
		}
		if (isset ( $this->ban_data [$player->getName ()] )) {
			$event->setJoinMessage ( "" );
			$attachment = $player->addAttachment ( $this );
			$attachment->setPermission ( "pocketmine", false );
			$player->sendMessage ( $this->getMessage ( "warning_banned" ) . "(" . $player->getName () . ")" );
			$player->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			$player->sendMessage ( $this->getMessage ( "contact-admin" ) );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$player ] );
			return;
		}
		$e = explode ( ".", $player->getAddress () );
		if (isset ( $this->subban_data [$e [0] . "." . $e [1]] )) {
			$event->setJoinMessage ( "" );
			$attachment = $player->addAttachment ( $this );
			$attachment->setPermission ( "pocketmine", false );
			$player->sendMessage ( $this->getMessage ( "warning_subnetbanned" ) . "(" . $e [0] . "." . $e [1] . ")" );
			$player->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			$player->sendMessage ( $this->getMessage ( "contact-admin" ) );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$player ] );
			return;
		}
		$this->onlinelist [] = $player->getName ();
	}
	public function onKick(PlayerKickEvent $event) {
		$player = $event->getPlayer ();
		
		$onlinekey = array_search ( $player->getName (), $this->onlinelist );
		if ($onlinekey !== false) array_splice ( $this->onlinelist, $onlinekey, 1 );
		
		$offlinekey = array_search ( $player->getName (), $this->offlinelist );
		if ($offlinekey === false) {
			if (count ( $this->offlinelist ) >= 5) array_shift ( $this->offlinelist );
			$this->offlinelist [] = $player->getName ();
		}
		
		$player_d_name = explode ( "d", $player->getName () );
		if ((is_numeric ( $player->getName () )) or (isset ( $player_d_name [1] ) and is_numeric ( $player_d_name [1] ))) {
			$event->setQuitMessage ( "" );
			return;
		}
		if (isset ( $this->ipban_data [$player->getAddress ()] )) {
			$event->setQuitMessage ( "" );
			return;
		}
		if (isset ( $this->ban_data [$player->getName ()] )) {
			$event->setQuitMessage ( "" );
			return;
		}
		if (isset ( $this->subban_data [$player->getAddress ()] )) {
			$event->setQuitMessage ( "" );
			return;
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		$player = $event->getPlayer ();
		
		$onlinekey = array_search ( $player->getName (), $this->onlinelist );
		if ($onlinekey !== false) array_splice ( $this->onlinelist, $onlinekey, 1 );
		
		$offlinekey = array_search ( $player->getName (), $this->offlinelist );
		if ($offlinekey === false) {
			if (count ( $this->offlinelist ) >= 5) array_shift ( $this->offlinelist );
			if (count ( $this->offline_iplist ) >= 5) array_shift ( $this->offlinelist );
			$this->offlinelist [] = $player->getName ();
			$this->offline_iplist [] = $player->getAddress ();
		}
		
		$player_d_name = explode ( "d", $player->getName () );
		if ((is_numeric ( $player->getName () )) or (isset ( $player_d_name [1] ) and is_numeric ( $player_d_name [1] ))) {
			$event->setQuitMessage ( "" );
			return;
		}
		if (isset ( $this->ipban_data [$player->getAddress ()] )) {
			$event->setQuitMessage ( "" );
			return;
		}
		if (isset ( $this->ban_data [$player->getName ()] )) {
			$event->setQuitMessage ( "" );
			return;
		}
		if (isset ( $this->subban_data [$player->getAddress ()] )) {
			$event->setQuitMessage ( "" );
			return;
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case "b" :
				if (isset ( $args [0] )) {
					if (isset ( $this->onlinelist [$args [0]] )) {
						// 인덱스를 통한 밴일 경우
						$target = $this->getServer ()->getPlayerExact ( $this->onlinelist [$args [0]] );
					} else {
						$e = explode ( "d", strtolower ( $args [0] ) );
						if (isset ( $e [1] ) and is_numeric ( $e [1] )) {
							// 오프라인 인덱스를 통한 밴일경우
							$target = $this->getServer ()->getOfflinePlayer ( $this->offlinelist [$e [1]] );
						} else {
							// 닉네임을 통한 밴일경우
							$name_search = $this->getServer ()->getOfflinePlayer ( $args [0] );
							if ($name_search instanceof IPlayer) $target = $name_search;
							else $sender->sendMessage ( $this->getMessage ( "not-found-user" ) );
						}
					}
					if (isset ( $args [1] )) {
						// 사유 받아오기
						array_shift ( $args );
						$cause = implode ( " ", $args );
					} else {
						// 사유가 없을때 기본사유 세팅
						$cause = $this->getMessage ( "default-cause-ban" );
					}
					if (isset ( $target )) {
						// 타겟을 찾았을때 밴처리
						$this->DoBan ( $sender, $target, $cause );
						return true;
					}
				}
				break;
			case "k" :
				if (isset ( $args [0] )) {
					if (isset ( $this->onlinelist [$args [0]] )) {
						// 인덱스를 통한 밴일 경우
						$target = $this->getServer ()->getPlayerExact ( $this->onlinelist [$args [0]] );
					} else {
						$e = explode ( "d", strtolower ( $args [0] ) );
						if (isset ( $e [1] ) and is_numeric ( $e [1] )) {
							// 오프라인 인덱스를 통한 밴일경우
							$target = $this->getServer ()->getOfflinePlayer ( $this->offlinelist [$e [1]] );
						} else {
							// 닉네임을 통한 밴일경우
							$name_search = $this->getServer ()->getOfflinePlayer ( $args [0] );
							if ($name_search instanceof IPlayer) {
								$target = $name_search;
							} else {
								$sender->sendMessage ( $this->getMessage ( "not-found-user" ) );
								return true;
							}
						}
					}
					if (isset ( $args [1] )) {
						// 사유 받아오기
						array_shift ( $args );
						$cause = implode ( " ", $args );
					} else {
						// 사유가 없을때 기본사유 세팅
						$cause = $this->getMessage ( "default-cause-kick" );
					}
					if (isset ( $target )) {
						// 타겟을 찾았을때 밴처리
						$this->DoKick ( $sender, $target, $cause );
						return true;
					}
				}
				break;
			case "i" :
				if (isset ( $args [0] )) {
					if (isset ( $this->onlinelist [$args [0]] )) {
						// 인덱스를 통한 밴일경우
						$target = $this->getServer ()->getPlayerExact ( $this->onlinelist [$args [0]] );
					} else {
						$name_search = $this->getServer ()->getOfflinePlayer ( $args [0] );
						$e = explode ( "d", strtolower ( $args [0] ) );
						if (isset ( $e [1] ) and is_numeric ( $e [1] )) {
							// 오프라인 인덱스를 통한 밴일경우
							$target = $this->offline_iplist [$e [1]];
						} else {
							// 아이피 주소를 통한 밴일경우
							$e = explode ( ".", $args [0] );
							if (isset ( $e [3] ) and is_numeric ( $e [0] ) and is_numeric ( $e [1] ) and is_numeric ( $e [2] ) and is_numeric ( $e [3] )) {
								$ip = $e [0] . "." . $e [1] . "." . $e [2] . "." . $e [3];
								foreach ( $this->getServer ()->getOnlinePlayers () as $checkip ) {
									if ($checkip->getAddress () == $ip) {
										$target = $checkip;
										break;
									}
								}
								$target = $ip;
							} else {
								$sender->sendMessage ( $this->getMessage ( "not-found-user" ) );
								return true;
							}
						}
					}
					if (isset ( $args [1] )) {
						// 사유 받아오기
						array_shift ( $args );
						$cause = implode ( " ", $args );
					} else {
						// 사유가 없을때 기본사유 세팅
						$cause = $this->getMessage ( "default-cause-ipban" );
					}
					if (isset ( $target )) {
						// 타겟을 찾았을때 밴처리
						$this->DoIPBan ( $sender, $target, $cause );
						return true;
					}
				}
				break;
			case "s" :
				if (isset ( $args [0] )) {
					if (isset ( $this->onlinelist [$args [0]] )) {
						// 인덱스를 통한 밴일경우
						$tp = $this->getServer ()->getPlayerExact ( $this->onlinelist [$args [0]] );
						foreach ( $this->getServer ()->getOnlinePlayers () as $checkip ) {
							$e = explode ( ".", $tp->getAddress () );
							$c = explode ( ".", $checkip->getAddress () );
							if ($e [0] . "." . $e [1] == $c [0] . "." . $c [1]) {
								$target [] = $checkip;
							}
						}
					} else {
						$name_search = $this->getServer ()->getOfflinePlayer ( $args [0] );
						$e = explode ( "d", strtolower ( $args [0] ) );
						if (isset ( $e [1] ) and is_numeric ( $e [1] )) {
							// 오프라인 인덱스를 통한 밴일경우
							$tp = explode ( ".", $this->offline_iplist [$e [1]] );
							$target [] = $tp [0] . "." . $tp [1];
							foreach ( $this->getServer ()->getOnlinePlayers () as $checkip ) {
								$e = explode ( ".", $tp->getAddress () );
								$c = explode ( ".", $checkip->getAddress () );
								if ($e [0] . "." . $e [1] == $c [0] . "." . $c [1]) {
									$target [] = $checkip;
								}
							}
						} else {
							// 서브넷 주소를 통한 밴일경우
							$e = explode ( ".", $args [0] );
							if (isset ( $e [1] ) and is_numeric ( $e [0] ) and is_numeric ( $e [1] )) {
								foreach ( $this->getServer ()->getOnlinePlayers () as $checkip ) {
									$c = explode ( ".", $checkip->getAddress () );
									if ($e [0] . "." . $e [1] == $c [0] . "." . $c [1]) {
										$target [] = $checkip;
									}
								}
								if (! isset ( $target )) $target = $e [0] . "." . $e [1];
							} else {
								$sender->sendMessage ( $this->getMessage ( "not-found-user" ) );
								return true;
							}
						}
					}
					if (isset ( $args [1] )) {
						// 사유 받아오기
						array_shift ( $args );
						$cause = implode ( " ", $args );
					} else {
						// 사유가 없을때 기본사유 세팅
						$cause = $this->getMessage ( "default-cause-subban" );
					}
					if (isset ( $target )) {
						// 타겟을 찾았을때 밴처리
						foreach ( $target as $t )
							$this->DoSubnetIPBan ( $sender, $t, $cause );
						return true;
					}
				}
				break;
			case "p" :
				if (isset ( $args [0] )) {
					$target = $args [0];
					if (isset ( $args [1] )) {
						// 사유 받아오기
						array_shift ( $args );
						$cause = implode ( " ", $args );
					} else {
						// 사유가 없을때 기본사유 세팅
						$cause = $this->getMessage ( "default-cause-pardon" );
					}
					$this->DoPardon ( $sender, $target, $cause );
					return true;
				}
				break;
			case "l" :
				if (isset ( $args [0] )) {
					if (isset ( $args [1] )) {
						$this->DoBanList ( $sender, $args [0], $args [1] );
					} else {
						$temp_array = [ 
								"b",
								"k",
								"i",
								"s",
								"p" ];
						foreach ( $temp_array as $temp_key ) {
							$e = explode ( $temp_key, strtolower ( $args [0] ) );
							if (isset ( $e [1] ) and is_numeric ( $e [1] )) {
								$this->DoBanList ( $sender, $temp_key, $e [1] );
								return true;
							}
						}
						$this->DoBanList ( $sender, $args [0] );
					}
				} else
					$this->DoList ( $sender );
				return true;
		}
		$sender->sendMessage ( $this->getMessage ( "info-ban" ) );
		$sender->sendMessage ( $this->getMessage ( "info-kick" ) );
		$sender->sendMessage ( $this->getMessage ( "info-ipban" ) );
		$sender->sendMessage ( $this->getMessage ( "info-subban" ) );
		$sender->sendMessage ( $this->getMessage ( "info-list" ) );
		$sender->sendMessage ( $this->getMessage ( "info-banlist" ) );
		$sender->sendMessage ( $this->getMessage ( "info-pardon" ) );
		return true;
	}
	public function DoList($executor) {
		$message = TextFormat::DARK_AQUA;
		$message .= $this->getMessage ( "now-onlinelist" ) . " (" . count ( $this->getServer ()->getOnlinePlayers () ) . "/" . $this->getServer ()->getMaxPlayers () . ") : ";
		foreach ( $this->onlinelist as $index => $value )
			$message .= $value . "[" . $index . "] ";
		$message .= "\n" . $this->getMessage ( "now-offlinelist" ) . " (" . count ( $this->offlinelist ) . "/5) : ";
		foreach ( $this->offlinelist as $index => $value )
			$message .= $value . "[d" . $index . "] ";
		$executor->sendMessage ( $message );
	}
	public function DoBanList($executor, $list_name, $index = 1) {
		switch (strtolower ( $list_name )) {
			case "b" :
				$target = $this->ban_data;
				$targetname = "밴";
				break;
			case "k" :
				$target = $this->kick_data;
				$targetname = "킥";
				break;
			case "i" :
				$target = $this->ipban_data;
				$targetname = "아이피밴";
				break;
			case "s" :
				$target = $this->subban_data;
				$targetname = "서브넷밴";
				break;
			case "p" :
				$target = $this->pardon_data;
				$targetname = "밴해제";
				break;
		}
		if (isset ( $target )) {
			$once_print = 5;
			
			$index_count = count ( $target );
			$index_key = array_keys ( $target );
			$full_index = floor ( $index_count / $once_print );
			
			if ($index_count > $full_index * $once_print) $full_index ++;
			
			if ($index <= $full_index) {
				$executor->sendMessage ( TextFormat::RED . $targetname . " " . $this->getMessage ( "search_info" ) . " (" . $index . "/" . $full_index . ") " . $this->getMessage ( "amount" ) . ": " . $index_count );
				$message = null;
				
				for($for_i = $once_print; $for_i >= 1; $for_i --) {
					$now_index = $index * $once_print - $for_i;
					if (! isset ( $index_key [$now_index] )) break;
					$now_key = $index_key [$now_index];
					$message .= TextFormat::RED . "[" . $now_key . "] " . $this->getMessage ( "execute-time" ) . ": " . $target [$now_key] ["time"] . "\n";
					$message .= $this->getMessage ( "executor" ) . ": " . $target [$now_key] ["executor"] . " " . $this->getMessage ( "execute-cause" ) . ": " . $target [$now_key] ["cause"] . "\n";
					if (isset ( $target [$now_key] ["before-cause"] )) $executor->sendMessage ( TextFormat::RED . "(" . $this->getMessage ( "execute-before-cause" ) . ": " . $target [$now_key] ["before-cause"] . ")" );
				}
				$executor->sendMessage ( $message );
				return;
			} else {
				$executor->sendMessage ( $this->getMessage ( "not-found-list" ) );
			}
		}
		$executor->sendMessage ( $this->getMessage ( "info-banlist-1" ) );
		$executor->sendMessage ( $this->getMessage ( "info-kicklist" ) );
		$executor->sendMessage ( $this->getMessage ( "info-ipbanlist" ) );
		$executor->sendMessage ( $this->getMessage ( "info-subbanlist" ) );
		$executor->sendMessage ( $this->getMessage ( "info-pardonlist" ) );
	}
	public function DoBan($executor, $target, $cause) {
		if ($target instanceof IPlayer) {
			$name = $target->getName ();
		} else {
			$name = $target;
		}
		if (isset ( $this->ban_data [$name] )) {
			$info_time = $this->ban_data [$name] ["time"];
			$info_cause = $this->ban_data [$name] ["cause"];
			$info_executor = $this->ban_data [$name] ["executor"];
			
			$executor->sendMessage ( $this->getMessage ( "already-banned" ) );
			$executor->sendMessage ( $this->getMessage ( "execute-time" ) . ": " . $info_time );
			$executor->sendMessage ( $this->getMessage ( "execute-cause" ) . ": " . $info_cause . " " . $this->getMessage ( "executor" ) . ":" . $info_executor );
			return;
		}
		$this->ban_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
		$this->ban_data [$name] ["cause"] = $cause;
		$this->ban_data [$name] ["executor"] = $executor->getName ();
		if ($target instanceof Player and ! $target->closed) {
			$executor->sendMessage ( $this->getMessage ( "executed-ban+kick" ) );
			$target->sendMessage ( $this->getMessage ( "warning-ban" ) );
			$target->sendMessage ( $this->getMessage ( "execute-cause" ) . ": " . $cause . " " . $this->getMessage ( "executor" ) . ": " . $executor->getName () );
			$target->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
		} else {
			$executor->sendMessage ( $this->getMessage ( "executed-ban" ) );
		}
		$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "broadcast-kick-info" ) . ":" . $cause );
		if ($target instanceof Player and ! $target->closed) $this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
				$target ] );
	}
	public function DoKick($executor, $target, $cause) {
		if ($target instanceof IPlayer) {
			$name = $target->getName ();
		} else {
			$name = $target;
		}
		$this->kick_data [$name] ["time"] = $this->getMessage ( "time" );
		$this->kick_data [$name] ["cause"] = $cause;
		$this->kick_data [$name] ["executor"] = $executor->getName ();
		
		if ($target instanceof Player and ! $target->closed) {
			$executor->sendMessage ( $this->getMessage ( "executed-kick" ) );
			$target->sendMessage ( $this->getMessage ( "warning-kick" ) );
			$target->sendMessage ( $this->getMessage ( "execute-cause" ) . ": " . $cause . " " . $this->getMessage ( "executor" ) . ": " . $executor->getName () );
			$target->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "broadcast-kick-info" ) . ":" . $cause );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$target ] );
		} else {
			$executor->sendMessage ( $this->getMessage ( "user-not-login" ) );
		}
	}
	public function DoIPBan($executor, $target, $cause) {
		if ($target instanceof IPlayer) {
			$address = $target->getAddress ();
		} else {
			$address = $target;
		}
		if (isset ( $this->ipban_data [$address] )) {
			$info_name = $this->ipban_data [$address] ["name"];
			$info_time = $this->ipban_data [$address] ["time"];
			$info_cause = $this->ipban_data [$address] ["cause"];
			$info_executor = $this->ipban_data [$address] ["executor"];
			
			$executor->sendMessage ( $this->getMessage ( "already-ipbanned" ) );
			$executor->sendMessage ( "( " . $this->getMessage ( "execute-time" ) . ":" . $info_time . " " . $this->getMessage ( "execute-cause" ) . ":" . $info_cause . " )" );
			$executor->sendMessage ( "( " . $this->getMessage ( "executor" ) . ":" . $info_executor . $this->getMessage ( "executed-name" ) . ": " . $info_name );
			return;
		}
		if ($target instanceof IPlayer) $this->ipban_data [$address] ["name"] = $target->getName ();
		$this->ipban_data [$address] ["time"] = date ( $this->getMessage ( "time" ) );
		$this->ipban_data [$address] ["cause"] = $cause;
		$this->ipban_data [$address] ["executor"] = $executor->getName ();
		
		if ($target instanceof Player and ! $target->closed) {
			$executor->sendMessage ( TextFormat::DARK_AQUA . $address . " " . $this->getMessage ( "executed-ipban+kick" ) );
			$target->sendMessage ( $this->getMessage ( "warning-ipban" ) );
			$target->sendMessage ( $this->getMessage ( "execute-cause" ) . ": " . $cause . " " . $this->getMessage ( "executor" ) . ": " . $executor->getName () );
			$target->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $target->getName () . " " . $this->getMessage ( "broadcast-ipban-info" ) . ":" . $cause );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$target ] );
		} else {
			$executor->sendMessage ( TextFormat::DARK_AQUA . $address . " " . $this->getMessage ( "executed-ipban" ) );
		}
	}
	public function DoSubnetIPBan($executor, $target, $cause) {
		if ($target instanceof IPlayer) {
			$e = explode ( ".", $target->getAddress () );
			$subnet = $e [0] . "." . $e [1];
		} else {
			$subnet = $target;
		}
		if (isset ( $this->subban_data [$subnet] )) {
			$info_name = $this->subban_data [$subnet] ["name"];
			$info_time = $this->subban_data [$subnet] ["time"];
			$info_cause = $this->subban_data [$subnet] ["cause"];
			$info_executor = $this->subban_data [$subnet] ["executor"];
			
			$executor->sendMessage ( $this->getMessage ( "already-subbanned" ) );
			$executor->sendMessage ( "( " . $this->getMessage ( "execute-time" ) . ":" . $info_time . " " . $this->getMessage ( "execute-cause" ) . ":" . $info_cause . " )" );
			$executor->sendMessage ( "( " . $this->getMessage ( "executor" ) . $info_executor . " " . $this->getMessage ( "executed-name" ) . ": " . $info_name );
			return;
		}
		if ($target instanceof IPlayer) $this->subban_data [$subnet] ["name"] = $target->getName ();
		$this->subban_data [$subnet] ["time"] = date ( $this->getMessage ( "time" ) );
		$this->subban_data [$subnet] ["cause"] = $cause;
		$this->subban_data [$subnet] ["executor"] = $executor->getName ();
		
		if ($target instanceof Player and ! $target->closed) {
			$executor->sendMessage ( TextFormat::DARK_AQUA . $subnet . " " . $this->getMessage ( "executed-subban+kick" ) );
			$target->sendMessage ( $this->getMessage ( "warning-subban" ) );
			$target->sendMessage ( $this->getMessage ( "execute-cause" ) . ": " . $cause . " " . $this->getMessage ( "executor" ) . ": " . $executor->getName () );
			$target->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $target->getName () . $this->getMessage ( "broadcast-subban-info" ) . ":" . $cause );
			$this->initialize_schedule_delay ( $this, "KickExecute", 100, [ 
					$target ] );
		} else {
			$executor->sendMessage ( TextFormat::DARK_AQUA . $subnet . " " . $this->getMessage ( "executed-subban" ) );
		}
	}
	public function DoPardon($executor, $target, $cause) {
		if ($target instanceof IPlayer) {
			$name = $target->getName ();
		} else {
			$name = $target;
		}
		$success_find = 0;
		
		$pardonkey = array_search ( $name, array_keys ( $this->ban_data ) );
		if ($pardonkey !== false) {
			$this->pardon_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
			$this->pardon_data [$name] ["before-cause"] = $this->ban_data [$name] ["cause"];
			$this->pardon_data [$name] ["cause"] = $cause;
			$this->pardon_data [$name] ["executor"] = $executor->getName ();
			
			array_splice ( $this->ban_data, $pardonkey, 1 );
			$success_find ++;
			$executor->sendMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "deleted-ban" ) );
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . $this->getMessage ( "broadcast-pardon-ban" ) . ":" . $cause );
		}
		$keylist = array_keys ( $this->ipban_data );
		foreach ( $keylist as $target_key ) {
			if (isset ( $this->ipban_data [$target_key] ["name"] ) and $this->ipban_data [$target_key] ["name"] == $name) {
				
				$this->pardon_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
				$this->pardon_data [$name] ["before-cause"] = $this->ipban_data [$name] ["cause"];
				$this->pardon_data [$name] ["cause"] = $cause;
				$this->pardon_data [$name] ["executor"] = $executor->getName ();
				
				array_splice ( $this->ipban_data, $target_key, 1 );
				$success_find ++;
				
				$executor->sendMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "deleted-ipban" ) );
				$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . $this->getMessage ( "broadcast-pardon-ipban" ) . ":" . $cause );
				break;
			}
		}
		$keylist = array_keys ( $this->subban_data );
		foreach ( $keylist as $target_key ) {
			if ($this->subban_data [$target_key] ["name"] == $name) {
				
				$this->pardon_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
				$this->pardon_data [$name] ["before-cause"] = $this->subban_data [$name] ["cause"];
				$this->pardon_data [$name] ["cause"] = $cause;
				$this->pardon_data [$name] ["executor"] = $executor->getName ();
				
				array_splice ( $this->subban_data, $target_key, 1 );
				$success_find ++;
				
				$executor->sendMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "deleted-subban" ) );
				$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . $this->getMessage ( "broadcast-pardon-subban" ) . ":" . $cause );
				break;
			}
		}
		if (isset ( $this->ipban_data [$name] )) {
			$this->pardon_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
			$this->pardon_data [$name] ["before-cause"] = $this->ipban_data [$name] ["cause"];
			$this->pardon_data [$name] ["cause"] = $cause;
			$this->pardon_data [$name] ["executor"] = $executor->getName ();
			
			array_splice ( $this->ipban_data, $pardonkey, 1 );
			$success_find ++;
			
			$executor->sendMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "deleted-ipban" ) );
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "broadcast-pardon-ipban" ) . ":" . $cause );
		}
		if (isset ( $this->subban_data [$name] )) {
			$this->pardon_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
			$this->pardon_data [$name] ["before-cause"] = $this->subban_data [$name] ["cause"];
			$this->pardon_data [$name] ["cause"] = $cause;
			$this->pardon_data [$name] ["executor"] = $executor->getName ();
			
			array_splice ( $this->subban_data, $pardonkey, 1 );
			$success_find ++;
			
			$executor->sendMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "deleted-subban" ) );
			$this->getServer ()->broadcastMessage ( TextFormat::DARK_AQUA . $name . " " . $this->getMessage ( "broadcast-pardon-subban" ) . ":" . $cause );
		}
		if ($success_find == 0) $executor->sendMessage ( $this->getMessage ( "can-not-found-ban" ) );
	}
	public function KickExecute($target) {
		if (! $target->closed) $target->kick ();
	}
	public function loadExecuteData() {
		$this->log_ban = $this->initializeYML ( "log_ban.yml", [ ] );
		$this->log_ipban = $this->initializeYML ( "log_ipban.yml", [ ] );
		$this->log_kick = $this->initializeYML ( "log_kick.yml", [ ] );
		$this->log_subban = $this->initializeYML ( "log_subban.yml", [ ] );
		$this->log_pardon = $this->initializeYML ( "log_pardon.yml", [ ] );
		$this->ban_data = $this->log_ban->getAll ();
		$this->kick_data = $this->log_kick->getAll ();
		$this->ipban_data = $this->log_ipban->getAll ();
		$this->subban_data = $this->log_subban->getAll ();
		$this->pardon_data = $this->log_pardon->getAll ();
	}
	public function saveExecuteData() {
		$this->log_ban->setAll ( $this->ban_data );
		$this->log_ipban->setAll ( $this->ipban_data );
		$this->log_subban->setAll ( $this->subban_data );
		$this->log_kick->setAll ( $this->kick_data );
		$this->log_pardon->setAll ( $this->pardon_data );
		$this->log_ban->save ();
		$this->log_ipban->save ();
		$this->log_subban->save ();
		$this->log_kick->save ();
		$this->log_pardon->save ();
		$this->defaultTextData ();
	}
	public function defaultTextData() {
		$this->language = $this->initializeYML ( "language.yml", [ 
				"setlanguage" => "ko",
				"ko-not-found-user" => "§3해당하는 유저를 찾을 수 없습니다.",
				"ko-default-cause-ban" => "OP에 의한 밴",
				"ko-default-cause-kick" => "OP에 의한 킥",
				"ko-default-cause-ipban" => "OP에 의한 아이피밴",
				"ko-default-cause-subnet" => "OP에 의한 서브넷밴",
				"ko-default-cause-pardon" => "OP에 의한 밴 해제",
				"ko-info-ban" => "§3[PSYCHOPASS] /b <인덱스 OR 유저명> -밴",
				"ko-info-kick" => "§3[PSYCHOPASS] /k <인덱스 OR 유저명> -킥",
				"ko-info-ipban" => "§3[PSYCHOPASS] /i <인덱스 OR 유저명> -아이피밴",
				"ko-info-subban" => "§3[PSYCHOPASS] /s <인덱스 OR 유저명> -서브넷밴",
				"ko-info-list" => "§3[PSYCHOPASS] /l -인덱스 조회",
				"ko-info-banlist" => "§3[PSYCHOPASS] /l <b:k:i:s:p> - 밴리스트 조회",
				"ko-info-pardon" => "§3[PSYCHOPASS] /p <인덱스 OR 유저명> -밴해제",
				"ko-now-onlinelist" => "§3현재 접속중 리스트",
				"ko-now-offlinelist" => "§3최근 오프라인된 리스트",
				"ko-time" => "Y년 m월 d일 H시 i분 s초",
				"ko-execute-time" => "§c처리일자",
				"ko-execute-cause" => "§c사유",
				"ko-execute-before-cause" => "§c이전사유",
				"ko-executor" => "§c처리자",
				"ko-executed-name" => "§c처리된닉네임",
				"ko-warning-disconnected" => "§c* 5초 뒤 서버와의 연결이 종료됩니다.",
				"ko-already-banned" => "§c이미 밴이력이 있습니다, 밴이력을 표시합니다.",
				"ko-executed-ban+kick" => "§3대상을 밴처리했습니다, (5초 후 킥처리됩니다)",
				"ko-executed-ban" => "§3대상을 밴처리했습니다.",
				"ko-warning-ban" => "§c경고,  본 회원분은 밴처리되었습니다.",
				"ko-broadcast-ban-info" => "§3님이 밴처리되었습니다, 사유",
				"ko-executed-kick" => "§3대상을 킥처리했습니다, (5초 후 밴처리됩니다)",
				"ko-warning-kick" => "§c경고,  본 회원분은 킥처리되었습니다.",
				"ko-broadcast-kick-info" => "§3님이 킥처리되었습니다, 사유",
				"ko-user-not-login" => "§3해당 유저가 접속 중이 아닙니다.",
				"ko-already-ipbanned" => "§c이미 아이피밴이력이 있습니다, 밴이력을 표시합니다.",
				"ko-executed-ipban+kick" => "§3대상을 아이피밴처리했습니다, (5초 후 킥처리됩니다)",
				"ko-executed-ipban" => "§3대상을 아이피밴처리했습니다.",
				"ko-warning-ipban" => "§c경고,  본 회원분은 아이피밴처리되었습니다.",
				"ko-broadcast-ipban-info" => "§3님이 아이피밴처리되었습니다, 사유",
				"ko-already-subbanned" => "§c이미 서브넷밴이력이 있습니다, 밴이력을 표시합니다.",
				"ko-executed-subban+kick" => "§3대상을 서브넷밴처리했습니다, (5초 후 킥처리됩니다)",
				"ko-executed-subban" => "§3대상을 서브넷밴처리했습니다.",
				"ko-warning-subban" => "§c경고,  본 회원분은 서브넷밴처리되었습니다.",
				"ko-broadcast-subban-info" => "§3님이 서브넷밴처리되었습니다, 사유",
				"ko-deleted-ban" => "§3님의 밴 기록을 삭제했습니다.",
				"ko-broadcast-pardon-ban" => "§3님이 밴해제 되었습니다, 사유",
				"ko-deleted-ipban" => "§3님의 아이피밴 기록을 삭제했습니다.",
				"ko-broadcast-pardon-ipban" => "§3님이 아이피밴 해제 되었습니다, 사유",
				"ko-deleted-subban" => "§3님의 서브넷밴 기록을 삭제했습니다.",
				"ko-broadcast-pardon-subban" => "§3님이 서브넷밴 해제 되었습니다, 사유",
				"ko-deleted-ipban" => "§3해당 아이피밴 기록을 삭제했습니다.",
				"ko-broadcast-pardon-ipbban" => "§3아이피밴 해제 되었습니다, 사유",
				"ko-can-not-found-ban" => "§3해당되는 밴이력을 찾을 수없습니다",
				"ko-warning_banned" => "§c해당 닉네임은 밴처리되어있습니다,",
				"ko-warning_ipbanned" => "§c해당 아아피는 밴처리되어있습니다",
				"ko-warning_subnetbanned" => "§c해당 서브넷아이피는 밴처리되어있습니다",
				"ko-contact-admin" => "§c관련문의는 서버 관리자에게 해주세요.",
				"ko-index_name_caution1" => "§c숫자로 된 닉네임이나 d<숫자>",
				"ko-index_name_caution2" => "§c닉네임은 사용이 불가능합니다.",
				"ko-index_name_caution3" => "§c(다른 닉네임을 사용해주세요.)",
				"ko-search_info" => "§c내역을 출력합니다.",
				"ko-info-banlist-1" => "§3[PSYCHOPASS] /l b<인덱스> -밴 내역 조회",
				"ko-info-kicklist" => "§3[PSYCHOPASS] /l k<인덱스> -킥 내역 조회",
				"ko-info-ipbanlist" => "§3[PSYCHOPASS] /l i<인덱스> -아이피밴 내역 조회",
				"ko-info-subbanlist" => "§3[PSYCHOPASS] /l s<인덱스> -서브넷밴 내역 조회",
				"ko-info-pardonlist" => "§3[PSYCHOPASS] /l p<인덱스> -밴해제 내역 조회",
				"ko-amount" => "총",
				"ko-not-found-list" => "§3검색되는 리스트가 없습니다." ] )->getAll ();
	}
	public function getMessage($var) {
		if (isset ( $this->language [$this->language ["setlanguage"] . "-" . $var] )) return $this->language [$this->language ["setlanguage"] . "-" . $var];
		else return $var . " NOT FOUND LANGUAGE DATA";
	}
	public function initializeYML($path, $array) {
		return new Config ( $this->getDataFolder () . $path, Config::YAML, $array );
	}
	public function initialize_schedule_repeat($class, $method, $second, $param) {
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$class,
				$method ], $param ), $second );
	}
	public function initialize_schedule_delay($class, $method, $second, $param) {
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
				$class,
				$method ], $param ), $second );
	}
}

?>