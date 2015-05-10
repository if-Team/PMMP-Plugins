<?php

namespace ifteam\emailAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\permission\PermissionAttachment;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\command\PluginCommand;

class emailAuth extends PluginBase implements Listener {
	private static $instance = null;
	public $db, $rand = [ ];
	public $needAuth = [ ];
	public $authcode = [ ];
	public $m_version = 1;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		if (self::$instance == null) self::$instance = $this;
		
		$this->saveDefaultConfig ();
		$this->reloadConfig ();
		
		$this->db = new dataBase ( $this->getDataFolder () . "database.yml" );
		
		$this->saveResource ( "signform.html", false );
		$this->saveResource ( "config.yml", false );
		$this->initMessage ();
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new AutoSaveTask ( $this ), 2400 );
		$this->onActivateCheck ();
		
		$this->registerCommand ( $this->get ( "login" ), "emailAuth.login", $this->get ( "login-help" ), "/" . $this->get ( "login" ) );
		$this->registerCommand ( $this->get ( "logout" ), "emailAuth.logout", $this->get ( "logout-help" ), "/" . $this->get ( "logout" ) );
		$this->registerCommand ( $this->get ( "register" ), "emailAuth.register", $this->get ( "register-help" ), "/" . $this->get ( "register" ) );
		$this->registerCommand ( $this->get ( "unregister" ), "emailAuth.unregister", $this->get ( "unregister-help" ), "/" . $this->get ( "unregister" ) );
		$this->registerCommand ( "emailauth", "emailAuth.manage", $this->get ( "manage-help" ), "/emailauth" );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->autoSave ();
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function autoSave() {
		$this->db->save ();
	}
	public function onJoin(PlayerJoinEvent $event) {
		if (isset ( $this->db->getAll ()["ip"][$event->getPlayer ()->getAddress ()] )) {
			$this->message ( $event->getPlayer (), $this->get ( "automatic-ip-logined" ) );
		} else {
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onActivateCheck() {
		if ($this->getConfig ()->get ( "adminEmail", null ) == null) {
			$this->getLogger ()->info ( $this->get ( "adminMail-doesnt-exist" ) );
			$this->getLogger ()->info ( $this->get ( "setup-help-mail" ) );
			return false;
		}
		if ($this->getConfig ()->get ( "adminEmailHost", null ) == null) {
			$this->getLogger ()->info ( $this->get ( "adminEmailHost-doesnt-exist" ) );
			$this->getLogger ()->info ( $this->get ( "setup-help-pass" ) );
			return false;
		}
		if ($this->getConfig ()->get ( "adminEmailPort", null ) == null) {
			$this->getLogger ()->info ( $this->get ( "adminEmailPort-doesnt-exist" ) );
			$this->getLogger ()->info ( $this->get ( "setup-help-host" ) );
			return false;
		}
		if ($this->getConfig ()->get ( "adminEmailPassword", null ) == null) {
			$this->getLogger ()->info ( $this->get ( "adminEmailPassword-doesnt-exist" ) );
			$this->getLogger ()->info ( $this->get ( "setup-help-port" ) );
			return false;
		}
		return true;
	}
	public function onCommand(CommandSender $player, Command $command, $label, array $args) {
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "login" ) :
				if (! isset ( $this->needAuth [$player->getName ()] )) {
					$this->message ( $player, $this->get ( "already-logined" ) );
					return true;
				}
				if ($this->db->getEmail ( $player )) {
					if (! isset ( $args [0] )) {
						$this->loginMessage ( $player );
						return true;
					}
					$email = $this->db->getEmail ( $player );
					if ($email != false) {
						$data = $this->db->getUserData ( $email );
						if ($data == false) {
							$this->message ( $player, $this->get ( "this-account-cant-use" ) );
							return true;
						}
						if ($data ["password"] != $args [0]) {
							echo $data ["password"] . " : " . $args [0] . "\n";
							$this->alert ( $player, $this->get ( "login-is-failed" ) );
							$this->deauthenticatePlayer ( $player );
						} else {
							$this->authenticatePlayer ( $player );
						}
					}
				} else {
					$this->registerMessage ( $player );
					return true;
				}
				break;
			case $this->get ( "logout" ) :
				$this->db->logout ( $this->db->getEmail ( $player ) );
				$this->message ( $player, $this->get ( "logout-complete" ) );
				break;
			case $this->get ( "register" ) :
				// 가입 <이메일또는 코드> <원하는암호>
				if (! isset ( $this->needAuth [$player->getName ()] )) {
					$this->message ( $player, $this->get ( "already-logined" ) );
					return true;
				}
				if (! isset ( $args [1] ) or $args [1] > 50) {
					$this->message ( $player, $this->get ( "you-need-a-register" ) );
					return true;
				}
				if (strlen ( $args [1] ) < $this->getConfig ()->get ( "minPasswordLength", 5 )) {
					$this->message ( $player, $this->get ( "too-short-password" ) );
					return true;
				}
				if (is_numeric ( $args [0] )) {
					if (isset ( $this->authcode [$player->getName ()] )) {
						if ($this->authcode [$player->getName ()] ["authcode"] == $args [0]) {
							$this->db->addUser ( $this->authcode [$player->getName ()] ["email"], $args [1], $player->getAddress (), false, $player->getName () );
							$this->message ( $player, $this->get ( "register-complete" ) );
							$this->authenticatePlayer ( $player );
						} else {
							$this->message ( $player, $this->get ( "wrong-authcode" ) );
							$this->deauthenticatePlayer ( $player );
						}
						unset ( $this->authcode [$player->getName ()] );
					} else {
						$this->message ( $player, $this->get ( "authcode-doesnt-exist" ) );
						$this->deauthenticatePlayer ( $player );
					}
				} else {
					// 이메일!
					$e = explode ( '@', $args [0] );
					if (! isset ( $e [1] )) {
						$this->message ( $player, $this->get ( "wrong-email-type" ) );
						return true;
					}
					$e1 = explode ( '.', $e [1] );
					if (! isset ( $e1 [1] )) {
						$this->message ( $player, $this->get ( "wrong-email-type" ) );
						return true;
					}
					$playerName = $player->getName ();
					$authCode = $this->authCodeGenerator ( 6 );
					$nowTime = date ( "Y-m-d H:i:s" );
					$serverName = $this->getConfig ()->get ( "serverName", "" );
					$task = new emailSendTask ( $args [0], $playerName, $nowTime, $serverName, $authCode, $this->getConfig ()->getAll (), $this->getDataFolder () . "signform.html" );
					$this->getServer ()->getScheduler ()->scheduleAsyncTask ( $task );
					$this->authcode [$playerName] = [ "authcode" => $authCode,"email" => $args [0] ];
					$this->message ( $player, $this->get ( "mail-has-been-sent" ) );
				}
				break;
			case $this->get ( "unregister" ) :
				$this->db->deleteUser ( $this->db->getEmail ( $player ) );
				$this->message ( $player, $this->get ( "unregister-is-complete" ) );
				break;
			case "emailauth" :
				if (! isset ( $args [0] )) {
					$this->message ( $player, $this->get ( "auth-help-setup" ) );
					$this->message ( $player, $this->get ( "auth-help-test" ) );
					$this->message ( $player, $this->get ( "auth-help-domain" ) );
					$this->message ( $player, $this->get ( "auth-help-length" ) );
					$this->message ( $player, $this->get ( "auth-help-whitelist" ) );
					return true;
				}
				switch (strtolower ( $args [0] )) {
					case "setup" :
						switch (strtolower ( $args [1] )) {
							case "mail" :
								if (! isset ( $args [2] )) {
									$this->message ( $player, $this->get ( "setup-help-mail" ) );
									break;
								}
								$this->getConfig ()->set ( "adminEmail", $args [2] );
								$this->message ( $player, $this->get ( "adminMail-setup-complete" ) );
								break;
							case "pass" :
								if (! isset ( $args [2] )) {
									$this->message ( $player, $this->get ( "setup-help-pass" ) );
									break;
								}
								$this->getConfig ()->set ( "adminEmailPassword", $args [2] );
								$this->message ( $player, $this->get ( "adminEmailPassword-setup-complete" ) );
								break;
							case "host" :
								if (! isset ( $args [2] )) {
									$this->message ( $player, $this->get ( "setup-help-host" ) );
									break;
								}
								$this->getConfig ()->set ( "adminEmailHost", $args [2] );
								$this->message ( $player, $this->get ( "adminEmailHost-setup-complete" ) );
								break;
							case "port" :
								if (! isset ( $args [2] )) {
									$this->message ( $player, $this->get ( "setup-help-port" ) );
									break;
								}
								$this->getConfig ()->set ( "adminEmailPort", $args [2] );
								$this->message ( $player, $this->get ( "adminEmailPort-setup-complete" ) );
								break;
							default :
								$this->message ( $player, $this->get ( "setup-help-mail" ) );
								$this->message ( $player, $this->get ( "setup-help-pass" ) );
								$this->message ( $player, $this->get ( "setup-help-host" ) );
								$this->message ( $player, $this->get ( "setup-help-port" ) );
								break;
						}
						$this->onActivateCheck ();
						break;
					case "test" :
						if ($this->getConfig ()->get ( "adminEmail", null ) == null) {
							$this->message ( $player, $this->get ( "adminMail-doesnt-exist" ) );
							$this->message ( $player, $player, $this->get ( "setup-help-mail" ) );
							return;
						}
						if ($this->getConfig ()->get ( "adminEmailHost", null ) == null) {
							$this->message ( $player, $this->get ( "adminEmailHost-doesnt-exist" ) );
							$this->message ( $player, $player, $this->get ( "setup-help-pass" ) );
							return;
						}
						if ($this->getConfig ()->get ( "adminEmailPort", null ) == null) {
							$this->message ( $player, $this->get ( "adminEmailPort-doesnt-exist" ) );
							$this->message ( $player, $player, $this->get ( "setup-help-host" ) );
							return;
						}
						if ($this->getConfig ()->get ( "adminEmailPassword", null ) == null) {
							$this->message ( $player, $this->get ( "adminEmailPassword-doesnt-exist" ) );
							$this->message ( $player, $player, $this->get ( "setup-help-port" ) );
							return;
						}
						$playerName = "CONSOLE";
						$authCode = $this->authCodeGenerator ( 6 );
						$nowTime = date ( "Y-m-d H:i:s" );
						$serverName = $this->getConfig ()->get ( "serverName", "" );
						$result = $this->sendRegisterMail ( $this->getConfig ()->get ( "adminEmail", null ), $playerName, $nowTime, $serverName, $authCode, true );
						if ($result) $this->message ( $player, $this->get ( "setup-complete" ) );
						if (! $result) $this->message ( $player, $this->get ( "setup-failed" ) );
						break;
					case "domain" :
						if (! isset ( $args [2] )) {
							$this->message ( $player, $this->get ( "auth-help-domain" ) );
							break;
						}
						$this->db->changeLockDomain ( $args [2] );
						break;
					case "length" :
						if (! isset ( $args [2] ) or ! is_numeric ( $args [2] )) {
							$this->message ( $player, $this->get ( "auth-help-length" ) );
							break;
						}
						$this->getConfig ()->set ( "minPasswordLength", $args [2] );
						break;
					case "whitelist" :
						$choose = $this->getConfig ()->get ( "minPasswordLength", false );
						$this->getConfig ()->set ( "minPasswordLength", ! $choose );
						(! $choose) ? $message = $this->get ( "whitelist-enabled" ) : $message = $this->get ( "whitelist-disabled" );
						$this->message ( $player, $message );
						break;
					default :
						$this->message ( $player, $this->get ( "auth-help-setup" ) );
						$this->message ( $player, $this->get ( "auth-help-test" ) );
						$this->message ( $player, $this->get ( "auth-help-domain" ) );
						$this->message ( $player, $this->get ( "auth-help-length" ) );
						$this->message ( $player, $this->get ( "auth-help-whitelist" ) );
						break;
				}
				break;
		}
		return true;
	}
	public function deauthenticatePlayer(Player $player) {
		$this->needAuth [$player->getName ()] = true;
		if ($this->db->getEmail ( $player ) != false) {
			$this->loginMessage ( $player );
		} else {
			$this->registerMessage ( $player );
		}
	}
	public function authenticatePlayer(Player $player) {
		$this->message ( $player, $this->get ( "login-is-success" ) );
		$this->db->updateIPAddress ( $this->db->getEmail ( $player ), $player->getAddress () );
		unset ( $this->needAuth [$player->getName ()] );
	}
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$message = $event->getMessage ();
			if ($message {0} === "/") {
				$event->setCancelled ( true );
				$command = substr ( $message, 1 );
				$args = explode ( " ", $command );
				if ($args [0] === $this->get ( "login" ) or $args [0] === $this->get ( "register" ) or $args [0] === "help") {
					$this->getServer ()->dispatchCommand ( $event->getPlayer (), $command );
				} else {
					$this->deauthenticatePlayer ( $event->getPlayer () );
				}
			} else {
				$event->setCancelled ();
			}
		}
	}
	public function authCodeGenerator($length) {
		$rand = "";
		for($i = 1; $i <= $length; $i ++)
			$rand .= mt_rand ( 0, 9 );
		return $rand;
	}
	public function onPlayerQuit(PlayerQuitEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			unset ( $this->needAuth [$event->getPlayer ()->getName ()] );
		}
	}
	public function registerMessage(CommandSender $player) {
		$this->message ( $player, $this->get ( "emailauth-notification" ) );
		$this->message ( $player, $this->get ( "you-need-a-register" ) );
	}
	public function loginMessage(CommandSender $player) {
		$this->message ( $player, $this->get ( "emailauth-notification" ) );
		$this->message ( $player, $this->get ( "you-need-a-login" ) );
	}
	public function sendRegisterMail($sendMail, $id, $time, $serverName, $code, $istest = false) {
		$signForm = file_get_contents ( $this->getDataFolder () . "signform.html" );
		$signForm = str_replace ( "##ID##", $id, $signForm );
		$signForm = str_replace ( "##TIME##", $time, $signForm );
		$signForm = str_replace ( "##SERVER##", $serverName, $signForm );
		$signForm = str_replace ( "##CODE##", $code, $signForm );
		return ($this->PHPMailer ( $sendMail, $signForm, $istest )) ? true : false;
	}
	public function PHPMailer($sendMail, $html, $istest = false) {
		$mail = new PHPMailer ();
		$mail->isSMTP ();
		$mail->SMTPDebug = 0;
		if ($istest) $mail->SMTPDebug = 2;
		
		$mail->SMTPSecure = 'tls';
		$mail->CharSet = $this->getConfig ()->get ( "encoding" );
		$mail->Encoding = "base64";
		$mail->Debugoutput = 'html';
		$mail->Host = $this->getConfig ()->get ( "adminEmailHost" );
		$mail->Port = $this->getConfig ()->get ( "adminEmailPort" );
		$mail->SMTPAuth = true;
		
		$mail->Username = explode ( "@", $this->getConfig ()->get ( "adminEmail" ) )[0];
		$mail->Password = $this->getConfig ()->get ( "adminEmailPassword" );
		
		$mail->setFrom ( $this->getConfig ()->get ( "adminEmail" ), $this->getConfig ()->get ( "serverName" ) );
		$mail->addReplyTo ( $this->getConfig ()->get ( "adminEmail" ), $this->getConfig ()->get ( "serverName" ) );
		$mail->addAddress ( $sendMail );
		$mail->Subject = $this->getConfig ()->get ( "subjectName" );
		
		$mail->msgHTML ( $html );
		
		if ($istest) echo $mail->ErrorInfo . "\n";
		return ($mail->send ()) ? true : false;
	}
	private function hash($salt, $password) {
		return bin2hex ( hash ( "sha512", $password . $salt, true ) ^ hash ( "whirlpool", $salt . $password, true ) );
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
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function message(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->getConfig ()->get ( "default-prefix", "" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . $text );
	}
	public function alert(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->getConfig ()->get ( "default-prefix", "" );
		$player->sendMessage ( TextFormat::RED . $mark . $text );
	}
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
	// -------------------------------------------------------------------------
	public function onMove(PlayerMoveEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$event->getPlayer ()->onGround = true;
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onChat(PlayerChatEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerInteract(PlayerInteractEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerDropItem(PlayerDropItemEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onEntityDamage(EntityDamageEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onBlockPlace(BlockPlaceEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onInventoryOpen(InventoryOpenEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPickupItem(InventoryPickupItemEvent $event) {
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	// -------------------------------------------------------------------------
}
?>