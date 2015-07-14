<?php

namespace ifteam\LoadBalancer;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\server\ServerCommandEvent;
use ifteam\LoadBalancer\task\LoadBalancerTask;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\Network;
use ifteam\LoadBalancer\dummy\DummyInterface;
use ifteam\LoadBalancer\dummy\DummyPlayer;
use pocketmine\utils\Utils;
use pocketmine\command\CommandSender;
use ifteam\LoadBalancer\task\GetExternalIPAsyncTask;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;
use ifteam\LoadBalancer\api\EDGEControl;

class LoadBalancer extends PluginBase implements Listener {
	private static $instance = null; /* Plug-in instance variables */
	public $messages, $db; /* Message variables, DB variables */
	public $m_version = 3; /* Current version of the message */
	public $updateList = [ ]; /* Slave Server List */
	public $cooltime = [ ]; /* Prevent access bombard */
	public $callback; /* LoadBalancerTask */
	public $dummyInterface; /* Dummy Player Interface */
	public $externalIp = null; /* Server External Ip */
	public $checkFistConnect = [ ]; /* Check First Connect */
	public $slaveData; /* SlaveMode Data */
	public function onEnable() {
		/* make Plug-in DataFolder */
		@mkdir ( $this->getDataFolder () );
		
		/* Initialize the translation */
		$this->initMessage ();
		
		/* Load Plug-in Settings */
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		
		/* Instance assignment */
		if (self::$instance == null) {
			self::$instance = $this;
		}
		
		/* If Not Exist CustomPacket Plugin, This Plugin will be disabled */
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) === null) {
			$this->getServer ()->getLogger ()->critical ( "[CustomPacket Example] CustomPacket plugin was not found. This plugin will be disabled." );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
			return;
		}
		
		/* Register command */
		$this->registerCommand ( $this->get ( "loadbalancer" ), "loadbalancer.control", $this->get ( "loadbalancer-help" ), "/" . $this->get ( "loadbalancer" ) );
		
		/* Get External IP To AsyncTask */
		$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new GetExternalIPAsyncTask ( $this->getName () ) );
		
		/* Listeners registered on PocketMine */
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		if (! isset ( $this->db ["mode"] )) {
			$this->getServer ()->getLogger ()->info ( TextFormat::DARK_AQUA . $this->get ( "please-choose-mode" ) );
		} else {
			if ($this->db ["mode"] == "master")
				$this->dummyInterface = new DummyInterface ( $this->getServer () );
			$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new LoadBalancerTask ( $this ), 20 );
			new EDGEControl ( $this );
		}
	}
	/**
	 * Reads translations
	 *
	 * @param string $var        	
	 */
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	/**
	 * Repeat every tick
	 */
	public function tick() {
		if ($this->db ["mode"] == "master") {
			$allPlayerList = [ ];
			$allMax = 0;
			foreach ( $this->updateList as $ipport => $data ) {
				// CHECK TIMEOUT
				$progress = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) ) - $this->updateList [$ipport] ["lastcontact"];
				if ($progress > 4) {
					unset ( $this->updateList [$ipport] );
					continue;
				}
				// RECALCULATING PLAYER LIST
				foreach ( $this->updateList [$ipport] ["list"] as $player ) {
					$allPlayerList [$player] = true;
				}
				// RECALCULATING MAX LIST
				$allMax += $this->updateList [$ipport] ["max"];
			}
			// APPLY MAX LIST
			$reflection_class = new \ReflectionClass ( $this->getServer () );
			$property = $reflection_class->getProperty ( 'maxPlayers' );
			$property->setAccessible ( true );
			$property->setValue ( $this->getServer (), $allMax );
			
			// RECALCULATING PLAYER LIST
			foreach ( $this->getServer ()->getOnlinePlayers () as $onlinePlayer ) {
				if (! $onlinePlayer instanceof DummyPlayer)
					continue;
				if (! isset ( $allPlayerList [$onlinePlayer->getName ()] )) {
					$onlinePlayer->loggedIn = false;
					$onlinePlayer->close ();
				}
			}
			foreach ( $allPlayerList as $name => $bool ) {
				$findPlayer = $this->getServer ()->getPlayer ( $name );
				if ($findPlayer == null)
					$this->dummyInterface->openSession ( $name );
			}
		} else if ($this->db ["mode"] == "slave") {
			$playerlist = [ ];
			foreach ( $this->getServer ()->getOnlinePlayers () as $player )
				$playerlist [] = $player->getName ();
			foreach ( $this->db ["masterList"] as $address ) {
				$address = explode ( ":", $address );
				CPAPI::sendPacket ( new DataPacket ( $address [0], $address [1], json_encode ( [ 
						$this->db ["passcode"],
						$playerlist,
						$this->getServer ()->getMaxPlayers (),
						$this->getServer ()->getPort () 
				] ) ) );
			}
		}
	}
	/**
	 * Connection packets Event handling
	 *
	 * @param DataPacketReceiveEvent $event        	
	 * @return boolean
	 */
	public function onDataPacketReceived(DataPacketReceiveEvent $event) {
		if ($event->getPacket ()->pid () == 0x82) {
			if (! isset ( $this->cooltime [$event->getPlayer ()->getAddress ()] )) {
				$this->cooltime [$event->getPlayer ()->getAddress ()] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
			} else {
				$diff = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) ) - $this->cooltime [$event->getPlayer ()->getAddress ()];
				if ($diff < 10) {
					$event->setCancelled ();
					return true;
				}
				$this->cooltime [$event->getPlayer ()->getAddress ()] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
			}
			if (isset ( $this->db ["mode"] ))
				if ($this->db ["mode"] == "master") {
					foreach ( $this->updateList as $ipport => $data ) {
						if (! isset ( $priority )) {
							$priority ["ip"] = explode ( ":", $ipport )[0];
							$priority ["port"] = $this->updateList [$ipport] ["port"];
							$priority ["list"] = count ( $this->updateList [$ipport] ["list"] );
							continue;
						}
						// Minimum connection check
						if ($priority ["list"] < 18) {
							break;
						}
						// Stable distribution
						if ($priority ["list"] > count ( $data ["list"] )) {
							if (count ( $data ["list"] ) >= $data ["max"]) {
								continue;
							}
							$priority ["ip"] = explode ( ":", $ipport )[0];
							$priority ["port"] = $this->updateList [$ipport] ["port"];
							$priority ["list"] = count ( $this->updateList [$ipport] ["list"] );
						}
					}
					if (! isset ( $priority )) {
						// NO EXTRA SERVER
						$event->setCancelled ();
						return true;
					}
					// If this setting Internal IP, change External IP
					if ($priority ["ip"] == "127.0.0.1" or $priority ["ip"] == "0.0.0.0") {
						$priority ["ip"] = $this->externalIp;
					}
					$event->getPlayer ()->dataPacket ( (new StrangePacket ( $priority ["ip"], $priority ["port"] ))->setChannel ( Network::CHANNEL_ENTITY_SPAWNING ) );
					$event->setCancelled ();
					return true;
				}
		}
		return false;
	}
	/**
	 * (non-PHPdoc)
	 *
	 * @see \pocketmine\plugin\PluginBase::onCommand()
	 */
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! isset ( $this->db ["mode"] )) {
			$this->alert ( $player, $this->get ( "please-choose-mode" ) );
			return true;
		}
		if (! isset ( $args [0] )) {
			$this->message ( $player, $this->get ( "loadbalancer-list-help" ) );
			$this->message ( $player, $this->get ( "loadbalancer-add-help" ) );
			$this->message ( $player, $this->get ( "loadbalancer-delete-help" ) );
			$this->message ( $player, $this->get ( "loadbalancer-changepw-help" ) );
			return true;
		}
		if ($this->db ["mode"] == "slave") {
			switch (strtolower ( $args [0] )) {
				case $this->get ( "loadbalancer-list" ) :
					foreach ( $this->db ["masterList"] as $index => $address ) {
						$this->message ( $player, "[" . $index . "] " . $address );
					}
					break;
				case $this->get ( "loadbalancer-add" ) :
					if (! isset ( $args [1] )) {
						$this->message ( $player, $this->get ( "loadbalancer-add-help" ) );
						return true;
					}
					$address = explode ( ":", $args [1] );
					$ip = explode ( ".", $address [0] );
					if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
						$this->message ( $player, $this->get ( "wrong-ip" ) );
						$this->message ( $player, $this->get ( "loadbalancer-add-help" ) );
						return true;
					}
					if (! is_numeric ( $address [1] ) or $address [1] <= 30 or $address [1] >= 65535) {
						$this->message ( $player, $this->get ( "wrong-port" ) );
						$this->message ( $player, $this->get ( "loadbalancer-add-help" ) );
						return true;
					}
					$this->db ["masterList"] [] = $args [1];
					$this->message ( $player, $this->get ( "master-server-added" ) );
					break;
				case $this->get ( "loadbalancer-delete" ) :
					if (! isset ( $args [1] )) {
						$this->message ( $player, $this->get ( "loadbalancer-delete-help" ) );
						return true;
					}
					$find = null;
					foreach ( $this->db ["masterList"] as $index => $address ) {
						if ($address == $args [1])
							$find = $index;
					}
					if ($find === null) {
						$this->message ( $player, $this->get ( "master-server-doesnt-exist" ) );
					} else {
						unset ( $this->db ["masterList"] [$find] );
						$this->message ( $player, $this->get ( "master-server-deleted" ) );
					}
					break;
				case $this->get ( "loadbalancer-changepw" ) :
					if (! isset ( $args [1] )) {
						$this->message ( $player, $this->get ( "loadbalancer-changepw-help" ) );
						return true;
					}
					if (mb_strlen ( $args [1], "UTF-8" ) < 8) {
						$this->message ( $player, $this->get ( "too-short-passcode" ) );
						$this->message ( $player, $this->get ( "loadbalancer-changepw-help" ) );
						return true;
					}
					$this->db ["passcode"] = $args [1];
					$this->message ( $sender, $this->get ( "passcode-selected" ) );
					break;
			}
		} else if ($this->db ["mode"] == "master") {
			switch (strtolower ( $command->getName () )) {
				case $this->get ( "loadbalancer-list" ) :
					$this->message ( $player, $this->get ( "only-available-slave-mode" ) );
					break;
				case $this->get ( "loadbalancer-add" ) :
					$this->message ( $player, $this->get ( "only-available-slave-mode" ) );
					break;
				case $this->get ( "loadbalancer-delete" ) :
					$this->message ( $player, $this->get ( "only-available-slave-mode" ) );
					break;
				case $this->get ( "loadbalancer-changepw" ) :
					if (! isset ( $args [1] )) {
						$this->message ( $player, $this->get ( "loadbalancer-changepw-help" ) );
						return true;
					}
					if (mb_strlen ( $args [1], "UTF-8" ) < 8) {
						$this->message ( $player, $this->get ( "too-short-passcode" ) );
						$this->message ( $player, $this->get ( "loadbalancer-changepw-help" ) );
						return true;
					}
					$this->db ["passcode"] = $args [1];
					$this->message ( $sender, $this->get ( "passcode-selected" ) );
					break;
			}
		}
		
		return true;
	}
	/**
	 * Manage the configuration of the load balancer
	 *
	 * @param ServerCommandEvent $event        	
	 */
	public function serverCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		$sender = $event->getSender ();
		if (! isset ( $this->db ["mode"] )) { // Select the server mode
			switch (strtolower ( $command )) {
				case "master" : // master
					$this->db ["mode"] = $command;
					$this->message ( $sender, $this->get ( "master-mode-selected" ) );
					$this->message ( $sender, $this->get ( "please-choose-passcode" ) );
					break;
				case "slave" : // slave
					$this->db ["mode"] = $command;
					$this->message ( $sender, $this->get ( "slave-mode-selected" ) );
					$this->message ( $sender, $this->get ( "please-choose-passcode" ) );
					break;
				default :
					$this->message ( $sender, $this->get ( "please-choose-mode" ) );
					break;
			}
			$event->setCancelled ();
			return;
		}
		if (! isset ( $this->db ["passcode"] )) { // Communication security password entered
			if (mb_strlen ( $command, "UTF-8" ) < 8) {
				$this->message ( $sender, $this->get ( "too-short-passcode" ) );
				$this->message ( $sender, $this->get ( "please-choose-passcode" ) );
				$event->setCancelled ();
				return;
			}
			$this->db ["passcode"] = $command;
			$this->message ( $sender, $this->get ( "passcode-selected" ) );
			if ($this->db ["mode"] == "slave") {
				$this->message ( $sender, $this->get ( "please-type-master-ip" ) );
			} else {
				$this->message ( $sender, $this->get ( "all-setup-complete" ) );
				$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new LoadBalancerTask ( $this ), 20 );
			}
			$event->setCancelled ();
			return;
		}
		if ($this->db ["mode"] == "slave") { // If the slave mode
			if (! isset ( $this->db ["masterList"] )) { // Enter the master server IP
				$address = explode ( ":", $command );
				$ip = explode ( ".", $address [0] );
				if (! isset ( $ip [3] ) or ! is_numeric ( $ip [0] ) or ! is_numeric ( $ip [1] ) or ! is_numeric ( $ip [2] ) or ! is_numeric ( $ip [3] )) {
					$this->message ( $sender, $this->get ( "wrong-ip" ) );
					$this->message ( $sender, $this->get ( "please-type-master-ip" ) );
					$event->setCancelled ();
					return;
				}
				if (! is_numeric ( $address [1] ) or $address [1] <= 30 or $address [1] >= 65535) {
					$this->message ( $sender, $this->get ( "wrong-port" ) );
					$this->message ( $sender, $this->get ( "please-type-master-ip" ) );
					$event->setCancelled ();
					return;
				}
				$this->db ["masterList"] = [ 
						$command 
				];
				$this->message ( $sender, $this->get ( "master-server-added" ) );
				$this->message ( $sender, $this->get ( "all-setup-complete" ) );
				$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new LoadBalancerTask ( $this ), 20 );
				$event->setCancelled ();
				return;
			}
		}
	}
	/**
	 * Plug-in instances returned
	 *
	 * @return \ifteam\LoadBalancer\LoadBalancer
	 */
	public static function getInstance() {
		return static::$instance;
	}
	/**
	 * Initialize the translation
	 */
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	/**
	 * Update the translation
	 *
	 * @param unknown $targetYmlName        	
	 */
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	/**
	 * Sent a message to the player
	 *
	 * @param CommandSender $player        	
	 * @param string $text        	
	 * @param string $mark        	
	 */
	public function message(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	/**
	 * Sent a alert to the player
	 *
	 * @param CommandSender $player        	
	 * @param string $text        	
	 * @param string $mark        	
	 */
	public function alert(CommandSender $player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	/**
	 * Register command
	 *
	 * @param string $name        	
	 * @param string $permission        	
	 * @param string $description        	
	 * @param string $usage        	
	 */
	public function registerCommand($name, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $name, $command );
	}
	/**
	 *
	 * @see \pocketmine\plugin\PluginBase::onDisable()
	 */
	public function onDisable() {
		/* Save Plug-in Config data */
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
		
		foreach ( $this->getServer ()->getOnlinePlayers () as $onlinePlayer ) {
			if (! $onlinePlayer instanceof DummyPlayer)
				continue;
			if (! isset ( $allPlayerList [$onlinePlayer->getName ()] )) {
				$onlinePlayer->loggedIn = false;
				$onlinePlayer->close ();
			}
		}
	}
	/**
	 * Custom Packet event processing, server connection work
	 *
	 * @param CustomPacketReceiveEvent $ev        	
	 */
	public function onPacketReceive(CustomPacketReceiveEvent $ev) {
		$data = json_decode ( $ev->getPacket ()->data );
		if (! isset ( $data [3] ) or $data [0] != $this->db ["passcode"])
			return;
		if ($this->db ["mode"] == "master") {
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["list"] = $data [1];
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["max"] = $data [2];
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["port"] = $data [3];
			$this->updateList [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] ["lastcontact"] = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
			
			if (! isset ( $this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] )) {
				$this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] = 1;
				$this->getLogger ()->info ( TextFormat::DARK_AQUA . $ev->getPacket ()->address . ":" . $ev->getPacket ()->port . " " . $this->get ( "mastermode-first-connected" ) );
				CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( [ 
						$this->db ["passcode"],
						"hello",
						"0",
						"0" 
				] ) ) );
			} else {
				CPAPI::sendPacket ( new DataPacket ( $ev->getPacket ()->address, $ev->getPacket ()->port, json_encode ( [ 
						$this->db ["passcode"],
						"online",
						count ( $this->getServer ()->getOnlinePlayers () ),
						$this->getServer ()->getMaxPlayers () 
				] ) ) );
			}
		} else if ($this->db ["mode"] == "slave") {
			switch ($data [1]) {
				case "hello" :
					if (! isset ( $this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] )) {
						$this->checkFistConnect [$ev->getPacket ()->address . ":" . $ev->getPacket ()->port] = 1;
						$this->getLogger ()->info ( TextFormat::DARK_AQUA . $ev->getPacket ()->address . ":" . $ev->getPacket ()->port . " " . $this->get ( "slavemode-first-connected" ) );
					}
					break;
				case "online" :
					$this->slaveData ["online"] = $data [2];
					$this->slaveData ["max"] = $data [3];
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
	/**
	 *
	 * GetExternalIPAsyncTask Only
	 *
	 * @param string $ip        	
	 */
	public function setExternalIp($ip) {
		$this->externalIp = $ip;
	}
}

?>