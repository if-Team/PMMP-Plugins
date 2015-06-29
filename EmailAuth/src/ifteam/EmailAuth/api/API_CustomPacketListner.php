<?php

namespace ifteam\EmailAuth\api;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerQuitEvent;
use ifteam\EmailAuth\EmailAuth;
use ifteam\EmailAuth\task\CustomPacketTask;
use pocketmine\nbt\tag\Compound;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerKickEvent;

// TODO - (연동기능)마스터모드일경우
// TODO - (연동기능)슬레이브모드일경우

// TODO - (연동기능)유저 비밀번호 및 계정정보 (IP사용반환해서 재갱신)
// TODO - (연동기능)이코노미API돈
// TODO - (연동기능)유저인벤토리 데이터

// TODO - (연동기능)타서버에 유저가 이미 접속해있으면 접속차단
// TODO - (연동기능)모든 연동작업은 비동기 스레딩으로 처리
// TODO - (연동기능)마스터에서 슬레이브로 데이터가 올때까지 대기 유저의 행동을 차단
// ----------------------------------------------------------------------------------------------
class API_CustomPacketListner implements Listener {
	/**
	 *
	 * @var EmailAuth
	 */
	private $plugin;
	/**
	 *
	 * @var Stand By Auth List
	 */
	public $standbyAuth;
	/**
	 *
	 * @var Slave Server List
	 */
	public $updateList = [ ];
	/**
	 *
	 * @var Check First Connect
	 */
	public $checkFistConnect = [ ];
	/**
	 *
	 * @var Online User List
	 */
	public $onlineUserList = [ ];
	/**
	 *
	 * @var Need Auth User List
	 */
	public $needAuth = [ ];
	/**
	 *
	 * @var Temporary DB
	 */
	public $tmpDb = [ ];
	/**
	 *
	 * @var EconomyAPI
	 */
	public $economyAPI;
	public function __construct(EmailAuth $plugin) {
		$this->plugin = $plugin;
		if ($this->plugin->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) != null) {
			$this->plugin->checkCustomPacket = true;
			if ($this->plugin->getConfig ()->get ( "usecustompacket", null ) === null) {
				$this->plugin->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->plugin->get ( "you-can-activate-custompacket" ) );
			}
			$this->plugin->getServer ()->getPluginManager ()->registerEvents ( $this, $plugin );
			$this->plugin->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CustomPacketTask ( $this ), 20 );
			$this->economyAPI = new API_EconomyAPIListner ( $this, $this->plugin );
		}
	}
	/**
	 * Called every tick
	 */
	public function tick() {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			// 'online' Packet transport
			// slave->master = [passcode, online]
			CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( [ 
					$this->plugin->getConfig ()->get ( "passcode" ),
					"online" 
			] ) ) );
		} else {
			foreach ( $this->updateList as $ipport => $data ) {
				// CHECK TIMEOUT
				$progress = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) ) - $this->updateList [$ipport] ["lastcontact"];
				if ($progress > 4) {
					unset ( $this->updateList [$ipport] );
					continue;
				}
			}
		}
	}
	/**
	 * Get a default set of servers.
	 *
	 * @param ServerCommandEvent $event        	
	 *
	 */
	public function serverCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		$sender = $event->getSender ();
		if ($this->plugin->getConfig ()->get ( "usecustompacket", false ) != true) {
			return;
		}
		// Select the server mode
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == null) {
			switch (strtolower ( $command )) {
				case "master" :
					$this->plugin->getConfig ()->set ( "servermode", $command );
					$this->plugin->message ( $sender, $this->plugin->get ( "master-mode-selected" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-passcode" ) );
					break;
				case "slave" :
					$this->plugin->getConfig ()->set ( "servermode", $command );
					$this->plugin->message ( $sender, $this->plugin->get ( "slave-mode-selected" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-passcode" ) );
					break;
				default :
					$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-mode" ) );
					break;
			}
			$event->setCancelled ();
			return;
		}
		// Communication security password entered
		if ($this->plugin->getConfig ()->get ( "passcode", null ) == null) {
			if (mb_strlen ( $command, "UTF-8" ) < 8) {
				$this->plugin->message ( $sender, $this->plugin->get ( "too-short-passcode" ) );
				$this->plugin->message ( $sender, $this->plugin->get ( "please-choose-passcode" ) );
				$event->setCancelled ();
				return;
			}
			$this->plugin->getConfig ()->set ( "passcode", $command );
			$this->plugin->message ( $sender, $this->plugin->get ( "passcode-selected" ) );
			if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
				$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-ip" ) );
			} else if ($this->plugin->getConfig ()->get ( "servermode", null ) == "master") {
				$this->plugin->message ( $sender, $this->plugin->get ( "all-setup-complete" ) );
			}
			$event->setCancelled ();
			return;
		}
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") { // If the slave mode
			if ($this->plugin->getConfig ()->get ( "masterip", null ) == null) { // Enter the master server IP
				$ip = explode ( ".", $command );
				if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
					$this->plugin->message ( $sender, $this->plugin->get ( "wrong-ip" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-ip" ) );
					$event->setCancelled ();
					return;
				}
				$this->plugin->getConfig ()->set ( "masterip", $command );
				$this->plugin->message ( $sender, $this->plugin->get ( "master-ip-selected" ) );
				$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-port" ) );
				$event->setCancelled ();
				return;
			}
			if ($this->plugin->getConfig ()->get ( "masterport", null ) == null) { // Enter the master server ports
				if (! is_numeric ( $command ) or $command <= 30 or $command >= 65535) {
					$this->plugin->message ( $sender, $this->plugin->get ( "wrong-port" ) );
					$this->plugin->message ( $sender, $this->plugin->get ( "please-type-master-port" ) );
					$event->setCancelled ();
					return;
				}
				$this->plugin->getConfig ()->set ( "masterport", $command );
				$this->plugin->message ( $sender, $this->plugin->get ( "master-port-selected" ) );
				$this->plugin->message ( $sender, $this->plugin->get ( "all-setup-complete" ) );
				$event->setCancelled ();
				return;
			}
		}
	}
	/**
	 * Returns the user nbt
	 *
	 * @param string $username        	
	 * @return null|Compound
	 */
	public function getItemData($username) {
		$data = $this->plugin->getServer ()->getOfflinePlayerData ( $username );
		if ($data instanceof Compound) {
			return json_encode ( $data );
		} else {
			return null;
		}
	}
	/**
	 * Apply the user nbt
	 *
	 * @param string $username        	
	 * @param string $data        	
	 */
	public function applyItemData($username, $data) {
		$player = $this->plugin->getServer ()->getPlayer ( $username );
		if (! $player instanceof Player) {
			echo "TEST# WRONG PLAYER CHECKED!\n";
			return;
		}
		
		$data = json_decode ( $data );
		if (! $data instanceof Compound) {
			echo "TEST# WRONG COMPOUND CHECKED!\n";
			return;
		}
		$player->namedtag = $data;
	}
	public function applyEconomyData($username, $money) {
		$this->economyAPI->setMoney ( $username, $money );
	}
	/**
	 * Called when the user changes the money
	 *
	 * @param string $username        	
	 * @param float $money        	
	 */
	public function onMoneyChangeEvent($username, $money) {
		$data = [ 
				$this->plugin->getConfig ()->get ( "passcode" ),
				"economySyncro",
				$username,
				$money 
		];
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			// economySyncro
			// slave->master = [passcode, economySyncro, username, money]
			CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), $data ) );
		} else {
			// economySyncro
			// master->slave = [passcode, economySyncro, username, money]
			foreach ( $this->updateList as $ipport => $data ) {
				$explode = explode ( ":", $ipport );
				CPAPI::sendPacket ( new DataPacket ( $explode [0], $explode [1], $data ) );
			}
		}
	}
	/**
	 * Called when the user logs in
	 *
	 * @param PlayerPreLoginEvent $event        	
	 */
	public function onLogin(PlayerPreLoginEvent $event) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			if (! $event->getPlayer () instanceof Player) {
				return;
			}
			/* defaultInfoRequest */
			/* slave->master = [passcode, defaultInfoRequest, username, IP] */
			/* master->slave = [passcode, defaultInfoRequest, username, IsAllowAccess[true|false], IsRegistered[true|false], IsAutoLogin[true|false], NBT] */
			$data = [ 
					$this->plugin->getConfig ()->get ( "passcode" ),
					"defaultInfoRequest",
					$event->getPlayer ()->getName (),
					$event->getPlayer ()->getAddress () 
			];
			CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), $data ) );
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	/**
	 * Add the user to standbyAuth Queue.
	 *
	 * @param Player $player        	
	 */
	public function standbyAuthenticatePlayer(Player $player) {
		$this->standbyAuth [$player->getName ()] = true;
		$this->plugin->message ( $player, $this->plugin->get ( "please-wait-a-certification-process" ) );
	}
	/**
	 * Start the certification process
	 *
	 * @param Player $player        	
	 */
	public function cueAuthenticatePlayer(Player $player) {
		if (isset ( $this->standbyAuth [$player->getName ()] )) {
			unset ( $this->standbyAuth [$player->getName ()] );
		}
		$this->plugin->message ( $player, $this->plugin->get ( "start-the-certification-process" ) );
		$this->needAuth [$player->getName ()] = true;
	}
	public function deauthenticatePlayer(Player $player) {
		$this->needAuth [$player->getName ()] = true;
		if (isset ( $this->tmpDb [$player->getName ()] )) {
			if ($this->tmpDb [$player->getName ()] ["isCheckAuthReady"]) {
				$this->plugin->needReAuthMessage ( $player );
				return;
			}
			if ($this->tmpDb [$player->getName ()] ["isRegistered"]) {
				$this->plugin->loginMessage ( $player );
			} else {
				$this->plugin->registerMessage ( $player );
			}
		}
	}
	public function authenticatePlayer(Player $player) {
		if (isset ( $this->needAuth [$player->getName ()] ))
			unset ( $this->needAuth [$player->getName ()] );
	}
	public function alreadyLogined(Player $player) {
		$player->kick ( $this->plugin->get ( "already-connected" ) );
	}
	public function onPlayerKickEvent(PlayerKickEvent $event) {
		if ($event->getReason () == $this->plugin->get ( "already-connected" )) {
			$event->setQuitMessage ( "" );
		}
	}
	/**
	 * Called when the user logs out
	 *
	 * @param PlayerQuitEvent $event        	
	 */
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			unset ( $this->standbyAuth [$event->getPlayer ()->getName ()] );
		}
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave") {
			return;
		}
		// logoutRequest
		// slave->master = [passcode, logoutRequest, username, IP]
		$data = [ 
				$this->plugin->getConfig ()->get ( "passcode" ),
				"logoutRequest",
				$event->getPlayer ()->getName (),
				$event->getPlayer ()->getAddress () 
		];
		CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), $data ) );
	}
	public function onCommand(CommandSender $player, Command $command, $label, array $args) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave")
			return true;
		switch (strtolower ( $command->getName () )) {
			case $this->plugin->get ( "login" ) :
				// loginRequest
				// slave->master = [passcode, loginRequest, username, password_hash, IP]
				// master->slave = [passcode, loginRequest, username, IsAccessSuccess[true||false]]
				break;
			case $this->plugin->get ( "logout" ) :
				// logoutRequest
				// slave->master = [passcode, logoutRequest, username, IP]
				break;
			case $this->plugin->get ( "register" ) :
				// registerRequest
				// slave->master = [passcode, registerRequest, username, password, IP, email]
				// master->slave = [passcode, registerRequest, username, IsAccessSuccess[true||false]]
				break;
			case $this->plugin->get ( "unregister" ) :
				// unregisterRequest
				// slave->master = [passcode, unregisterRequest, username, password_hash]
				// master->slave = [passcode, unregisterRequest, username, IsUnRegisterSuccess[true||false]]
				break;
		}
		return true;
	}
	// TODO - (연동기능)타서버에 유저가 이미 접속해있으면 접속차단
	// TODO - (연동기능)모든 연동작업은 비동기 스레딩으로 처리
	// TODO - (연동기능)마스터에서 슬레이브로 데이터가 올때까지 대기 유저의 행동을 차단
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = json_decode ( $ev->getPacket ()->data );
		if (! is_array ( $data ) or $data [0] != $this->plugin->getConfig ()->get ( "passcode", false )) {
			return;
		}
		if ($this->getConfig ()->get ( "servermode", null ) == "master") {
			switch ($data [1]) {
				case "online" :
					// online
					// slave->master = [패스코드, online]
					$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["lastcontact"] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
					if (! isset ( $this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] )) {
						$this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] = 1;
						$this->plugin->getLogger ()->info ( TextFormat::DARK_AQUA . $ev->getPacket ()->address . ":" . $ev->getPacket ()->port . " " . $this->plugin->get ( "mastermode-first-connected" ) );
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( [ 
								$this->plugin->getConfig ()->get ( "passcode" ),
								"hello",
								"0",
								"0" 
						] ) ) );
					}
					break;
				case "defaultInfoRequest" :
					/* defaultInfoRequest */
					/* slave->master = [패스코드, defaultInfoRequest, 유저명, IP] */
					/* master->slave = [패스코드, defaultInfoRequest, 유저명, 타서버접속여부[true|false], 가입여부[true||false], 자동로그인처리[true||false], 유저정보데이터] */
					$requestedUserName = $data [2];
					$requestedUserIp = $data [3];
					
					// getUserData
					$email = $this->plugin->db->getEmailToName ( $requestedUserName );
					$userdata = $this->plugin->db->getUserData ( $email );
					if ($email === false or $userdata === false) {
						// 가입안함
						$isConnect = false;
						$isRegistered = false;
						$isAutoLogin = false;
						$NBT = null;
					} else {
						if (isset ( $this->onlineUserList [$requestedUserName] )) {
							$isConnect = true;
						} else {
							$isConnect = false;
						}
						if ($userdata ["ip"] == $requestedUserIp) {
							$isAutoLogin = true;
						} else {
							$isAutoLogin = false;
						}
						$isRegistered = true;
						$NBT = $this->getItemData ( $requestedUserName );
					}
					$isCheckAuthReady = $this->plugin->db->checkAuthReady ( $requestedUserName );
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"defaultInfoRequest",
							$isConnect,
							$isRegistered,
							$isAutoLogin,
							$NBT,
							$isCheckAuthReady 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
					break;
				case "loginRequest" :
					// loginRequest
					// slave->master = [패스코드, loginRequest, 유저명, 암호해시, IP]
					// master->slave = [패스코드, loginRequest, 유저명, 접속성공여부[true||false]]
					$username = $data [2];
					$password_hash = $data [3];
					$address = $data [4];
					
					$email = $this->plugin->db->getEmailToName ( $username );
					$userdata = $this->plugin->db->getUserData ( $email );
					
					if ($email === false or $userdata === false) {
						$isSuccess = false;
					} else {
						if ($userdata ["password"] == $password_hash) {
							$isSuccess = true;
							$this->onlineUserList [$username] = $ev->getPacket ()->address . ":" . $ev->getPacket ()->port;
							$this->plugin->db->updateIPAddress ( $email, $address );
						} else {
							$isSuccess = false;
						}
					}
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"loginRequest",
							$username,
							$password_hash,
							$isSuccess 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
					break;
				case "logoutRequest" :
					// logoutRequest
					// slave->master = [패스코드, logoutRequest, 유저명, IP]
					$username = $data [2];
					$address = $data [3];
					if (isset ( $this->onlineUserList [$username] )) {
						unset ( $this->onlineUserList [$username] );
					}
					break;
				case "registerRequest" :
					// registerRequest
					// slave->master = [패스코드, registerRequest, 유저명, 암호, IP, 이메일]
					// master->slave = [패스코드, registerRequest, 유저명, 접속성공여부[true||false]]
					$username = $data [2];
					$password_hash = $data [3];
					$address = $data [4];
					$email = $data [5];
					
					$isSuccess = $this->plugin->db->addUser ( $email, $password, $address, false, $username );
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"registerRequest",
							$username,
							$isSuccess 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, $data ) );
					break;
				case "itemSyncro" :
					// itemSyncro
					// slave->master = [패스코드, itemSyncro, 유저명, itemData]
					// master->slave = [패스코드, itemSyncro, 유저명, itemData]
					$username = $data [2];
					$itemData = $data [3];
					$this->applyItemData ( $username, $itemData );
					break;
				case "economySyncro" :
					// master
					// economySyncro
					// slave->master = [패스코드, economySyncro, 유저명, 금액]
					// master->slave = [패스코드, economySyncro, 유저명, 금액]
					$username = $data [2];
					$money = $data [3];
					$this->applyEconomyData ( $username, $money );
					break;
			}
		} else if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			switch ($data [1]) {
				case "hello" :
					if (! isset ( $this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] )) {
						$this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] = 1;
						$this->plugin->getLogger ()->info ( TextFormat::DARK_AQUA . $ev->getPacket ()->address . ":" . $ev->getPacket ()->port . " " . $this->plugin->get ( "slavemode-first-connected" ) );
					}
					break;
				case "defaultInfoRequest" :
					/* defaultInfoRequest */
					/* slave->master = [패스코드, defaultInfoRequest, 유저명, IP] */
					/* master->slave = [패스코드, defaultInfoRequest, 유저명, 타서버접속여부[true|false], 가입여부[true||false], 자동로그인처리[true||false], 유저정보데이터] */
					$username = $data [2];
					$isConnect = $data [3];
					$isRegistered = $data [4];
					$isAutoLogin = $data [5];
					$NBT = $data [6];
					$isCheckAuthReady = $data [7];
					
					$this->tmpDb [$username] = [ 
							"isConnect" => $isConnect,
							"isRegistered" => $isRegistered,
							"isAutoLogin" => $isAutoLogin,
							"isCheckAuthReady" => $isCheckAuthReady 
					];
					
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player)
						return;
					if ($isConnect) {
						$this->alreadyLogined ( $player );
						return;
					}
					$this->applyItemData ( $username, $NBT );
					if ($isAutoLogin) {
						$this->plugin->message ( $player, $this->plugin->get ( "automatic-ip-logined" ) );
						$this->authenticatePlayer ( $player );
						return;
					}
					$this->cueAuthenticatePlayer ( $player );
					break;
				case "loginRequest" :
					// loginRequest
					// slave->master = [패스코드, loginRequest, 유저명, 암호해시, IP]
					// master->slave = [패스코드, loginRequest, 유저명, 접속성공여부[true||false]]
					$username = $data [2];
					$isSuccess = $data [3];
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player)
						return;
					if ($isSuccess) {
						$this->plugin->message ( $player, $this->plugin->get ( "login-is-success" ) );
						$this->authenticatePlayer ( $player );
					} else {
						$this->plugin->message ( $player, $this->plugin->get ( "login-is-failed" ) );
						$this->deauthenticatePlayer ( $player );
					}
					break;
				case "registerRequest" :
					// registerRequest
					// slave->master = [패스코드, registerRequest, 유저명, 암호, IP, 이메일]
					// master->slave = [패스코드, registerRequest, 유저명, 접속성공여부[true||false]]
					$username = $data [2];
					$isSuccess = $data [3];
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player)
						return;
					if ($isSuccess) {
						$this->plugin->message ( $player, $this->plugin->get ( "register-complete" ) );
						$this->authenticatePlayer ( $player );
					} else {
						$this->plugin->message ( $player, $this->plugin->get ( "register-failed" ) );
						$this->deauthenticatePlayer ( $player );
					}
					break;
				case "itemSyncro" :
					// itemSyncro
					// slave->master = [패스코드, itemSyncro, 유저명, itemData]
					// master->slave = [패스코드, itemSyncro, 유저명, itemData]
					$username = $data [2];
					$itemData = $data [3];
					$this->applyItemData ( $username, $itemData );
					break;
				case "economySyncro" :
					// slave
					// economySyncro
					// slave->master = [패스코드, economySyncro, 유저명, 금액]
					// master->slave = [패스코드, economySyncro, 유저명, 금액]
					$username = $data [2];
					$money = $data [3];
					$this->applyEconomyData ( $username, $money );
					break;
			}
		}
	}
	/**
	 *
	 * make Unix Time Stamp
	 *
	 * @param date $date        	
	 */
	public function makeTimestamp($date) {
		$yy = substr ( $date, 0, 4 );
		$mm = substr ( $date, 5, 2 );
		$dd = substr ( $date, 8, 2 );
		$hh = substr ( $date, 11, 2 );
		$ii = substr ( $date, 14, 2 );
		$ss = substr ( $date, 17, 2 );
		return mktime ( $hh, $ii, $ss, $mm, $dd, $yy );
	}
	// ↓ Events interception of not joined users
	// -------------------------------------------------------------------------
	public function onMove(PlayerMoveEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$event->getPlayer ()->onGround = true;
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onChat(PlayerChatEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerInteract(PlayerInteractEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerDropItem(PlayerDropItemEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onEntityDamage(EntityDamageEvent $event) {
		if (! $event->getEntity () instanceof Player)
			return;
		if (isset ( $this->standbyAuth [$event->getEntity ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getEntity () );
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onBlockPlace(BlockPlaceEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onInventoryOpen(InventoryOpenEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
	}
	// -------------------------------------------------------------------------
}
?>