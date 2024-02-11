<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
$dbInstance = db::getInstance();


if (isset($_REQUEST['status']) && $_REQUEST['status'] == "1" && isset($_REQUEST['err']) && $_REQUEST['err'] == "") {
    $user_id = $_REQUEST['data']['customFields']['cField3'];/* your logic to determine user_id */;
    $transactionId = $_REQUEST['data']['transactionId'];

    // Check if a record with the same user_id and transactionId already exists
    //$existingRecord = R::findOne('zahiusersubscription', ' user_id = ? AND transactionId = ? ', [$user_id, $transactionId]);
    $start_date;
    $end_date;
    $month_amount;
    $price = $_REQUEST['data']['sum']; // Assuming 'sum' is the price
    //$record->currency_id = 0 /* currency id logic */; // You need to define how to get this
    // if ($_REQUEST['data']['period'] == "month") {
        $start_date = date('Y-m-d H:i:s'); // Current date and time
        $end_date = date('Y-m-d H:i:s', strtotime('+1 month')); // Current date and time + 1 month
        
        $month_amount=1;
    // } else {
    //     $start_date = date('Y-m-d'); // Current date
    //     $end_date = date('Y-m-d', strtotime('+1 year')); // Current date + 1 year
    //     $month_amount=12;
    // }

    // if (!$existingRecord) {
    //     $record = R::dispense('zahiusersubscription');

    //     $record->date_in = date('Y-m-d H:i:s'); // Current date and time
    //     $record->user_id = $user_id;
    //     $record->transactionId = $transactionId;
    //     // $_REQUEST['data']['customFields']['cField4']
    //     $price = $_REQUEST['data']['sum']; // Assuming 'sum' is the price
    //     $record->currency_id = 0 /* currency id logic */; // You need to define how to get this
    //     if ($_REQUEST['data']['period'] == "month") {
    //         $start_date = date('Y-m-d'); // Current date
    //         $end_date = date('Y-m-d', strtotime('+1 month')); // Current date + 1 month
    //         $month_amount=1;
    //     } else {
    //         $start_date = date('Y-m-d'); // Current date
    //         $end_date = date('Y-m-d', strtotime('+1 year')); // Current date + 1 year
    //         $month_amount=12;
    //     }

    //     $record->transactionToken = $_REQUEST['data']['transactionToken'];
    //     $record->processId = $_REQUEST['data']['processId'];
    //     $record->processToken = $_REQUEST['data']['processToken'];
    //     $record->plan_id = $_REQUEST['data']['customFields']['cField2'];
    //     $record->is_auto_renew = $_REQUEST['data']['customFields']['cField4'];
    //     $record->price = $_REQUEST['data']['customFields']['cField3'];

    //     // Set other fields from $inputData as needed
    //     // Example: $record->price = $_REQUEST['data']['sum'];
    //     $filePath = $_SERVER['DOCUMENT_ROOT'] . '/meshulam_callback.txt';
    //     if (file_put_contents($filePath, "ww000000000000002220000", FILE_APPEND) === false) {
    //         //         throw new Exception("Failed to write to file: $filePath");
    //     }   // Y
    //     $id = R::store($record); // Save the new record
    //     echo "New record created with ID: $id";
    // } else {
    //     $filePath = $_SERVER['DOCUMENT_ROOT'] . '/meshulam_callback.txt';
    //     if (file_put_contents($filePath, "ww000000000045555600000000", FILE_APPEND) === false) {
    //         //         throw new Exception("Failed to write to file: $filePath");
    //     }   // Y
    //     echo "Record with user_id $user_id and transactionId $transactionId already exists.";
    // }

    // R::close(); // Close the database connection
    // No errors
    // Your code here...

    $user_id = $_REQUEST['data']['customFields']['cField3'];
    $local_guid = $_REQUEST['data']['customFields']['cField4'];
    $pdo = $dbInstance->getPdo();
    // Check if a record with the same user_id and transactionId already exists
    $stmt = $pdo->prepare("SELECT * FROM wp_zahi_user_subscription WHERE user_id = :user_id AND local_guid = :local_guid");
    $stmt->execute(['user_id' => $user_id, 'local_guid' => $local_guid]);
    $existingRecord = $stmt->fetch();

    if (!$existingRecord) {
        $insertQuery = "INSERT INTO wp_zahi_user_subscription (
            date_in, user_id, plan_id, start_date, month_amount, is_auto_renew, 
            price, currency_id, end_date, transactionId, payment_processor_id, 
            transactionToken, processId, processToken,local_guid
        ) VALUES (
            :date_in, :user_id, :plan_id, :start_date, :month_amount, :is_auto_renew, 
            :price, :currency_id, :end_date, :transactionId, :payment_processor_id, 
            :transactionToken, :processId, :processToken, :local_guid
        )";
        $stmt = $pdo->prepare($insertQuery);
        // Replace the placeholders with actual values from your data source
       
        $stmt->execute([
            'date_in' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'plan_id' => $_REQUEST['data']['customFields']['cField2'], // Replace $plan_id with actual value
            'start_date' => $start_date, // Replace $start_date with actual value
            'month_amount' => $month_amount, // Replace $month_amount with actual value
            'is_auto_renew' => 1, // Replace $is_auto_renew with actual value
            'price' => $price, // Replace $price with actual value
            'currency_id' => 1, // Replace $currency_id with actual value
            'end_date' => $end_date, // Replace $end_date with actual value
            'transactionId' => $transactionId,
            'payment_processor_id' => $payment_processor_id, // Replace $payment_processor_id with actual value
            'transactionToken' => $_REQUEST['data']['transactionToken'],
            'processId' => $_REQUEST['data']['processId'],
            'processToken' => $_REQUEST['data']['processToken'],
            'local_guid' => $_REQUEST['data']['customFields']['cField4']
        ]);
        $id = $pdo->lastInsertId();
        echo "New record created with ID: $id";
    } else {
        echo "Record with user_id $user_id and transactionId $transactionId already exists.";
    }
    $pdo = null;





} elseif (isset($_REQUEST['status']) && $_REQUEST['status'] == "0" && isset($_REQUEST['err']) && $_REQUEST['err'] != "") {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/meshulam_callback.txt';
    if (file_put_contents($filePath, "ww000000000000000000", FILE_APPEND) === false) {
        //         throw new Exception("Failed to write to file: $filePath");
    }   // Your code here...
} else {
    try {
        $textToAdd = "POST Data:\n";
        foreach ($_POST as $key => $value) {
            // Ensure that $value is a string before attempting to decode it
            if (is_string($value)) {
                $decodedValue = json_decode($value, true); // Decode as an associative array
                if (json_last_error() === JSON_ERROR_NONE) {
                    // It's JSON, append a printout of the object/array
                    $textToAdd .= "$key: " . print_r($decodedValue, true) . "\n";
                } else {
                    // It's a regular string
                    $textToAdd .= "$key: $value\n";
                }
            } elseif (is_array($value)) {
                // It's a regular array, append a printout of the array
                $textToAdd .= "$key: " . print_r($value, true) . "\n";
            }
        }

        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/meshulam_callback.txt';

        if (file_put_contents($filePath, $textToAdd, FILE_APPEND) === false) {
            throw new Exception("Failed to write to file: $filePath");
        }
    } catch (Exception $e) {
        // Handle the exception by logging the error message
        error_log("Error: " . $e->getMessage());
    }
}
