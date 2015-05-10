<?php

namespace IchiKaku\PocketClan;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\command\PluginCommand;

class PocketClan extends PluginBase implements Listener {
	private static $obj = null;
	public $m_version = 1;
	/**
	 *
	 * @var EconomyAPI
	 */
	private $api = null;
	private $clanlist, $clandata, $playerclan, $setting, $type, $messages;
	/**
	 *
	 * @var Config
	 */
	private $clan_list, $clan_data, $player_clan;
	public static function getInstance() {
		return self::$obj;
	}
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		if ($this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ) != null) {
			$this->api = EconomyAPI::getInstance ();
		} else {
			$this->getLogger ()->error ( "'EconomyAPI' plugin was not exist!" );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		}
		
		$this->initMessage ();
		$this->loadData ();
		
		$this->registerCommand ( $this->get ( "clan" ), "pocketclan.command", $this->get ( "clan-desc" ), $this->get ( "clan-help" ) );
		$this->registerCommand ( $this->get ( "clanManage" ), "pocketclan.command", $this->get ( "clanManage-desc" ), $this->get ( "clanManage-help" ) );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->saveData ();
	}
	public function onChat(PlayerChatEvent $e) {
		if ($this->getClan ( $e->getPlayer ()->getName () ) != null) {
			$e->setMessage ( TextFormat::GOLD . "[ " . $this->getClan ( $e->getPlayer ()->getName () ) . " ] " . TextFormat::WHITE . $e->getMessage () );
		}
	}
	public function onCommand(CommandSender $sp, Command $command, $label, array $args) {
		$p = $sp->getName ();
		if (! ($sp instanceof Player)) $sp->sendMessage ( $this->get ( "command-inConsole" ) );
		else switch ($command) {
			case $this->get ( "clan" ) :
				if (! isset ( $args [0] )) {
					$this->message ( $sp, $this->get ( "clan-help" ) );
					break;
				}
				switch ($args [0]) {
					case $this->get ( "make" ) :
						if (! isset ( $args [1] )) {
							$this->message ( $sp, $this->get ( "clan-create-help" ) );
							$this->message ( $sp, $this->get ( "clan-type" ) );
							break;
						}
						if ($this->api->myMoney ( $p ) < $this->setting ["money"]) {
							$this->message ( $sp, $this->get ( "not-enough-money" ) . " " . $this->setting ["money"] . "$ " . $this->get ( "need-money" ) );
						} else if (isset ( $args [1] )) {
							if (isset ( $args [2] )) {
								$type = $args [2];
							} else {
								$type = "talk";
							}
							$this->makeClan ( $sp, $args [1], $type );
						}
						return true;
					case $this->get ( "join" ) :
						if (! isset ( $args [1] )) {
							$this->message ( $sp, $this->get ( "clan-notInput" ) );
							break;
						}
						if ($this->getClan ( $p ) == $args [1]) {
							$this->message ( $sp, $this->get ( "aleady-inClan" ) . " [" . $args [1] . "]" );
							break;
						}
						if ($this->getClan ( $p ) != null) {
							$this->message ( $sp, $this->get ( "aleady-inClan" ) . " [" . $this->getClan ( $p ) . "]" );
							break;
						}
						foreach ( $this->clanlist as $cl ) {
							if ($cl == $args [1]) {
								$this->clandata [$args [1]] [$p] = "user";
								array_push ( $this->clandata [$args [1]] ["list"], $p );
								$this->playerclan [$p] = $args [1];
								$this->message ( $sp, $this->get ( "success-joined" ) . "\"" . $args [1] . "\"" );
								break;
							} else {
								$this->message ( $sp, $this->get ( "PocketClan-cantfindclan" ) );
							}
						}
						return true;
					case $this->get ( "list" ) :
						if (isset ( $args [1] )) {
							$list = "";
							foreach ( $this->clandata [$args [1]] ["list"] as $cl )
								$list .= $cl . ", ";
							$this->message ( $sp, "[PocketClan] " . $args [1] . " " . $this->get ( "people" ) . " : " . sizeof ( $this->clandata [$args [1]] ["list"] ) . " " . $this->get ( "list" ) . " : " . $list );
						} else {
							$list = "";
							foreach ( $this->clanlist as $cl )
								$list .= $cl . ", ";
							$this->message ( $sp, $list );
						}
						return true;
					case $this->get ( "leave" ) :
						if ($this->getClan ( $p ) != null) {
							$this->clandata [$this->getClan ( $p )] [$p] = "NotInClan";
							$this->playerclan [$p] = null;
							unset ( $this->clandata [$this->getClan ( $p )] ["list"] [array_search ( $p, $this->clandata [$this->getClan ( $p )] ["list"] )] );
							$this->message ( $sp, $this->get ( "leave-clan" ) . " [" . $this->getClan ( $p ) . "]" );
						} else {
							$this->message ( $sp, $this->get ( "PocketClan-cantfindclan" ) );
						}
						break;
					default :
						$this->message ( $sp, $this->get ( "clan-help" ) );
				}
				break;
			case $this->get ( "clanManage" ) :
				switch ($args [0]) {
					case $this->get ( "delete" ) :
						if ($this->clandata [$this->getClan ( $p )] [$p] == "admin") {
							foreach ( $this->clandata [$this->getClan ( $p )] ["list"] as $pl )
								$this->playerclan [$pl] = null;
							unset ( $this->clanlist [array_search ( $this->getClan ( $p ), $this->clanlist )] );
							unset ( $this->clandata [array_search ( $this->getClan ( $p ), $this->clandata )] );
						} else if (! isset ( $args [1] )) $this->message ( $sp, $this->get ( "delete-help" ) );
						else if ($sp->isOP ()) {
							if (! isset ( $this->clanlist [$args [1]] )) {
								$this->message ( $sp, $this->get ( "clan-not-found" ) );
								break;
							}
							foreach ( $this->clandata [$args [1]] ["list"] as $pl )
								$this->playerclan [$pl] = null;
							unset ( $this->clanlist [array_search ( $args [1], $this->clanlist )] );
							unset ( $this->clandata [array_search ( $args [1], $this->clandata )] );
						}
						return true;
					case $this->get ( "ban" ) :
						if (! isset ( $args [1] )) {
							$this->message ( $sp, $this->get ( "ban-help" ) );
							break;
						}
						if (! isset ( $this->clandata [$this->getClan ( $p )] ["list"] [$args [1]] )) {
							$this->message ( $sp, $this->get ( "player-not-found" ) );
							break;
						}
						if ($this->clandata [$this->getClan ( $p )] [$p] == ("admin" || "op")) {
							$this->playerclan [$args [1]] = null;
							unset ( $this->clandata [$this->getClan ( $p )] ["list"] [array_search ( $p, $this->clandata [$this->getClan ( $p )] ["list"] )] );
						}
						return true;
					case $this->get ( "admin" ) :
						if (! isset ( $args [1] )) $sp->sendMessage ( $this->get ( "admin-help" ) );
						if (! isset ( $this->clandata [$this->getClan ( $p )] ["list"] [$args [1]] )) {
							$this->message ( $sp, $this->get ( "player-not-found" ) );
							break;
						}
						if ($this->clandata [$this->getClan ( $p )] [$p] == ("admin" || "op")) {
							$this->clandata [$this->getClan ( $p )] ["list"] [array_search ( $p, $this->clandata [$this->getClan ( $p )] ["list"] )] = "op";
						}
						return true;
					default :
						$this->message ( $sp, $this->get ( "clanManage-help" ) );
				}
				break;
			default :
				$this->message ( $sp, $this->get ( "clanManage-help" ) );
		}
		return true;
	}
	public function makeClan(CommandSender $maker, $name, $type = "talk") {
		$this->api->reduceMoney ( $maker->getName (), $this->setting ["money"] );
		$this->clanlist [$name] = $name;
		$this->clandata [$name] [$maker->getName ()] = "admin";
		$this->clandata [$name] ["list"] = array ();
		array_push ( $this->clandata [$name] ["list"], $maker->getName () );
		$this->playerclan [$maker->getName ()] = $name;
		if (! isset ( $this->type ["type"] [$type] )) {
			$maker->sendMessage ( $this->get ( "type-notFound" ) . " : " . $type );
			$maker->sendMessage ( $this->get ( "clan-type" ) );
			return;
		} else {
			$this->clandata [$name] ["type"] = $type;
		}
		$this->message ( $maker, $this->get ( "PocketClan-ClanMade" ) . " [" . $name . "] [" . $type . "]" );
	}
	public function clanInfo(CommandSender $asker, $name) {
		// TODO 클랜정보 조회
		// 톡방의 경우 단순 유저수만
		// PVP그룹의 경우 전적까지 포함
		// 경제그룹인 경우 그룹돈까지 포함
	}
	public function defineType($type) {
		array_push ( $this->type ["type"], $type );
	}
	public function getClan($player) {
		return isset ( $this->playerclan [$player] ) ? $this->playerclan [$player] : null;
	}
	public function loadData() {
		$this->saveResource ( "config.yml", false );
		$this->setting = (new Config ( $this->getDataFolder () . "config.yml", Config::YAML ))->getAll ();
		$this->saveResource ( "clantype.yml", false );
		$this->type = (new Config ( $this->getDataFolder () . "clantype.yml", Config::YAML ))->getAll ();
		var_dump ( $this->type );
		$this->clan_list = $this->initializeYML ( "clan_list.yml", [ ] );
		$this->clan_data = $this->initializeYML ( "clan_data.yml", [ ] );
		$this->player_clan = $this->initializeYML ( "player_clan.yml", [ ] );
		$this->clandata = $this->clan_data->getAll ();
		$this->clanlist = $this->clan_list->getAll ();
		$this->playerclan = $this->player_clan->getAll ();
	}
	public function saveData() {
		$this->clan_list->setAll ( $this->clanlist );
		$this->clan_data->setAll ( $this->clandata );
		$this->player_clan->setAll ( $this->playerclan );
		$this->clan_list->save ();
		$this->clan_data->save ();
		$this->player_clan->save ();
	}
	public function initializeYML($path, $array) {
		return new Config ( $this->getDataFolder () . $path, Config::YAML, $array );
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
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}