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
        // only event "messages.upsert" contains the message
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
                    // get the sender by phone from db
                    $userMeta = R::getRow("SELECT * FROM wp_usermeta WHERE meta_value = :value", [':value' => $senderPhone]);

                    if (empty($userMeta)) {
                        whatsapp::insertMessageToWhatsAppAll($requestBody, 25);
                    } else {
                        // instance_id is the girl vitual phone instance id from social app
                        $instance_id = strtoupper(filter_var($jsonObject['instance_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)); // girl whatsapp instance id
                        $whatsapp = new whatsapp($instance_id);
                        $dbRes = whatsapp::insertMessageToWhatsAppAll($requestBody, $userMeta["user_id"]);

                        if ($canContinue) {
                            // if message is from me (virtual phone) then do not continue
                            if ($jsonObject['data']['data']['messages'][0]['key']['fromMe'] === true) {
                                $canContinue = false;
                            }
                        }

                        $user = R::getRow("SELECT * FROM wp_users WHERE ID = :value", [':value' => $userMeta["user_id"]]);
                        $modelUser = R::getRow('SELECT * FROM wp_users WHERE whatsapp_instance_id = ? LIMIT 1', [$instance_id]);
                        $modelUserMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$modelUser['ID'], "phone"]);
                        if ($canContinue) {
                            // check if user or girl request help for short codes
                            $messageTextGlb = htmlspecialchars(whatsapp::extraxtMessageFromJson($jsonObject));
                            $short_codeGlb = model_helper::checkMessageForShortCode($messageTextGlb);
                            if ($short_codeGlb->action == "listcodes") {
                                $shortCodesContentHelp = whatsapp::getHelpShortCodeContent($user["is_model"]);
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, $shortCodesContentHelp);
                                $canContinue = false;
                            }
                            // user send gift to girl
                            else if ($short_codeGlb->action == "gift") {
                                $gift_amount = str_replace($short_codeGlb->code . "+", "",  $messageTextGlb);
                                if (is_numeric($gift_amount)) {
                                    $gift_amount_in_seconds = intval($gift_amount);
                                    $time_left = ChatTimeUse::getTimeLeft($user['ID']);
                                    if ($time_left < $gift_amount_in_seconds) {
                                        $canContinue = false;
                                        // notify the user and the girl that he has no time left
                                        $whatsappRes = $whatsapp->sendMessage($senderPhone, 'הודעת מערכת: מתנה לא נשלחה. סכום המתנה הוא יותר ממה שיש לך. ניתן לרכוש עוד זמן כאן -> https://mifgashim.net/user-payment');
                                    } else {
                                        whatsapp::insertChatTimeUseAndCheckTime($modelUser, $user, 2, $new_message_id, $gift_amount,  $whatsapp, $senderPhone, $modelUserMetaPhone["meta_value"]);
                                    }
                                } else {
                                    $whatsappRes = $whatsapp->sendMessage($senderPhone, 'הודעת מערכת: המתנה לא נשלחה. אנא ודא שאתה משתמש בתחביר הנכון. הקלד "--help" לפרטים נוספים.');
                                }

                                $canContinue = false;
                            }
                        }
                        $modelSendMessage = false;
                        if ($canContinue) {
                            // check if the sender is not model
                            if ($user["is_model"]) {
                                if ($user['ID'] != $modelUser['ID']) { // one girl write to other
                                    die();
                                }
                                $canContinue = false;
                                $modelSendMessage = true;
                                $messageWasQuoted = false;

                                // get all posible message types
                                $pathCaptionMessage = ['data', 'data', 'messages', 0, 'message', 'documentWithCaptionMessage', 'message', 'documentMessage'];
                                $documentWithCaptionMessage = whatsapp::getNestedArrayValue($jsonObject, $pathCaptionMessage);
                                $pathImageMessage = ['data', 'data', 'messages', 0, 'message', 'imageMessage'];
                                $imageMessage = whatsapp::getNestedArrayValue($jsonObject, $pathImageMessage);
                                $pathDocumentMessage = ['data', 'data', 'messages', 0, 'message', 'documentMessage'];
                                $documentMessage = whatsapp::getNestedArrayValue($jsonObject, $pathDocumentMessage);
                                $pathVideoMessage = ['data', 'data', 'messages', 0, 'message', 'videoMessage'];
                                $videoMessage = whatsapp::getNestedArrayValue($jsonObject, $pathVideoMessage);
                                $pathAudioMessage = ['data', 'data', 'messages', 0, 'message', 'audioMessage'];
                                $audioMessage = whatsapp::getNestedArrayValue($jsonObject, $pathAudioMessage);
                                $pathStickerMessage = ['data', 'data', 'messages', 0, 'message', 'stickerMessage'];
                                $stickerMessage = whatsapp::getNestedArrayValue($jsonObject, $pathStickerMessage);

                                $acceptMessage = false;
                                $messageTypeID = 0;
                                $new_message_id = 0;
                                if (empty($imageMessage) && empty($videoMessage) && empty($audioMessage) && empty($stickerMessage) && empty($documentWithCaptionMessage) && empty($documentMessage)) {
                                    // The $documentCaption is empty therefore is regular text message                             
                                    $messageText = htmlspecialchars(whatsapp::extraxtMessageFromJson($jsonObject));
                                    $short_code = model_helper::checkMessageForShortCode($messageText);

                                    // if girl reply to customer message this is the sender data
                                    $quotedMessageText = whatsapp::getCaptionFromReply(
                                        $jsonObject,
                                        ['data', 'data', 'messages', 0, 'message', 'extendedTextMessage'],
                                        $whatsapp,
                                        $senderPhone
                                    );
                                    $unique_user_code = substr($quotedMessageText, 0, 4);
                                    $user = R::getRow("SELECT * FROM wp_users WHERE unique_user_code = :value", [':value' => $unique_user_code]);
                                    $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
                                    $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $user['ID']]);
                                    $room_id = $message_room['room_id'];
                                    $acceptMessage = true;
                                    // check if the message from the girl is a short code to do some action
                                    if ($short_code->action == "") {
                                        // girl send regular message to customer
                                        $new_message_id =  whatsapp::insertMessageToWhatsApp($messageText, 0, $user['ID'], $modelUser['ID'], 1);
                                        $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $messageText);
                                        $messageTypeID = 0;
                                    } else {
                                        if ($short_code->action == "generateChat") {
                                            // if message is short code to generate chat 
                                            $time_left = ChatTimeUse::getTimeLeft($user['ID']);
                                            if ($time_left < 60) {
                                                // if less than 60 seconds left we notify both sides and NOT generate video chat
                                                $whatsappRes = $whatsapp->sendMessage($senderPhone,  model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . "הודעת מערכת: למשתמש אין מספיק זמן להתחיל שיחת וידאו. הוא גם קיבל הודעה עם קישור לתשלום.");
                                                $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], "הודעת מערכת: המבצע ניסה לשלוח לך בקשת צ'אט אך אין לך מספיק זמן להתחיל שיחת וידאו. נא לרכוש עוד זמן כאן -> https://mifgashim.net/user-payment");
                                            } else {
                                                // if enough time left we generate video chat and send ONLY to the girl at this point
                                                $user_guid = $user['user_guid'];
                                                $room_guid = ChatTimeUse::insertChatTimeUse($modelUser['ID'], $user_guid, 0, 0, 0, 0);
                                                $chatUrl = model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . "https://mifgashim.net/video-chat?room_guid=" .    $room_guid . "&other_user_guid=" .  $user_guid . "&user_type=manager&user_guid=" . $modelUser['user_guid'];
                                                $whatsappRes = $whatsapp->sendMessage($senderPhone, $chatUrl);
                                            }
                                        }
                                    }
                                } else {
                                    // if message is not regular text - we need to handle it
                                    $image_type_if_document = 0;
                                    $pathToImage = [];
                                    $pathToVideo = [];
                                    if (empty($documentMessage) == false) {
                                        $mimetype = $documentMessage['mimetype'];
                                        if (stripos($mimetype, "image") !== false) {
                                            $imageMessage = $documentMessage;
                                            $image_type_if_document = 4;
                                            $pathToImage = ['data', 'data', 'messages', 0, 'message', 'documentMessage'];
                                        } else if (stripos($mimetype, "video") !== false) {
                                            $videoMessage = $documentMessage;
                                            $pathVideoMessage = ['data', 'data', 'messages', 0, 'message', 'documentMessage'];
                                        }
                                    } else if (empty($documentWithCaptionMessage) == false) {
                                        $mimetype = $documentWithCaptionMessage['mimetype'];
                                        if (stripos($mimetype, "image") !== false) {
                                            $image_type_if_document = 4;
                                            $imageMessage = $documentWithCaptionMessage;
                                            $pathToImage = ['data', 'data', 'messages', 0, 'message', 'documentWithCaptionMessage', 'message', 'documentMessage'];
                                        } else if (stripos($mimetype, "video") !== false) {
                                            $videoMessage = $documentWithCaptionMessage;
                                            $pathToVideo = ['data', 'data', 'messages', 0, 'message', 'documentWithCaptionMessage', 'message', 'documentMessage'];
                                        }
                                    }
                                    if (empty($imageMessage) == false) {
                                        $final_path_image =  count($pathToImage) == 0 ?  ['data', 'data', 'messages', 0, 'message', 'imageMessage'] :  $pathToImage;
                                        $quotedMessageText = whatsapp::getCaptionFromReply(
                                            $jsonObject,
                                            $final_path_image,
                                            $whatsapp,
                                            $senderPhone
                                        );
                                        $unique_user_code = substr($quotedMessageText, 0, 4);
                                        $user = R::getRow("SELECT * FROM wp_users WHERE unique_user_code = :value", [':value' => $unique_user_code]);
                                        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
                                        $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $user['ID']]);
                                        $room_id = $message_room['room_id'];

                                        $imageUrl = $imageMessage['url'];
                                        $mediaKey = $imageMessage['mediaKey'];
                                        $mimetype = $imageMessage['mimetype'];
                                        $caption = whatsapp::getNestedArrayValue($imageMessage, ['caption']);
                                        if (empty($caption)) {
                                            $caption = "";
                                        }
                                        $extension = explode('/', $mimetype)[1];
                                        $base64Content = whatsapp::decryptWhatsappImage($imageUrl, $mediaKey, ($image_type_if_document == 0 ? 1 : $image_type_if_document), $extension);
                                        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                        if (!file_exists($target_dir)) {
                                            mkdir($target_dir, 0777, true);
                                        }
                                        $new_file_name = uniqid() . '.' . $extension;
                                        $target_file = $target_dir . $new_file_name;
                                        whatsapp::createFileFromBase64($base64Content, $target_file);
                                        $new_message_id = whatsapp::insertMessageToWhatsApp($caption, 1, $user['ID'], $modelUser['ID'], 1, $new_file_name);
                                        $file_website_url = whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $whatsappRes = $whatsapp->sendFile($file_website_url, $caption, $new_file_name, $userMetaPhone["meta_value"]);

                                        $messageTypeID = 1;
                                        $acceptMessage = true;
                                    } else    if (empty($stickerMessage) == false) {
                                        $quotedMessageText = whatsapp::getCaptionFromReply(
                                            $jsonObject,
                                            ['data', 'data', 'messages', 0, 'message', 'stickerMessage'],
                                            $whatsapp,
                                            $senderPhone
                                        );
                                        $unique_user_code = substr($quotedMessageText, 0, 4);
                                        $user = R::getRow("SELECT * FROM wp_users WHERE unique_user_code = :value", [':value' => $unique_user_code]);
                                        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
                                        $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $user['ID']]);
                                        $room_id = $message_room['room_id'];

                                        $imageUrl = $stickerMessage['url'];
                                        $mediaKey = $stickerMessage['mediaKey'];
                                        $mimetype = $stickerMessage['mimetype'];
                                        $caption = "";
                                        $extension = explode('/', $mimetype)[1];
                                        $base64Content = whatsapp::decryptWhatsappImage($imageUrl, $mediaKey, ($image_type_if_document == 0 ? 1 : $image_type_if_document), $extension);
                                        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                        if (!file_exists($target_dir)) {
                                            mkdir($target_dir, 0777, true);
                                        }
                                        $new_file_name = uniqid() . '.' . $extension;
                                        $target_file = $target_dir . $new_file_name;
                                        whatsapp::createFileFromBase64($base64Content, $target_file);
                                        $new_message_id = whatsapp::insertMessageToWhatsApp($caption, 1, $user['ID'], $modelUser['ID'], 1, $new_file_name);
                                        $file_website_url = whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $whatsappRes = $whatsapp->sendFile($file_website_url, $caption, $new_file_name, $userMetaPhone["meta_value"]);
                                        $messageTypeID = 1;
                                        $acceptMessage = true;
                                    } else if (empty($videoMessage) == false) {
                                        $final_path_video =  count($pathToVideo) == 0 ?  ['data', 'data', 'messages', 0, 'message', 'videoMessage'] :  $pathToVideo;
                                        $quotedMessageText = whatsapp::getCaptionFromReply(
                                            $jsonObject,
                                            $final_path_video,
                                            $whatsapp,
                                            $senderPhone
                                        );
                                        $unique_user_code = substr($quotedMessageText, 0, 4);
                                        $user = R::getRow("SELECT * FROM wp_users WHERE unique_user_code = :value", [':value' => $unique_user_code]);
                                        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
                                        $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $user['ID']]);
                                        $room_id = $message_room['room_id'];

                                        $videoUrl = $videoMessage['url'];
                                        $mediaKey = $videoMessage['mediaKey'];
                                        $mimetype = $videoMessage['mimetype'];
                                        $extension = explode('/', $mimetype)[1];
                                        $caption = whatsapp::getNestedArrayValue($videoMessage, ['caption']);
                                        if (empty($caption)) {
                                            $caption = "";
                                        }
                                        $base64Content = whatsapp::decryptWhatsappImage($videoUrl, $mediaKey, 2, $extension);
                                        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                        if (!file_exists($target_dir)) {
                                            mkdir($target_dir, 0777, true);
                                        }
                                        $new_file_name = uniqid() . '.' . $extension;
                                        $target_file = $target_dir . $new_file_name;
                                        whatsapp::createFileFromBase64($base64Content, $target_file);
                                        $new_message_id =  whatsapp::insertMessageToWhatsApp($caption, 2, $user['ID'], $modelUser['ID'], 1, $new_file_name);
                                        $file_website_url = whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $whatsappRes = $whatsapp->sendFile($file_website_url, $caption, $new_file_name, $userMetaPhone["meta_value"]);
                                        $messageTypeID = 2;
                                        $acceptMessage = true;
                                    } else if (empty($audioMessage) == false) {
                                        $quotedMessageText = whatsapp::getCaptionFromReply(
                                            $jsonObject,
                                            ['data', 'data', 'messages', 0, 'message', 'audioMessage'],
                                            $whatsapp,
                                            $senderPhone
                                        );
                                        $unique_user_code = substr($quotedMessageText, 0, 4);
                                        $user = R::getRow("SELECT * FROM wp_users WHERE unique_user_code = :value", [':value' => $unique_user_code]);
                                        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "phone"]);
                                        $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $user['ID']]);
                                        $room_id = $message_room['room_id'];

                                        $audioUrl = $audioMessage['url'];
                                        $mediaKey = $audioMessage['mediaKey'];
                                        $mimetype = $audioMessage['mimetype'];
                                        $extension = explode('/', $mimetype)[1];
                                        // Handling different MIME types to set the appropriate file extension
                                        if (strpos($mimetype, 'audio/ogg') !== false || strpos($mimetype, 'opus') !== false) {
                                            $extension = 'ogg'; // Set extension for OGG Opus files
                                        }
                                        $caption = "";
                                        $base64Content = whatsapp::decryptWhatsappImage($audioUrl, $mediaKey, 3, $extension);
                                        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                        if (!file_exists($target_dir)) {
                                            mkdir($target_dir, 0777, true);
                                        }
                                        $new_file_name = uniqid() . '.' . $extension;
                                        $target_file = $target_dir . $new_file_name;
                                        whatsapp::createFileFromBase64($base64Content, $target_file);
                                        $new_message_id =  whatsapp::insertMessageToWhatsApp($caption, 5, $user['ID'], $modelUser['ID'], 1, $new_file_name);
                                        $file_website_url = whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $audioMsgContent = "הודעת שמע. לחצו לשמוע -> " . $file_website_url;
                                        $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $audioMsgContent);
                                        $messageTypeID = 5;
                                        $acceptMessage = true;
                                    }
                                }
                                if ($acceptMessage == false) { // unsuported message type
                                    $whatsappRes = $whatsapp->sendMessage($senderPhone, "הודעת מערכת: סוג הודעה לא נתמך.");
                                }
                            }
                        }
                        if ($canContinue) {
                            // check if user has time to send message
                            $time_left = ChatTimeUse::getTimeLeft($user['ID']);
                            if ($time_left < 20) {
                                $canContinue = false;
                                // notify the user and the girl that he has no time left
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "הודעת מערכת: תם הזמן הקצוב למשתמש. אנא המתן עד שהוא ירכוש.");
                                $whatsappRes = $whatsapp->sendMessage($modelUserMetaPhone["meta_value"], model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . "הודעת מערכת: תם הזמן הקצוב למשתמש. אנא המתן עד שהוא ירכוש.");
                            }
                        }
                        if ($canContinue) {
                            // check if user is blocked by model
                            $userBlocked = model_helper::getGirlBlockUser($modelUser['ID'], $user['ID']);
                            if ($userBlocked) {
                                $canContinue = false;
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "הודעת מערכת: לא ניתן לשלוח לדגם זה. אינך מחויב עבור הודעה זו");
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
                            $pathAudioMessage = ['data', 'data', 'messages', 0, 'message', 'audioMessage'];
                            $audioMessage = whatsapp::getNestedArrayValue($jsonObject, $pathAudioMessage);
                            $pathStickerMessage = ['data', 'data', 'messages', 0, 'message', 'stickerMessage'];
                            $stickerMessage = whatsapp::getNestedArrayValue($jsonObject, $pathStickerMessage);

                            $message_room = R::getRow("SELECT * FROM wp_whatsapp_chats WHERE girl_num = :value1 AND user_num = :value2", [':value1' => $modelUser['ID'], ':value2' => $user['ID']]);
                            $room_id = $message_room['room_id'];
                            $userMetaName = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$user['ID'], "nickname"]);

                            $acceptMessage = false;
                            $messageTypeID = 0;
                            $new_message_id = 0;
                            $girlCanGetWhatsapp = whatsapp::girlCanGetWhatsapp($modelUser['ID']);
                            if (empty($imageMessage) && empty($videoMessage) && empty($audioMessage) && empty($stickerMessage) && empty($documentWithCaptionMessage) && empty($documentMessage)) {
                                // The $documentCaption is empty                               
                                $messageText = htmlspecialchars(whatsapp::extraxtMessageFromJson($jsonObject));
                                if (strlen($messageText) > 0) {
                                    $new_message_id =  whatsapp::insertMessageToWhatsApp($messageText, 0, $user['ID'], $modelUser['ID'], 0);
                                    if ($girlCanGetWhatsapp) {
                                        $whatsappRes = $whatsapp->sendMessage($modelUserMetaPhone["meta_value"], model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . $messageText);
                                    }
                                    $messageTypeID = 0;
                                    $acceptMessage = true;
                                }
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
                                    $caption = model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . whatsapp::getNestedArrayValue($imageMessage, ['caption']);
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
                                    $new_message_id = whatsapp::insertMessageToWhatsApp($caption, 1, $user['ID'], $modelUser['ID'], 0, $new_file_name);
                                    if ($girlCanGetWhatsapp) {
                                        $file_website_url = whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $whatsappRes = $whatsapp->sendFile($file_website_url, $caption, $new_file_name, $modelUserMetaPhone["meta_value"]);
                                    }
                                    $messageTypeID = 1;
                                    $acceptMessage = true;
                                } else    if (empty($stickerMessage) == false) {
                                    $imageUrl = $stickerMessage['url'];
                                    $mediaKey = $stickerMessage['mediaKey'];
                                    $mimetype = $stickerMessage['mimetype'];
                                    $caption = model_helper::getUserUniqueNumberForWhatsapp($user['ID']);
                                    $extension = "png"; //explode('/', $mimetype)[1];
                                    $base64Content = whatsapp::decryptWhatsappImage($imageUrl, $mediaKey, ($image_type_if_document == 0 ? 1 : $image_type_if_document), $extension);
                                    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                    if (!file_exists($target_dir)) {
                                        mkdir($target_dir, 0777, true);
                                    }
                                    $new_file_name = uniqid() . '.' . $extension;
                                    $target_file = $target_dir . $new_file_name;
                                    whatsapp::createFileFromBase64($base64Content, $target_file);
                                    $new_message_id = whatsapp::insertMessageToWhatsApp($caption, 1, $user['ID'], $modelUser['ID'], 0, $new_file_name);
                                    if ($girlCanGetWhatsapp) {
                                        $file_website_url =  whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $whatsappRes = $whatsapp->sendFile($file_website_url, $caption, $new_file_name, $modelUserMetaPhone["meta_value"]);
                                    }
                                    $messageTypeID = 1;
                                    $acceptMessage = true;
                                } else if (empty($videoMessage) == false) {
                                    $videoUrl = $videoMessage['url'];
                                    $mediaKey = $videoMessage['mediaKey'];
                                    $mimetype = $videoMessage['mimetype'];
                                    $extension = explode('/', $mimetype)[1];
                                    $caption = model_helper::getUserUniqueNumberForWhatsapp($user['ID']) . whatsapp::getNestedArrayValue($videoMessage, ['caption']);
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
                                    $new_message_id =  whatsapp::insertMessageToWhatsApp($caption, 2, $user['ID'], $modelUser['ID'], 0, $new_file_name);
                                    if ($girlCanGetWhatsapp) {
                                        $file_website_url =  whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $whatsappRes = $whatsapp->sendFile($file_website_url, $caption, $new_file_name, $modelUserMetaPhone["meta_value"]);
                                    }
                                    $messageTypeID = 2;
                                    $acceptMessage = true;
                                } else if (empty($audioMessage) == false) {
                                    $audioUrl = $audioMessage['url'];
                                    $mediaKey = $audioMessage['mediaKey'];
                                    $mimetype = $audioMessage['mimetype'];
                                    $extension = explode('/', $mimetype)[1];
                                    // Handling different MIME types to set the appropriate file extension
                                    if (strpos($mimetype, 'audio/ogg') !== false || strpos($mimetype, 'opus') !== false) {
                                        $extension = 'ogg'; // Set extension for OGG Opus files
                                    }
                                    $caption =  model_helper::getUserUniqueNumberForWhatsapp($user['ID']);
                                    $base64Content = whatsapp::decryptWhatsappImage($audioUrl, $mediaKey, 3, $extension);
                                    // $base64Content = explode(',', $base64Image)[1];
                                    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/models_chats/" . $modelUser['ID'] . "/" . $room_id . "/";
                                    // Check if the upload folder exists, if not create it
                                    if (!file_exists($target_dir)) {
                                        mkdir($target_dir, 0777, true);
                                    }
                                    $new_file_name = uniqid() . '.' . $extension;
                                    $target_file = $target_dir . $new_file_name;
                                    whatsapp::createFileFromBase64($base64Content, $target_file);
                                    $new_message_id =  whatsapp::insertMessageToWhatsApp($caption, 5, $user['ID'], $modelUser['ID'], 0, $new_file_name);
                                    if ($girlCanGetWhatsapp) {
                                        $file_website_url =  whatsapp::getCurrentDomain() . "/wp-content/uploads/models_chats/" .  $modelUser['ID']  . "/" . $room_id . "/" . $new_file_name;
                                        $audioMsgContent = $caption . "הודעת שמע. לחצו לשמוע -> " . $file_website_url;
                                        $whatsappRes = $whatsapp->sendMessage($modelUserMetaPhone["meta_value"], $audioMsgContent);
                                    }
                                    $messageTypeID = 5;
                                    $acceptMessage = true;
                                }
                            }
                            if ($acceptMessage) { // send message to model                             
                                $price_per_message = whatsapp::getPricePerMessageTypeInSeconds($messageTypeID);
                                whatsapp::insertChatTimeUseAndCheckTime($modelUser, $user, 1, $new_message_id, $price_per_message,  $whatsapp, $senderPhone, $modelUserMetaPhone["meta_value"]);
                            } else {
                                $whatsappRes = $whatsapp->sendMessage($senderPhone, "הודעת מערכת: סוג הודעה לא נתמך. אינך מחויב עבור הודעה זו");
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
