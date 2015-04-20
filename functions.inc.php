<?php

function checkport($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 4);
    if ($fp) {
        return true;
    } else {
        return false;
    }
}


function c2f($celsius){
  return  round((intval(trim($celsius)) * 1.8) + 32, 2);
}

function f2c($fahrenheit){
    return round(intval((trim($fahrenheit)) - 32) / 1.8, 2);
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
//Thanks to http://www.ibm.com/developerworks/xml/library/x-youtubeapi/
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

?> 