<?php

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
    function insertChatTimeUse($girl_num, $user_id, $sessionMinTime, $pricePerMinute, $totalPrice, $discount) {
        $dbInstance = db::getInstance();
        $pdo = $dbInstance->getPdo();
    
        $sql = "INSERT INTO chat_time_use (time_use, datein, dateout, girl_num, rnd_num, user_id, session_status, user_ask_to_delete, user_validate, send_message, multiply_sum, end_error, sessionMinTime, pricePerMinute, totalPrice, discount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $pdo->prepare($sql);
        $rnd_num=helpers::generateRandomString(10);
        $stmt->execute([0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $girl_num,$rnd_num , $user_id, 0, 0, 0, 0, 1, "", $sessionMinTime, $pricePerMinute, $totalPrice, $discount]);
        return $rnd_num;
    }
    
}

?>
