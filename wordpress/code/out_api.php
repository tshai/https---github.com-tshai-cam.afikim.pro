<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
$q_type = $_REQUEST['q_type'];
$helpersInstance = new helpers();

if ($q_type == "models_list") {
    $models = [
        [
            "name" => "John Doe",
            "description" => "Software Engineer",
            "guid" => 73,
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Jane Smith",
            "description" => "Graphic Designer",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ],
        [
            "name" => "Bob Johnson",
            "description" => "Marketing Manager",
            "image" => "https://cam.afikim.pro/wp-content/uploads/2024/01/One-Line-Art-Woman-Body-Phone-Wallpaper.png"
        ]
    ];

    echo json_encode($models);
    die();
} else if ($q_type == "prices_list") {
    $dbInstance = db::getInstance();
    $sql = "SELECT * FROM prices WHERE price_status=? order by price_ils";
    $prices = R::getAll($sql, [1]);
    echo json_encode($prices);
    die();
} else if ($q_type == "user_payment_request") {
    try {
        $json = file_get_contents('php://input');
        // $json = stripslashes($_REQUEST['json']);
        $data = json_decode($json);

        $url = 'https://www.newcam18.com/cam-online-2024/user_payment_request.aspx';
        $current_user = wp_get_current_user();
        $packageID = $data->packageID;

        $dbInstance = db::getInstance();
        $sql2 = "SELECT * FROM prices WHERE id=? LIMIT 1";
        $price = R::getAll($sql2, [$packageID]);
        $data->price = $price[0]["price_ils"];
        $data_request = [
            'user_guid' => $current_user->user_guid,
            'payment_data' => $data
        ];

//        $response = credit_card_payment::sendPaymentRequest($url, json_encode($data_request));
//        $jsonResponse = json_decode($response);


        $jsonResponse = json_decode('
    {
        "payment_response": "success",
        "temp_answer": "2000",
        "url_3D": "sample_url_3D",
        "transaction_id": "test",
        "email": "sample_email",
        "lastName": "sample_lastName",
        "firstName": "sample_firstName",
        "id_number": "111111",
        "orderId": "sample_orderId"
    }
');


        $creditCard = new CreditCard();
        $creditCard->email = $jsonResponse->email;
        $creditCard->lastName = $jsonResponse->lastName;
        $creditCard->card_number = $data->ccNumber;
        $creditCard->firstName = $jsonResponse->firstName;
        $creditCard->cvv = $data->ccCCV;
        $creditCard->last_digits = substr($data->ccNumber, -4);
        $creditCard->user_id = $current_user->ID;
        $creditCard->id_number = $jsonResponse->id_number;
        $creditCard->month_date = substr($data->ccExpire, 0, 2);
        $creditCard->year_date = substr($data->ccExpire, 3, 2);
        $ccDetailsID = credit_card_payment::insertCCDetails($creditCard);

        $paymentData = new PaymentData();
        $paymentData->user_id = $current_user->ID;
        $paymentData->price = $data->price;
        $paymentData->paymentStatus = "0";
        $paymentData->ccDetailsID = $ccDetailsID;
        $paymentData->inatecTransactionid = $jsonResponse->transaction_id;
        $paymentData->inatecTransactionStatus = $jsonResponse->temp_answer;
        $paymentData->lastdigits = $creditCard->last_digits;
        $paymentData->orderId = $jsonResponse->orderId;
        $paymentData->timeExpiredTemp = $price[0]["time_expire"];
        credit_card_payment::insertCardCam($paymentData);

        echo json_encode('{"sts": "ok"}');
        die();
    } catch (Exception $ex) {
        errors::addError($ex->getMessage(), "out_api.php->102");
    }
} else if ($q_type == "user_response_payment") {
    $json = file_get_contents('php://input');
    errors::addError("Error: " . json_encode($json), "out_api.php line 86");
    die();
}

