<?php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;


define("BASE_API_URL", "https://coding-game.swat-sii.fr/api");
// define("BASE_API_URL", "http://192.168.0.2/api");

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
    private $opponent=null;
    private $speed=0;
    private $gameToken=null;

    private $client;

    public function __construct(){
        $this->client = new Client(['base_uri' => BASE_API_URL, 'verify' => false ]);
    }

    private function extractGame($res){
        echo "status code : ".$res->getStatusCode()."\n";
        if($res->getStatusCode() < 400){
            return json_decode($res->getBody());
        }else{
            $obj = new stdClass();
            $obj->error = new stdClass();
            $obj->error->statusCode = $res->getStatusCode();
            return $obj;
        }
    }

    private function resolveGameStarted($game){
        $this->gameToken = $game->token;
        $this->speed = $game->speed;
        if($game->status === GAME_STATUS::PLAYING){
            $this->me = $game->me;
            $this->opponent = $game->foe;
            $start = $game->countDown + 10;
            $start = $start * 1000;
            echo "waiting for start".$start."\n";
            usleep($start);
            return true;
        }else{
            return false;
        }
    }



    public function createGame($name, $speedy, $versus){
        echo "TEST : ".$name." speed:  ".$speedy." versus : ".$versus;
        try{
            $res = $this->client->request('POST', '/api/fights', [
                'json' => [
                    'name' => $name,
                    'speedy'=> $speedy,
                    'versus'=> $versus
                ]
            ]);
            return $this->extractGame($res);
        }catch(RequestException $e){
            if($e->hasResponse()){
                return $this->extractGame($e->getResponse());
            }
            return null;
        }
    }

    public function joinGame($gameToken, $playerKey, $playerName, $character){
        echo "join game".$gameToken;
        try{
            $res = $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey, [
                'json' => [
                    'character' => $character,
                    'name'=> $playerName
                ]
            ]);

            return $this->extractGame($res);
        }catch(RequestException $e){
            if($e->hasResponse()){
                return $this->extractGame($e->getResponse());
            }
            return null;
        }
    }

    public function joinGameWithCountDown($gameToken, $playerKey, $playerName, $character){
        $game = $this->joinGame($gameToken, $playerKey, $playerName, $character);
        if($game){
            if(!property_exists($game, "error")){
                if($this->resolveGameStarted($game)){
                    return $game;
                }else{
                    return $this->checkGameReady($gameToken, $playerKey);
                }

            }else{
                return $game;
            }

        }
    }

    public function getGame($gameToken, $playerKey){
        try{
            $res = $this->client->request('GET', '/api/fights/'.$gameToken.'/players/'.$playerKey);
            return $this->extractGame($res);
        }catch(RequestException $e){
            if($e->hasResponse()){
                return $this->extractGame($e->getResponse());
            }
            return null;
        }
    }

    public function checkGameReady($gameToken, $playerKey){
        echo "check game ready";

        $game = $this->getGame($gameToken, $playerKey);
        if(!$this->resolveGameStarted($game)){
            sleep(1);
            return $this->checkGameReady($gameToken, $playerKey);
        }else{
            return $game;
        }
    }

    public function performAction($gameToken, $playerKey, $actionName, $delay){
        if($delay){
            usleep($delay*1000);
        }
        try{
            $res = $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey.'/actions/'.$actionName);
            $game = $this->extractGame($res);
            $this->me = $game->me;
            $this->opponent = $game->foe;
            return $game;

        }catch(RequestException $e){
            if($e->hasResponse()){
                return $this->extractGame($e->getResponse());
            }
            // var_dump($e);
            return null;
        }
    }


    public function performActionWithCoolDown($gameToken, $playerKey, $actionName, $delay){
        $coolDown = 0;
        foreach($this->me->character->actions as $action){
            if($action->name === $actionName){
                $coolDown = $action->coolDown;
                break;
            }
        }
        $coolDown = $coolDown * $this->speed;
        $game = $this->performAction($gameToken, $playerKey, $actionName, $delay);
        usleep($coolDown * 1000);
        return $game;
    }

    public function generateUniquePlayerKey() {
        return md5(uniqid(rand(),true));
    }


}

?>