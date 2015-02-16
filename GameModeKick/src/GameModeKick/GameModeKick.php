<?php

namespace GameModeKick;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\nbt\tag\Int;
use pocketmine\scheduler\CallbackTask;

class GameModeKick extends PluginBase implements Listener {
	public function onEnable() {
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	// ex: gamemode 1 hm
	public function ServerCommand(ServerCommandEvent $event) {
		$command = $event->getCommand ();
		$sender = $event->getSender ();
		
		$command = explode ( ' ', $command );
		
		if ($command [0] != 'gamemode')
			return;
		
		if (isset ( $command [1] ) and isset ( $command [2] )) {
			$event->setCancelled ();
			
			$gameMode = Server::getGamemodeFromString ( $command [1] );
			
			if ($gameMode === - 1) {
				$sender->sendMessage ( "Unknown game mode" );
			}
			
			$target = $sender;
			if (isset ( $command [2] )) {
				// $target = $sender->getServer ()->getPlayerExact ( $command [2] )->getPlayer ();
				$target = $sender->getServer ()->getPlayerExact ( $command [2] );
				
				if ($target === null) {
					$nbt = $this->getServer ()->getOfflinePlayerData ( $command [2] );
					if (! isset ( $nbt->NameTag )) {
						$sender->sendMessage ( TextFormat::RED . "해당 플레이어를 찾을 수 없습니다, " . $command [2] );
						return true;
					} else {
						$nbt ["playerGameType"] = $gameMode;
						$this->getServer ()->saveOfflinePlayerData ( $command [2], $nbt );
						$sender->sendMessage ( TextFormat::DARK_AQUA . "비로그인 중인 " . $command [2] . "님의 게임모드를 " . strtolower ( Server::getGamemodeString ( $gameMode ) ) . "로 변경했습니다." );
						return true;
					}
				}
			} elseif (! ($sender instanceof Player)) {
				$sender->sendMessage ( TextFormat::RED . "사용방법 : /gamemode <모드번호> [유저명]" );
			}
		}
		
		if ($gameMode !== $target->getGamemode ()) {
			// $target->setGamemode ( $gameMode ); // set
			$this->setGamemode ( $target, $gameMode ); // set
			if ($gameMode !== $target->getGamemode ()) {
				$sender->sendMessage ( TextFormat::RED . "게임모드 전환에 실패했습니다," . $target->getName () );
			} else {
				if ($target === $sender) {
					Command::broadcastCommandMessage ( $sender, "게임모드를 전환합니다, " . strtolower ( Server::getGamemodeString ( $gameMode ) ) . " mode" );
				} else {
					Command::broadcastCommandMessage ( $sender, $target->getName () . "님의 게임모드를 " . strtolower ( Server::getGamemodeString ( $gameMode ) ) . " 로 변경했습니다." );
				}
			}
		} else {
			$sender->sendMessage ( $target->getName () . "님은 이미 " . strtolower ( Server::getGamemodeString ( $gameMode ) . " 모드입니다." ) );
		}
	}
	public function setGamemode(Player $player, $gm) {
		if ($gm < 0 or $gm > 3 or $player->gamemode === $gm) {
			return\false;
		}
		
		$player->getServer ()->getPluginManager ()->callEvent ( $ev = new PlayerGameModeChangeEvent ( $player, ( int ) $gm ) );
		if ($ev->isCancelled ()) {
			return false;
		}
		if (($player->gamemode & 0x01) === ($gm & 0x01)) {
			$player->gamemode = $gm;
			$player->sendMessage ( TextFormat::DARK_AQUA . "게임모드가 변경되었습니다, " . Server::getGamemodeString ( $player->getGamemode () ) . ".\n" );
		} else {
			$player->gamemode = $gm;
			$player->sendMessage ( TextFormat::DARK_AQUA . "게임모드가 변경되었습니다, " . Server::getGamemodeString ( $player->getGamemode () ) . ".\n" );
			$player->getInventory ()->clearAll ();
			$player->getInventory ()->sendContents ( $player->getViewers () );
			$player->getInventory ()->sendHeldItem ( $player->getViewers () );
		}
		$player->namedtag->playerGameType = new Int ( "playerGameType", $player->gamemode );
		$player->sendMessage ( TextFormat::DARK_AQUA . "새 게임모드 적용을 위해 서버에서 킥처리됩니다." );
		
		$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
				$this,
				"Kick" 
		], [ 
				$player 
		] ), 50 );
		/*
		 * $spawnPosition = $player->getSpawn (); $pk = new StartGamePacket (); $pk->seed = $player->level->getSeed (); $pk->x = $player->x; $pk->y = $player->y + $player->getEyeHeight (); $pk->z = $player->z; $pk->spawnX = ( int ) $spawnPosition->x; $pk->spawnY = ( int ) $spawnPosition->y; $pk->spawnZ = ( int ) $spawnPosition->z; $pk->generator = 1; // 0 old, 1 infinite, 2 flat $pk->gamemode = $player->gamemode & 0x01; $pk->eid = 0; // Always use EntityID as zero for the actual player $player->dataPacket ( $pk ); $player->sendSettings ();
		 */
		return true;
	}
	public function Kick(Player $player) {
		$player->kick ( "게임모드 변경" );
	}
}

?>