<?php
namespace PMSocket;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\TextFormat;

class PMSocket extends PluginBase implements Listener {
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info(TextFormat::GOLD . "[PMSocket]enabled");
        if ($this->getServer()->getPluginManager()->getPlugin("CustomPacket") === null) {
            $this->getServer()->getLogger()->critical("[CustomPacket Example] CustomPacket plugin was not found. This plugin will be disabled.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }
    public function onPacketReceive(CustomPacketReceiveEvent $ev) {
        $this->getLogger()->info("PacketRecieved : ".$ev->getPacket()->data);
        $this->getLogger()->info("Address - ".$ev->getPacket()->address.":".$ev->getPacket()->port);
        CPAPI::sendPacket(new DataPacket($ev->getPacket()->address, $ev->getPacket()->port, "hello"));
    }
}