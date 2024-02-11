<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
$q_type = $_REQUEST['q_type'];
if ($q_type == "send_message") {
    try {
        $requestBody = file_get_contents('php://input');
        //  $requestBody = stripslashes($_REQUEST['json']);
        $jsonObject = json_decode($requestBody);
        $phoneExtract = $jsonObject->data->data->messages[0]->key->remoteJid;
        $senderPhone = htmlspecialchars($phoneExtract);
        if ($senderPhone != '') {
            $senderPhone = '+' . explode('@', $senderPhone)[0];
            $whereClause = array(
                "meta_value" => $senderPhone
            );
            $userMeta = helpers::selectFromTableByColumn("wp_usermeta", $whereClause)[0];
            if (empty($userMeta)) {
                whatsapp::insertMessageToWhatsAppAll($requestBody, 0);
            } else {
                $instance_id = strtoupper($jsonObject->instance_id);
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
                if ($canContinue) {
                    $user = R::getRow("SELECT * FROM wp_users WHERE ID = :value", [':value' => $userMeta["user_id"]]);
                    if ($user["is_model"]) {
                        $canContinue = false;
                        $whatsappRes = $whatsapp->sendMessage($senderPhone, "Cannot send messages here");
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
                        $messageText = htmlspecialchars(whatsapp::extraxtMessageFromJson($jsonObject));
                        errors::addError($messageText, "whatsapp_api.php line 54");
                        $whatsappRes = $whatsapp->sendMessage("359877364683", $messageText);
                        errors::addError(json_encode($whatsappRes), "whatsapp_api.php line 54");
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
