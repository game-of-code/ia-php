<?php
require 'vendor/autoload.php';
require_once('sii-cg-helper.php');

/**
 * IA class
 */
class IA{

    private $mode = null;
    private $gameName = null;
    private $gameVersusPlayer = null;
    private $gameToken = null;
    private $character = null;
    private $playerName = "DEFAULT_PLAYER_NAME";
    private $playerKey = null;
    private $speed = 0;
    private $cgHelper = null;

    /**
     * Constructor
     */
    public function __construct(){
        $this->cgHelper = new SIICgHelper();
        $this->playerKey = $this->cgHelper->generateUniquePlayerKey();

    }
    
    /**
     * Main function of the IA
     *
     * @param [Arguments] $cliArgs
     * @return void
     */
    public function main($cliArgs){
        $cliArgsLength = count($cliArgs);
        
        if($cliArgsLength < 2 || (strpos($cliArgs[1], "CREATE") === false && strpos($cliArgs[1], "JOIN") === false)){
            echo("CREATE or JOIN argument is required");
            exit();
        }

        $this->mode = $cliArgs[1];

        if($this->mode === "CREATE"){
            if($cliArgsLength<3){
                echo "Game argument is required";
                exit();
            }
            $this->gameName = $cliArgs[2];

            $this->gameVersusPlayer = ($cliArgsLength >= 6) ? $cliArgs[5] : false;
        }

        if($this->mode === "JOIN") {
            if($cliArgsLength<3){
                echo("Game token is required");
                exit();
            }
            $this->gameToken = $cliArgs[2];
        }

        if($cliArgsLength<4){
            echo "Character argument is required";
            exit();
        }

        $characterArray = array(CHARACTERS::DRUID,CHARACTERS::PALADIN, CHARACTERS::WARRIOR, CHARACTERS::SORCERER);
        if(array_search($cliArgs[3], $characterArray)=== false){
            echo "Character need to be DRUID, PALADIN, WARRIOR, SORCERER";
            exit();
        }

        $this->character = $cliArgs[3];
        $this->playerName = $cliArgsLength >=5 ? $cliArgs[4] : $this->playerName;

        if($this->mode === "CREATE"){
            $this->createGame($this->gameName, $this->gameVersusPlayer,$this->playerKey, $this->character, $this->playerName);
        } else {
            $this->joinGame($this->gameToken, $this->playerKey,$this->character, $this->playerName);
        }

    }

    /**
     * create Game
     *
     * @param [string] $gameName
     * @param [boolean] $gameVersusPlayer
     * @param [string] $playerKey
     * @param [CHARACTER] $character
     * @param [string] $playerName
     * @return void
     */
    public function createGame($gameName, $gameVersusPlayer, $playerKey, $character, $playerName){
        echo "create game ".$gameName." ".$gameVersusPlayer." ".$playerKey." ".$character." ".$playerName;
        $game = $this->cgHelper->createGame($gameName, "false", $gameVersusPlayer);
        if($game && !property_exists($game,"error")){
            $this->gameToken = $game->token;
            $this->speed = $game->speed;
            $this->joinGame($this->gameToken, $playerKey, $character, $playerName);
        }
    }

    /**
     * Join game function
     *
     * @param [string] $gameToken
     * @param [string] $playerKey
     * @param [CHARACTER] $character
     * @param [string] $playerName
     * @return void
     */
    public function joinGame($gameToken, $playerKey, $character, $playerName){
        echo("join game : ".$gameToken."\n");
        $game = $this->cgHelper->joinGameWithCountDown($gameToken, $playerKey, $playerName, $character);
        if($game && !property_exists($game,"error")){
            $this->startPlaying();
        }
    }

    /**
     * Start Playing function
     * executed at startup of the game
     *
     * @return void
     */
    public function startPlaying(){
        echo "start playing"; 
        /*
                MAKE YOUR IA HERE
        */
        $this->makeAction();
    }

    /**
     * Make action function
     * executed when player attack
     * 
     * @return void
     */
    public function makeAction(){
        /*
                MAKE YOUR IA HERE

                exemple this code use the HIT attack only and catch http error from server
        */
        echo "action : ".ACTIONS::HIT." ";
        $game = $this->cgHelper->performActionWithCoolDown($this->gameToken, $this->playerKey, ACTIONS::HIT,0);
        if($game){ 
        
            if(!property_exists($game,"error")){
                echo "YOU : ".$game->me->healthPoints."  -  HIM : ".$game->foe->healthPoints."\n";
                $this->makeAction();
            }else{
                if($game->error->statusCode){
                    switch($game->error->statusCode){
                        case 403:
                            echo "Gama not ready \n";
                            usleep(5000);
                            $this->makeAction();
                        break;
                        case 423:
                            echo "too fast \n";
                            usleep(300000);
                            $this->makeAction();
                        break;
                        case 410:
                            echo "game ended \n";
                            $game = $this->cgHelper->getGame($this->gameToken, $this->playerKey);
                            if($game){
                                echo ($game->me->healthPoints === 0 ? "You Lose" : "You Win")."\n";
                            }
                        break;
                        default:
                            echo "error in request ".$game->error->statusCode;
                    }
                }
            }
        }else{
            echo "no game returned...";
        }
        
    }
}
$ia = new IA();
$ia->main($argv);
?>