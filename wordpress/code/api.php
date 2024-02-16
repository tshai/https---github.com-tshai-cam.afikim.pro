<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');

$helpersInstance = new helpers();
$logsInstance = new Logs();
$errorsInstance = new errors();
$dbInstance = db::getInstance();
$current_user = wp_get_current_user();
$q_type = $_REQUEST['q_type'];
if ($q_type == "start_chat") {
    $time_left = ChatTimeUse::getTimeLeft($current_user->ID);
    if ($time_left > 20) {
        $model_guid = $_REQUEST['model_guid'];
        $dbInstance = db::getInstance();
        $modelUserDb = R::load('wp_users', $model_guid);
        $modelUser = json_decode($modelUserDb, true);
        $whatsapp = new whatsapp($modelUser['whatsapp_instance_id']);
        // send message to user
        $newMessageToUser = "hello from girl " . $modelUser['user_login'];
        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$current_user->ID, "xoo_ml_phone_display"]);
        $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $newMessageToUser);
        whatsapp::insertMessageToWhatsApp($newMessageToUser, 0, $current_user->ID, $modelUser['ID'], 1);
        //send message to model
        $newMessageToGirl = "hello from user " . $current_user->user_login;
        $girlMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "xoo_ml_phone_display"]);
        $whatsappResGirl = $whatsapp->sendMessage($girlMetaPhone["meta_value"], $newMessageToGirl);
        whatsapp::insertMessageToWhatsApp($newMessageToGirl, 0, $current_user->ID, $modelUser['ID'], 0);
        ChatTimeUse::insertChatTimeUseMessage($modelUser['ID'], $current_user->ID, 0, 0);
        echo "ok";
    } else {
        echo 'not_enough_time';
    }
    die();
} else if ($q_type == "models_list") {
    $models = [
        [
            "name" => "John Doe",
            "description" => "Software Engineer",
            "guid" => 73,
            "phone" => "+972535316742",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ]
    ];

    echo json_encode($models);
    die();
} else if ($q_type == "models_chats") {
    $model_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $chatsSql = "SELECT * FROM wp_whatsapp_chats WHERE girl_num=? order by last_time_update desc";
    $chats = R::getAll($chatsSql, [$model_user->ID]);
    $chatsResponse = [];
    foreach ($chats as $chat) {
        $dateString = $chat['last_time_update'];
        $parsedDate = new DateTime($dateString);
        $currentDate = new DateTime(); // Current date and time
        if ($parsedDate->format('d/m/y') === $currentDate->format('d/m/y')) {
            $cardNew['last_time_update'] = $parsedDate->format('H:i');
        } else {
            $cardNew['last_time_update'] = $parsedDate->format('d/m/y');
        }
        $cardNew['room_id'] = $chat['room_id'];
        $cardNew['name'] = $chat['user_num'];
        $cardNew['is_read'] = $chat['girl_read'];
        $cardNew['newest_message'] = $chat['newest_message_cut'];
        $cardNew['image'] = '/wp-content/uploads/2024/02/user.png';
        $chatsResponse[] = $cardNew;
    }
    echo json_encode($chatsResponse);
    die();
} else if ($q_type == "models_single_chat") {
    $model_user = wp_get_current_user();
    $room_id = $_REQUEST['room_id'];
    $dbInstance = db::getInstance();
    $chatsSql = "SELECT * FROM wp_whatsapp_chats WHERE room_id=? LIMIT 1";
    $chat = R::getAll($chatsSql, [$room_id])[0];
    $user = R::getRow("SELECT * FROM wp_users WHERE ID = :value", [':value' => $chat["user_num"]]);
    $messagesSql = "SELECT * FROM wp_whatsapp_messages WHERE wp_whatsapp_chats_id=? order by id desc LIMIT 100";
    $messages = R::getAll($messagesSql, [$chat['ID']]);
    $messagesResponse = [];
    foreach ($messages as $message) {
        $dateString = $message['date_in'];
        $parsedDate = new DateTime($dateString);
        $currentDate = new DateTime(); // Current date and time
        if ($parsedDate->format('d/m/y') === $currentDate->format('d/m/y')) {
            $cardNew['date_in'] = $parsedDate->format('H:i');
        } else {
            $cardNew['date_in'] = $parsedDate->format('d/m/y');
        }
        $cardNew['message'] = $message['message'];
        $cardNew['message_type'] = $message['message_type'];
        $cardNew['girl_send'] = $message['girl_send'];
        $cardNew['file_name'] = $message['file_name'];
        $messagesResponse[] = $cardNew;
    }
    $response = new stdClass();
    $response->messages = $messagesResponse;
    $response->name = $user['ID'];
    $response->user_guid = $user['user_guid'];
    echo json_encode($response);
    die();
} else if ($q_type == "create_chat_room_user") {
    $model_user = wp_get_current_user();
    $room_guid = $_POST['room_guid'];
    $other_user_guid = $_POST['other_user_guid'];
    $user = R::getRow("SELECT * FROM wp_users WHERE user_guid = :value", [':value' => $other_user_guid]);
    $other_user_url = "https://cam.afikim.pro/video-chat?room_guid=" . $room_guid . "&other_user_guid=" . $other_user_guid
        . "&user_type=customer&user_guid=" . $model_user->user_guid;
    $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "xoo_ml_phone_display"]);
    $whatsapp = new whatsapp($model_user->whatsapp_instance_id);
//    $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $other_user_url);
//    whatsapp::insertMessageToWhatsApp($other_user_url, 3, $user["ID"], $model_user->ID, 1);
    echo '{"res":"' . $other_user_url . '"}';
    die();
} else if ($q_type == "send_message_to_user") {
    $model_user = wp_get_current_user();
    $room_id = $_POST['room_id'];
    $message = $_POST['message'];
    $dbInstance = db::getInstance();
    $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE room_id = :value1 AND girl_num = :value2", [':value1' => $room_id, ':value2' => $model_user->ID]);
    $messageText = htmlspecialchars($message);
    $whatsapp = new whatsapp($model_user->whatsapp_instance_id);
    $customerUserMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$message_room['user_num'], "xoo_ml_phone_display"]);
    if (!empty($_FILES)) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $model_user->ID . "/" . $room_id . "/";
        $extension = pathinfo($_FILES["file"]['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid() . '.' . $extension;
        $target_file = $target_dir . $new_file_name;

// Check if the upload folder exists, if not create it
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
        $whatsappRes = $whatsapp->sendFile($target_file, $messageText, $new_file_name, $customerUserMetaPhone["meta_value"]);
        whatsapp::insertMessageToWhatsApp($messageText, 1, $message_room["user_num"], $model_user->ID, 1, $new_file_name);
    } else {
        $whatsappRes = $whatsapp->sendMessage($customerUserMetaPhone["meta_value"], $messageText);
        whatsapp::insertMessageToWhatsApp($messageText, 0, $message_room["user_num"], $model_user->ID, 1);
    }

    echo '{"res":"ok"}';
    die();
} else if ($q_type == "log_out") {
    wp_logout(); // WordPress function to log out the current user
    // Redirect to the home page or login page after logging out
    wp_redirect(home_url());
    exit;
}

