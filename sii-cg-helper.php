<?php
use GuzzleHttp\Client;

define("BASE_API_URL", "https://coding-game.swat-sii.fr/api");

class CHARACTERS{
    const WARRIOR="WARRIOR";
    const PALADIN="PALADIN";
    const DRUID="DRUID";
    const SORCERER="SORCERER";
}

class ACTIONS{
    const HIT = "HIT";
    const THURST = "THURST";
    const HEAL = "HEAL";
    const SHIELD = "SHIELD";
}

class GAME_STATUS{
    const WAITING = "WAITING";
    const PLAYING = "PLAYING";
    const FINISHED = "FINISHED";
}

class SIICgHelper{
    private $me=null;
    private $opponenet=null;
    private $speed=0;
    private $gameToken=null;

    private $client;

    public function __construct(){
        $this->client = new Client(['base_uri' => BASE_API_URL, 'verify' => false ]);
    }

    private function extractGame($res){
        if($res->getStatusCode() === 200){
            return json_decode($res->getBody());
        }else{
            return null;
        }
    }

    private function resolveGameStarted($game){
        $this->gameToken = $game->token;
        $this->speed = $game->speed;
        if($game->status === GAME_STATUS::PLAYING){
            $this->me = $game->me;
            $this->opponent = $game->foe;
            usleep($game->countDown*1000);
            return true;
        }else{
            return false;
        }
    }



    public function createGame($name, $speedy, $versus){
        $res = $this->client->request('POST', '/api/fights', [
            'json' => [
                'name' => $name,
                'speedy'=> $speedy,
                'versus'=> $versus
            ]
        ]);
        return $this->extractGame($res);
    }

    public function joinGame($gameToken, $playerKey, $playerName, $character){
         $res = $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey, [
            'json' => [
                'character' => $character,
                'name'=> $playerName
            ]
        ]);

       return $this->extractGame($res);
    }

    public function joinGameWithCountDown($gameToken, $playerKey, $playerName, $character){
         $res = $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey, [
            'json' => [
                'character' => $character,
                'name'=> $playerName
            ]
        ]);

        $game = $this->extractGame($res);
        if($game){
            if($this->resolveGameStarted($game)){
                return $game;
            }else{
                return $this->checkGameReady($gameToken, $playerKey);
            }

        }
    }

    public function getGame($gameToken, $playerKey){
        $res = $this->client->request('GET', '/api/fights/'.$gameToken.'/players/'.$playerKey);
        return $this.extractGame($res);
    }

    public function checkGameReady($gameToken, $playerKey){
        $game = $this->getGame($gameToken, $playerKey);
        if(!$this->resolveGameStarted($game)){
            sleep(1);
            return checkGameReady($gameToken, $playerKey);
        }else{
            return $game;
        }
    }

    public function makeAction($gameToken, $playerKey, $actionName, $delay){
        if($delay){
            usleep($delay*1000);
        }
        $res = $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey.'/actions/'.$action);
        $game = $this->extractGame($res);
        $this->me = $game->me;
        $this->opponent = $game->foe;
        return $game;
    }


    public function makeActionWithCoolDown($gameToken, $playerKey, $actionName, $delay){
        $coolDown = 0;
        foreach($this->me->character->actions as $action){
            if($action->name === $actionName){
                $coolDown = $action->coolDown;
                break;
            }
        }
        $coolDown = $coolDown * $this->speed;
        $game = $this->makeAction($gameToken, $playerKey, $actionName, delay);
        usleep($coolDown * 1000);
        return $game;
    }

    public function generateUniquePlayerKey() {
        return md5(uniqid(rand(),true));
    }


}

// define("SII_CG_HELPER", new SIICgHelper());
?>