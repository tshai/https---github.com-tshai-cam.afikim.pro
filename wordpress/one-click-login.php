<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Login script (e.g., one-click-login.php)
require_once('wp-load.php'); // Adjust the path as necessary to ensure wp-load.php is included correctly

function loginUserWithCredentials($user, $pass) {
    $creds = array();
    $creds['user_login'] = $user;
    $creds['user_password'] = $pass;
    $creds['remember'] = true;
    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {
        echo $user->get_error_message();
    } else {
        if (is_user_logged_in()) { // Check if user is logged in
            $current_user = wp_get_current_user();
            //echo "sss";
            wp_redirect("/dashboard");
            echo 'Your User ID is: ' . $current_user->ID; // Display user ID
        }
        else {

            echo 'User not logged in';
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $uri = $_SERVER['REQUEST_URI'];
            $currentUrl = $protocol . '://' . $host . $uri;

            header('Location: ' . $currentUrl);
        }
        
        exit;
    }
}


if (isset($_REQUEST['user']) && isset($_REQUEST['pass'])) {
    loginUserWithCredentials($_REQUEST['user'], $_REQUEST['pass']);
}



?>