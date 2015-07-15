<?php

namespace ifteam\RankManager;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;

class RankManager extends PluginBase implements Listener {
	/**
	 *
	 * @var RankManager default config
	 */
	public $db;
	/**
	 *
	 * @var Users prefix data
	 */
	public $users = [ ];
	/**
	 *
	 * @var Users special prefix data
	 */
	public $specialPrefix = [ ];
	/**
	 *
	 * @var Message file version
	 */
	private $m_version = 1;
	/**
	 *
	 * @var Plug-in Instance
	 */
	private static $instance = null;
	/**
	 *
	 * @var EventListener
	 */
	public $eventListener;
	public function onEnable() {
		if (! file_exists ( $this->getDataFolder () ))
			@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		// chat example
		// [ 광산 ] [ 일반 ] hmhmmhm > 채팅메시지 견본
		
		// nametag example
		// [ 24레벨 ] [ 일반 ]
		// hmhmmhm
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ 
				"defaultPrefix" => $this->get ( "default-player-prefix" ),
				"defaultPrefixFormat" => TextFormat::GOLD . "[ %prefix% ]",
				"chatPrefix" => "%special_prefix% %prefixs% %user_name% > %message%",
				"nameTagPrefix" => "%prefixs% %user_name%",
				"rankShop" => [ ] 
		] ))->getAll ();
		
		if (self::$instance == null)
			self::$instance = $this;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->eventListener = new EventListener ( $this );
	}
	
	/**
	 * Read the Prefix information of the user
	 *
	 * @param Player|string $player        	
	 * @return Array
	 */
	public function readPrefixData($player) {
		if ($player instanceof IPlayer) {
			$username = strtolower ( $player->getName () );
		} else {
			$username = strtolower ( $player );
		}
		$alpha = substr ( $username, 0, 1 );
		
		if (! file_exists ( $this->getDataFolder () . "player/{$alpha}/" ))
			@mkdir ( $this->getDataFolder () . "player/{$alpha}/" );
		
		return (new Config ( $this->getDataFolder () . "player/{$alpha}/{$username}.yml", Config::YAML, [ 
				"nowPrefix" => "",
				"havePrefixList" => [ ] 
		] ))->getAll ();
	}
	
	/**
	 * Save the Prefix information of the user
	 *
	 * @param Player|string $player        	
	 * @param string $nowPrefix        	
	 * @param array $havePrefixList        	
	 */
	public function savePrefixData($player, $nowPrefix = "", Array $havePrefixList = []) {
		if ($player instanceof IPlayer) {
			$username = strtolower ( $player->getName () );
		} else {
			$username = strtolower ( $player );
		}
		$username = strtolower ( $player->getName () );
		$alpha = substr ( $username, 0, 1 );
		
		if (! file_exists ( $this->getDataFolder () . "player/{$alpha}/" ))
			@mkdir ( $this->getDataFolder () . "player/{$alpha}/" );
		
		if ($nowPrefix == "") {
			if (isset ( $this->users [$username] ["nowPrefix"] )) {
				$nowPrefix = $this->users [$username] ["nowPrefix"];
			} else {
				$nowPrefix = "";
			}
		}
		if ($havePrefixList == [ ]) {
			if (isset ( $this->users [$username] ["nowPrefix"] ["havePrefixList"] )) {
				$havePrefixList = $this->users [$username] ["nowPrefix"] ["havePrefixList"];
			} else {
				$havePrefixList = [ ];
			}
		}
		
		(new Config ( $this->getDataFolder () . "player/{$alpha}/{$username}.yml", Config::YAML, [ 
				"nowPrefix" => $nowPrefix,
				"havePrefixList" => $havePrefixList 
		] ))->save ( true );
	}
	
	/**
	 * Add a Prefix to the user
	 *
	 * @param Player|string $player        	
	 * @param array $prefixs        	
	 */
	public function addPrefixData($player, Array $prefixs) {
		if ($player instanceof IPlayer) {
			$username = strtolower ( $player->getName () );
		} else {
			$username = strtolower ( $player );
		}
		$prefixData = $this->readPrefixData ( $player );
		
		foreach ( $prefixs as $prefix )
			$prefixData ["havePrefixList"] [$prefix] = true;
		
		$this->savePrefixData ( $player, $prefixData ["havePrefixList"] );
		$this->refreshPrefix ( $player, $prefixData );
	}
	public function deletePrefixData($player, Array $prefixs) {
		if ($player instanceof IPlayer) {
			$username = strtolower ( $player->getName () );
		} else {
			$username = strtolower ( $player );
		}
		$prefixData = $this->readPrefixData ( $player );
		
		foreach ( $prefixs as $prefix )
			if (isset ( $prefixData ["havePrefixList"] [$prefix] ))
				unset ( $prefixData ["havePrefixList"] [$prefix] );
		
		if (! isset ( $prefixData ["havePrefixList"] [$prefixData ["nowPrefix"]] )) {
			$defaultPrefix = $this->db ["defaultPrefix"];
			$prefixData ["nowPrefix"] = $defaultPrefix;
		}
		
		$this->savePrefixData ( $player, $prefixData ["havePrefixList"] );
		$this->refreshPrefix ( $player, $prefixData );
	}
	/**
	 * Apply the prefix to be seen immediately
	 *
	 * @param Player|string $player        	
	 * @param string $prefix        	
	 */
	public function setNowPrefix($player, $prefix) {
		if ($player instanceof IPlayer) {
			$username = strtolower ( $player->getName () );
		} else {
			$username = strtolower ( $player );
		}
		$prefixData = $this->readPrefixData ( $player );
		$prefixData ["nowPrefix"] = $prefix;
		$this->savePrefixData ( $player, $prefixData ["havePrefixList"] );
		$this->refreshPrefix ( $player, $prefixData );
	}
	/**
	 * applyDefaultPrefixFormat
	 *
	 * @param string $prefix        	
	 */
	public function applyDefaultPrefixFormat($prefix) {
		return str_replace ( "%prefix%", $prefix, $this->db ["defaultPrefixFormat"] );
	}
	public function setSpecialPrefix($player, $prefix) {
		if ($player instanceof IPlayer) {
			$username = strtolower ( $player->getName () );
		} else {
			$username = $player;
		}
		$this->specialPrefix [$username] = $prefix;
	}
	/**
	 * Refresh the Prefix Data
	 *
	 * @param Player|string $player        	
	 * @param array $prefixData        	
	 * @return boolean
	 */
	private function refreshPrefix($player, Array $prefixData) {
		if (! $player instanceof Player) {
			$player = $this->getServer ()->getPlayer ( $player );
			if (! $player instanceof Player)
				return false;
		}
		
		if (! isset ( $prefixData ["nowPrefix"] ))
			$prefixData ["nowPrefix"] = "";
		
		$this->db [strtolower ( $player->getName () )] = $prefixData;
		$prefix = $this->applyDefaultPrefixFormat ( $prefixData ["nowPrefix"] );
		$player->setNameTag ( $name, $prefix );
		return true;
	}
	private function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	private function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null)
			$mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		$this->eventListener->onCommand ( $player, $command, $label, $args );
	}
}

?>