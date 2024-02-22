<?php

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $_SERVER['DOCUMENT_ROOT']);
}
require_once(PROJECT_ROOT . '/code/webscoket.php');

class websocketHelpers
{
    private static $instance = null;
    private $pdo;
    private $mysqli;
    private $host;
    private $easy_wordpress_user;
    private $easy_wordpress_password;
    private $easy_wordpress_database;

    private function __construct()
    {
        try {
            $this->host = '127.0.0.1';
            $this->easy_wordpress_user = 'wp_cam.afikim.pro';
            $this->easy_wordpress_password = 'pqOewg';
            $this->easy_wordpress_database = 'wp_MM_cam_afikim_pro';
            $charset = 'utf8mb4'; // Defaulting to utf8mb4 if not defined elsewhere
            $dsn = "mysql:host={$this->host};dbname={$this->easy_wordpress_database};charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            // Create the PDO connection
            $this->pdo = new PDO($dsn, $this->easy_wordpress_user, $this->easy_wordpress_password, $options);

            // Create the mysqli connection
            $this->mysqli = new mysqli($this->host, $this->easy_wordpress_user, $this->easy_wordpress_password, $this->easy_wordpress_database);
        } catch (Exception $e) {
            // Handle any connection error
            die("DB Connection failed: " . $e->getMessage());
        }
    }


    public static function getInstance()
    {
        if (self::$instance == null) {
            echo "37";
            self::$instance = new websocketHelpers();
        }
        return self::$instance;
    }

    // Optionally, you can add a method to close the database connection
    public function closeConnection()
    {
        $this->pdo = null;
    }

    public static function build_user_object($user_guid, $room_guid)
    {
        $roomData = new roomData();
        $roomData->room_guid = $room_guid;
        $roomData->user_guid = $user_guid;
        $instance = self::getInstance();
        $sql2 = "SELECT session_status  FROM chat_time_use WHERE room_guid = ?";
        $stmt2 = $instance->pdo->prepare($sql2);
        $stmt2->execute([$room_guid]);
        $sessionStatus = $stmt2->fetchColumn();


        if ($sessionStatus == 1) {
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
        } else {
            return null;
        }
    }
    // function mysqli()
    // {

    //     return new mysqli($this->host, $this->easy_wordpress_user, $this->easy_wordpress_password, $this->easy_wordpress_database);
    // }
    public static function getTimeLeft($user_num)
    {
        $dbInstance = self::getInstance();
        $mysqli = $dbInstance->mysqli;
        $tt = $tt1 = $tt2 = 0;
        $query1 = "SELECT COALESCE(SUM(time_expire) * 60, 0) AS tt FROM card_cam WHERE user_id = ?";
        $stmt1 = $mysqli->prepare($query1);
        $stmt1->bind_param("i", $user_num);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        if ($result1->num_rows > 0) {
            $row = $result1->fetch_assoc();
            $tt = $row['tt'];
        }
        $stmt1->close();


        $query2 = "SELECT COALESCE(SUM(time_use), 0) AS tt1 FROM chat_time_use WHERE user_id = ?";
        $stmt2 = $mysqli->prepare($query2);
        $stmt2->bind_param("i", $user_num);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2->num_rows > 0) {
            $row = $result2->fetch_assoc();
            $tt1 = $row['tt1'];
        }
        $stmt2->close();


        $tt3 = $tt - ($tt1 + $tt2);
        //$mysqli->close();
        return $tt3;
    }

    public static function getSessionStatus(roomData $roomData)
    {
        $dbInstance = self::getInstance();
        $mysqli = $dbInstance->mysqli;
        $query1 = "SELECT now() as sqlTime,session_status,user_last_refresh,model_last_refresh FROM chat_time_use WHERE room_guid = ?";
        $stmt1 = $mysqli->prepare($query1);
        $stmt1->bind_param("s", $roomData->room_guid);
        $stmt1->execute();
        $result1 = $stmt1->get_result();
        $stmt1->close();
        $row = $result1->fetch_assoc();
        if($roomData->is_model == 0)
        {
            if(($row["sqlTime"]-$row["model_last_refresh"])>10){
                return 1;
            }
            else{
                return $row["session_status"];
            };
        }
        else{//model
            if(($row["sqlTime"]-$row["user_last_refresh"])>10){
                return 1;
            }
            else{
                return $row["session_status"];
            };
        }
        //return $row["session_status"];
    }

    public static function update_chat_time_use(roomData $roomData)
    {
        echo "update\n";
        try {
            $instance = self::getInstance();
            // Update 'datein' and 'user_enter_chat' if conditions are met
            $sql2="";
            if($roomData->is_model == 0){
                $sql2 = "UPDATE chat_time_use SET model_last_refresh= NOW(),dateout = NOW(),time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()) WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
            }
            else{
                $sql2 = "UPDATE chat_time_use SET user_last_refresh= NOW(),dateout = NOW(),time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()) WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";

            }

            $sql2 = "UPDATE chat_time_use SET dateout = NOW(),time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()) WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
            $stmt2 = $instance->pdo->prepare($sql2);
            $stmt2->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
            $stmt2->execute();
            echo "update_chat_time_use: " . $roomData->user_id . " " . $roomData->room_guid . "\n";
            $timeLeft = self::getTimeLeft($roomData->user_id);
            if ($timeLeft <= 30) {
                echo $timeLeft . " sec left\n";
                return "30 sec";
            } else if ($timeLeft <= 0) {
                echo "0 sec left\n";
                return self::closeChat($roomData->room_guid, $roomData->user_id);
            } else {
                return "ok";
            }

        } catch (PDOException $e) {
            // Handle database error
            echo "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
            return "Database error";
        }
    }

    public static function userEnterChat(roomData $roomData)
    {
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

    public static function closeChat($room_guid, $user_id)
    {
        echo "closeChat\n"; // Updated for clarity
        try {
            $instance = self::getInstance();
            $sql3 = "UPDATE chat_time_use SET dateout = NOW(), time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()), session_status = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
            $stmt3 = $instance->pdo->prepare($sql3);
            $stmt3->bindParam(':room_guid', $room_guid, PDO::PARAM_STR);
            $stmt3->execute();
            //echo "UPDATE chat_time_use SET dateout = NOW(), time_use=DATEDIFF(NOW(), dateIn), session_status = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_id = :user_id";
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
