<?php

namespace ChatReport;

use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class Main extends PluginBase implements Listener{

    private $last_id = 0;
    private $last_chat;

    public function onEnable(){
        $this->last_chat = new \SplFixedArray(256);
        @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * @priority high
     */
    public function onChat(PlayerChatEvent $ev){
        $id = sprintf("%02x", $this->last_id);
        $this->last_chat[$this->last_id] = $ev->getPlayer()->getDisplayName() . ($hooked = ": {". $id . "} " . $ev->getMessage());
        ++$this->last_id > 255 ? $this->last_id -= 256 : false;
        $ev->setMessage($hooked);
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if($sender->getName() === "CONSOLE") return true;
        if(!isset($args[0]) or !is_numeric($args[0]) or ($id = hexdec($args[0])) < 0 or $id > 256) return false;

        $file = "=== START CHAT REPORT ===\n\nReported by: " . $sender->getName() . "\nReported at: ".date("Y-m-d | h:i:sa")."\n";
        for($i = $id + 252; $i <= $id + 256; $i++){
            isset($this->last_chat[$i % 256]) ? $file .= "\n" . $this->last_chat[$i % 256] : false;
        }
        $file .= "\n\n=== END CHAT REPORT ===";
        file_put_contents(($dir = $this->getDataFolder().DIRECTORY_SEPARATOR."report-".date("Y-m-d.h.i.sa").".txt"), $file);
        $sender->sendMessage("[ChatReport] 채팅 신고가 접수되었습니다.");
        $sender->sendMessage("[ChatReport] 파일은 서버 내의 report-".date("Y-m-d.h.i.sa").".txt에 저장되었습니다.");
        $sender->sendMessage("[ChatReport] 현재 스크린을 캡쳐하여  간단한 설명과 함께 올려주세요.");
        $this->getServer()->getLogger()->notice("[ChatReport] NEW CHAT REPORT: Please check ". $dir . " file.");

        return true;
    }
}
