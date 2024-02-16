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

    public static function insertMessageToWhatsApp($messageText, $messageType, $userNum, $girlNum, $girl_send, $file_name = null): void
    {

        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();

        // Check for connection errors
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        $date = date('Y-m-d H:i:s');
        $girl_read = $girl_send != true;
        $substringMessage = (strlen($messageText) > 100 ? substr($messageText, 0, 100) : $messageText);
        $chatsSql = "SELECT * FROM wp_whatsapp_chats WHERE girl_num=? and user_num=? LIMIT 1";
        $chats = R::getAll($chatsSql, [$girlNum, $userNum]);
        $wp_whatsapp_chats_id = 0;
        if ($chats) {
            $wp_whatsapp_chats_id = $chats[0]["ID"];
            $updateSql = "UPDATE wp_whatsapp_chats SET last_time_update=?, newest_message_cut=?, girl_read=? WHERE girl_num=? AND user_num=?";
            $updateParams = [$date, $substringMessage, $girl_read, $girlNum, $userNum];
            R::exec($updateSql, $updateParams);
        } else {
            $room_id = uniqid();
            $sqlChatsInsert = "INSERT INTO wp_whatsapp_chats (girl_num, user_num, date_in, room_id, last_time_update,newest_message_cut,girl_read) VALUES (?, ?, ?, ?,?,?,?)";
            $stmtChatsInsert = $mysqli->prepare($sqlChatsInsert);
            // Bind parameters
            $stmtChatsInsert->bind_param("iissss", $girlNum, $userNum, $date, $room_id, $date, $substringMessage, $girl_read);
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
        $stmt->close();
        $mysqli->close();
    }

    public static function insertMessageToWhatsAppAll($messageText, $userNum)
    {
        try {
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

    public static function getNestedPropertyValue($object, $path)
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (is_object($object) && property_exists($object, $key)) {
                $object = $object->{$key};
            } elseif (is_array($object)) {
                // Check if the key is in the format "array[index]"
                if (preg_match('/^(\w+)\[(\d+)\]$/', $key, $matches)) {
                    $arrayKey = $matches[1];
                    $index = (int)$matches[2];
                    if (isset($object[$arrayKey][$index]) && is_array($object[$arrayKey])) {
                        $object = $object[$arrayKey][$index];
                    } else {
                        // Key or index not found, return null
                        return null;
                    }
                } else {
                    // Key not found, return null
                    return null;
                }
            } else {
                // Key not found, return null
                return null;
            }
        }

        return $object;
    }

    public static function getValueFromJsonNested($key1, $key2, $key3, $parsedMessageWithFile)
    {
        $returnValue = whatsapp::getNestedPropertyValue($parsedMessageWithFile, $key1);
        if (empty($returnValue)) {
            $returnValue = whatsapp::getNestedPropertyValue($parsedMessageWithFile, $key2);
        }
        if (empty($returnValue)) {
            $returnValue = whatsapp::getNestedPropertyValue($parsedMessageWithFile, $key3);
        }
        return $returnValue;
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
            errors::addError("Error: " . $res, "classes/whatsapp.php line 148");
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

    public static function extraxtMessageFromJson($jsonObject)
    {
        $messageText = $jsonObject->data->data->messages[0]->message->conversation;
        if ($messageText == "") {
            $messageText = htmlspecialchars($jsonObject->data->data->messages[0]->message->extendedTextMessage->text);
        }

        return $messageText;
    }
}
