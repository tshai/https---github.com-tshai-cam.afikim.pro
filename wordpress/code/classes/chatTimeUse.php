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
    public static function insertChatTimeUse($girl_num, $user_guid, $sessionMinTime, $pricePerMinute, $totalPrice, $discount) {
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
   
}



?>
