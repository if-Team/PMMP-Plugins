<?php

namespace ifteam\SuperUser;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

class SuperUser extends PluginBase implements Listener {
	public $messages, $db; // 메시지 변수, DB변수
	public $m_version = 1; // 현재 메시지 버전
	public $brute_force = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () ); // 플러그인 폴더생성
		
		$this->initMessage (); // 기본언어메시지 초기화
		                       
		// YAML 형식의 DB생성 후 불러오기
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		// 플러그인의 명령어 등록
		$this->registerCommand ( "su", "SuperUser.commands", $this->get ( "su-description" ), $this->get ( "su-usage" ) );
		$this->registerCommand ( "staff", "StaffUser.commands", $this->get ( "staff-description" ), $this->get ( "staff-usage" ) );
		$this->registerCommand ( "passwd", "PassWD.commands", $this->get ( "passwd-description" ), $this->get ( "passwd-usage" ) );
		
		// 서버이벤트를 받아오게끔 플러그인 리스너를 서버에 등록
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
		if ($event->getPlayer ()->isOp ()) {
			$command = explode ( " ", $event->getMessage () );
			if ($command [0] == "/op" or $command [0] == "/deop") {
				$event->setCancelled ();
				$this->alert ( $event->getPlayer (), $this->get ( "su-access-denied" ) );
				$this->getLogger ()->alert ( $event->getPlayer ()->getName () . ": " . $this->get ( "su-access-denied" ) . " (" . $command [0] . ")" );
			}
		}
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command )) {
			case "staff" :
			case "su" :
				if (! is_array ( $args ) or ! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "su-usage" ) . " - " . $this->get ( "su-description" ) );
					$this->message ( $player, $this->get ( "staff-usage" ) . " - " . $this->get ( "staff-description" ) );
					return true;
				}
				if (! isset ( $this->db ["su"] ) and ! isset ( $this->db ["staff"] )) {
					$this->message ( $player, $this->get ( "su-not-exist-passkey" ) );
					$this->checkBruteForce ( $player );
					return true;
				}
				if (! $player instanceof Player) {
					$this->message ( $player, $this->get ( "that-command-only-can-ingame" ) );
					return true;
				}
				if (isset ( $this->db ["su"] [$args [0]] )) {
					$player->setGamemode ( 1 );
					if ($this->db ["su"] [$args [0]] ["firstLoginName"] == null)
						$this->db ["su"] [$args [0]] ["firstLoginName"] = $player->getName ();
					$this->db ["su"] [$args [0]] ["lastLoginIP"] = $player->getAddress ();
					if ($this->db ["su"] [$args [0]] ["firstLoginName"] != $player->getName ()) {
						$this->message ( $player, $this->get ( "passkey-is-has-been-exposed-so-expired" ) );
						$this->getLogger ()->alert ( $player->getName () . ": " . $this->get ( "passkey-is-has-been-exposed-so-expired" ) . "({$args [0]})" );
						unset ( $this->db ["su"] [$args [0]] );
						return true;
					}
					$this->db ["su"] [$args [0]] ["lastlogIn"] = date ( "Y-m-d H:i:s" );
					
					$this->message ( $player, $this->get ( "su-welcome-to-access" ) );
					$this->getLogger ()->info ( $player->getName () . ": " . $this->get ( "su-aceess-success" ) . " key:" . $args [0] );
					$player->setOp ( true );
				} else if (isset ( $this->db ["staff"] [$args [0]] )) {
					$player->setGamemode ( 3 );
					$attachment = $player->addAttachment ( $this );
					$attachment->setPermission ( "pocketmine.command.ban.player", true );
					$attachment->setPermission ( "pocketmine.command.ban.ip", true );
					$attachment->setPermission ( "pocketmine.command.ban.ip", true );
					$attachment->setPermission ( "pocketmine.command.ban.list", true );
					$attachment->setPermission ( "pocketmine.command.unban.player", true );
					$attachment->setPermission ( "pocketmine.command.unban.ip", true );
					$attachment->setPermission ( "pocketmine.command.unban.ip", true );
					$attachment->setPermission ( "pocketmine.command.teleport", true );
					
					if ($this->db ["staff"] [$args [0]] ["firstLoginName"] == null)
						$this->db ["staff"] [$args [0]] ["firstLoginName"] = $player->getName ();
					$this->db ["staff"] [$args [0]] ["lastLoginIP"] = $player->getAddress ();
					if ($this->db ["staff"] [$args [0]] ["firstLoginName"] != $player->getName ()) {
						$this->message ( $player, $this->get ( "passkey-is-has-been-exposed-so-expired" ) );
						$this->getLogger ()->alert ( $player->getName () . ": " . $this->get ( "passkey-is-has-been-exposed-so-expired" ) . "({$args [0]})" );
						unset ( $this->db ["staff"] [$args [0]] );
						return true;
					}
					$this->db ["staff"] [$args [0]] ["lastlogIn"] = date ( "Y-m-d H:i:s" );
					
					$this->message ( $player, $this->get ( "su-welcome-to-access" ) );
					$this->getLogger ()->info ( $player->getName () . ": " . $this->get ( "su-aceess-success" ) . " key:" . $args [0] );
				} else {
					$this->message ( $player, $this->get ( "su-not-exist-that-passkey" ) );
					$this->getLogger ()->alert ( $player->getName () . ": " . $this->get ( "su-access-wrong" ) . " (" . "/su " . $args [0] . ")" );
					$this->checkBruteForce ( $player );
					return true;
				}
				break;
			case "passwd" :
				if ($player instanceof Player) {
					$this->message ( $player, $this->get ( "that-command-only-can-console" ) );
					$this->getLogger ()->alert ( $player->getName () . ": " . $this->get ( "that-command-only-can-console" ) . " (/passwd)" );
					return true;
				}
				if (! is_array ( $args ) or ! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "help-passwd-add" ) );
					$this->message ( $player, $this->get ( "help-passwd-del" ) );
					$this->message ( $player, $this->get ( "help-passwd-reset" ) );
					$this->message ( $player, $this->get ( "help-passwd-list" ) );
					return true;
				}
				switch ($args [0]) {
					case "add" :
						if (! isset ( $args [1] ) or ($args [1] != "su" and $args [1] != "staff")) {
							$this->message ( $player, $this->get ( "help-passwd-add" ) );
							return true;
						}
						if (! isset ( $args [2] )) {
							$this->message ( $player, $this->get ( "help-passwd-add" ) );
							return true;
						}
						$passkey = $args;
						array_shift ( $passkey );
						array_shift ( $passkey );
						$passkey = implode ( " ", $passkey );
						
						if ($args [1] == "su") {
							$this->db ["su"] [$passkey] = [ 
									"registed" => date ( "Y-m-d H:i:s" ),
									"lastlogIn" => null,
									"firstLoginName" => null,
									"lastLoginIP" => null 
							];
						}
						if ($args [1] == "staff") {
							$this->db ["staff"] [$passkey] = [ 
									"registed" => date ( "Y-m-d H:i:s" ),
									"firstlogIn" => date ( "Y-m-d H:i:s" ),
									"firstLoginName" => null,
									"lastLoginIP" => null 
							];
						}
						
						$this->message ( $player, $this->get ( "passwd-passkey-added" ) );
						break;
					case "del" :
						if (! isset ( $args [1] )) {
							$this->message ( $player, $this->get ( "help-passwd-del" ) );
							return true;
						}
						if (isset ( $this->db ["su"] [$args [1]] )) {
							unset ( $this->db ["su"] [$args [1]] );
							$this->message ( $player, $this->get ( "passwd-passkey-deleted" ) );
						} else if (isset ( $this->db ["staff"] [$args [1]] )) {
							unset ( $this->db ["staff"] [$args [1]] );
							$this->message ( $player, $this->get ( "passwd-passkey-deleted" ) );
						} else {
							$this->message ( $player, $this->get ( "su-not-exist-that-passkey" ) );
						}
						break;
					case "reset" :
						$this->db ["su"] = [ ];
						$this->message ( $player, $this->get ( "passwd-passkey-resetted" ) );
						break;
					case "list" :
						$this->message ( $player, $this->get ( "show-all-passkey" ) );
						if (isset ( $this->db ["su"] )) {
							$index = 1;
							foreach ( $this->db ["su"] as $key => $data ) {
								$date = $this->db ["su"] [$key] ["registed"];
								$firstLoginName = $this->db ["su"] [$key] ["firstLoginName"];
								$this->message ( $player, "[SU/{$index}] {$key} [$date][{$firstLoginName}]" );
								$index ++;
							}
						}
						if (isset ( $this->db ["staff"] )) {
							$index = 1;
							foreach ( $this->db ["staff"] as $key => $date ) {
								$date = $this->db ["staff"] [$key] ["registed"];
								$firstLoginName = $this->db ["staff"] [$key] ["firstLoginName"];
								$this->message ( $player, "[ST/{$index}] {$key} [$date][{$firstLoginName}]" );
								$index ++;
							}
						}
						break;
					default :
						$this->message ( $player, $this->get ( "help-passwd-add" ) );
						$this->message ( $player, $this->get ( "help-passwd-del" ) );
						$this->message ( $player, $this->get ( "help-passwd-reset" ) );
						$this->message ( $player, $this->get ( "help-passwd-list" ) );
						break;
				}
				break;
		}
		return true;
	}
	public function checkBruteForce(CommandSender $player) {
		if (! $player instanceof Player)
			return;
		if (! isset ( $this->brute_force [$player->getName ()] )) {
			$this->brute_force [$player->getName ()] = 1;
		} else {
			if (++ $this->brute_force [$player->getName ()] > 10) {
				$player->kick ( "brute-forcing" );
				$this->getServer ()->blockAddress ( $player->getAddress (), 300 );
			}
		}
	}
	// -------------------------------------------------------------
	public function onPlayerJoin(PlayerJoinEvent $event) {
		if ($event->getPlayer () instanceof Player) {
			if ($event->getPlayer ()->isOp ())
				$event->getPlayer ()->setOp ( false );
			$event->getPlayer ()->setGamemode ( $this->getServer ()->getGamemode () );
		}
	}
	public function onPlayerQuit(PlayerQuitEvent $event) {
		if ($event->getPlayer () instanceof Player) {
			if ($event->getPlayer ()->isOp ())
				$event->getPlayer ()->setOp ( false );
			$event->getPlayer ()->setGamemode ( $this->getServer ()->getGamemode () );
		}
	}
	public function onPlayerKick(PlayerKickEvent $event) {
		if ($event->getPlayer () instanceof Player) {
			if ($event->getPlayer ()->isOp ())
				$event->getPlayer ()->setOp ( false );
			$event->getPlayer ()->setGamemode ( $this->getServer ()->getGamemode () );
		}
	}
	// -------------------------------------------------------------
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
	public function message(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	// ----------------------------------------------------------------------------------
}

?>