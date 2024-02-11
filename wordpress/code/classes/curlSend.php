
<?php
class curlSend
{
/* ---------------Your Api Access Key --------------------- */
/* -------------------------------------------------------- */

/* -------------------------------------------------------- */
public static function send_sms($to,$message){
	$ApiKey = "4Wu233XcP7x99y48826CN5d7n54Y26Sh";
	$from="0559662231";
	// $to="0502263423";
	// $message="בדיקה \n test";
	$dteToDeliver="";
	$txtAddInf="localid";
	
	//echo "try send sms from: ".$from." to: ".$to."   | message: ".$message."<br /><br />";
	$data=array('ApiKey'=>$ApiKey,'txtOriginator'=>$from,'destinations'=>$to,'txtSMSmessage'=>$message,'dteToDeliver'=>$dteToDeliver,'txtAddInf'=>$txtAddInf);
	$response=self::curl_call_global_sms("sendSmsToRecipients",$data);
	
	//if result is numeric then successful
	echo "ok";
}

public static function curl_call_global_sms($cmd, $data)
{	
	//	create soap/global sms compatible xml from data and cmd
	//	cmd: command to send ('getSmsCount', 'getSmsResponses') etc.
	//	data: array('un'=><user name>, 'pw'=><password>, ...)
	$xml_body = '';
	foreach($data as $k=>$v) $xml_body .= '<'.$k.'>'.$v.'</'.$k.'>';
	$xml = '<?xml version="1.0" encoding="utf-8"?>'.
		'<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'.
			'<soap:Body>'.
				'<'.$cmd.' xmlns="apiGlobalSms">'.
				$xml_body.
				'</'.$cmd.'>'.
			'</soap:Body>'.
		'</soap:Envelope>';
	
	$baseUrl = "http://api.itnewsletter.co.il/webServices/";
	$fileName = "WsSMS.asmx";
	
	$url = $baseUrl.$fileName."?wsdl";
	
	$headers = array();
	array_push($headers, "Content-Type: text/xml; charset=utf-8");
	array_push($headers, "Accept: text/xml");
	array_push($headers, "Cache-Control: no-cache");
	array_push($headers, "Pragma: no-cache");
	array_push($headers, "SOAPAction: apiGlobalSms/" . $cmd);
	array_push($headers, "Content-Length: " . strlen($xml));
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_POSTFIELDS, "$xml");
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$cerr = curl_error($ch);
	curl_close($ch);
	
	return array($response, $code, $cerr);
}





}
?>
