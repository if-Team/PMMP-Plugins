<?php

namespace hm\SignRegeneration;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\scheduler\CallbackTask;
use pocketmine\tile\Sign;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\Compound;
use pocketmine\nbt\tag\String;
use pocketmine\nbt\tag\Int;
use pocketmine\tile\Tile;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;

class SignRegeneration extends PluginBase implements Listener {
	public $listyml, $list;
	public $config;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		
		$this->listyml = $this->initializeYML ( "signdata.yml", [ ] );
		$this->list = $this->listyml->getAll ();
		$this->config = $this->initializeYML ( "config.yml", [ "DivisionProcess_Count" => "50","DivisionProcess_Tick" => "20","Repeat_Generation" => "yes","Repeat_Tick" => "2000" ] )->getAll ();
		if ($this->config ["Repeat_Generation"] == "yes") $this->initialize_schedule_repeat ( $this, "SignRegeneration", $this->config ["Repeat_Tick"] );
	}
	public function onDisable() {
		$this->listyml->setAll ( $this->list );
		$this->listyml->save ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if ($command->getName () == "resign" or $command->getName () == "재생성") {
			$sender->sendMessage ( TextFormat::DARK_AQUA . "표지판 재생이 시작됩니다" );
			$this->SignRegeneration ();
			return true;
		}
		return false;
	}
	public function initializeYML($path, $array) {
		return new Config ( $this->getDataFolder () . $path, Config::YAML, $array );
	}
	public function initialize_schedule_repeat($class, $method, $second) {
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new CallbackTask ( [ $class,$method ] ), $second );
	}
	public function initialize_schedule_delay($class, $method, $second, $param) {
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ $class,$method ], $param ), $second );
	}
	public function signCatch(SignChangeEvent $event) {
		if ($event->getLine ( 0 ) == null and $event->getLine ( 1 ) == null and $event->getLine ( 2 ) == null and $event->getLine ( 3 ) == null) return;
		$block = $event->getBlock ();
		if ($block->getID () != 0) {
			$this->list [$block->x . "." . $block->y . "." . $block->z] ['id'] = $block->getID ();
			$this->list [$block->x . "." . $block->y . "." . $block->z] ['damage'] = $block->getDamage ();
			$this->list [$block->x . "." . $block->y . "." . $block->z] ['t0'] = $event->getLine ( 0 );
			$this->list [$block->x . "." . $block->y . "." . $block->z] ['t1'] = $event->getLine ( 1 );
			$this->list [$block->x . "." . $block->y . "." . $block->z] ['t2'] = $event->getLine ( 2 );
			$this->list [$block->x . "." . $block->y . "." . $block->z] ['t3'] = $event->getLine ( 3 );
			$this->initialize_schedule_delay ( $this, "signReplaceCatch", 40, [ $block->x,$block->y,$block->z ] );
		}
	}
	public function signReplaceCatch($x, $y, $z) {
		$pos = new Vector3 ( $x, $y, $z );
		
		$block = $this->getServer ()->getDefaultLevel ()->getBlock ( $pos );
		if ($block->getID () != 323 and $block->getID () != 63 and $block->getID () != 68) {
			if (isset ( $this->list [$x . "." . $y . "." . $z . "."] )) return;
		}
		
		$sign = $this->getServer ()->getDefaultLevel ()->getTile ( $pos );
		if (! $sign instanceof Sign) return;
		
		$lines = $sign->getText ();
		$this->list [$block->x . "." . $block->y . "." . $block->z] ['t0'] = $lines [0];
		$this->list [$block->x . "." . $block->y . "." . $block->z] ['t1'] = $lines [1];
		$this->list [$block->x . "." . $block->y . "." . $block->z] ['t2'] = $lines [2];
		$this->list [$block->x . "." . $block->y . "." . $block->z] ['t3'] = $lines [3];
	}
	public function signDataRemove(BlockBreakEvent $event) {
		if ($event->isCancelled ()) return;
		$block = $event->getBlock ();
		if (isset ( $this->list [$block->x . "." . $block->y . "." . $block->z] )) {
			unset ( $this->list [$block->x . "." . $block->y . "." . $block->z] );
		}
	}
	public function SignRegeneration() {
		$poslist = array_keys ( $this->list );
		$count = count ( $this->list );
		$index = $count / $this->config ["DivisionProcess_Count"];
		
		if ($count > $this->config ["DivisionProcess_Count"] * $index) $index ++;
		// echo "[ 사인리제너레이터 ] 인덱싱 작업중...\n";
		if ($count < $this->config ["DivisionProcess_Count"]) {
			// echo "[ 사인리제너레이터 ] 인덱싱 모드 심플 진행 \n";
			$this->SignSpawn_Array ( $poslist );
		} else if ($count >= $this->config ["DivisionProcess_Count"]) {
			// echo "[ 사인리제너레이터 ] 인덱싱 모드 배분 시작\n";
			$a_f = 0;
			for($i = 1; $i <= $index; $i ++) {
				// echo "[ 사인리제너레이터 ] 인덱싱 완료 스케쥴 등록 시작\n";
				$cutpos = [ ];
				for($b = $a_f; $b < $a_f + $this->config ["DivisionProcess_Count"]; $b ++) {
					if (! isset ( $poslist [$a_f + $b] )) break;
					$cutpos [$b] = $poslist [$a_f + $b];
				}
				$a_f += $this->config ["DivisionProcess_Count"];
				$this->initialize_schedule_delay ( $this, "SignSpawn_Array", $this->config ["DivisionProcess_Tick"] * $i, [ $cutpos ] );
			}
		}
	}
	public function SignSpawn_Array(array $cutpos) {
		foreach ( $cutpos as $pos ) {
			$e = explode ( ".", $pos );
			if (isset ( $this->list [$pos] )) $this->SignSpawn ( [ $this->list [$pos] ['t0'],$this->list [$pos] ['t1'],$this->list [$pos] ['t2'],$this->list [$pos] ['t3'] ], $e );
		}
	}
	public function SignSpawn($text, $pos) {
		if ($this->getServer ()->getDefaultLevel ()->isChunkGenerated ( $pos [0], $pos [2] )) $this->getServer ()->getDefaultLevel ()->generateChunk ( $pos [0], $pos [1] );
		$chunk = $this->getServer ()->getDefaultLevel ()->getChunk ( $pos [0] >> 4, $pos [2] >> 4, true );
		$nbt = new Compound ( "", [ new String ( "Text1", $text [0] ),new String ( "Text2", $text [1] ),new String ( "Text3", $text [2] ),new String ( "Text4", $text [3] ),new String ( "id", Tile::SIGN ),new Int ( "x", ( int ) $pos [0] ),new Int ( "y", ( int ) $pos [1] ),new Int ( "z", ( int ) $pos [2] ) ] );
		if (! ($chunk instanceof FullChunk)) break;
		$entities = $this->getServer ()->getDefaultLevel ()->getEntities ();
		foreach ( $entities as $tile ) {
			if (! $tile instanceof Tile) continue;
			if ($tile->x != $pos [0] or $tile->y != $pos [1] or $tile->z != $pos [2]) continue;
			$tile->close ();
		}
		$id = $this->list [$pos [0] . "." . $pos [1] . "." . $pos [2]] ['id'];
		$damage = $this->list [$pos [0] . "." . $pos [1] . "." . $pos [2]] ['damage'];
		$this->getServer ()->getDefaultLevel ()->setBlock ( new Vector3 ( $pos [0], $pos [1], $pos [2] ), Block::get ( $id, $damage ), false, true );
		$sign = new Sign ( $chunk, $nbt );
		$sign->saveNBT ();
		$this->getServer ()->getDefaultLevel ()->addTile ( $sign );
	}
}
?>