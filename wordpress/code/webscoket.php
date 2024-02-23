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



class WebSocketServer implements MessageComponentInterface
{
    private $logFile;
    private $clients;


    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->logFile = __DIR__ . '/websocket_logs.txt'; // Log file path
    }

    public function writeToLog($message)
    {
        $logMessage = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;
        //file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }


    public function onOpen(ConnectionInterface $conn)
    {
        // Initialize storage for this connection with a stdClass object
        $connectionData = new \stdClass();
        $connectionData->user_id = null; // Default value or null
        $connectionData->user_guid = null; // Default value or null
        $connectionData->room_guid = null; // Default value or null
        $connectionData->user_type = null; // Default value or null
        $connectionData->is_model = null; // Default value or null
        $connectionData->last_date = null; // Default value or null
        $this->clients->attach($conn, $connectionData);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        $websocketHelpers = websocketHelpers::getInstance();
        // Retrieve the sender's roomData
        $senderData = $this->clients->offsetGet($from);
        $room_data = new roomData();
        if ($senderData->user_id === null) {//the user is not identified
            $room_data = $websocketHelpers->build_user_object($data['user_guid'], $data['room_guid']);
            if ($room_data == null) {
                foreach ($this->clients as $client) {
                    // Retrieve stored roomData for this client
                    $clientData = $this->clients->offsetGet($client);
                    if ($clientData->room_guid == $senderData->room_guid && $clientData->user_id == $senderData->user_id) {
                        $client->send("{session_finished}");
                    }
                }
                echo "room_data==null\n";
                return;
                //die();
            } else {
                websocketHelpers::updateSenderData($senderData, $room_data);
            }
        } else {
            websocketHelpers::updateRoomData($room_data, $senderData);
        }
        $result = websocketHelpers::countUsersInRoom($this->clients, $senderData, $room_data);
        $count_how_many_same_user_in_room = $result['same_user_count'];
        $count_how_many_total_members_in_room = $result['total_member_count'];
        $count_how_many_same_user_in_room = 0;
        $count_how_many_total_members_in_room = 0;
        foreach ($this->clients as $client) {

            // Retrieve stored data for this client
            $clientData = $this->clients->offsetGet($client);
            echo "clientData " . $clientData->room_guid . "\n";
            echo "room_data " . $room_data->room_guid . "\n";
            if ($clientData->room_guid == $senderData->room_guid) {
                $count_how_many_total_members_in_room++;
            }
            if ($clientData->room_guid == $room_data->room_guid && $clientData->user_guid == $room_data->user_guid) {
                // Assuming you're checking for both user_guid and room_guid similarity
                $count_how_many_same_user_in_room++;

            }
        }
        if ($count_how_many_same_user_in_room > 1) {
            echo "count_how_many_same_user_in_room==" . $count_how_many_same_user_in_room . "\n";
            // Send a message to the client indicating more than one user is in the same room
            $client->send(json_encode(['message' => 'moreThanOneUser']));
            // Assuming you have an instance of your websocketHelpers class accessible as $websocketHelpers
            // and a method closeChat that takes room_guid and user_id as parameters
            $this->onClose($from);
            //$websocketHelpers->closeChat($room_data->room_guid, $room_data->user_id);
            return;
            // $websocketHelpers->closeChat($room_data->room_guid,$room_data->user_id);
            // return;
        }
        echo "Count membres in room => " . $count_how_many_total_members_in_room;
        if ($count_how_many_total_members_in_room < 2) {
            if ($room_data->is_model == 0) {
                websocketHelpers::send_message($this->clients, $senderData, "{\"error\":\"girlDiscounectInternet\"}",2);
                // foreach ($this->clients as $client) {
                //     // Retrieve stored roomData for this client
                //     $clientData = $this->clients->offsetGet($client);
                //     // Check if this client is in the same room and not the sender
                //     if ($clientData->room_guid == $senderData->room_guid) {
                //         $client->send("{\"error\":\"girlDiscounectInternet\"}");
                //         echo "girlDiscounectInternet 129";
                //         // If you wish to send the message to all clients in the room (excluding the sender), remove the break statement
                //         // break; // Remove or comment out if broadcasting to the entire room
                //     }
                // }
                $this->onClose($from);
               // websocketHelpers::closeChat($room_data->room_guid, $room_data->user_id);
            }
        }
        echo "room_data->user_id==" . $room_data->user_id . "\n";
        if ($data['type'] == "disconnect") {
            echo "disconnect called 139";
            websocketHelpers::send_message($this->clients, $senderData, "{\"error\":\"sessionSts1\"}",2);
            $this->onClose($from);
            // Now you can use $room_guid for logging or cleanup purposes
            echo "Connection {$from->resourceId} with room ID $room_data->room_guid has disconnected user_id $room_data->user_id\n";
            return;
        }

        if ($data['type'] == "update") {
            //$senderData->last_date = $now->format('Y-m-d H:i:s'); // Store as DateTime object

            $sessionStatus = $websocketHelpers::getSessionStatus($room_data);
            echo "session status => " . $sessionStatus;
            if ($sessionStatus == "1") {
                echo "sessionSts1 called 152";
                websocketHelpers::send_message($this->clients, $senderData, "{\"error\":\"sessionSts1\"}",2);
                // foreach ($this->clients as $client) {
                //     // Retrieve stored roomData for this client
                //     $clientData = $this->clients->offsetGet($client);
                //     // Check if this client is in the same room and not the sender
                //     if ($clientData->room_guid == $senderData->room_guid) {
                //         $client->send("{\"error\":\"sessionSts1\"}");
                //         echo "sessionSts1 send 158";
                //         // If you wish to send the message to all clients in the room (excluding the sender), remove the break statement
                //         // break; // Remove or comment out if broadcasting to the entire room
                //     }
                // }
                $this->onClose($from);
                //$websocketHelpers->closeChat($room_data->room_guid,$room_data->user_id);
                // Now you can use $room_guid for logging or cleanup purposes
                //echo "Connection {$conn->resourceId} with room ID $room_guid has disconnected user_id $user_id\n";
            } else {
                echo "success session sts 0 165";
                $websocketHelpers->update_chat_time_use($room_data);
                websocketHelpers::send_message($this->clients, $senderData, "{\"success\":\"0\"}",0);
                // foreach ($this->clients as $client) {
                //     // Retrieve stored roomData for this client
                //     $clientData = $this->clients->offsetGet($client);
                //     // Check if this client is in the same room and not the sender
                //     if ($clientData->room_guid == $senderData->room_guid && $clientData->user_id != $senderData->user_id) {
                //         $client->send("{\"success\":\"0\"}");
                //         // If you wish to send the message to all clients in the room (excluding the sender), remove the break statement
                //         // break; // Remove or comment out if broadcasting to the entire room
                //     }
                // }
            }
            return;
        };
        // Update $senderData->last_date to the current time

        // Or if you need to store as a string: $senderData->last_date = $now->format('Y-m-d H:i:s');
        // Output: Difference in seconds: 90
        $websocketHelpers->userEnterChat($room_data);
        $this->writeToLog("User {$senderData->user_id} identified in room {$senderData->room_guid} with type {$senderData->user_type}");
        echo "User {$senderData->user_id} identified in room {$senderData->room_guid} with type {$senderData->user_type}\n";
        // Broadcast the message to other clients in the same room, excluding the sender
        websocketHelpers::send_message($this->clients, $senderData, $msg,0);
        // foreach ($this->clients as $client) {
        //     // Retrieve stored roomData for this client
        //     $clientData = $this->clients->offsetGet($client);

        //     // Check if this client is in the same room and not the sender
        //     if ($clientData->room_guid == $senderData->room_guid && $clientData->user_id != $senderData->user_id) {
        //         $client->send($msg);
        //         // If you wish to send the message to all clients in the room (excluding the sender), remove the break statement
        //         // break; // Remove or comment out if broadcasting to the entire room
        //     }
        // }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->writeToLog("onClose called");
        // Assuming you've stored connection details as suggested above
        if ($this->clients->contains($conn)) {
            $clientData = $this->clients->offsetGet($conn);
            $room_guid = $clientData->room_guid; // Access the stored room_guid
            $user_id = $clientData->user_id; // Access the stored room_guid
            $websocketHelpers = websocketHelpers::getInstance();
            $websocketHelpers->closeChat($room_guid, $user_id);
            // Now you can use $room_guid for logging or cleanup purposes
            echo "Connection {$conn->resourceId} with room ID $room_guid has disconnected user_id $user_id\n";
        }

        // Don't forget to detach the client from storage
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->writeToLog("An error has occurred: {$e->getMessage()}");
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Event loop
$loop = LoopFactory::create();

// SSL options as an array
$sslOptions = [
    'local_cert' => '/etc/letsencrypt/live/cam.afikim.pro/fullchain.pem',
    'local_pk' => '/etc/letsencrypt/live/cam.afikim.pro/privkey.pem',
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
