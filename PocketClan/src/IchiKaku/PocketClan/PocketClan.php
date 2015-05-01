<?php

namespace IchiKaku\PocketClan;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;


class PocketClan extends PluginBase implements Listener {
    private static $obj = null;

    public $m_version = 1;
    /** @var EconomyAPI */
    private $api = null;
    private $clanlist, $clandata, $playerclan, $setting, $type, $messages;
    /** @var  Config */
    private $clan_list, $clan_data, $player_clan;

    public static function getInstance() {
        return self::$obj;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if ($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") != null) $this->api = EconomyAPI::getInstance(); else {
            $this->getLogger()->error("'EconomyAPI' plugin was not activitied!");
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }

        $this->initMessage();

        $this->saveResource("config.yml", false);
        $this->setting = (new Config($this->getDataFolder() . "config.yml", Config::YAML))->getAll();
        $this->saveResource("clantype.yml", false);
        $this->type = (new Config($this->getDataFolder() . "clantype.yml", Config::YAML))->getAll();

        $this->loadData();
        $this->getLogger()->info(TextFormat::GOLD . $this->get("plugin-loaded"));
    }

    public function onDisable() {
        $this->saveData();
    }

    public function onChat(PlayerChatEvent $e) {
        $e->setCancelled(true);
        $e->getPlayer()->setRemoveFormat(false);
        $this->getServer()->broadcastMessage("[" . $this->getClan($e->getPlayer()->getName()) . "] " . $e->getPlayer()->getName() . " : " . $e->getMessage());
    }

    public function onCommand(CommandSender $sp, Command $command, $label, array $args) {
        $p = $sp->getName();
        if (!($sp instanceof Player)) $sp->sendMessage($this->get("command-inConsole")); else
            switch ($command) {
                case "clan":
                    if (!isset($args[0])) {
                        $sp->sendMessage("[PocketClan] Usage: /clan [make/join/leave/list");
                        break;
                    }
                    switch ($args[0]) {
                        case "make":
                            if (!isset($args[1])) {
                                $sp->sendMessage($this->get("clan-notInput"));
                                break;
                            }
                            if ($this->api->myMoney($p) < $this->setting["money"]) $sp->sendMessage("[PocketClan] You don't have enough money"); else if (isset($args[1])) {
                                $this->makeClan($sp, $args[1], $args[2]);
                            }
                            return true;
                        case "join" :
                            if (!isset($args[1])) {
                                $sp->sendMessage($this->get("clan-notInput"));
                                break;
                            }
                            if ($this->getClan($p) == $args[1]) {
                                $sp->sendMessage($this->get("aleady-inClan") . " [" . $args[1] . "]");
                                break;
                            }
                            if ($this->getClan($p) != "none") {
                                $sp->sendMessage($this->get("aleady-inClan") . " [" . $this->getClan($p) . "]");
                                break;
                            }
                            foreach ($this->clanlist as $cl) {
                                if ($cl == $args[1]) {
                                    $this->clandata[$args[1]][$p] = "user";
                                    array_push($this->clandata[$args[1]]["list"], $p);
                                    $this->playerclan[$p] = $args[1];
                                    $sp->sendMessage("[PocketClan] Succesfully joined in  " . "\"" . $args[1] . "\"");
                                    break;
                                } else $sp->sendMessage($this->get("PocketClan-cantfindclan"));
                            }
                            return true;
                        case "list" :
                            if (isset($args[1])) {
                                $list = "";
                                foreach ($this->clandata[$args[1]]["list"] as $cl) $list .= $cl . ",";
                                $sp->sendMessage("[PocketClan] " . $args[1] . " people : " . sizeof($this->clandata[$args[1]]["list"]) . " list : " . $list);
                            } else {
                                $list = "";
                                foreach ($this->clanlist as $cl) $list .= $cl . ",";
                                $sp->sendMessage("[PocketClan] " . $list);
                            }
                            return true;
                        case "leave" :
                            if ($this->getClan($p) != "none") {
                                $this->clandata[$this->getClan($p)][$p] = "NotInClan";
                                $this->playerclan[$p] = "none";
                                unset($this->clandata[$this->getClan($p)]["list"][array_search($p, $this->clandata[$this->getClan($p)]["list"])]);
                                $sp->sendMessage($this->get("leave-clan") . " [" . $this->getClan($p) . "]");
                            } else {
                                $sp->sendMessage($this->get("PocketClan-cantfindclan"));
                            }
                            break;
                        default:
                            $sp->sendMessage("[PocketClan] Usage: /clan [make/join/leave/list");
                    }
                    break;
                case "clanManage" :
                    switch ($args[0]) {
                        case "delete" :
                            if ($this->clandata[$this->getClan($p)][$p] == "manager") {
                                foreach ($this->clandata[$this->getClan($p)]["list"] as $pl) $this->playerclan[$pl] = "none";
                                unset($this->clanlist[array_search($this->getClan($p), $this->clanlist)]);
                                unset($this->clandata[array_search($this->getClan($p), $this->clandata)]);
                            } else if (!isset($args[1])) $sp->sendMessage("[PocketClan] Usage: /clan delete <name>");
                            else if ($sp->isOP()) {
                                if (!isset($this->clanlist[$args[1]])) {
                                    $sp->sendMessage("[PocketClan] Clan not found!");
                                    break;
                                }
                                foreach ($this->clandata[$args[1]]["list"] as $pl) $this->playerclan[$pl] = "none";
                                unset($this->clanlist[array_search($args[1], $this->clanlist)]);
                                unset($this->clandata[array_search($args[1], $this->clandata)]);
                            }
                            return true;
                        case "ban" :
                            if (!isset($args[1])) {
                                $sp->sendMessage("[PocketClan] Usage: /clan ban <name>");
                                break;
                            }
                            if (!isset($this->clandata[$this->getClan($p)]["list"][$args[1]])) {
                                $sp->sendMessage("[PocketClan] Player not found");
                                break;
                            }
                            if ($this->clandata[$this->getClan($p)][$p] == ("manager" || "staff")) {
                                $this->playerclan[$args[1]] = "none";
                                unset($this->clandata[$this->getClan($p)]["list"][array_search($p, $this->clandata[$this->getClan($p)]["list"])]);
                            }
                            return true;
                        case "admin" :
                            if (!isset($args[1])) $sp->sendMessage("[PocketClan] Usage: /clan admin <name>");
                            if (!isset($this->clandata[$this->getClan($p)]["list"][$args[1]])) {
                                $sp->sendMessage("[PocketClan] Player not found");
                                break;
                            }
                            if ($this->clandata[$this->getClan($p)][$p] == ("manager" || "staff")) {
                                $this->clandata[$this->getClan($p)]["list"][array_search($p, $this->clandata[$this->getClan($p)]["list"])] = "staff";
                            }
                            return true;
                        default:
                            $sp->sendMessage("[PocketClan] Usage: /clanManage [delete/ban/admin]");
                    }
                    break;
                default :
                    $sp->sendMessage("[PocketClan] Usage: /clanManage [delete/ban/admin]");
            }
        return true;
    }

    public function makeClan($maker, $name, $type = "default") {
        if ($maker instanceof Player) $this->api->reduceMoney($maker->getName(), 30000);
        $this->clanlist[$name] = $name;
        $this->clandata[$name][$maker->getName()] = "manager";
        $this->clandata[$name]["list"] = array();
        array_push($this->clandata[$name]["list"], $maker->getName());
        $this->playerclan[$maker->getName()] = $name;
        if (!isset($this->type[$type])) {
            $maker->sendMessage($this->get("type-notFound"));
            return;
        } else {
            $this->clandata[$name]["type"] = $type;
        }
        $maker->sendMessage($this->get("PocketClan-ClanMade") . " [" . $name . "]");
    }

    public function defineType($type) {
        array_push($this->type["type"], $type);
    }

    public function getClan($player) {
        return isset($this->playerclan[$player]) ? $this->playerclan[$player] : "none";
    }

    public function loadData() {
        $this->clan_list = $this->initializeYML("clan_list.yml", []);
        $this->clan_data = $this->initializeYML("clan_data.yml", []);
        $this->player_clan = $this->initializeYML("player_clan.yml", []);
        $this->clandata = $this->clan_data->getAll();
        $this->clanlist = $this->clan_list->getAll();
        $this->playerclan = $this->player_clan->getAll();
    }

    public function saveData() {
        $this->clan_list->setAll($this->clanlist);
        $this->clan_data->setAll($this->clandata);
        $this->player_clan->setAll($this->playerclan);
        $this->clan_list->save();
        $this->clan_data->save();
        $this->player_clan->save();
    }

    public function initializeYML($path, $array) {
        //method used by hmmm
        return new Config ($this->getDataFolder() . $path, Config::YAML, $array);
    }

    public function initMessage() {
        //method used by hmmm
        $this->saveResource("messages.yml", false);
        $this->messagesUpdate("messages.yml");
        $this->messages = (new Config ($this->getDataFolder() . "messages.yml", Config::YAML))->getAll();
    }

    public function messagesUpdate($targetYmlName) {
        //method used by hmmm
        $targetYml = (new Config ($this->getDataFolder() . $targetYmlName, Config::YAML))->getAll();
        if (!isset ($targetYml ["m_version"])) {
            $this->saveResource($targetYmlName, true);
        } else if ($targetYml ["m_version"] < $this->m_version) {
            $this->saveResource($targetYmlName, true);
        }
    }

    public function get($var) {
        //method used by hmmm
        return $this->messages [$this->messages ["default-language"] . "-" . $var];
    }
}