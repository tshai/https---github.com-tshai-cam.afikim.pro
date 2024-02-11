<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/code/classes/includes.php');
class paymentCompany
{

    public static function showTable($company, $plan, $hasTokenInSystem)
    {
        $user_id = get_current_user_id(); // Replace with the actual user ID you want to search for
        $currentDate = date('Y-m-d');
        $dbInstance = db::getInstance();
        $plan = R::findOne('wp_zahi_user_subscription', 'user_id = ? AND end_date > ?', [$user_id, $currentDate]);

        if ($plan) {
            // A matching record was found
            // You can access the fields of the $plan object as needed
            $id = $plan->id;
            $startDate = $plan->start_date;
            return $startDate;
            // ... (access other fields as needed)
        } else {
            return "No matching record was found";
            // Handle the case where there is no valid plan
        }
    }
}
