<?php

namespace Khinenw\SpeedBlock;

use Khinenw\SpeedBlock\task\FlyingCheck;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\Player;

class SpeedBlock extends PluginBase implements Listener{
	
	private $sblocks = [];
	private $players = [];

	public $flyingPlayers = [];

	private static $instance;

	public function onEnable(){
		self::$instance = $this;
		@mkdir($this->getDataFolder());
		$this->sblocks = (new Config($this->getDataFolder()."sblocks.yml", Config::YAML))->getAll();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new FlyingCheck($this), 10);
	}

	public static function getInstance(){
		return self::$instance;
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "sblock":

				if(!($sender instanceof Player)){
					$sender->sendMessage(TextFormat::RED."You must call this command in-game!");
					return true;
				}
				
				if(isset($this->players[$sender->getName()])){
					unset($this->players[$sender->getName()]);
					$sender->sendMessage(TextFormat::AQUA."Speed block function has been disabled!");
					return true;
				}
				
				if(count($args) < 1) return false;
				if(!is_numeric($args[0])) return false;
				
				$directionVector = $sender->getDirectionVector();
				
				$this->players[$sender->getName()] = [
					"speed" => $args[0],
					"x" => $directionVector->getX(),
					"y" => $directionVector->getY(),
					"z" => $directionVector->getZ(),
					"type" => "speed"
				];
				
				$sender->sendMessage(TextFormat::AQUA."Setting speed block function has been enabled!");
				return true;

			case "sblockdmg":

				if(!($sender instanceof Player)){
					$sender->sendMessage(TextFormat::RED."You must call this command in-game!");
					return true;
				}

				if(isset($this->players[$sender->getName()])){
					unset($this->players[$sender->getName()]);
					$sender->sendMessage(TextFormat::AQUA."Speed block function has been disabled!");
					return true;
				}

				if(count($args) < 1) return false;
				if(!is_numeric($args[0])) return false;

				$directionVector = $sender->getDirectionVector();

				$this->players[$sender->getName()] = [
					"speed" => $args[0],
					"x" => $directionVector->getX(),
					"y" => $directionVector->getY(),
					"z" => $directionVector->getZ(),
					"type" => "speeddmg"
				];

				$sender->sendMessage(TextFormat::AQUA."Setting speed block function has been enabled!");
				return true;

			case "sblockdel":
				if(!($sender instanceof Player)){
					$sender->sendMessage(TextFormat::RED."You must call this command in-game!");
					return true;
				}
				
				if(isset($this->players[$sender->getName()])){
					unset($this->players[$sender->getName()]);
					$sender->sendMessage(TextFormat::AQUA."Speed block function has been disabled!");
					return true;
				}
				
				$this->players[$sender->getName()] = [
					"type" => "del"
				];
				
				$sender->sendMessage(TextFormat::AQUA."Deleting speed block function has been enabled!");
				return true;
		}

		return false;
	}
	
	public function onPlayerInteract(PlayerInteractEvent $event){
		$event->getBlock();
		if(isset($this->players[$event->getPlayer()->getName()])){
			switch($this->players[$event->getPlayer()->getName()]["type"]){

				case "speeddmg":
				case "speed":
					$sbData = $this->players[$event->getPlayer()->getName()];
					$this->sblocks[$this->getLocByBlock($event->getBlock())] = $sbData;
					$event->getPlayer()->sendMessage(TextFormat::AQUA."Speed block setted!");
					$this->saveSpeedBlocks();
					break;
				
				case "del":
					if(isset($this->sblocks[$this->getLocByBlock($event->getBlock())])){
						unset($this->sblocks[$this->getLocByBlock($event->getBlock())]);
						$event->getPlayer()->sendMessage(TextFormat::AQUA."Speed block removed!");
						$this->saveSpeedBlocks();
						break;
					}
			}
		}
	}
	
	public function onPlayerMove(PlayerMoveEvent $event){
		$tag = $this->getLocByVector($event->getTo()->subtract(0, 1, 0), $event->getTo()->getLevel()->getFolderName());
		if(isset($this->sblocks[$tag])){
			$data = $this->sblocks[$tag];
			$event->getPlayer()->setMotion((new Vector3($data["x"], $data["y"], $data["z"]))->multiply($data["speed"]));
			if($this->sblocks[$tag]["type"] !== "speeddmg"){
				$this->flyingPlayers[$event->getPlayer()->getName()] = [
					"player" => $event->getPlayer(),
					"lastground" => -1
				];
			}
		}
	}

	public function onEntityDamage(EntityDamageEvent $event){
		$player = $event->getEntity();
		if(!($player instanceof Player)) return;

		if($event->getCause() !== EntityDamageEvent::CAUSE_FALL) return;

		if(isset($this->flyingPlayers[$player->getName()])){
			$lastGround = $this->flyingPlayers[$player->getName()]["lastground"];
			if($lastGround === -1 || time() - $lastGround <= 3){
				$event->setCancelled();
			}
			unset($this->flyingPlayers[$player->getName()]);
		}
	}
	
	public function getLocByBlock(Position $block){
		return $block->getFloorX().";".$block->getFloorY().";".$block->getFloorZ().";".$block->getLevel()->getFolderName();
	}
	
	public function getLocByVector(Vector3 $block, $levelFolderName){
		return $block->getFloorX().";".$block->getFloorY().";".$block->getFloorZ().";".$levelFolderName;
	}
	
	public function saveSpeedBlocks(){
		$config = (new Config($this->getDataFolder()."sblocks.yml", Config::YAML));
		$config->setAll($this->sblocks);
		$config->save();
	}
}