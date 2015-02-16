<?php

namespace NydusCanal;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\scheduler\CallbackTask;

class NydusCanal extends PluginBase implements Listener {
	public $NydusCanal, $NydusCanal_List;
	public $warpCooltime, $customCooltime, $timeout;
	public $economyAPI = null;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->NydusCanal = new Config ( $this->getDataFolder () . "warpList.yml", Config::YAML );
		$this->NydusCanal_List = $this->NydusCanal->getAll ();
		
		if ($this->checkEconomyAPI ()) $this->economyAPI = \onebone\economyapi\EconomyAPI::getInstance ();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function onDisable() {
		$this->NydusCanal->setAll ( $this->NydusCanal_List );
		$this->NydusCanal->save ();
	}
	public function onPlace(BlockPlaceEvent $event) {
		$username = $event->getPlayer ()->getName ();
		if (isset ( $this->PlacePrevent [$username] )) {
			$event->setCancelled ( true );
			unset ( $this->PlacePrevent [$username] );
		}
	}
	public function onSign(SignChangeEvent $event) {
		if ($event->getLine ( 0 ) == "[워프]" or $event->getLine ( 0 ) == "워프") {
			$player = $event->getPlayer ();
			if (! $player->hasPermission ( "nyduscanal.commands.addwarp" )) {
				$player->sendMessage ( TextFormat::DARK_AQUA . "포탈생성권한이 없습니다." );
				$event->setCancelled ();
				return false;
			}
			if (! isset ( explode ( "[", $event->getLine ( 1 ) )[1] )) if (! isset ( $this->NydusCanal_List ["warp"] [$event->getLine ( 1 )] )) {
				$player->sendMessage ( TextFormat::DARK_AQUA . "해당하는 워프포인트가 없습니다." );
				$event->setCancelled ();
				return false;
			}
			$event->setLine ( 0, "[워프]" );
			if (isset ( $this->NydusCanal_List ["warp"] [$event->getLine ( 1 )] ["price"] )) if (isset ( explode ( "+", $this->NydusCanal_List ["warp"] [$event->getLine ( 1 )] ["price"] )[1] )) {
				$event->setLine ( 2, "보상:" . explode ( "+", $this->NydusCanal_List ["warp"] [$event->getLine ( 1 )] ["price"] )[1] . "$" );
			} else {
				$event->setLine ( 2, "비용:" . $this->NydusCanal_List ["warp"] [$event->getLine ( 1 )] ["price"] . "$" );
			}
			$player->sendMessage ( "포탈생성이 완료되었습니다." );
			
			$block = $event->getBlock ();
			$this->NydusCanal_List ["signs"] [$player->getLevel ()->getFolderName ()] [$block->x . ":" . $block->y . ":" . $block->z] = $event->getLine ( 1 );
			if ($event->getLine ( 2 ) == "x") {
				$this->NydusCanal_List ["touch-signs"] [$player->getLevel ()->getFolderName ()] [$block->x . ":" . $block->y . ":" . $block->z] = 1;
				$event->setLine ( 2, "" );
			}
		}
	}
	public function touchSign(PlayerInteractEvent $event) {
		if ($event->getBlock ()->getId () == Block::SIGN_POST or $event->getBlock ()->getId () == Block::WALL_SIGN) {
			$x = $event->getBlock ()->x;
			$y = $event->getBlock ()->y;
			$z = $event->getBlock ()->z;
			
			if (isset ( $this->NydusCanal_List ["signs"] [$event->getPlayer ()->getLevel ()->getFolderName ()] [$x . ":" . $y . ":" . $z] )) {
				$this->PlacePrevent [$event->getPlayer ()->getName ()] = true;
				if (! $event->getPlayer ()->hasPermission ( "nyduscanal.signportal" )) {
					$event->getPlayer ()->sendMessage ( TextFormat::RED . "포탈을 이용할 수 있는 권한이 없습니다." );
					$event->setCancelled ();
				}
				$this->NydusCanal ( $event->getPlayer (), $this->NydusCanal_List ["signs"] [$event->getPlayer ()->getLevel ()->getFolderName ()] [$x . ":" . $y . ":" . $z] );
				$event->setCancelled ();
			}
		}
	}
	public function breakSign(BlockBreakEvent $event) {
		if ($event->getBlock ()->getId () == Block::SIGN_POST or $event->getBlock ()->getId () == Block::WALL_SIGN) {
			$block = $event->getBlock ();
			if ($event->getPlayer ()->hasPermission ( "simplearea.commands.delwarp" )) {
				if (isset ( $this->NydusCanal_List ["signs"] [$event->getPlayer ()->getLevel ()->getFolderName ()] [$block->x . ":" . $block->y . ":" . $block->z] )) {
					unset ( $this->NydusCanal_List ["signs"] [$event->getPlayer ()->getLevel ()->getFolderName ()] [$block->x . ":" . $block->y . ":" . $block->z] );
					if (isset ( $this->NydusCanal_List ["touch-signs"] [$event->getPlayer ()->getLevel ()->getFolderName ()] [$block->x . ":" . $block->y . ":" . $block->z] )) unset ( $this->NydusCanal_List ["touch-signs"] [$event->getPlayer ()->getLevel ()->getFolderName ()] [$block->x . ":" . $block->y . ":" . $block->z] );
					$event->setCancelled ();
				}
			}
		}
	}
	public function onMove(PlayerMoveEvent $event) {
		if (! $event->getPlayer ()->hasPermission ( "nyduscanal.portal" )) return;
		
		$to = $event->getTo ();
		$x = ( int ) round ( $to->x );
		$y = ( int ) round ( $to->y );
		$z = ( int ) round ( $to->z );
		
		if (isset ( $this->warpCooltime [$event->getPlayer ()->getName ()] )) {
			$this->warpCooltime [$event->getPlayer ()->getName ()] --;
			if ($this->warpCooltime [$event->getPlayer ()->getName ()] <= 0) unset ( $this->warpCooltime [$event->getPlayer ()->getName ()] );
			return;
		}
		
		$player = $event->getPlayer ();
		
		if ($this->checkMove ( $player, $x . ":" . $y . ":" . $z )) return;
		if ($this->checkMove ( $player, $x + 1 . ":" . $y . ":" . $z )) return;
		if ($this->checkMove ( $player, $x . ":" . $y . ":" . $z + 1 )) return;
		if ($this->checkMove ( $player, $x - 1 . ":" . $y . ":" . $z )) return;
		if ($this->checkMove ( $player, $x . ":" . $y . ":" . $z - 1 )) return;
	}
	public function checkMove(Player &$player, $pos) {
		if (isset ( $this->NydusCanal_List ["signs"] [$player->getLevel ()->getFolderName ()] [$pos] )) {
			if (isset ( $this->NydusCanal_List ["touch-signs"] [$player->getLevel ()->getFolderName ()] [$pos] )) return;
			$this->NydusCanal ( $player, $this->NydusCanal_List ["signs"] [$player->getLevel ()->getFolderName ()] [$pos] );
			$this->warpCooltime [$player->getName ()] = 10;
			return true;
		}
		return false;
	}
	public function onCommand(CommandSender $player, Command $command, $label, Array $args) {
		switch (strtolower ( $command->getName () )) {
			case "warp" :
				if ($args == null) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "/warp <이동할포인트>" );
					$player->sendMessage ( TextFormat::DARK_AQUA . "( 이동포인트는 /warplist로 확인가능 )" );
					return true;
				}
				if (! $player instanceof Player) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "콘솔내에선 이용불가능합니다." );
					return true;
				}
				
				if (! $player->hasPermission ( "nyduscanal.commands.addwarp" )) if (isset ( $this->NydusCanal_List ["reward-warp"] [$args [0]] )) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "보상지역은 명령어로 워프할 수 없습니다 !" );
					return true;
				}
				$this->NydusCanal ( $player, $args [0] );
				break;
			case "warplist" :
				$this->printWarpList ( $player );
				break;
			case "delwarp" :
				if ($args == null) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "/delwarp <워프포인트>" );
					return true;
				}
				if (isset ( $this->NydusCanal_List ["warp"] [$args [0]] )) {
					unset ( $this->NydusCanal_List ["warp"] [$args [0]] );
					$player->sendMessage ( "해당 워프포인트를 삭제했습니다." );
				} else {
					$player->sendMessage ( "해당 워프포인트가 존재하지않습니다." );
				}
				break;
			case "addwarp" :
				if (! $player instanceof Player) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "콘솔내에선 이용불가능합니다." );
					return true;
				}
				if ($args == null) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "/addwarp <워프포인트>" );
					return true;
				}
				if (isset ( $args [1] )) {
					if (isset ( explode ( '+', $args [1] )[1] )) {
						$givemode = 1;
						$args [1] = explode ( '+', $args [1] )[1];
					}
					if (isset ( explode ( '-', $args [1] )[1] )) $args [1] = explode ( '-', $args [1] )[1];
				}
				
				if (isset ( $args [0] )) {
					$warppoint = $args [0];
					if (isset ( $args [1] )) {
						if (! is_numeric ( $args [1] )) {
							$player->sendMessage ( TextFormat::DARK_AQUA . "이동비용은 숫자여야합니다." );
							return true;
						}
						$price = $args [1];
					}
				} else {
					$warppoint = $args;
				}
				if (isset ( $this->NydusCanal_List ["warp"] [$warppoint] )) {
					$player->sendMessage ( TextFormat::RED . "이미 있는 워프포인트 이름입니다 !" );
					$player->sendMessage ( TextFormat::RED . "( 다시생성하려면 /delwarp 로 지워주세요 ! )" );
					return true;
				}
				if (isset ( $args [2] )) {
					if (isset ( explode ( "timeout:", $args [2] )[1] )) {
						if (! is_numeric ( explode ( "timeout:", $args [2] )[1] )) {
							$player->sendMessage ( TextFormat::DARK_AQUA . "타임아웃값은 숫자로만 되야합니다." );
							return true;
						}
						$this->NydusCanal_List ["warp"] [$warppoint] ["timeout"] = explode ( "timeout:", $args [2] )[1];
					} else if (isset ( explode ( "cooltime:", $args [2] )[1] )) {
						if (! is_numeric ( explode ( "cooltime:", $args [2] )[1] )) {
							$player->sendMessage ( TextFormat::DARK_AQUA . "쿨타임값은 숫자로만 되야합니다." );
							return true;
						}
						$this->NydusCanal_List ["warp"] [$warppoint] ["cooltime"] = explode ( "cooltime:", $args [2] )[1];
					}
				}
				$this->NydusCanal_List ["warp"] [$warppoint] ["x"] = ( int ) round ( $player->x );
				$this->NydusCanal_List ["warp"] [$warppoint] ["y"] = ( int ) round ( $player->y );
				$this->NydusCanal_List ["warp"] [$warppoint] ["z"] = ( int ) round ( $player->z );
				$this->NydusCanal_List ["warp"] [$warppoint] ["yaw"] = ( int ) round ( $player->yaw );
				$this->NydusCanal_List ["warp"] [$warppoint] ["pitch"] = ( int ) round ( $player->pitch );
				$this->NydusCanal_List ["warp"] [$warppoint] ["level"] = $player->getLevel ()->getFolderName ();
				if (isset ( $price ) and $price != 0) {
					if (isset ( $givemode )) {
						$this->NydusCanal_List ["warp"] [$warppoint] ["price"] = "+" . $price;
						$this->NydusCanal_List ["reward-warp"] [$warppoint] = 1;
					} else {
						$this->NydusCanal_List ["warp"] [$warppoint] ["price"] = $price;
					}
				}
				$player->sendMessage ( TextFormat::DARK_AQUA . "워프포인트 생성에 성공했습니다." );
				break;
			case "lockwarp" :
				if ($args == null) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "/lockwarp <워프포인트>" );
					return true;
				}
				if (isset ( $args [0] )) {
					$warppoint = $args [0];
				} else {
					$warppoint = $args;
				}
				if (! isset ( $this->NydusCanal_List ["warp"] [$warppoint] )) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "해당 워프포인트가 존재하지 않습니다 !" );
					return true;
				}
				if (! isset ( $this->NydusCanal_List ["locked"] [$warppoint] )) {
					$this->NydusCanal_List ["locked"] [$warppoint] = 1;
					$player->sendMessage ( TextFormat::DARK_AQUA . "해당 워프포인트를 잠금처리했습니다 !" );
				} else {
					unset ( $this->NydusCanal_List ["locked"] [$warppoint] );
					$player->sendMessage ( TextFormat::DARK_AQUA . "해당 워프포인트를 잠금해제처리했습니다 !" );
				}
				break;
		}
		return true;
	}
	public function NydusCanal(Player $player, $warp = null) {
		if ($warp == null) return false;
		if (isset ( explode ( "[", $warp )[1] )) {
			$level = explode ( "[", $warp )[1];
			$level = explode ( "]", $level )[0];
			$level = $this->getServer ()->getLevelByName ( $level );
			if (! $level instanceof Level) {
				$player->sendMessage ( TextFormat::DARK_AQUA . $level . "맵 폴더를 찾을 수 없습니다 !, 워프불가" );
				return false;
			}
			$pos = $level->getSafeSpawn ();
			if ($pos == false) {
				$player->sendMessage ( TextFormat::RED . "해당 맵의 기본스폰위치를 찾을 수없습니다, 워프불가" );
				return false;
			}
			$player->teleport ( $pos );
			return true;
		}
		if (! isset ( $this->NydusCanal_List ["warp"] [$warp] )) {
			$player->sendMessage ( TextFormat::DARK_AQUA . "해당 워프가 삭제되어있습니다 !, 워프불가" );
			return false;
		}
		$x = $this->NydusCanal_List ["warp"] [$warp] ['x'];
		$y = $this->NydusCanal_List ["warp"] [$warp] ['y'];
		$z = $this->NydusCanal_List ["warp"] [$warp] ['z'];
		$yaw = $this->NydusCanal_List ["warp"] [$warp] ['yaw'];
		$pitch = $this->NydusCanal_List ["warp"] [$warp] ['pitch'];
		$level = $this->getServer ()->getLevelByName ( $this->NydusCanal_List ["warp"] [$warp] ['level'] );
		
		if (! $level instanceof Level) {
			$player->sendMessage ( TextFormat::DARK_AQUA . $this->NydusCanal_List ["warp"] [$warp] ['level'] . "맵 폴더를 찾을 수 없습니다 !, 워프불가" );
			return false;
		}
		if (! $player->hasPermission ( "nyduscanal.lockwarp" )) if (isset ( $this->NydusCanal_List ["locked"] [$warp] )) {
			$player->sendMessage ( TextFormat::DARK_AQUA . "해당 워프가 잠겨있습니다, 워프불가" );
			return false;
		}
		if (isset ( $this->NydusCanal_List ["warp"] [$warp] ["cooltime"] )) {
			if (! isset ( $this->customCooltime [$warp] [$player->getName ()] )) {
				$this->customCooltime [$warp] [$player->getName ()] = date ( "Y-m-d H:i:s" );
			} else {
				$before = $this->makeTimestamp ( $this->customCooltime [$warp] [$player->getName ()] );
				$after = $this->makeTimestamp ( date ( "Y-m-d H:i:s" ) );
				$timeout = intval ( $after - $before );
				if ($timeout < ( int ) $this->NydusCanal_List ["warp"] [$warp] ["cooltime"]) {
					$player->sendMessage ( TextFormat::RED . "워프 쿨타임이 아직 남았습니다!" );
					$player->sendMessage ( TextFormat::RED . intval ( ( int ) $this->NydusCanal_List ["warp"] [$warp] ["cooltime"] - $timeout ) . "초 후에 이용가능합니다!" );
					return false;
				} else {
					unset ( $this->customCooltime [$warp] [$player->getName ()] );
				}
			}
		}
		if (isset ( $this->timeout [$warp] [$player->getName ()] )) {
			$player->sendMessage ( TextFormat::DARK_AQUA . "[ 시간제한 ] 시간제한이 해제되었습니다." );
			$this->timeout [$warp] ["cancel"] [$player->getName ()] = 1;
		}
		if (isset ( $this->NydusCanal_List ["warp"] [$warp] ["timeout"] )) {
			if (! isset ( $this->timeout [$warp] [$player->getName ()] )) {
				$this->timeout [$warp] [$player->getName ()] = $player->add ( 2 );
				$player->sendMessage ( TextFormat::RED . "[ 주의 ] [ 시간제한 ] " . $this->NydusCanal_List ["warp"] [$warp] ["timeout"] . "초 뒤에 이전 위치로 복귀됩니다!" );
				$this->getServer ()->getScheduler ()->scheduleDelayedTask ( new CallbackTask ( [ 
						$this,
						"warpTimeout" ], [ 
						$player,
						$warp ] ), 20 * $this->NydusCanal_List ["warp"] [$warp] ["timeout"] );
			}
		}
		if (isset ( $this->NydusCanal_List ["warp"] [$warp] ["price"] ) and $this->checkEconomyAPI ()) {
			if (isset ( explode ( "+", $this->NydusCanal_List ["warp"] [$warp] ["price"] )[1] )) {
				$this->economyAPI->addMoney ( $player, explode ( "+", $this->NydusCanal_List ["warp"] [$warp] ["price"] )[1] );
			} else {
				$myMoney = $this->economyAPI->myMoney ( $player );
				if (($myMoney - $this->NydusCanal_List ["warp"] [$warp] ["price"]) < 0) {
					$player->sendMessage ( TextFormat::DARK_AQUA . "[ 서버 ] 이동비용이 부족합니다, 워프불가능" );
					return false;
				}
				$this->economyAPI->reduceMoney ( $player, $this->NydusCanal_List ["warp"] [$warp] ["price"] );
			}
		}
		$player->teleport ( new Position ( $x, $y, $z, $level ), $yaw, $pitch );
		$player->addEntityMotion ( 0, 0, 0.6, 0 );
		$player->sendMessage ( TextFormat::LIGHT_PURPLE . "[ 서버 ] " . $warp . " 로 워프 되었습니다" );
		if (isset ( $this->NydusCanal_List ["warp"] [$warp] ["price"] ) and $this->checkEconomyAPI ()) {
			if (isset ( explode ( "+", $this->NydusCanal_List ["warp"] [$warp] ["price"] )[1] )) {
				$player->sendMessage ( TextFormat::DARK_AQUA . "[ 서버 ] 보상금액 " . $this->NydusCanal_List ["warp"] [$warp] ["price"] . "$ 가 지급되었습니다." );
			} else {
				$player->sendMessage ( TextFormat::DARK_AQUA . "[ 서버 ] 워프비용 " . $this->NydusCanal_List ["warp"] [$warp] ["price"] . "$ 가 지불되었습니다." );
			}
		}
	}
	public function warpTimeout(Player $player, $warp) {
		if (! isset ( $this->timeout [$warp] ["cancel"] [$player->getName ()] )) {
			$player->sendMessage ( TextFormat::DARK_AQUA . "[ 워프 ] [ 시간제한 ] 이전 위치로 복귀되었습니다." );
			$player->teleport ( $this->timeout [$warp] [$player->getName ()] );
			unset ( $this->timeout [$warp] [$player->getName ()] );
			unset ( $this->timeout [$warp] ["cancel"] [$player->getName ()] );
		}
	}
	public function makeTimestamp($date) {
		$yy = substr ( $date, 0, 4 );
		$mm = substr ( $date, 5, 2 );
		$dd = substr ( $date, 8, 2 );
		$hh = substr ( $date, 11, 2 );
		$ii = substr ( $date, 14, 2 );
		$ss = substr ( $date, 17, 2 );
		return mktime ( $hh, $ii, $ss, $mm, $dd, $yy );
	}
	public function printWarpList($player) {
		$player->sendMessage ( TextFormat::DARK_AQUA . "*워프가능한 리스트를 출력합니다." );
		$result = TextFormat::WHITE;
		if (! isset ( $this->NydusCanal_List ["warp"] )) return false;
		foreach ( array_keys ( $this->NydusCanal_List ["warp"] ) as $list )
			$result .= $list . " ";
		$player->sendMessage ( $result );
	}
	public function checkEconomyAPI() {
		return (($this->getServer ()->getLoader ()->findClass ( 'onebone\\economyapi\\EconomyAPI' )) == null) ? false : true;
	}
}

?>