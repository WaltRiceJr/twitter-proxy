<?php

/**
 * =======================
 * Twitter API OAuth Proxy
 * =======================
 * Connect from (unauthenticated) javascript to this script, which authenticates the request
 * and retrieves the results from the Twitter API
 *
 * Walt Rice, Jr., R&R Computer Solutions
 * WaltRiceJr@gmail.com
 *
 * Credits:
 * Main flow of code from Mike Rogers, http://mikerogers.io/2013/02/25/how-use-twitter-oauth-1-1-javascriptjquery.html
 * Caching code mostly from Matt Mombrea, https://github.com/mombrea/twitter-api-php-cached
 *
 * Usage:
 * Send the url you want to access url encoded in the url paramater, for example (This is with JS): 
 * /twitter-proxy.php?url='+encodeURIComponent('statuses/user_timeline.json?screen_name=MikeRogers0&count=2')
*/

// The tokens, keys and secrets from the app you created at https://dev.twitter.com/apps
$config = array(
	'oauth_access_token' => 'your-token-here',
	'oauth_access_token_secret' => 'your-token-secret-here',
	'consumer_key' => 'your-key-here',
	'consumer_secret' => 'your-secret-here',
	'use_whitelist' => true, // If you want to only allow some requests to use this script.
	'base_url' => 'http://api.twitter.com/1.1/',
	'cache_time' => 60 // cache time in seconds
);

// Only allow certain requests to twitter. Stop randoms using your server as a proxy.
$whitelist = array(
	'statuses/user_timeline.json?screen_name=GreenfieldHWE&count=5&include_rts=1'=>true
);

/*
* Ok, no more config should really be needed. Yay!
*/

// We'll get the URL from $_GET[]. Make sure the url is url encoded, for example 
// encodeURIComponent('statuses/user_timeline.json?screen_name=MikeRogers0&count=10&include_rts=false&exclude_replies=true')
if(!isset($_GET['url'])){
	die('No URL set');
}

$url = $_GET['url'];

ReadLatestUpdate($url);

/*
* Caching code from Matt Mombrea, https://github.com/mombrea/twitter-api-php-cached
* modified to support caching of multiple urls in different cache files (named with the hash of the requested url)
*/

function ReadLatestUpdate($url)
{
	global $config;

	$tweet_file = 'tweet-cache-' . md5($url);

	if(!file_exists($tweet_file))
	{
		UpdateTimeline($url);
		return;
	}
	$handle = fopen($tweet_file,'r');
	$strUpdateDate = fgets($handle);
	fclose($handle);
	if(empty($strUpdateDate))
	{
		//file is empty
		UpdateTimeline($url);
	}
	else
	{
		$updateDate = new DateTime($strUpdateDate);
		$now = new DateTime("now");
		$minutes = round(($now->format('U') - $updateDate->format('U')));
	
		if($minutes > $config['cache_time'])
		{
			//reload feed
			UpdateTimeline($url);
		}
		else
		{
			//read cache
			ReadFromCache($url);
		}
	
	}
}
 
function ReadFromCache($url)
{
	$tweet_file = 'tweet-cache-' . md5($url);
	
	$handle = fopen($tweet_file,'r');
	$data = fgets($handle); //skip first line
	$data = '';

	while(!feof($handle))
	{
		$data.= fgets($handle);
	}
	
	fclose($handle);
	echo $data;
}
 
function UpdateCache($url, $response)
{
	$tweet_file = 'tweet-cache-' . md5($url);

	$handle = fopen($tweet_file,'w') or die ('Cannot open cache file');
	$data = date('m/d/Y h:i:s a', time()) . "\r\n" . $response;
	fwrite($handle, $data);
	fclose($handle);
}

/*
 * Code from Mike Rogers, http://mikerogers.io/2013/02/25/how-use-twitter-oauth-1-1-javascriptjquery.html
 * with minor modifications to work with the caching
 */

function UpdateTimeline($url)
{
	global $config;
	global $whitelist;

	if($config['use_whitelist'] && !isset($whitelist[$url])){
		die('URL is not authorised');
	}
	
	// Figure out the URL parmaters
	$url_parts = parse_url($url);
	parse_str($url_parts['query'], $url_arguments);
	
	$full_url = $config['base_url'].$url; // Url with the query on it.
	$base_url = $config['base_url'].$url_parts['path']; // Url without the query.

	// Set up the oauth Authorization array
	$oauth = array(
		'oauth_consumer_key' => $config['consumer_key'],
		'oauth_nonce' => time(),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_token' => $config['oauth_access_token'],
		'oauth_timestamp' => time(),
		'oauth_version' => '1.0'
	);
		
	$base_info = buildBaseString($base_url, 'GET', array_merge($oauth, $url_arguments));
	$composite_key = rawurlencode($config['consumer_secret']) . '&' . rawurlencode($config['oauth_access_token_secret']);
	$oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
	$oauth['oauth_signature'] = $oauth_signature;
	
	// Make Requests
	$header = array(
		buildAuthorizationHeader($oauth), 
		'Expect:'
	);
	$options = array(
		CURLOPT_HTTPHEADER => $header,
		//CURLOPT_POSTFIELDS => $postfields,
		CURLOPT_HEADER => false,
		CURLOPT_URL => $full_url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false
	);
	
	$feed = curl_init();
	curl_setopt_array($feed, $options);
	$result = curl_exec($feed);
	$info = curl_getinfo($feed);
	curl_close($feed);

	UpdateCache($url, $result);

	// Send suitable headers to the end user.
	if(isset($info['content_type']) && isset($info['size_download'])){
		header('Content-Type: '.$info['content_type']);
		header('Content-Length: '.$info['size_download']);
	}

	echo $result;
}

/**
* Code below from http://stackoverflow.com/questions/12916539/simplest-php-example-retrieving-user-timeline-with-twitter-api-version-1-1 by Rivers 
* with a few modfications by Mike Rogers to support variables in the URL nicely
*/

function buildBaseString($baseURI, $method, $params) 
{
	$r = array();
	ksort($params);
	foreach($params as $key=>$value){
	$r[] = "$key=" . rawurlencode($value);
	}
	return $method."&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $r));
}

function buildAuthorizationHeader($oauth) 
{
	$r = 'Authorization: OAuth ';
	$values = array();
	foreach($oauth as $key=>$value)
	$values[] = "$key=\"" . rawurlencode($value) . "\"";
	$r .= implode(', ', $values);
	return $r;
}


?>