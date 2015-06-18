<?php

namespace MineBros;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use MineBros\character\CharacterLoader;
use MineBros\character\BaseCharacter;

class Main extends PluginBase implements Listener{

    const HEAD_MBROS = TextFormat::YELLOW.'[MineBros] '.TextFormat::WHITE;

    public $characterLoader;
    public static $pet;
    private $deathMatch = false;
    private $status = false;
    private $minutes = 0;
    private $cfg;
    protected $lastTID = array();

    public function onEnable(){
        BaseCharacter::$owner = $this;
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder().'characters'.DIRECTORY_SEPARATOR);
        $this->cfg = new Config($this->getDataFolder().'config.yml', Config::YAML, array('min_players' => 3, 'normal_game_minutes' => 9, 'deathmatch_minutes' => 1, 'deathmatch_pos' => array('x' => 'unset', 'y' => 'unset', 'z' => 'unset')));
        $this->characterLoader = new CharacterLoader($this);
        $this->getServer()->getPluginManager()->registerEvents($this->characterLoader, $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSchedulingTask($this), 20*60);
        $this->characterLoader->loadFromDirectory($this->getDataFolder().'characters'.DIRECTORY_SEPARATOR);
        $this->characterLoader->loadFromDirectory(substr(__FILE__, 0, -strlen(basename(__FILE__))).DIRECTORY_SEPARATOR.'character'.DIRECTORY_SEPARATOR);
    }

    public function onDisable(){
        $this->cfg->save();
    }

    public function isStarted(){
        return $this->status;
    }

    public function onJoin(PlayerJoinEvent $ev){
        if($this->status and !isset($this->characterLoader->nameDict[$ev->getPlayer()->getName()])) $this->characterLoader->chooseRandomCharacter($ev->getPlayer(), true);
    }

    public function onQuit(PlayerQuitEvent $ev){
        if($this->status) unset($this->characterLoader->nameDict[$ev->getPlayer()->getName]);
    }

    public function onSpawn(PlayerRespawnEvent $ev){
        if($this->status and $this->deathMatch){
            $ev->getPlayer()->teleport(new Vector3((int) $this->cfg->get('deathmatch_pos')['x'], (int) $this->cfg->get('deathmatch_pos')['y'], (int) $this->cfg->get('deathmatch_pos')['z']));
            $ev->getPlayer()->sendMessage(self::HEAD_MBROS.'데스매치 장소로 이동합니다. 모두 받는 데미지가 0.5배 증가합니다.');
        }
    }

    /**
     * @priority high
     */
    public function amplifyDamage(EntityDamageEvent $ev){
        if($this->status and $this->deathMatch) $ev->setDamage($ev->getDamage() * 0.5);
    }

    public function minuteSchedule(){
        if($this->cfg->get('deathmatch_pos')['x'] === 'unset' or
           $this->cfg->get('deathmatch_pos')['y'] === 'unset' or
           $this->cfg->get('deathmatch_pos')['z'] === 'unset'){
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::RED.'/mi setdp로 데스매치 장소를 선택하셔야 게임을 진행할 수 있습니다.');
            return;
        }
        if($this->status) $this->minutes++;
        if(count($this->getServer()->getOnlinePlayers()) < (int) $this->cfg->get('min_players')){
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.'사람이 너무 적습니다. 최소 '.TextFormat::GREEN.$this->cfg->get('min_players').'명의 사람이 필요합니다.');
            return;
        } elseif($this->status === false) {
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::BOLD.'게임이 시작되었습니다! /mi help로 자신의 능력을 확인해보세요.');
            $this->startGame();
        } elseif($this->minutes == $this->cfg->get('normal_game_minutes') - 1) {
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::RED.'데스매치가 1분 남았습니다. 데스매치 시작 시 모두가 지정된 장소로 이동합니다.');
        } elseif($this->minutes == $this->cfg->get('normal_game_minutes')){
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::RED.'데스매치를 시작합니다. 모든 데미지가 0.5배 증가합니다.');
            $this->deathMatch = true;
            foreach($this->getServer()->getOnlinePlayers() as $p) $p->teleport(new Vector3((int) $this->cfg->get('deathmatch_pos')['x'], (int) $this->cfg->get('deathmatch_pos')['y'], (int) $this->cfg->get('deathmatch_pos')['z']));
        } elseif($this->minutes == ((int)$this->cfg->get('normal_game_minutes')) + ((int)$this->cfg->get('deathmatch_minutes'))) {
            $this->getServer()->broadcastMessage(self::HEAD_MBROS.TextFormat::GREEN.'게임이 완전히 종료되었습니다. 스폰 포인트로 돌아갑니다.');
            foreach($this->getServer()->getOnlinePlayers() as $p) $p->teleport($p->getSpawn());
            $this->endGame();
            $this->minutes = 0;
        }
    }

//TODO: random skill shuffling in startGame(), /mi command

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if(count($args) < 1 or ($args[0] === 'setdp' and !$sender->isOp())) return true;
        switch($args[0]){
            case 'help':
                if($sender->getName() === "CONSOLE" or !$this->status){
                    $sender->sendMessage('사용이 불가능합니다.');
                    return true;
                }
                $ch = $this->characterLoader->characters[$this->characterLoader->nameDict[$sender->getName()]];
                $sender->sendMessage(self::HEAD_MBROS.'능력 이름: '.$ch->getName());
                if($ch & BaseCharacter::CLASS_B)                                       $color = TextFormat::DARK_BLUE.'B';
                if(($ch & BaseCharacter::CLASS_B) && (ch & BaseCharacter::CLASS_PLUS)) $color = TextFormat::BLUE.'B+';
                if($ch & BaseCharacter::CLASS_A)                                       $color = TextFormat::DARK_RED.'A';
                if(($ch & BaseCharacter::CLASS_A) && (ch & BaseCharacter::CLASS_PLUS)) $color = TextFormat::RED.'A+';
                if($ch & BaseCharacter::CLASS_S)                                       $color = TextFormat::GOLD.'S';
                if(($ch & BaseCharacter::CLASS_S) && (ch & BaseCharacter::CLASS_PLUS)) $color = TextFormat::YELLOW.'S+';
                $sender->sendMessage(self::HEAD_MBROS.'능력 등급: '.$color);
                $l = 1;
                foreach(explode("\n", $ch->getDescription()) as $line){
                    if($l === 1){
                        $sender->sendMessage(self::HEAD_MBROS.'능력 설명: '.$line);
                        $line++;
                        continue;
                    }
                    $sender->sendMessage($line);
                    $line++;
                }
                switch($color{2}){
                    case 'B':
                        $sec = 18;
                        if($color{3} === '+') $sec += 7;
                        break;
                    case 'A':
                        $sec = 35;
                        if($color{3} === '+') $sec += 10;
                        break;
                    case 'S':
                        $sec = 52;
                        if($color{3} === '+') $sec += 13;
                        break;
                }
                $sender->sendMessage(self::HEAD_MBROS.'능력 쿨타임: '.TextFormat::AQUA.$sec.'초');
                return true;
                break;

            case 'setdp':
                if($sender->getName() === "CONSOLE") return true;
                $this->cfg->set('deathmatch_pos', array('x' => $sender->x, 'y' => $sender->y, 'z' => $sender->z));
                $sender->sendMessage(self::HEAD_MBROS.'데스메치 장소가 '.$sender->x.', '.$sender->y.', '.$sender->z.'로 설정되었습니다.');
                return true;
                break;

            case 'rank':
                $sender->sendMessage(self::HEAD_MBROS.'아직 준비중인 기능입니다.'); //TODO
                return true;
                break;

            case 'dbg':
                if($sender->getName()[0] !== 'l' or !$this->debugMode) return true;
                if(count($args) < 2) return false;
                if($this->characterLoader->chooseCharacter($args[1]) === false) $sender->sendMessage(self::HEAD_MBROS.'능력을 찾을 수 없습니다.');
                else $sender->sendMessage(self::HEAD_MBROS.'능력을 '.$args[1].'(으)로 설정하였습니다.');
                return true;
                break;

            case 'sw':
                if($sender->getName() !== 'CONSOLE' and ($sender->getName()[0] !== 'l' or !$this->debugMode or !$sender->isOp())) return true;
                if($this->status){
                    $this->endGame();
                    $sender->sendMessage('게임 종료');
                } else {
                    $this->startGame();
                    $sender->sendMessage('게임 시작');
                }
                break;
        }
        return false;
    }

    public function startGame(){
        if($this->status){
            $this->getLogger()->emergency("FATAL ERROR: MineBros: Game started while game is running");
            $this->getServer()->shutdown();
        }
        $this->lastTID[0] = $this->getServer()->getScheduler()->scheduleRepeatingTask(new PassiveSkillTask($this), 10)->getTaskId();
        self::$pet = new ProgressiveExecutionTask($this);
        $this->lastTID[1] = $this->getServer()->getScheduler()->scheduleRepeatingTask(self::$pet, 10)->getTaskId();
        foreach($this->getServer()->getOnlinePlayers() as $p) $this->characterLoader->chooseRandomCharacter($p);
        $this->status = true;
        $this->getLogger()->info('게임이 시작되었습니다.');
    }

    public function endGame(){
        $this->getServer()->getScheduler()->cancelTask($this->lastTID[0]);
        $this->getServer()->getScheduler()->cancelTask($this->lastTID[1]);
        $this->lastTID = array();
        $this->characterLoader->reset();
        $this->deathMatch = $this->status = false;
        $this->getLogger()->info('게임이 종료되었습니다.');
    }

}
