<?php

namespace ifteam\SnowHalation;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\entity\Entity;
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
	public $m_version = 2, $pk;
	
	/**
	 *
	 * @var array
	 */
	public $config, $messages;
	
	/**
	 *
	 * @var Config
	 */
	public $config_File;
	public $denied = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		
		$this->config_File = new Config ( $this->getDataFolder () . "set-up.yml", Config::YAML, [ "enable-snowing" => 1,"enable-sunlight" => 0 ] );
		$this->config = $this->config_File->getAll ();
		
		$this->pk = new AddEntityPacket ();
		$this->pk->type = 81;
		$this->pk->metadata = [ Entity::DATA_FLAGS => [ Entity::DATA_TYPE_BYTE,0 ],Entity::DATA_SHOW_NAMETAG => [ Entity::DATA_TYPE_BYTE,0 ],Entity::DATA_AIR => [ Entity::DATA_TYPE_SHORT,10 ] ];
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new SnowHalationTask ( $this ), 4 );
		
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
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
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
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "heavysnow-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "heatwave-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snowworld-help" ) );
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
				case $this->get ( "heavysnow" ) :
					if (! $sender->isOp ()) return false;
					$this->heavySnow ( $sender );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "heavysnow-success" ) );
					break;
				case $this->get ( "heatwave" ) :
					if (! $sender->isOp ()) return false;
					$this->heatwave ( $sender );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "heatwave-success" ) );
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
				case $this->get ( "snowworld" ) :
					if (! $sender instanceof Player) return false;
					if (! $sender->isOp ()) return false;
					if (isset ( $this->config ["world"] [$sender->getLevel ()->getFolderName ()] )) {
						if ($this->config ["world"] [$sender->getLevel ()->getFolderName ()]) {
							$this->config ["world"] [$sender->getLevel ()->getFolderName ()] = false;
							$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snowworld-off" ) );
						} else {
							$this->config ["world"] [$sender->getLevel ()->getFolderName ()] = true;
							$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snowworld-on" ) );
						}
					} else {
						$this->config ["world"] [$sender->getLevel ()->getFolderName ()] = false;
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snowworld-off" ) );
					}
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "on-help" ) );
					$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "off-help" ) );
					if ($sender->isOp ()) {
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "enable-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "disable-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "sunlight-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "heavysnow-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "heatwave-help" ) );
						$sender->sendMessage ( TextFormat::DARK_AQUA . $this->get ( "snowworld-help" ) );
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
					if (! $player->spawned or isset ( $this->denied [$player->getName ()] )) continue;
					$x = mt_rand ( $player->x - 15, $player->x + 15 );
					$z = mt_rand ( $player->z - 15, $player->z + 15 );
					
					if ($this->cooltime < 11) {
						$this->cooltime ++;
						
						$y = $player->getLevel ()->getHighestBlockAt ( $x, $z );
						$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new destructSnowLayerTask ( $this, new Position ( $x, $y, $z, $player->getLevel () ) ), 20 );
					}
				}
			}
			return;
		}
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if (! $player->spawned or isset ( $this->denied [$player->getName ()] )) continue;
			if (isset ( $this->config ["world"] [$player->getLevel ()->getFolderName ()] )) {
				if (! $this->config ["world"] [$player->getLevel ()->getFolderName ()]) continue;
			}
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
			$this->pk->eid = Entity::$entityCount ++;
			$this->pk->x = $x;
			$this->pk->y = $player->y + 13;
			$this->pk->z = $z;
			$player->dataPacket ( $this->pk );
		}
		if (! $this->checkEnableSunLight ()) {
			if ($this->cooltime < 11) {
				$this->cooltime ++;
				
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new createSnowLayerTask ( $this, new Position ( $x, $y, $z, $player->getLevel () ) ), 20 );
			}
		} else {
			if ($this->cooltime < 11) {
				$this->cooltime ++;
				
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new destructSnowLayerTask ( $this, new Position ( $x, $y, $z, $player->getLevel () ) ), 20 );
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
		
		$pos->getLevel ()->setBlock ( $up, Block::get ( Item::SNOW_LAYER ), 0, false );
	}
	public function destructSnowLayer(Position $pos) {
		$this->cooltime --;
		if ($pos == null) return;
		if ($pos->getLevel ()->getBlockIdAt($pos->x, $pos->y, $pos->z) == Block::SNOW_LAYER){
			$pos->getLevel ()->setBlock ( $pos, Block::get ( Block::AIR ), 0, false );
		}
	}
	public function heavySnow(Player $player) {
		$pos = new Position ( $player->x, $player->y, $player->z, $player->getLevel () );
		for($x = - 15; $x <= 15; $x ++)
			for($z = - 15; $z <= 15; $z ++) {
				$dx = $player->x + $x;
				$dz = $player->z + $z;
				$dy = $player->getLevel ()->getHighestBlockAt ( $dx, $dz );
				$this->createSnowLayer ( $pos->setComponents ( $dx, $dy, $dz ) );
			}
	}
	public function heatwave(Player $player) {
		$pos = new Position ( $player->x, $player->y, $player->z, $player->getLevel () );
		for($x = - 15; $x <= 15; $x ++)
			for($z = - 15; $z <= 15; $z ++) {
				$dx = $player->x + $x;
				$dz = $player->z + $z;
				$dy = $player->getLevel ()->getHighestBlockAt ( $dx, $dz );
				$this->destructSnowLayer ( $pos->setComponents ( $dx, $dy, $dz ) );
			}
	}
}

?>