<?php

class tools{

    public static function SendGlobalSms($fullPhone, $message) {
        // Use the provided endpoint for the GlobalSms SOAP service
        $wsdl = 'http://api.itnewsletter.co.il/webservices/wssms.asmx?WSDL';
        
        // Options for the SOAP client
        $options = array(
            'trace' => 1,    // Enables tracing of request so we can view it later
            'cache_wsdl' => WSDL_CACHE_NONE, // Disables WSDL caching
        );
    
        try {
            // Create a new SOAP client using the WSDL URL
            $client = new SoapClient($wsdl, $options);
    
            // Prepare parameters as an associative array
            // Note: You might need to adjust parameter names based on the actual API documentation
            $params = array(
                'sKey' => '4Wu233XcP7x99y48826CN5d7n54Y26Sh', // API Key, adjust the parameter name as needed
                'sSource' => '0559662231', // Source phone number, adjust the parameter name as needed
                'sDestination' => '0' . $fullPhone, // Destination phone number, prepended with '0'
                'sMessage' => $message, // The SMS message, adjust the parameter name as needed
                // Assuming 'sSchedTime' and 'sId' are additional optional parameters you might not use
            );
    
            // The method name 'sendSmsToRecipients' should be replaced with the actual method name provided by the GlobalSms API
            // This name can be found in the WSDL or API documentation
            $result = $client->__soapCall("SendSms", array($params));
    
            // Return the result
            return $result;
        } catch (SoapFault $e) {
            // Handle exceptions or errors
            return 'Error: ' . $e->getMessage();
        }
    }
    
}

?>