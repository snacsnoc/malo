<?php

//Libs
//Switch to an autoloader
require_once 'functions.inc.php';
require_once 'forecast.io-php-api/lib/forecast.io.php';
require 'banlist.inc.php';
require_once './google-api-php-client/src/Google/autoload.php';
#require_once './reddit-api-client/Reddit.php';


$socket = fsockopen($config['server'], $config['port']);

fputs($socket, "USER " . $config['nick'] . " moreau doctor :Something nice\n");

fputs($socket, "NICK " . $config['nick'] . "\n");

if(!empty($config['password'])){
fputs($socket, "PRIVMSG NickServ : identify " . $config['password'] . "\n");
}

//Some servers dont let you join a channel instantly
$logincount = 0;
while ($logincount < 10) {
     $logincount++;
     $data = fgets($socket, 128);
     echo nl2br($data);

// Separate all data
     $ex = explode(' ', $data);

// Send PONG back to the server
     if ($ex[0] == "PING") {
         fputs($socket, "PONG " . $ex[1] . "\r\n");
     }
     flush();
}
echo 'Starting bot...';
sleep(2);

fputs($socket, "JOIN " . $config['chan'] . "\n");



while (!feof($socket)){
     while ($data = fgets($socket)) {
         echo nl2br($data);
         flush();

         $ex = explode(' ', $data);
         $rawcmd = explode(':', $ex[3]);

         //We set the channel config to the incoming channel
         //So if a message is sent on #channel1, it is replied on #channel1 even if the bot is in multiple channels
         $config['chan'] = $ex[2];
         $nicka = explode('@', $ex[0]);
         $nickb = explode('!', $nicka[0]);
         $nickc = explode(':', $nickb[0]);

        if ($ex[0] == "PING") {
             fputs($socket, "PONG " . $ex[1] . "\r\n");
         }
         
       
         
         $args = NULL;
         for ($i = 4; $i < count($ex); $i++) {
             $args .= $ex[$i] . ' ';
         }

         //Include all the commands
         //This allows us to remove/add/fix commands on the fly without restarting the bot
         require 'commands.inc.php';
     }
}
?> 