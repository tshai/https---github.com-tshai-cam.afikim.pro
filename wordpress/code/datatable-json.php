<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

function fetchTableDataAsJson() {
    $current_user = wp_get_current_user();
    // Ensure that only a valid table name is used to prevent SQL injection
    // This is a basic example, you might need a more sophisticated check depending on your needs
    $dbInstance=db::getInstance();
    $pdo = $dbInstance->getPdo();
   

    // Prepare the SQL statement with a WHERE clause if provided
    $sql = "SELECT * FROM wp_zahi_user_subscription WHERE user_id = :user_id ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $current_user->ID, PDO::PARAM_INT); // Assuming $user_id is an integer
    $stmt->execute();
    $results = $stmt->fetchAll();

    // Return data as JSON
    return json_encode($results);
}



$jsonData = fetchTableDataAsJson();
echo $jsonData;
?>