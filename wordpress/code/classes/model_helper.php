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

    public static function getMetaValue($meta_key, $filteredByUserId)
    {
        $avatar = current(array_filter($filteredByUserId, function ($item) use ($meta_key) {
            return $item['meta_key'] === $meta_key;
        }));
        return $avatar["meta_value"];
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
}
