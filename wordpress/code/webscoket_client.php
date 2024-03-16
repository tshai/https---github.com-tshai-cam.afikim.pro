<?php
//die();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/../vendor/autoload.php';


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;

require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');




?>
<script>
    const socket = new WebSocket('wss://mifgashim.net:8080');

    socket.addEventListener('open', function(event) {
        console.log('Connected to WebSocket server');

        // Send a message to the server
        socket.send('Hello, WebSocket server!');
    });

    socket.addEventListener('message', function(event) {
        console.log('Received message:', event.data);
    });

    socket.addEventListener('close', function(event) {
        console.log('Connection closed');
    });

    socket.addEventListener('error', function(event) {
        console.error('WebSocket error:', event);
    });
</script>