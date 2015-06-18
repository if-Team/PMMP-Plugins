<?php

namespace ifteam\Chatty;

use chalk\utils\Messages;
use ifteam\CustomPacket\CPAPI;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\entity\Entity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\protocol\TextPacket;
use pocketmine\event\TranslationContainer;

class Chatty extends PluginBase implements Listener {
	/**
	 *
	 * @var array
	 */
	private $db = [ ], $packets = [ ], $lastNametags = [ ], $messageStack = [ ];
	private $customPacketAvailable = false;
	
	/**
	 *
	 * @var Messages
	 */
	private $messages = null;
	const MESSAGE_VERSION = 1;
	const MESSAGE_LENGTH = 50;
	const MESSAGE_MAX_LINES = 5;
	const LOCAL_CHAT_DISTANCE = 50;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->saveDefaultConfig ();
		
		$this->initMessages ();
		
		$this->db = (new Config ( $this->getDataFolder () . "database.yml", Config::YAML, [ ] ))->getAll ();
		
		$this->packets ["AddPlayerPacket"] = new AddPlayerPacket ();
		$this->packets ["AddPlayerPacket"]->clientID = 0;
		$this->packets ["AddPlayerPacket"]->yaw = 0;
		$this->packets ["AddPlayerPacket"]->pitch = 0;
		$this->packets ["AddPlayerPacket"]->metadata = [ 
				Entity::DATA_FLAGS => [ 
						Entity::DATA_TYPE_BYTE,
						1 << Entity::DATA_FLAG_INVISIBLE 
				],
				Entity::DATA_AIR => [ 
						Entity::DATA_TYPE_SHORT,
						20 
				],
				Entity::DATA_SHOW_NAMETAG => [ 
						Entity::DATA_TYPE_BYTE,
						1 
				] 
		];
		
		$this->registerCommand ( $this->getMessage ( "chatty-command-name" ), "Chatty.commands", $this->getMessage ( "chatty-command-description" ), "/" . $this->getMessage ( "chatty-command-name" ) );
		
		$this->packets ["RemovePlayerPacket"] = new RemovePlayerPacket ();
		$this->packets ["RemovePlayerPacket"]->clientID = 0;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new ChattyTask ( $this ), 1 );
		
		$this->customPacketAvailable = $this->getServer ()->getPluginManager ()->getPlugin ( "CustomPacket" ) !== null;
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "database.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	
	/**
	 *
	 * @param string $name        	
	 * @param string $fallback        	
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
	 * @return Messages
	 */
	public function getMessages() {
		return $this->messages;
	}
	
	/**
	 *
	 * @param string $key        	
	 * @param array $format        	
	 * @return null|string
	 */
	public function getMessage($key, $format = []) {
		return $this->getMessages ()->getMessage ( $key, $format, $this->getServer ()->getLanguage ()->getLang () );
	}
	public function initMessages() {
		$this->saveResource ( "messages.yml", false );
		$this->updateMessages ( "messages.yml" );
		
		$this->messages = new Messages ( (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll () );
	}
	public function updateMessages($filename) {
		$messages = (new Config ( $this->getDataFolder () . $filename, Config::YAML ))->getAll ();
		if (! isset ( $messages ["version"] ) or $messages ["version"] < self::MESSAGE_VERSION) {
			$this->saveResource ( $filename, true );
		}
	}
	public function onLogin(PlayerLoginEvent $event) {
		$name = $event->getPlayer ()->getName ();
		
		$this->messageStack [$name] = [ ];
		if (! isset ( $this->db [$name] )) {
			$this->db [$name] = [ ];
			$this->db [$name] ["chat"] = true;
			$this->db [$name] ["nametag"] = false;
			$this->db [$name] ["local-chat"] = false;
		}
	}
	public function onQuit(PlayerQuitEvent $event) {
		unset ( $this->messageStack [$event->getPlayer ()->getName ()] );
		unset ( $this->lastNametags [$event->getPlayer ()->getName ()] );
	}
	public function putStack($key, $message) {
		$messages = [ ];
		for($start = 0; $start < mb_strlen ( $message, "UTF-8" ); $start += self::MESSAGE_LENGTH) {
			$messages [] = mb_substr ( $message, $start, self::MESSAGE_LENGTH, "UTF-8" );
		}
		
		$this->messageStack [$key] += $messages;
		$this->messageStack [$key] = array_slice ( $this->messageStack [$key], - self::MESSAGE_MAX_LINES );
		
		foreach ( $this->messageStack [$key] as $index => $message ) {
			$this->messageStack [$key] [$index] = TextFormat::WHITE . $message;
		}
	}
	public function prePlayerCommand(PlayerCommandPreprocessEvent $event) {
		if (strpos ( $event->getMessage (), "/" ) === 0) {
			return;
		}
		$event->setCancelled ( true );
		$sender = $event->getPlayer ();
		
		$this->getServer ()->getPluginManager ()->callEvent ( $myEvent = new PlayerChatEvent ( $sender, $event->getMessage () ) );
		if ($myEvent->isCancelled ()) {
			return;
		}
		
		$message = $this->getServer ()->getLanguage ()->translateString ( $myEvent->getFormat (), [ 
				$myEvent->getPlayer ()->getDisplayName (),
				$myEvent->getMessage () 
		] );
		
		if ($this->customPacketAvailable and $this->getConfig ()->get ( "echo-enabled", false )) {
			$passcode = $this->getConfig ()->get ( "echo-passcode", null );
			if ($passcode === null) {
				return;
			}
			
			$myName = $this->getConfig ()->get ( "echo-my-name", "MAIN" );
			$data = json_encode ( [ 
					$passcode,
					$myName,
					$message 
			] );
			foreach ( $this->getConfig ()->get ( "echo-recipients", [ ] ) as $recipient ) {
				$address = explode ( ":", $recipient );
				CPAPI::sendPacket ( new DataPacket ( $address [0], $address [1], $data ) );
			}
		}
	}
	
	/**
	 *
	 * @param string $message        	
	 * @param Player $sender        	
	 */
	public function broadcastMessage($message, $sender = null) {
		$this->getLogger ()->info ( $message );
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if (isset ( $this->db [$player->getName ()] ["local-chat"] ) and $this->db [$player->getName ()] ["local-chat"] == true) {
				if ($sender === null or $sender->distance ( $player ) > self::LOCAL_CHAT_DISTANCE) {
					continue;
				}
			}
			$player->sendMessage ( $message );
		}
	}
	public function onPacketReceive(CustomPacketReceiveEvent $event) {
		if (! $this->getConfig ()->get ( "echo-enabled", false )) {
			return;
		}
		
		// $data[0] "passcode" => $passcode,
		// $data[1] "name" => $myName,
		// $data[2] "message" => $message
		$passcode = $this->getConfig ()->get ( "echo-passcode", null );
		if ($passcode === null) {
			return;
		}
		$data = json_decode ( $event->getPacket ()->data );
		if (! is_array ( $data ) or ! isset ( $data [0] )) {
			return;
		}
		if ($passcode !== $data [0]) {
			return;
		}
		$this->broadcastMessage ( "[" . $data [1] . "] " . $data [2] );
	}
	public function onDataPacket(DataPacketSendEvent $event) {
		if (! $event->getPacket () instanceof TextPacket or $event->getPacket ()->pid () != 0x85 or $event->isCancelled ()) {
			return;
		}
		
		if (isset ( $this->db [$event->getPlayer ()->getName ()] ["chat"] ) and $this->db [$event->getPlayer ()->getName ()] ["chat"] == false) {
			$event->setCancelled ();
			return;
		}
		
		if (isset ( $this->db [$event->getPlayer ()->getName ()] ["nametag"] ) and $this->db [$event->getPlayer ()->getName ()] ["nametag"] == true) {
			$message = $this->getServer ()->getLanguage ()->translate ( new TranslationContainer ( $event->getPacket ()->message, $event->getPacket ()->parameters ) );
			$this->putStack ( $event->getPlayer ()->getName (), $message );
		}
	}
	public function tick() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$key = $player->getName ();
			
			if (isset ( $this->lastNametags [$key] )) {
				$this->packets ["RemovePlayerPacket"]->eid = $this->lastNametags [$key] ["eid"];
				$this->packets ["RemovePlayerPacket"]->clientID = $this->lastNametags [$key] ["eid"];
				
				$player->directDataPacket ( $this->packets ["RemovePlayerPacket"] ); // 네임택 제거패킷 전송
			}
			
			if (! isset ( $this->db [$key] ["nametag"] ) or $this->db [$key] ["nametag"] == false or ! isset ( $this->messageStack [$key] )) {
				continue;
			}
			
			$this->lastNametags [$key] = [ 
					"eid" => Entity::$entityCount ++ 
			];
			
			$this->packets ["AddPlayerPacket"]->eid = $this->lastNametags [$key] ["eid"];
			$this->packets ["AddPlayerPacket"]->clientID = $this->lastNametags [$key] ["eid"];
			$this->packets ["AddPlayerPacket"]->username = implode ( "\n", $this->messageStack [$key] );
			$this->packets ["AddPlayerPacket"]->x = round ( $player->x ) + (-\sin ( ($player->yaw / 180 * M_PI) - 0.4 )) * 7;
			$this->packets ["AddPlayerPacket"]->y = round ( $player->y ) + (-\sin ( $player->pitch / 180 * M_PI )) * 7;
			$this->packets ["AddPlayerPacket"]->z = round ( $player->z ) + (cos ( ($player->yaw / 180 * M_PI) - 0.4 )) * 7;
			
			$player->dataPacket ( $this->packets ["AddPlayerPacket"] );
		}
	}
	public function sendMessage(CommandSender $player, $text, $prefix = null) {
		if ($prefix === null) {
			$prefix = $this->getMessage ( "default-prefix" );
		}
		
		$player->sendMessage ( TextFormat::DARK_AQUA . $prefix . " " . $this->getMessage ( $text ) );
	}
	public function sendAlert(CommandSender $player, $text, $prefix = null) {
		if ($prefix === null) {
			$prefix = $this->getMessage ( "default-prefix" );
		}
		
		$player->sendMessage ( TextFormat::RED . $prefix . " " . $this->getMessage ( $text ) );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (strToLower ( $command->getName () ) !== $this->getMessage ( "chatty-command-name" )) {
			return true;
		}
		
		if (! isset ( $args [0] )) {
			$this->sendMessage ( $player, "chatty-command-usage" );
			return true;
		}
		
		if (! $player instanceof Player) {
			$this->sendAlert ( $player, "only-in-game" );
			return true;
		}
		
		switch ($args [0]) {
			default :
				$this->sendMessage ( $player, "chatty-command-usage" );
				break;
			
			case $this->getMessage ( "chatty-on-command-name" ) :
				$this->db [$player->getName ()] ["chat"] = true;
				$this->sendMessage ( $player, "chat-enabled" );
				break;
			
			case $this->getMessage ( "chatty-off-command-name" ) :
				$this->db [$player->getName ()] ["chat"] = false;
				$this->sendMessage ( $player, "chat-disabled" );
				break;
			
			case $this->getMessage ( "chatty-local-chat-command-name" ) :
				if (! isset ( $this->db [$player->getName ()] ["local-chat"] ) or $this->db [$player->getName ()] ["local-chat"] == false) {
					$this->db [$player->getName ()] ["local-chat"] = true;
					$this->sendMessage ( $player, "local-chat-enabled" );
				} else {
					$this->db [$player->getName ()] ["local-chat"] = false;
					$this->sendMessage ( $player, "local-chat-disabled" );
				}
				break;
			
			case $this->getMessage ( "chatty-nametag-command-name" ) :
				if (! isset ( $this->db [$player->getName ()] ["nametag"] ) or $this->db [$player->getName ()] ["nametag"] == false) {
					$this->db [$player->getName ()] ["nametag"] = true;
					$this->sendMessage ( $player, "nametag-enabled" );
				} else {
					$this->db [$player->getName ()] ["nametag"] = false;
					$this->sendMessage ( $player, "nametag-disabled" );
				}
				break;
		}
		return true;
	}
}

?>
