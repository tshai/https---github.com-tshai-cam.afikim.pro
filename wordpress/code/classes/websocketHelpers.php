<?php

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $_SERVER['DOCUMENT_ROOT']);
}
require_once(PROJECT_ROOT . '/code/webscoket.php');

class websocketHelpers {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            // Retrieve database credentials from wp-config.php
            $host = '127.0.0.1';
            $easy_wordpress_user = 'wp_cam.afikim.pro';
            $easy_wordpress_password =  'pqOewg';
            $easy_wordpress_database = 'wp_MM_cam_afikim_pro';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $dsn = "mysql:host=$host;dbname=$easy_wordpress_database;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            echo "26";
            // Create the PDO connection
            $this->pdo = new PDO($dsn, $easy_wordpress_user, $easy_wordpress_password, $options);
        } catch (PDOException $e) {
            // Handle any connection error
            die("DB Connection failed: " . $e->getMessage(). " " . $dsn . " " . $easy_wordpress_user . " " . $easy_wordpress_password . " " . $options . " " );
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            echo "37";
            self::$instance = new websocketHelpers();
        }
        return self::$instance;
    }

    // Optionally, you can add a method to close the database connection
    public function closeConnection() {
        $this->pdo = null;
    }

    public static function build_user_object($user_guid, $room_guid){
        $roomData=new roomData();
        $roomData->room_guid=$room_guid;
        $roomData->user_guid=$user_guid;
        $instance = self::getInstance();
        $sql2 = "SELECT session_status  FROM chat_time_use WHERE room_guid = ?";
        $stmt2 = $instance->pdo->prepare($sql2);
        $stmt2->execute([$room_guid]);
        $sessionStatus = $stmt2->fetchColumn();


        if($sessionStatus == 1){
            return null;
        };
        // Check if the user is a model
        $sql1 = "SELECT is_model, ID FROM wp_users WHERE user_guid = ?";
        $stmt1 = $instance->pdo->prepare($sql1);
        $stmt1->execute([$user_guid]);
        $userData = $stmt1->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            // Extract the is_model and ID values
            $roomData->is_model = $userData['is_model'];
            $roomData->user_id = $userData['ID'];
            return $roomData;
        }
        else
        {
            return null;
        }
    }

    public static function userEnterChat(roomData $roomData) {
        echo "userEnterChat\n";
        try {
            $instance = self::getInstance();
            if ($roomData->is_model == 0) {
                // Update 'datein' and 'user_enter_chat' if conditions are met
                $sql2 = "UPDATE chat_time_use SET datein = NOW(), user_enter_chat = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 0";
                $stmt2 = $instance->pdo->prepare($sql2);
                $stmt2->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
                $stmt2->execute();
    
                // Update 'dateout' regardless of 'user_enter_chat' status
                $sql3 = "UPDATE chat_time_use SET dateout = NOW() WHERE room_guid = :room_guid AND session_status = 0";
                $stmt3 = $instance->pdo->prepare($sql3);
                $stmt3->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
                $stmt3->execute();
    
                echo "User entered chat: " . $roomData->user_id . " " . $roomData->room_guid . "\n";
            }
    
            return "ok";
        } catch (PDOException $e) {
            // Handle database error
            error_log("Database error: " . $e->getMessage());
            return "Database error";
        }
    }
    public static function closeChat($room_guid,$user_id) {
        echo "closeChat\n"; // Updated for clarity
        try {
            $instance = self::getInstance();
                    $sql3 = "UPDATE chat_time_use SET dateout = NOW(), session_status = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_id = :user_id";
                    $stmt3 = $instance->pdo->prepare($sql3);
                    $stmt3->bindParam(':room_guid', $room_guid, PDO::PARAM_STR);
                    $stmt3->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt3->execute();
        
                    echo "Chat closed for user: " . $user_id . " in room: " . $room_guid . "\n";
                return "ok";
        } catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
            return "Database error"; // Consider a more structured error response
        }
    }
    
}

?>
