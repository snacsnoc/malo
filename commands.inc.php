<?php

//Goddamnit this code is terrible
//....but it works
require_once './reddit-api-client/Reddit.php';

use \RedditApiClient\Reddit;

require 'banlist.inc.php';

$redis = new Redis();

//Check if we can connect to redis
// port 6379 by default
if(false == $redis->connect($config['redis_server'])){
    die('Redis server down. Check configuration!');
}
//
//Version 
$version = "malo IRC bot version 1.87 by snacsnoc <easton@geekness.eu>";

//Check if the user is in the banlist
if (false == in_array($nickc[1], $ban_list)) {


//We use $ex[3] instead of $rawcmd[3] so it doesn't split into two keys.
    if (true == preg_match_all("%(?:http://)?(?:www.)?(?:youtube.com|youtu.be)/(?:watch\?v=)?(.+)%", trim($ex[3]), $m)) {


        /*
          This should not work:
          wwww.youtube.com/watch?v=vGWppcrAo

          Should work:
          http://youtu.be/Yf8IzxaSr1U
          http://www.youtube.com/watch?v=uQX4GuSXYLM
         */
        if (!empty($m['0'])) {
            if (preg_match("#(\?|&)#si", $m[1][1])) {
                parse_str($m['1'], $vars);

                if (!empty($vars['v'])) {
                    $youtube_video_key = $vars['v'];
                }
            } else {
                //Maybe test against preg_match("#^([A-Za-z0-9\-\_]+?)#si", $m['1'])...
                $youtube_video_key = $m['1'];
            }

            //Validate that video key is 14 characters
            if (strlen($youtube_video_key[0]) <= 14) {

                if (get_url_contents('http://gdata.youtube.com/feeds/api/videos/' . $youtube_video_key[0]) == "Invalid id") {
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :Invalid YouTube video ID \r\n");
                } else {
                    $feedURL = 'http://gdata.youtube.com/feeds/api/videos/' . $youtube_video_key[0];
                    $ulaz = simplexml_load_file($feedURL);
                    $video = parseVideoEntry($ulaz);
                    if ($video == NULL) {
                        throw new exception("video is null, why is video null");
                    }
                    $video->viewCount = base_convert($video->viewCount, 10, 2);
                    $video->rating = base_convert($video->rating, 10, 8);
                    $t = sprintf("%0.2f", $video->length / 60);
                    $time = dechex(sprintf("%0.2f", $video->length / 60));

                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: [" . $video->title . "] [" . $time . " min] [" . $video->rating . " user rating] [" . $video->viewCount . " views] \r\n");
                }
            }
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
        fputs($socket, "PRIVMSG " . $nickc[1] . " : $version, running on " . PHP_OS . " with PHP version " . phpversion() . " \r\n");
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

            //Replace space with a slash
            $args = preg_replace('/[[:space:]]+/', '/', trim($args));

            //Found bug when using # in searches
            $args = preg_replace('/#/', 'pound', $args);

            $feedURL = "http://gdata.youtube.com/feeds/api/videos?q=$args&orderby=relevance&max-results=1";
            $sxml = simplexml_load_file($feedURL);
            // get summary counts from opensearch: namespace
            $counts = $sxml->children('http://a9.com/-/spec/opensearchrss/1.0/');
            if ($counts->totalResults == "0") {
                fputs($socket, "PRIVMSG " . $config['chan'] . " : no results, dupin \r\n");
                break;
            }
            //   $total = $counts->totalResults;
            //    $startOffset = $counts->startIndex;
            //      $endOffset = ($startOffset - 1) + $counts->itemsPerPage;


            foreach ($sxml->entry as $ulaz) {
                //yay, children!
                $media = $ulaz->children('http://search.yahoo.com/mrss/');

                $attrs = $media->group->player->attributes();
                $gledati = $attrs['url'];

                // get <yt:duration> node for video length
                $yt = $media->children('http://gdata.youtube.com/schemas/2007');
                $attrs = $yt->duration->attributes();
                $length = $attrs['seconds'];

                // get <gd:rating> node for video ratings
                $gd = $ulaz->children('http://schemas.google.com/g/2005');
                if ($gd->rating) {
                    $attrs = $gd->rating->attributes();
                    $rating = $attrs['average'];
                } else {
                    $rating = 0;
                }
                // get <yt:stats> node for viewer statistics
                $yt = $ulaz->children('http://gdata.youtube.com/schemas/2007');
                $attrs = $yt->statistics->attributes();


                // $viewCount = decbin($attrs['viewCount']);
                //  $rating = decoct($rating);
                // $time = base_convert(sprintf("%0.2f", $length / 60), 10, 16);
                //  $time = substr_replace($time, ".", 1, 0);
                $viewCount = $attrs['viewCount'];

                $time = sprintf("%0.2f", $length / 60);

                $gledati = explode("&", $gledati);

                fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $gledati[0] . "  [" . $media->group->title . "] [" . $time . " min] [" . $rating . " user rating] [" . $viewCount . " views] \r\n");
            }

            break;


        //Reddit commands
        case ".reddit":

            //Separeate commands my spaces
            $reddit_command = explode(" ", $args);

            if (null == $args) {
                fputs($socket, "PRIVMSG " . $nickc[1] . " :Usage:\r\n");
                fputs($socket, "PRIVMSG " . $nickc[1] . " :get <subreddit>     Get top link in a given subreddit\r\n");
                fputs($socket, "PRIVMSG " . $nickc[1] . " :comment <thread_id> <comment text>      Comment on a thread given the ID\r\n");
                fputs($socket, "PRIVMSG " . $nickc[1] . " :vote <thread_id> <up/down>     Upvote/downvote a post given the ID\r\n");
                fputs($socket, "PRIVMSG " . $nickc[1] . " :info     Get current Reddit bot info\r\n");
                break;
            }
            //Log in
            $reddit = new Reddit($config['reddit_username'], $config['reddit_password']);




            switch (trim($reddit_command[0])) {
                //Get info about logged in user
                case "info":

                    $response = $reddit->sendRequest("GET", "http://www.reddit.com/api/me.json");
                    if (true == $response['data']['has_mail']) {
                        $response['data']['has_mail'] = "yes";
                    } else {
                        $response['data']['has_mail'] = "no";
                    }
                    fputs($socket, "PRIVMSG " . $config['chan'] . " : [Username: " . $response['data']['name'] . "] [Comment karma: " . $response['data']['comment_karma'] . "] [Has mail: " . $response['data']['has_mail'] . "] \r\n");
                    break;

                //Retrieves the top link in a given subreddit
                case "get":
                    try {
                        $subreddit_links = $reddit->getLinksBySubreddit(trim($reddit_command[1]));

                        $topLink = $subreddit_links[0];
                        $reddit_thread_id = $topLink->getId();
                        $upvotes = $topLink->getUpvotes();
                        $downvotes = $topLink->getDownvotes();

                        //Fix some problems with titles of threads
                        $title = trim($topLink->getTitle());

                        if ($topLink->isSelfPost()) {
                            $self_text = $topLink->getSelfText();
                            $self_text = substr($self_text, 0, 50);
                            $self_text = "[$self_text...]";
                        } else {
                            $self_text = '';
                        }

                        //To save some screen space
                        $short_url = make_bitly_url($topLink->getUrl(), $config['bitly_username'], $config['bitly_apikey'], 'xml');


                        fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $title . " " . $short_url . " [$upvotes/$downvotes] [TID:$reddit_thread_id] $self_text\n");

                        //Unset it so it doesn't reappear again
                        unset($self_text);
                    } catch (Exception $e) {

                        fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $e->getMessage() . " \r\n");
                    }
                    break;

                //Posts a comment based on the thread ID
                case "comment":

                    $thread_id = $reddit_command[1];

                    //Separate the comment from the command
                    $text = explode("comment $thread_id", $args);
                    $comment_text = $text[1];

                    //Get Reddit link and post comment on root  
                    $subreddit_post = $reddit->getLink($thread_id);

                    if ($subreddit_post->reply($comment_text)) {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: comment confirmed \r\n");
                    } else {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: error: cannot comment :( \r\n");
                    }

                    break;
                //Upvotes or downvotes a thread (link) given the thread ID
                case "vote":
                    $thread_id = $reddit_command[1];

                    //Separate up/down from the command
                    $vote = explode("vote $thread_id", $args);
                    //Maybe it's Pidgin, but there seems to be a whitespace issue
                    $vote_direction = trim($vote[1]);

                    //Convert the words to an integer! Yay!   
                    if ($vote_direction == "up") {
                        $vote_direction = 1;
                    } elseif ($vote_direction == "down") {
                        $vote_direction = -1;
                    } else {
                        $vote_direction = 0;
                    }

                    $set_vote = $reddit->getLink($thread_id);
                    if (true == $set_vote) {
                        //If the vote was successful, return it to the user
                        if ($set_vote->vote($vote_direction)) {

                            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: vote confirmed \r\n");
                        } else {
                            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: cannot vote :( \r\n");
                        }
                    }
                    break;
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

        case ".poke":
            $args = substr($args, 0, -3);
            fputs($socket, "PRIVMSG " . $config['chan'] . ' :' . chr(1) . 'ACTION pokes ' . "$args" . chr(1) . "\r\n");
            break;

        case ".mem":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . convert(memory_get_usage(true)) . "\r\n");
            break;

        case ".checkport":
            $args = explode(":", $args);
            fputs($socket, "PRIVMSG " . $config['chan'] . " :rezultat: " . Portscan($args[0], $args[1]) . "\r\n");
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
                        $data = array("name" => 'irc.devhax.com #fallout memo', "content" => $file_contents, "visible" => false);
                        $data_string = json_encode($data);

                        $ch = curl_init('http://paste.gelat.in/api/v1/create');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_NOBODY, 0);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($data_string))
                        );
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

                        $curl = curl_exec($ch);

                        curl_close($ch);

                        $result = json_decode($curl, true);

                        fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: http://paste.gelat.in/" . $result['id'] . "\r\n");

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


        case '.sonatia':
            /*
             * http://feeds.feedburner.com/DinnerTonight
             * http://www.taste.com.au/feeds/latest+recipes.xml
             * http://darinacooking.com/category/course/dessert/feed/
             * http://www.food.com/rssapi.do?page_type=28&slug=desserts
             */
            $food_xml = simplexml_load_file('http://www.food.com/rssapi.do?page_type=28&slug=desserts');
            if ($food_xml) {
                $title = $food_xml->channel->item->title;
                $description = $food_xml->channel->item->description;

                //Get rid of nasty HTML
                $description = preg_replace("/<img[^>]+\>/i", "", $description);
                $description = strip_tags($description);
                //Shorten description
                $description = substr($description, 0, strpos($description, ' ', 50));
                //Shorten URL with bit.ly
                $url = make_bitly_url($food_xml->channel->item->link, $config['bitly_username'], $config['bitly_apikey'], 'xml');
                fputs($socket, "PRIVMSG " . $config['chan'] . " :$title [$description...] $url\r\n");
            }

            break;

        case ".malo":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :http://i.imgur.com/Bb7WO.jpg\r\n");
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

        //IMDB search
        case ".imdb":

            //http://clickontyler.com/blog/2008/05/scraping-imdb-with-php/
            $m = new MediaInfo();
            $info = $m->getMovieInfo($args);

            //replace null values with ?
            if (null == $info['rating']):
                $info['rating'] = '?';
            endif;
            if (null == $info['director']):
                $info['director'] = '?';
            endif;

            if (null == $info['release_date']):
                $info['release_date'] = '?';
            endif;

            if (null == $info['id']):
                fputs($socket, "PRIVMSG " . $config['chan'] . " : no results, dupin \r\n");
                break;

            endif;

            fputs($socket, "PRIVMSG " . $config['chan'] . " : title: [" . $info['title'] . "] rating: [" . $info['rating'] . "] director: [" . $info['director'] . "] release date: [" . $info['release_date'] . "] \r\n");

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
            $btc = json_decode($mtgox_json,true);

            $last = intval($btc['last']);
            $usd = trim($args) * $last;
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $usd . " USD\r\n");

            break;

        //Converts USD to Bitcoin
        case ".usd2btc":
            $mtgox_json = file_get_contents('https://api.bitcoinaverage.com/ticker/USD/');
            $btc = json_decode($mtgox_json,true);

            $last = intval($btc['last']);
            $btc = trim($args) / $last;
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: " . $btc . " BTC\r\n");

            break;

        //Bitcoin related functions
        case ".btc":

            //Query mtgox 
            $mtgox_json = file_get_contents('https://api.bitcoinaverage.com/ticker/USD/');
            $btc = json_decode($mtgox_json,true);

            switch (trim($args)) {

                case 'bid':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: buy: " . $btc['bid'] . "\r\n");
                    break;
                case 'last':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: last: " . $btc['last'] . "\r\n");
                    break;

                case 'ask':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: sell: " . $btc['ask'] . "\r\n");
                    break;

                case 'avg':
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: high: " . $btc['24h_avg'] . "\r\n");
                    break;

                case null:
                    fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]:  use bid, last, ask or avg\r\n");
                    break;
            }

            break;

        //Gets weather conditions by postal code/zip
        case ".w":
            $weather_command = explode(" ", $args);

            $forecast = new ForecastIO($config['forecast.io_apikey']);

            switch (trim($weather_command[0])) {

                case 'set':
                    //Get user's location
                    $address = rawurlencode($ex[5] . $ex[6]);
                    $geocode = file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $address . '&sensor=false');
                    $output = json_decode($geocode);
                    $geo['lat'] = $output->results[0]->geometry->location->lat;
                    $geo['long'] = $output->results[0]->geometry->location->lng;

                    $location = array(
                        'lat' => $geo['lat'],
                        'long' => $geo['long']);

                    if (true == $location) {
                        //store lat and long in redis
                        $redis->set($nickc[1], $location['lat'] . ',' . $location['long']);
                        echo "geo lat & long (" . $location['lat'] . $location['long'] . ") set for " . $nickc[1] . "\r\n";
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :location set for " . $nickc[1] . "\r\n");
                    } elseif (false == $location) {
                        echo "couln't find lat & long for $location\n";
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :error: could not add location for " . $nickc[1] . "\r\n");
                    }

                    break;


                default:
                    //If user has a lat and long in redis, get conditions
                    if (($user_location = $redis->get($nickc[1]))) {
                        $geo = explode(',', $user_location);

                        $condition = $forecast->getCurrentConditions($geo[0], $geo[1]);

                        $forecast_conditions = $forecast->getForecastWeek($geo[0], $geo[1]);

   


                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Currently " . substr($condition->getTemperature(),0,5) . "C (" . c2f($condition->getTemperature()) . "F) and " . $condition->getSummary() . ". Tomorrow low of " . substr($forecast_conditions[1]->getMinTemperature(), 0, 5) . "C (" . c2f($forecast_conditions[1]->getMinTemperature()) . "F), high of " . substr($forecast_conditions[1]->getMaxTemperature(), 0, 5) . "C (" . c2f($forecast_conditions[1]->getMaxTemperature()) . "F) and " . $forecast_conditions[1]->getSummary() . " \r\n");
                    } else {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :You don't exist. Please set your location by using .w set <city, state/postal code/zipcode>\r\n");
                    }

                    break;

                case 'get':
                    $address = rawurlencode($ex[5] . $ex[6]);
                    $geocode = file_get_contents('http://maps.google.com/maps/api/geocode/json?address=' . $address . '&sensor=false');
                    $output = json_decode($geocode);
                    $coordinates['lat'] = $output->results[0]->geometry->location->lat;
                    $coordinates['long'] = $output->results[0]->geometry->location->lng;


                    $condition = $forecast->getCurrentConditions($coordinates['lat'], $coordinates['long']);
                    $forecast_conditions = $forecast->getForecastWeek($coordinates['lat'], $coordinates['long']);

                    if (null !== $condition) {
                        fputs($socket, "PRIVMSG " . $config['chan'] . " :" . $nickc[1] . ": Currently " . $condition->getTemperature() . "C (" . c2f($condition->getTemperature()) . "F) and " . $condition->getSummary() . ". Tomorrow low of " . $forecast_conditions[1]->getMinTemperature() . "C (" . c2f($forecast_conditions[1]->getMinTemperature()) . "F), high of " . $forecast_conditions[1]->getMaxTemperature() . "C (" . c2f($forecast_conditions[1]->getMaxTemperature()) . "F) and " . $forecast_conditions[1]->getSummary() . " \r\n");
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
            $slink[] = "lose some weight!";
            $slink[] = "you smell, shower!";
            $slink[] = "you have the figure of an angel";
            $slink[] = "everyone in your life loves you";
            $slink[] = "why are you so handsome?";
            $slink[] = "lookin good!";
            
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


        //There needs to a better way to list commands
        case ".help":
            fputs($socket, "PRIVMSG " . $nickc[1] . " :All commands are here: http://paste.gelat.in/26 \r\n");

            break;

        ##BOT/SYS COMMANDS##
        case ".uptime":
            #  if ($ex[0] == $config['admin']) {
            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . exec('uptime') . "\r\n");
            # }
            break;

        case ".free":
            #  if ($ex[0] == $config['admin']) {
            fputs($socket, "PRIVMSG " . $config['chan'] . " :" . exec('free -m | egrep Mem:') . "\r\n");
            #  }
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
            #if ($ex[0] == $config['admin']) {
            $channel = trim($args);
            fputs($socket, "JOIN $channel\r\n");
            # }
            break;


        case ".kick":
            // if ($ex[0] == $config['admin']) {
            fputs($socket, "KICK " . $config['chan'] . " $args\n");
            // }
            break;
        case ".octouncle":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: meat popsicle\r\n");
            break;

        case ".octoaunt":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: don't make me tell mom\r\n");
            break;

        case ".octomom":
            fputs($socket, "MODE " . $config['chan'] . " +o snacsnoc \r\n");
            fputs($socket, "MODE " . $config['chan'] . " +o Gr33n3gg \r\n");
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: dad knows about our secret!\r\n");
            break;

        case ".octodad":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: meow\n");
            break;

        case ".linux":
            $rss_feed = 'https://github.com/torvalds/linux/commits/master.atom';
            $feed = simplexml_load_file($rss_feed);
            if ($feed) {

                $link = $feed->entry[0]->link->attributes();
                $title = trim($feed->entry[0]->title);

                $short_url = make_bitly_url($link['href'], $config['bitly_username'], $config['bitly_apikey'], 'xml');
                $latest_commit = "$title [commit: $short_url]";
            } else {
                return false;
            }

            fputs($socket, "PRIVMSG " . $config['chan'] . " :https://github.com/torvalds/linux Latest commit: $latest_commit\r\n");

            break;

        case ".snacklinux":
            $rss_feed = 'https://github.com/snacsnoc/snacklinux/commits/master.atom';
            $feed = simplexml_load_file($rss_feed);
            if ($feed) {

                $link = $feed->entry[0]->link->attributes();
                $title = trim($feed->entry[0]->title);


                $short_url = make_bitly_url($link['href'], $config['bitly_username'], $config['bitly_apikey'], 'xml');
                $latest_commit = "$title [commit: $short_url]";
            } else {
                return false;
            }

            fputs($socket, "PRIVMSG " . $config['chan'] . " :https://github.com/snacsnoc/snacklinux Latest commit: $latest_commit\r\n");
            break;

        case ".shibabot":
            $rss_feed = 'https://github.com/notori0us/shibabot/commits/master.atom';
            $feed = simplexml_load_file($rss_feed);
            if ($feed) {

                $link = $feed->entry[0]->link->attributes();
                $title = trim($feed->entry[0]->title);


                $short_url = make_bitly_url($link['href'], $config['bitly_username'], $config['bitly_apikey'], 'xml');
                $latest_commit = "$title [commit: $short_url]";
            } else {
                return false;
            }

            fputs($socket, "PRIVMSG " . $config['chan'] . " :https://github.com/notori0us/shibabot Latest commit: $latest_commit\r\n");
            break;


        case ".octobaby":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: feed me your soul\r\n");
            break;

        case ".octosister":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: lets commit incestuous incest\r\n");
            break;

        case ".octobrother":
            fputs($socket, "PRIVMSG " . $config['chan'] . " :$nickc[1]: show me where the :V is at \r\n");
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
