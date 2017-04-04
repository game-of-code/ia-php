<?php

require_once('fight.php');

class Ia{

    private $api;
    private $playerKey = 'MySecretPlayer';
    private $character = 'WARRIOR';
    private $playerName = 'xortam';
    private $currentGame;
    private $myCharacter;
    private $currentAction;

    public function __construct($api){
        $this->api = $api;
    }

    public function versusIa(){
        $res = $this->api->createGame('PHP', false, false);
        if($res->getStatusCode()===200){
            $this->currentGame = json_decode($res->getBody());
            $res = $this->api->joinGame($this->currentGame->token, $this->playerKey, $this->character, $this->playerName);
              if($res->getStatusCode()===200){
                $this->currentGame = json_decode($res->getBody());
                if($this->currentGame->me){
                    $this->myCharacter = $this->currentGame->me->character;
                    $this->currentAction = $this->myCharacter->actions[0];
                }
                $this->startAttack();
              }

        }
    }

    public function startAttack(){
        if($this->currentGame){
            if($this->currentGame->countDown > 0){
                echo 'waiting for countdown ::'.$this->currentGame->countDown / 1000;
                sleep(round($this->currentGame->countDown / 1000, 0, PHP_ROUND_HALF_UP));
            }
            $this->performAction();
        }
    }

    public function performAction(){
        echo 'performing attack'.$this->currentAction->name.'---'.$this->currentGame->status;
        if($this->currentGame->status ==='PLAYING'){
            $res = $this->api->performAction($this->currentGame->token,$this->playerKey,$this->currentAction->name);
            if($res->getStatusCode()===200){
                $this->currentGame = json_decode($res->getBody());
                echo '\n performing action :: '.($this->currentGame->speed * $this->currentAction->coolDown)/1000;
                sleep(($this->currentGame->speed * $this->currentAction->coolDown)/1000);
                $this->performAction();
           }else{
               echo $res->getStatusCode().'<--->'.$res->getBody();
           }
        }
    }
    
    public static function main(){
        $fight = new Fight();

        $ia = new Ia($fight);
        $ia->versusIa();
         
    }
}

Ia::main();
?>