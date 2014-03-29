<?php

// Prevent PHP from stopping the script after 30 seconds
set_time_limit(0);
ini_set( 'display_errors', 'on' );


//config what else?
$config = array(
     "server" => "irc.devhax.com",
     "chan" => "#fallout",
     "port" => 6697,
     'nick' => 'malo',
     'admin' => '#',
     'password' => "#",
     'email' => "#",
     'reddit_username' => '#',
     'reddit_password' => '#',
     'bitly_username' => "#",
     'bitly_apikey' => "#",
     'redis_server' => '#',
     'forecast.io_apikey' => "#"
);
date_default_timezone_set('America/Edmonton');

//Libs
//Switch to an autoloader
require_once 'functions.inc.php';
require_once 'forecast.io-php-api/lib/forecast.io.php';


$socket = fsockopen("ssl://".$config['server'], $config['port']);

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
         fputs($socket, "PONG " . $ex[1] . "\n");
     }
     flush();
}
echo 'Starting malo...';
sleep(2);

fputs($socket, "JOIN " . $config['chan'] . "\n");



while (!feof($socket)){
     while ($data = fgets($socket)) {
         echo nl2br($data);
         flush();

         $ex = explode(' ', $data);
         $rawcmd = explode(':', $ex[3]);

         $config['chan'] = $ex[2];
         $nicka = explode('@', $ex[0]);
         $nickb = explode('!', $nicka[0]);
         $nickc = explode(':', $nickb[0]);

        if ($ex[0] == "PING") {
             fputs($socket, "PONG " . $ex[1] . "\n");
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

