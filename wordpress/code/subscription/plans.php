<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
if (!empty($_REQUEST["clickButton"])) {
    if ($_REQUEST["clickButton"] == "1") {
        //$allString1 = PaymentsCompany::showTable($company, $_REQUEST["plan"], $hasTokenInSystem);
        $current_user = wp_get_current_user();

        $isSendbox = true;
        if ($isSendbox) {
            $meshulamProcessor = new MeshulamPaymentProcessor("4faf996ba14f", "9b8c8529ecdbb233", '9bc8ba49c829', true);
        } else {
            //$meshulamProcessor = new MeshulamPaymentProcessor("4faf996ba14f","9b8c8529ecdbb233", '9bc8ba49c829',false);
        }
        $dbInstance = db::getInstance();
        $CurrentPlan = R::findOne('wp_plans', 'id = ? AND is_active = ?', [$_GET['plan_id'], 1]);


        $currentDate = date('Y-m-d');
        $user_current_plans = R::find('wp_zahi_user_subscription', 
        'user_id = ? AND end_date > ? ORDER BY id DESC LIMIT 1', 
        [$user_id, $currentDate]);

        // Since `find` returns an array, get the first element
        $user_current_plan = reset($user_current_plans);
        //$user_current_plan = R::findOne('wp_zahi_user_subscription', 'user_id = ? AND end_date > ?', [$current_user->ID, $currentDate]);
        $user_wanted_plan = R::findOne('wp_plans', 'id = ? AND is_active = ?', [$_GET['plan_id'], 1]);
        $price;
        if ($user_current_plan) {
            $user_current_plan_data = R::findOne('wp_plans', 'id = ? AND is_active = ?', [$user_current_plan->plan_id, 1]);
            if ($user_wanted_plan->price_month > $user_current_plan->price) { // upgrage subscription to bigger plan
                $partial_time_left_seconds = strtotime($user_current_plan->end_date) - strtotime(date('Y-m-d H:i:s'));
                $partial_time_left_hours = $partial_time_left_seconds / 3600; // Convert seconds to hours
                $full_time_subscription_seconds = strtotime($user_current_plan->end_date) - strtotime($user_current_plan->start_date);
                $full_time_subscription_hours = $full_time_subscription_seconds / 3600; // Convert seconds to days
                $partal_time_in_percent = $partial_time_left_hours / $full_time_subscription_hours; // Assuming the plan period is in months
                //echo $partal_time_in_percent;
                $price_to_add = ($user_wanted_plan->price_month * $partal_time_in_percent) - $user_current_plan->price;
                $price_to_add = round($price_to_add, 2);
                $price=$price_to_add;
            }
        }
        else
        {
            $price=$user_wanted_plan->price_month;
        }

        // echo $price;
        // die();




        
        // if ($_REQUEST['period'] == "month") {
        //     $price = $CurrentPlan->price_month;
        // } else {
        //     $price = $CurrentPlan->price_year;
        // }

        $response = $meshulamProcessor->createPayment(
            $price, // sum
            'https://easy-wordpress.org/code/subscription/successful_payment.php?action=1', // successUrl
            'https://easy-wordpress.org/code/subscription/fails_payment.php?action=1', // cancelUrl
            'Payment for a monthly subscription', // description
            '1', // saveCardToken
            '',
            $current_user->user_nicename,
            $current_user->user_email,
            '0502263423',
            '1',
            $_REQUEST['period'],
            $_REQUEST['plan_id'],
            $current_user->ID,
            helpers::generateRandomString(14)
        );
        $responseObj = json_decode($response);

        // Extract the status and processToken
        $status = $responseObj->status;
        if ($status == "1") {
            $processToken = $responseObj->data->processToken;
            echo $responseObj->data->url;
            exit;
        }
        $processToken = $responseObj->data->processToken;
        echo $response;
        exit;
    }
}
// Assuming you have database connection code here

// Assuming you have a valid database connection and $wpdb initialized

// Fetch data from wp_plans
$plansQuery = "SELECT * FROM wp_plans";
$plansResult = $wpdb->get_results($plansQuery, OBJECT);

$plansData = array();

foreach ($plansResult as $plan) {
    $planData = array(
        'id' => $plan->id,
        'plan_name_other_lang' => $plan->plan_name_other_lang,
        'is_active' => $plan->is_active,
        'Currency' => $plan->currency,
        'price_month' => $plan->price_month,
        'price_year' => $plan->price_year,
        'properties' => array()
    );

    // Fetch properties associated with the plan
    $propertiesQuery = "SELECT p.property_name_en, p.property_name_other_lang, pp.amount, pp.units
                        FROM wp_plan_to_plan_properties pp
                        INNER JOIN wp_plan_properties p ON pp.plan_property_id = p.id
                        WHERE pp.plan_id = " . $plan->id;

    $propertiesResult = $wpdb->get_results($propertiesQuery, OBJECT);

    foreach ($propertiesResult as $property) {
        $planData['properties'][] = array(
            'property_name_en' => $property->property_name_en,
            'property_name_other_lang' => $property->property_name_other_lang,
            'Amount' => $property->amount,
            'Units' => $property->units
        );
    }

    $plansData[] = $planData;
}

// Convert the data to JSON
$jsonData = json_encode($plansData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Set response content type to JSON
header('Content-Type: application/json');

// Output the JSON data
echo $jsonData;
