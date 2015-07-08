<?php

namespace ifteam\EmailAuth;

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
use pocketmine\command\PluginCommand;
use ifteam\EmailAuth\provider\YAMLDataProvider;
use ifteam\EmailAuth\provider\SQLite3DataProvider;
use ifteam\EmailAuth\provider\MySQLDataProvider;
use ifteam\EmailAuth\provider\DummyDataProvider;
use ifteam\EmailAuth\provider\DataProvider;
use ifteam\EmailAuth\api\API_CustomPacketListner;
use ifteam\EmailAuth\task\AutoSaveTask;
use ifteam\EmailAuth\task\EmailSendTask;

class EmailAuth extends PluginBase implements Listener {
	private static $instance = null;
	public $db = [ ];
	public $needAuth = [ ];
	public $authcode = [ ];
	public $wrongauth = [ ]; // Prevent brute forcing
	public $m_version = 6;
	public $checkCustomPacket = false;
	public $api_custompacket;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		if (self::$instance == null)
			self::$instance = $this;
		
		$this->saveDefaultConfig ();
		$this->reloadConfig ();
		
		$this->db = new DataBase ( $this->getDataFolder () . "database.yml" );
		
		$this->saveResource ( "signform.html", false );
		$this->saveResource ( "config.yml", false );
		$this->initMessage ();
		
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new AutoSaveTask ( $this ), 2400 );
		$this->onActivateCheck ();
		
		$this->api_custompacket = new API_CustomPacketListner ( $this );
		
		$this->registerCommand ( $this->get ( "login" ), "EmailAuth.login", $this->get ( "login-help" ), "/" . $this->get ( "login" ) );
		$this->registerCommand ( $this->get ( "logout" ), "EmailAuth.logout", $this->get ( "logout-help" ), "/" . $this->get ( "logout" ) );
		$this->registerCommand ( $this->get ( "register" ), "EmailAuth.register", $this->get ( "register-help" ), "/" . $this->get ( "register" ) );
		$this->registerCommand ( $this->get ( "unregister" ), "EmailAuth.unregister", $this->get ( "unregister-help" ), "/" . $this->get ( "unregister" ) );
		$this->registerCommand ( "emailauth", "EmailAuth.manage", $this->get ( "manage-help" ), "/emailauth" );
		
		if (file_exists ( $this->getDataFolder () . "SimpleAuth/players" ))
			$this->getSimpleAuthData ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	/**
	 * Import data from SimpleAuth
	 */
	public function getSimpleAuthData() {
		if (! file_exists ( $this->getDataFolder () . "SimpleAuth/players" ))
			return;
		$config = (new Config ( $this->getDataFolder () . "SimpleAuth/config.yml", Config::YAML ))->getAll ();
		$provider = $config ["dataProvider"];
		switch (strtolower ( $provider )) {
			case "yaml" :
				$this->getLogger ()->debug ( "Using YAML data provider" );
				$provider = new YAMLDataProvider ( $this );
				break;
			case "sqlite3" :
				$this->getLogger ()->debug ( "Using SQLite3 data provider" );
				$provider = new SQLite3DataProvider ( $this );
				break;
			case "mysql" :
				$this->getLogger ()->debug ( "Using MySQL data provider" );
				$provider = new MySQLDataProvider ( $this );
				break;
			case "none" :
			default :
				$provider = new DummyDataProvider ( $this );
				break;
		}
		// SimpleAuth/* 폴더 검색 후 들어가서 모든 yml 읽어서 데이터화
		$folderList = $this->getFolderList ( $this->getDataFolder () . "SimpleAuth/players", "folder" );
		foreach ( $folderList as $alphabet ) {
			$ymlList = $this->getFolderList ( $this->getDataFolder () . "SimpleAuth/players/" . $alphabet, "file" );
			foreach ( $ymlList as $ymlName ) {
				$yml = (new Config ( $this->getDataFolder () . "SimpleAuth/players/" . $alphabet . "/" . $ymlName, Config::YAML ))->getAll ();
				$name = explode ( ".", $ymlName )[0];
				$this->db->addAuthReady ( mb_convert_encoding ( $name, "UTF-8" ), $yml ["hash"] );
			}
		}
		$this->rmdirAll ( $this->getDataFolder () . "SimpleAuth" );
	}
	/**
	 *
	 * It gets a list of folders or files
	 *
	 * @param string $rootDir        	
	 * @param string $filter
	 *        	= "folder" || "file" || null
	 *        	
	 * @return array $rList
	 */
	public function getFolderList($rootDir, $filter = "") {
		$handler = opendir ( $rootDir );
		$rList = array ();
		$fCounter = 0;
		while ( $file = readdir ( $handler ) ) {
			if ($file != '.' && $file != '..') {
				if ($filter == "folder") {
					if (is_dir ( $rootDir . "/" . $file )) {
						$rList [$fCounter ++] = $file;
					}
				} else if ($filter == "file") {
					if (! is_dir ( $rootDir . "/" . $file )) {
						$rList [$fCounter ++] = $file;
					}
				} else {
					$rList [$fCounter ++] = $file;
				}
			}
		}
		closedir ( $handler );
		return $rList;
	}
	/**
	 *
	 * Delete the folder to the subfolders
	 *
	 * @param string $dir        	
	 *
	 */
	public function rmdirAll($dir) {
		$dirs = dir ( $dir );
		while ( false !== ($entry = $dirs->read ()) ) {
			if (($entry != '.') && ($entry != '..')) {
				if (is_dir ( $dir . '/' . $entry )) {
					$this->rmdirAll ( $dir . '/' . $entry );
				} else {
					@unlink ( $dir . '/' . $entry );
				}
			}
		}
		$dirs->close ();
		@rmdir ( $dir );
	}
	public function onDisable() {
		$this->autoSave ();
	}
	/**
	 * Save the user information automatically
	 */
	public function autoSave() {
		$this->db->save ();
		$this->getConfig ()->save ();
	}
	/**
	 * It returns an instance of the plug-in
	 */
	public static function getInstance() {
		return static::$instance;
	}
	public function onJoin(PlayerJoinEvent $event) {
		if ($this->getConfig ()->get ( "servermode", null ) == "slave") {
			// 커스텀패킷이 작동하고 있고, 슬레이브모드면 일단 모든걸 중지 후
			// 마스터서버로의 데이터가 오고 인증이 재기되기까지 대기
			if ($this->checkCustomPacket)
				return;
		}
		$email = $this->db->getEmailToIp ( $event->getPlayer ()->getAddress () );
		$userdata = $this->db->getUserData ( $email );
		if ($email === false or $userdata === false) {
			$this->deauthenticatePlayer ( $event->getPlayer () );
			return;
		}
		if ($userdata ["ip"] == $event->getPlayer ()->getAddress ()) {
			if ($userdata ["name"] == $event->getPlayer ()->getName ()) {
				$this->message ( $event->getPlayer (), $this->get ( "automatic-ip-logined" ) );
				return;
			}
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
		// 연속으로 7회 이상틀리면 밴 처리(이메일 전송포함)
		if ($player instanceof Player) {
			if (isset ( $this->wrongauth [$player->getAddress ()] )) {
				if ($this->wrongauth [$player->getAddress ()] >= 7) {
					$this->getServer ()->blockAddress ( $player->getAddress (), 400 );
				}
			}
		}
		switch (strtolower ( $command->getName () )) {
			case $this->get ( "login" ) :
				if ($this->getConfig ()->get ( "servermode", null ) == "slave") {
					// 커스텀패킷이 작동하고 있고, 슬레이브모드면 일단 모든걸 중지 후
					// 마스터서버로의 데이터가 오고 인증이 재기되기까지 대기
					if ($this->checkCustomPacket) {
						$this->api_custompacket->onCommand ( $player, $command, $label, $args );
						return true;
					}
				}
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
							$this->alert ( $player, $this->get ( "login-is-failed" ) );
							if ($player instanceof Player) {
								if (isset ( $this->wrongauth [$player->getAddress ()] )) {
									$this->wrongauth [$player->getAddress ()] ++;
								} else {
									$this->wrongauth [$player->getAddress ()] = 1;
								}
							}
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
				if ($this->getConfig ()->get ( "servermode", null ) == "slave") {
					// 커스텀패킷이 작동하고 있고, 슬레이브모드면 일단 모든걸 중지 후
					// 마스터서버로의 데이터가 오고 인증이 재기되기까지 대기
					if ($this->checkCustomPacket) {
						$this->api_custompacket->onCommand ( $player, $command, $label, $args );
						return;
					}
				}
				$this->db->logout ( $this->db->getEmail ( $player ) );
				$this->message ( $player, $this->get ( "logout-complete" ) );
				break;
			case $this->get ( "register" ) :
				if ($this->getConfig ()->get ( "servermode", null ) == "slave") {
					// 커스텀패킷이 작동하고 있고, 슬레이브모드면 일단 모든걸 중지 후
					// 마스터서버로의 데이터가 오고 인증이 재기되기까지 대기
					if ($this->checkCustomPacket) {
						$this->api_custompacket->onCommand ( $player, $command, $label, $args );
						return true;
					}
				}
				// 가입 <이메일또는 코드> <원하는암호>
				if (! isset ( $this->needAuth [$player->getName ()] )) {
					$this->message ( $player, $this->get ( "already-logined" ) );
					return true;
				}
				if (! isset ( $args [1] )) {
					$this->message ( $player, $this->get ( "you-need-a-register" ) );
					return true;
				}
				$temp = $args;
				array_shift ( $temp );
				$password = implode ( $temp );
				unset ( $temp );
				
				if ($password > 50) {
					$this->message ( $player, $this->get ( "you-need-a-register" ) );
					return true;
				}
				if (! $this->db->checkAuthReady ( $player->getName () )) {
					if (strlen ( $password ) < $this->getConfig ()->get ( "minPasswordLength", 5 )) {
						$this->message ( $player, $this->get ( "too-short-password" ) );
						return true;
					}
				} else {
					if (! $this->db->checkAuthReadyKey ( $player->getName (), $password )) {
						$this->message ( $player, $this->get ( "wrong-password" ) );
						if ($player instanceof Player) {
							if (isset ( $this->wrongauth [strtolower ( $player->getAddress () )] )) {
								$this->wrongauth [$player->getAddress ()] ++;
							} else {
								$this->wrongauth [$player->getAddress ()] = 1;
							}
						}
						return true;
					}
				}
				if (is_numeric ( $args [0] )) {
					if (isset ( $this->authcode [$player->getName ()] )) {
						if ($this->authcode [$player->getName ()] ["authcode"] == $args [0]) {
							$password_hash = $this->hash ( strtolower ( $player->getName () ), $password );
							$result = $this->db->addUser ( $this->authcode [$player->getName ()] ["email"], $password_hash, $player->getAddress (), false, $player->getName () );
							if ($result) {
								$this->message ( $player, $this->get ( "register-complete" ) );
							} else {
								$this->message ( $player, $this->get ( "register-failed" ) );
								return true;
							}
							$this->authenticatePlayer ( $player );
							if ($this->db->checkAuthReady ( $player->getName () )) {
								$this->db->completeAuthReady ( $player->getName () );
							}
						} else {
							$this->message ( $player, $this->get ( "wrong-authcode" ) );
							if ($player instanceof Player) {
								if (isset ( $this->wrongauth [strtolower ( $player->getAddress () )] )) {
									$this->wrongauth [$player->getAddress ()] ++;
								} else {
									$this->wrongauth [$player->getAddress ()] = 1;
								}
							}
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
					if ($this->db->checkUserData ( $args [0] ) != false) {
						$this->message ( $player, $this->get ( "already-registred" ) );
						return true;
					}
					$domainLock = $this->db->getLockDomain ();
					if ($domainLock != null) {
						if ($domainLock != $e [1]) {
							$msg = str_replace ( "%domain%", $domainLock, $this->get ( "you-can-use-email-domain" ) );
							$this->message ( $player, $msg );
							$this->message ( $player, $this->get ( "you-need-a-register" ) );
							return true;
						}
					}
					$playerName = $player->getName ();
					$authCode = $this->authCodeGenerator ( 6 );
					$nowTime = date ( "Y-m-d H:i:s" );
					$serverName = $this->getConfig ()->get ( "serverName", "" );
					if (isset ( $this->wrongauth [$player->getAddress ()] )) {
						$this->wrongauth [$player->getAddress ()] ++;
					} else {
						$this->wrongauth [$player->getAddress ()] = 1;
					}
					$task = new EmailSendTask ( $args [0], $playerName, $nowTime, $serverName, $authCode, $this->getConfig ()->getAll (), $this->getDataFolder () . "signform.html" );
					$this->getServer ()->getScheduler ()->scheduleAsyncTask ( $task );
					$this->authcode [$playerName] = [ 
							"authcode" => $authCode,
							"email" => $args [0] 
					];
					$this->message ( $player, $this->get ( "mail-has-been-sent" ) );
				}
				break;
			case $this->get ( "unregister" ) :
				if ($this->getConfig ()->get ( "servermode", null ) == "slave") {
					// 커스텀패킷이 작동하고 있고, 슬레이브모드면 일단 모든걸 중지 후
					// 마스터서버로의 데이터가 오고 인증이 재기되기까지 대기
					if ($this->checkCustomPacket) {
						$this->api_custompacket->onCommand ( $player, $command, $label, $args );
						return true;
					}
				}
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
							$this->message ( $player, $this->get ( "setup-help-mail" ) );
							return true;
						}
						if ($this->getConfig ()->get ( "adminEmailHost", null ) == null) {
							$this->message ( $player, $this->get ( "adminEmailHost-doesnt-exist" ) );
							$this->message ( $player, $this->get ( "setup-help-pass" ) );
							return true;
						}
						if ($this->getConfig ()->get ( "adminEmailPort", null ) == null) {
							$this->message ( $player, $this->get ( "adminEmailPort-doesnt-exist" ) );
							$this->message ( $player, $this->get ( "setup-help-host" ) );
							return true;
						}
						if ($this->getConfig ()->get ( "adminEmailPassword", null ) == null) {
							$this->message ( $player, $this->get ( "adminEmailPassword-doesnt-exist" ) );
							$this->message ( $player, $this->get ( "setup-help-port" ) );
							return true;
						}
						$playerName = "CONSOLE";
						$authCode = $this->authCodeGenerator ( 6 );
						$nowTime = date ( "Y-m-d H:i:s" );
						$serverName = $this->getConfig ()->get ( "serverName", "" );
						$result = $this->sendRegisterMail ( $this->getConfig ()->get ( "adminEmail", null ), $playerName, $nowTime, $serverName, $authCode, true );
						if ($result)
							$this->message ( $player, $this->get ( "setup-complete" ) );
						if (! $result)
							$this->message ( $player, $this->get ( "setup-failed" ) );
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
					// 서버연동 /emailauth custompacket 으로 활성화처리
					case "custompacket" :
						$usecustompacket = $this->getConfig ()->get ( "usecustompacket", null );
						if ($usecustompacket == null) {
							$this->getConfig ()->set ( "usecustompacket", true );
							$this->message ( $player, $this->get ( "custompacket-enabled" ) );
							$this->message ( $player, $this->get ( "please-choose-mode" ) );
							return true;
						}
						if ($usecustompacket) {
							$this->getConfig ()->set ( "usecustompacket", false );
							$this->message ( $player, $this->get ( "custompacket-disabled" ) );
						} else {
							$this->getConfig ()->set ( "usecustompacket", true );
							$this->message ( $player, $this->get ( "custompacket-enabled" ) );
						}
						$this->message ( $player, $this->get ( "please-choose-mode" ) );
						break;
				}
				break;
		}
		return true;
	}
	public function deauthenticatePlayer(Player $player) {
		$this->needAuth [$player->getName ()] = true;
		if ($this->db->checkAuthReady ( $player->getName () )) {
			$this->needReAuthMessage ( $player );
			return;
		}
		if ($this->db->getEmail ( $player ) != false) {
			$this->loginMessage ( $player );
		} else {
			$this->registerMessage ( $player );
		}
	}
	public function authenticatePlayer(Player $player) {
		$this->message ( $player, $this->get ( "login-is-success" ) );
		$this->db->updateIPAddress ( $this->db->getEmail ( $player ), $player->getAddress () );
		if (isset ( $this->needAuth [$player->getName ()] ))
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
		
		$domainLock = $this->db->getLockDomain ();
		if ($domainLock != null) {
			$msg = str_replace ( "%domain%", $domainLock, $this->get ( "you-can-use-email-domain" ) );
			$this->message ( $player, $msg );
		}
	}
	public function loginMessage(CommandSender $player) {
		$this->message ( $player, $this->get ( "emailauth-notification" ) );
		$this->message ( $player, $this->get ( "you-need-a-login" ) );
	}
	public function needReAuthMessage(CommandSender $player) {
		$this->message ( $player, $this->get ( "emailauth-notification" ) );
		$this->message ( $player, $this->get ( "you-needReAuthMessage" ) );
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
		if ($istest)
			$mail->SMTPDebug = 2;
		
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
		
		$mail->smtpConnect ( array (
				'ssl' => array (
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true 
				) 
		) );
		
		if ($istest)
			echo $mail->ErrorInfo . "\n";
		return ($mail->send ()) ? true : false;
	}
	public function hash($salt, $password) {
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
		if ($mark == null)
			$mark = $this->getConfig ()->get ( "default-prefix", "" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . $text );
	}
	public function alert(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->getConfig ()->get ( "default-prefix", "" );
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
	// ↓ Events interception of not joined users
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
		if (! $event->getEntity () instanceof Player)
			return;
		if (isset ( $this->needAuth [$event->getEntity ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getEntity () );
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
	// -------------------------------------------------------------------------
}
?>