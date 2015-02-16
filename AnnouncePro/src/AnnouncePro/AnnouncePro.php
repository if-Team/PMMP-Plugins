<?php

namespace AnnouncePro;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\CallbackTask;
use pocketmine\command\PluginCommand;

class AnnouncePro extends PluginBase implements Listener {
	public $config, $configData;
	public $callback, $before = 1;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->registerCommand ( $this->get ( "commands-announce" ), "AnnouncePro", "announcepro" );
		
		$this->config = new Config ( $this->getDataFolder () . "announce.yml", Config::YAML, [ 
				"enable" => true,
				"repeat-second" => 5,
				"prefix" => $this->get ( "default-prefix" ),
				"suffix" => "",
				"announce" => [ ] ] );
		$this->configData = $this->config->getAll ();
		$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"AnnouncePro" ] ), $this->configData ["repeat-second"] * 20 );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config->setAll ( $this->configData );
		$this->config->save ();
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function registerCommand($name, $fallback, $permission, $description = "", $usage = "") {
		$commandMap = $this->getServer ()->getCommandMap ();
		$command = new PluginCommand ( $name, $this );
		$command->setDescription ( $description );
		$command->setPermission ( $permission );
		$command->setUsage ( $usage );
		$commandMap->register ( $fallback, $command );
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		if (! isset ( $args [0] )) {
			$this->helpPage ( $player );
			return true;
		}
		switch ($args [0]) {
			case $this->get ( "sub-commands-enable" ) :
				$this->configData ["enable"] = true;
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "enable-complete" ) );
				break;
			case $this->get ( "sub-commands-disable" ) :
				$this->configData ["enable"] = false;
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "enable-complete" ) );
				break;
			case $this->get ( "sub-commands-add" ) :
				array_shift ( $args );
				$text = $this->replaceColor ( implode ( " ", $args ) );
				if ($text == "" or $text == " ") {
					$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "add-help" ) );
					break;
				}
				$this->configData ["announce"] [] = $text;
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "add-complete" ) );
				break;
			case $this->get ( "sub-commands-delete" ) :
				if (! isset ( $args [1] )) {
					$player->sendMessage ( TextFormat::RED . $this->get ( "delete-help" ) );
					break;
				}
				if (! is_numeric ( $args [1] )) {
					$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "delete-must-number" ) );
					break;
				}
				if (isset ( $this->configData ["announce"] [$args [1]] )) {
					unset ( $this->configData ["announce"] [$args [1]] );
					ksort ( $this->configData ["announce"] );
					$match_new = array ();
					$keys = array_keys ( $this->configData ["announce"] );
					while ( $aaa = each ( $keys ) )
						$match_new [] = $this->configData ["announce"] [$aaa [1]];
					$this->configData ["announce"] = $match_new;
					unset ( $match_new );
					$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "delete-complete" ) );
				}
				break;
			case $this->get ( "sub-commands-list" ) :
				if (isset ( $args [1] ) and is_numeric ( $args [1] )) {
					$this->AnnounceList ( $player, $args [1] );
				} else {
					$this->AnnounceList ( $player );
				}
				break;
			case $this->get ( "sub-commands-repeat" ) :
				if (! is_numeric ( $args [1] )) {
					$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "repeat-must-number" ) );
					break;
				}
				$this->configData ["repeat-second"] = $args [1];
				$this->callback->remove ();
				$this->callback = $this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
						$this,
						"AnnouncePro" ] ), $this->configData ["repeat-second"] * 20 );
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "repeat-complete" ) );
				break;
			case $this->get ( "sub-commands-prefix" ) :
				if (! isset ( $args [1] )) {
					$player->sendMessage ( TextFormat::RED . $this->get ( "prefix-help" ) );
					break;
				}
				$this->configData ["prefix"] = $args [1];
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "prefix-complete" ) );
				break;
			case $this->get ( "sub-commands-suffix" ) :
				if (! isset ( $args [1] )) {
					$player->sendMessage ( TextFormat::RED . $this->get ( "suffix-help" ) );
					break;
				}
				$this->configData ["suffix"] = $args [1];
				$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "suffix-complete" ) );
				break;
			default :
				$this->helpPage ( $player );
				break;
		}
		return true;
	}
	public function AnnouncePro() {
		if ($this->configData ["enable"] != true) return;
		if (isset ( $this->configData ["announce"] )) $rand = rand ( 0, count ( $this->configData ["announce"] ) - 1 );
		if (count ( $this->configData ["announce"] ) > 3) while ( $rand == $this->before )
			$rand = rand ( 0, count ( $this->configData ["announce"] ) - 1 );
		$this->before = $rand;
		if (isset ( $rand )) if (isset ( $this->configData ["announce"] [$rand] )) foreach ( $this->getServer ()->getOnlinePlayers () as $player )
			$player->sendMessage ( $this->configData ["prefix"] . " " . $this->configData ["announce"] [$rand] . " " . $this->configData ["suffix"] );
	}
	public function replaceColor($text) {
		for($i = 0; $i <= 9; $i ++)
			$text = str_replace ( "&" . $i, "§" . $i, $text );
		for($i = 'a'; $i <= 'f'; $i ++)
			$text = str_replace ( "&" . $i, "§" . $i, $text );
		return $text;
	}
	public function AnnounceList(CommandSender $player, $index = 1) {
		$once_print = 5;
		$target = $this->configData ["announce"];
		
		$index_count = count ( $target );
		$index_key = array_keys ( $target );
		$full_index = floor ( $index_count / $once_print );
		
		if ($index_count > $full_index * $once_print) $full_index ++;
		
		if ($index <= $full_index) {
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "now-list-show" ) . " ({$index}/{$full_index}) " . $this->get ( "index_count" ) . ": {$index_count}" );
			$message = null;
			for($for_i = $once_print; $for_i >= 1; $for_i --) {
				$now_index = $index * $once_print - $for_i;
				if (! isset ( $index_key [$now_index] )) break;
				$now_key = $index_key [$now_index];
				$message .= TextFormat::DARK_AQUA . "[" . $now_key . "] : " . $target [$now_key] . "\n";
			}
			$player->sendMessage ( $message );
		} else {
			$player->sendMessage ( TextFormat::RED . $this->get ( "there-is-no-list" ) );
			return false;
		}
	}
	public function helpPage(CommandSender $player) {
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "help-1" ) );
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "help-2" ) );
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "help-3" ) );
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "help-4" ) );
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "help-5" ) );
		$player->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "help-6" ) );
	}
}
?>