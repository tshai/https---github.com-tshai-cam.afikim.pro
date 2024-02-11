<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
class helpers
{
    //private $errors;

    public function __construct()
    {
        //$this->errors=new errors();
    }

    public static function echo_display($ToShow, $input)
    {
        if ($ToShow == 1) {
            echo $input;
        }
    }

    public static function selectFromTableByColumn($tableName, $whereClause)
    {
        $dbInstance = db::getInstance();
        $conn = $dbInstance->mysqli();

        // Check for connection errors
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $whereClauseStr = "";
        foreach ($whereClause as $key => $value) {
            $whereClauseStr  = $whereClauseStr . " $key='$value' and";
        }
        $whereClauseStr = substr($whereClauseStr, 0, -3);
        // SQL SELECT query
        $sql = "SELECT * FROM $tableName where $whereClauseStr";

        // Execute the query
        $result = $conn->query($sql);

        $data = []; // Initialize an empty array to store the selected data

        // Check if there are rows returned
        if ($result->num_rows > 0) {
            // Fetch each row and add it to the $data array
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        // Close the database connection
        $conn->close();

        // Return the selected data as an array
        return $data;
    }

    public static function generateRandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        $maxIndex = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $maxIndex)];
        }

        return $randomString;
    }

    public static function get_plan($plan_id)
    {
        $dbInstance = db::getInstance();
        $plan = R::findOne('wp_plans', 'id=?', [$plan_id]);
        return $plan;
    }
    public static function show_payment_processor($id)
    {
        $dbInstance = db::getInstance();
        $pdo = $dbInstance->getPdo();
        try {
            $sql = "SELECT payment_processor_name FROM wp_payment_processors WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['payment_processor_name'];
            } else {
                return "No payment processor found for ID " . $id;
            }
        } catch (PDOException $e) {
            return "Error showing payment processor: " . $e->getMessage();
        }
    }
    public static function show_currency($id)
    {
        $dbInstance = db::getInstance();
        $pdo = $dbInstance->getPdo();
        try {
            $sql = "SELECT currency_name FROM wp_currency WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['currency_name'];
            } else {
                return "No currency found for ID " . $id;
            }
        } catch (PDOException $e) {
            return "Error showing currency: " . $e->getMessage();
        }
    }

    public static function clearErrorsTable()
    {
        $dbInstance = db::getInstance();
        $pdo = $dbInstance->getPdo();
        try {
            $sql = "DELETE FROM errors";
            $pdo->exec($sql);
            return "1";
        } catch (PDOException $e) {
            return "Error clearing table: " . $e->getMessage();
        }
    }

    public static function create_guid()
    {
        try {
            return Uuid::uuid4();
        } catch (Exception $e) {
            echo $e->getMessage();
            errors::addError($e->getMessage(), "create_guid");
        }
    }

    function custom_log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "$timestamp - $message\n";
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/code/logfile.log", $logMessage, FILE_APPEND);
    }
}
