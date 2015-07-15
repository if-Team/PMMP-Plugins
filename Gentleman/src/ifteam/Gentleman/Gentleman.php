<?php

namespace ifteam\Gentleman;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Event;
use ifteam\Gentleman\task\GentlemanAsyncTask;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerChatEvent;

class Gentleman extends PluginBase implements Listener {
	private static $instance = null;
	public $m_version = 1;
	public $messages, $list, $dictionary;
	public $badQueue = [ ], $oldChat = [ ];
	public $chatCheck = [ ], $signCheck = [ ];
	public $commandCheck = [ ], $nameCheck = [ ];
	public $playerTemp = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->messagesUpdate ();
		
		if (self::$instance == null)
			self::$instance = $this;
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		// $this->parseXEDatabaseToJSON (); //*CONVERT ONLY*
	}
	public static function getInstance() {
		return static::$instance;
	}
	public function userCommand(PlayerCommandPreprocessEvent $event) {
		$command = $event->getMessage ();
		if (! isset ( $this->playerTemp [$event->getPlayer ()->getName ()] )) {
			$this->playerTemp [$event->getPlayer ()->getName ()] = $event->getPlayer ();
		}
		if (\substr ( $command, 0, 1 ) != "/") { // only chat
			if (! isset ( $this->chatCheck [$event->getPlayer ()->getName () . ">" . $command] )) {
				$this->chatCheck [$event->getPlayer ()->getName () . ">" . $command] = false;
				$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new GentlemanAsyncTask ( $event->getPlayer ()->getName (), "chat.type.text", $event->getMessage (), $this->badQueue, $this->dictionary, "chat", true, true ) );
				$event->setCancelled ();
				return;
			} else {
				if (! $this->chatCheck [$event->getPlayer ()->getName () . ">" . $event->getMessage ()]) {
					$event->setCancelled ();
					return;
				} else {
					unset ( $this->chatCheck [$event->getPlayer ()->getName () . ">" . $event->getMessage ()] );
				}
			}
			return;
		}
		$command = explode ( ' ', $command );
		if ($command [0] != "/me" and $command [0] != "/tell" and $command [0] != "/w" and $command [0] != "/환영말")
			return;
		
		if (! isset ( $this->commandCheck [$event->getPlayer ()->getName () . ">" . $event->getMessage ()] )) {
			$this->commandCheck [$event->getPlayer ()->getName () . ">" . $event->getMessage ()] = false;
			$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new GentlemanAsyncTask ( $event->getPlayer ()->getName (), null, $event->getMessage (), $this->badQueue, $this->dictionary, "command", true ) );
			$event->setCancelled ();
		} else {
			if (! $this->commandCheck [$event->getPlayer ()->getName () . ">" . $event->getMessage ()]) {
				$event->setCancelled ();
				return;
			} else {
				unset ( $this->commandCheck [$event->getPlayer ()->getName () . ">" . $event->getMessage ()] );
			}
		}
	}
	public function signPlace(SignChangeEvent $event) {
		if ($event->getPlayer ()->isOp ())
			return;
		$message = "";
		foreach ( $event->getLines () as $line )
			$message .= $line . "\n";
		
		if (! isset ( $this->signCheck [$event->getPlayer ()->getName () . ">" . $message] )) {
			$this->signCheck [$event->getPlayer ()->getName () . ">" . $message] = false;
			$blockPos = "{$event->getBlock ()->x}:{$event->getBlock ()->y}:{$event->getBlock ()->z}";
			$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new GentlemanAsyncTask ( $event->getPlayer ()->getName (), [ 
					$event->getBlock ()->getId (),
					$event->getBlock ()->getDamage (),
					$blockPos 
			], $message, $this->badQueue, $this->dictionary, "sign", true ) );
			$event->setCancelled ();
			return;
		} else {
			if (! $this->signCheck [$event->getPlayer ()->getName () . ">" . $message]) {
				$event->setCancelled ();
				return;
			} else {
				unset ( $this->signCheck [$event->getPlayer ()->getName () . ">" . $message] );
			}
		}
	}
	public function onJoin(PlayerJoinEvent $event) {
		if ($event->getPlayer ()->isOp ())
			return;
		$this->playerTemp [$event->getPlayer ()->getName ()] = $event->getPlayer ();
		if (! isset ( $this->nameCheck [$event->getPlayer ()->getName ()] )) {
			$this->nameCheck [$event->getPlayer ()->getName ()] = true;
			$this->getServer ()->getScheduler ()->scheduleAsyncTask ( new GentlemanAsyncTask ( $event->getPlayer ()->getName (), $event->getJoinMessage (), $event->getPlayer ()->getName (), $this->badQueue, $this->dictionary, "name", true ) );
			$event->setJoinMessage ( "" );
		} else {
			if (! $this->nameCheck [$event->getPlayer ()->getName ()]) {
				$event->setCancelled ();
				return;
			}
		}
	}
	public function asyncProcess($name, $format, $message, $find, $eventType) {
		$player = $this->playerTemp [$name];
		if (! $player instanceof Player) {
			return;
		}
		if ($player->closed) {
			return;
		}
		switch ($eventType) {
			case "chat" :
				if ($find == null) {
					if (isset ( $this->chatCheck [$name . ">" . $message] )) {
						$this->chatCheck [$name . ">" . $message] = true;
						$this->getServer ()->getPluginManager ()->callEvent ( $event = new PlayerChatEvent ( $player, $message, $format ) );
						if (! $event->isCancelled ()) {
							$this->getServer ()->broadcastMessage ( $this->getServer ()->getLanguage ()->translateString ( $event->getFormat (), [ 
									$event->getPlayer ()->getDisplayName (),
									$event->getMessage () 
							] ), $event->getRecipients () );
						}
					}
				} else {
					$player->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) . ": " . $message . "( " . $this->get ( "doubt" ) . ": " . $find . " ) " );
					$player->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
					$this->cautionNotice ( $player, $message . "( " . $find . " ) " );
					return;
				}
				break;
			case "command" :
				if ($find == null) {
					if (isset ( $this->commandCheck [$player->getName () . ">" . $message] )) {
						$this->commandCheck [$player->getName () . ">" . $message] = true;
						$this->getServer ()->getPluginManager ()->callEvent ( $event = new PlayerCommandPreprocessEvent ( $player, $message ) );
						if (! $event->isCancelled ()) {
							$this->getServer ()->dispatchCommand ( $event->getPlayer (), substr ( $event->getMessage (), 1 ) );
						}
					}
				} else {
					$player->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) . ": " . $message . " ( " . $this->get ( "doubt" ) . ": " . $find . " )" );
					$player->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
					$this->cautionNotice ( $player, $message . " ( " . $find . " ) " );
					return;
				}
				break;
			case "sign" :
				if ($find == null) {
					if (isset ( $this->signCheck [$player->getName () . ">" . $message] )) {
						$this->signCheck [$player->getName () . ">" . $message] = true;
						$blockPos = explode ( ":", $format [2] );
						$block = Block::get ( $format [0], $format [1], new Position ( $blockPos [0], $blockPos [1], $blockPos [2], $player->getLevel () ) );
						$lines = explode ( "\n", $message );
						$event = new SignChangeEvent ( $block, $player, [ 
								TextFormat::clean ( $lines [0], $player->getRemoveFormat () ),
								TextFormat::clean ( $lines [1], $player->getRemoveFormat () ),
								TextFormat::clean ( $lines [2], $player->getRemoveFormat () ),
								TextFormat::clean ( $lines [3], $player->getRemoveFormat () ) 
						] );
						$this->getServer ()->getPluginManager ()->callEvent ( $event );
						$tile = $player->getLevel ()->getTile ( $block );
						if (! $tile instanceof Sign)
							return;
						if (! $event->isCancelled ()) {
							$tile->setText ( $lines [0], $lines [1], $lines [2], $lines [3] );
						}
					}
				} else {
					$message = explode ( "\n", $message );
					$message = implode ( " ", $message );
					$player->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) . ": " . $message . " ( " . $this->get ( "doubt" ) . ": " . $find . " )" );
					$player->sendMessage ( TextFormat::RED . $this->get ( "be-careful-about-badwords" ) );
					$this->cautionNotice ( $player, $message . " ( " . $find . " ) " );
					return;
				}
				break;
			case "name" :
				if (isset ( $this->nameCheck [$player->getName ()] )) {
					$this->nameCheck [$player->getName ()] = true;
					if (strlen ( trim ( $format ) ) > 0)
						$this->getServer ()->broadcastMessage ( $format );
				} else {
					$player->kick ( $this->get ( "badwords-nickname" ) );
					return;
				}
				break;
		}
	}
	public function onKick(PlayerKickEvent $event) {
		if ($event->getReason () == $this->get ( "badwords-nickname" )) {
			$event->setQuitMessage ( "" );
		}
	}
	public function cautionNotice(Player $player, $word) {
		$this->getLogger ()->alert ( $this->get ( "some-badwords-found" ) );
		$this->getLogger ()->alert ( $player->getName () . "> " . $word );
		foreach ( $this->getServer ()->getOnlinePlayers () as $online ) {
			if (! $online->isOp ())
				continue;
			$player->sendMessage ( TextFormat::RED . $this->get ( "some-badwords-found" ) );
			$player->sendMessage ( TextFormat::RED . $player->getName () . "> " . $word );
		}
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->saveResource ( "badwords.json", false );
		$this->saveResource ( "dictionary.yml", false ); // $dictionary
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		$this->list = (new Config ( $this->getDataFolder () . "badwords.json", Config::JSON ))->getAll ();
		$this->dictionary = (new Config ( $this->getDataFolder () . "dictionary.yml", Config::YAML ))->getAll ();
		$this->makeQueue ();
	}
	public function makeQueue() {
		foreach ( $this->list ["badwords"] as $badword )
			$this->badQueue [] = $this->cutWords ( $badword );
	}
	public function cutWords($str) {
		$cut_array = [ ];
		for($i = 0; $i < mb_strlen ( $str, "UTF-8" ); $i ++)
			$cut_array [] = mb_substr ( $str, $i, 1, 'UTF-8' );
		return $cut_array;
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function messagesUpdate() {
		if (! isset ( $this->messages ["default-language"] ["m_version"] )) {
			$this->saveResource ( "messages.yml", true );
			$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
		} else {
			if ($this->messages ["default-language"] ["m_version"] < $this->m_version) {
				$this->saveResource ( "messages.yml", true );
				$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
			}
		}
	}
	public function parseXEDatabaseToJSON() {
		$parseBadwords = file_get_contents ( $this->getDataFolder () . "badwords.txt" );
		// $parseBadwords = mb_convert_encoding ( $parseBadwords, "UTF-8", "CP949" );
		$parseBadwords = explode ( "\n", $parseBadwords );
		
		$checkDuplicate = [ ];
		foreach ( $parseBadwords as $index => $badword ) {
			$checkDuplicate [$badword] = $index;
		}
		
		foreach ( $checkDuplicate as $badword => $data ) {
			$list ["badwords"] [] = $badword;
		}
		$this->list = new Config ( $this->getDataFolder () . "badwords_conv.yml", Config::YAML, [ ] );
		
		$this->list = new Config ( $this->getDataFolder () . "badwords_conv.json", Config::JSON, $this->list->getAll () );
		$this->list->save ();
	}
}

?>