<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
class errors{

    public function __construct() {

    }
    // Function to insert an error into the "errors" table
    public static function addError($message, $url) {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();

        // Check for connection errors
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }

        // Prepare the SQL statement with placeholders
        $sql = "INSERT INTO errors (date_in, message, url) VALUES (NOW(), ?, ?)";

        // Create a prepared statement
        $stmt = $mysqli->prepare($sql);

        // Bind parameters to the placeholders
        $stmt->bind_param("ss", $message, $url);

        // Execute the statement
        if ($stmt->execute()) {
            $stmt->close();
            $mysqli->close();
            return true; // Insert successful
        } else {
            $stmt->close();
            $mysqli->close();
            return false; // Insert failed
        }
    }
}


?>