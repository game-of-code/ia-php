<?php
require 'vendor/autoload.php';
use GuzzleHttp\Client;



class Fight{
    private $client;
    private $BASE_URL = 'https://coding-game.swat-sii.fr/api';

    public function __construct(){
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $this->client = new Client(['base_uri' => 'https://coding-game.swat-sii.fr', 'verify' => false ]);
    }

    public function createGame($name, $speedy, $versus){
        return $this->client->request('POST', '/api/fights', [
            'json' => [
                'name' => $name,
                'speedy'=> $speedy,
                'versus'=> $versus
            ]
        ]);
    }

    public function joinGame($gameToken, $playerKey, $character, $characterName){
        return $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey, [
            'json' => [
                'character' => $character,
                'name'=> $characterName
            ]
        ]);
    }

    public function getGame($gameToken, $playerKey){
         return $this->client->request('GET', '/api/fights/'.$gameToken.'/players/'.$playerKey);
    }

    public function performAction($gameToken, $playerKey, $action){
        return $this->client->request('POST', '/api/fights/'.$gameToken.'/players/'.$playerKey.'/actions/'.$action);
    }

    
}
?>