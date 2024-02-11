<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');

class logs
{
    function save_logs()
    {
    }
    // Function to insert data into the domains_logs table
    public static function insertLog($user_id, $log_desc, $domain_name)
    {
        $dbInstance = db::getInstance();
        $mysqli = $dbInstance->mysqli();    //$mysqli = mysqli_connect('localhost', 'root', 'w262sUQ4J2P');
        // Check for connection errors
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }

        // Prepare the SQL statement with placeholders
        $sql = "INSERT INTO domains_logs (date_in, user_id, log_desc, domain_name) VALUES (?, ?, ?, ?)";

        // Create a prepared statement
        $stmt = $mysqli->prepare($sql);

        // Bind parameters to the placeholders
        $date1=date('Y-m-d H:i:s');
        $stmt->bind_param("siss", $date1, $user_id, $log_desc, $domain_name);

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
