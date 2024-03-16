<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

use GuzzleHttp\Client;

class whatsapp
{
    private string $access_token = "65c23c9932502";
    private string $instance_id;

    public function __construct($param1 = null)
    {
        // Constructor logic
        if ($param1 !== null) {
            $this->instance_id = $param1;
        }
    }

    public static function checkWelcomeMessageWasSendToday($userNum, $girlNum)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        $chatsSql = "SELECT * FROM wp_whatsapp_chats WHERE girl_num=? and user_num=? LIMIT 1";
        $chats = R::getAll($chatsSql, [$girlNum, $userNum]);
        if ($chats) {
            $wp_whatsapp_chats_id = $chats[0]["ID"];
            $currentDate = new DateTime(); // Uses the current date and time
            // Format the date to a string that matches your database's expected format
            $formattedDate = $currentDate->format('Y-m-d');
            $welcomeMessage = "SELECT * FROM wp_whatsapp_messages WHERE wp_whatsapp_chats_id=? and message_type=4 and DATE(date_in) = ? LIMIT 1";
            $welcomeMessageList = R::getAll($welcomeMessage, [$wp_whatsapp_chats_id, $formattedDate]);
            if ($welcomeMessageList) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function getPricePerMessageTypeInSeconds($messageTypeID)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        $whatsapp_message_types = "SELECT * FROM whatsapp_message_types WHERE ID=? LIMIT 1";
        $whatsapp_message_type_res =  R::getRow($whatsapp_message_types, [$messageTypeID]);
        return $whatsapp_message_type_res["price_per_message_in_seconds"];
    }


    public static function insertMessageToWhatsApp($messageText, $messageType, $userNum, $girlNum, $girl_send, $file_name = null): int
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $date = date('Y-m-d H:i:s');
        $girl_read = $girl_send;
        $substringMessage = (strlen($messageText) > 99 ? substr($messageText, 0, 99) : $messageText);
        $chatsSql = "SELECT * FROM wp_whatsapp_chats WHERE girl_num=? and user_num=? LIMIT 1";
        $chats = R::getAll($chatsSql, [$girlNum, $userNum]);
        $wp_whatsapp_chats_id = 0;
        if ($chats) {
            $wp_whatsapp_chats_id = $chats[0]["ID"];
            $updateSql = "UPDATE wp_whatsapp_chats SET last_time_update=?, newest_message_cut=?,newest_message_type=?, girl_read=? WHERE girl_num=? AND user_num=?";
            $updateParams = [$date, $substringMessage, $messageType, $girl_read, $girlNum, $userNum];
            R::exec($updateSql, $updateParams);
        } else {
            $room_id = uniqid();
            $sqlChatsInsert = "INSERT INTO wp_whatsapp_chats (girl_num, user_num, date_in, room_id, last_time_update,newest_message_cut,newest_message_type,girl_read) VALUES (?, ?, ?, ?,?,?,?,?)";
            $stmtChatsInsert = $mysqli->prepare($sqlChatsInsert);
            $stmtChatsInsert->bind_param("iissssii", $girlNum, $userNum, $date, $room_id, $date, $substringMessage,  $messageType, $girl_read);
            if (!$stmtChatsInsert->execute()) {
                errors::addError("Error: " . $stmtChatsInsert->error, "classes/whatsapp.php line 43");
            }
            $stmtChatsInsert->close();
            $wp_whatsapp_chats_id = $mysqli->insert_id;
        }

        $sql = "INSERT INTO wp_whatsapp_messages (date_in, message, message_type, girl_send,wp_whatsapp_chats_id,file_name) VALUES (?, ?, ?, ?, ?,?)";
        $stmt = $mysqli->prepare($sql);
        $date = date('Y-m-d H:i:s');
        $stmt->bind_param("sssiis", $date, $messageText, $messageType, $girl_send, $wp_whatsapp_chats_id, $file_name);
        if (!$stmt->execute()) {
            errors::addError("Error: " . $stmt->error, "classes/whatsapp.php line 45");
        }
        $insertedId = $mysqli->insert_id;
        $stmt->close();
        $mysqli->close();
        return $insertedId;
    }

    public static function insertMessageToWhatsAppAll($messageText, $userNum)
    {
        try {
            $messageText = substr($messageText, 0, 3999);
            $dbInstance = db::getInstance();
            $mysqli = $dbInstance->mysqli();
            // Check for connection errors
            if ($mysqli->connect_error) {
                die("Connection failed: " . $mysqli->connect_error);
            }

            // Create a prepared statement
            $sql = "INSERT INTO wp_income_messages (date_in, message_content, user_id) VALUES (?, ?, ?)";
            // Create a prepared statement
            $stmt = $mysqli->prepare($sql);
            if ($stmt === false) {
                // Log the error
                errors::addError("Prepare failed: " . $mysqli->error, "dbRes");
            }


            $date = date('Y-m-d H:i:s');
            // Bind parameters
            $stmt->bind_param("ssi", $date, $messageText, $userNum);
            // Execute the statement
            if ($stmt->execute()) {
                $stmt->close();
                $mysqli->close();
                return "success";
            } else {
                // Close the statement and connection
                $stmt->close();
                $mysqli->close();
                return "Error: " . $stmt->error;
            }
        } catch (Exception $ex) {
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
        }
    }
    public function sendMessage($phoneNumber, $message)
    {
        try {
            $phoneWithoutPlus = str_replace('+', '', $phoneNumber);
            $jsonUrl = "https://social.hilix.org/api/send?" . http_build_query([
                'access_token' => $this->access_token,
                'instance_id' => $this->instance_id,
                'number' => $phoneWithoutPlus,
                'type' => 'text',
                'message' => $message
            ]);
            $res = whatsapp::webRequestPost($jsonUrl);
            $resJson = json_decode($res);
            if (isset($resJson->status) && strtolower($resJson->status) == "success") {
                return $resJson;
            } else {
                return $resJson;
            }
        } catch (Exception $ex) {
            // Handle exceptions as needed, e.g., log errors
            return false;
        }
    }

    public function sendFile($fileUrl, $message, $fileName, $phoneNumber)
    {
        try {
            $phoneWithoutPlus = str_replace('+', '', $phoneNumber);
            $jsonUrl = "https://social.hilix.org/api/send?" . http_build_query([
                'access_token' => $this->access_token,
                'instance_id' => $this->instance_id,
                'media_url' => $fileUrl,
                'message' => $message,
                'type' => 'media',
                'number' => $phoneWithoutPlus,
                'filename' => $fileName
            ]);
            $res = whatsapp::webRequestPost($jsonUrl);
            $resJson = json_decode($res);
            if (isset($resJson->status) && strtolower($resJson->status) == "success") {
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            // Handle exceptions as needed, e.g., log errors
            return false;
        }
    }

    private static function webRequestPost($jsonUrl)
    {
        try {
            $jsonContent = file_get_contents($jsonUrl);
            return $jsonContent;
        } catch (Exception $ex) {
            // Handle the exception as needed
            errors::addError("Error: " . $ex->getMessage(), "classes/whatsapp.php line 195");
            return "0";
        }
    }

    public static  function getNestedArrayValue($array, $path)
    {
        foreach ($path as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                // Key doesn't exist, return default null or any other default value
                return null;
            }
        }
        return $array;
    }

    public static function getCaptionFromReply($jsonObject, $pathArray, $whatsapp, $senderPhone)
    {
        $quotedMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'conversation']);
        $quotedMessageText = whatsapp::getNestedArrayValue($jsonObject, $quotedMessage);
        $pathCaptionMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'documentWithCaptionMessage', 'message', 'documentMessage', 'caption']);
        $documentWithCaptionMessage = whatsapp::getNestedArrayValue($jsonObject, $pathCaptionMessage);
        $pathImageMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'imageMessage', 'caption']);
        $imageMessage = whatsapp::getNestedArrayValue($jsonObject, $pathImageMessage);
        $pathDocumentMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'documentMessage', 'caption']);
        $documentMessage = whatsapp::getNestedArrayValue($jsonObject, $pathDocumentMessage);
        $pathVideoMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'videoMessage', 'caption']);
        $videoMessage = whatsapp::getNestedArrayValue($jsonObject, $pathVideoMessage);
        $pathAudioMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'audioMessage', 'caption']);
        $audioMessage = whatsapp::getNestedArrayValue($jsonObject, $pathAudioMessage);
        $pathStickerMessage = array_merge($pathArray, ['contextInfo', 'quotedMessage', 'stickerMessage', 'caption']);
        $stickerMessage = whatsapp::getNestedArrayValue($jsonObject, $pathStickerMessage);
        $caption = "";
        if (!empty($quotedMessageText)) {
            $caption = $quotedMessageText;
        } else if (!empty($documentWithCaptionMessage)) {
            $caption = $documentWithCaptionMessage;
        } else if (!empty($imageMessage)) {
            $caption = $imageMessage;
        } else if (!empty($documentMessage)) {
            $caption = $documentMessage;
        } else if (!empty($videoMessage)) {
            $caption = $videoMessage;
        } else if (!empty($audioMessage)) {
            $caption = $audioMessage;
        } else if (!empty($stickerMessage)) {
            $caption = $stickerMessage;
        }

        if ($caption == null || $caption == "") {
            $whatsappRes = $whatsapp->sendMessage($senderPhone, "הודעת מערכת: לא ניתן לשלוח הודעות כאן עליך להיכנס לכרטיס של המשתמש");
            die();
        }
        return $caption;
    }

    public static function girlCanGetWhatsapp($modelUserID)
    {
        $modelUserFromWorking = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUserID, "online_from"]);
        $modelUserToWorking = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUserID, "online_to"]);
        $isTimeInRangeToSendMessage = model_helper::isCurrentHourInRange($modelUserFromWorking['meta_value'], $modelUserToWorking['meta_value']);
        if ($isTimeInRangeToSendMessage) {
            return true;
        } else {
            return false;
        }
    }

    public static function extraxtMessageFromJson($jsonObject)
    {
        $pathMessageText = ['data', 'data', 'messages', 0, 'message', 'conversation'];
        $messageText = whatsapp::getNestedArrayValue($jsonObject, $pathMessageText);
        if (empty($messageText)) {
            $pathMessageText = ['data', 'data', 'messages', 0, 'message', 'extendedTextMessage', 'text'];
            $messageText = whatsapp::getNestedArrayValue($jsonObject, $pathMessageText);
        }

        return $messageText;
    }

    public static  function base64ToHex($base64String)
    {
        // Decode the Base64 string to a byte string (binary data)
        $binaryData = base64_decode($base64String);

        // Convert the binary data to a hexadecimal string
        $hexString = bin2hex($binaryData);

        return $hexString;
    }
    public static  function decryptWhatsappImage($fileUrl, $mediaKey, $docType, $extention)
    {
        $hexString = self::base64ToHex($mediaKey);

        // Prepare the request URL
        $requestUrl = "https://wad.hilix.org/download?fileExt=" . $extention . "&docType=" . $docType . "&url=" . urlencode($fileUrl) . "&mediaKey=" . $hexString;

        // Make the web request and get the byte content
        $byteContent = file_get_contents($requestUrl);

        // Return the content as a base64-encoded string
        return base64_encode($byteContent);
    }

    public static function createFileFromBase64($base64Content, $outputFilePath)
    {
        // Decode the base64 content to get the binary data
        $binaryData = base64_decode($base64Content);

        // Write the binary data to a file
        file_put_contents($outputFilePath, $binaryData);

        // Check if the file was created successfully
        if (file_exists($outputFilePath)) {
            return true;
        } else {
            return false;
        }
    }

    public static function getCurrentDomain()
    {
        // Check if SSL is enabled or not
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        // Get the server name (domain)
        $domainName = $_SERVER['HTTP_HOST'];
        // Concatenate to get the full URL
        $currentDomain = $protocol . $domainName;
        return $currentDomain; // Outputs something like https://example.com
    }

    public static function getHelpShortCodeContent($isModel)
    {
        try {
            $isModel = ($isModel == 1  ? 1 : 0);
            $dbInstance = db::getInstance();
            $mysqli = $dbInstance->mysqli();
            $sql = "SELECT * FROM chat_short_codes WHERE action<>'listcodes' and is_girl_action=?";
            $chat_short_codes = R::getAll($sql, [$isModel]);
            $messageToSendWithHelp = "";
            foreach ($chat_short_codes as $chat_short_code) {
                $messageToSendWithHelp .= $chat_short_code['array_of_keywords'] . "->" . $chat_short_code['action_description_users'] . "\n\r";
            }
            return $messageToSendWithHelp;
        } catch (Exception $ex) {
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
            return "";
        }
    }

    public static function insertChatTimeUseAndCheckTime($modelUser, $user, $send_message, $new_message_id, $price_per_message, $whatsapp, $senderPhone, $modelPhone)
    {
        ChatTimeUse::insertChatTimeUseMessage($modelUser['ID'], $user['ID'], $send_message, $new_message_id, $price_per_message, 0, 0);
        if ($send_message == 2) { // fix and send to girl also
            $whatsapp->sendMessage($senderPhone, "הודעת מערכת: שלחת בהצלחה מתנה לדגם זה.");
            $whatsapp->sendMessage($modelPhone, model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . "הודעת מערכת: קיבלת מתנה");
        }
        $time_left = ChatTimeUse::getTimeLeft($user['ID']);
        if ($time_left <= 60) {
            $whatsapp->sendMessage($senderPhone, "הודעת מערכת: נשארו לך פחות מדקה לשימוש במערכת. לחץ על הקישור להטענת חשבונך והמשך התהליך. https://mifgashim.net/user-payment");
        }
    }
}
