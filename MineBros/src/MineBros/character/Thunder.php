<?php

use MineBros\Main;
use MineBros\character\BaseCharacter;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;
use pocketmine\math\Vector3;

class Thunder extends BaseCharacter {

    protected $amplify = array();
    protected $cnt = array();

    final public function getOptions(){
         return BaseCharacter::CLASS_B | BaseCharacter::TRIGR_TOUCH | BaseCharacter::TRIGR_PASIV;
    }

    final public function getDescription(){
        return <<<EOF
패시브: 4칸 이내의 주변 플레이어들을 2초마다 2의 데미지로 공격합니다.
스킬 사용: 패시브 데미지가 10초간 2배 증가합니다.
(발동 조건: 바닥 또는 아무 플레이어나 터치)
EOF;
    }

    final public function getName(){
        return '뇌전';
    }

    public function onTouchAnything(Player $who, $targetIsPlayer, Vector3 $pos, $targetPlayer = NULL){
        $this->amplify[$who->getName()] = true;
        $this->getProgressiveExecutionTask()->addTimer($this, 'disableAmplification', 10, $who);
        $who->sendMessage(Main::HEAD_MBROS.'[뇌전]의 데미지가 10초간 2배로 증가합니다.');
    }

    public function onPassiveTick(Player $who, $currentTick){
        if(!isset($this->cnt[$who->getName()])) $this->cnt[$who->getName()] = 1;
        if(++$this->cnt[$who->getName()] < 4) return;
        foreach(self::$owner->getServer()->getOnlinePlayers() as $p){
            if($p->getName() === $who->getName() or $p->distance($who) > 4) continue;
            $p->attack($this->amplify[$who->getName()] ? 4 : 2, new EntityDamageEvent($who, $p, self::EV_CAUSE_TASK, $this->amplify[$who->getName()] ? 4 : 2, 0));
        }
        $this->cnt[$who->getName()] = 1;
    }

    public function disableAmplification($player){
        $this->amplify[$who->getName()] = false;
        $who->sendMessage(Main::HEAD_MBROS.'[뇌전]의 데미지 증폭이 비활성화되었습니다.');
    }

}