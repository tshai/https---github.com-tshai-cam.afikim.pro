<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use React\Socket\SecureServer;
use React\Socket\Server as ReactServer;
use React\EventLoop\Factory as LoopFactory;

class roomData{
    public $room_id;
    public $user_id;
    public $user_type;
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
        // Initialize storage for this connection
        $this->clients->attach($conn, new \stdClass());
        $this->writeToLog("New connection! ({$conn->resourceId})");
    }
   
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        // Retrieve the sender's roomData
        $senderData = $this->clients->offsetGet($from);
        // Initialize default values or perform early exit if keys are not set
        $userId = isset($data['user_id']) ? $data['user_id'] : null;
        $roomId = isset($data['room_id']) ? $data['room_id'] : null;
        $userType = isset($data['user_type']) ? $data['user_type'] : null;
        
        if ($userId === null || $roomId === null || $userType === null) {
            // Log missing data or handle the error as appropriate
            $this->writeToLog("Missing data from message: " . $msg);
            return; // Exit the function if the essential data is not present
        }
        // Update the sender's roomData based on the received message
        $senderData->user_id = $data['user_id'];
        $senderData->room_id = $data['room_id'];
        $senderData->user_type = $data['user_type'];
        
        // Log the identification/update event
        $this->writeToLog("User {$senderData->user_id} identified in room {$senderData->room_id} with type {$senderData->user_type}");
        
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
        // Clean up when a connection is closed
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->writeToLog("An error has occurred: {$e->getMessage()}");
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
