Malo the IRC bot. The code has been in use for a number of years. It is terrible but it works. 

###Requirements###
* My fork of [reddit-api-client](https://github.com/snacsnoc/reddit-api-client) (because I haven't bothered updating it from upstream). 

* [forecast.io-php-api](https://github.com/tobias-redmann/forecast.io-php-api?source=c) 

* [Redis PHP extension](https://github.com/nicolasff/phpredis)

* [Google API client](https://github.com/google/google-api-php-client)

###Configuration###

Edit the config values in ``proc.php``. Clone my fork of reddit-api-client and put it and the forecast.io-php-api folder in the same directory as malo (I know, I'll fix this at somepoint).

###Execution###

To start malo, just run ``php proc.php``.
