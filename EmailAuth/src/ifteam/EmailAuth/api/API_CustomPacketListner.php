<?php

namespace ifteam\EmailAuth\api;

use pocketmine\event\Listener;
use pocketmine\plugin\Plugin;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\utils\TextFormat;
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
use pocketmine\event\player\PlayerKickEvent;
use ifteam\EmailAuth\task\EmailSendTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\Long;
use pocketmine\nbt\tag\Double;
use pocketmine\nbt\tag\String;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Int;
use pocketmine\nbt\tag\Float;
use pocketmine\nbt\tag\Short;
use pocketmine\nbt\tag\Byte;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item as ItemItem;
use pocketmine\entity\Effect;
use pocketmine\inventory\InventoryHolder;
use pocketmine\event\player\PlayerCommandPreprocessEvent;

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
			$this->plugin->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CustomPacketTask ( $this ), 50 );
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
				if ($progress > 10) {
					foreach ( $this->onlineUserList as $username => $address ) {
						if ($ipport == $address) {
							unset ( $this->onlineUserList [$username] );
						}
					}
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
	public function getPlayerDataFile($name) {
		$name =\strtolower ( $name );
		$path = $this->plugin->getServer ()->getDataPath () . "players/";
		if (\file_exists ( $path . "$name.dat" )) {
			return mb_convert_encoding ( file_get_contents ( $path . "$name.dat" ), "UTF-8", "ISO-8859-1" );
		} else {
			return null;
		}
	}
	public function getPlayerData($name, $data) {
		$name = \strtolower ( $name );
		$path = $this->plugin->getServer ()->getDataPath () . "players/";
		if ($data !== null) {
			$data = mb_convert_encoding ( $data, "ISO-8859-1", "UTF-8" );
			try {
				$nbt = new NBT ( NBT::BIG_ENDIAN );
				$nbt->readCompressed ( $data );
				return $nbt->getData ();
			} catch ( \Exception $e ) { // zlib decode error / corrupt data
				return null;
			}
		} else {
			$this->plugin->getLogger ()->notice ( $this->plugin->getServer ()->getLanguage ()->translateString ( "pocketmine.data.playerNotFound", [ 
					$name 
			] ) );
		}
		$spawn = $this->plugin->getServer ()->getDefaultLevel ()->getSafeSpawn ();
		$nbt = new Compound ( "", [ 
				new Long ( "firstPlayed", \floor (\microtime ( \true ) * 1000 ) ),
				new Long ( "lastPlayed", \floor (\microtime ( \true ) * 1000 ) ),
				new Enum ( "Pos", [ 
						new Double ( 0, $spawn->x ),
						new Double ( 1, $spawn->y ),
						new Double ( 2, $spawn->z ) 
				] ),
				new String ( "Level", $this->plugin->getServer ()->getDefaultLevel ()->getName () ),
				new Enum ( "Inventory", [ ] ),
				new Compound ( "Achievements", [ ] ),
				new Int ( "playerGameType", $this->plugin->getServer ()->getGamemode () ),
				new Enum ( "Motion", [ 
						new Double ( 0, 0.0 ),
						new Double ( 1, 0.0 ),
						new Double ( 2, 0.0 ) 
				] ),
				new Enum ( "Rotation", [ 
						new Float ( 0, 0.0 ),
						new Float ( 1, 0.0 ) 
				] ),
				new Float ( "FallDistance", 0.0 ),
				new Short ( "Fire", 0 ),
				new Short ( "Air", 300 ),
				new Byte ( "OnGround", 1 ),
				new Byte ( "Invulnerable", 0 ),
				new String ( "NameTag", $name ) 
		] );
		$nbt->Pos->setTagType ( NBT::TAG_Double );
		$nbt->Inventory->setTagType ( NBT::TAG_Compound );
		$nbt->Motion->setTagType ( NBT::TAG_Double );
		$nbt->Rotation->setTagType ( NBT::TAG_Float );
		return $nbt;
	}
	/**
	 * Apply the user nbt
	 *
	 * @param string $username        	
	 * @param string $data        	
	 */
	public function applyItemData($username, $data) {
		$player = $this->plugin->getServer ()->getPlayer ( $username );
		
		$compound = $data;
		if (! $compound instanceof Compound) {
			return false;
		}
		if (! $player instanceof Player) {
			$this->plugin->getServer ()->saveOfflinePlayerData ( $username, $compound );
			return true;
		} else {
			if (! isset ( explode ( ".", $player->getAddress () )[3] )) { // Check DummyPlayer
				$this->plugin->getServer ()->saveOfflinePlayerData ( $username, $compound );
				return true;
			}
			if ((! $player instanceof InventoryHolder) or ($player->getInventory () == null)) {
				$this->plugin->getServer ()->saveOfflinePlayerData ( $username, $compound );
				return true;
			}
			// Human initialize
			if (! ($player instanceof Player)) {
				if (isset ( $compound->NameTag )) {
					$player->setNameTag ( $compound ["NameTag"] );
				}
				if (isset ( $compound->Skin ) and $compound->Skin instanceof Compound) {
					$player->setSkin ( $compound->Skin ["Data"], $compound->Skin ["Slim"] > 0 );
				}
			}
			if (isset ( $compound->Inventory ) and $compound->Inventory instanceof Enum) {
				foreach ( $compound->Inventory as $item ) {
					if ($item ["Slot"] >= 0 and $item ["Slot"] < 9) { // Hotbar
						$player->getInventory ()->setHotbarSlotIndex ( $item ["Slot"], isset ( $item ["TrueSlot"] ) ? $item ["TrueSlot"] : - 1 );
					} elseif ($item ["Slot"] >= 100 and $item ["Slot"] < 104) { // Armor
						$player->getInventory ()->setItem ( $player->getInventory ()->getSize () + $item ["Slot"] - 100, ItemItem::get ( $item ["id"], $item ["Damage"], $item ["Count"] ) );
					} else {
						$player->getInventory ()->setItem ( $item ["Slot"] - 9, ItemItem::get ( $item ["id"], $item ["Damage"], $item ["Count"] ) );
					}
				}
			}
			
			// Living initialize
			if (isset ( $compound->HealF )) {
				$compound->Health = new Short ( "Health", ( int ) $compound ["HealF"] );
				unset ( $compound->HealF );
			}
			if (! isset ( $compound->Health ) or ! ($compound->Health instanceof Short)) {
				$compound->Health = new Short ( "Health", $player->getMaxHealth () );
			}
			$player->setHealth ( $compound ["Health"] );
			
			// Entity initialize
			if (isset ( $compound->ActiveEffects )) {
				foreach ( $compound->ActiveEffects->getValue () as $e ) {
					$effect = Effect::getEffect ( $e ["Id"] );
					if ($effect === \null) {
						continue;
					}
					$effect->setAmplifier ( $e ["Amplifier"] )->setDuration ( $e ["Duration"] )->setVisible ( $e ["ShowParticles"] > 0 );
					
					$player->addEffect ( $effect );
				}
			}
			if (isset ( $compound->CustomName )) {
				$player->setNameTag ( $compound ["CustomName"] );
				$player->setNameTagVisible ( $compound ["CustomNameVisible"] > 0 );
			}
		}
		return true;
	}
	/**
	 * Apply the EconomyData
	 *
	 * @param string $username        	
	 * @param string $money        	
	 */
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
			CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
		} else {
			// economySyncro
			// master->slave = [passcode, economySyncro, username, money]
			foreach ( $this->updateList as $ipport => $datas ) {
				$explode = explode ( ":", $ipport );
				CPAPI::sendPacket ( new DataPacket ( $explode [0], $explode [1], json_encode ( $data ) ) );
			}
		}
	}
	/**
	 * Called when the user logs in
	 *
	 * @param PlayerPreLoginEvent $event        	
	 */
	public function onJoin(PlayerPreLoginEvent $event) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			if (! $event->getPlayer () instanceof Player) {
				return;
			}
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
			$data = [ 
					$this->plugin->getConfig ()->get ( "passcode" ),
					"defaultInfoRequest",
					$event->getPlayer ()->getName (),
					$event->getPlayer ()->getAddress () 
			];
			/* defaultInfoRequest */
			/* slave->master = [passcode, defaultInfoRequest, username, IP] */
			/* master->slave = [passcode, defaultInfoRequest, username, IsAllowAccess[true|false], IsRegistered[true|false], IsAutoLogin[true|false], NBT] */
			CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
			
			/* Fix $Achivevements Non Exist Problem */
			$nbt = $this->plugin->getServer ()->getOfflinePlayerData ( $event->getPlayer ()->getName () );
			if ($nbt instanceof Compound) {
				if (! isset ( $nbt->Achievements )) {
					$nbt->Achievements = new Compound ( "Achievements", [ ] );
					$this->plugin->getServer ()->saveOfflinePlayerData ( $event->getPlayer ()->getName (), $nbt );
				}
			}
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
		$this->deauthenticatePlayer ( $player );
	}
	public function deauthenticatePlayer(Player $player) {
		$this->needAuth [$player->getName ()] = true;
		if (isset ( $this->tmpDb [$player->getName ()] )) {
			if ($this->tmpDb [$player->getName ()] ["isCheckAuthReady"]) {
				$this->plugin->needReAuthMessage ( $player );
				if ($this->tmpDb [$player->getName ()] ["lockDomain"] != null) {
					$msg = str_replace ( "%domain%", $this->tmpDb [$player->getName ()] ["lockDomain"], $this->plugin->get ( "you-can-use-email-domain" ) );
					$this->plugin->message ( $player, $msg );
					$this->plugin->message ( $player, $this->plugin->get ( "you-need-a-register" ) );
				}
				return;
			}
			if ($this->tmpDb [$player->getName ()] ["isRegistered"]) {
				$this->plugin->loginMessage ( $player );
			} else {
				$this->plugin->registerMessage ( $player );
				if ($this->tmpDb [$player->getName ()] ["lockDomain"] != null) {
					$msg = str_replace ( "%domain%", $this->tmpDb [$player->getName ()] ["lockDomain"], $this->plugin->get ( "you-can-use-email-domain" ) );
					$this->plugin->message ( $player, $msg );
					$this->plugin->message ( $player, $this->plugin->get ( "you-need-a-register" ) );
				}
			}
		}
	}
	/**
	 * Authenticate the Player
	 *
	 * @param Player $player        	
	 */
	public function authenticatePlayer(Player $player) {
		if (isset ( $this->needAuth [$player->getName ()] ))
			unset ( $this->needAuth [$player->getName ()] );
		if (isset ( $this->standbyAuth [$player->getName ()] ))
			unset ( $this->standbyAuth [$player->getName ()] );
	}
	/**
	 * Called when the user logs out
	 *
	 * @param PlayerQuitEvent $event        	
	 */
	public function onQuit(PlayerQuitEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			unset ( $this->standbyAuth [$event->getPlayer ()->getName ()] );
			return;
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			unset ( $this->needAuth [$event->getPlayer ()->getName ()] );
			return;
		}
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave") {
			return;
		}
		// logoutRequest
		// slave->master = [passcode, logoutRequest, username, IP, isUserGenerate]
		$data = [ 
				$this->plugin->getConfig ()->get ( "passcode" ),
				"logoutRequest",
				$event->getPlayer ()->getName (),
				$event->getPlayer ()->getAddress (),
				false 
		];
		CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
		
		$event->getPlayer ()->save ();
		// itemSyncro
		// slave->master = [passcode, itemSyncro, username, itemData]
		$data = [ 
				$this->plugin->getConfig ()->get ( "passcode" ),
				"itemSyncro",
				$event->getPlayer ()->getName (),
				$this->getPlayerDataFile ( $event->getPlayer ()->getName () ) 
		];
		CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
	}
	public function alreadyLogined(Player $player) {
		$player->kick ( $this->plugin->get ( "already-connected" ) );
	}
	public function onPlayerKickEvent(PlayerKickEvent $event) {
		if ($event->getReason () == $this->plugin->get ( "already-connected" )) {
			$event->setQuitMessage ( "" );
		}
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			unset ( $this->standbyAuth [$event->getPlayer ()->getName ()] );
			return;
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			unset ( $this->needAuth [$event->getPlayer ()->getName ()] );
			return;
		}
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave") {
			return;
		}
		// logoutRequest
		// slave->master = [passcode, logoutRequest, username, IP, isUserGenerate]
		$data = [ 
				$this->plugin->getConfig ()->get ( "passcode" ),
				"logoutRequest",
				$event->getPlayer ()->getName (),
				$event->getPlayer ()->getAddress (),
				false 
		];
		CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
		
		$event->getPlayer ()->save ();
		// itemSyncro
		// slave->master = [passcode, itemSyncro, username, itemData]
		$data = [ 
				$this->plugin->getConfig ()->get ( "passcode" ),
				"itemSyncro",
				$event->getPlayer ()->getName (),
				$this->getPlayerDataFile ( $event->getPlayer ()->getName () ) 
		];
		CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
	}
	public function onPlayerCommandPreprocessEvent(PlayerCommandPreprocessEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] ) or isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$mes = explode ( " ", $event->getMessage () );
			switch ($mes [0]) {
				case "/" . $this->plugin->get ( "login" ) :
				case "/" . $this->plugin->get ( "logout" ) :
				case "/" . $this->plugin->get ( "register" ) :
					break;
				default :
					$this->plugin->message ( $event->getPlayer (), $this->plugin->get ( "youre-not-yet-login" ) );
					$event->setCancelled ();
					break;
			}
		}
	}
	public function onCommand(CommandSender $player, Command $command, $label, array $args) {
		if ($this->plugin->getConfig ()->get ( "servermode", null ) != "slave")
			return true;
		if (isset ( $this->standbyAuth [$player->getName ()] )) {
			$this->standbyAuthenticatePlayer ( $player );
			return true;
		}
		switch (strtolower ( $command->getName () )) {
			case $this->plugin->get ( "login" ) :
				// loginRequest
				// slave->master = [passcode, loginRequest, username, password_hash, IP]
				if (! isset ( $this->needAuth [$player->getName ()] )) {
					$this->plugin->message ( $player, $this->plugin->get ( "already-logined" ) );
					return true;
				}
				if (! isset ( $args [0] )) {
					$this->plugin->loginMessage ( $player );
					return true;
				}
				$username = $player->getName ();
				$password_hash = $this->plugin->hash ( strtolower ( $username ), $args [0] );
				$address = $player->getAddress ();
				$this->plugin->message ( $player, $this->plugin->get ( "proceed-to-login-please-wait" ) );
				$data = [ 
						$this->plugin->getConfig ()->get ( "passcode" ),
						"loginRequest",
						$username,
						$password_hash,
						$address 
				];
				CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
				break;
			case $this->plugin->get ( "logout" ) :
				// logoutRequest
				// slave->master = [passcode, logoutRequest, username, IP, isUserGenerate]
				if (isset ( $this->needAuth [$player->getName ()] )) {
					$this->plugin->loginMessage ( $player );
					return true;
				}
				$data = [ 
						$this->plugin->getConfig ()->get ( "passcode" ),
						"logoutRequest",
						$player->getName (),
						$player->getAddress (),
						true 
				];
				$this->plugin->message ( $player, $this->plugin->get ( "proceed-to-logout-please-wait" ) );
				CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
				break;
			case $this->plugin->get ( "register" ) :
				// registerRequest
				// slave->master = [passcode, registerRequest, username, password, IP, email]
				if (! isset ( $this->needAuth [$player->getName ()] )) {
					$this->plugin->message ( $player, $this->plugin->get ( "already-logined" ) );
					return true;
				}
				if (! isset ( $args [1] )) {
					$this->plugin->message ( $player, $this->plugin->get ( "you-need-a-register" ) );
					return true;
				}
				$temp = $args;
				array_shift ( $temp );
				$password = implode ( " ", $temp );
				unset ( $temp );
				
				if (strlen ( $password ) > 50) {
					$this->plugin->message ( $player, $this->plugin->get ( "password-is-too-long" ) );
					return true;
				}
				if (! $this->plugin->db->checkAuthReady ( $player->getName () )) {
					if (strlen ( $password ) < $this->plugin->getConfig ()->get ( "minPasswordLength", 5 )) {
						$this->plugin->message ( $player, $this->plugin->get ( "too-short-password" ) );
						return true;
					}
				} else {
					if (! $this->plugin->db->checkAuthReadyKey ( $player->getName (), $password )) {
						$this->plugin->message ( $player, $this->plugin->get ( "wrong-password" ) );
						if ($player instanceof Player) {
							if (isset ( $this->plugin->wrongauth [strtolower ( $player->getAddress () )] )) {
								$this->plugin->wrongauth [$player->getAddress ()] ++;
							} else {
								$this->plugin->wrongauth [$player->getAddress ()] = 1;
							}
						}
						return true;
					}
				}
				if (is_numeric ( $args [0] )) {
					/* checkAuthCode */
					/* slave->master = [passcode, checkAuthCode, username, authCode, passcode_hash] */
					/* master->slave = [passcode, checkAuthCode, username, email, isSuccess, orAuthCodeNotExist, passcode_hash] */
					$password_hash = $this->plugin->hash ( strtolower ( $player->getName () ), $password );
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"checkAuthCode",
							$player->getName (),
							$args [0],
							$password_hash 
					];
					$this->plugin->message ( $player, $this->plugin->get ( "request-an-authorization-code" ) );
					CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
				} else {
					// 이메일!
					$e = explode ( '@', $args [0] );
					if (! isset ( $e [1] )) {
						$this->plugin->message ( $player, $this->plugin->get ( "wrong-email-type" ) );
						return true;
					}
					$e1 = explode ( '.', $e [1] );
					if (! isset ( $e1 [1] )) {
						$this->plugin->message ( $player, $this->plugin->get ( "wrong-email-type" ) );
						return true;
					}
					/* checkisRegistered */
					/* slave->master = [passcode, checkisRegistered, username, email] */
					$this->plugin->message ( $player, $this->plugin->get ( "check-email-registered" ) );
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"checkisRegistered",
							$player->getName (),
							$e [0] . "@" . $e [1] 
					];
					CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
				}
				break;
			case $this->plugin->get ( "unregister" ) :
				// unregisterRequest
				// slave->master = [passcode, unregisterRequest, username]
				if (isset ( $this->needAuth [$player->getName ()] )) {
					$this->plugin->loginMessage ( $player );
					return true;
				}
				$data = [ 
						$this->plugin->getConfig ()->get ( "passcode" ),
						"unregisterRequest",
						$player->getName () 
				];
				CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
				$this->plugin->message ( $player, $this->plugin->get ( "proceed-to-unregister-please-wait" ) );
				break;
		}
		return true;
	}
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = json_decode ( $ev->getPacket ()->data );
		if (! is_array ( $data ) or $data [0] != $this->plugin->getConfig ()->get ( "passcode", false )) {
			return;
		}
		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "master") {
			switch ($data [1]) {
				case "online" :
					// online
					// slave->master = [passcode, online]
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
				case "checkisRegistered":
					/* checkisRegistered */
					/* slave->master = [passcode, checkisRegistered, username, email] */
					/* master->slave = [passcode, checkisRegistered, username, email, isRegistered[true||false] */
					$username = $data [2];
					$email = $data [3];
					
					if ($this->plugin->db->getUserData ( $email ) !== false) {
						$isRegistered = true;
					} else {
						$isRegistered = false;
					}
					
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"checkisRegistered",
							$username,
							$email,
							$isRegistered 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "sendAuthCode":
					/* sendAuthCode */
					/* slave->master = [passcode, sendAuthCode, username, email] */
					/* master->slave = [passcode, sendAuthCode, username, email, authcodeSented */
					$username = $data [2];
					$email = $data [3];
					
					$verifyNotWrong = false;
					$verifyNotRegistered = false;
					
					$check = explode ( "@", $email );
					if (isset ( $check [1] )) {
						$lockDomain = $this->plugin->db->getLockDomain ();
						if ($lockDomain != null and $check [1] == $lockDomain) {
							$verifyNotWrong = true;
						}
					}
					if (! $this->plugin->db->checkUserData ( $email )) {
						$verifyNotRegistered = true;
					}
					if ($verifyNotWrong != false and $verifyNotRegistered != false) {
						$nowTime = date ( "Y-m-d H:i:s" );
						$serverName = $this->plugin->getConfig ()->get ( "serverName", "" );
						
						$authCode = $this->plugin->authCodeGenerator ( 6 );
						$this->plugin->authcode [$username] = [ 
								"authcode" => $authCode,
								"email" => $email 
						];
						
						$task = new EmailSendTask ( $email, $username, $nowTime, $serverName, $authCode, $this->plugin->getConfig ()->getAll (), $this->plugin->getDataFolder () . "signform.html" );
						$this->plugin->getServer ()->getScheduler ()->scheduleAsyncTask ( $task );
						
						$data = [ 
								$this->plugin->getConfig ()->get ( "passcode" ),
								"sendAuthCode",
								$username,
								$email,
								true 
						];
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					} else {
						$data = [ 
								$this->plugin->getConfig ()->get ( "passcode" ),
								"sendAuthCode",
								$username,
								$email,
								false 
						];
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					}
					break;
				case "checkAuthCode" :
					/* checkAuthCode */
					/* slave->master = [passcode, checkAuthCode, username, authCode, passcode_hash] */
					/* master->slave = [passcode, checkAuthCode, username, email, isSuccess, orAuthCodeNotExist, passcode_hash] */
					$username = $data [2];
					$authCode = $data [3];
					$passcode_hash = $data [4];
					
					$isSuccess = false;
					$orAuthCodeNotExist = false;
					if (isset ( $this->plugin->authcode [$username] )) {
						if ($this->plugin->authcode [$username] ["authcode"] == $authCode) {
							$isSuccess = true;
						} else {
							$isSuccess = false;
						}
						$email = $this->plugin->authcode [$username] ["email"];
					} else {
						$email = null;
						$orAuthCodeNotExist = true;
					}
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"checkAuthCode",
							$username,
							$email,
							$isSuccess,
							$orAuthCodeNotExist,
							$passcode_hash 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "deleteAuthCode":
					/* deleteAuthCode */
					/* slave->master = [passcode, deleteAuthCode, username, email] */
					$username = $data [2];
					$email = $data [3];
					if (isset ( $this->plugin->authcode [$username] )) {
						if ($this->plugin->authcode [$username] ["email"] == $email) {
							unset ( $this->plugin->authcode [$username] );
						}
					}
					break;
				case "defaultInfoRequest" :
					/* defaultInfoRequest */
					/* slave->master = [passcode, defaultInfoRequest, username, IP] */
					/* master->slave = [passcode, defaultInfoRequest, username, isConnected[true|false], isRegistered[true||false], isAutoLogin[true||false], NBT, isCheckAuthReady, lockDomain] */
					$requestedUserName = $data [2];
					$requestedUserIp = $data [3];
					
					// IPCHECK
					$email = $this->plugin->db->getEmailToIp ( $requestedUserIp );
					$userdata = $this->plugin->db->getUserData ( $email );
					if ($email === false or $userdata === false) {
						// did not join
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
						if ($userdata ["name"] == $requestedUserName) {
							$isAutoLogin = true;
							$isRegistered = true;
							$this->onlineUserList [$requestedUserName] = $ev->getPacket ()->address . ":" . $ev->getPacket ()->port;
							$this->plugin->db->updateIPAddress ( $email, $requestedUserIp );
						} else {
							$isAutoLogin = false;
							$isRegistered = false;
						}
						$NBT = $this->getPlayerDataFile ( $requestedUserName );
					}
					
					// EMAIL-CHECK
					if ($email === false or $userdata === false) {
						$email = $this->plugin->db->getEmailToName ( $requestedUserName );
						$userdata = $this->plugin->db->getUserData ( $email );
						if ($email === false or $userdata === false) {
							// did not join
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
							$isAutoLogin = false;
							$isRegistered = true;
							$NBT = $this->getPlayerDataFile ( $requestedUserName );
						}
					}
					
					$isCheckAuthReady = $this->plugin->db->checkAuthReady ( $requestedUserName );
					$lockDomain = $this->plugin->db->getLockDomain ();
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"defaultInfoRequest",
							$requestedUserName,
							$isConnect,
							$isRegistered,
							$isAutoLogin,
							$NBT,
							$isCheckAuthReady,
							$lockDomain 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "loginRequest" :
					// loginRequest
					// slave->master = [passcode, loginRequest, username, password_hash, IP]
					// master->slave = [passcode, loginRequest, username, isSuccess[true||false]]
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
							$isSuccess 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "logoutRequest" :
					// logoutRequest
					// slave->master = [passcode, logoutRequest, username, IP, isUserGenerate]
					// master->slave = [passcode, logoutRequest, username, isSuccess]
					$username = $data [2];
					$address = $data [3];
					$isUserGenerate = $data [4];
					if ($isUserGenerate) {
						$result = $this->plugin->db->logout ( $this->plugin->db->getEmailToName ( $username ) );
					}
					if (isset ( $this->onlineUserList [$username] )) {
						$isSuccess = true;
						unset ( $this->onlineUserList [$username] );
					} else {
						$isSuccess = false;
					}
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"logoutRequest",
							$username,
							$isSuccess 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "registerRequest" :
					// registerRequest
					// slave->master = [passcode, registerRequest, username, password_hash, IP, email]
					// master->slave = [passcode, registerRequest, username, isSuccess[true||false]]
					$username = $data [2];
					$password_hash = $data [3];
					$address = $data [4];
					$email = $data [5];
					
					$isSuccess = $this->plugin->db->addUser ( $email, $password_hash, $address, false, $username );
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"registerRequest",
							$username,
							$isSuccess 
					];
					if ($this->plugin->db->checkAuthReady ( $username )) {
						$this->plugin->db->completeAuthReady ( $username );
					}
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "unregisterRequest" :
					// unregisterRequest
					// slave->master = [passcode, unregisterRequest, username]
					// master->slave = [passcode, unregisterRequest, username, isSuccess]
					$username = $data [2];
					$email = $this->plugin->db->getEmailToName ( $username );
					$deleteCheck = $this->plugin->db->deleteUser ( $email );
					($email === false or $deleteCheck === false) ? $isSuccess = false : $isSuccess = true;
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"unregisterRequest",
							$username,
							$isSuccess 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "itemSyncro" :
					// itemSyncro
					// slave->master = [passcode, itemSyncro, username, itemData]
					// master->slave = [passcode, itemSyncro, username, itemData]
					$username = $data [2];
					$itemData = $data [3];
					$playerdata = $this->getPlayerData ( $username, $itemData );
					$this->applyItemData ( $username, $playerdata );
					break;
				case "itemSyncroRequest" :
					// itemSyncroRequest
					// slave->master = [passcode, itemSyncro, username]
					// master->slave = [passcode, itemSyncro, username, itemData]
					$username = $data [2];
					// itemSyncro
					// slave->master = [passcode, itemSyncro, username, itemData]
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"itemSyncro",
							$username,
							$this->getPlayerDataFile ( $username ) 
					];
					CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					break;
				case "economyRequest" :
					// economyRequest
					// slave->master = [passcode, economySyncro, username]
					// master->slave = [passcode, economySyncro, username, money]
					// TODO
					break;
				case "economySyncro" :
					// economySyncro
					// slave->master = [passcode, economySyncro, username, money]
					// master->slave = [passcode, economySyncro, username, money]
					$username = $data [2];
					$money = $data [3];
					$this->applyEconomyData ( $username, $money );
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"economySyncro",
							$username,
							$money 
					];
					$target_addr = $ev->getPacket ()->address . ":" . $ev->getPacket ()->port;
					foreach ( $this->updateList as $ipport => $data ) {
						if ($target_addr == $ipport)
							continue;
						$addr = explode ( ":", $ipport );
						CPAPI::sendPacket ( new DataPacket ( $addr [0], $addr [1], json_encode ( $data ) ) );
					}
					break;
			}
		} else 

		if ($this->plugin->getConfig ()->get ( "servermode", null ) == "slave") {
			switch ($data [1]) {
				case "hello" :
					if (! isset ( $this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] )) {
						$this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] = 1;
						$this->plugin->getLogger ()->info ( TextFormat::DARK_AQUA . $ev->getPacket ()->address . ":" . $ev->getPacket ()->port . " " . $this->plugin->get ( "slavemode-first-connected" ) );
					}
					break;
				case "checkisRegistered" :
					/* checkisRegistered */
					/* slave->master = [passcode, checkisRegistered, username, email] */
					/* master->slave = [passcode, checkisRegistered, username, email, isRegistered[true||false] */
					$username = $data [2];
					$email = $data [3];
					$isRegistered = $data [4];
					
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player) {
						return;
					}
					if ($isRegistered) {
						// ALREADY REGISTERED
						$this->plugin->message ( $player, $this->plugin->get ( "that-email-is-already-registered" ) );
						return;
					} else {
						// NOT REGISTERED
						$this->plugin->message ( $player, $this->plugin->get ( "issuing-an-authorization-code" ) );
						/* sendAuthCode */
						/* slave->master = [passcode, sendAuthCode, username, email] */
						$data = [ 
								$this->plugin->getConfig ()->get ( "passcode" ),
								"sendAuthCode",
								$username,
								$email 
						];
						CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( $data ) ) );
					}
					break;
				case "sendAuthCode" :
					/* sendAuthCode */
					/* slave->master = [passcode, sendAuthCode, username, email] */
					/* master->slave = [passcode, sendAuthCode, username, email, authcodeSented */
					$username = $data [2];
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					$email = $data [3];
					$authcodeSented = $data [4];
					
					if (! $player instanceof Player)
						return;
					if ($authcodeSented) {
						$this->plugin->message ( $player, $this->plugin->get ( "mail-has-been-sent" ) );
					} else {
						$this->plugin->message ( $player, $this->plugin->get ( "rejected-an-authorization-code" ) );
					}
					break;
				case "checkAuthCode" :
					/* checkAuthCode */
					/* slave->master = [passcode, checkAuthCode, username, authCode, passcode_hash] */
					/* master->slave = [passcode, checkAuthCode, username, email, isSuccess, orAuthCodeNotExist, passcode_hash] */
					$username = $data [2];
					$email = $data [3];
					$isSuccess = $data [4];
					$orAuthCodeNotExist = $data [5];
					$passcode_hash = $data [6];
					
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player) {
						return;
					}
					if ($orAuthCodeNotExist) {
						$this->plugin->message ( $player, $this->plugin->get ( "authcode-resetted" ) );
						$this->deauthenticatePlayer ( $player );
						return;
					}
					if ($isSuccess) {
						/* registerRequest */
						/* slave->master = [passcode, registerRequest, username, password_hash, IP, email] */
						$data = [ 
								$this->plugin->getConfig ()->get ( "passcode" ),
								"registerRequest",
								$player->getName (),
								$passcode_hash,
								$player->getAddress (),
								$email 
						];
						CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
						$this->plugin->message ( $player, $this->plugin->get ( "proceed-to-register-please-wait" ) );
					} else {
						$this->plugin->message ( $player, $this->plugin->get ( "wrong-authcode" ) );
						if ($player instanceof Player) {
							if (isset ( $this->plugin->wrongauth [strtolower ( $player->getAddress () )] )) {
								$this->plugin->wrongauth [$player->getAddress ()] ++;
							} else {
								$this->plugin->wrongauth [$player->getAddress ()] = 1;
							}
						}
						$this->deauthenticatePlayer ( $player );
					}
					/* deleteAuthCode */
					/* slave->master = [passcode, deleteAuthCode, username, email] */
					$data = [ 
							$this->plugin->getConfig ()->get ( "passcode" ),
							"deleteAuthCode",
							$player->getName (),
							$email 
					];
					CPAPI::sendPacket ( new DataPacket ( $this->plugin->getConfig ()->get ( "masterip" ), $this->plugin->getConfig ()->get ( "masterport" ), json_encode ( $data ) ) );
					break;
				case "defaultInfoRequest" :
					/* defaultInfoRequest */
					/* slave->master = [passcode, defaultInfoRequest, username, IP] */
					/* master->slave = [passcode, defaultInfoRequest, username, isConnected[true|false], isRegistered[true||false], isConnected[true||false], NBT, isCheckAuthReady, lockDomain] */
					$username = $data [2];
					$isConnect = $data [3];
					$isRegistered = $data [4];
					$isAutoLogin = $data [5];
					$NBT = $data [6];
					$isCheckAuthReady = $data [7];
					$lockDomain = $data [8];
					
					$this->tmpDb [$username] = [ 
							"isConnect" => $isConnect,
							"isRegistered" => $isRegistered,
							"isAutoLogin" => $isAutoLogin,
							"isCheckAuthReady" => $isCheckAuthReady,
							"lockDomain" => $lockDomain 
					];
					
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player) {
						return;
					}
					if ($isConnect) {
						$this->alreadyLogined ( $player );
						return;
					}
					$this->applyItemData ( $username, $this->getPlayerData ( $username, $NBT ) );
					if ($isAutoLogin) {
						$this->plugin->message ( $player, $this->plugin->get ( "automatic-ip-logined" ) );
						$this->authenticatePlayer ( $player );
						return;
					}
					$this->cueAuthenticatePlayer ( $player );
					break;
				case "loginRequest" :
					// loginRequest
					// slave->master = [passcode, loginRequest, username, password_hash, IP]
					// master->slave = [passcode, loginRequest, username, isSuccess[true||false]]
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
				case "logoutRequest" :
					// logoutRequest
					// slave->master = [passcode, logoutRequest, username, IP, isUserGenerate]
					// master->slave = [passcode, logoutRequest, username, isSuccess]
					$username = $data [2];
					$isSuccess = $data [3];
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player)
						return;
					if ($isSuccess) {
						$this->plugin->message ( $player, $this->plugin->get ( "logout-complete" ) );
						$player->kick ( "logout" );
					} else {
						$this->plugin->message ( $player, $this->plugin->get ( "logout-failed" ) );
					}
					break;
				case "registerRequest" :
					// registerRequest
					// slave->master = [passcode, registerRequest, username, password, IP, email]
					// master->slave = [passcode, registerRequest, username, isSuccess[true||false]]
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
				case "unregisterRequest" :
					// unregisterRequest
					// slave->master = [passcode, unregisterRequest, username]
					// master->slave = [passcode, unregisterRequest, username, isSuccess]
					$username = $data [2];
					$isSuccess = $data [3];
					$player = $this->plugin->getServer ()->getPlayer ( $username );
					if (! $player instanceof Player)
						return;
					if ($isSuccess) {
						$player->kick ( "회원탈퇴완료" );
					} else {
						$this->plugin->message ( $player, $this->plugin->get ( "unregister-is-fail" ) );
					}
					break;
				case "itemSyncro" :
					// itemSyncro
					// slave->master = [passcode, itemSyncro, username, itemData]
					// master->slave = [passcode, itemSyncro, username, itemData]
					$username = $data [2];
					$itemData = $data [3];
					$this->applyItemData ( $username, $this->getPlayerData ( $username, $itemData ) );
					break;
				case "economyRequest" :
					// slave
					// economyRequest
					// slave->master = [passcode, economySyncro, username]
					// master->slave = [passcode, economySyncro, username, money]
					$username = $data [2];
					$money = $data [3];
					$this->applyEconomyData ( $username, $money );
					break;
				case "economySyncro" :
					// slave
					// economySyncro
					// slave->master = [passcode, economySyncro, username, money]
					// master->slave = [passcode, economySyncro, username, money]
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
			$event->getPlayer ()->teleport ( $event->getPlayer ()->getLevel ()->getSafeSpawn ( $event->getPlayer ()->getPosition () ) );
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$event->getPlayer ()->onGround = true;
			$event->getPlayer ()->teleport ( $event->getPlayer ()->getLevel ()->getSafeSpawn ( $event->getPlayer ()->getPosition () ) );
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onChat(PlayerChatEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
			return;
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
			return;
		}
	}
	public function onPlayerInteract(PlayerInteractEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerDropItem(PlayerDropItemEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			// $this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			// $this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onEntityDamage(EntityDamageEvent $event) {
		if (! $event->getEntity () instanceof Player)
			return;
		if (isset ( $this->standbyAuth [$event->getEntity ()->getName ()] )) {
			$event->setCancelled ();
			// $this->standbyAuthenticatePlayer ( $event->getEntity () );
		}
		if (isset ( $this->needAuth [$event->getEntity ()->getName ()] )) {
			$event->setCancelled ();
			// $this->deauthenticatePlayer ( $event->getEntity () );
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	public function onBlockPlace(BlockPlaceEvent $event) {
		if (isset ( $this->standbyAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->standbyAuthenticatePlayer ( $event->getPlayer () );
		}
		if (isset ( $this->needAuth [$event->getPlayer ()->getName ()] )) {
			$event->setCancelled ();
			$this->deauthenticatePlayer ( $event->getPlayer () );
		}
	}
	// -------------------------------------------------------------------------
}
?>