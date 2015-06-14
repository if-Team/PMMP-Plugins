<?php

namespace MineBros\character;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use MineBros\Main;

class CharacterLoader implements Listener{

    public $nameDict;

    private $characters = array();
    private $owner;

    public function __construct(Main $owner){
        $this->owner = $owner;
    }

    public function chooseRandomCharacter(){

    }

    public function registerCharacter(BaseCharacter $character){
        if(isset($this->characters[$character->getName()])){
            $owner->getLogger()->warning("[MineBros] Oops: Duplicated name detected while registering character");
            return false;
        }
        $this->characters[] = $character;
    }

    public function onBlockTouch(PlayerInteractEvent $ev){
        if($ev->getPlayer()->getInventory()->getItemInHand()->getId() !== 265) return; //Iron ingot
        if(!isset($ch = $this->nameDict[$ev->getPlayer())->getName()])) return;
        if($ch->getOptions() & BaseCharacter::TRIGR_TOUCH){
            $ev->setCancelled();
            $ch->onTouchAnything($ev->getPlayer(), false, $ev->getTouchVector());
        }
    }

    public function onPlayerTouch(EntityDamageByEntityEvent $ev){
        if(!($ev->getEntity() instanceof Player and $ev->getDamager() instanceof Player)
          or $ev->getPlayer()->getInventory()->getItemInHand()->getId() !== 265
          or !isset($ch = $this->nameDict[$ev->getEntity()->getName()])) return;
        if($ch->getOptions() & BaseCharacter::TRIGR_TOUCH){
            $ev->setCancelled();
            $entity = $ev->getEntity();
            $ch->onTouchAnything($ev->getEntity(), true, new Vector3($entity->x, $entity->y, $entity->z), $ev->getEntity());
        }
        if($ch->getOptions() & BaseCharacter::TRIGR_PONLY){
            $ev->setCancelled();
            $ch->onHarmPlayer($ev->getEntity(), $ev->getDamager());
        }
    }

}