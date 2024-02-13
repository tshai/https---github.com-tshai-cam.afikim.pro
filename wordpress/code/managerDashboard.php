<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
$current_user = wp_get_current_user();
if(isset($_POST['action']) && $_POST['action'] == "createChatRoom"){
    $user_guid = $_POST['user_guid'];
    
    //echo $user_id;
    $room_guid=ChatTimeUse::insertChatTimeUse($current_user->ID, $user_guid, 0, 0, 0, 0);
    echo $room_guid;
    exit;
}
echo $current_user->user_guid;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WebRTC Video Chat</title>
</head>
<body>
    <h2>WebRTC Video Chat Sample</h2>
   <div onclick="createChatRoom()">שלחי הזמנה לצאט</div>


   <script>
function createChatRoom(){
    const params = new URLSearchParams(window.location.search);
    const user_guid = params.get('user_guid'); // This gets '1' from your example URL
    // Specify the server endpoint
    var serverUrl = 'managerDashboard.php';

    // Create a FormData object and append the key-value pair
    var formData = new FormData();
    formData.append('user_guid', user_guid);
    formData.append('action', "createChatRoom");

    // Use fetch API to send the data to the server
    fetch(serverUrl, {
            method: 'POST',
            // Note: When using FormData, the Content-Type header is set automatically by the browser,
            // including the boundary parameter. Therefore, we don't manually set the Content-Type header here.
            body: formData, // Sending the combined phone number as form data
        })
        .then(response => response.text()) // Convert the response to text (assuming text response)
        .then(text => {
            window.location.href = "videoChatClient.php?room_guid=" + text + "&other_user_guid="+ user_guid +"&user_type=manager&user_guid=<?php echo $current_user->user_guid; ?>";

                //alert(text);

           
        })
        .catch((error) => {
            console.error('Error:', error); // Handle errors
        });
}
    </script>
</body>
</html>