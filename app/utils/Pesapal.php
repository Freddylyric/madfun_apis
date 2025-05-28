<?php

/**
 * Description of Pesapal
 *
 * @author kevinmwando
 */
use Phalcon\Mvc\Controller;
use ControllerBase as base;

class Pesapal extends Controller {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }

    private function createToken() {
        $base = new base();
        try {
            $endpoint = $base->settings['PESAPAL']['baseURLDemo'];
            $consumerKey = $base->settings['PESAPAL']['consumerKeyDemo'];
            $consumerSecret = $base->settings['PESAPAL']['consumerSecretDemo'];
            if ($base->settings['PESAPAL']['ISLive']) {
                $endpoint = $base->settings['PESAPAL']['baseURLLive'];
                $consumerKey = $base->settings['PESAPAL']['consumerKeyLive'];
                $consumerSecret = $base->settings['PESAPAL']['consumerSecretLive'];
            }

            $payload = [
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerSecret
            ];
            $response = $base->sendJsonPostData($endpoint."/api/Auth/RequestToken", $payload);
            
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | createToken response::" . json_encode($response).
                    "consumer_key:".$consumerKey." consumer_secret:"
                    . " ".$consumerSecret." Endpoint: ".$endpoint);
            if ($response['statusCode'] != 200) {
                return false;
            }
            $res = json_decode($response['response']);
            if ($res->status != 200) {
                return false;
            }
            return $res->token;
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | createToken Exception::" . $ex->getMessage());
            return false;
        }
    }

    public function submitOrder($params) {
        $base = new base();
        try {
            $accessToken = $this->createToken();
            if (!$accessToken) {
                return [
                    'status' => 401,
                    'message' => 'Failed to generate AccessToken',
                    'redirect_url' => "",
                    'order_tracking_id'=>""
                ];
            }

             $endpoint = $base->settings['PESAPAL']['baseURLDemo'];
            if ($base->settings['PESAPAL']['ISLive']) {
                $endpoint = $base->settings['PESAPAL']['baseURLLive'];
            }
            $payload = [
                "id" => $params['account'],
                "currency" => "KES",
                "amount" => $params['amount'],
                "description" => $params['description'],
                "callback_url"=>"https://gigs.madfun.com/event/dpo/payment",
                "cancellation_url"=>"https://gigs.madfun.com/event/".$params['eventId'],
                "notification_id" => $base->settings['PESAPAL']['notificationId'],
                "branch" => "MADFUN PESAPAL",
                "redirect_mode"=>"PARENT_WINDOW",
                "billing_address" => [
                    "email_address" => $params['email'],
                    "phone_number" => $params['phone'],
                    "country_code" => "KE",
                    "first_name" => $params['first_name'],
                    "middle_name" => $params['middle_name'],
                    "last_name" => $params['last_name'],
                ]
            ];
            
            $endpoint = $endpoint."/api/Transactions/SubmitOrderRequest";
            $response = $base->sendJsonTokenPesapalPostData($endpoint,
                    $payload, $accessToken);
            
            
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | createToken response::" . json_encode($response).
                    "accessToken:".$accessToken." Payload: ". json_encode($payload). " Endpoint".$endpoint);
            if ($response['statusCode'] != 200) {
                return [
                    'status' => $response['statusCode'],
                    'message' => 'Failed to Inititate Card Payments',
                    'redirect_url' => "",
                    'order_tracking_id'=>""
                ];
            }
            $res = json_decode($response['response']);
            if ((INT) $res->status != 200) {
                return [
                    'status' => $res->status,
                    'message' => $res->error->message,
                    'redirect_url' => "",
                    'order_tracking_id'=>""
                ];
            }

            return [
                'status' => (INT) $res->status,
                'message' => "Successful Initiated",
                'redirect_url' => $res->redirect_url,
                'order_tracking_id'=>$res->order_tracking_id,
            ];
        } catch (Exception $ex) {
            $this->infologger->emergency(__LINE__ . ":" . __CLASS__
                    . " | submitOrder Exception::" . $ex->getMessage());
            return false;
        }
    }
    
    public function queryPaymentStatus($orderTrackingId){
        $base = new base();
        try{
            $accessToken = $this->createToken();
            if (!$accessToken) {
                return false;
            }
            $endpoint = $base->settings['PESAPAL']['baseURLDemo'];
            if ($base->settings['PESAPAL']['ISLive']) {
                $endpoint = $base->settings['PESAPAL']['baseURLLive'];
            }
            $endpoint = $endpoint."/api/Transactions/GetTransactionStatus?orderTrackingId=".$orderTrackingId;
            $response = $base->sendGetRequestWithHeaders($endpoint,$accessToken);
            
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " IPNPesapal | queryPaymentStatus response::" . json_encode($response).
                    "accessToken:".$accessToken." orderTrackingId:"
                    . " ". $orderTrackingId. " Endpoint".$endpoint);
            
            if ($response['statusCode'] != 200) {
               return false;
            }
            $res = json_decode($response['response']);
            if ((INT) $res->status_code != 1) {
                return false;
            }
            return true;
        } catch (Exception $ex) {

            $this->infologger->emergency(__LINE__ . ":" . __CLASS__
                    . " | submitOrder Exception::" . $ex->getMessage());
            return false;
        }
        
    }
}
