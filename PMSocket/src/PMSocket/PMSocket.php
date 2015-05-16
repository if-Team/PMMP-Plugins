<?php
namespace PMSocket;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\TextFormat;

class PMSocket extends PluginBase implements Listener {
    public $adr, $port;
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info(TextFormat::GOLD . "[PMSocket]enabled");
        if ($this->getServer()->getPluginManager()->getPlugin("CustomPacket") === null) {
            $this->getServer()->getLogger()->critical("[CustomPacket Example] CustomPacket plugin was not found. This plugin will be disabled.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }
    public function onChat(PlayerChatEvent $ev) {
        CPAPI::sendPacket(new DataPacket($this->adr, $this->port, "[CHAT] "."<".$ev->getPlayer()->getName()."> ".$ev->getMessage()));
    }
    public function onPacketReceive(CustomPacketReceiveEvent $ev) {
        $data = explode(" ", $ev->getPacket()->data);
        $this->port = $ev->getPacket()->port;
        $this->adr = $ev->getPacket()->address;
        switch($data[0]) {
            case "connect" :
                $this->getLogger()->info("Connected in ".$this->adr.":".$this->port);
                break;
        }
    }
}