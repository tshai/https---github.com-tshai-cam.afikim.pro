<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

class credit_card_payment
{
    public function __construct()
    {
    }

    public static function insert3DLog($transcationID, $errorCode, $errorMessage, $orderID, $security): void
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $sql = "INSERT INTO 3DSecureLogs (transcationID,errorCode,errorMessage,orderID,security,dateIn) VALUES (?,?,?,?,?,?)";
        $stmt = $mysqli->prepare($sql);
        $date = date('Y-m-d H:i:s');
        $stmt->bind_param("ssssss", $transcationID, $errorCode, $errorMessage, $orderID, $security, $date);
        if (!$stmt->execute()) {
            errors::addError("Error: " . $stmt->error, "classes/credit_card_payment.php line 23");
        }
        $stmt->close();
        $mysqli->close();
    }

    public static function insertCardCam(PaymentData $paymentData): void
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $sql = "INSERT INTO card_cam (order_day,price,site_name,ipaddress,time_expire,lastdigits,original_date,user_id,user_ask_to_delete,admin_show,TransactionID,Amount_Currency,referrer,transactionProcessor,noRealPayment,orderId,country,CCCountry,inatecTransactionid,inatecTransactionStatus,ccDetailsID,paymentStatus,approved3D,timeExpiredTemp) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $mysqli->prepare($sql);
        $date = date('Y-m-d H:i:s');
        $website = "mifgashim.net";
        $ipAddress = credit_card_payment::getUserIP();
        $zeroVal = "0";
        $oneVal = "1";
        $emptyVal = "";
        $curency = "ILS";
        $country = "IL";
        $timeExpire = $paymentData->paymentStatus == 1 ? $paymentData->timeExpiredTemp : "0";
        $transactionProcessor = "Cam online";
        $stmt->bind_param(
            "ssssdssiiissssssssssisss",
            $date,
            $paymentData->price,
            $website,
            $ipAddress,
            $timeExpire,
            $paymentData->lastdigits,
            $date,
            $paymentData->user_id,
            $zeroVal,
            $oneVal,
            $emptyVal,
            $curency,
            $emptyVal,
            $transactionProcessor,
            $zeroVal,
            $paymentData->orderId,
            $country,
            $country,
            $paymentData->inatecTransactionid,
            $paymentData->inatecTransactionStatus,
            $paymentData->ccDetailsID,
            $paymentData->paymentStatus,
            $zeroVal,
            $paymentData->timeExpiredTemp
        );
        if (!$stmt->execute()) {
            errors::addError("Error: " . $stmt->error, "classes/credit_card_payment.php line 35");
        }
        $stmt->close();
        $mysqli->close();
    }

    public static function insertCCDetailsIfNotExists(CreditCard $creditCard): string
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $checkSql = "SELECT id FROM cc_details WHERE card_number = ? and user_id = ? and year_date = ? and month_date = ? and cvv = ?";
        $checkStmt = $mysqli->prepare($checkSql);
        $checkStmt->bind_param("sisss", $creditCard->card_number, $creditCard->user_id, $creditCard->year_date, $creditCard->month_date, $creditCard->cvv);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $existingId = $row['id'];
            $checkStmt->close();
            return $existingId; // Return the existing ID
        } else {
            $checkStmt->close();
            $sql = "INSERT INTO cc_details (card_number,id_number,year_date,month_date,cvv,user_id,last_digits,date_in,active,firstName,lastName,encryptCard,email,encryptIDNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $date = date('Y-m-d H:i:s');
            $encryptedCard = CryptoHelper::encrypt($creditCard->card_number);
            $encryptIDNumber = CryptoHelper::encrypt($creditCard->id_number);
            $active = "1";
            $stmt->bind_param(
                "sssssississsss",
                $creditCard->card_number,
                $creditCard->id_number,
                $creditCard->year_date,
                $creditCard->month_date,
                $creditCard->cvv,
                $creditCard->user_id,
                $creditCard->last_digits,
                $date,
                $active,
                $creditCard->firstName,
                $creditCard->lastName,
                $encryptedCard,
                $creditCard->email,
                $encryptIDNumber
            );
            if (!$stmt->execute()) {
                errors::addError("Error: " . $stmt->error, "classes/credit_card_payment.php line 69");
            }
            $insertedId = $mysqli->insert_id;
            $stmt->close();
            $mysqli->close();
            return $insertedId;
        }
    }

    public static function getUserIP()
    {
        // Check for forwarded IP address
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim(end($ipAddresses));
        }
        // Return remote address if no forwarded for header exists
        return $_SERVER['REMOTE_ADDR'];
    }

    public static function sendPaymentRequest($url, $data_request): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_request)
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
        }
        curl_close($ch);
        return $response;
    }
}
