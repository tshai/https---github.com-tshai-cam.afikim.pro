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
            websocketHelpers::writeToLog($e->getMessage());
            die("DB Connection failed: " . $e->getMessage());
        }
    }


    public static function getInstance()
    {
        if (self::$instance == null) {
            //  echo "37";
            self::$instance = new websocketHelpers();
        }
        return self::$instance;
    }

    public static function writeToLog($message)
    {
        $log_file =  PROJECT_ROOT . '/code/websocket_logs.txt';
        $logMessage = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
        file_put_contents($log_file, $logMessage, FILE_APPEND | LOCK_EX);
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
        $roomData->user_start_chat = 0;
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

        //  echo "Time buy: " . $tt . " Time use: " . $tt1 . "\n";
        $tt3 = $tt - ($tt1 + $tt2);
        //$mysqli->close();
        // echo "Time left: " . $tt3 . "\n";
        return $tt3;
    }

    public static function getSessionStatus(roomData $roomData)
    {
        $dbInstance = self::getInstance();
        $mysqli = $dbInstance->mysqli;
        $query = "SELECT now() as sqlTime, user_enter_chat, session_status, user_last_refresh, model_last_refresh 
                  FROM chat_time_use 
                  WHERE room_guid = ?";

        if ($stmt = $mysqli->prepare($query)) {
            $stmt->bind_param("s", $roomData->room_guid);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $stmt->close();

                if ($row = $result->fetch_assoc()) {
                    // Assuming user_enter_chat is a flag (0 or 1)
                    if ($row["user_enter_chat"] == 1) {
                        $sqlTime = strtotime($row["sqlTime"]);
                        $modelLastRefresh = strtotime($row["model_last_refresh"]);
                        $userLastRefresh = strtotime($row["user_last_refresh"]);

                        // Determine which last_refresh time to compare based on is_model flag
                        $lastRefresh = $roomData->is_model == 0 ? $modelLastRefresh : $userLastRefresh;

                        // Compare and return based on the 10-second rule
                        if ($sqlTime - $lastRefresh > 1000) {
                            // echo "Session timeout detected\n";
                            return 1;
                        } else {
                            //  echo "Session active\n";
                            return $row["session_status"];
                        }
                    } else {
                        if ($row["session_status"] == 1) {
                            return 1;
                        } else {
                            echo "User has not entered the chat\n";
                            return 0; // Adjust based on your logic
                        }
                    }
                } else {
                    //  echo "No matching records found for room_guid: " . $roomData->room_guid . "\n";
                    return 0; // Adjust based on your logic
                }
            } else {
                $stmt->close();
                //  echo "Query execution failed\n";
                return 0; // Adjust based on your logic
            }
        } else {
            //echo "Query preparation failed\n";
            return 0; // Adjust based on your logic
        }
    }

    public static function update_chat_time_use(roomData $roomData, $clients)
    {
        // echo "update\n";
        try {
            $instance = self::getInstance();
            // Update 'datein' and 'user_enter_chat' if conditions are met
            $sql2 = "";
            if ($roomData->is_model == 1) {
                $sql2 = "UPDATE chat_time_use SET model_last_refresh= NOW(),dateout = NOW(),time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()) WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
                $sql3 = "UPDATE chat_time_use SET model_last_refresh= NOW() WHERE room_guid = :room_guid AND session_status = 0";
                $stmt3 = $instance->pdo->prepare($sql3);
                $stmt3->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
                $stmt3->execute();
            } else {
                $sql2 = "UPDATE chat_time_use SET user_last_refresh= NOW(),dateout = NOW(),time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()) WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
            }


            //$sql2 = "UPDATE chat_time_use SET dateout = NOW(),time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()) WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
            $stmt2 = $instance->pdo->prepare($sql2);
            $stmt2->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
            $stmt2->execute();
            $response = new stdClass();
            // echo "update_chat_time_use: " . $roomData->user_id . " " . $roomData->room_guid . "\n";
            // echo "roomData " . $roomData->is_model . "\n";
            if ($roomData->is_model == 1) { // in case is model we loop all users in room and check if the customer has time
                foreach ($clients as $client) {
                    // Retrieve stored roomData for this client
                    $clientData = $clients->offsetGet($client);
                    // Check if this client is in the same room and not the sender
                    //   echo "clientData " . $clientData->is_model . "\n";
                    if ($clientData->room_guid == $roomData->room_guid && $clientData->is_model == 0) {
                        $timeLeft = self::getTimeLeft($clientData->user_id);
                        if ($timeLeft <= 0) {
                            // echo "0 sec left\n";
                            $response->answer = 0;
                        } else if ($timeLeft <= 30) {
                            // echo $timeLeft . " sec left\n";
                            $response->answer = 1;
                        } else {
                            $response->answer = 2;
                        }
                        $response->time_left = $timeLeft;
                    }
                }
            } else {
                $timeLeft = self::getTimeLeft($roomData->user_id);
                if ($timeLeft <= 0) {
                    // echo "0 sec left\n";
                    $response->answer = 0;
                } else if ($timeLeft <= 30) {
                    //  echo $timeLeft . " sec left\n";
                    $response->answer = 1;
                } else {
                    $response->answer = 2;
                }
                $response->time_left = $timeLeft;
            }
            return $response;
        } catch (PDOException $e) {
            // Handle database error
            echo "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
            websocketHelpers::writeToLog($e->getMessage());
            return "Database error";
        }
    }

    public static function userEnterChat(roomData $roomData)
    {
        // echo "userEnterChat\n";
        try {
            $instance = self::getInstance();
            if ($roomData->is_model == 0) {
                // Update 'datein' and 'user_enter_chat' if conditions are met
                $sql2 = "UPDATE chat_time_use SET datein = NOW(),user_last_refresh= NOW(),dateout = NOW(), user_enter_chat = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 0";
                $stmt2 = $instance->pdo->prepare($sql2);
                $stmt2->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
                $stmt2->execute();

                // Update 'dateout' regardless of 'user_enter_chat' status
                $sql3 = "UPDATE chat_time_use SET dateout = NOW() WHERE room_guid = :room_guid AND session_status = 0";
                $stmt3 = $instance->pdo->prepare($sql3);
                $stmt3->bindParam(':room_guid', $roomData->room_guid, PDO::PARAM_STR);
                $stmt3->execute();

                //  echo "User entered chat: " . $roomData->user_id . " " . $roomData->room_guid . "\n";
            }

            return "ok";
        } catch (PDOException $e) {
            // Handle database error
            error_log("Database error: " . $e->getMessage());
            websocketHelpers::writeToLog($e->getMessage());
            return "Database error";
        }
    }

    public static function closeChat($room_guid, $user_id)
    {
        // echo "closeChat\n"; // Updated for clarity
        try {
            $instance = self::getInstance();
            $sql3 = "UPDATE chat_time_use SET dateout = NOW(), time_use = TIMESTAMPDIFF(SECOND, dateIn, NOW()), session_status = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_enter_chat = 1";
            $stmt3 = $instance->pdo->prepare($sql3);
            $stmt3->bindParam(':room_guid', $room_guid, PDO::PARAM_STR);
            $stmt3->execute();

            $sql4 = "UPDATE chat_time_use SET session_status = 1 WHERE room_guid = :room_guid AND session_status = 0";
            $stmt4 = $instance->pdo->prepare($sql4);
            $stmt4->bindParam(':room_guid', $room_guid, PDO::PARAM_STR);
            $stmt4->execute();
            //echo "UPDATE chat_time_use SET dateout = NOW(), time_use=DATEDIFF(NOW(), dateIn), session_status = 1 WHERE room_guid = :room_guid AND session_status = 0 AND user_id = :user_id";
            // echo "Chat closed for user: " . $user_id . " in room: " . $room_guid . "\n";
            return "ok";
        } catch (PDOException $e) {
            //  echo "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
            websocketHelpers::writeToLog($e->getMessage());
            return "Database error"; // Consider a more structured error response
        }
    }


    public static function send_message($clients, $senderData, $message, $sameUser)
    { //1 =same user, 0=other user,2 =all users in room
        foreach ($clients as $client) {
            // Retrieve stored roomData for this client
            $clientData = $clients->offsetGet($client);
            // Check if this client is in the same room and not the sender
            if ($sameUser == 1) { // send to same user
                if ($clientData->room_guid == $senderData->room_guid && $clientData->user_guid == $senderData->user_guid) {
                    $client->send($message);
                    // echo $message;
                }
            } else if ($sameUser == 0) { // send to other user
                if ($clientData->room_guid == $senderData->room_guid && $clientData->user_guid != $senderData->user_guid) {
                    $client->send($message);
                    // echo $message;
                }
            } else { // send to all users in room
                if ($clientData->room_guid == $senderData->room_guid) {
                    $client->send($message);
                    //  echo $message;
                }
            }
        }
    }
    public static function countUsersInRoom($clients, $senderData, $room_data)
    {
        $count_how_many_same_user_in_room = 0;
        $count_how_many_total_members_in_room = 0;

        foreach ($clients as $client) {
            // Retrieve stored data for this client
            $clientData = $clients->offsetGet($client);
            // echo "clientData " . $clientData->room_guid . "\n";
            //  echo "room_data " . $room_data->room_guid . "\n";

            if ($clientData->room_guid == $senderData->room_guid) {
                $count_how_many_total_members_in_room++;
            }

            if ($clientData->room_guid == $room_data->room_guid && $clientData->user_guid == $room_data->user_guid) {
                // Assuming you're checking for both user_guid and room_guid similarity
                $count_how_many_same_user_in_room++;
            }
        }

        return [
            'same_user_count' => $count_how_many_same_user_in_room,
            'total_member_count' => $count_how_many_total_members_in_room
        ];
    }
    public static function updateSenderData($senderData, $room_data)
    {
        $senderData->user_id = $room_data->user_id;
        $senderData->user_guid = $room_data->user_guid;
        $senderData->room_guid = $room_data->room_guid;
        $senderData->user_type = $room_data->user_type;
        $senderData->is_model = $room_data->is_model;
        $senderData->last_date = date('Y-m-d H:i:s');
    }
    public static function updateRoomData($room_data, $senderData)
    {
        $room_data->user_id = $senderData->user_id;
        $room_data->user_guid = $senderData->user_guid;
        $room_data->room_guid = $senderData->room_guid;
        $room_data->user_type = $senderData->user_type;
        $room_data->is_model = $senderData->is_model;
    }
}
class roomData
{
    public $room_guid;
    public $user_id;
    public $user_type;
    public $user_guid;
    public $is_model;
    public $action;
    public $user_start_chat;
}
