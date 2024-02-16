<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

class ChatTimeUse
{
    public $time_use;
    public $ID;
    public $datein;
    public $dateout;
    public $girl_num;
    public $rnd_num;
    public $user_id;
    public $session_status;
    public $user_ask_to_delete;
    public $user_validate;
    public $send_message;
    public $multiply_sum;
    public $end_error;
    public $sessionMinTime;
    public $pricePerMinute;
    public $totalPrice;
    public $discount;

    public $create_date;

    // Constructor
    public static function insertChatTimeUse($girl_num, $user_guid, $sessionMinTime, $pricePerMinute, $totalPrice, $discount)
    {
        $dbInstance = db::getInstance();
        $pdo = $dbInstance->getPdo();
        $user_id = null; // Default value if user_id is not found

        $query = "SELECT * FROM wp_users WHERE user_guid = :user_guid";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_guid', $user_guid, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if 'ID' key exists in the result array
            if (isset($user['ID'])) {
                $user_id = $user['ID'];

                // Generate random string
                $room_guid = helpers::generateRandomString(10);

                $sql = "INSERT INTO chat_time_use (create_date, time_use, datein, dateout, girl_num, room_guid, user_id, multiply_sum, end_error, sessionMinTime, pricePerMinute, totalPrice, discount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                // Preparing the statement
                $stmt = $pdo->prepare($sql);

                // Values array
                $values = [date('Y-m-d H:i:s'), 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $girl_num, $room_guid, $user_id, 1, "", $sessionMinTime, $pricePerMinute, $totalPrice, $discount];

                // Execute the SQL query
                $stmt->execute($values);

                // Display the SQL query on the screen for debugging
                // echo "Executing SQL query: " . htmlspecialchars($sql) . "<br>";
                // echo "With values: <br>";
                // print_r($values);
                // Return the generated random string
                return $room_guid;
            } else {
                // Handle case where 'ID' key is not present
                // Log a warning or handle it as appropriate for your application
                // Example: log the warning
                error_log("User ID not found in result array.");
            }
        } else {
            // Handle case where no user is found
            // Example: log the warning
            error_log("No user found with the provided user_guid.");
        }
    }

    public static function insertChatTimeUseMessage($girl_num, $user_num, $totalPrice, $discount): void
    {
        $dbInstance = db::getInstance();
        $pdo = $dbInstance->getPdo();
        $sql = "INSERT INTO chat_time_use (create_date, time_use, datein, dateout, girl_num, send_message, user_id, multiply_sum, end_error, sessionMinTime, pricePerMinute, totalPrice, discount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $values = [date('Y-m-d H:i:s'), 20, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $girl_num, 1, $user_num, 1, "", 0, 0, $totalPrice, $discount];
        $stmt->execute($values);
    }

    public static function getTimeLeft($user_num)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();
        $tt = $tt1 = $tt2 = 0;

// Query 1: Calculate tt
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

// Query 2: Calculate tt1
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

//// Query 3: Calculate tt2
//        $query3 = "SELECT COALESCE(time_use, 0) AS tt2 FROM sum_log_chat_time_use WHERE user_id = ?";
//        $stmt3 = $mysqli->prepare($query3);
//        $stmt3->bind_param("i", $user_num);
//        $stmt3->execute();
//        $result3 = $stmt3->get_result();
//        if ($result3->num_rows > 0) {
//            $row = $result3->fetch_assoc();
//            $tt2 = $row['tt2'];
//        }
//        $stmt3->close();

// Calculate tt3
        $tt3 = $tt - ($tt1 + $tt2);
        $mysqli->close();
        return $tt3;
    }
}

?>
