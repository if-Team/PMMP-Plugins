<?php

namespace SnowHalation;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\scheduler\CallbackTask;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\level\Position;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class SnowHalation extends PluginBase implements Listener {
	public $cooltime = 0;
	public $m_version = 1, $pk;

    /** @var array */
	public $config, $messages;

    /** @var Config */
    public $config_File;

	public $denied = [ ];

	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		
		$this->config_File = new Config ( $this->getDataFolder () . "set-up.yml", Config::YAML, [ "enable-snowing" => 1,"enable-sunlight" => 0 ] );
		$this->config = $this->config_File->getAll ();
		
		$this->pk = new AddEntityPacket ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $this,"SnowHalation" ] ), 4 );
		
		new OutEventListener ( $this );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->config_File->setAll ( $this->config );
		$this->config_File->save ();
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messagesUpdate ( "messages.yml" );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function messagesUpdate($targetYmlName) {
		$targetYml = (new Config ( $this->getDataFolder () . $targetYmlName, Config::YAML ))->getAll ();
		if (! isset ( $targetYml ["m_version"] )) {
			$this->saveResource ( $targetYmlName, true );
		} else if ($targetYml ["m_version"] < $this->m_version) {
			$this->saveResource ( $targetYmlName, true );
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () == $this->get ( "snow" ) )) {
			if (! $sender->hasPermission ( "snowhalation" )) return false;
			if (! isset ( $args [0] )) {
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "on-help" ) );
				$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "off-help" ) );
				if ($sender->isOp ()) {
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "enable-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "disable-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-help" ) );
				}
				return true;
			}
			switch ($args [0]) {
				case $this->get ( "enable" ) :
					if (! $sender->isOp ()) return false;
					$this->config ["enable-snowing"] = 1;
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snow-enabled" ) );
					break;
				case $this->get ( "disable" ) :
					if (! $sender->isOp ()) return false;
					$this->config ["enable-snowing"] = 0;
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snow-disabled" ) );
					break;
				case $this->get ( "on" ) :
					if (isset ( $this->denied [$sender->getName ()] )) unset ( $this->denied [$sender->getName ()] );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snow-on" ) );
					break;
				case $this->get ( "off" ) :
					$this->denied [$sender->getName ()] = 1;
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snow-off" ) );
                    break;
				case $this->get ( "sunlight" ) :
					if (! $sender->isOp ()) return false;
					if ($this->config ["enable-sunlight"] == 0) {
						$this->config ["enable-sunlight"] = 1;
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-on-1" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-on-2" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-on-3" ) );
					} else {
						$this->config ["enable-sunlight"] = 0;
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-off-1" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-off-2" ) );
					}
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "on-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "off-help" ) );
					if ($sender->isOp ()) {
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "enable-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "disable-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-help" ) );
					}
					break;
			}
			return true;
		}
        return true;
	}

	public function SnowHalation() {
		if (! $this->checkEnableSnowing ()) {
			if ($this->checkEnableSunLight ()) {
				foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
					if ($player->spawned != true or isset ( $this->denied [$player->getName ()] )) continue;
					$x = mt_rand ( $player->x - 15, $player->x + 15 );
					$z = mt_rand ( $player->z - 15, $player->z + 15 );
					
					if ($this->cooltime < 11) {
						$this->cooltime ++;
						
						$y = $player->getLevel ()->getHighestBlockAt ( $x, $z );
						$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ $this,"destructSnowLayer" ], [ new Position ( $x, $y, $z, $player->getLevel () ) ] ), 20 );
					}
				}
			}
			return;
		}
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if ($player->spawned != true or isset ( $this->denied [$player->getName ()] )) continue;
			$this->createSnow ( $player );
		}
	}
	public function checkEnableSnowing() {
		return ( bool ) $this->config ["enable-snowing"];
	}
	public function checkEnableSunLight() {
		return ( bool ) $this->config ["enable-sunlight"];
	}
	public function createSnow(Player $player) {
		$x = mt_rand ( $player->x - 15, $player->x + 15 );
		$z = mt_rand ( $player->z - 15, $player->z + 15 );
		$y = $player->getLevel ()->getHighestBlockAt ( $x, $z );
		
		if ($y <= $player->y) {
			$this->pk->type = 81;
			$this->pk->eid = Entity::$entityCount ++;
			$this->pk->x = $x;
			$this->pk->y = $player->y + 13;
			$this->pk->z = $z;
			$player->dataPacket ( $this->pk );
		}
		
		if (! $this->checkEnableSunLight ()) {
			if ($this->cooltime < 11) {
				$this->cooltime ++;
				
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ $this,"createSnowLayer" ], [ new Position ( $x, $y, $z, $player->getLevel () ) ] ), 20 );
			}
		} else {
			if ($this->cooltime < 11) {
				$this->cooltime ++;
				
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ $this,"destructSnowLayer" ], [ new Position ( $x, $y, $z, $player->getLevel () ) ] ), 20 );
			}
		}
	}
	public function createSnowLayer(Position $pos) {
		$this->cooltime --;
		
		if ($pos == null) return;
		
		$down = $pos->getLevel ()->getBlock ( $pos );
		if (! $down->isSolid ()) return;
		if ($down->getId () == Block::GRAVEL or $down->getId () == Block::COBBLESTONE or $down->getId () == 32 or $down->getId () == Block::DIAMOND_BLOCK or $down->getId () == Block::WATER or $down->getId () == Block::WOOL or $down->getId () == 44 or $down->getId () == Block::FENCE or $down->getId () == Block::STONE_BRICK_STAIRS or $down->getId () == 43 or $down->getId () == Block::FARMLAND) return;
		
		$up = $pos->getLevel ()->getBlock ( $pos->add ( 0, 1, 0 ) );
		if ($up->getId () != Block::AIR) return;
		
		$pos->getLevel ()->setBlock ( $up, Block::get ( Item::SNOW_LAYER ), 0, true );
	}
	public function destructSnowLayer(Position $pos) {
		$this->cooltime --;
		
		if ($pos == null) return;
		
		if ($pos->getLevel ()->getBlock ( $pos )->getId () == Block::SNOW_LAYER) $pos->getLevel ()->setBlock ( $pos, Block::get ( Item::AIR ), 0, true );
	}
}

?>