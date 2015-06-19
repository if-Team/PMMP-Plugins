<?php

namespace ifteam\Sparklers;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\block\Block;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\sound\PopSound;

class Sparklers extends PluginBase implements Listener {
	public $messages, $db;
	public $m_version = 1;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->initMessage ();
		$this->db = (new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML, [ ] ))->getAll ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new SparklersTask ( $this ), 60 );
	}
	public function fire() {
		foreach ( $this->getServer ()->getLevels () as $level ) {
			if (! $level instanceof Level) continue;
			if (! isset ( $this->db ["Sparklers"] [$level->getFolderName ()] )) continue;
			foreach ( $this->db ["Sparklers"] [$level->getFolderName ()] as $keyPos => $keyValue ) {
				$explode = explode ( ".", $keyPos );
				if (! isset ( $explode [2] )) break; // WRONG DATA
				
				$pillarPos = new Position ( $explode [0], $explode [1], $explode [2], $level );
				
				$players = [ ];
				foreach ( $this->getServer ()->getOnlinePlayers () as $player )
					if ($pillarPos->distance ( $player ) < 25) $players [] = $player;
				
				if (count ( $players ) == 0) continue;

				$level->addSound(new PopSound($pillarPos), $players);
				for($h = 1; $h <= 11; $h ++) {
					$pillarPos->setComponents ( $pillarPos->x, ++ $pillarPos->y, $pillarPos->z );
					$level->addParticle ( new DustParticle ( $pillarPos, 255, 255, 255, 255 ), $players );
				}
				$headPos = new Position ( $pillarPos->x, $pillarPos->y - 10, $pillarPos->z, $level );
				
				$r = mt_rand(0, 255);
				$g =mt_rand(0, 255);
				$b =mt_rand(0, 255);
				for($r = 1; $r <= 5; $r ++) {
					$headPos->setComponents ( $pillarPos->x + mt_rand ( - 3, 3 ), $pillarPos->y + mt_rand ( - 3, 3 ), $pillarPos->z + mt_rand ( - 3, 3 ) );
					$level->addParticle ( new DustParticle ( $headPos, $r, $g, $b, 255 ), $players ); // WHITE
				}
				$r = mt_rand(0, 255);
				$g =mt_rand(0, 255);
				$b =mt_rand(0, 255);
				for($r = 1; $r <= 5; $r ++) {
					$headPos->setComponents ( $pillarPos->x + mt_rand ( - 3, 3 ), $pillarPos->y + mt_rand ( - 3, 3 ), $pillarPos->z + mt_rand ( - 3, 3 ) );
					$level->addParticle ( new DustParticle ( $headPos, $r, $g, $b, 255 ), $players ); // GREEN
				}
				$r = mt_rand(0, 255);
				$g =mt_rand(0, 255);
				$b =mt_rand(0, 255);
				for($r = 1; $r <= 5; $r ++) {
					$headPos->setComponents ( $pillarPos->x + mt_rand ( - 3, 3 ), $pillarPos->y + mt_rand ( - 3, 3 ), $pillarPos->z + mt_rand ( - 3, 3 ) );
					$level->addParticle ( new DustParticle ( $headPos, $r, $g, $b, 255 ), $players ); // PINK
				}
				$r = mt_rand(0, 255);
				$g =mt_rand(0, 255);
				$b =mt_rand(0, 255);
				for($r = 1; $r <= 5; $r ++) {
					$headPos->setComponents ( $pillarPos->x + mt_rand ( - 3, 3 ), $pillarPos->y + mt_rand ( - 3, 3 ), $pillarPos->z + mt_rand ( - 3, 3 ) );
					$level->addParticle ( new DustParticle ( $headPos, $r, $g, $b, 255 ), $players ); // ORANGE
				}
				$r = mt_rand(0, 255);
				$g =mt_rand(0, 255);
				$b =mt_rand(0, 255);
				for($r = 1; $r <= 5; $r ++) {
					$headPos->setComponents ( $pillarPos->x + mt_rand ( - 3, 3 ), $pillarPos->y + mt_rand ( - 3, 3 ), $pillarPos->z + mt_rand ( - 3, 3 ) );
					$level->addParticle ( new DustParticle ( $headPos, $r, $g, $b, 255 ), $players ); // BLUE
				}
			}
		}
	}
	public function onSignChange(SignChangeEvent $event) {
		if (! $event->getPlayer ()->isOp ()) return;
		if ($event->getLine ( 0 ) != $this->get ( "Sparklers" )) return;
		$this->db ["Sparklers"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"] = true;
		$this->message ( $event->getPlayer (), $this->get ( "Sparklers-set-succeess" ) );
		$event->getBlock ()->getLevel ()->setBlock ( $event->getBlock (), Block::get ( Block::REDSTONE_BLOCK ) );
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		if (! $event->getPlayer ()->isOp ()) return;
		if (isset ( $this->db ["Sparklers"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"] )) {
			unset ( $this->db ["Sparklers"] [$event->getBlock ()->getLevel ()->getFolderName ()] ["{$event->getBlock()->x}.{$event->getBlock()->y}.{$event->getBlock()->z}"] );
			$this->message ( $event->getPlayer (), $this->get ( "Sparklers-unset-succeess" ) );
		}
	}
	public function onDisable() {
		$save = new Config ( $this->getDataFolder () . "pluginDB.yml", Config::YAML );
		$save->setAll ( $this->db );
		$save->save ();
	}
	public function get($var) {
		if (isset ( $this->messages [$this->getServer ()->getLanguage ()->getLang ()] )) {
			$lang = $this->getServer ()->getLanguage ()->getLang ();
		} else {
			$lang = "eng";
		}
		return $this->messages [$lang . "-" . $var];
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
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}

?>