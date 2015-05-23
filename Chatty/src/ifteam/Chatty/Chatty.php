<?php

namespace ifteam\Chatty;

use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;
use pocketmine\entity\Entity;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\protocol\TextPacket;
use pocketmine\event\TranslationContainer;

class Chatty extends PluginBase implements Listener {
    public $packet = []; // 전역 패킷 변수
    public $packetQueue = []; // 패킷 큐
    public $messages, $db; // 메시지 변수, DB 변수
    public $messageStack = []; // 메시지 스택

    const MESSAGE_VERSION = 2; // 현재 메시지 버전
    const LOCAL_CHAT_DISTANCE = 50;

    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->initMessage(); // 기본언어메시지 초기화

        // YAML 형식의 DB 생성 후 불러오기
        $this->db = (new Config($this->getDataFolder() . "pluginDB.yml", Config::YAML, []))->getAll();

        $this->packet["AddPlayerPacket"] = new AddPlayerPacket();
        $this->packet["AddPlayerPacket"]->clientID = 0;
        $this->packet["AddPlayerPacket"]->yaw = 0;
        $this->packet["AddPlayerPacket"]->pitch = 0;
        $this->packet["AddPlayerPacket"]->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE], Entity::DATA_AIR => [Entity::DATA_TYPE_SHORT, 20], Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1]];

        // 플러그인의 명령어 등록
        $this->registerCommand($this->getMessage("Chatty"), $this->getMessage("Chatty"), "Chatty.commands", $this->getMessage("Chatty-command-help"), "/" . $this->getMessage("Chatty"));

        $this->packet["RemovePlayerPacket"] = new RemovePlayerPacket();
        $this->packet["RemovePlayerPacket"]->clientID = 0;

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new ChattyTask($this), 1);
    }

    public function onDisable(){
        $save = new Config($this->getDataFolder() . "pluginDB.yml", Config::YAML);
        $save->setAll($this->db);
        $save->save();
    }

    public function registerCommand($name, $fallback, $permission, $description = "", $usage = ""){
        $commandMap = $this->getServer()->getCommandMap();
        $command = new PluginCommand($name, $this);
        $command->setDescription($description);
        $command->setPermission($permission);
        $command->setUsage($usage);
        $commandMap->register($fallback, $command);
    }

    public function getMessage($var){
        if(isset($this->messages[$this->getServer()->getLanguage()->getLang()])){
            $lang = $this->getServer()->getLanguage()->getLang();
        }else{
            $lang = "eng";
        }
        return $this->messages[$lang . "-" . $var];
    }

    public function initMessage(){
        $this->saveResource("messages.yml", false);
        $this->updateMessage("messages.yml");
        $this->messages = (new Config($this->getDataFolder() . "messages.yml", Config::YAML))->getAll();
    }

    public function updateMessage($targetYmlName){
        $targetYml = (new Config($this->getDataFolder() . $targetYmlName, Config::YAML))->getAll();
        if(!isset($targetYml["message-version"])){
            $this->saveResource($targetYmlName, true);
        }else if($targetYml["message-version"] < self::MESSAGE_VERSION){
            $this->saveResource($targetYmlName, true);
        }
    }

    public function onLogin(PlayerLoginEvent $event){
        $name = $event->getPlayer()->getName();

        $this->messageStack[$name] = [];
        if(!isset($this->db[$name])){
            $this->db[$name] = [];
            $this->db[$name]["chat"] = true;
            $this->db[$name]["nametag"] = false;
            $this->db[$name]["local-chat"] = true;
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        unset($this->messageStack[$event->getPlayer()->getName()]);
        if(isset($this->packetQueue[$event->getPlayer()->getName()]))
            unset($this->packetQueue[$event->getPlayer()->getName()]);
    }

    public function putStack($name, $message){
        $messages = [];
        $enter = 50;
        $first = 0;
        $end = $enter;
        $strLen = mb_strlen($message, "UTF-8");
        while(1){
            if($strLen - $first > $enter){
                $messages[] = mb_substr($message, $first, $end, 'utf8');
                $first = $first + $enter;
                $end = $end + $enter;
            }else{
                $messages[] = mb_substr($message, $first, $end, 'utf8');
                break;
            }
        }
        foreach($messages as $m)
            array_push($this->messageStack[$name], $m);

        while(1){
            if(count($this->messageStack[$name]) > 6){
                array_shift($this->messageStack[$name]);
            }else{
                break;
            }
        }
    }

    public function prePlayerCommand(PlayerCommandPreprocessEvent $event){
        if(strpos($event->getMessage(), "/") === 0){
            return;
        }
        $event->setCancelled(true);

        $sender = $event->getPlayer();
        $this->getServer()->getPluginManager()->callEvent($myEvent = new PlayerChatEvent($sender, $event->getMessage()));

        if($myEvent->isCancelled()){
            return;
        }

        $message = $this->getServer()->getLanguage()->translateString($myEvent->getFormat(), [$myEvent->getPlayer()->getDisplayName(), $myEvent->getMessage()]);

        $this->getLogger()->info($message);
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if(isset($this->db[$player->getName()]["local-chat"]) and $this->db[$player->getName()]["local-chat"] === true){
                if($sender->distance($player) > self::LOCAL_CHAT_DISTANCE){
                    continue;
                }
            }
            $player->sendMessage($message);
        }
    }

    public function onDataPacket(DataPacketSendEvent $event){
        if(!$event->getPacket() instanceof TextPacket or $event->getPacket()->pid() != 0x85 or $event->isCancelled()){
            return;
        }

        if(isset($this->db[$event->getPlayer()->getName()]["chat"]) and $this->db[$event->getPlayer()->getName()]["chat"] == false){
            $event->setCancelled();
            return;
        }

        if(isset($this->db[$event->getPlayer()->getName()]["nametag"]) and $this->db[$event->getPlayer()->getName()]["nametag"] == true){
            $message = $this->getServer()->getLanguage()->translate(new TranslationContainer($event->getPacket()->message, $event->getPacket()->parameters));
            $this->putStack($event->getPlayer()->getName(), $message);
        }
    }

    public function tick(){
        foreach($this->getServer()->getOnlinePlayers() as $OnlinePlayer){
            if(isset($this->packetQueue[$OnlinePlayer->getName()]["eid"])){
                $this->packet["RemovePlayerPacket"]->eid = $this->packetQueue[$OnlinePlayer->getName()]["eid"];
                $this->packet["RemovePlayerPacket"]->clientID = $this->packetQueue[$OnlinePlayer->getName()]["eid"];
                $OnlinePlayer->directDataPacket($this->packet["RemovePlayerPacket"]); // 네임택 제거패킷 전송
            }

            if(!isset($this->db[$OnlinePlayer->getName()]["nametag"]) or $this->db[$OnlinePlayer->getName()]["nametag"] == false){
                continue;
            }

            $px = round($OnlinePlayer->x);
            $py = round($OnlinePlayer->y);
            $pz = round($OnlinePlayer->z);

            $allMessages = "";
            if(!isset($this->messageStack[$OnlinePlayer->getName()])){
                continue;
            }

            foreach($this->messageStack[$OnlinePlayer->getName()] as $message){
                $allMessages .= TextFormat::WHITE . $message . "\n"; // 색상표시시 \n이 작동안됨
            }

            $this->packetQueue[$OnlinePlayer->getName()]["x"] = round($px);
            $this->packetQueue[$OnlinePlayer->getName()]["y"] = round($py);
            $this->packetQueue[$OnlinePlayer->getName()]["z"] = round($pz);
            $this->packetQueue[$OnlinePlayer->getName()]["eid"] = Entity::$entityCount++;

            $this->packet["AddPlayerPacket"]->eid = $this->packetQueue[$OnlinePlayer->getName()]["eid"];
            $this->packet["AddPlayerPacket"]->clientID = $this->packetQueue[$OnlinePlayer->getName()]["eid"];
            $this->packet["AddPlayerPacket"]->username = $allMessages;
            $this->packet["AddPlayerPacket"]->x = $px + (-\sin(($OnlinePlayer->yaw   / 180 * M_PI) - 0.4)) * 7;
            $this->packet["AddPlayerPacket"]->y = $py + (-\sin( $OnlinePlayer->pitch / 180 * M_PI)       ) * 7;
            $this->packet["AddPlayerPacket"]->z = $pz + ( \cos(($OnlinePlayer->yaw   / 180 * M_PI) - 0.4)) * 7;

            $OnlinePlayer->dataPacket($this->packet["AddPlayerPacket"]);
        }
    }

    public function sendMessage(CommandSender $player, $text, $prefix = null){
        if($prefix == null){
            $prefix = $this->getMessage("default-prefix");
        }

        $player->sendMessage(TextFormat::DARK_AQUA . $prefix . " " . $this->getMessage($text));
    }

    public function sendAlert(CommandSender $player, $text, $prefix = null){
        if($prefix == null){
            $prefix = $this->getMessage("default-prefix");
        }

        $player->sendMessage(TextFormat::RED . $prefix . " " . $this->getMessage($text));
    }

    public function sendHelpMessage(CommandSender $sender){
        $this->sendMessage($sender, "help-on");
        $this->sendMessage($sender, "help-off");
        $this->sendMessage($sender, "help-local-chat");
        $this->sendMessage($sender, "help-nametag");
    }

    public function onCommand(CommandSender $player, Command $command, $label, Array $args){
        if(strToLower($command->getName()) != $this->getMessage("Chatty")){
            return true;
        }

        if(!isset($args[0])){
            $this->sendHelpMessage($player);
            return true;
        }

        if(!$player instanceof Player){
            $this->sendAlert($player, "only-in-game");
            return true;
        }

        switch($args[0]){
            default:
                $this->sendHelpMessage($player);
                break;

            case $this->getMessage("on"):
                $this->db[$player->getName()]["chat"] = true;
                $this->sendMessage($player, "chat-enabled");
                break;

            case $this->getMessage("off"):
                $this->db[$player->getName()]["chat"] = false;
                $this->sendMessage($player, "chat-disabled");
                break;

            case $this->getMessage("local-chat"):
                if(!isset($this->db[$player->getName()]["local-chat"]) or $this->db[$player->getName()]["local-chat"] == false){
                    $this->db[$player->getName()]["local-chat"] = true;
                    $this->sendMessage($player, "local-chat-enabled");
                }else{
                    $this->db[$player->getName()]["local-chat"] = false;
                    $this->sendMessage($player, "local-chat-disabled");
                }
                break;

            case $this->getMessage("nametag"):
                if(!isset($this->db[$player->getName()]["nametag"]) or $this->db[$player->getName()]["nametag"] == false){
                    $this->db[$player->getName()]["nametag"] = true;
                    $this->sendMessage($player, "nametag-enabled");
                }else{
                    $this->db[$player->getName()]["nametag"] = false;
                    $this->sendMessage($player, "nametag-disabled");
                }
                break;
        }
        return true;
    }
}

?>
