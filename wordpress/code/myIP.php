
<?php


function curl_myIP()
{	
	
	$url = "http://api.itnewsletter.co.il/ip.aspx";
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	
	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$cerr = curl_error($ch);
	curl_close($ch);
	
	return array($response, $code, $cerr);
}

$response=curl_myIP();

echo "my IP=".$response[0];

/*
//url/myIP.php#module_soap
phpinfo();
*/

?>
