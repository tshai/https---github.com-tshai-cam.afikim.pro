<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
$q_type = filter_var($_REQUEST['q_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($q_type == "send_message") {
    try {
        $requestBody = file_get_contents('php://input');
        errors::addError($requestBody, "whatsapp_api.php->send_message");
        $canContinue = true;
        $jsonObject = json_decode($requestBody, true);
        $dataEvent = filter_var($jsonObject['data']['event'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($dataEvent)) {
            if (strtolower($dataEvent) !== "messages.upsert") {
                $canContinue = false;
            }
        } else {
            $canContinue = false;
        }
        if ($canContinue) {
            $phoneExtract = filter_var($jsonObject['data']['data']['messages'][0]['key']['remoteJid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($phoneExtract != null) {
                $senderPhone = htmlspecialchars($phoneExtract); // user that send message
                if ($senderPhone != '') {
                    $dbInstance = db::getInstance();
                    $senderPhone = explode('@', $senderPhone)[0];
                    $userMeta = R::getRow("SELECT * FROM wp_usermeta WHERE meta_value = :value", [':value' => $senderPhone]);

                    if (empty($userMeta)) {
                        whatsapp::insertMessageToWhatsAppAll($requestBody, 25);
                    } else {
                        $instance_id = strtoupper(filter_var($jsonObject['instance_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)); // girl whatsapp instance id
                        $whatsapp = new whatsapp($instance_id);
                        $dbRes = whatsapp::insertMessageToWhatsAppAll($requestBody, $userMeta["user_id"]);

                        if ($canContinue) {
                            if ($jsonObject['data']['data']['messages'][0]['key']['fromMe'] === true) {
                                $canContinue = false;
                            }
                        }
                        $user = R::getRow("SELECT * FROM wp_users WHERE ID = :value", [':value' => $userMeta["user_id"]]);
                        $modelUser = R::getRow('SELECT * FROM wp_users WHERE whatsapp_instance_id = ? LIMIT 1', [$instance_id]);
                        if ($canContinue) {
                            // check if the sender is not model
                            if ($user["is_model"]) {
                                $canContinue = false;
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Cannot send messages here");
                            }
                        }
                        if ($canContinue) {
                            // check if user has time to send message
                            $time_left = ChatTimeUse::getTimeLeft($user['ID']);
                            if ($time_left < 20) {
                                $canContinue = false;
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Not enough time to send message");
                            }
                        }
                        if ($canContinue) {
                            // check if user is blocked by model
                            $userBlocked = model_helper::getGirlBlockUser($modelUser['ID'], $user['ID']);
                            if ($userBlocked) {
                                $canContinue = false;
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Cannot send to this model. You are not charged for this message");
                            }
                        }
                        if ($canContinue) {
                            $pathCaptionMessage = ['data', 'data', 'messages', 0, 'message', 'documentWithCaptionMessage', 'message', 'documentMessage'];
                            $documentWithCaptionMessage = whatsapp::getNestedArrayValue($jsonObject, $pathCaptionMessage);
                            $pathImageMessage = ['data', 'data', 'messages', 0, 'message', 'imageMessage'];
                            $imageMessage = whatsapp::getNestedArrayValue($jsonObject, $pathImageMessage);
                            $pathDocumentMessage = ['data', 'data', 'messages', 0, 'message', 'documentMessage'];
                            $documentMessage = whatsapp::getNestedArrayValue($jsonObject, $pathDocumentMessage);
                            $pathVideoMessage = ['data', 'data', 'messages', 0, 'message', 'videoMessage'];
                            $videoMessage = whatsapp::getNestedArrayValue($jsonObject, $pathVideoMessage);

                            $modelUserMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "phone"]);
                            $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $userMeta["user_id"]]);
                            $room_id = $message_room['room_id'];
                            $userMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "nickname"]);

                            $acceptMessage = false;
                            if (empty($imageMessage) && empty($videoMessage) && empty($documentWithCaptionMessage) && empty($documentMessage)) {
                                // The $documentCaption is empty                               
                                $messageText = htmlspecialchars(whatsapp::extraxtMessageFromJson($jsonObject));
                                whatsapp::insertMessageToWhatsApp($messageText, 0, $userMeta["user_id"], $modelUser['ID'], 0);
                                $acceptMessage = true;
                            } else {
                                $image_type_if_document = 0;
                                if (empty($documentMessage) == false) {
                                    $mimetype = $documentMessage['mimetype'];
                                    if (stripos($mimetype, "image") !== false) {
                                        $imageMessage = $documentMessage;
                                        $image_type_if_document = 4;
                                    } else if (stripos($mimetype, "video") !== false) {
                                        $videoMessage = $documentMessage;
                                    }
                                } else if (empty($documentWithCaptionMessage) == false) {
                                    $mimetype = $documentWithCaptionMessage['mimetype'];
                                    if (stripos($mimetype, "image") !== false) {
                                        $image_type_if_document = 4;
                                        $imageMessage = $documentWithCaptionMessage;
                                    } else if (stripos($mimetype, "video") !== false) {
                                        $videoMessage = $documentWithCaptionMessage;
                                    }
                                }
                                if (empty($imageMessage) == false) {
                                    $imageUrl = $imageMessage['url'];
                                    $mediaKey = $imageMessage['mediaKey'];
                                    $mimetype = $imageMessage['mimetype'];
                                    $caption = whatsapp::getNestedArrayValue($imageMessage, ['caption']);
                                    if (empty($caption)) {
                                        $caption = "";
                                    }
                                    $extension = explode('/', $mimetype)[1];
                                    $base64Content = whatsapp::decryptWhatsappImage($imageUrl, $mediaKey, ($image_type_if_document == 0 ? 1 : $image_type_if_document), $extension);
                                    // $base64Content = explode(',', $base64Image)[1];
                                    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                    // Check if the upload folder exists, if not create it
                                    if (!file_exists($target_dir)) {
                                        mkdir($target_dir, 0777, true);
                                    }
                                    $new_file_name = uniqid() . '.' . $extension;
                                    $target_file = $target_dir . $new_file_name;
                                    whatsapp::createFileFromBase64($base64Content, $target_file);
                                    whatsapp::insertMessageToWhatsApp($caption, 1, $userMeta["user_id"], $modelUser['ID'], 0, $new_file_name);
                                    $acceptMessage = true;
                                } else if (empty($videoMessage) == false) {
                                    $videoUrl = $videoMessage['url'];
                                    $mediaKey = $videoMessage['mediaKey'];
                                    $mimetype = $videoMessage['mimetype'];
                                    $extension = explode('/', $mimetype)[1];
                                    $caption = whatsapp::getNestedArrayValue($videoMessage, ['caption']);
                                    if (empty($caption)) {
                                        $caption = "";
                                    }
                                    $base64Content = whatsapp::decryptWhatsappImage($videoUrl, $mediaKey, 2, $extension);
                                    // $base64Content = explode(',', $base64Image)[1];
                                    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                    // Check if the upload folder exists, if not create it
                                    if (!file_exists($target_dir)) {
                                        mkdir($target_dir, 0777, true);
                                    }
                                    $new_file_name = uniqid() . '.' . $extension;
                                    $target_file = $target_dir . $new_file_name;
                                    whatsapp::createFileFromBase64($base64Content, $target_file);
                                    whatsapp::insertMessageToWhatsApp($caption, 2, $userMeta["user_id"], $modelUser['ID'], 0, $new_file_name);
                                    $acceptMessage = true;
                                }
                            }
                            if ($acceptMessage) { // send message to model
                                $modelUserFromWorking = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "online_from"]);
                                $modelUserToWorking = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "online_to"]);
                                $isTimeInRangeToSendMessage = model_helper::isCurrentHourInRange($modelUserFromWorking['meta_value'], $modelUserToWorking['meta_value']);
                                if ($isTimeInRangeToSendMessage) {
                                    $messageTextFullUrl = "New message from user: " .  $userMetaName['meta_value'] . ". Click to answer -> https://cam.afikim.pro/model-chats/?room_id=" . $room_id;
                                    $whatsappRes = $whatsapp->sendMessage($modelUserMetaPhone["meta_value"], $messageTextFullUrl);
                                } else {
                                    $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Model is not available now. She will be available from " . $modelUserFromWorking['meta_value'] . " to " . $modelUserToWorking['meta_value'] . ". Please, wait for her to respond.");
                                }
                                ChatTimeUse::insertChatTimeUseMessage($modelUser['ID'], $user['ID'], 0, 0);
                            } else {
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "System message: Unsuported message type. You are not charged for this message");
                            }
                        }
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
