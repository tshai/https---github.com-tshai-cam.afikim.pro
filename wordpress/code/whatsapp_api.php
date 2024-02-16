<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
$q_type = $_REQUEST['q_type'];
if ($q_type == "send_message") {
    try {
        $requestBody = file_get_contents('php://input');
        $jsonObject = json_decode($requestBody);
        $phoneExtract = $jsonObject->data->data->messages[0]->key->remoteJid;
        $senderPhone = htmlspecialchars($phoneExtract); // user that send message
        if ($senderPhone != '') {
            $senderPhone = '+' . explode('@', $senderPhone)[0];
            $whereClause = array(
                "meta_value" => $senderPhone
            );
            $userMeta = helpers::selectFromTableByColumn("wp_usermeta", $whereClause)[0];
            if (empty($userMeta)) {
                whatsapp::insertMessageToWhatsAppAll($requestBody, 0);
            } else {
                $instance_id = strtoupper($jsonObject->instance_id); // girl whatsapp instance id
                $whatsapp = new whatsapp($instance_id);
                $dbRes = whatsapp::insertMessageToWhatsAppAll($requestBody, $userMeta["user_id"]);
                $canContinue = true;
                $dataEvent = $jsonObject->data->event;
                if (!empty($dataEvent)) {
                    if (strtolower($dataEvent) !== "messages.upsert") {
                        $canContinue = false;
                    }
                } else {
                    $canContinue = false;
                }
                if ($canContinue) {
                    if ($jsonObject->data->data->messages[0]->key->fromMe === true) {
                        $canContinue = false;
                    }
                }
                $user = R::getRow("SELECT * FROM wp_users WHERE ID = :value", [':value' => $userMeta["user_id"]]);
                if ($canContinue) {
                    // check if the sender is not model
                    if ($user["is_model"]) {
                        $canContinue = false;
                        $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Cannot send messages here");
                    }
                }
                if ($canContinue) {
                    $time_left = ChatTimeUse::getTimeLeft($user['ID']);
                    if ($time_left < 20) {
                        $canContinue = false;
                        $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Not enough time to send message");
                    }
                }
                if ($canContinue) {
                    $documentCaption = whatsapp::getValueFromJsonNested(
                        "data.data.messages[0].message.documentWithCaptionMessage.message.documentMessage",
                        "data.data.messages[0].message.documentMessage",
                        "data.data.messages[0].message.imageMessage",
                        $jsonObject
                    );
                    if (empty($documentCaption)) {
                        // The $documentCaption is empty
                        $modelUser = R::getRow('SELECT * FROM wp_users WHERE whatsapp_instance_id = ? LIMIT 1', [$instance_id]);
                        $modelUserMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "xoo_ml_phone_display"]);
                        $dbInstance = db::getInstance();
                        $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $userMeta["user_id"]]);
                        $room_id = $message_room['room_id'];
                        $messageText = htmlspecialchars(whatsapp::extraxtMessageFromJson($jsonObject));
                        $messageTextFullUrl = "New message from user: " . $user["ID"] . ". Click to answer -> https://cam.afikim.pro/model-chats/?room_id=" . $room_id;
                        $whatsappRes = $whatsapp->sendMessage($modelUserMetaPhone["meta_value"], $messageTextFullUrl);
                        whatsapp::insertMessageToWhatsApp($messageText, 0, $userMeta["user_id"], $modelUser['ID'], 0);
                        ChatTimeUse::insertChatTimeUseMessage($modelUser['ID'], $user['ID'], 0, 0);
                    } else {
                        // The $documentCaption is NOT empty
                    }
                }
            }
        }
        echo json_encode('ok');
        die();
    } catch (Exception $ex) {
        errors::addError($ex->getMessage(), "whatsapp_api.php->send_message");
    }
} else {
    echo json_encode('ok');
    die();
}
