<?php

/**  __    __       __    __
 * /＼ ＼_＼ ＼   /＼  "-./ ＼
 * ＼ ＼  __   ＼ ＼ ＼ ＼/＼＼
 *  ＼ ＼_＼ ＼ _＼＼ ＼_＼ ＼_＼
 *   ＼/_/  ＼/__/   ＼/_/ ＼/__/
 * ( *you can redistribute it and/or modify *) */
namespace hm\PSYCHOPASS;

use pocketmine\command\PluginCommand;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\IPlayer;
use pocketmine\event\player\PlayerChatEvent;

class PSYCHOPASS_Dominator extends PluginBase implements Listener {
	/**
	 *
	 * @var PSYCHOPASS_Dominator
	 */
	private static $instance = null;
	/**
	 *
	 * @var Config
	 */
	public $log_ban, $log_kick, $log_ipban, $log_subban, $log_pardon;
	/**
	 *
	 * @var array
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
	/*
	 * @var PSYCHOPASS_API
	 */
	/*
	 * @var MESSAGE_VERSION
	*/
	public $m_version = 1;
	public $api = null;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		if (self::$instance == null) self::$instance = $this;
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "PSYCHOPASS_API" ) != null) {
			$this->api = PSYCHOPASS_API::getInstance ();
		}
		
		$this->loadExecuteData ();
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->initialize_schedule_repeat ( new SaveExecuteDataTask ( $this ), 2000 );
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
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $player ), 100 );
			return;
		}
		if (isset ( $this->ipban_data [$player->getAddress ()] )) {
			$event->setJoinMessage ( "" );
			$attachment = $player->addAttachment ( $this );
			$attachment->setPermission ( "pocketmine", false );
			$player->sendMessage ( $this->getMessage ( "warning_ipbanned" ) . "(" . $player->getAddress () . ")" );
			$player->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			$player->sendMessage ( $this->getMessage ( "contact-admin" ) );
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $player ), 100 );
			return;
		}
		if (isset ( $this->ban_data [$player->getName ()] )) {
			$event->setJoinMessage ( "" );
			$attachment = $player->addAttachment ( $this );
			$attachment->setPermission ( "pocketmine", false );
			$player->sendMessage ( $this->getMessage ( "warning_banned" ) . "(" . $player->getName () . ")" );
			$player->sendMessage ( $this->getMessage ( "warning-disconnected" ) );
			$player->sendMessage ( $this->getMessage ( "contact-admin" ) );
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $player ), 100 );
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
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $player ), 100 );
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
	public function onChat(PlayerChatEvent $event) {
		$player = $event->getPlayer ();
		if ((is_numeric ( $player->getName () )) or (isset ( $player_d_name [1] ) and is_numeric ( $player_d_name [1] ))) {
			$event->setCancelled ();
			return;
		}
		if (isset ( $this->ipban_data [$player->getAddress ()] )) {
			$event->setCancelled ();
			return;
		}
		if (isset ( $this->ban_data [$player->getName ()] )) {
			$event->setCancelled ();
			return;
		}
		if (isset ( $this->subban_data [$player->getAddress ()] )) {
			$event->setCancelled ();
			return;
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case "b" :
				if (isset ( $args [0] )) {
					if (isset ( $this->onlinelist [$args [0]] )) {
						// 인덱스를 통한 밴일 경우
						$target = $this->getServer ()->getPlayer ( $this->onlinelist [$args [0]] );
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
						$target = $this->getServer ()->getPlayer ( $this->onlinelist [$args [0]] );
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
						$target = $this->getServer ()->getPlayer ( $this->onlinelist [$args [0]] );
					} else {
						// $name_search = $this->getServer ()->getOfflinePlayer ( $args [0] );
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
										// $target = $checkip;
										break;
									}
								}
								$target = $ip;
							} else {
								$playerSearch = $this->getServer ()->getPlayer ( $args [0] );
								if ($playerSearch != null) {
									$target = $playerSearch;
								} else {
									$sender->sendMessage ( $this->getMessage ( "not-found-user" ) );
									return true;
								}
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
				$target = [ ];
				if (isset ( $args [0] )) {
					if (isset ( $this->onlinelist [$args [0]] )) {
						// 인덱스를 통한 밴일경우
						$tp = $this->getServer ()->getPlayer ( $this->onlinelist [$args [0]] );
						foreach ( $this->getServer ()->getOnlinePlayers () as $checkip ) {
							$e = explode ( ".", $tp->getAddress () );
							$c = explode ( ".", $checkip->getAddress () );
							if ($e [0] . "." . $e [1] == $c [0] . "." . $c [1]) {
								$target [] = $checkip;
							}
						}
					} else {
						$e = explode ( "d", strtolower ( $args [0] ) );
						if (isset ( $e [1] ) and is_numeric ( $e [1] )) {
							// 오프라인 인덱스를 통한 밴일경우
							/**
							 * @var $tp Player
							 */
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
								$playerSearch = $this->getServer ()->getPlayer ( $args [0] );
								if ($playerSearch != null) {
									$target [] = $playerSearch;
								} else {
									$sender->sendMessage ( $this->getMessage ( "not-found-user" ) );
									return true;
								}
							}
						}
					}
					if (isset ( $args [1] )) {
						// 사유 받아오기
						array_shift ( $args );
						$cause = implode ( " ", $args );
					} else {
						// 사유가 없을때 기본사유 세팅
						$cause = $this->getMessage ( "default-cause-subnet" );
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
						$temp_array = [ "b","k","i","s","p" ];
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
	public function DoList(CommandSender $executor) {
		$message = TextFormat::DARK_AQUA;
		$message .= $this->getMessage ( "now-onlinelist" ) . " (" . count ( $this->getServer ()->getOnlinePlayers () ) . "/" . $this->getServer ()->getMaxPlayers () . ") : ";
		foreach ( $this->onlinelist as $index => $value )
			$message .= $value . "[" . $index . "] ";
		$message .= "\n" . $this->getMessage ( "now-offlinelist" ) . " (" . count ( $this->offlinelist ) . "/5) : ";
		foreach ( $this->offlinelist as $index => $value )
			$message .= $value . "[d" . $index . "] ";
		$executor->sendMessage ( $message );
	}
	public function DoBanList(CommandSender $executor, $list_name, $index = 1) {
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
			
			default :
				$targetname = "";
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
	public function DoBan(CommandSender $executor, $target, $cause) {
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
		if ($target instanceof Player and ! $target->closed) $this->initialize_schedule_delay ( new KickExecuteTask ( $this, $target ), 100 );
	}
	public function DoKick(CommandSender $executor, $target, $cause) {
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
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $target ), 100 );
		} else {
			$executor->sendMessage ( $this->getMessage ( "user-not-login" ) );
		}
	}
	public function DoIPBan(CommandSender $executor, $target, $cause) {
		if ($target instanceof Player) {
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
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $target ), 100 );
		} else {
			$executor->sendMessage ( TextFormat::DARK_AQUA . $address . " " . $this->getMessage ( "executed-ipban" ) );
		}
	}
	public function DoSubnetIPBan(CommandSender $executor, $target, $cause) {
		if ($target instanceof Player) {
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
			$this->initialize_schedule_delay ( new KickExecuteTask ( $this, $target ), 100 );
		} else {
			$executor->sendMessage ( TextFormat::DARK_AQUA . $subnet . " " . $this->getMessage ( "executed-subban" ) );
		}
	}
	public function DoPardon(CommandSender $executor, $target, $cause) {
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
				if (isset ( $this->ipban_data [$name] ["cause"] )) {
					$this->pardon_data [$name] ["before-cause"] = $this->ipban_data [$name] ["cause"];
				} else {
					$this->pardon_data [$name] ["before-cause"] = "";
				}
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
		foreach ( $keylist as $index => $target_key ) {
			if ($this->subban_data [$target_key] ["name"] == $name) {
				
				$this->pardon_data [$name] ["time"] = date ( $this->getMessage ( "time" ) );
				if (isset ( $this->subban_data [$name] ["cause"] )) {
					$this->pardon_data [$name] ["before-cause"] = $this->subban_data [$name] ["cause"];
				} else {
					$this->pardon_data [$name] ["before-cause"] = "";
				}
				$this->pardon_data [$name] ["cause"] = $cause;
				$this->pardon_data [$name] ["executor"] = $executor->getName ();
				
				array_splice ( $this->subban_data, $index, 1 );
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
	public function KickExecute(Player $target) {
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
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->language = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function getMessage($var) {
		if (isset ( $this->language [$this->language ["setlanguage"] . "-" . $var] )) return $this->language [$this->language ["setlanguage"] . "-" . $var];
		else return $var . " NOT FOUND LANGUAGE DATA";
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function initializeYML($path, $array) {
		return new Config ( $this->getDataFolder () . $path, Config::YAML, $array );
	}
	public function initialize_schedule_repeat($task, $period) {
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( $task, $period );
	}
	public function initialize_schedule_delay($task, $period) {
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( $task, $period );
	}
}

?>