<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
//die();
$current_user = wp_get_current_user();
echo $current_user->user_guid;
$other_user_url="https://cam.afikim.pro/code/videoChatClient.php?room_guid=".$_REQUEST['room_guid']."&other_user_guid=".$_REQUEST['user_guid']."&user_type=customer&user_guid=".$_REQUEST['other_user_guid']."";
echo "<br>".$other_user_url;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WebRTC Video Chat</title>
</head>
<body>
    <h2>WebRTC Video Chat Sample</h2>
    <video id="localVideo" autoplay muted></video>
    <video id="remoteVideo" autoplay></video>
    <script src="js/webrtc.js?v=1.11"></script>
    <button id="startCall">Start Call</button>
</body>
</html>