<?php
class MeshulamPaymentProcessor {
    private $apiUrl;
    private $pageCode;
    private $userId;
    private $apiKey;

    public function __construct($pageCode, $userId, $apiKey,$isSandbox) {
        //$this->apiUrl = 'https://sandbox.meshulam.co.il/api/light/server/1.0/createPaymentProcess/';
        if($isSandbox){
            $this->apiUrl = 'https://sandbox.meshulam.co.il/api/light/server/1.0/createPaymentProcess';
        }
        else{
            $this->apiUrl = 'https://secure.meshulam.co.il/api/light/server/1.0/createPaymentProcess';
        }
        $this->pageCode = $pageCode;
        $this->userId = $userId;
        $this->apiKey = $apiKey;
    }

    public function createPayment($sum, $successUrl, $cancelUrl, $description,$saveCardToken, $cField1,$fullName,$email,$phone,$chargeType,$period,$plan_id,$user_id,$is_auto_renew) {
        $postData = [
            'sum' => $sum,
            'successUrl' => $successUrl,
            'cancelUrl' => $cancelUrl,
            'description' => 'הרשמה לסדנה',
            'pageField[fullName]' => "user ".$fullName,
            'pageField[phone]' => $phone,
            'pageField[email]' => $email,
            'userId' => $this->userId,
            'pageCode' => $this->pageCode,
            'apiKey' => $this->apiKey,
            'paymentType' => '3',
            'saveCardToken' => $saveCardToken,
             'maxPaymentNum' => 1,
             'notifyUrl'=>'https://easy-wordpress.org/code/subscription/meshulam_callback.php',
            'cField1' => $period,
            'cField2' => $plan_id,
            'cField3' => $user_id,
            'cField4' => $is_auto_renew,

            // 'companyCommission' => $companyCommission,
            // Include other fields if necessary
        ];
        
        $jsonPostData = json_encode($postData);
        //echo ($jsonPostData);
        //die();
        return $this->sendRequest($this->apiUrl, $postData);
    }

    private function sendRequest($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
    
        // Logging response
        file_put_contents($_SERVER['DOCUMENT_ROOT'] .'/response_log.txt', $response);
    
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }
    
}



?>