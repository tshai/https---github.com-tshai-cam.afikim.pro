<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');

$helpersInstance = new helpers();
$logsInstance = new Logs();
$errorsInstance = new errors();
$dbInstance = db::getInstance();
//$wp_usersInstance = new wp_users($dbInstance->getPdo());
if (is_user_logged_in()) { //this api request from other site
    $current_user = wp_get_current_user();
    // $wp_usersInstance->setID($current_user->ID);
    $q_type = $_REQUEST['q_type'];
    if ($q_type == "start_chat") {
        $model_guid = $_REQUEST['model_guid'];
        $dbInstance = db::getInstance();
        $modelUserDb = R::load('wp_users', $model_guid);
        $modelUser = json_decode($modelUserDb, true);

        $newMessageToUser = "hello from girl " . $modelUser['user_login'];
        $whatsapp = new whatsapp($modelUser['whatsapp_instance_id']);
        // R::debug(true);
        $userMetaPhone = R::getRow('SELECT * FROM wp_usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1', [$current_user->ID, "xoo_ml_phone_display"]);
        $whatsappRes = $whatsapp->sendMessage($userMetaPhone["meta_value"], $newMessageToUser);
        whatsapp::insertMessageToWhatsApp($newMessageToUser, 0, $current_user->ID, $modelUser['ID']);
        echo "ok";
        die();
    } else if ($q_type == "log_out") {
        wp_logout(); // WordPress function to log out the current user
        // Redirect to the home page or login page after logging out
        wp_redirect(home_url());
        exit;
    }
}
