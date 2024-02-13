<?php
define('PROJECT_ROOT', dirname(__DIR__) . '/'); // Assuming webscoket.php is one directory deeper than the root

require_once(PROJECT_ROOT . '/code/classes/websocketHelpers.php');
require_once(PROJECT_ROOT . '/vendor/autoload.php');
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use React\Socket\SecureServer;
use React\Socket\Server as ReactServer;
use React\EventLoop\Factory as LoopFactory;

class roomData{
    public $room_guid;
    public $user_id;
    public $user_type;
    public $user_guid;
    public $is_model;

}

class WebSocketServer implements MessageComponentInterface {
    private $logFile;
    private $clients;


    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $this->logFile = __DIR__ . '/websocket_logs.txt'; // Log file path
    }

    private function writeToLog($message) {
        $logMessage = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }


    public function onOpen(ConnectionInterface $conn) {
        // Initialize storage for this connection with a stdClass object
        $connectionData = new \stdClass();
        $connectionData->user_id = null; // Default value or null
        $connectionData->user_guid = null; // Default value or null
        $connectionData->room_guid = null; // Default value or null
        $connectionData->user_type = null; // Default value or null
        $connectionData->is_model = null; // Default value or null
        $this->clients->attach($conn, $connectionData);
        echo "New connection! ({$conn->resourceId})\n";
    }
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        $websocketHelpers = websocketHelpers::getInstance();
        // Retrieve the sender's roomData
        $senderData = $this->clients->offsetGet($from);
        if($senderData->user_id === null){
            $room_data=$websocketHelpers->build_user_object($data['user_guid'],$data['room_guid']);
            if($room_data==null){
               echo "room_data==null\n";
               die();
            }
            else
            {
                $senderData->user_id=$room_data->user_id;
                $senderData->user_guid=$room_data->user_guid;
                $senderData->room_guid=$room_data->room_guid;
                $senderData->user_type=$room_data->user_type;
                $senderData->is_model=$room_data->is_model;
            }
        }
        $websocketHelpers->userEnterChat($senderData->room_guid,$room_data->user_id);
     
        
        // if ($userId === null || $roomId === null || $userType === null) {
        //     // Log missing data or handle the error as appropriate
        //     $this->writeToLog("Missing data from message: " . $msg);
        //     echo "Missing data from message: " . $msg."\n";
        //     return; // Exit the function if the essential data is not present
        // }
        // Update the sender's roomData based on the received message
        // $senderData->user_id = $data['user_id'];
        // $senderData->user_guid = $data['user_guid'];
        // $senderData->room_id = $data['room_id'];
        // $senderData->user_type = $data['user_type'];
        
        // Log the identification/update event
        $this->writeToLog("User {$senderData->user_id} identified in room {$senderData->room_id} with type {$senderData->user_type}");
        echo "User {$senderData->user_id} identified in room {$senderData->room_id} with type {$senderData->user_type}\n";
        // Broadcast the message to other clients in the same room, excluding the sender
        foreach ($this->clients as $client) {
            // Retrieve stored roomData for this client
            $clientData = $this->clients->offsetGet($client);
            
            // Check if this client is in the same room and not the sender
            if ($clientData->room_id == $senderData->room_id && $clientData->user_id != $senderData->user_id) {
                $client->send($msg);
                // If you wish to send the message to all clients in the room (excluding the sender), remove the break statement
                // break; // Remove or comment out if broadcasting to the entire room
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        // Assuming you've stored connection details as suggested above
        if ($this->clients->contains($conn)) {
            $clientData = $this->clients->offsetGet($conn);
            $room_id = $clientData->room_id; // Access the stored room_id
            $user_id = $clientData->user_id; // Access the stored room_id
            $websocketHelpers = websocketHelpers::getInstance();
            $websocketHelpers->closeChat($user_id,$room_id);
            // Now you can use $room_id for logging or cleanup purposes
            echo "Connection {$conn->resourceId} with room ID $room_id has disconnected user_id $user_id\n";
        }
    
        // Don't forget to detach the client from storage
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->writeToLog("An error has occurred: {$e->getMessage()}");
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Event loop
$loop = LoopFactory::create();

// SSL options as an array
$sslOptions = [
    'local_cert'  => '/etc/letsencrypt/live/cam.afikim.pro/fullchain.pem',
    'local_pk'    => '/etc/letsencrypt/live/cam.afikim.pro/privkey.pem',
    'verify_peer' => false,
    'allow_self_signed' => true,
];

// ReactPHP socket server
$socket = new ReactServer('0.0.0.0:8080', $loop);
$secureSocket = new SecureServer($socket, $loop, $sslOptions);

// WebSocket server
$webSocket = new WsServer(new WebSocketServer());
$httpServer = new HttpServer($webSocket);

// Running the server
$server = new IoServer($httpServer, $secureSocket, $loop);
echo "WebSocket server started on port 8080\n";
$server->run();
