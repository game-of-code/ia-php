<?php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;


define("BASE_API_URL", "https://coding-game.swat-sii.fr/api");
// define("BASE_API_URL", "http://192.168.0.2/api");

/**
 * Class CHARACTERS static clas to retrieve Characters type
 */
class CHARACTERS{
    const WARRIOR="WARRIOR";
    const PALADIN="PALADIN";
    const DRUID="DRUID";
    const SORCERER="SORCERER";
    const ELF="ELF";
    const TROLL="TROLL";
}

/**
 * Class ACTIONS static clas to retrieve action type
 */
class ACTIONS{
    const HIT = "HIT";
    const THURST = "THURST";
    const HEAL = "HEAL";
    const SHIELD = "SHIELD";
}


/**
 * Class GAME_STATUS static clas to retrieve status type
 */
class GAME_STATUS{
    const WAITING = "WAITING";
    const PLAYING = "PLAYING";
    const FINISHED = "FINISHED";
}

/**
 * Helper for coding game
 */
class SIICgHelper{
    private $me=null;
    private $opponent=null;
    private $speed=0;
    private $gameToken=null;

    private $client;


    /**
     * constructor
     */
    public function __construct(){
        $this->client = new Client(['base_uri' => BASE_API_URL, 'verify' => false ]);
    }

    /**
     * extract Game function. called to retur game from an API call
     *
     * @param [Response] $res
     * @return Game || Error
     */
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

    /**
     * resolveGameStarted function called to check if game is ready to start
     *
     * @param [Game] $game
     * @return boolean
     */
    private function resolveGameStarted($game){
        $this->gameToken = $game->token;
        $this->speed = $game->speed;
        if($game->status === GAME_STATUS::PLAYING){
            $this->me = $game->me;
            $this->opponent = $game->foe;
            $start = $game->countDown;
            $start = $start * 1000;
            echo "waiting for start".$start."\n";
            usleep($start);
            return true;
        }else{
            return false;
        }
    }


    /**
     * createGame function called to create a Game
     *
     * @param [string] $name
     * @param [boolean] $speedy
     * @param [boolean] $versus
     * @return void
     */
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

    /**
     * Join game function called to join a game
     *
     * @param [string] $gameToken
     * @param [string] $playerKey
     * @param [string] $playerName
     * @param [CHARACTER] $character
     * @return Game
     */
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

    /**
     * joinGameWithCountDown function with countDown to wait the start up of the game
     *
     * @param [type] $gameToken
     * @param [type] $playerKey
     * @param [type] $playerName
     * @param [type] $character
     * @return Game
     */
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

    /**
     * getGame function called to retrieve a game
     *
     * @param [string] $gameToken
     * @param [string] $playerKey
     * @return Game
     */
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

    /**
     * checkGameReady function to wait arrival of a second participant
     *
     * @param [string] $gameToken
     * @param [string] $playerKey
     * @return Game
     */
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

    /**
     * performAction function called to execut an action
     *
     * @param [string] $gameToken
     * @param [string] $playerKey
     * @param [ACTION] $actionName
     * @param [int] $delay
     * @return Game
     */
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


    /**
     * performActionWithCoolDown function called to execute action en return when cooldown is over
     *
     * @param [string] $gameToken
     * @param [string] $playerKey
     * @param [ACTION] $actionName
     * @param [int] $delay
     * @return Game
     */
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

    /**
     * generateUniquePlayerKey function generate unique id for player key
     *
     * @return string
     */
    public function generateUniquePlayerKey() {
        return md5(uniqid(rand(),true));
    }


}

?>
