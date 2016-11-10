<?php

//Goddamnit this code is terrible
//....but it works

$redis = new Redis();


//Check if we can connect to redis
// port 6379 by default
if (false == $redis->connect($config['redis_server'])) {
    die('Redis server down. Check configuration!');
}

$client = new Google_Client();
$client->setApplicationName("Client_Library_Examples");

$client->setDeveloperKey($config['google_services_apikey']);


//Version 
$version = "malo IRC bot version 1.915 by snacsnoc <easton@geekness.eu>";

//Check if the user is in the banlist
if (false == in_array($nickc[1], $ban_list)) {
    
    /*
    Check if a user sent another a youtube link or in channel, eg:
    myfavouriteircfriend: https://www.youtube.com/watch?v=JL6RQdDh0mk
     OR
    https://www.youtube.com/watch?v=JL6RQdDh0mk
    */

    if(!isset($ex[4])){
        $search_key = trim($ex[3]);

    }else{
        $search_key = trim($ex[4]);
    }

    if (true == preg_match_all("%(?:.*)?(?:s)?(?:.*)(?s)(?:http(?:s)://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?(.{11})?%", $search_key, $m)) {

        /*
        This should not work:
        wwww.youtube.com/watch?v=vGWppcrAo
        
        Should work:
        http://youtu.be/Yf8IzxaSr1U
        http://www.youtube.com/watch?v=uQX4GuSXYLM
        */

        if (!empty($m[0])) {
            if (preg_match("#(\?|&)#si", $m[1][1])) {
                parse_str($m['1'], $vars);
                
                if (!empty($vars['v'])) {
                    $youtube_video_key = $vars['v'];
                }
            } else {
                //Maybe test against preg_match("#^([A-Za-z0-9\-\_]+?)#si", $m['1'])...
                $youtube_video_key = $m[1][0];
            }

            //Validate that video key is 11 characters
            if (strlen($youtube_video_key) == 11) {

                $youtube = new Google_Service_YouTube($client);

                //Get view count and like count
                $videosResponse = $youtube->videos->listVideos('snippet, statistics', array(
                'id' => $youtube_video_key,
                ));


                

                $youtube_video_title = $videosResponse['items'][0]['snippet']['title'];

                $youtube_video_viewcount = $videosResponse['items'][0]['statistics']['viewCount'];

                //Get more details about the video
                $contentResponse = $youtube->videos->listVideos('snippet, contentDetails', array(
                'id' => $youtube_video_key,
                ));
                

                $youtube_video_length = $contentResponse['items'][0]['contentDetails']['duration'];

                //A response returns with "PT99M99S" so we remove the "PT" and get just the minute and second length
                $youtube_video_length = substr($youtube_video_length, 2);

                fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: [" . $youtube_video_title . "] [" . $youtube_video_length . "] [" . $youtube_video_viewcount . " views] \r\n");
            }
        }
    }

    //Parse github.com URLs
     if (true == preg_match_all("%(?:http(?:s):\/\/)?(?:www.)?github.com\/(.*)\/(.*)%", $ex[3], $match)) {

        if($match){
            $repo_to_get = trim($match[1][0]."/".$match[2][0]);
            $rss_feed    = "https://github.com/$repo_to_get/commits/master.atom";
            $feed        = simplexml_load_file($rss_feed);
            if ($feed) {
                
                $link  = $feed->entry[0]->link->attributes();
                $short_url  = make_bitly_url($link['href'], $config['bitly_username'], $config['bitly_apikey'], 'xml');
               
                $title = trim($feed->entry[0]->title);
                
                #$latest_commit = "$title [commit: $short_url]";
                fputs($socket, "PRIVMSG " . $config['chan'] . " :Latest commit: $title [commit: $short_url]\r\n");
            } else {
                fputs($socket, "PRIVMSG " . $config['chan'] . " :Unable to get latest activity :() \r\n");
            }
        }else{
            echo "No match on github URL\r\n";
        }
      }  

    if ($ex[1] == "KICK") {
        fputs($socket, "JOIN " . $config['chan'] . "\r\n");
    }
    if ($ex[1] == "Killed") {
        fputs($socket, "JOIN " . $config['chan'] . "\r\n");
    }
    
    //CTCP VERSION reply
    if (trim($ex[3]) == ':' . chr(1) . "VERSION" . chr(1)) {
        fputs($socket, "PRIVMSG " . $nickc[1] . " : $version, running on " . PHP_OS . " with PHP version " . phpversion() . ". https://github.com/snacsnoc/malo \r\n");
    }
    
    
    /* uncomment for auto voice (must have half-ops at a minimum)
    if ($ex[1] == "JOIN") {
    echo $nickc[1];
    echo fputs($socket, "MODE " . $config['chan2'] . " +v " . "$nickc[1]\r\n");
    }
    */
    
    
    //Standard period and exclaimation mark prefixed commands
    switch (strtolower(rtrim($rawcmd[1]))) {
        
        case ".say":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$args \r\n");
            break;
        
        case "!ping":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :pong!\r\n");
            break;
        
        //searches youtube for keyword
        case ".yt":
            
            //See https://developers.google.com/youtube/v3/docs/videos
            $youtube = new Google_Service_YouTube($client);

            $searchResponse = $youtube->search->listSearch('id,snippet', array(
                'q' => $args,
                'maxResults' => 1,
                'type' => 'video',
                ));

            if($searchResponse['items'][0]['id']['videoId']){

                $youtube_video_id = $searchResponse['items'][0]['id']['videoId']; 
                $youtube_title = $searchResponse['items'][0]['snippet']['title'];

                //$youtube_published_at = $searchResponse['items'][0]['snippet']['publishedAt'];

                //Get view count and like count
                $videosResponse = $youtube->videos->listVideos('snippet, statistics', array(
                'id' => $youtube_video_id,
                ));

                $youtube_viewcount = $videosResponse['items'][0]['statistics']['viewCount'];

                //$youtube_likecount = $videosResponse['items'][0]['statistics']['likeCount'];
                
                //Get more details about the video
                $contentResponse = $youtube->videos->listVideos('snippet, contentDetails', array(
                'id' => $youtube_video_id,
                ));
                
                $youtube_length = $contentResponse['items'][0]['contentDetails']['duration'];
                //A response returns with "PT99M99S" so we remove the "PT" and get just the minute and second length
                $youtube_length = substr($youtube_length, 2);
                preg_match('/(\d+)M(\d+)S/', $youtube_length, $youtube_length);

                //standard definition or high definition
                $youtube_definition = $contentResponse['items'][0]['contentDetails']['definition'];

                //2d or 3d
                $youtube_dimension = $contentResponse['items'][0]['contentDetails']['dimension'];

                fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: [Title: $youtube_title] [http://youtube.com/watch?v=$youtube_video_id] [Views: $youtube_viewcount] [Length: ".$youtube_length[1]." min ".$youtube_length[2]." sec] [$youtube_definition] [$youtube_dimension]\r\n");
            
            }else{
                fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: No results :( \r\n");
            }
            break;
        
        //random noun
        case ".noun":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . lineread("nouns_a.txt", rand(1, count(file("nouns_a.txt")))) . " \r\n");
            break;
        
        //random verb
        case ".verb":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . lineread("verbs.txt", rand(1, count(file("verbs.txt")))) . " \r\n");
            break;
        
        //random adjective
        case ".adjective":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . lineread("adjectives.txt", rand(1, count(file("adjectives.txt")))) . " \r\n");
            break;
        
        //random adverb
        case ".adverb":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . lineread("adverbs.txt", rand(1, count(file("adverbs.txt")))) . " \r\n");
            break;
        
        //adjective noun verb noun    
        case ".anvn":
            
            if (null == $ex[4]) {
                fputs($socket, "PRIVMSG " . $config['chan'] . " :No user specified! Use .anvn <nick> \r\n");
            } else {
                $user = trim($ex[4]);
                
                $adverb = lineread("adverbs.txt", rand(1, count(file("adverbs.txt"))));
                
                $noun = lineread("nouns_a.txt", rand(1, count(file("nouns_a.txt"))));
                
                $noun_second = lineread("nouns_a.txt", rand(1, count(file("nouns_a.txt"))));
                
                $verb = lineread("verbs.txt", rand(1, count(file("verbs.txt"))));
                
                $adjective = rtrim(lineread("adjectives.txt", rand(1, count(file("adjectives.txt")))));
                
                fputs($socket, "PRIVMSG " . $config['chan'] . " :$user is a $adjective $noun to $verb a $noun_second \r\n");
            }
            break;
        
        case ".poke":
            $args = substr($args, 0, -3);
            fputs($socket, "PRIVMSG " . $config['chan'] . ' :' . chr(1) . 'ACTION pokes ' . "$args" . chr(1) . "\r\n");
            break;
        
        case ".mem":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :Allocated: " . convert(memory_get_usage(true)) . "\r\n");
            break;
        
        case ".memp":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :Allocated peak: " . convert(memory_get_peak_usage(true)) . "\r\n");
            break;
        
        //Check if a port is open    
        case ".checkport":
            $args = explode(":", $args);
            
            if (is_int((int) $args[1])) {
                if (true == checkport($args[0], $args[1])) {
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :Result: port is open!\r\n");
                } else {
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :Result: port is closed!\r\n");
                }
            } else {
                fputs($socket, "PRIVMSG " . $config['chan'] . " :Not valid port number. Use .checkport <hostname/ip>:<port>\r\n");
            }
            break;
        
        //Looks up acronyms
        case ".acr":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . Acronyms($args) . "\r\n");
            break;
        
        //memo
        case ".memo":
            $args = trim($args);
            //TODO: this needs to be fixed 
            if ($args != NULL || !empty($args)) {
                switch ($args) {
                    
                    //this uploads memo.txt to pastebin.com
                    case 'showall':
                        
                        $filename = "memo.txt";
                        
                        $file_contents = file_get_contents($filename);
                        
                        //JSON POST to pate.gelat.in
                        //See http://paste.gelat.in/api
                        
                        $data        = array(
                            "name" => 'irc.devhax.com #fallout memo',
                            "content" => $file_contents,
                            "visible" => false
                        );
                        $data_string = json_encode($data);
                        
                        $ch = curl_init('https://pasteros.io/api/v1/create');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_NOBODY, 0);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($data_string)
                        ));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Expect:'
                        ));
                        
                        $curl = curl_exec($ch);
                        
                        curl_close($ch);
                        
                        $result = json_decode($curl, true);
                        
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: https://pasteros.io/" . $result['id'] . "\r\n");
                        
                        break;
                    
                    //Get a random line
                    case 'r':
                        $random = rand(1, count(file("memo.txt")));
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :[line $random]" . lineread("memo.txt", $random) . "\r\n");
                        
                        
                        break;
                    
                    //This works so don't fuck with it
                    //Write a new memo
                    case is_numeric($args) == FALSE && $args != "r" && $args != "showall":
                        
                        $fh = fopen("memo.txt", 'a+') or die("can't open memo.txt");
                        
                        $args = $args . "\n";
                        fwrite($fh, $args);
                        fclose($fh);
                        
                        $lines = count(file("memo.txt")) - 1;
                        
                        
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :saved on line " . $lines . "\r\n");
                        
                        break;
                    
                    //Get a specific line
                    case is_numeric($args):
                        if (!lineread("memo.txt", $args) == NULL) {
                            
                            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . lineread("memo.txt", $args) . "\r\n");
                        }
                        
                        break;
                }
            }
            
            break;
        
        //colour ref: http://forum.egghelp.org/viewtopic.php?t=3867
        case ".svaj":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . strtoupper($nickc[1]) . chr(3) . chr(53) . chr(44) . chr(49) . chr(51) . " PARTY TIME! WOOT WOOT!\r\n");
            break;
        
        //Get the latest recipe
        case '.sonatia':
            $food_xml = simplexml_load_file('http://www.taste.com.au/feeds/latest+recipes.xml');
            if ($food_xml) {
                $title       = $food_xml->channel->item->title;
                $description = $food_xml->channel->item->description;
                
                //Get rid of nasty HTML
                $description = preg_replace("/<img[^>]+\>/i", "", $description);
                $description = strip_tags($description);
                //Shorten description
                $description = substr($description, 0, strpos($description, ' ', 50));
                //Shorten URL with bit.ly
                $url         = make_bitly_url($food_xml->channel->item->link, $config['bitly_username'], $config['bitly_apikey'], 'xml');
                fputs($socket, "PRIVMSG " . $config['chan'] . " :$title [$description...] $url\r\n");
            }
            
            break;
        
        case ".malo":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :http://i.imgur.com/Bb7WO.jpg https://github.com/snacsnoc/malo\r\n");
            break;
        
        
        case '.back':
            //Reverse text
            $reverse_text = strrev(trim($args));
            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $reverse_text . "\r\n");
            break;
        
        case "!rimshot":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :*ba-dum-tsh*\r\n");
            break;
        
        case ".version":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :version $version running on " . PHP_OS . " with PHP version " . phpversion() . "\r\n");
            break;
        
        //md5
        case ".md5":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: MD5 " . md5($args) . "\r\n");
            break;
        
        case ".sha1":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: SHA1 " . sha1($args) . "\r\n");
            break;
        //decimal to binary
        case ".d2b":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: decimal to binary: " . decbin($args) . "\r\n");
            break;
        //binary to decimal
        case ".b2d":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: binary to decimal: " . bindec($args) . "\r\n");
            break;
        //binary to ascii
        case ".b2a":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: binary to ascii: " . bin2asc($args) . "\r\n");
            break;
        //ascii to binary
        case ".a2b":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: ascii to binary: " . asc2bin($args) . "\r\n");
            break;
        //octal to decimal
        case ".o2d":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: octal to decimal: " . octdec($args) . "\r\n");
            break;
        //decimal to octal
        case ".d2o":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: decimal to octal: " . decoct($args) . "\r\n");
            break;
        //hex to decimal
        case ".h2d":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: hex to decimal: " . hexdec($args) . "\r\n");
            break;
        //decimal to octal
        case ".d2h":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: decimal to hex: " . dechex($args) . "\r\n");
            break;
        //search PHP.net for a function
        case ".php":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . phpHelp($args) . "\r\n");
            break;
        
        //show current top movies
        case ".topmovies":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . topmovies() . "\r\n");
            break;
        case ".topsongs":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . topsongs() . "\r\n");
            break;
        
        //Celsius to fahrenheit
        case ".c2f":
            $fahrenheit = round((intval(trim($args)) * 1.8) + 32, 2);
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: celsius to fahrenheit : " . $fahrenheit . "F \r\n");
            break;
        
        //Fahrenheit to celsius (the superior system)
        case ".f2c":
            $celsius = round(intval((trim($args)) - 32) / 1.8, 2);
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: fahrenheit to celsius: " . $celsius . "C \r\n");
            break;
        
        //Converts Bitcoin to USD
        case ".btc2usd":
            $mtgox_json = file_get_contents('https://api.bitcoinaverage.com/ticker/USD/');
            $btc        = json_decode($mtgox_json, true);
            
            $last = intval($btc['last']);
            $usd  = trim($args) * $last;
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $usd . " USD\r\n");
            
            break;
        
        //Converts USD to Bitcoin
        case ".usd2btc":
            $mtgox_json = file_get_contents('https://api.bitcoinaverage.com/ticker/USD/');
            $btc        = json_decode($mtgox_json, true);
            
            $last = intval($btc['last']);
            $btc  = trim($args) / $last;
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $btc . " BTC\r\n");
            
            break;
        
        //Bitcoin related functions
        case ".btc":
            
            //Query mtgox 
            $mtgox_json = file_get_contents('https://api.bitcoinaverage.com/ticker/USD/');
            $btc        = json_decode($mtgox_json, true);
            
            switch (trim($args)) {
                
                case 'bid':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: bid: " . $btc['bid'] . "\r\n");
                    break;
                case 'last':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: last: " . $btc['last'] . "\r\n");
                    break;
                
                case 'ask':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: ask: " . $btc['ask'] . "\r\n");
                    break;
                
                case 'avg':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: avg: " . $btc['24h_avg'] . "\r\n");
                    break;
                
                case null:
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]:  use bid, last, ask or avg. Data via bitcoinaverage.com\r\n");
                    break;
            }
            
            break;
        
        
        case ".github":
            $github_command = explode(" ", $args);
            
            switch (trim($github_command[0])) {
                
                case "getuser":
                    $user_to_get = trim($ex[5]);
                    $rss_feed    = "https://github.com/$user_to_get.atom";
                    $feed        = simplexml_load_file($rss_feed);
                    
                    if ($feed) {
                        
                        $link  = $feed->entry[0]->link->attributes();
                        $link  = $link['href'];
                        $title = trim($feed->entry[0]->title);
                        $time  = $feed->entry[0]->published;
                        
                        
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :Latest activity for $user_to_get: $title [URL: $link] @ $time\r\n");
                    } else {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :Unable to get latest activity :( \r\n");
                    }
                    
                    break;
                
                case "getrepo":
                    $repo_to_get = trim($ex[5]);
                    $rss_feed    = "https://github.com/$repo_to_get/commits/master.atom";
                    $feed        = simplexml_load_file($rss_feed);
                    if ($feed) {
                        
                        $link  = $feed->entry[0]->link->attributes();
                        $link  = $link['href'];
                        $title = trim($feed->entry[0]->title);
                        
                        $latest_commit = "$title [commit: $link]";
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :Latest commit: $title [commit: $link]\r\n");
                    } else {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :Unable to get latest activity :(( \r\n");
                    }
                    
                    break;
                
                default:
                    
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :Use .github getuser/getrepo <username>/<username/reponame>\r\n");
                    
                    break;
                    
            }
            break;
        
        
        //Gets weather conditions by postal code/zip
        case ".w":
            $weather_command = explode(" ", $args);
            
            $forecast = new ForecastIO($config['forecast.io_apikey'], 'ca', 'en');

            switch (trim($weather_command[0])) {
                
                case 'set':
                    //Get user's location
                    $address     = rawurlencode($ex[5] . $ex[6]);
                    $geocode     = file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $address . '&sensor=false');
                    $output      = json_decode($geocode);

                    if($output->status == "ZERO_RESULTS"){
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Error: zero results for that location\r\n");
                        break;
                    }

                    $geo['lat']  = $output->results[0]->geometry->location->lat;
                    $geo['long'] = $output->results[0]->geometry->location->lng;
                    
                    $location = array(
                        'lat' => $geo['lat'],
                        'long' => $geo['long']
                    );
                    
                    if (true == $location) {
                        //store lat and long in redis
                        if(true == $redis->set($nickc[1], $location['lat'] . ',' . $location['long'])){
                            
                            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Location set. You may now use .w\r\n");
                        }else{
                            //Possible OOM error
                            fputs($socket, "PRIVMSG " . $config['chan'] . " : Redis error!\r\n");
                        }
                    } elseif (false == $location) {
                        
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :SUPER MEGA ERROR: could not add location for " . $nickc[1] . "\r\n");
                    }
                    
                    break;
                
                
                default:
                    //If user has a lat and long in redis, get conditions
                    if (true == $redis->get($nickc[1])) {
                        $user_location = $redis->get($nickc[1]);
                        $geo           = explode(',', $user_location);

                        //Display units
                        //If short_name is US then we use F, otherwise C
                        switch ($output->results[0]->address_components[3]->short_name){
                            case "US":
                                #$units = "°F";
                                $units = "°C"; 
                                break;

                            default:
                                $units = "°C";  
                                break;  


                        }

                        $condition = $forecast->getCurrentConditions($geo[0], $geo[1]);
                        
                        $forecast_conditions = $forecast->getForecastWeek($geo[0], $geo[1]);
                        
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Currently " . $condition->getTemperature() . "$units and " . $condition->getSummary() . ". Tomorrow low of " . $forecast_conditions[1]->getMinTemperature() . "$units, high of " . $forecast_conditions[1]->getMaxTemperature() . "$units  and " . $forecast_conditions[1]->getSummary() . " \r\n");
                    } else {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :You don't exist. Please set your location by using .w set <city, state/postal code/zipcode> then use .w, or just use .w get <location>\r\n");
                    }
                    
                    break;
                
                case 'get':
                    $address             = rawurlencode($ex[5] . $ex[6]);
                    $geocode             = file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $address . '&sensor=false');
                    $output              = json_decode($geocode);


                    if($output->status == "ZERO_RESULTS"){
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Error: zero results for that location\r\n");
                        break;
                    }
                    $coordinates['lat']  = $output->results[0]->geometry->location->lat;
                    $coordinates['long'] = $output->results[0]->geometry->location->lng;
                    
                    
                    //Display units
                    //If short_name is US then we use F, otherwise C
                    switch ($output->results[0]->address_components[3]->short_name){
                        case "US":
                            #$units = "°F";
                        $units = "°C"; 
                            break;

                        default:
                            $units = "°C";  
                            break;  


                    }
                    $condition           = $forecast->getCurrentConditions($coordinates['lat'], $coordinates['long']);
                    $forecast_conditions = $forecast->getForecastWeek($coordinates['lat'], $coordinates['long']);

                    if (null !== $condition) {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Currently " . $condition->getTemperature() . "$units and " . $condition->getSummary() . ". Tomorrow low of " . $forecast_conditions[1]->getMinTemperature() . "$units, high of " . $forecast_conditions[1]->getMaxTemperature() . "$units and " . $forecast_conditions[1]->getSummary() . " \r\n");
                    } else {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": error: could not get weather\r\n");
                    }
                    
                    break;
            }
            
            break;
        
        ###############
        
        case ".comp":
            $slink[] = "you are sexy!";
            $slink[] = "damn, you're fine!";
            $slink[] = "you smell, shower!";
            $slink[] = "you have the figure of an angel";
            $slink[] = "everyone in your life loves you";
            $slink[] = "why are you so handsome?";
            $slink[] = "lookin good!";
            $slink[] = "Yes, I've got a lot going on, but I'm never too busy for you.";
            $slink[] = "When I grow up, I want to be you.";
            $slink[] = "You're smarter than Google and Mary Poppins combined";
            $slink[] = "Your confidence is so impressive, you could walk into Mordor and everybody would be like 'Yep, that makes sense.'";
            $slink[] = "If you were running for the next Linux maintainer, I would vote for you. And clear your search history. Because programming is a dirty business, and they will dig that shit up. But don't worry, I got you";
            $slink[] = "You inspire me. And strangers, probably. Also, friends and stalkers. You are the inspiration to many.";
            $slink[] = "Your eyes color my cheeks in blush, for you are my Maybeline";
            
            $random = rand(0, count($slink) - 1);
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . "$slink[$random]\r\n");
            break;
        
        case "!8ball":
            $link[] = "never going to happen.";
            $link[] = "most positively.";
            $link[] = "it is decidely so.";
            $link[] = "yes.";
            $link[] = "no.";
            $link[] = "try again later.";
            $link[] = "sure.";
            $link[] = "perhaps.";
            $link[] = "maybe later.";
            $link[] = "never.";
            
            $random = rand(0, count($link) - 1);
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . "$link[$random]\r\n");
            break;

        case "!rms":
            $link[] = "Thanks to Mr. Gates, we now know that an open Internet with protocols anyone can implement is communism; it was set up by that famous communist agent, the US Department of Defense.";
            $link[] = "People said I should accept the world. Bullshit! I don't accept the world.";
            $link[] = "Four species of penguins that breed in Antartica are endangered by global warming.";
            $link[] = "C++ is a badly designed and ugly language. It would be a shame to use it in Emacs.";
            $link[] = "If the users don't control the program, the program controls the users. With proprietary software, there is always some entity, the owner of the program, that controls the program—and through it, exercises power over its users. A nonfree program is a yoke, an instrument of unjust power.";
            $link[] = "Any time I connect to a website other than Wikipedia, it's through Tor.";
            $link[] = "People sometimes ask me if it is a sin in the Church of Emacs to use vi. Using a free version of vi is not a sin; it is a penance. So happy hacking. ";
            $link[] = "GNU is not in the public domain. Everyone will be permitted to modify and redistribute GNU, but no distributor will be allowed to restrict its further redistribution. That is to say, proprietary modifications will not be allowed. I want to make sure that all versions of GNU remain free.";
            $link[] = "Writing non-free software is not an ethically legitimate activity, so if people who do this run into trouble, that's good! All businesses based on non-free software ought to fail, and the sooner the better.";
            $link[] = "Very ironic things have happened, but nothing to match this — giving the Linus Torvalds Award to the Free Software Foundation is sort of like giving the Han Solo Award to the Rebel Fleet.";
            $link[] = "Today many people are switching to free software for purely practical reasons. That is good, as far as it goes, but that isn't all we need to do! Attracting users to free software is not the whole job, just the first step.";
            $link[] = "I could have made money this way, and perhaps amused myself writing code. But I knew that at the end of my career, I would look back on years of building walls to divide people, and feel I had spent my life making the world a worse place.";
            $random = rand(0, count($link) - 1);
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . "$link[$random]\r\n");
            break;        
        
        //There needs to a better way to list commands         
        case ".help":
            fputs($socket, "PRIVMSG " . $nickc[1] . " :All commands are here: https://pasteros.io/5822582913ee8/raw \r\n");
            break;
  
        case "!lenny":
            fputs($socket, "PRIVMSG " . $config['chan']. " :( ͡° ͜ʖ ͡°) \r\n");
            break;       

        ##BOT/SYS COMMANDS##
        case ".uptime":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "PRIVMSG " . $config['chan'] . " :" . exec('uptime') . "\r\n");
            }
            break;
        
        case ".free":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "PRIVMSG " . $config['chan'] . " :" . exec('free -m | egrep Mem:') . "\r\n");
            }
            break;
        
        case ".register":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "PRIVMSG NickServ : register " . $config['password'] . " " . $config['email'] . "\r\n");
            }
            break;
        
        case ".login":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "PRIVMSG NickServ : identify " . $config['password'] . "\r\n");
            }
            break;
        
        case ".join":
            //Somehow multi-channel works...
            if ($ex[0] == $config['admin']) {
                $channel = trim($args);
                fputs($socket, "JOIN $channel\r\n");
            }
            break;
        
        
        case ".kick":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "KICK " . $config['chan'] . " $args\r\n");
            }
            break;
        
        case ".linux":
            $rss_feed = 'https://github.com/torvalds/linux/commits/master.atom';
            $feed     = simplexml_load_file($rss_feed);
            if ($feed) {
                
                $link  = $feed->entry[0]->link->attributes();
                $title = trim($feed->entry[0]->title);
                
                $short_url     = make_bitly_url($link['href'], $config['bitly_username'], $config['bitly_apikey'], 'xml');
                $latest_commit = "$title [commit: $short_url]";
            } else {
                return false;
            }
            
            fputs($socket, "PRIVMSG " . $config['chan'] . " :https://github.com/torvalds/linux Latest commit: $latest_commit\r\n");
            
            break;
        
        case ".snacklinux":
            $rss_feed = 'https://github.com/snacsnoc/snacklinux/commits/master.atom';
            $feed     = simplexml_load_file($rss_feed);
            if ($feed) {
                
                $link  = $feed->entry[0]->link->attributes();
                $title = trim($feed->entry[0]->title);
                
                $short_url     = make_bitly_url($link['href'], $config['bitly_username'], $config['bitly_apikey'], 'xml');
                $latest_commit = "$title [commit: $short_url]";
            } else {
                return false;
            }
            
            fputs($socket, "PRIVMSG " . $config['chan'] . " :https://github.com/snacsnoc/snacklinux Latest commit: $latest_commit\r\n");
            break;
             
        
        //This doesn't work
        case ".restart":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "QUIT restarting\n");
                socket_close($socket);
            }
            break;
        
        case ".exit":
            if ($ex[0] == $config['admin']) {
                fputs($socket, "PRIVMSG " . $config['chan'] . " :ko\n");
                fputs($socket, "QUIT bok\n");
                fclose($socket);
                die('Exited!');
            }
            break;
    }
}
