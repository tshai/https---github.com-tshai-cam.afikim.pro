<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://mifgashim.net/; style-src 'self' https://mifgashim.net/;");

$helpersInstance = new helpers();
$logsInstance = new Logs();
$errorsInstance = new errors();
$dbInstance = db::getInstance();
$current_user = wp_get_current_user();
$q_type = filter_var($_REQUEST['q_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($q_type == "start_chat") {
    if ($current_user->ID == 0) {
        echo 'noSession';
        die();
    } else if ($current_user->is_model) {
        echo "הודעת מערכת : אין אפשרות ליצור קשר עם בחורות אחרות";
        die();
    }
    $model_guid = filter_var($_REQUEST['model_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dbInstance = db::getInstance();
    $modelUser = R::getRow('SELECT * FROM wp_users WHERE user_guid = ? LIMIT 1', [$model_guid]);
    $time_left = ChatTimeUse::getTimeLeft($current_user->ID);
    $userBlocked = model_helper::getGirlBlockUser($modelUser['ID'], $current_user->ID);
    if ($time_left < 20) {
        echo "noTimeLeft";
    } else if ($userBlocked) {
        echo 'הודעת מערכת: בחורה זו חסמה אותך. לא חוייבת עבור פעולה זו.';
    } else {
        $welcomeMessageWasSendToday = whatsapp::checkWelcomeMessageWasSendToday($current_user->ID, $modelUser['ID']);
        if ($welcomeMessageWasSendToday == false) {
            $whatsapp = new whatsapp($modelUser['whatsapp_instance_id']);
            // send message to user
            $modelMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "nickname"]);
            $newMessageToUser = " שלום מ " . $modelMetaName['meta_value'] . " אתה יכול להתחיל לשוחח עם הבחורה כאן. אם ברצונך לחזור לרשימה לחץ כאן"
                . " -> https://mifgashim.net/";
            $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$current_user->ID, "phone"]);
            $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $newMessageToUser);
            whatsapp::insertMessageToWhatsApp($newMessageToUser, 4, $current_user->ID, $modelUser['ID'], 1);
            //send message to model
            $userMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$current_user->ID, "nickname"]);
            $newMessageToGirl = "שלום ממשתמש " . $userMetaName['meta_value'];
            $girlMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "phone"]);
            $whatsappResGirl = $whatsapp->sendMessage($girlMetaPhone["meta_value"], $newMessageToGirl);
            whatsapp::insertMessageToWhatsApp($newMessageToGirl, 4, $current_user->ID, $modelUser['ID'], 0);
            // ChatTimeUse::insertChatTimeUseMessage($modelUser['ID'], $current_user->ID, 0, 0);
            echo "ok";
        } else {
            echo "הודעת מערכת: כבר לחצת על בחורה זו היום. אין אפשרות ללחוץ פעמיים באותו היום";
        }
    }
    die();
} else if ($q_type == "models_list") {
    $current_user = wp_get_current_user();
    $selectQA = "";
    if ($current_user->ID != 0 && $current_user->is_qa) {
        $selectQA = " or is_qa=1";
    }
    $dbInstance = db::getInstance();
    $modelsSql = "SELECT * FROM wp_users WHERE is_model=? and admin_approve=1 and (is_qa=0 or is_qa is null" . $selectQA . ") order by ID desc";
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
        $cardNew['description'] =  (strlen($description) > 100 ? substr($description, 0, 100) . "..." : $description);
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
    echo json_encode($modelResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
        $filteredByUserId = R::getAll("SELECT * FROM wp_usermeta where user_id = :value1", [':value1' => $user['ID']]);
        $cardNew['name'] = model_helper::getMetaValue("nickname", $filteredByUserId);
        $cardNew['user_guid'] = $user['user_guid'];
        $cardNew['is_read'] = $chat['girl_read'];
        $cardNew['newest_message'] = htmlspecialchars_decode($chat['newest_message_cut']);
        $cardNew['newest_message_type'] = $chat['newest_message_type'];
        $hasImage = model_helper::getMetaValue("user_avatar", $filteredByUserId);
        $cardNew['image'] = $hasImage == "" ? "/wp-content/uploads/2024/02/user.png" : "/wp-content" . $hasImage;
        $chatsResponse[] = $cardNew;
    }
    echo json_encode($chatsResponse);
    die();
} else if ($q_type == "models_single_chat") {
    $model_user = wp_get_current_user();
    $room_id = filter_var($_REQUEST['room_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $dbInstance = db::getInstance();
    $other_user_guid = filter_var($_REQUEST['user_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $user = R::getRow("SELECT * FROM wp_users WHERE user_guid = :value", [':value' => $other_user_guid]);
    $chatsSql = "SELECT * FROM wp_whatsapp_chats WHERE room_id=? and girl_num=? and user_num=? LIMIT 1";
    $chatList = R::getAll($chatsSql, [$room_id, $model_user->ID, $user['ID']]);
    if (empty($chatList)) {
        echo "{\"error\":\"Chat room not found\"}";
        die();
    }
    $chat = $chatList[0];
    $offset = filter_var($_REQUEST['offset'], FILTER_SANITIZE_NUMBER_INT);
    $check_for_new = filter_var($_REQUEST['check_for_new'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($offset)) {
        $offset = 0;
    }
    $paramsArr = [];
    if ($check_for_new == "0") {
        $messagesSql = "SELECT * FROM wp_whatsapp_messages WHERE wp_whatsapp_chats_id=? order by id desc LIMIT 500 OFFSET ?";
        $paramsArr = [$chat['ID'], $offset];
    } else {
        $messagesSql = "SELECT * FROM wp_whatsapp_messages WHERE wp_whatsapp_chats_id=? and date_in>? order by id desc LIMIT 500 OFFSET ?";
        $simplifiedDateTime = preg_replace('/GMT.*$/', '', $check_for_new);
        $dateTime = new DateTime($simplifiedDateTime);
        $formattedDateTime = $dateTime->format('Y-m-d H:i:s');
        $paramsArr = [$chat['ID'], $formattedDateTime, $offset];
    }
    $messages = R::getAll($messagesSql, $paramsArr);
    $checkGirlCanSendMessages = model_helper::checkGirlCanSendMessages($chat['ID']);
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
        $cardNew['index'] = $message['id'];
        $cardNew['file_name'] = "/wp-content/uploads/models_chats/" . $model_user->ID . "/" . $room_id . "/" . $message['file_name'];
        $messagesResponse[] = $cardNew;
    }
    $response = new stdClass();
    $response->messages = $messagesResponse;
    $filteredByUserId = R::getAll("SELECT * FROM wp_usermeta where user_id = :value1", [':value1' => $user['ID']]);
    $response->name = model_helper::getMetaValue("nickname", $filteredByUserId);
    $response->user_guid = $user['user_guid'];
    $response->user_blocked = model_helper::getGirlBlockUser($model_user->ID, $user['ID']);
    $response->checkGirlCanSendMessages = $checkGirlCanSendMessages;
    echo json_encode($response);
    die();
} else if ($q_type == "create_chat_room_user") {
    $model_user = wp_get_current_user();
    $room_guid = filter_var($_POST['room_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $other_user_guid = filter_var($_POST['other_user_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $user = R::getRow("SELECT * FROM wp_users WHERE user_guid = :value", [':value' => $other_user_guid]);
    $other_user_url = "https://mifgashim.net/video-chat-customer?room_guid=" . $room_guid . "&user_type=customer&user_guid=" . $other_user_guid;
    $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
    $whatsapp = new whatsapp($model_user->whatsapp_instance_id);
    $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $other_user_url);
    whatsapp::insertMessageToWhatsApp($other_user_url, 3, $user["ID"], $model_user->ID, 1);
    echo '{"res":"' . $other_user_url . '"}';
    die();
} else if ($q_type == "send_message_to_user") {
    $model_user = wp_get_current_user();
    $room_id = filter_var($_POST['room_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $messageText = (filter_var($_POST['message'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $originalMessage = htmlspecialchars_decode($messageText);
    $dbInstance = db::getInstance();
    $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE room_id = :value1 AND girl_num = :value2", [':value1' => $room_id, ':value2' => $model_user->ID]);
    $whatsapp = new whatsapp($model_user->whatsapp_instance_id);
    $customerUserMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$message_room['user_num'], "phone"]);
    $inserted_id;
    if (!empty($_FILES)) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $model_user->ID . "/" . $room_id . "/";
        $extension = pathinfo($_FILES["file"]['name'], PATHINFO_EXTENSION);
        $new_file_name = uniqid() . '.' . $extension;
        $target_file = $target_dir . $new_file_name;
        // Check if the upload folder exists, if not create it
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $messageType = 0;
        if (model_helper::isImageUploaded($_FILES["file"]['tmp_name'])) {
            $messageType = 1;
        } else if (model_helper::isVideoUploaded($_FILES["file"]['tmp_name'])) {
            $messageType = 2;
        }
        if ($messageType == 0) {
            echo "{\"error\":\"file type not supported\"}";
            die();
        } else {
            move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
            $file_website_url = whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" . $model_user->ID . "/" . $room_id . "/" . $new_file_name;
            $whatsappRes = $whatsapp->sendFile($file_website_url, $originalMessage, $new_file_name, $customerUserMetaPhone["meta_value"]);
            $inserted_id = whatsapp::insertMessageToWhatsApp($messageText, $messageType, $message_room["user_num"], $model_user->ID, 1, $new_file_name);
        }
    } else {
        $whatsappRes = $whatsapp->sendMessage($customerUserMetaPhone["meta_value"], $originalMessage);
        $inserted_id = whatsapp::insertMessageToWhatsApp($messageText, 0, $message_room["user_num"], $model_user->ID, 1);
    }
    $message = R::getRow("SELECT * FROM wp_whatsapp_messages WHERE id = :value1", [':value1' => $inserted_id]);
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
    $cardNew['index'] = $message['id'];
    $cardNew['file_name'] = "/wp-content/uploads/models_chats/" . $model_user->ID . "/" . $room_id . "/" . $message['file_name'];
    echo json_encode($cardNew);
    die();
} else if ($q_type == "user_statistic") {
    $customer_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $action = filter_var($_REQUEST['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $draw = filter_var($_REQUEST['draw'], FILTER_SANITIZE_NUMBER_INT);
    $count = filter_var($_REQUEST['length'], FILTER_SANITIZE_NUMBER_INT);
    $skip = filter_var($_REQUEST['start'], FILTER_SANITIZE_NUMBER_INT);
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
    $startDate = filter_var($_REQUEST['startDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $endDate = filter_var($_REQUEST['endDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $draw =  filter_var($_REQUEST['draw'], FILTER_SANITIZE_NUMBER_INT);
    $count = filter_var($_REQUEST['length'], FILTER_SANITIZE_NUMBER_INT);
    $skip = filter_var($_REQUEST['start'], FILTER_SANITIZE_NUMBER_INT);
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
        $hasError =  $_FILES["image"]["error"];
        if ($hasError == 1) {
            echo 'הקובץ גדול מדי';
        } else {
            try {
                $current_user = wp_get_current_user();
                $dbInstance = db::getInstance();
                $action = filter_var($_REQUEST['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $new_file_name = uniqid() . '.png';
                if ($action == "avatar") {
                    $db_file = model_helper::resizeImage($_FILES["image"]["tmp_name"],  $new_file_name);
                    model_helper::insertOrUpdateUserAvatar($db_file, $current_user->ID);
                    echo 'האווטר עודכן בהצלחה';
                } else if ($action == "gallery") {
                    $db_file = model_helper::resizeImage($_FILES["image"]["tmp_name"],  $new_file_name);
                    model_helper::insertModelImageToGallery($new_file_name, $current_user->ID);
                    echo 'הקובץ הועלה בהצלחה';
                }
            } catch (Exception $ex) {
                errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
            }
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
        $cardNew['index'] = $image['ID'];
        $images[] = $cardNew;
    }

    echo json_encode($images);
    die();
} else if ($q_type == "delete_image_gallery") {
    $current_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $imageID = $_GET['id'];
    $image = R::getRow("SELECT * FROM models_gallery where ID = :value1 and girl_num = :value2", [':value1' => $imageID, ':value2' =>  $current_user->ID]);
    if ($image) {
        // Remove a file from the system
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content";
        $sub_dir = "/uploads/files/";
        $new_file_name =   $image['image_name'];
        $file =  $target_dir . $sub_dir . $new_file_name;
        if (file_exists($file)) {
            if (unlink($file)) {
                R::exec("DELETE FROM models_gallery WHERE ID = :value1 and girl_num=:value2", [':value1' => $imageID, ':value2' =>  $current_user->ID]);
                echo "הקובץ נמחק בהצלחה.";
            } else {
                echo "אין אפשרות למחוק את הקובץ.";
            }
        } else {
            echo "הקובץ לא נמצא.";
        }
    }
    die();
} else if ($q_type == "view_model_profile") {
    $any_user = wp_get_current_user();
    $model_guid = "";
    if (isset($_REQUEST['model_guid']) && $_REQUEST['model_guid'] != "") {
        $model_guid = filter_var($_REQUEST['model_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    } else {
        $model_guid = $any_user->user_guid;
    }
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
    $hasImage = model_helper::getMetaValue("user_avatar", $filteredByUserId);
    $cardNew['image'] = $hasImage == "" ? "/wp-content/uploads/2024/02/user.png" : "/wp-content" . $hasImage;
    $cardNew['name'] = model_helper::getMetaValue("nickname", $filteredByUserId);
    $cardNew['description'] =  model_helper::getMetaValue("description", $filteredByUserId);
    $cardNew['guid'] = $modelUser["user_guid"];
    $cardNew['phone'] = model_helper::getMetaValue("virtual_phone", $filteredByUserId);
    $cardNew['age'] = model_helper::calculateAge(model_helper::getMetaValue("age", $filteredByUserId));
    $cardNew['online_from'] = model_helper::getMetaValue("online_from", $filteredByUserId);
    $cardNew['online_to'] = model_helper::getMetaValue("online_to", $filteredByUserId);
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
} else if ($q_type == "block_user") {
    $model_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $user_guid = filter_var($_REQUEST['user_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $room_id = filter_var($_REQUEST['room_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $user = R::getRow('SELECT * FROM wp_users WHERE user_guid = ? LIMIT 1', [$user_guid]);
    $chat = R::getRow('SELECT * FROM wp_whatsapp_chats WHERE room_id=? LIMIT 1', [$room_id]);
    if ($user['ID'] == $chat["user_num"] && $model_user->ID == $chat['girl_num']) {
        model_helper::girlBlockUser($model_user->ID, $user['ID']);
    }
    echo "ok";
    die();
} else if ($q_type == "unblock_user") {
    $model_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $user_guid = filter_var($_REQUEST['user_guid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $room_id = filter_var($_REQUEST['room_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $user = R::getRow('SELECT * FROM wp_users WHERE user_guid = ? LIMIT 1', [$user_guid]);
    $chat = R::getRow('SELECT * FROM wp_whatsapp_chats WHERE room_id=? LIMIT 1', [$room_id]);
    if ($user['ID'] == $chat["user_num"] && $model_user->ID == $chat['girl_num']) {
        $updateSql = "UPDATE black_list SET blocked_status=? WHERE girl_num=? AND user_id=?";
        $updateParams = [0, $model_user->ID, $user['ID']];
        R::exec($updateSql, $updateParams);
    }
    echo "ok";
    die();
} else if ($q_type == "log_out") {
    wp_logout(); // WordPress function to log out the current user
    // Redirect to the home page or login page after logging out
    wp_redirect(home_url());
    exit;
}
