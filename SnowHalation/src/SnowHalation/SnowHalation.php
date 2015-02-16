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
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;

class SnowHalation extends PluginBase implements Listener {
	public $cooltime = 0;
	public $pk;
	public $config, $config_File;
	public $denied = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		$this->config_File = new Config ( $this->getDataFolder () . "set-up.yml", Config::YAML, [ 
				"enable-snowing" => 1,
				"enable-sunlight" => 0 
		] );
		$this->config = $this->config_File->getAll ();
		
		$this->pk = new AddEntityPacket ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ 
				$this,
				"SnowHalation" 
		] ), 1 );
	}
	public function onDisable() {
		$this->config_File->setAll ( $this->config );
		$this->config_File->save ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, Array $args) {
		if (strtolower ( $command->getName () == "snow" )) {
			if (! $sender->hasPermission ( "snowhalation" ))
				return false;
			if (! isset ( $args [0] )) {
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow on - 눈이 보이게 합니다." );
				$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow off - 눈이 보이지않게 합니다." );
				if ($sender->isOp ()) {
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow enable - 서버에 눈이내리게 합니다." );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow disable - 서버에 눈이내리지않게 합니다." );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow sunlight - 유저 주변에 눈이 랜덤하게 녹게합니다." );
				}
				return true;
			}
			switch ($args [0]) {
				case "enable" :
					if ($sender->isOp ()) {
						$this->config ["enable-snowing"] = 1;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] 서버에 눈이내리게 했습니다." );
					}
					break;
				case "disable" :
					if ($sender->isOp ()) {
						$this->config ["enable-snowing"] = 0;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] 서버에 눈이내리지않게 했습니다." );
					}
					break;
				case "on" :
					if (isset ( $this->denied [$sender->getName ()] ))
						unset ( $this->denied [$sender->getName ()] );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] 눈이 내리게 설정했습니다." );
					break;
				case "off" :
					$this->denied [$sender->getName ()] = 1;
					$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] 눈이 내리지않게 설정했습니다." );
				case "sunlight" :
					if ($this->config ["enable-sunlight"] == 0) {
						$this->config ["enable-sunlight"] = 1;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] 유저 주변에 눈이 녹기시작합니다." );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] (눈이 오더라도 쌓이지 않습니다.)" );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] /snow sunlight 를 다시입력시 녹지않음" );
					} else {
						$this->config ["enable-sunlight"] = 0;
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] 유저 주변에 눈이 녹지않습니다" );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "[SnowHalation] /snow sunlight 를 다시입력시 녹음" );
					}
					break;
				default :
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow on - 눈이 보이게 합니다." );
					$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow off - 눈이 보이지않게 합니다." );
					if ($sender->isOp ()) {
						$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow enable - 서버에 눈이내리게 합니다." );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow disable - 서버에 눈이내리지않게 합니다." );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "/snow sunlight - 유저 주변에 눈이 랜덤하게 녹게합니다." );
					}
					break;
			}
			return true;
		}
	}
	public function SnowHalation() {
		if (! $this->checkEnableSnowing ()) {
			if ($this->checkEnableSunLight ()) {
				foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
					if ($player->spawned != true or isset ( $this->denied [$player->getName ()] ))
						continue;
					$x = mt_rand ( $player->x - 15, $player->x + 15 );
					$z = mt_rand ( $player->z - 15, $player->z + 15 );
					
					if ($this->cooltime < 11) {
						$this->cooltime ++;
						
						$y = $player->getLevel ()->getHighestBlockAt ( $x, $z );
						$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
								$this,
								"destructSnowLayer" 
						], [ 
								new Position ( $x, $y, $z, $player->getLevel () ) 
						] ), 20 );
					}
				}
			}
			return;
		}
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			if ($player->spawned != true or isset ( $this->denied [$player->getName ()] ))
				continue;
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
		
		$this->pk->type = 81;
		$this->pk->eid = Entity::$entityCount ++;
		$this->pk->x = $x;
		$this->pk->y = $player->y + 13;
		$this->pk->z = $z;
		$this->pk->did = 0;
		$player->dataPacket ( $this->pk );
		
		if (! $this->checkEnableSunLight ()) {
			if ($this->cooltime < 11) {
				$this->cooltime ++;
				
				$y = $player->getLevel ()->getHighestBlockAt ( $x, $z );
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"createSnowLayer" 
				], [ 
						new Position ( $x, $y, $z, $player->getLevel () ) 
				] ), 20 );
			}
		} else {
			if ($this->cooltime < 11) {
				$this->cooltime ++;
				
				$y = $player->getLevel ()->getHighestBlockAt ( $x, $z );
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"destructSnowLayer" 
				], [ 
						new Position ( $x, $y, $z, $player->getLevel () ) 
				] ), 20 );
			}
		}
	}
	public function createSnowLayer(Position $pos) {
		$this->cooltime --;
		
		if ($pos == null)
			return;
		
		$down = $pos->getLevel ()->getBlock ( $pos );
		if (! $down->isSolid ())
			return;
		
		$up = $pos->getLevel ()->getBlock ( $pos->add ( 0, 1, 0 ) );
		if ($up->getId () != Block::AIR)
			return;
		
		$pos->getLevel ()->setBlock ( $up, Block::get ( Item::SNOW_LAYER ), 0, true );
	}
	public function destructSnowLayer(Position $pos) {
		$this->cooltime --;
		
		if ($pos == null)
			return;
		
		if ($pos->getLevel ()->getBlock ( $pos )->getId () == Block::SNOW_LAYER)
			$pos->getLevel ()->setBlock ( $pos, Block::get ( Item::AIR ), 0, true );
	}
}

?>