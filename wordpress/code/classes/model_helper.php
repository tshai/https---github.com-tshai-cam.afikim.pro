<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

class model_helper
{
    public function __construct()
    {
    }

    public static function getChatTipe($send_mesage)
    {
        if ($send_mesage == "0") {
            return "Chat";
        } else {
            return "Message";
        }
    }

    public static function getMessageType($wp_message_id)
    {
        if ($wp_message_id == null || $wp_message_id == 0) {
            return "";
        }
        $dbInstance = db::getInstance();
        $wp_message = R::getRow('SELECT * FROM wp_whatsapp_messages WHERE id=?', [$wp_message_id]);
        if ($wp_message) {
            $wp_message_type = R::getRow('SELECT * FROM whatsapp_message_types WHERE id=?', [$wp_message['message_type']]);
            return $wp_message_type['description'];
        } else {
            return "";
        }
    }

    public static function getUserOpenLinkChatTimeUseByMessageID($wp_message_id)
    {
        if ($wp_message_id == null || $wp_message_id == 0) {
            return false;
        }
        $dbInstance = db::getInstance();
        $chat_time_use = R::getRow('SELECT * FROM chat_time_use WHERE wp_message_id=?', [$wp_message_id]);
        if ($chat_time_use) {
            $user_open_link = $chat_time_use['user_open_link'];
            if ($user_open_link) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function getGirlBlockUser($girl_num, $user_num)
    {
        $dbInstance = db::getInstance();
        $blackList = R::getRow('SELECT * FROM black_list WHERE user_id=? and girl_num=?', [$user_num, $girl_num]);
        if ($blackList && $blackList['blocked_status'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    public static function girlBlockUser($girl_num, $user_num)
    {
        try {
            $dbInstance = db::getInstance();
            $mysqli = $dbInstance->mysqli();
            if ($mysqli->connect_error) {
                die("Connection failed: " . $mysqli->connect_error);
            }

            $blackList = R::getRow('SELECT * FROM black_list WHERE user_id=? and girl_num=?', [$user_num, $girl_num]);
            if ($blackList) {
                $updateSql = "UPDATE black_list SET blocked_status=? WHERE girl_num=? AND user_id=?";
                $updateParams = [1, $girl_num, $user_num];
                R::exec($updateSql, $updateParams);
            } else {
                $sql = "INSERT INTO black_list (user_id, girl_num, date_in, blocked_status) VALUES (?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                if ($stmt === false) {
                    errors::addError("Prepare failed: " . $mysqli->error, "dbRes");
                }

                $date = date('Y-m-d H:i:s');
                $blocked_sts = 1;
                $stmt->bind_param("iisi", $user_num, $girl_num, $date, $blocked_sts);
                if ($stmt->execute()) {
                    $stmt->close();
                    $mysqli->close();
                    return "success";
                } else {
                    $stmt->close();
                    $mysqli->close();
                    return "Error: " . $stmt->error;
                }
            }
        } catch (Exception $ex) {
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
        }
    }

    public static function getMetaValue($meta_key, $filteredByUserId)
    {
        $avatar = current(array_filter($filteredByUserId, function ($item) use ($meta_key) {
            return $item['meta_key'] === $meta_key;
        }));

        // Check if $avatar is not false and not empty
        if ($avatar !== false && !empty($avatar)) {
            return htmlspecialchars_decode($avatar["meta_value"]);
        } else {
            // Handle the case where no item was found. You might return a default value or null.
            return ""; // Or return a default avatar or any other default value as needed
        }
    }

    public static function determineStringType($string)
    {
        // Check if it's serialized data
        // The @ symbol is used to suppress any errors that might be thrown during unserialization
        $isSerialized = @unserialize($string) !== false || $string === 'b:0;';
        if ($isSerialized) {
            return 'serialized';
        }

        // Check if it's JSON
        // json_decode() returns null for null input, so check for non-null string to avoid false positive
        if (!empty($string) && ($string[0] === '{' || $string[0] === '[')) {
            json_decode($string);
            $isJson = json_last_error() == JSON_ERROR_NONE;
            if ($isJson) {
                return 'json';
            }
        }

        // If it's neither serialized nor JSON, it's a regular string
        return 'regular string';
    }

    public static function calculateAge($yearOfBirth)
    {
        $currentYear = date("Y"); // Get the current year
        $age = $currentYear - $yearOfBirth; // Calculate the age
        return $age;
    }

    public static function returnFlag($language)
    {
        if ($language == "English") {
            return "/wp-content/uploads/2024/02/united-states-of-america.png";
        } else {
            return "/wp-content/uploads/2024/02/israel.png";
        }
    }

    public static function insertModelImageToGallery($imageLink, $userNum)
    {
        try {
            $dbInstance = db::getInstance();
            $mysqli = $dbInstance->mysqli(); // Ensure this method correctly returns a mysqli object.
            if ($mysqli->connect_error) {
                die("Connection failed: " . $mysqli->connect_error);
            }

            $sql = "INSERT INTO models_gallery (girl_num, image_name) VALUES (?, ?)";
            $stmt = $mysqli->prepare($sql);
            if ($stmt === false) {
                // Log the error and return or throw
                errors::addError("Prepare failed: " . $mysqli->error, "dbRes");
                return "Prepare failed: " . $mysqli->error; // Or throw new Exception("Prepare failed: " . $mysqli->error);
            }

            $stmt->bind_param("is", $userNum, $imageLink);
            if ($stmt->execute()) {
                // Success path
                $stmt->close();
                $mysqli->close();
                return "success";
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

    public static function insertOrUpdateUserAvatar($imageLink, $userNum)
    {
        try {
            $dbInstance = db::getInstance();
            $customerUserMetaAvatar = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$userNum, "user_avatar"]);
            if ($customerUserMetaAvatar) {
                $sqlUpdate = "UPDATE wp_usermeta SET meta_value=? WHERE meta_key = 'user_avatar' and user_id = ?";
                R::exec($sqlUpdate, [$imageLink, $userNum]);
            } else {
                $mysqli = $dbInstance->mysqli();
                if ($mysqli->connect_error) {
                    die("Connection failed: " . $mysqli->connect_error);
                }
                $sql = "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                if ($stmt === false) {
                    // Log the error
                    errors::addError("Prepare failed: " . $mysqli->error, "dbRes");
                }
                $user_avatar = "user_avatar";
                $stmt->bind_param("iss", $userNum, $user_avatar, $imageLink);
                if ($stmt->execute()) {
                    $stmt->close();
                    $mysqli->close();
                    return "success";
                } else {
                    $stmt->close();
                    $mysqli->close();
                    return "Error: " . $stmt->error;
                }
            }
        } catch (Exception $ex) {
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
        }
    }

    public static function resizeImage($tmpFileName,  $new_file_name)
    {
        try {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content";
            $sub_dir = "/uploads/files/";

            $target_file = $target_dir . $sub_dir . $new_file_name;
            $db_file = $sub_dir . $new_file_name;

            // Load the uploaded image
            $imageInfo = getimagesize($tmpFileName);
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            // Calculate new height to maintain aspect ratio
            $new_width = 600;
            $new_height = ($height / $width) * $new_width;

            // Create a new true color image
            $new_image = imagecreatetruecolor($new_width, $new_height);

            // Depending on the image type, create a new image from the file
            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    $uploaded_image = imagecreatefromjpeg($tmpFileName);
                    break;
                case 'image/png':
                    $uploaded_image = imagecreatefrompng($tmpFileName);
                    break;
                case 'image/gif':
                    $uploaded_image = imagecreatefromgif($tmpFileName);
                    break;
                default:
                    die('Unsupported image format');
            }

            // Copy and resize the uploaded image to the new image
            imagecopyresampled($new_image, $uploaded_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            // Save the resized image to the target location
            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    imagejpeg($new_image, $target_file, 30); // Reduced quality for smaller file size
                    break;
                case 'image/png':
                    imagepng($new_image, $target_file, 9); // Increased compression level
                    break;
                case 'image/gif':
                    imagegif($new_image, $target_file);
                    break;
            }


            // Free up memory
            imagedestroy($new_image);
            imagedestroy($uploaded_image);
        } catch (Exception $ex) {
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
        }
        return $db_file;
    }

    public static function checkGirlCanSendMessages($chatID)
    {
        $dbInstance = db::getInstance();
        $lastTwoMessagesSql = "SELECT * FROM wp_whatsapp_messages WHERE wp_whatsapp_chats_id=? order by id desc LIMIT 2";
        $lastTwoMessagesParamsArr = [$chatID];
        $lastTwoMessages = R::getAll($lastTwoMessagesSql, $lastTwoMessagesParamsArr);
        $canSend = true;
        $girlSendCount = 0;
        foreach ($lastTwoMessages as $message) {
            if ($message['girl_send'] == 1) { // girl can send only 2 messages at a time
                if ($message['message_type'] == 3) { // chat link
                    $getUserOpenLinkChatTimeUseByMessageID = model_helper::getUserOpenLinkChatTimeUseByMessageID($message['id']);
                    if ($getUserOpenLinkChatTimeUseByMessageID == false) {
                        $girlSendCount++;
                    }
                } else {
                    $girlSendCount++;
                }
            }
        }
        if ($girlSendCount == 2) {
            $canSend = false;
        }
        if (!empty($lastTwoMessages)) {
            // Get the last element of the array
            $lastMessage = $lastTwoMessages[0];
            if ($lastMessage['message_type'] == 4) { // if welcome, girl can't send until the user send
                $canSend = false;
            }
        }
        return $canSend;
    }

    public static function getUserUniqueNumberForWhatsapp($user_id)
    {
        $dbInstance = db::getInstance();
        $user = R::getRow('SELECT * FROM wp_users WHERE ID=?', [$user_id]);
        if ($user) {
            $unique_user_code = $user['unique_user_code'];
            $userMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user_id, "nickname"]);
            return "UserID->" . $unique_user_code . " || Nickname-> " . $userMetaName["meta_value"] . ":\r\n\r\n";
        } else {
            return "";
        }
    }

    public static function isCurrentHourInRange($startHour, $endHour)
    {
        $startTime = strtotime($startHour);
        $endTime = strtotime($endHour);
        // Get current time
        $currentHour = date("H:i");
        $currentTime = strtotime($currentHour);
        // Check if range spans over midnight
        if ($endTime < $startTime) {
            // Range spans over midnight
            if ($currentTime > $startTime || $currentTime < $endTime) {
                return true; // Current time is within the range
            }
        } else {
            // Range does not span over midnight
            if ($currentTime >= $startTime && $currentTime <= $endTime) {
                return true; // Current time is within the range
            }
        }

        return false; // Current time is not within the range
    }

    public static function isImageUploaded($fileTmpName)
    {
        $validImageTypes = ['image/gif', 'image/jpeg', 'image/png', 'image/webp', 'image/bmp'];
        // Get the MIME type of the uploaded file
        $fileMimeType = mime_content_type($fileTmpName);
        return in_array($fileMimeType, $validImageTypes);
    }
    public static function isVideoUploaded($fileTmpName)
    {
        $videoMimeTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/mpeg', 'video/avi', 'video/quicktime'];
        // Get the MIME type of the uploaded file
        $fileMimeType = mime_content_type($fileTmpName);
        return in_array($fileMimeType, $videoMimeTypes);
    }

    public static function isAudioUploaded($fileTmpName)
    {
        $audioMimeTypes = ['audio/mpeg', 'audio/mp3'];
        // Get the MIME type of the uploaded file
        $fileMimeType = mime_content_type($fileTmpName);
        return in_array($fileMimeType, $audioMimeTypes);
    }

    public static function checkMessageForShortCode($message)
    {
        try {
            $dbInstance = db::getInstance();
            $chat_short_codes = R::getAll('SELECT * FROM chat_short_codes');
            foreach ($chat_short_codes as $chat_short_code) {
                $short_codes = explode(",", $chat_short_code['array_of_keywords']);
                foreach ($short_codes as $short_code) {
                    if (str_contains(strtolower(trim($message)), strtolower(trim($short_code)))) {
                        $response = new stdClass();
                        $response->action = $chat_short_code['action'];
                        $response->code = $short_code;
                        return $response;
                    }
                }
            }
            return "";
        } catch (Exception $ex) {
            errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
            return "";
        }
    }
}
