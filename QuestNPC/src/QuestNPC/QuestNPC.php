<?php

namespace QuestNPC;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\event\player\PlayerMoveEvent;

class QuestNPC extends PluginBase implements Listener{
    public $messages, $db, $temp;
    public $packet = []; //전역 패킷 변수
    public $positive = [];
    public $negative = [];

    public function onEnable(){
        @mkdir($this->getDataFolder());

        $this->initMessage();
        $this->db = (new Config($this->getDataFolder() . "QuestNPC_DB.yml", Config::YAML))->getAll();

        $this->packet["AddPlayerPacket"] = new AddPlayerPacket();
        $this->packet["AddPlayerPacket"]->clientID = 0;
        $this->packet["AddPlayerPacket"]->yaw = 0;
        $this->packet["AddPlayerPacket"]->pitch = 0;
        $this->packet["AddPlayerPacket"]->item = 0;
        $this->packet["AddPlayerPacket"]->meta = 0;
        $this->packet["AddPlayerPacket"]->metadata = [
            0 => ["type" => 0, "value" => 0],
            1 => ["type" => 1, "value" => 0],
            16 => ["type" => 0, "value" => 0],
            17 => ["type" => 6, "value" => [0, 0, 0]]
        ];

        $this->packet["RemovePlayerPacket"] = new RemovePlayerPacket();
        $this->packet["RemovePlayerPacket"]->clientID = 0;

        $this->packet["MovePlayerPacket"] = new MovePlayerPacket();

        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "tick"]), 2);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(){
        $save = new Config($this->getDataFolder() . "QuestNPC_DB.yml", Config::YAML);
        $save->setAll($this->db);
        $save->save();
    }

    public function onQuit(PlayerQuitEvent $event){
        if(isset($this->temp[$event->getPlayer()->getName()])){
            unset($this->temp[$event->getPlayer()->getName()]);
        }
    }

    public function onMove(PlayerMoveEvent $event){
        //TODO: temp에 활성화된 네임택이 있을 때에만
        //TODO: 여러명일 경우 가장 가까운 순서대로
        $pitch = $event->getPlayer()->pitch / 180 * M_PI;
        if($pitch < 0){ //UP
            $this->positive[$event->getPlayer()->getName()] = round(microtime(true) * 1000);
        }else if($pitch > 0){ //DOWN
            if(isset($this->positive[$event->getPlayer()->getName()])){
                $past = $this->positive[$event->getPlayer()->getName()];
                if((round(microtime(true) * 1000) - $past) <= 200){
                    //TODO: 종류별로 사전에 세팅한 내용 진행
                    //echo "긍정감지 ! \n";
                }
                unset($this->positive[$event->getPlayer()->getName()]);
            }
        }
    }

    public function SignChange(SignChangeEvent $event){
        if(!$event->getPlayer()->isOp()){
            return;
        }
        if(strtolower($event->getLine(0)) != $this->get("QuestNPC-line0")){
            return;
        }

        $message = $event->getLine(1);
        
        if($event->getLine(2) == null){
            $this->alert($event->getPlayer(), $this->get("PleaseChooseType"));
            $this->alert($event->getPlayer(), $this->get("TypeList"));
            return;
        }else{
            switch($event->getLine(2)){
                case $this->get("Type-Heal") :
                    //TODO 힐링형 - 긍정의사보이면 힐링
                    break;
                case $this->get("Type-Coll") :
                    //TODO 수집형 - 긍정의사보이면 뭔가 모아오게함
                    break;
                case $this->get("Type-Find") :
                    //TODO 탐색형 - 긍정의사보이면 다른 NPC를 찾아오게함
                    break;
                case $this->get("Type-Question") :
                    //TODO 문제형 - 긍정의사보이면 문제를 냄
                    break;
                case $this->get("Type-Abil") :
                    //TODO 능력형 - 긍정의사보이면 아이템을 받고 능력을 줌
                    break;
                default :
                    $this->alert($event->getPlayer(), $this->get("PleaseChooseType"));
                    $this->alert($event->getPlayer(), $this->get("TypeList"));
                    return;
            }
        }
        $block = $event->getBlock()->getSide(0);
        $blockPos = "{$block->x}.{$block->y}.{$block->z}";

        $this->db["QuestNPC"][$block->level->getFolderName()][$blockPos]["nametag"] = $message;
        $block->level->setBlock($block->getSide(1), Block::get(Block::AIR));
        $this->message($event->getPlayer(), $this->get("QuestNPC-added"));
    }

    public function BlockBreak(BlockBreakEvent $event){
        if(!$event->getPlayer()->isOp()){
            return;
        }

        $block = $event->getBlock();
        $blockPos = "{$block->x}.{$block->y}.{$block->z}";

        if(!isset($this->db["QuestNPC"][$block->level->getFolderName()][$blockPos])){
            return;
        }

        if(isset($this->temp[$event->getPlayer()->getName()]["nametag"][$blockPos])){
            $this->packet["RemovePlayerPacket"]->eid = $this->temp[$event->getPlayer()->getName()]["nametag"][$blockPos];
            $event->getPlayer()->dataPacket($this->packet["RemovePlayerPacket"]); //네임택 제거패킷 전송
        }

        unset($this->db["QuestNPC"][$block->level->getFolderName()][$blockPos]);
        $this->message($event->getPlayer(), $this->get("QuestNPC-deleted"));
    }

    public function tick(){
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if(!isset($this->db["QuestNPC"][$player->level->getFolderName()]["nametag"])){
                continue;
            }
            foreach($this->db["QuestNPC"][$player->level->getFolderName()]["nametag"] as $tagPos => $message){
                $explodePos = explode(".", $tagPos);
                if(!isset($explodePos[2])){
                    continue;
                }

                $dx = abs($explodePos[0] - $player->x);
                $dy = abs($explodePos[1] - $player->y);
                $dz = abs($explodePos[2] - $player->z);

                if(!($dx <= 25 and $dy <= 25 and $dz <= 25)){
                    //반경 25블럭을 넘어갔을경우 생성해제 패킷 전송후 생성패킷큐를 제거
                    if(isset($this->temp[$player->getName()]["nametag"][$tagPos])){
                        $this->packet["RemovePlayerPacket"]->eid = $this->temp[$player->getName()]["nametag"][$tagPos];
                        $player->dataPacket($this->packet["RemovePlayerPacket"]); //네임택 제거패킷 전송
                        unset($this->temp[$player->getName()]["nametag"][$tagPos]);
                    }
                }else{
                    //반경 25블럭 내일경우 생성패킷 전송 후 생성패킷큐에 추가
                    $x = $player->x - ($explodePos[0] + 0.4);
                    $y = $player->y - ($explodePos[1] + 1);
                    $z = $player->z - ($explodePos[2] + 0.4);
                    $dXZ = sqrt(pow($x, 2) + pow($z, 2));
                    $atn = atan2($z, $x);
                    $yaw = rad2deg($atn - M_PI_2);
                    $pitch = rad2deg(-atan2($y, $dXZ));

                    if(isset($this->temp[$player->getName()]["nametag"][$tagPos])){
                        //유저바라보게 하기
                        $this->packet["MovePlayerPacket"]->eid = $this->temp[$player->getName()]["nametag"][$tagPos];
                        $this->packet["MovePlayerPacket"]->x = ($explodePos[0] + 0.4);
                        $this->packet["MovePlayerPacket"]->y = ($explodePos[1] + 1);
                        $this->packet["MovePlayerPacket"]->z = ($explodePos[2] + 0.4);
                        $this->packet["MovePlayerPacket"]->yaw = $yaw;
                        $this->packet["MovePlayerPacket"]->pitch = $pitch;
                        $this->packet["MovePlayerPacket"]->bodyYaw = $yaw;
                        $player->directDataPacket($this->packet["MovePlayerPacket"]);
                        continue;
                    }

                    //유저 패킷을 상점밑에 보내서 네임택 출력
                    $this->temp[$player->getName()]["nametag"][$tagPos] = Entity::$entityCount++;
                    $this->packet["AddPlayerPacket"]->eid = $this->temp[$player->getName()]["nametag"][$tagPos];
                    $this->packet["AddPlayerPacket"]->username = $message;
                    $this->packet["AddPlayerPacket"]->x = $explodePos[0] + 0.4;
                    $this->packet["AddPlayerPacket"]->y = $explodePos[1] + 1;
                    $this->packet["AddPlayerPacket"]->z = $explodePos[2] + 0.4;
                    $this->packet["AddPlayerPacket"]->yaw = $yaw;
                    $this->packet["AddPlayerPacket"]->pitch = $pitch;
                    $player->dataPacket($this->packet["AddPlayerPacket"]);
                }
            }
        }
    }

    public function get($var){
        return $this->messages[$this->messages["default-language"] . "-" . $var];
    }

    public function initMessage(){
        $this->saveResource("messages.yml", false);
        $this->messages = (new Config($this->getDataFolder() . "messages.yml", Config::YAML))->getAll();
    }

    public function message(Player $player, $text = "", $mark = null){
        if($mark == null){
            $mark = $this->get("default-prefix");
        }
        $player->sendMessage(TextFormat::DARK_AQUA . $mark . " " . $text);
    }

    public function alert(Player $player, $text = "", $mark = null){
        if($mark == null){
            $mark = $this->get("default-prefix");
        }
        $player->sendMessage(TextFormat::RED . $mark . " " . $text);
    }
}

?>