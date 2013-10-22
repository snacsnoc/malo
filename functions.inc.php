<?php

function Portscan($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 4);
    if ($fp) {
        return "open";
    } else {
        return "closed";
    }
}


function c2f($celsius){
  return  round((intval(trim($celsius)) * 1.8) + 32, 2);
}

function f2c($fahrenheit){
    return round(intval((trim($fahrenheit)) - 32) / 1.8, 2);
}
/**
 * Get weather conditions
 * @param type $woeid Yahoo Weather ID
 * @param type $unit Temperature unit, F or C
 * @return mixed 
 */
function getWeather($woeid, $unit = 'F') {
    if ($unit == 'F') {
        $data = get_data("http://weather.yahooapis.com/forecastrss?w=" . $woeid . "&u=f");
    }

    /*
      $weather = simplexml_load_string($data);
      $channel_yweather = $weather->channel->children("http://xml.weather.yahoo.com/ns/rss/1.0");
     */

    $tomorrow = date('D', strtotime(' +1 day'));
    #9885 cranbrook, 8775 calgary
    #[^"] negated character class. matches to anything but "
    $temperature = get_match('/<yweather:condition  text="[^"]*"  code="[0-9][0-9]?[0-9]?[0-9]?"  temp="(.*)"/isU', $data);

    $cond = strtolower(get_match('/<yweather:condition  text="(.*)"/isU', $data));

    //  $humidity = get_match('/<yweather:atmosphere humidity="(.*)"/isU', $data);
    //    $pressure_unit = get_match('/<yweather:units temperature="[^"]*" distance="[^"]*" pressure="(.*)"/isU', $data);

    preg_match('/<yweather:forecast day="' . $tomorrow . '" date="[^"]*" low="(.*)"/isU', $data, $forecast_low);

    preg_match('/<yweather:forecast day="' . $tomorrow . '" date="[^"]*" low="[^"]*" high="(.*)"/isU', $data, $forecast_high);

    preg_match('/<yweather:forecast day="' . $tomorrow . '" date="[^"]*" low="[^"]*" high="[^"]*" text="(.*)"/isU', $data, $forecast_cond);

    $forecast_cond[1] = strtolower($forecast_cond[1]);

    //Check if the forecast is set
    if (!isset($forecast_low[1]) || !isset($forecast_high[1])) {
        $forecast_low[1] = 'unknown ';
        $forecast_high[1] = 'unknown ';
    }

    if ($cond == "unknown") {
        $cond = "unable to find condition";
    }
    if (null == $temperature) {
        return false;
        // fputs($socket, "PRIVMSG " . $config['chan'] . " :unknown or unavailable, klokan\n");
    } else {
        return $weather = array('condition' => $cond,
            'temperature' => $temperature,
            'unit' => $unit,
            'forecast_high' => $forecast_high[1],
            'forecast_low' => $forecast_low[1],
            'forecast_condition' => $forecast_cond[1]);
    }
}

function convert($size) {
    $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
}

// http://www.bumpershine.com/making-short-wordpress-urls-with-bit-ly-and-php

function make_bitly_url($url, $login, $appkey, $format = 'xml', $history = 1) {
//create the URL
    $bitly = 'http://api.bit.ly/v3/shorten?login=' . $login . '&apiKey=' . $appkey . '&uri=' . urlencode($url) .
            '&format=' . $format . '&history=' . $history;
//get the url
//could also use cURL here
    $response = file_get_contents($bitly);
//parse depending on desired format
    if (strtolower($format) == 'json') {
        $json = @json_decode($response, true);
        return $json['data']['url'];
    } elseif (strtolower($format) == 'xml') { //xml
        $xml = simplexml_load_string($response);
        return $xml->data->url;
    } elseif (strtolower($format) == 'txt') { //text
        return $response;
    }
}

//This is old
function linkify($t) {
    $t = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]", "<a target=\"_blank\" href=\"\\0\">\\0</a>", $t);
    return $t;
}

function mtgox_query($path, array $req = array()) {
    // API settings
    $key = '24233448-9a64-4b68-866d-622716e1e964';
    $secret = 'H89hWo6Y+MfsVbsgf42rEYxWnkkn2+Da3IjfaqUIsl/SlPCocWaEFcJKToaEbE6lgnIyMewhEUlM1FbjJE2vJw==';

    // generate a nonce as microtime, with as-string handling to avoid problems with 32bits systems
    $mt = explode(' ', microtime());
    $req['nonce'] = $mt[1] . substr($mt[0], 2, 6);

    // generate the POST data string
    $post_data = http_build_query($req, '', '&');

    // generate the extra headers
    $headers = array(
        'Rest-Key: ' . $key,
        'Rest-Sign: ' . base64_encode(hash_hmac('sha512', $post_data, base64_decode($secret), true)),
    );

    // our curl handle (initialize if required)
    static $ch = null;
    if (is_null($ch)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MtGox PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')');
    }
    curl_setopt($ch, CURLOPT_URL, 'https://data.mtgox.com/api/' . $path);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // run the query
    $res = curl_exec($ch);
    if ($res === false)
        throw new Exception('Could not get reply: ' . curl_error($ch));
    $dec = json_decode($res, true);
    if (!$dec)
        throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
    return $dec;
}

function bin2asc($in) {
    $out = '';
    for ($i = 0, $len = strlen($in); $i < $len; $i += 8) {
        $out .= chr(bindec(substr($in, $i, 8)));
    }
    return $out;
}

function asc2bin($in) {
    $out = '';
    for ($i = 0, $len = strlen($in); $i < $len; $i++) {
        $out .= sprintf("%08b", ord($in{$i}));
    }
    return $out;
}

function parseVideoEntry($entry) {
    $obj = new stdClass;

    // get nodes in media: namespace for media information
    $media = $entry->children('http://search.yahoo.com/mrss/');
    $obj->title = $media->group->title;
    $obj->description = $media->group->description;

    // get video player URL
    $attrs = $media->group->player->attributes();
    $obj->watchURL = $attrs['url'];

    // get video thumbnail
    $attrs = $media->group->thumbnail[0]->attributes();
    $obj->thumbnailURL = $attrs['url'];

    // get <yt:duration> node for video length
    $yt = $media->children('http://gdata.youtube.com/schemas/2007');
    $attrs = $yt->duration->attributes();
    $obj->length = $attrs['seconds'];

    // get <yt:stats> node for viewer statistics
    $yt = $entry->children('http://gdata.youtube.com/schemas/2007');
    $attrs = $yt->statistics->attributes();
    $obj->viewCount = $attrs['viewCount'];

    // get <gd:rating> node for video ratings
    $gd = $entry->children('http://schemas.google.com/g/2005');
    if ($gd->rating) {
        $attrs = $gd->rating->attributes();
        $obj->rating = $attrs['average'];
    } else {
        $obj->rating = 0;
    }

    // get <gd:comments> node for video comments
    $gd = $entry->children('http://schemas.google.com/g/2005');
    if ($gd->comments->feedLink) {
        $attrs = $gd->comments->feedLink->attributes();
        $obj->commentsURL = $attrs['href'];
        $obj->commentsCount = $attrs['countHint'];
    }

    // get feed URL for video responses
    $entry->registerXPathNamespace('feed', 'http://www.w3.org/2005/Atom');
    $nodeset = $entry->xpath("feed:link[@rel='http://gdata.youtube.com/schemas/
      2007#video.responses']");
    if (count($nodeset) > 0) {
        $obj->responsesURL = $nodeset[0]['href'];
    }

    // get feed URL for related videos
    $entry->registerXPathNamespace('feed', 'http://www.w3.org/2005/Atom');
    $nodeset = $entry->xpath("feed:link[@rel='http://gdata.youtube.com/schemas/
      2007#video.related']");
    if (count($nodeset) > 0) {
        $obj->relatedURL = $nodeset[0]['href'];
    }

    // return object to caller
    return $obj;
}

function get_web_page($url) {
    $options = array(
        CURLOPT_RETURNTRANSFER => true, // return web page
        CURLOPT_HEADER => false, // don't return headers
        CURLOPT_FOLLOWLOCATION => true, // follow redirects
        CURLOPT_ENCODING => "", // handle all encodings
        CURLOPT_USERAGENT => "malo-irc-bot", // who am i
        CURLOPT_AUTOREFERER => true, // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
        CURLOPT_TIMEOUT => 120, // timeout on response
        CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $content = curl_exec($ch);
    $err = curl_errno($ch);
    $errmsg = curl_error($ch);
    $header = curl_getinfo($ch);
    curl_close($ch);

    $header['errno'] = $err;
    $header['errmsg'] = $errmsg;
    $header['content'] = $content;
    return $header;
}

/**
 * http://www.phpro.org/examples/Read-Line-From-File.html
 * Read a line number from a file
 *
 * @param    string    $file    The path to the file
 * @param    int    $line_num    The line number to read
 * @param    string    $delimiter    The character that delimits lines
 * @return    string    The line that is read
 *
 */

function lineread($file, $line_num, $delimiter = "\n") {
    // set the counter to one 
    $i = 1;

    // open the file for reading 
    $fp = fopen($file, 'r');

    // loop over the file pointer 
    while (!feof($fp)) {
       // read the line into a buffer 
        $buffer = stream_get_line($fp, 1024, $delimiter);
        // if we are at the right line number 
        if ($i == $line_num) {
            #            return the line that is currently in the buffer
            return $buffer;
        }
        // increment the line counter 
        $i++;
        //clear the buffer 
        $buffer = '';
    }

    fclose($fp);
    return false;
}


function validateURL($url) {
    $pattern = '/^(([\w]+:)?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&amp;?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?$/';
    return preg_match($pattern, $url);
}

function phpHelp($fun) {
    $fun = str_replace("_", "-", $fun);
    $fun = trim($fun);
    #http://www.php.net/manual/en/function.fread.php
    if ($handle = fopen("http://php.net/manual/en/function.$fun.php", "r")) {
        $contents = '';
        while (!feof($handle)) {
            $contents .= fread($handle, 8192);
        }
        $contents = str_replace("\n", "", $contents);
        preg_match("/\<span class=\"refname\"\>(.*?)\<\/span\>.*?\<span class=\"dc-title\"\>(.*?)\<\/span\>/", $contents, $match);
        $title = $match[1];
        $desc = $match[2];


        preg_match("/\<div class=\"methodsynopsis dc-description\"\>(.*?)\<\/div\>/", $contents, $match);
        $syntax = $match[1];

        $syntax = preg_replace("/<tt class=\".*?\">(.*?)<\/tt>/", chr(3) . "14$1" . chr(3), $syntax);
        $syntax = preg_replace("/<span class=\".*?\">(.*?)<\/span>/", "$1", $syntax);
        $syntax = preg_replace("/<span class=\".*?\">(.*?)<\/span>/", "$1", $syntax);
        $syntax = preg_replace("/<span class=\".*?\">(.*?)<\/span>/", "$1", $syntax);
        $syntax = preg_replace("/<b>(.*?)<\/b>/", chr(32) . "$1" . chr(32), $syntax);
        $desc = preg_replace("/<a href=\"(.*?)\" class=\".*?\">(.*?)<\/a>/", "http://us.php.net/manual/en/$1 (" . chr(32) . "$2" . chr(32) . ")", $desc);
        $desc = strip_tags($desc);
        $syntax = strip_tags($syntax);
        if ($syntax)
            return "$title - $desc Syntax: $syntax (http://php.net/manual/en/function.{$fun}.php)";
        if ($title)
            return "$title - $desc (http://php.net/manual/en/function.{$fun}.php)";
        else
            return false;

        fclose($handle);
    } else
        return false;
}

function topsongs() {
    $objDOM = new DOMDocument();
    $objDOM->load("http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/ws/RSS/topsongs/sf=143441/limit=5/xml");

    $note = $objDOM->getElementsByTagName("entry");
    foreach ($note as $value) {
        $i++;
        $song = $value->getElementsByTagName("title");
        $songs[$i]['song'] = $song->item(0)->nodeValue;

        $category = $value->getElementsByTagName("category");
        foreach ($category as $cat) {
            $songs[$i]['cat'] = $cat->getAttribute('label');
        }
    }
    $m = "Top 5 Songs";
    $b = chr(32);
    foreach ($songs as $key => $song) {
        $e = explode("-", $song['song']);
        $artist = trim($e[1]);
        $title = trim($e[0]);
        $cat = trim($song['cat']);
        $m .= " $key. $b$artist - $title$b ($cat)";
    }
    return $m;
}

function topmovies() {
    $objDOM = new DOMDocument();
    $objDOM->load("http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/ws/RSS/topmovies/sf=143441/limit=5/xml");

    $note = $objDOM->getElementsByTagName("entry");
    foreach ($note as $value) {
        $i++;
        $song = $value->getElementsByTagName("title");
        $movies[$i]['title'] = $song->item(0)->nodeValue;
    }
    $m = "Top 5 Movies:";
    foreach ($movies as $key => $movie) {
        $e = explode("-", $movie['title']);
        #$artist = $e[1];
        unset($title, $n);
        $featuring = $e[count($e) - 1];
        for ($n = 0; $n < count($e) - 1; $n++) {
            if ($n > 0)
                $title .= "-" . $e[$n];
            else
                $title .= $e[$n];
        }
        $title = trim($title);
        $featuring = trim($featuring);
        $b = chr(32);
        $m .= " $key. $b$title$b ($featuring)";
    }
    return $m;
}

//This doesn't work very well
function newegg() {
    $objDOM = new DOMDocument();
    $objDOM->load("http://www.newegg.com/Product/RSS.aspx?Submit=RSSDailyDeals");

    $note = $objDOM->getElementsByTagName("item");
    $i = 0;
    foreach ($note as $value) {
        $i++;
        $t = $value->getElementsByTagName("title");
        $item[$i]['title'] = $t->item(0)->nodeValue;

        $l = $value->getElementsByTagName("link");
        $item[$i]['link'] = $l->item(0)->nodeValue;

        $l = $value->getElementsByTagName("description");
        $item[$i]['description'] = strip_tags($l->item(0)->nodeValue);
    }
    $r = rand(1, $i);
    $abc = preg_match("/(http\:\/\/www\.newegg\.com\/Product\/Product\.aspx\?Item=[A-Z0-9a-z]*?)\&.*?/", $item[$r]['link'], $l);
    $l = $l[1];
    if ($r) {
        print_r(linkify($item[$r]['title'] . " " . $l . " " . str_replace(array('Add To Cart', "\t", "\n", "\r"), ' ', $item[$r]['description'])));
        return linkify($item[$r]['title'] . " " . $l . " " . str_replace(array('Add To Cart', "\t", "\n", "\r"), ' ', $item[$r]['description']));
    }
}

function Acronyms($query) {
    $query = ereg_replace('[[:space:]]+', '/', trim($query));
    $url = "http://acronyms.thefreedictionary.com/" . $query;
    preg_match_all('/<*td><td>(.*?)<\/td>/', file_get_contents($url), $matches);
    if (!$matches[1][0]) {
        return "there were no results for $query";
    } else {
        #return print_r($query);
        $result1 = '';
        $result2 = '';

        #return print_r($matches);
        $limit1 = count($matches['0']) < 5 ? count($matches['0']) : 5;
        $limit2 = count($matches['0']) < 10 ? count($matches['0']) : 10;
        for ($i = 0; $i < $limit1; $i++) {
            $result1 .= " | " . html_entity_decode(strip_tags($matches[1][$i]));
        }
        for ($i = $limit1; $i < $limit2; $i++) {
            $result2 .= " | " . html_entity_decode(strip_tags($matches[1][$i]));
        }
        return substr($result1, 3);
        if ($limit2 > 5) {
            return substr($result2, 3);
        }
    }
}

/**
 * Get Yahoo Weather ID
 * @param string $loc Location
 * @param object $redis Redis instance
 * @return boolean 
 */
function getWOEID($loc) {
/*

    //If we're using redis, get it
    if (true == $redis) {
        //If it exists, return
        if (($woeid = $redis->get($loc))) {
            return $woeid;
        }else{
            echo 'FAILURE!';
        }
        
    } else {
        /**
         * You will need to manually make the subdirectory "cache"
         * in order for this to work, but that's all.
         */
    /*
        $cache = "./cache/$loc.txt";
        if (file_exists($cache)) {
            return file_get_contents($cache);
        }
    }
    */
//http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20geo.places%20where%20text%3D%22Barrie%20CA%22&format=xml

    $q = "select woeid from geo.places where text = '$loc' limit 1";
    $ch = curl_init('http://query.yahooapis.com/v1/public/yql?format=json&q=' . urlencode($q));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response) {
        try {
            
            $response = json_decode($response, true);
            
           var_dump($response['query']['results']['place']);
           
            $response = intval($response['query']['results']['place']['woeid']);
            
            if(null == $response){
              $response =   intval($response['query']['results']['place'][0]['woeid']);
            }
            // this block is new, we store the response locally
            if ($response) {
                /*
                //Cache via Redis
                if (true == $redis) {
                    $redis->set($loc, $response);
                } else {
                    file_put_contents($cache, $response);
                }
                 * 
                 */
                return $response;
            }
        } catch (Exception $ex) {
            return false;
        }
    }
    return false;
}

/* format the result */

//function contents($parser, $data) {
//    echo $data;
//}

function format_result($input) {
    return strtolower(str_replace(array(' ', '(', ')'), array('-', '-', ''), $input));
}

/* helper:  does regex */

function get_match($regex, $content) {
    preg_match($regex, $content, $matches);
    return $matches[1];
}

/* gets the xml data from Alexa */

function get_data($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $xml = curl_exec($ch);
    curl_close($ch);
    return $xml;
}



function weatherInfo($countryId = "SWXX0031", $unit = "c") {
    $url = "http://weather.yahooapis.com/forecastrss?w=$countryId&u=$unit";
    //echo $url;

    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $xml = curl_exec($ch);
    curl_close($ch);


    $string = simplexml_load_string($xml);
    var_dump($string);
    // Load RSS from yahoo-weather
    //  $doc = domxml_xmltree($xml);
    //echo $doc->yweather->node_name();
    // Get all elements that have this namespace (the yweather-elements)
    //   $elements = $doc->get_elements_by_tagname("*");
    // Return array
    $info = array();

    // Get all yweather-elements and most of their attributes
    foreach ($elements as $element) {
        // Condition
        if ($element->localName == "condition") {
            $info['condition_text'] = $element->getAttribute("text");
            $info['condition_code'] = $element->getAttribute("code");
            $info['condition_temp'] = $element->getAttribute("temp");
        }
        // Location
        if ($element->localName == "location") {
            $info['location_city'] = $element->getAttribute("city");
            $info['location_country'] = $element->getAttribute("country");
        }
        // Wind
        if ($element->localName == "wind") {
            $info['wind_chill'] = $element->getAttribute("chill");
            $info['wind_direction'] = $element->getAttribute("direction");
            $info['wind_speed'] = $element->getAttribute("speed");
        }
        // Atmosphere
        if ($element->localName == "atmosphere") {
            $info['atmosphere_humidity'] = $element->getAttribute("humidity");
            $info['atmosphere_visibility'] = $element->getAttribute("visibility");
            $info['atmosphere_pressure'] = $element->getAttribute("pressure");
            $info['atmosphere_rising'] = $element->getAttribute("rising");
        }
        // Astronomy
        if ($element->localName == "astronomy") {
            $info['astronomy_sunrise'] = $element->getAttribute("sunrise");
            $info['astronomy_sunset'] = $element->getAttribute("sunset");
        }
        // Check the RSS-feed if you want to extend this
    }

    // Create weather-css-class
    $info['weather_css_class'] = strtolower(str_replace(array(" ", "(", ")"), array("-", "-", ""), $info['condition_text']));

    // Create time css-class
    // Get the lastBuildDate from the feed
    // (updates every 30 mins and is sure to be local time)
    // Fuck, this is gonna need a fix, it doesn't always update that often!
    //$lbds = $doc->get_elements_by_tagname("lastBuildDate");
    // Anyway not to loop this? I know there's only _one_ lastBuildDate-element
    foreach ($lbds as $lbd) {
        $localTime = $lbd->firstChild->nodeValue;
    }

    // Generate timestamp
    $localTS = strtotime($localTime);

    $info['last_build_date'] = $localTime;

    // Get hour (only interested in what hour it is)
    $hour = date("H", $localTS);

    $info['hour_of_day'] = $hour;

    // Night (00:00 -> 05:00)
    if ($hour >= 0 and $hour < 6) {
        $info['time_css_class'] = "night";
    }
    // Morning (06:00 -> 11:00)
    elseif ($hour > 5 and $hour < 12) {
        $info['time_css_class'] = "morning";
    }
    // Day (12:00 -> 17:00)
    elseif ($hour > 11 and $hour < 18) {
        $info['time_css_class'] = "day";
    }
    // Evening (18:00 -> 23:00)
    elseif ($hour > 17 and $hour <= 23) {
        $info['time_css_class'] = "evening";
    }
    // WTF
    else {
        $info['time_css_class'] = "wtf?!";
    }
    // Extend if you want to be more specific

    return $info;
}

/**
 * Link: http://www.bin-co.com/php/scripts/load/
 * Version : 3.00.A
 */
function load($url, $options = array()) {
    $default_options = array(
        'method' => 'get',
        'post_data' => false,
        'return_info' => false,
        'return_body' => true,
        'cache' => false,
        'referer' => '',
        'headers' => array(),
        'session' => false,
        'session_close' => false,
    );
    // Sets the default options.
    foreach ($default_options as $opt => $value) {
        if (!isset($options[$opt]))
            $options[$opt] = $value;
    }

    $url_parts = parse_url($url);
    $ch = false;
    $info = array(//Currently only supported by curl.
        'http_code' => 200
    );
    $response = '';

    $send_header = array(
        'Accept' => 'text/*',
        'User-Agent' => 'BinGet/1.00.A (http://www.bin-co.com/php/scripts/load/)'
            ) + $options['headers']; // Add custom headers provided by the user.

    if ($options['cache']) {
        $cache_folder = joinPath(sys_get_temp_dir(), 'php-load-function');
        if (isset($options['cache_folder']))
            $cache_folder = $options['cache_folder'];
        if (!file_exists($cache_folder)) {
            $old_umask = umask(0); // Or the folder will not get write permission for everybody.
            mkdir($cache_folder, 0777);
            umask($old_umask);
        }

        $cache_file_name = md5($url) . '.cache';
        $cache_file = joinPath($cache_folder, $cache_file_name); //Don't change the variable name - used at the end of the function.

        if (file_exists($cache_file)) { // Cached file exists - return that.
            $response = file_get_contents($cache_file);

            //Seperate header and content
            $separator_position = strpos($response, "\r\n\r\n");
            $header_text = substr($response, 0, $separator_position);
            $body = substr($response, $separator_position + 4);

            foreach (explode("\n", $header_text) as $line) {
                $parts = explode(": ", $line);
                if (count($parts) == 2)
                    $headers[$parts[0]] = chop($parts[1]);
            }
            $headers['cached'] = true;

            if (!$options['return_info'])
                return $body;
            else
                return array('headers' => $headers, 'body' => $body, 'info' => array('cached' => true));
        }
    }

    if (isset($options['post_data'])) { //There is an option to specify some data to be posted.
        $options['method'] = 'post';

        if (is_array($options['post_data'])) { //The data is in array format.
            $post_data = array();
            foreach ($options['post_data'] as $key => $value) {
                $post_data[] = "$key=" . urlencode($value);
            }
            $url_parts['query'] = implode('&', $post_data);
        } else { //Its a string
            $url_parts['query'] = $options['post_data'];
        }
    } elseif (isset($options['multipart_data'])) { //There is an option to specify some data to be posted.
        $options['method'] = 'post';
        $url_parts['query'] = $options['multipart_data'];
        /*
          This array consists of a name-indexed set of options.
          For example,
          'name' => array('option' => value)
          Available options are:
          filename: the name to report when uploading a file.
          type: the mime type of the file being uploaded (not used with curl).
          binary: a flag to tell the other end that the file is being uploaded in binary mode (not used with curl).
          contents: the file contents. More efficient for fsockopen if you already have the file contents.
          fromfile: the file to upload. More efficient for curl if you don't have the file contents.

          Note the name of the file specified with fromfile overrides filename when using curl.
         */
    }

    ///////////////////////////// Curl /////////////////////////////////////
    //If curl is available, use curl to get the data.
    if (function_exists("curl_init")
            and (!(isset($options['use']) and $options['use'] == 'fsocketopen'))) { //Don't use curl if it is specifically stated to use fsocketopen in the options
        if (isset($options['post_data'])) { //There is an option to specify some data to be posted.
            $page = $url;
            $options['method'] = 'post';

            if (is_array($options['post_data'])) { //The data is in array format.
                $post_data = array();
                foreach ($options['post_data'] as $key => $value) {
                    $post_data[] = "$key=" . urlencode($value);
                }
                $url_parts['query'] = implode('&', $post_data);
            } else { //Its a string
                $url_parts['query'] = $options['post_data'];
            }
        } else {
            if (isset($options['method']) and $options['method'] == 'post') {
                $page = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
            } else {
                $page = $url;
            }
        }

        if ($options['session'] and isset($GLOBALS['_binget_curl_session']))
            $ch = $GLOBALS['_binget_curl_session']; //Session is stored in a global variable
        else
            $ch = curl_init($url_parts['host']);

        curl_setopt($ch, CURLOPT_URL, $page) or die("Invalid cURL Handle Resouce");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Just return the data - not print the whole thing.
        curl_setopt($ch, CURLOPT_HEADER, true); //We need the headers
        curl_setopt($ch, CURLOPT_NOBODY, !($options['return_body'])); //The content - if true, will not download the contents. There is a ! operation - don't remove it.
        $tmpdir = NULL; //This acts as a flag for us to clean up temp files
        if (isset($options['method']) and $options['method'] == 'post' and isset($url_parts['query'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            if (is_array($url_parts['query'])) {
                //multipart form data (eg. file upload)
                $postdata = array();
                foreach ($url_parts['query'] as $name => $data) {
                    if (isset($data['contents']) && isset($data['filename'])) {
                        if (!isset($tmpdir)) { //If the temporary folder is not specifed - and we want to upload a file, create a temp folder.
                            //  :TODO: DICKS
                            $dir = sys_get_temp_dir();
                            $prefix = 'load';

                            if (substr($dir, -1) != '/')
                                $dir .= '/';
                            do {
                                $path = $dir . $prefix . mt_rand(0, 9999999);
                            } while (!mkdir($path, $mode));

                            $tmpdir = $path;
                        }
                        $tmpfile = $tmpdir . '/' . $data['filename'];
                        file_put_contents($tmpfile, $data['contents']);
                        $data['fromfile'] = $tmpfile;
                    }
                    if (isset($data['fromfile'])) {
                        // Not sure how to pass mime type and/or the 'use binary' flag
                        $postdata[$name] = '@' . $data['fromfile'];
                    } elseif (isset($data['contents'])) {
                        $postdata[$name] = $data['contents'];
                    } else {
                        $postdata[$name] = '';
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $url_parts['query']);
            }
        }

        //Set the headers our spiders sends
        curl_setopt($ch, CURLOPT_USERAGENT, $send_header['User-Agent']); //The Name of the UserAgent we will be using ;)
        $custom_headers = array("Accept: " . $send_header['Accept']);
        if (isset($options['modified_since']))
            array_push($custom_headers, "If-Modified-Since: " . gmdate('D, d M Y H:i:s \G\M\T', strtotime($options['modified_since'])));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);
        if ($options['referer'])
            curl_setopt($ch, CURLOPT_REFERER, $options['referer']);

        curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/binget-cookie.txt"); //If ever needed...
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $custom_headers = array();
        unset($send_header['User-Agent']); // Already done (above)
        foreach ($send_header as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $custom_headers[] = "$name: $item";
                }
            } else {
                $custom_headers[] = "$name: $value";
            }
        }
        if (isset($url_parts['user']) and isset($url_parts['pass'])) {
            $custom_headers[] = "Authorization: Basic " . base64_encode($url_parts['user'] . ':' . $url_parts['pass']);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);

        $response = curl_exec($ch);

        if (isset($tmpdir)) {
            //rmdirr($tmpdir); //Cleanup any temporary files :TODO:
        }

        $info = curl_getinfo($ch); //Some information on the fetch

        if ($options['session'] and !$options['session_close'])
            $GLOBALS['_binget_curl_session'] = $ch; //Dont close the curl session. We may need it later - save it to a global variable
        else
            curl_close($ch);  //If the session option is not set, close the session.




            
//////////////////////////////////////////// FSockOpen //////////////////////////////
    } else { //If there is no curl, use fsocketopen - but keep in mind that most advanced features will be lost with this approch.
        if (!isset($url_parts['query']) || (isset($options['method']) and $options['method'] == 'post'))
            $page = $url_parts['path'];
        else
            $page = $url_parts['path'] . '?' . $url_parts['query'];

        if (!isset($url_parts['port']))
            $url_parts['port'] = ($url_parts['scheme'] == 'https' ? 443 : 80);
        $host = ($url_parts['scheme'] == 'https' ? 'ssl://' : '') . $url_parts['host'];
        $fp = fsockopen($host, $url_parts['port'], $errno, $errstr, 30);
        if ($fp) {
            $out = '';
            if (isset($options['method']) and $options['method'] == 'post' and isset($url_parts['query'])) {
                $out .= "POST $page HTTP/1.1\r\n";
            } else {
                $out .= "GET $page HTTP/1.0\r\n"; //HTTP/1.0 is much easier to handle than HTTP/1.1
            }
            $out .= "Host: $url_parts[host]\r\n";
            foreach ($send_header as $name => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $out .= "$name: $item\r\n";
                    }
                } else {
                    $out .= "$name: $value\r\n";
                }
            }
            $out .= "Connection: Close\r\n";

            //HTTP Basic Authorization support
            if (isset($url_parts['user']) and isset($url_parts['pass'])) {
                $out .= "Authorization: Basic " . base64_encode($url_parts['user'] . ':' . $url_parts['pass']) . "\r\n";
            }

            //If the request is post - pass the data in a special way.
            if (isset($options['method']) and $options['method'] == 'post') {
                if (is_array($url_parts['query'])) {
                    //multipart form data (eg. file upload)
                    // Make a random (hopefully unique) identifier for the boundary
                    srand((double) microtime() * 1000000);
                    $boundary = "---------------------------" . substr(md5(rand(0, 32000)), 0, 10);

                    $postdata = array();
                    $postdata[] = '--' . $boundary;
                    foreach ($url_parts['query'] as $name => $data) {
                        $disposition = 'Content-Disposition: form-data; name="' . $name . '"';
                        if (isset($data['filename'])) {
                            $disposition .= '; filename="' . $data['filename'] . '"';
                        }
                        $postdata[] = $disposition;
                        if (isset($data['type'])) {
                            $postdata[] = 'Content-Type: ' . $data['type'];
                        }
                        if (isset($data['binary']) && $data['binary']) {
                            $postdata[] = 'Content-Transfer-Encoding: binary';
                        } else {
                            $postdata[] = '';
                        }
                        if (isset($data['fromfile'])) {
                            $data['contents'] = file_get_contents($data['fromfile']);
                        }
                        if (isset($data['contents'])) {
                            $postdata[] = $data['contents'];
                        } else {
                            $postdata[] = '';
                        }
                        $postdata[] = '--' . $boundary;
                    }
                    $postdata = implode("\r\n", $postdata) . "\r\n";
                    $length = strlen($postdata);
                    $postdata = 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\n" .
                            'Content-Length: ' . $length . "\r\n" .
                            "\r\n" .
                            $postdata;

                    $out .= $postdata;
                } else {
                    $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
                    $out .= 'Content-Length: ' . strlen($url_parts['query']) . "\r\n";
                    $out .= "\r\n" . $url_parts['query'];
                }
            }
            $out .= "\r\n";

            fwrite($fp, $out);
            while (!feof($fp)) {
                $response .= fgets($fp, 128);
            }
            fclose($fp);
        }
    }

    //Get the headers in an associative array
    $headers = array();

    if ($info['http_code'] == 404) {
        $body = "";
        $headers['Status'] = 404;
    } else {
        //Seperate header and content
        $header_text = substr($response, 0, $info['header_size']);
        $body = substr($response, $info['header_size']);

        foreach (explode("\n", $header_text) as $line) {
            $parts = explode(": ", $line);
            if (count($parts) == 2) {
                if (isset($headers[$parts[0]])) {
                    if (is_array($headers[$parts[0]]))
                        $headers[$parts[0]][] = chop($parts[1]);
                    else
                        $headers[$parts[0]] = array($headers[$parts[0]], chop($parts[1]));
                } else {
                    $headers[$parts[0]] = chop($parts[1]);
                }
            }
        }
    }

    if (isset($cache_file)) { //Should we cache the URL?
        file_put_contents($cache_file, $response);
    }

    if ($options['return_info'])
        return array('headers' => $headers, 'body' => $body, 'info' => $info, 'curl_handle' => $ch);
    return $body;
}

function get_url_contents($url) {
    $crl = curl_init();
    $timeout = 5;
    curl_setopt($crl, CURLOPT_URL, $url);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
    $ret = curl_exec($crl);
    curl_close($crl);
    return $ret;
}

///////////////////////////////////////////////////////////////////////////////////////////////////////// 
// Free PHP IMDb Scraper API for the new IMDb Template. 
// Version: 2.5 
// Author: Abhinay Rathore 
// Website: http://www.AbhinayRathore.com 
// Blog: http://web3o.blogspot.com 
// Demo: http://lab.abhinayrathore.com/imdb/ 
// More Info: http://web3o.blogspot.com/2010/10/php-imdb-scraper-for-new-imdb-template.html 
// Last Updated: Sept 22, 2011 
///////////////////////////////////////////////////////////////////////////////////////////////////////// 

class Imdb {

    function getMovieInfo($title) {
        $imdbId = $this->getIMDbIdFromGoogle(trim($title));
        if ($imdbId === NULL) {
            $arr = array();
            $arr['error'] = "No Title found in Search Results!";
            return $arr;
        }
        return $this->getMovieInfoById($imdbId);
    }

    function getMovieInfoById($imdbId) {
        $arr = array();
        $imdbUrl = "http://www.imdb.com/title/" . trim($imdbId) . "/";
        $html = $this->geturl($imdbUrl);
        if (stripos($html, "<meta name=\"application-name\" content=\"IMDb\" />") !== false) {
            $arr = $this->scrapMovieInfo($html);
            $arr['imdb_url'] = $imdbUrl;
        } else {
            $arr['error'] = "No Title found on IMDb!";
        }
        return $arr;
    }

    function getIMDbIdFromGoogle($title) {
        $url = "http://www.google.com/search?q=imdb+" . rawurlencode($title);
        $html = $this->geturl($url);
        $ids = $this->match_all('/<a href="http:\/\/www.imdb.com\/title\/(tt\d+).*?".*?>.*?<\/a>/ms', $html, 1);
        if (!isset($ids[0])) //if Google fails 
            return $this->getIMDbIdFromBing($title); //search using Bing 
        else
            return $ids[0]; //return first IMDb result 
    }

    function getIMDbIdFromBing($title) {
        $url = "http://www.bing.com/search?q=imdb+" . rawurlencode($title);
        $html = $this->geturl($url);
        $ids = $this->match_all('/<a href="http:\/\/www.imdb.com\/title\/(tt\d+).*?".*?>.*?<\/a>/ms', $html, 1);
        if (!isset($ids[0]))
            return NULL;
        else
            return $ids[0]; //return first IMDb result 
    }

    // Scan movie meta data from IMDb page 
    function scrapMovieInfo($html) {
        $arr = array();
        $arr['title_id'] = $this->match('/<link rel="canonical" href="http:\/\/www.imdb.com\/title\/(tt\d+)\/" \/>/ms', $html, 1);
        $arr['title'] = trim($this->match('/<title>(IMDb \- )*(.*?) \(.*?<\/title>/ms', $html, 2));
        $arr['original_title'] = trim($this->match('/class="title-extra">(.*?)</ms', $html, 1));
        $arr['year'] = trim($this->match('/<title>.*?\(.*?(\d{4}).*?\).*?<\/title>/ms', $html, 1));
        $arr['rating'] = $this->match('/ratingValue">(\d.\d)</ms', $html, 1);
        $arr['genres'] = array();
        foreach ($this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Genre.?:(.*?)(<\/div>|See more)/ms', $html, 1), 1) as $m)
            array_push($arr['genres'], $m);
        $arr['directors'] = array();
        foreach ($this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Director.?:(.*?)(<\/div>|>.?and )/ms', $html, 1), 1) as $m)
            array_push($arr['directors'], $m);
        $arr['writers'] = array();
        foreach ($this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Writer.?:(.*?)(<\/div>|>.?and )/ms', $html, 1), 1) as $m)
            array_push($arr['writers'], $m);
        $arr['stars'] = array();
        foreach ($this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Stars:(.*?)<\/div>/ms', $html, 1), 1) as $m)
            array_push($arr['stars'], $m);
        $arr['cast'] = array();
        foreach ($this->match_all('/<td class="name">(.*?)<\/td>/ms', $html, 1) as $m)
            array_push($arr['cast'], trim(strip_tags($m)));
        $arr['mpaa_rating'] = $this->match('/infobar">.<img.*?alt="(.*?)".*?>/ms', $html, 1);
        //Get extra inforation on  Release Dates and AKA Titles 
        if ($arr['title_id'] != "") {
            $releaseinfoHtml = $this->geturl("http://www.imdb.com/title/" . $arr['title_id'] . "/releaseinfo");
            $arr['also_known_as'] = $this->getAkaTitles($releaseinfoHtml, $usa_title);
            $arr['usa_title'] = $usa_title;
            $arr['release_date'] = $this->match('/Release Date:<\/h4>.*?([0-9][0-9]? (January|February|March|April|May|June|July|August|September|October|November|December) (19|20)[0-9][0-9]).*?(\(|<span)/ms', $html, 1);
            $arr['release_dates'] = $this->getReleaseDates($releaseinfoHtml);
        }
        $arr['plot'] = trim(strip_tags($this->match('/<p itemprop="description">(.*?)(<\/p>|<a)/ms', $html, 1)));
        $arr['poster'] = $this->match('/img_primary">.*?<img src="(.*?)".*?<\/td>/ms', $html, 1);
        $arr['poster_large'] = "";
        $arr['poster_small'] = "";
        $arr['poster_full'] = "";
        if ($arr['poster'] != '' && strrpos($arr['poster'], "nopicture") === false && strrpos($arr['poster'], "ad.doubleclick") === false) { //Get large and small posters 
            $arr['poster_large'] = preg_replace('/_V1\..*?.jpg/ms', "_V1._SY500.jpg", $arr['poster']);
            $arr['poster_small'] = preg_replace('/_V1\..*?.jpg/ms', "_V1._SY150.jpg", $arr['poster']);
            $arr['poster_full'] = preg_replace('/_V1\..*?.jpg/ms', "_V1._SY0.jpg", $arr['poster']);
        } else {
            $arr['poster'] = "";
        }
        $arr['runtime'] = trim($this->match('/Runtime:<\/h4>.*?(\d+) min.*?<\/div>/ms', $html, 1));
        if ($arr['runtime'] == '')
            $arr['runtime'] = trim($this->match('/infobar.*?(\d+) min.*?<\/div>/ms', $html, 1));
        $arr['top_250'] = trim($this->match('/Top 250 #(\d+)</ms', $html, 1));
        $arr['oscars'] = trim($this->match('/Won (\d+) Oscars./ms', $html, 1));
        $arr['awards'] = trim($this->match('/(\d+) wins/ms', $html, 1));
        $arr['nominations'] = trim($this->match('/(\d+) nominations/ms', $html, 1));
        $arr['storyline'] = trim(strip_tags($this->match('/Storyline<\/h2>(.*?)(<em|<\/p>|<span)/ms', $html, 1)));
        $arr['tagline'] = trim(strip_tags($this->match('/Tagline.?:<\/h4>(.*?)(<span|<\/div)/ms', $html, 1)));
        $arr['votes'] = $this->match('/ratingCount">(\d+,?\d*)<\/span>/ms', $html, 1);
        $arr['language'] = array();
        foreach ($this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Language.?:(.*?)(<\/div>|>.?and )/ms', $html, 1), 1) as $m)
            array_push($arr['language'], trim($m));
        $arr['country'] = array();
        foreach ($this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Country:(.*?)(<\/div>|>.?and )/ms', $html, 1), 1) as $c)
            array_push($arr['country'], $c);

        if ($arr['title_id'] != "")
            $arr['media_images'] = $this->getMediaImages($arr['title_id']);

        return $arr;
    }

    // Scan all Release Dates 
    function getReleaseDates($html) {
        $releaseDates = array();
        foreach ($this->match_all('/<tr>(.*?)<\/tr>/ms', $this->match('/Date<\/th><\/tr>(.*?)<\/table>/ms', $html, 1), 1) as $r) {
            $country = trim(strip_tags($this->match('/<td><b>(.*?)<\/b><\/td>/ms', $r, 1)));
            $date = trim(strip_tags($this->match('/<td align="right">(.*?)<\/td>/ms', $r, 1)));
            array_push($releaseDates, $country . " = " . $date);
        }
        return $releaseDates;
    }

    // Scan all AKA Titles 
    function getAkaTitles($html, &$usa_title) {
        $akaTitles = array();
        foreach ($this->match_all('/<tr>(.*?)<\/tr>/msi', $this->match('/Also Known As(.*?)<\/table>/ms', $html, 1), 1) as $m) {
            $akaTitleMatch = $this->match_all('/<td>(.*?)<\/td>/ms', $m, 1);
            $akaTitle = trim($akaTitleMatch[0]);
            $akaCountry = trim($akaTitleMatch[1]);
            array_push($akaTitles, $akaTitle . " = " . $akaCountry);
            if ($akaCountry != '' && strrpos(strtolower($akaCountry), "usa") !== false)
                $usa_title = $akaTitle;
        }
        return $akaTitles;
    }

    // Collect all Media Images 
    function getMediaImages($titleId) {
        $url = "http://www.imdb.com/title/" . $titleId . "/mediaindex";
        $html = $this->geturl($url);
        $media = array();
        $media = array_merge($media, $this->scanMediaImages($html));
        foreach ($this->match_all('/<a href="\?page=(.*?)">/ms', $this->match('/<span style="padding: 0 1em;">(.*?)<\/span>/ms', $html, 1), 1) as $p) {
            $html = $this->geturl($url . "?page=" . $p);
            $media = array_merge($media, $this->scanMediaImages($html));
        }
        return $media;
    }

    // Scan all media images 
    function scanMediaImages($html) {
        $pics = array();
        foreach ($this->match_all('/src="(.*?)"/ms', $this->match('/<div class="thumb_list" style="font-size: 0px;">(.*?)<\/div>/ms', $html, 1), 1) as $i) {
            array_push($pics, preg_replace('/_V1\..*?.jpg/ms', "_V1._SY0.jpg", $i));
        }
        return $pics;
    }

    // ************************[ Extra Functions ]****************************** 
    function geturl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $ip = rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/" . rand(3, 5) . "." . rand(0, 3) . " (Windows NT " . rand(3, 5) . "." . rand(0, 2) . "; rv:2.0.1) Gecko/20100101 Firefox/" . rand(3, 5) . ".0.1");
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    function match_all($regex, $str, $i = 0) {
        if (preg_match_all($regex, $str, $matches) === false)
            return false;
        else
            return $matches[$i];
    }

    function match($regex, $str, $i = 0) {
        if (preg_match($regex, $str, $match) == 1)
            return $match[$i];
        else
            return false;
    }

}

class MediaInfo {

    public $info;

    function __construct($str = null) {
        if (!is_null($str))
            $this->autodetect($str);
    }

    function autodetect($str) {
        // Attempt to cleanup $str in case it's a filename ;-)
        $str = pathinfo($str, PATHINFO_FILENAME);
        $str = $this->normalize($str);

        // Is it a movie or tv show?
        if (preg_match('/s[0-9][0-9]?.?e[0-9][0-9]?/i', $str) == 1)
            $this->info = $this->getEpisodeInfo($str);
        else
            $this->info = $this->getMovieInfo($str);

        return $this->info;
    }

    function getEpisodeInfo($str) {
        $arr = array();
        $arr['kind'] = 'tv';
        return $arr;
    }

    function getMovieInfo($str) {
        $str = str_ireplace('the ', '', $str);
        $url = "http://www.google.com/search?hl=en&q=imdb+" . urlencode($str) . "&btnI=I%27m+Feeling+Lucky";
        $html = $this->geturl($url);
        if (stripos($html, "302 Moved") !== false)
            $html = $this->geturl($this->match('/HREF="(.*?)"/ms', $html, 1));

        $arr = array();
        $arr['kind'] = 'movie';
        $arr['id'] = $this->match('/poster.*?(tt[0-9]+)/ms', $html, 1);
        $arr['title'] = $this->match('/<title>(.*?)<\/title>/ms', $html, 1);
        $arr['title'] = preg_replace('/\([0-9]+\)/', '', $arr['title']);
        $arr['title'] = trim($arr['title']);
        $arr['rating'] = $this->match('/([0-9]\.[0-9])\/10/ms', $html, 1);
        $arr['director'] = trim(strip_tags($this->match('/Director:(.*?)<\/a>/ms', $html, 1)));
        $arr['release_date'] = $this->match('/([0-9][0-9]? (January|February|March|April|May|June|July|August|September|October|November|December) (19|20)[0-9][0-9])/ms', $html, 1);
        $arr['plot'] = trim(strip_tags($this->match('/Plot:(.*?)<a/ms', $html, 1)));
        $arr['genres'] = $this->match_all('/Sections\/Genres\/(.*?)[\/">]/ms', $html, 1);
        $arr['genres'] = array_unique($arr['genres']);
        $arr['poster'] = $this->match('/<a.*?name=.poster.*?src=.(.*?)(\'|")/ms', $html, 1);

        $arr['cast'] = array();
        foreach ($this->match_all('/class="nm">(.*?\.\.\..*?)<\/tr>/ms', $html, 1) as $m) {
            list($actor, $character) = explode('...', strip_tags($m));
            $arr['cast'][trim($actor)] = trim($character);
        }

        return $arr;
    }

    // ****************************************************************

    function normalize($str) {
        $str = str_replace('_', ' ', $str);
        $str = str_replace('.', ' ', $str);
        $str = preg_replace('/ +/', ' ', $str);
        return $str;
    }

    function geturl($url, $username = null, $password = null) {
        $ch = curl_init();
        if (!is_null($username) && !is_null($password))
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . base64_encode("$username:$password")));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    function match_all($regex, $str, $i = 0) {
        if (preg_match_all($regex, $str, $matches) === false)
            return false;
        else
            return $matches[$i];
    }

    function match($regex, $str, $i = 0) {
        if (preg_match($regex, $str, $match) == 1)
            return $match[$i];
        else
            return false;
    }

}

?> 