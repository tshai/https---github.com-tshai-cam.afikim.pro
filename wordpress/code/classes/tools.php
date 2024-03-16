<?php

class tools
{

    public static function sendZadarmaSMS($phone, $message)
    {
        $api = new \Zadarma_API\Api("b217ecd93b742a6c8e53", "4be13469607b674aa8fd");
        try {
            $result = $api->sendSms($phone, $message);
            return $result;
        } catch (\Zadarma_API\ApiException $ex) {
            errors::addError($ex->getMessage(), "tools.php->13");
            return $ex->getMessage();
        }
    }

    public static function insertSmsLog($user_id, $success, $message)
    {
        try {
            $dbInstance = db::getInstance();
            $mysqli = $dbInstance->mysqli(); // Ensure this method correctly returns a mysqli object.
            if ($mysqli->connect_error) {
                die("Connection failed: " . $mysqli->connect_error);
            }

            $sql = "INSERT INTO sms_logs (user_id, sms_code,datein,success,ip_address) VALUES (?, ?,?,?,?)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt === false) {
                // Log the error and return or throw
                errors::addError("Prepare failed: " . $mysqli->error, "dbRes");
                return "Prepare failed: " . $mysqli->error; // Or throw new Exception("Prepare failed: " . $mysqli->error);
            }
            $date = date('Y-m-d H:i:s');
            $ip_address = credit_card_payment::getUserIP();
            $stmt->bind_param("issis", $user_id, $message, $date, $success, $ip_address);
            if ($stmt->execute()) {
                // Success path
                $insertedId = $mysqli->insert_id;
                $stmt->close();
                $mysqli->close();
                return   $insertedId;
            } else {
                // Execution failed, log the error, close the statement and connection then return or throw.
                $error = "Execute failed: " . $stmt->error;
                errors::addError($error, "dbRes");
                $stmt->close();
                $mysqli->close();
                return $error; // Or throw new Exception($error);
            }
        } catch (Exception $ex) {
            // Catch block for handling exceptions
            // Assuming errors::addError logs the error, consider whether you need to return or handle the exception further.
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
            return "Exception: " . $ex->getMessage(); // Consider how you want to handle exceptions: log, return, or throw again.
        }
    }

    public static function updateSmsLog($id, $success, $user_id)
    {
        try {
            $dbInstance = db::getInstance();
            $mysqli = $dbInstance->mysqli(); // Ensure this method correctly returns a mysqli object.
            if ($mysqli->connect_error) {
                die("Connection failed: " . $mysqli->connect_error);
            }

            $sql = "UPDATE sms_logs SET success = ?, user_id=? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if ($stmt === false) {
                // Log the error and return or throw
                errors::addError("Prepare failed: " . $mysqli->error, "dbRes");
                return "Prepare failed: " . $mysqli->error; // Or throw new Exception("Prepare failed: " . $mysqli->error);
            }
            $stmt->bind_param("iii", $success, $user_id, $id);
            if ($stmt->execute()) {
                // Success path
                $stmt->close();
                $mysqli->close();
                return   true;
            } else {
                // Execution failed, log the error, close the statement and connection then return or throw.
                $error = "Execute failed: " . $stmt->error;
                errors::addError($error, "dbRes");
                $stmt->close();
                $mysqli->close();
                return $error; // Or throw new Exception($error);
            }
        } catch (Exception $ex) {
            // Catch block for handling exceptions
            // Assuming errors::addError logs the error, consider whether you need to return or handle the exception further.
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
            return "Exception: " . $ex->getMessage(); // Consider how you want to handle exceptions: log, return, or throw again.
        }
    }

    public static function checkIfUniqueCodeExists($code)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $checkSql = "SELECT count(id) as countID FROM wp_users WHERE unique_user_code = ?";
        $checkStmt = $mysqli->prepare($checkSql);
        $checkStmt->bind_param("s", $code);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $countID = $row['countID'];
            if ($countID > 0) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    public static  function checkUserSmsLogs($userId)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $now =  date('Y-m-d H:i:s');
        $time   = strtotime($now);
        $time   = $time - (60 * 60); //one hour
        $beforeOneHour = date("Y-m-d H:i:s", $time);
        $checkSql = "SELECT count(id) as countID FROM sms_logs WHERE user_id = ? and success=0 AND datein > ?";
        $checkStmt = $mysqli->prepare($checkSql);
        $checkStmt->bind_param("is", $userId, $beforeOneHour);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $countID = $row['countID'];
            if ($countID >= 3) {
                return false;
            } else {
                return true;
            }
        }
    }

    public static  function checkIPSmsLogs($ip_address)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $now =  date('Y-m-d H:i:s');
        $time   = strtotime($now);
        $time   = $time - (60 * 60); //one hour
        $beforeOneHour = date("Y-m-d H:i:s", $time);
        $checkSql = "SELECT count(id) as countID FROM sms_logs WHERE ip_address = ? and success=0 AND datein >?";
        $checkStmt = $mysqli->prepare($checkSql);
        $checkStmt->bind_param("ss", $ip_address, $beforeOneHour);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $countID = $row['countID'];
            if ($countID >= 3) {
                return false;
            } else {
                return true;
            }
        }
    }

    public static function checkIfUserExistByPhone($phone)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $checkSql = "SELECT count(umeta_id) as countID FROM wp_usermeta WHERE meta_key='phone' and meta_value =?";
        $checkStmt = $mysqli->prepare($checkSql);
        $checkStmt->bind_param("s", $phone);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $countID = $row['countID'];
            if ($countID > 0) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }


    public static function SendGlobalSms($fullPhone, $message)
    {
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
