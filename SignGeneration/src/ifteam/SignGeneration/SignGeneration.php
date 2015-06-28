<?php

namespace ifteam\SignGeneration;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\block\Block;
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
use pocketmine\event\block\SignChangeEvent;
use pocketmine\level\Level;

class SignGeneration extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onCommand(CommandSender $player, Command $command, $label, array $args) {
		/* SEND HELP */
		if (! isset ( $args [3] )) {
			$player->sendMessage ( TextFormat::DARK_AQUA . "/signgen <x> <y> <z> <world> <id> <meta> <text>" );
			return true;
		}
		/* SET X Y Z */
		$x = ( int ) $args [0];
		$y = ( int ) $args [1];
		$z = ( int ) $args [2];
		
		/* SET WORLD */
		$world = $this->getServer ()->getLevelByName ( $args [3] );
		if (! $world instanceof Level) {
			$world = $this->getServer ()->getDefaultLevel ();
		}
		
		/* SET ID AND DAMAGE */
		$id = $args [4];
		$damage = $args [5];
		
		/* SET TEXT */
		for($i = 0; $i <= 5; $i ++)
			array_shift ( $args );
		$text = implode ( " ", $args );
		$text = explode ( "\\n", $text );
		if (! isset ( $text [1] ))
			$text [1] = "";
		if (! isset ( $text [2] ))
			$text [2] = "";
		if (! isset ( $text [3] ))
			$text [3] = "";
			
			/* SET CHUNK */
		if ($world->isChunkGenerated ( $x, $z ))
			$world->generateChunk ( $x, $z );
		$chunk = $world->getChunk ( $x >> 4, $z >> 4, true );
		if (! ($chunk instanceof FullChunk)) {
			$player->sendMessage ( TextFormat::DARK_AQUA . "[SignGeneration] WRONG CHUNK PROBLEM EXIST" );
			return true;
		}
		
		/* SET NBT */
		$nbt = new Compound ( "", [ 
				new String ( "Text1", $text [0] ),
				new String ( "Text2", $text [1] ),
				new String ( "Text3", $text [2] ),
				new String ( "Text4", $text [3] ),
				new String ( "id", Tile::SIGN ),
				new Int ( "x", ( int ) $x ),
				new Int ( "y", ( int ) $y ),
				new Int ( "z", ( int ) $z ) 
		] );
		
		/* DELETE OVERLAP TILE */
		$entities = $world->getEntities ();
		foreach ( $entities as $tile ) {
			if (! $tile instanceof Tile)
				continue;
			if ($tile->x != $x or $tile->y != $y or $tile->z != $z)
				continue;
			$tile->close ();
		}
		
		/* SET SIGN BLOCK */
		$world->setBlock ( new Vector3 ( $x, $y, $z ), Block::get ( $id, $damage ), false, true );
		
		$sign = new Sign ( $chunk, $nbt );
		$sign->saveNBT ();
		$world->addTile ( $sign );
		$player->sendMessage ( TextFormat::DARK_AQUA . "[SignGeneration] COMPLETE!" );
		return true;
	}
	public function onSignChange(SignChangeEvent $event) {
		if (! $event->getPlayer ()->isOp ())
			return;
		if ($event->getLine ( 0 ) == "check") {
			$text = TextFormat::DARK_AQUA;
			$text .= "X:" . $event->getBlock ()->getX ();
			$text .= ", Y" . $event->getBlock ()->getY ();
			$text .= ", Z:" . $event->getBlock ()->getZ ();
			$text .= ", ID: " . $event->getBlock ()->getId ();
			$text .= ", DMG :" . $event->getBlock ()->getDamage ();
			$this->getLogger ()->info ( $text );
			$event->getPlayer ()->sendMessage ( $text );
		}
	}
}
?>