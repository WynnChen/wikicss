<?php
/**
 * config
 */

use mWiki\WikiConfig;

return new WikiConfig(
	endpoint: 'https://terraria.wiki.gg/api.php',
	username: '<name>',  // username of bot 
	password:  '<pwd>', // password for bot user 
	is_bot: false, // Is the account a bot account? true for bot account (with 'bot' right), false for normal user account (with 'user' right).

	/* below are optional options (and their default values) */
	//max_con: 6, // number of concurrent requests 
	//con_timeout: 10, // connection timeout, in seconds 
	//trans_timeout: 180, // transporting timeout, in seconds 

	/* Other curl options. */
	curl_options: array(
		// proxy setting for this wiki, if you do not want to use any proxy, just comment them out 
		//CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
		//CURLOPT_PROXY => '127.0.0.1',
		//CURLOPT_PROXYPORT => '1080',
	)
);