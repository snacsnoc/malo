<?php

// Prevent PHP from stopping the script after 30 seconds
set_time_limit(0);
ini_set( 'display_errors', 'on' );


//config what else?
$config = array(
     "server" => "#",
     "chan" => "#",
     "port" => 6667,
     'nick' => '#',
     'admin' => ':#',
     'password' => "#",
     'email' => "#",
     'reddit_username' => '#',
     'reddit_password' => '#',
     'bitly_username' => "#",
     'bitly_apikey' => "#",
     'redis_server' => '#',
     'forecast.io_apikey' => "#",
     'google_services_apikey' => "#"
);
date_default_timezone_set('America/Edmonton');

require 'bot.php';

