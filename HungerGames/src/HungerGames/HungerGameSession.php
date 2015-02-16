<?php

namespace HungerGames;

use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class HungerGameSession {
	public $sakura = [ ];
	public $ATField = 0;
	public $firstpos = 0;
	public function TouchCheck(Player $player, $x, $y, $z, $check) {
		if (isset ( $this->sakura [$x . ":" . $y . ":" . $z] )) {
			if ($check == 1) $player->sendMessage ( TextFormat::RED . "이미 아이템을 얻은 상자입니다 !" );
			if ($check == 2) $player->sendMessage ( TextFormat::RED . "이미 체력을 얻은 블럭입니다 !" );
		} else {
			// 아이템랜덤증정
			$this->sakura [$x . ":" . $y . ":" . $z] = 1;
			$category = rand ( 1, 4 );
			if ($check == 1) {
				$player->sendMessage ( TextFormat::DARK_AQUA . "아이템을 얻었습니다 !" );
			}
			if ($check == 2) {
				$player->setHealth ( $player->getHealth () + 2 );
				$player->sendMessage ( TextFormat::DARK_AQUA . "체력을 회복했습니다 !" );
			}
			if ($check == 1) switch ($category) {
				case 1 :
					$sword_c = rand ( 1, 9 );
					if ($sword_c == 1) $player->getInventory ()->addItem ( new Item ( Item::IRON_SWORD, 0, 1 ) );
					if ($sword_c == 2) $player->getInventory ()->addItem ( new Item ( Item::WOODEN_SWORD, 0, 1 ) );
					if ($sword_c == 3) $player->getInventory ()->addItem ( new Item ( Item::STONE_SWORD, 0, 1 ) );
					if ($sword_c == 4) $player->getInventory ()->addItem ( new Item ( Item::DIAMOND_SWORD, 0, 1 ) );
					if ($sword_c == 5) $player->getInventory ()->addItem ( new Item ( Item::GOLD_SWORD, 0, 1 ) );
					if ($sword_c == 6) $player->getInventory ()->addItem ( new Item ( Item::WOODEN_AXE, 0, 1 ) );
					if ($sword_c == 7) $player->getInventory ()->addItem ( new Item ( Item::STONE_AXE, 0, 1 ) );
					if ($sword_c == 8) $player->getInventory ()->addItem ( new Item ( Item::DIAMOND_AXE, 0, 1 ) );
					if ($sword_c == 9) $player->getInventory ()->addItem ( new Item ( Item::GOLD_AXE, 0, 1 ) );
					break;
				case 2 :
					foreach ( $player->getInventory ()->getContents () as $inven ) {
						if ($inven->getID () == Item::BOW) {
							$player->getInventory ()->addItem ( new Item ( Item::ARROW, 0, 12 ) );
							return;
						}
					}
					$player->getInventory ()->addItem ( new Item ( Item::BOW, 0, 1 ) );
					$player->getInventory ()->addItem ( new Item ( Item::ARROW, 0, 12 ) );
					break;
				case 3 :
					$care_c = rand ( 1, 3 );
					if ($care_c == 1) $player->getInventory ()->addItem ( new Item ( Item::APPLE, 0, 3 ) );
					if ($care_c == 2) $player->getInventory ()->addItem ( new Item ( Item::COOKED_PORKCHOP, 0, 3 ) );
					if ($care_c == 3) $player->getInventory ()->addItem ( new Item ( Item::COOKED_CHICKEN, 0, 3 ) );
					break;
				case 4 :
					foreach ( $player->getInventory ()->getContents () as $inven ) {
						$c = array (
								Item::LEATHER_CAP,
								Item::CHAIN_HELMET,
								Item::IRON_HELMET,
								Item::DIAMOND_HELMET,
								Item::GOLD_HELMET );
						foreach ( $c as $cc )
							if ($inven->getID () == $cc) return;
					}
					$armor_c = rand ( 1, 5 );
					if ($armor_c == 1) {
						$player->getInventory ()->setArmorContents ( [ 
								new Item ( 298 ),
								new Item ( 299 ),
								new Item ( 300 ),
								new Item ( 301 ) ] );
						$player->getInventory ()->sendArmorContents ( $player );
						$player->sendMessage ( TextFormat::RED . "[보호] 가죽갑옷이 장착되었습니다 !" );
					}
					if ($armor_c == 2) {
						$player->getInventory ()->setArmorContents ( [ 
								new Item ( 302 ),
								new Item ( 303 ),
								new Item ( 304 ),
								new Item ( 305 ) ] );
						$player->getInventory ()->sendArmorContents ( $player );
						$player->sendMessage ( TextFormat::RED . "[보호] 체인갑옷이 장착되었습니다 !" );
					}
					if ($armor_c == 3) {
						$player->getInventory ()->setArmorContents ( [ 
								new Item ( 306 ),
								new Item ( 307 ),
								new Item ( 308 ),
								new Item ( 309 ) ] );
						$player->getInventory ()->sendArmorContents ( $player );
						$player->sendMessage ( TextFormat::RED . "[보호] 철갑옷이 장착되었습니다 !" );
					}
					if ($armor_c == 4) {
						$player->getInventory ()->setArmorContents ( [ 
								new Item ( 310 ),
								new Item ( 311 ),
								new Item ( 312 ),
								new Item ( 313 ) ] );
						$player->getInventory ()->sendArmorContents ( $player );
						$player->sendMessage ( TextFormat::RED . "[보호] 다이아갑옷이 장착되었습니다 !" );
					}
					if ($armor_c == 5) {
						$player->getInventory ()->setArmorContents ( [ 
								new Item ( 314 ),
								new Item ( 315 ),
								new Item ( 316 ),
								new Item ( 317 ) ] );
						$player->getInventory ()->sendArmorContents ( $player );
						$player->sendMessage ( TextFormat::RED . "[보호] 금갑옷이 장착되었습니다 !" );
					}
					break;
			}
		}
	}
}

?>