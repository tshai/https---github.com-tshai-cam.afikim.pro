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
        $modelUser = R::getRow('SELECT * FROM wp_users WHERE user_guid = ? LIMIT 1', [$model_guid]);
        errors::addError(json_encode($modelUser), "20");
        $whatsapp = new whatsapp($modelUser['whatsapp_instance_id']);
        // send message to user
        $modelMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "nickname"]);
        $newMessageToUser = "hello from girl " . $modelMetaName['meta_value'];
        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$current_user->ID, "phone"]);
        $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $newMessageToUser);
        whatsapp::insertMessageToWhatsApp($newMessageToUser, 0, $current_user->ID, $modelUser['ID'], 1);
        //send message to model
        $newMessageToGirl = "hello from user " . $current_user->user_login;
        $girlMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "virtual_phone"]);
        $whatsappResGirl = $whatsapp->sendMessage($girlMetaPhone["meta_value"], $newMessageToGirl);
        whatsapp::insertMessageToWhatsApp($newMessageToGirl, 0, $current_user->ID, $modelUser['ID'], 0);
        ChatTimeUse::insertChatTimeUseMessage($modelUser['ID'], $current_user->ID, 0, 0);
        echo "ok";
    } else {
        echo 'not_enough_time';
    }
    die();
} else if ($q_type == "models_list") {
    $dbInstance = db::getInstance();
    $modelsSql = "SELECT * FROM wp_users WHERE is_model=? order by ID desc";
    $models = R::getAll($modelsSql, [1]);
    $modelIds = array_map(function ($model) {
        return $model['ID'];
    }, $models);
    $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
    $modelsMetaSql = "SELECT * FROM wp_usermeta WHERE user_id IN ($placeholders)";
    $modelsMeta = R::getAll($modelsMetaSql, $modelIds);
    $modelResponse = [];
    foreach ($models as $model) {
        $user_id = $model['ID'];
        $filteredByUserId = array_filter($modelsMeta, function ($item) use ($user_id) {
            return $item['user_id'] === $user_id;
        });
        $cardNew['image'] = "/wp-content" . model_helper::getMetaValue("user_avatar", $filteredByUserId);
        $cardNew['name'] = model_helper::getMetaValue("nickname", $filteredByUserId);
        $description = model_helper::getMetaValue("description", $filteredByUserId);
        $cardNew['description'] = (strlen($description) > 100 ? substr($description, 0, 100) . "..." : $description);
        $cardNew['guid'] = $model["user_guid"];
        $cardNew['phone'] = model_helper::getMetaValue("virtual_phone", $filteredByUserId);
        // $cardNew['age'] = model_helper::calculateAge(model_helper::getMetaValue("age", $filteredByUserId));
        $languages = model_helper::getMetaValue("languages", $filteredByUserId);
        $languagesArr = [];
        if (model_helper::determineStringType($languages) == "serialized") {
            $languagesArr = unserialize($languages);
        } else {
            $languagesArr[] = $languages;
        }
        $languagesArrRes = [];
        foreach ($languagesArr as $lang) {
            $langNew['image'] = model_helper::returnFlag($lang);
            $langNew['name'] = $lang;
            $languagesArrRes[] = $langNew;
        }

        $cardNew['languages'] = $languagesArrRes;
        $modelResponse[] = $cardNew;
    }
    echo json_encode($modelResponse);
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
        $user = R::getRow("SELECT * FROM wp_users WHERE ID = :value", [':value' => $chat["user_num"]]);
        $cardNew['name'] = $chat['user_num'];
        $cardNew['user_guid'] = $user['user_guid'];
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
        $cardNew['file_name'] = "/wp-content/uploads/models_chats/" . $model_user->ID . "/" . $room_id . "/" . $message['file_name'];
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
    $other_user_url = "https://cam.afikim.pro/video-chat-customer?room_guid=" . $room_guid . "&user_type=customer&user_guid=" . $other_user_guid;
    $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
    $whatsapp = new whatsapp($model_user->whatsapp_instance_id);
    $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $other_user_url);
    whatsapp::insertMessageToWhatsApp($other_user_url, 3, $user["ID"], $model_user->ID, 1);
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
    $customerUserMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$message_room['user_num'], "phone"]);
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
} else if ($q_type == "user_statistic") {
    $customer_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $action = $_REQUEST['action'];
    $draw = $_REQUEST["draw"];
    $count = $_REQUEST["length"];
    $skip = $_REQUEST["start"];
    if ($action == "payments") {
        $payments = R::getAll("SELECT * FROM card_cam WHERE time_expire <> 0.00 AND user_id = :value1 AND user_ask_to_delete = 0 AND TIMESTAMPDIFF(MONTH, order_day, CURDATE()) < 6 ORDER BY ID DESC", [':value1' => $customer_user->ID]);
        $paymentsRes = [];
        foreach ($payments as $payment) {
            $dateString = $payment['order_day'];
            $parsedDate = new DateTime($dateString);
            $cardNew['date_in'] = $parsedDate->format('d/m/y H:i');
            $cardNew['price'] = $payment['price'];
            $cardNew['ipaddress'] = $payment['ipaddress'];
            $cardNew['time_expire'] = $payment['time_expire'];
            $cardNew['lastdigits'] = $payment['lastdigits'];
            $cardNew['transactionProcessor'] = $payment['transactionProcessor'];
            $paymentsRes[] = $cardNew;
        }
        $countRows = count($paymentsRes);
        $paymentsRes = array_slice($paymentsRes, $skip, $count);
        $responseClss = new stdClass();
        $responseClss->data = $paymentsRes;
        $responseClss->draw = $draw;
        $responseClss->recordsTotal = $countRows;
        $responseClss->recordsFiltered = $countRows;
        echo json_encode($responseClss);
    } else if ($action == "chats") {
        $chat_time_uses = R::getAll("SELECT * FROM chat_time_use where user_id=:value1 and user_ask_to_delete=0 and time_use<>0 order by ID desc", [':value1' => $customer_user->ID]);
        $chat_time_use_res = [];
        foreach ($chat_time_uses as $chat_time_use) {
            $dateInString = $chat_time_use['datein'];
            $parsedDateIn = new DateTime($dateInString);
            $cardNew['date_in'] = $parsedDateIn->format('d/m/y H:i:s');
            if ($chat_time_use['send_message'] == "0") {
                $dateOutString = $chat_time_use['dateout'];
                $parsedDateOut = new DateTime($dateOutString);
                $cardNew['date_out'] = $parsedDateOut->format('d/m/y H:i:s');
            } else {
                $cardNew['date_out'] = "N/A";
            }
            $cardNew['time_use'] = $chat_time_use['time_use'];
            $modelUserMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$chat_time_use['girl_num'], "nickname"]);
            $cardNew['modelName'] = $modelUserMetaName['meta_value'];
            $send_message = $chat_time_use["send_message"];
            $cardNew['chat_type'] = model_helper::getChatTipe($chat_time_use['send_message']);
            $chat_time_use_res[] = $cardNew;
        }

        $countRows2 = count($chat_time_use_res);
        $chat_time_use_res = array_slice($chat_time_use_res, $skip, $count);
        $responseClss = new stdClass();
        $responseClss->data = $chat_time_use_res;
        $responseClss->draw = $draw;
        $responseClss->recordsTotal = $countRows2;
        $responseClss->recordsFiltered = $countRows2;
        echo json_encode($responseClss);
    }
    die();
} else if ($q_type == "model_statistic") {
    $modelUser = wp_get_current_user();
    $dbInstance = db::getInstance();
    $startDate = $_REQUEST['startDate'];
    $endDate = $_REQUEST['endDate'];
    $draw = $_REQUEST["draw"];
    $count = $_REQUEST["length"];
    $skip = $_REQUEST["start"];
    $totalTimeUseInSeconds = R::getRow("SELECT IFNULL(SUM(time_use), 0) AS time_use FROM chat_time_use WHERE girl_num = :value1 AND datein >= :value2 AND datein <= DATE_ADD(:value3, INTERVAL 1 DAY)", [':value1' => $modelUser->ID, ':value2' => $startDate, ':value3' => $endDate]);
    $chat_time_uses = R::getAll("SELECT * FROM chat_time_use where time_use>0 and girl_num = :value1 AND datein >= :value2 AND datein <= DATE_ADD(:value3, INTERVAL 1 DAY) order by ID desc", [':value1' => $modelUser->ID, ':value2' => $startDate, ':value3' => $endDate]);
    $chat_time_use_res = [];
    foreach ($chat_time_uses as $chat_time_use) {
        $dateInString = $chat_time_use['datein'];
        $parsedDateIn = new DateTime($dateInString);
        $cardNew['date_in'] = $parsedDateIn->format('d/m/y H:i:s');
        if ($chat_time_use['send_message'] == "0") {
            $dateOutString = $chat_time_use['dateout'];
            $parsedDateOut = new DateTime($dateOutString);
            $cardNew['date_out'] = $parsedDateOut->format('d/m/y H:i:s');
        } else {
            $cardNew['date_out'] = "N/A";
        }
        $cardNew['time_use'] = $chat_time_use['time_use'];
        $userMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$chat_time_use['user_id'], "nickname"]);
        $cardNew['user_name'] = $userMetaName['meta_value'];
        $send_message = $chat_time_use["send_message"];
        $cardNew['chat_type'] = model_helper::getChatTipe($chat_time_use['send_message']);
        $chat_time_use_res[] = $cardNew;
    }

    $countRows2 = count($chat_time_use_res);
    $chat_time_use_res = array_slice($chat_time_use_res, $skip, $count);
    $responseClss = new stdClass();
    $responseClss->totalTimeUseInSeconds = $totalTimeUseInSeconds;
    $responseClss->data = $chat_time_use_res;
    $responseClss->draw = $draw;
    $responseClss->recordsTotal = $countRows2;
    $responseClss->recordsFiltered = $countRows2;
    echo json_encode($responseClss);
    die();
} else if ($q_type == "upload_image_profile") {
    if (!empty($_FILES)) {
        $current_user = wp_get_current_user();
        $dbInstance = db::getInstance();
        $action = $_REQUEST['action'];
        if ($action == "avatar") {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content";
            $sub_dir = "/uploads/files/";
            $new_file_name = uniqid() . '.png';
            $target_file = $target_dir . $sub_dir . $new_file_name;
            $db_file = $sub_dir . $new_file_name;
            move_uploaded_file($_FILES["avatar_image"]["tmp_name"], $target_file);
            model_helper::insertOrUpdateUserAvatar($db_file, $current_user->ID);
            echo 'avatar update successfully';
        } else if ($action == "gallery") {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content";
            $sub_dir = "/uploads/files/";
            $new_file_name = uniqid() . '.png';
            $target_file = $target_dir . $sub_dir . $new_file_name;
            $db_file = $sub_dir . $new_file_name;
            move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
            model_helper::insertModelImageToGallery($new_file_name, $current_user->ID);
            echo 'image upload successfully';
        }
    }
    die();
} else if ($q_type == "list_images_gallery") {
    $current_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $imagesDb = R::getAll("SELECT * FROM models_gallery where girl_num = :value1 order by ID desc", [':value1' => $current_user->ID]);
    $images = [];
    foreach ($imagesDb as $image) {
        $cardNew['image_name'] = "/wp-content/uploads/files/" . $image['image_name'];
        $images[] = $cardNew;
    }

    echo json_encode($images);
    die();
} else if ($q_type == "view_model_profile") {
    $model_guid = $_REQUEST['model_guid'];
    $dbInstance = db::getInstance();
    $modelUser = R::getRow('SELECT * FROM wp_users WHERE user_guid = ? LIMIT 1', [$model_guid]);
    $imagesDb = R::getAll("SELECT * FROM models_gallery where girl_num = :value1 order by ID desc", [':value1' => $modelUser['ID']]);
    $filteredByUserId = R::getAll("SELECT * FROM wp_usermeta where user_id = :value1", [':value1' => $modelUser['ID']]);
    $images = [];
    foreach ($imagesDb as $image) {
        $imageNew['image_name'] = "/wp-content/uploads/files/" . $image['image_name'];
        $images[] = $imageNew;
    }
    $cardNew['gallery_images'] = $images;
    $cardNew['image'] = "/wp-content" . model_helper::getMetaValue("user_avatar", $filteredByUserId);
    $cardNew['name'] = model_helper::getMetaValue("nickname", $filteredByUserId);
    $cardNew['description'] =  model_helper::getMetaValue("description", $filteredByUserId);
    $cardNew['guid'] = $modelUser["user_guid"];
    $cardNew['phone'] = model_helper::getMetaValue("virtual_phone", $filteredByUserId);
    $cardNew['age'] = model_helper::calculateAge(model_helper::getMetaValue("age", $filteredByUserId));
    $languages = model_helper::getMetaValue("languages", $filteredByUserId);
    $languagesArr = [];
    if (model_helper::determineStringType($languages) == "serialized") {
        $languagesArr = unserialize($languages);
    } else {
        $languagesArr[] = $languages;
    }
    $languagesArrRes = [];
    foreach ($languagesArr as $lang) {
        $langNew['image'] = model_helper::returnFlag($lang);
        $langNew['name'] = $lang;
        $languagesArrRes[] = $langNew;
    }

    $cardNew['languages'] = $languagesArrRes;

    echo json_encode($cardNew);
    die();
} else if ($q_type == "log_out") {
    wp_logout(); // WordPress function to log out the current user
    // Redirect to the home page or login page after logging out
    wp_redirect(home_url());
    exit;
}
