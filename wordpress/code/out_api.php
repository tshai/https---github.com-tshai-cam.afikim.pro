<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
$q_type = $_REQUEST['q_type'];
$helpersInstance = new helpers();

if ($q_type == "prices_list") {
    $current_user = wp_get_current_user();
    $dbInstance = db::getInstance();
    $pricesSql = "SELECT * FROM prices WHERE price_status=? order by price_ils";
    $prices = R::getAll($pricesSql, [1]);

    $userCardsSql = "SELECT * FROM cc_details WHERE user_id=? and active=? order by ID desc";
    $userCards = R::getAll($userCardsSql, [$current_user->ID, 1]);
    $modifiedUserCards = [];

    foreach ($userCards as $card) {
        $cardNew['last4Digits'] = $card['last_digits'];
        $cardNew['ID'] = $card['ID'];
        $cardNew['expire'] = $card['month_date'] . "/" . $card['year_date'];
        $modifiedUserCards[] = $cardNew;
    }

    $response = new stdClass();
    $response->prices = $prices;
    $response->cards = $modifiedUserCards;
    echo json_encode($response);
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
        $sqlPrices = "SELECT * FROM prices WHERE id=? LIMIT 1";
        $price = R::getAll($sqlPrices, [$packageID]);
        $data->price = $price[0]["price_ils"];
        $ipAddress = credit_card_payment::getUserIP();
        $data->ipAddress = $ipAddress;
        if ($data->userPayNewCard === false) {
            $sqlCard = "SELECT * FROM cc_details WHERE id=? LIMIT 1";
            $ccDetails = R::getAll($sqlCard, [$data->ccID])[0];
            $data->ccNumber = $ccDetails['card_number'];
            $data->ccExpire = $ccDetails['month_date'] . "/" . $ccDetails['year_date'];
            $data->ccCCV = $ccDetails['cvv'];
        }
        $data_request = [
            'user_guid' => $current_user->user_guid,
            'payment_data' => $data
        ];

        $response = credit_card_payment::sendPaymentRequest($url, json_encode($data_request));
        $jsonResponse = json_decode($response);

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
        $ccDetailsID = credit_card_payment::insertCCDetailsIfNotExists($creditCard);

        $paymentData = new PaymentData();
        $paymentData->user_id = $current_user->ID;
        $paymentData->price = $data->price;
        if ($jsonResponse->temp_answer === "0") {
            $paymentData->paymentStatus = "1"; // success no 3d needed
        } else if ($jsonResponse->temp_answer === "2000") {
            $paymentData->paymentStatus = "0"; // pending 3d
        } else {
            $paymentData->paymentStatus = "2"; // failed
        }
        $paymentData->ccDetailsID = $ccDetailsID;
        $paymentData->inatecTransactionid = $jsonResponse->transaction_id;
        $paymentData->inatecTransactionStatus = $jsonResponse->temp_answer . ' ' . $jsonResponse->payment_response;
        $paymentData->lastdigits = $creditCard->last_digits;
        $paymentData->orderId = $jsonResponse->orderId;
        $paymentData->timeExpiredTemp = $price[0]["time_expire"];
        credit_card_payment::insertCardCam($paymentData);

        if ($paymentData->paymentStatus === "1") {
            echo json_encode('{"status": "ok"}');
        } else {
            if ($jsonResponse->url_3D != "" && $jsonResponse->url_3D != "none") {
                echo json_encode('{"status": "pending", "url_3D":"' . $jsonResponse->url_3D . '","orderID":"' . $jsonResponse->orderId . '"}');
            } else {
                echo json_encode('{"status": "failed"}');
            }
        }
        die();
    } catch (Exception $ex) {
        errors::addError($ex->getMessage(), "out_api.php->157");
    }
} else if ($q_type == "user_response_payment") { // call from inatec notification payment
    $json = file_get_contents('php://input');
    //$json = stripslashes($_REQUEST['json']);
    $data = json_decode($json);
    $dbInstance = db::getInstance();
    credit_card_payment::insert3DLog($data->transcationID, $data->errorCode, $data->errorMessage, $data->orderID, $data->security);
    if ($data->errorCode === "0") { // success
        $sqlUpdate = "UPDATE card_cam SET time_expire=timeExpiredTemp,approved3D=1,paymentStatus=1,inatecTransactionStatus=? WHERE orderId = ? and inatecTransactionid = ?";
    } else { // failed
        $sqlUpdate = "UPDATE card_cam SET paymentStatus=2,price=0,inatecTransactionStatus=? WHERE orderId = ? and inatecTransactionid = ?";
    }
    R::exec($sqlUpdate, [$data->errorCode . ' ' . $data->errorMessage, $data->orderID, $data->transcationID]);
    errors::addError("Error: " . json_encode($json), "out_api.php line 161");
    echo "ok";
    die();
} else if ($q_type == "check3d_status") {
    $postOrderID = $_REQUEST['postOrderID'];
    $dbInstance = db::getInstance();
    $current_user = wp_get_current_user();
    $sqlCard = "SELECT * FROM card_cam WHERE orderId=? and user_id=? LIMIT 1";
    $paymentSts = R::getAll($sqlCard, [$postOrderID, $current_user->ID])[0];
    $responseJson = new stdClass();
    if ($paymentSts['paymentStatus'] != "0") {
        $responseJson->state = "ready";
        $responseJson->status = $paymentSts['paymentStatus'] == "1" ? "success" : "failed";

    } else {
        $responseJson->state = "not ready";
    }
    echo json_encode($responseJson);
    die();
}

