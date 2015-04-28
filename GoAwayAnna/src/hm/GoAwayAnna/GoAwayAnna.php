<?php

namespace hm\GoAwayAnna;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\Network;

class GoAwayAnna extends PluginBase implements Listener {
    /** @var string */
    private $ip;

    /** @var int */
    private $port;

    /** @var array */
    private $lookup = [];

    public function onEnable(){
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $config = $this->getConfig();
        $this->ip = $config->get("ip", "127.0.0.1");
        $this->port = $config->get("port", 19132);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDataPacketReceived(DataPacketReceiveEvent $event){
        if($event->getPacket()->pid() == 0x82){
            if(count($this->getServer()->getOnlinePlayers()) <= $this->getServer()->getMaxPlayers()){
                return false;
            }

            $ip = $this->lookupAddress($this->ip);
            if($ip === null){
                return false;
            }

            $event->getPlayer()->dataPacket((new StrangePacket($ip, $this->port))->setChannel(Network::CHANNEL_ENTITY_SPAWNING));
            $event->setCancelled();
            return true;
        }
        return false;
    }

    private function lookupAddress($address){
        // IPv4 address
        if(preg_match("/^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$/", $address) > 0){
            return $address;
        }

        $address = strtolower($address);

        if(isset($this->lookup[$address])){
            return $this->lookup[$address];
        }

        $host = gethostbyname($address);
        if($host === $address){
            return null;
        }

        $this->lookup[$address] = $host;
        return $host;
    }
}

?>