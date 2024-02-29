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
            return $avatar["meta_value"];
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
}
