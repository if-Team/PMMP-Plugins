<?php
namespace PMSocket;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\item\DiamondAxe;
use pocketmine\plugin\PluginBase;
use ifteam\CustomPacket\event\CustomPacketReceiveEvent;
use ifteam\CustomPacket\DataPacket;
use ifteam\CustomPacket\CPAPI;
use pocketmine\utils\TextFormat;
use PMSocket\PMAttachment;

class PMSocket extends PluginBase implements Listener {
    private $adr, $port;
    /* @var PMAttachMent */
    public $att;

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
        $data = explode(" ", $ev->getPacket()->data);
        $this->adr = $ev->getPacket()->address;
        $this->port = $ev->getPacket()->port;
        switch ($data[0]) {
            case "connect" :
                if ($this->adr == null && $this->port == null) $this->getLogger()->info("Connected in " . $this->adr . ":" . $this->port); else {
                    $this->getLogger()->info("Tried to Connect in " . $this->adr . ":" . $this->port);
                    CPAPI::sendPacket(new DataPacket($this->adr, $this->port, "cantconnect"));
                }

                break;
        }
    }
}