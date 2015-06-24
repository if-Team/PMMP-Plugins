<?php

/**
 * @author ChalkPE <amato0617@gmail.com>
 * @since 2015-06-24 20:50
 */

namespace chalk\cameraman\task;

use chalk\cameraman\Cameraman;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;

class CountdownTask extends PluginTask {
    /** @var Player */
    private $player;

    /** @var string */
    private $message;

    /** @var callable */
    private $callback;

    /** @var int */
    private $countdown;

    /**
     * @param Player $player
     * @param string $message
     * @param callable $callback
     * @param int $countdown
     */
    function __construct(Player $player, $message, callable $callback, $countdown = 3){
        parent::__construct(Cameraman::getInstance());

        $this->player = $player;
        $this->message = $message;
        $this->callback = $callback;
        $this->countdown = $countdown;
    }

    /**
     * @param $currentTick
     */
    public function onRun($currentTick){
        echo $this->countdown . PHP_EOL;
        if($this->countdown <= 0){
            Cameraman::getInstance()->cancelCountdownTask($this); //FIXME: Task doesn't stop!
            return;
        }

        if(is_string($this->message)){
            Cameraman::getInstance()->sendMessage($this->player, str_replace("%countdown%", $this->countdown, $this->message));
        }

        $this->countdown--;
    }

    public function onCancel(){
        call_user_func($this->callback);
    }


}