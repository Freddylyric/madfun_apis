<?php

/**
 * Description of EntriesController
 *
 * @author kevinkmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class UtilitiesController extends ControllerBase {

    protected $payload;
    protected $infologger;
    protected $errorlogger;
    protected $moduleName;

    function onConstruct() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
    }

    public function utilityPurchaseAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Transaction Request:" . json_encode($request->getJsonRawBody()));

        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $source = isset($data->source) ? strtoupper($data->source) : null;
        $purchase_amount = isset($data->purchase_amount) ? $data->purchase_amount : 0;
        $service_id = isset($data->service_id) ? $data->service_id : null;
        $account_number = isset($data->account_number) ? $data->account_number : null;
        $source_address = isset($data->source_address) ? $data->source_address : null;
        $web_extra_data = isset($data->extra_data) ? $data->extra_data : false;

        if (!$unique_id || !$phone || !$source || !$purchase_amount || !$source_address || !$service_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        $headers = $request->getHeaders();

        $auth['bearer_token'] = isset($headers['X-Authorization']) ? $headers['X-Authorization'] : false;
        $auth['app_token'] = isset($headers['X-App-Key']) ? $headers['X-App-Key'] : false;
        $auth['requested_with'] = isset($headers['X-Requested-With']) ? $headers['X-Requested-With'] : false;
        $auth['origin'] = isset($headers['Origin']) ? $headers['Origin'] : false;

        $bearer = explode('Bearer', $auth['bearer_token']);
        $token = isset($bearer[1]) ? preg_replace('/[\t\n\r\s]+/', '', $bearer[1]) : false;

        $accessToken = isset($auth['app_token']) ? preg_replace('/[\t\n\r\s]+/', '', $auth['app_token']) : false;

        if (!$token || !$accessToken) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$auth['requested_with'] || $auth['requested_with'] != 'XMLHttpRequest') {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed. Invalid Request!!']);
        }
        if (!$this->ValidateNumber($purchase_amount)) {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed. '
                        . 'Invalid Number ( KES ' . $this->settings['MinAmount']
                        . ' to ' . $this->settings['MaxAmount'] . ')!!']);
        }

        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        $msisdn = $this->formatMobileNumber($phone, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
        if ($network == "UNKNOWN") {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
        }

        $ip_address = $this->getClientIPServer();
        if (!in_array($ip_address, ['35.187.93.149', '197.248.63.121'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__ . ":"
                            . json_encode($request->getJsonRawBody())
                            , 'Un-authorised source!' . $ip_address .
                            '. UA:' . $request->getUserAgent());
        }
        try {
            $auth = new Authenticate();
            $auth_app = $auth->AutheticateRequest($source, preg_replace('/\s+/', '', $token));
            if (!$auth_app) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failed!!');
            }
            $auth_response = $auth->QuickTokenAuthenticate($accessToken);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User Authentication Failure.');
            }
            if (in_array($service_id, [3])) {
                $account_number = $this->formatMobileNumber($account_number, "254");
                $blacklist = $this->selectQuery("SELECT * FROM blacklist WHERE msisdn=:msisdn "
                        , [':msisdn' => $account_number]);

                if ($blacklist) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Service Error'
                                    , ['code' => 400, 'message' => 'Request Failed. Number is blacklisted!!']);
                }
            }
            $services = $this->selectQuery("SELECT * FROM services WHERE service_id=:service_id "
                    . "AND status=:status"
                    , [':service_id' => $service_id, ':status' => 1]);

            if (!$services) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Service Error'
                                , ['code' => 400, 'message' => 'Request Failed. Invalid Service Specified!!']);
            }
            $check_duplicate = $this->rawSelect("SELECT transaction_id FROM transaction_initiated"
                    . " WHERE `source`=:source AND `service_id`=:service_id "
                    . "AND `reference_id`=:reference_id", [':reference_id' => $unique_id
                , ':service_id' => $service_id, ':source' => $source]);
            if ($check_duplicate) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Duplicate Error'
                                , ['code' => 400, 'message' => 'Request Failed. Duplicate UniqueId Found!!']);
            }

            if (in_array($service_id, [4, 5])) {
                if (!$account_number) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Service Error'
                                    , ['code' => 400, 'message' => 'Request Failed. Invalid Electricity Account Number!']);
                }
            }
            if (!$account_number) {
                $account_number = $msisdn;
            }
            $xt = ['amount' => $purchase_amount,
                'account_number' => $account_number,
                'ip' => $ip_address,
                'source_address' => $source_address];

            if ($web_extra_data) {
                if (isset($web_extra_data->agent_service_id)) {
                    $xt['agent_service_id'] = $web_extra_data->agent_service_id;
                }

                if (isset($web_extra_data->agent_account_id)) {
                    $xt['agent_account_id'] = $web_extra_data->agent_account_id;
                }

                if (isset($web_extra_data->agent_package_discount_offer)) {
                    $xt['agent_package_discount_offer'] = $web_extra_data->agent_package_discount_offer;
                }

                if (isset($web_extra_data->agent_package_id)) {
                    $xt['agent_package_id'] = $web_extra_data->agent_package_id;
                }

                $web_extra_data->version = "0.00";
                $web_extra_data->browser = "$source_address";
                $web_extra_data->os = "$source";
            }
            $params = [
                'service_id' => $service_id,
                'profile_id' => Profiling::Profile($msisdn),
                'reference_id' => $unique_id,
                'source' => $source,
                'description' => $services[0]['service_description'],
                'extra_data' => json_encode($xt),];

            if ($source == 'MOBILE_APP') {
                if (isset($web_extra_data->reg_id)) {
                    $params['extra_data']['reg_id'] = $web_extra_data->reg_id;
                }

                if (isset($web_extra_data->push_url)) {
                    $params['extra_data']['push_url'] = $web_extra_data->push_url;
                }

                $web_extra_data = new stdClass();
                $web_extra_data->version = "0.00";
                $web_extra_data->browser = "$source_address";
                $web_extra_data->os = "Android";
            }
            $st = $this->getMicrotime();
            $transactionId = Transactions::Initiate($params);
            $stop = $this->getMicrotime() - $st;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stop Seconds"
                    . " | UniqueId:" . $unique_id
                    . " | Mobile:$msisdn"
                    . " | " . $services[0]['service_name']
                    . " | Transactions::Initiate Reponse:" . json_encode($transactionId));

            if (!$transactionId) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Duplicate Info"
                                , ['code' => 202, 'message' => 'Transaction is a Duplicate'], true);
            }
            $accNumber = "TAT" . $transactionId;
            if (in_array($source, ['WEB', 'USSD'])) {
                if ($service_id == 3 && $purchase_amount > 1000) {
                    return $this->success(__LINE__ . ":" . __CLASS__, "Transaction NOT allowed"
                                    , ['code' => 202, 'message' => 'Transaction NOT allowed'], true);
                }

                $checkout_payload = [
                    "apiKey" => $this->settings['ServiceApiKey'],
                    "amount" => "$purchase_amount",
                    "phoneNumber" => "$msisdn",
                    "callbackURL" => $this->settings['Mpesa']['CheckOutCallback'],
                    "paybillNumber" => $this->settings['Mpesa']['DefaultPaybillNumber'],
                    "transactionDesc" => "$accNumber",];
            }

            if ($source == 'CARD') {
                return $this->success(__LINE__ . ":" . __CLASS__, "Success",
                                ['code' => 200,
                                    'message' => 'Transction initiated successfully',
                                    'account_number' => $accNumber]);
            }
            $sts = $this->getMicrotime();
            $response = $this->sendJsonPostData($this->settings['Mpesa']['CheckOutUrl'], $checkout_payload);
            $stopped = $this->getMicrotime() - $sts;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stopped Seconds"
                    . " | UniqueId:" . $unique_id
                    . " | Mobile:$msisdn"
                    . " | " . $services[0]['service_name'] . " URL: " . $this->settings['Mpesa']['CheckOutUrl']
                    . " | TransactionId:" . $transactionId . " PAYLOAD: " . json_encode($checkout_payload)
                    . " | sendPostData Reponse:" . json_encode($response));

            $iserror = false;
            if ($response['statusCode'] != 200) {//|| $source == 'USSD') {
                $sms = [
                    'created_by' => $source,
                    'profile_id' => $params['profile_id'],
                    'msisdn' => $msisdn,
                    'short_code' => 'Madfun',
                    'message' => "Make Payment Via Mpesa Use below "
                    . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                    . "\nAmount:$purchase_amount\nAccount Number:$accNumber",
                    'is_bulk' => true,
                    'link_id' => '',];
                $sts = $this->getMicrotime();
                messaging::LogOutbox($sms);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | Took $stopped Seconds"
                        . " | UniqueId:" . $unique_id
                        . " | Mobile:$msisdn"
                        . " | " . $services[0]['service_name']
                        . " | TransactionId:" . $transactionId
                        . " | messaging::LogOutbox Reponse:" . json_encode($response));
                $iserror = true;
                if ($source == 'USSD') {
                    $iserror = false;
                }
            }

            $res = json_decode($response['response']);
            $statusDesc = isset($res->statusDescription) ? $res->statusDescription : "";
            if ($res->code != "Success") {
                $statusMessage = $statusDesc;
                if (isset($res->data)) {
                    $statusMessage = isset($res->data->message) ? $res->data->message : "";
                }

                return $this->success(__LINE__ . ":" . __CLASS__, "$statusDesc"
                                , ['code' => $response['statusCode'], 'message' => $statusMessage
                            , 'account_number' => $accNumber, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber'], 'amount' => $purchase_amount
                            , 'payment_info' => "Make Payment Via Mpesa Use below "
                            . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                , $iserror);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                            , ['code' => $response['statusCode'], 'message' => $statusDesc
                        , 'account_number' => $accNumber, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber'], 'amount' => $purchase_amount
                        , 'payment_info' => "Make Payment Via Mpesa Use below "
                        . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                        . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                            , $iserror);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:"
                    . " | " . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , "Internal Server Error !!");
        }
    }

    public function utilityCallback() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Airtime Callback Request:" . json_encode($data));
        $this->payload['unique_id'] = isset($data->unique_id) ? $data->unique_id : NULL;
        $this->payload['transaction_id'] = isset($data->transaction_id) ? $data->transaction_id : NULL;
        $this->payload['status'] = strtoupper(isset($data->status) ? $data->status : null);
        $this->payload['description'] = isset($data->description) ? $data->description : null;
        if (!$this->payload['status'] || !$this->payload['description'] ||
                !$this->payload['unique_id'] || !$this->payload['transaction_id']) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        $headers = $request->getHeaders();
        $auth['bearer_token'] = isset($headers['X-Authorization']) ? $headers['X-Authorization'] : false;
        $auth['requested_with'] = isset($headers['X-Requested-With']) ? $headers['X-Requested-With'] : false;
        $auth['origin'] = isset($headers['Origin']) ? $headers['Origin'] : false;

        $bearer = explode('Bearer', $auth['bearer_token']);
        $token = isset($bearer[1]) ? preg_replace('/[\t\n\r\s]+/', '', $bearer[1]) : false;
        if (!$token) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Invalid Token!');
        }

        try {
            if ((strpos($this->payload['unique_id'], 'TOKEA_AIRTIME') !== false)) {
                $unique = substr($this->payload['unique_id'], 3, strlen($this->payload['unique_id']));

                $unique_arr = explode('_', $unique);
                $retries = isset($unique_arr[1]) ? $unique_arr[1] : 0;
                $unique_id = isset($unique_arr[0]) ? $unique_arr[0] : false;

                $statement = "select * from transaction_callback "
                        . "WHERE transaction_id=:transaction_id "
                        . "AND receipt_number=:receipt_number";

                $statement_param = [
                    ':receipt_number' => $this->payload['transaction_id'],
                    ':transaction_id' => $unique_id];
                $result = $this->rawSelect($statement, $statement_param);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Retries:$retries"
                        . " | UniqueId:$unique_id"
                        . " | RecieptNumber:" . $this->payload['transaction_id']
                        . " | Results::" . json_encode($result));
                if (!$result) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'Request Failed'
                                    , ['code' => 404, 'message' => 'Invalid Request Posted']
                                    , true);
                }
                if ($result[0]['response_description'] == 'Callback Request is Successful') {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'Request is Successfu'
                                    , ['code' => 404, 'message' => 'Request was alreay a success']
                                    , true);
                }

                $response_description = "Callback Request is Successful";
                if ($this->payload['status'] == 3) {
                    $this->payload['status'] = 400;
                    $response_description = "Callback Request is Failed";
                    $narration = "Airtime Transaction Marked as Failed";
                }

                if ($this->payload['status'] == 200) {
                    $response_description = "Callback Request is Successful";
                    $narration = "Airtime Transaction Marked as Success";
                }

                if (Transactions::UpdateTransactionCallback($this->payload['status']
                                , $response_description, $narration
                                , $this->payload['transaction_id'], $result[0]['id'])) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Update Successfull', ['code' => 200
                                , 'message' => $narration]);
                }

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Update Failed', ['code' => 200
                            , 'message' => $narration]);
            }


            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Validation Failed', ['code' => 422
                        , 'message' => 'Invalid Request']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:" . $ex->getCode()
                    . " | Message:" . $ex->getMessage());

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Update Failed', ['code' => 500
                        , 'message' => "Internal Server Error."]);
        }
    }

    /**
     * RetryTransactionAction
     * @return type
     */
    public function utilityRetryTransactionAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | RetryTransactionAction Request:" . json_encode($data));

        $this->payload['service_id'] = isset($data->service_id) ? $data->service_id : NULL;
        $this->payload['source'] = isset($data->source) ? $data->source : NULL;

        $headers = $request->getHeaders();

        $auth['bearer_token'] = isset($headers['X-Authorization']) ? $headers['X-Authorization'] : false;
        $auth['requested_with'] = isset($headers['X-Requested-With']) ? $headers['X-Requested-With'] : false;
        $auth['origin'] = isset($headers['Origin']) ? $headers['Origin'] : false;

        $bearer = explode('Bearer', $auth['bearer_token']);
        $token = isset($bearer[1]) ? preg_replace('/[\t\n\r\s]+/', '', $bearer[1]) : false;

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$auth['requested_with'] || $auth['requested_with'] != 'XMLHttpRequest') {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed. Invalid Request!!']);
        }

        try {
            $auth = Authenticate
                    ::AutheticateRequest($this->payload['source'], preg_replace('/\s+/', '', $token));
            if (!$auth) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failed!!');
            }

            if (in_array($this->payload['service_id'], [4, 5])) {
                $statement = "select transaction_callback.id,transaction.transaction_id"
                        . ",transaction.profile_id,transaction.service_id,transaction_callback.retries"
                        . ",profile.msisdn,transaction.extra_data,transaction.source"
                        . ",transaction.description,transaction_callback.response_code"
                        . ",transaction.amount,transaction_callback.response_description"
                        . ",transaction_callback.narration,transaction_callback.receipt_number"
                        . ",transaction.created from transaction join transaction_callback"
                        . " on transaction.transaction_id =transaction_callback.transaction_id join profile "
                        . "on transaction.profile_id=profile.profile_id "
                        . "where transaction.service_id=:service_id "
                        . "and date(transaction.created)>=curdate()-7 "
                        . "AND transaction_callback.response_code in (400,402,500,502) ";
                $rs = [':service_id' => $this->payload['service_id']];
                $select_results = $this->rawSelect($statement, $rs);
                if (!$select_results) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'No Failed Transactions'
                                    , ['code' => 'Found No Failed records to process'], true);
                }

                $final_status = 200;
                $narration = 'Successfully Re-Tried Transaction';
                foreach ($select_results as $select_result) {
                    $select_result['retries']++;

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | amount:" . $select_result['amount']
                            . " | Failure Reason:" . $select_result['narration']);

                    $data_back = [
                        'previous_receipt_number' => $select_result['receipt_number'],
                        'previous_response_code' => $select_result['response_code'],
                        'previous_narration' => $select_result['narration'],];

                    $service_name = "";
                    $account_type = "";
                    if ($select_result['service_id'] == 2) {
                        $account_type = 1;
                        $service_name = "PrePaid Tokens";
                    } else {
                        $account_type = 2;
                        $service_name = "PostPaid Electricity";
                    }

                    $data_back = [
                        'previous_receipt_number' => $select_result['receipt_number'],
                        'previous_response_code' => $select_result['response_code'],
                        'previous_narration' => $select_result['narration'],];

                    if ($select_result['retries'] > 140) {
                        $final_status = 505;
                        $narration = "Max Retries reached for $service_name.";

                        $update_response = Transactions
                                ::UpdateRetries($select_result['transaction_id']
                                        , $select_result['receipt_number']
                                        , $final_status
                                        , $narration
                                        , 'Request Failed'
                                        , $select_result['id']
                                        , $data_back);

                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | Retry Request Request"
                                . " | CallBackId:" . $select_result['id']
                                . " | TrxnId:" . $select_result['transaction_id']
                                . " | Source:" . $select_result['source']
                                . " | Data Is Compromised .. Max Retries reached."
                                . " | Transactions::UpdateRetries =>$update_response");

                        continue;
                    }

                    $extra_data = json_decode($select_result['extra_data']);
                    $account_number = $extra_data->account_number;
                    $paid_msisdn = $extra_data->paid_msisdn;
                    $amount = $extra_data->amount;

                    $postData = [
                        'apiKey' => $this->settings['ServiceApiKey'],
                        'unique_id' => "MADFUN" . $select_result['transaction_id'] . "_" . $select_result['retries'],
                        'amount' => $select_result['amount'],
                        'msisdn' => $select_result['msisdn'],
                        'account_type' => "$account_type",
                        'bill_reference' => $extra_data->account_number,
                        'callback' => 'http://35.187.164.231/ticket-bay-api/api/utility/v1/callback'];

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | Service:$service_name"
                            . " | Eletricity Payload::" . json_encode($postData));

                    $result = $this->sendJsonPostData($this->settings['Rewards']['ElectricityUrl'], $postData);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | Service:$service_name"
                            . " | Fulfil Eletricity::" . json_encode($result));

                    $res = json_decode($result['response']);

                    if ($result['statusCode'] != 200) {
                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | Retry Request Request"
                                . " | CallBackId:" . $select_result['id']
                                . " | TrxnId:" . $select_result['transaction_id']
                                . " | Source:" . $select_result['source']
                                . " | Service:$service_name"
                                . " | ReQueue The Request");
                    }

                    $response_description = $res->statusDescription;
                    $data_response = isset($res->data) ? $res->data : false;
                    $receipt_number = isset($data_response->data->transaction_id) ? $data_response->data->transaction_id : "Failed#" . $select_result['transaction_id'] . "$" . $this->now('YmdHs');
                    $account_details = isset($data_response->data->account_details) ? $data_response->data->account_details : "";
                    $token = isset($data_response->data->token) ? $data_response->data->token : "";

                    $narration = isset($data_response->message) ? $data_response->message : $response_description;
                    $response_code = isset($data_response->code) ? $data_response->code : $result['statusCode'];

                    if ($response_code == 200) {
                        $id = Profiling::saveProfileAccounts(['service_id' => $select_result['service_id']
                                    , 'profile_id' => $select_result['profile_id']
                                    , "accounts" => $extra_data->account_number
                                    , "account_details" => $account_details]);

                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | Retry Request Request"
                                . " | CallBackId:" . $select_result['id']
                                . " | TrxnId:" . $select_result['transaction_id']
                                . " | Source:" . $select_result['source']
                                . " | Service:$service_name"
                                . " | Profiling::saveProfileAccounts::" . json_encode($id));

                        if ($account_type == 1) {
                            $narration .= ". With Token $token";
                        }
                    }

                    $update_response = Transactions::UpdateRetries($select_result['transaction_id']
                                    , $receipt_number
                                    , $response_code
                                    , $narration
                                    , $response_description
                                    , $select_result['id']
                                    , $data_back);
                    $this->infologger->addAlert(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | Update Response:" . $update_response);
                }

                return $this->success(__LINE__ . ":" . __CLASS__, 'Response is Successful'
                                , ['code' => '200', 'message' => 'Completed Successfully']);
            }

            $statement = "select transaction_callback.id,transaction.transaction_id"
                    . ",transaction.amount,transaction.source,transaction.extra_data"
                    . ",transaction_callback.narration,transaction_callback.retries "
                    . ",transaction_callback.response_code,transaction_callback.receipt_number from transaction_callback "
                    . "join transaction on transaction_callback.transaction_id=transaction.transaction_id "
                    . "where transaction.service_id in (SELECT service_id FROM services where service_id=:service_id) "
                    . "AND transaction_callback.response_code in (400,402,500,502) "
                    . "AND date(transaction_callback.created)>=CURDATE()-9 "
                    . "ORDER BY transaction_callback.id ASC";

            $rs = [':service_id' => $this->payload['service_id']];

            $select_results = $this->rawSelect($statement, $rs);
            if (!$select_results) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Failed Transactions'
                                , ['code' => 'Found No Failed records to process'], true);
            }

            $failed_count = count($select_results);
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Retry Request Request"
                    . " | Failed Count::$failed_count");
            if ($failed_count < 1) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Failed Transactions'
                                , ['code' => 'Found No Failed records to process for serviceId=>' . $this->payload['service_id']], true);
            }

            $final_status = 200;
            $narration = 'Successfully Re-Tried Transaction';
            foreach ($select_results as $select_result) {
                $select_result['retries']++;

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Retry Request Request"
                        . " | CallBackId:" . $select_result['id']
                        . " | TrxnId:" . $select_result['transaction_id']
                        . " | Source:" . $select_result['source']
                        . " | amount:" . $select_result['amount']
                        . " | Failure Reason:" . $select_result['narration']);

                $data_back = [
                    'previous_receipt_number' => $select_result['receipt_number'],
                    'previous_response_code' => $select_result['response_code'],
                    'previous_narration' => $select_result['narration'],];
                if ($select_result['retries'] > 20) {

                    $final_status = 505;
                    $narration = 'Max Retries reached.';

                    $update_response = Transactions::UpdateRetries($select_result['transaction_id']
                                    , $select_result['receipt_number']
                                    , $final_status
                                    , $narration
                                    , 'Request Failed'
                                    , $select_result['id']
                                    , $data_back);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | Data Is Compromised .. Max Retries reached."
                            . " | Transactions::UpdateRetries =>$update_response");

                    continue;
                }

                //{"transaction_id":"968","amount":50,"account_number":"0728931052","paid_msisdn":"254728931052"}

                $extra_data = json_decode($select_result['extra_data']);
                $account_number = $extra_data->account_number;
                $paid_msisdn = $extra_data->paid_msisdn;

                if (!$paid_msisdn) {
                    $final_status = 504;
                    $narration = 'Data Is Compromised .. Paid Mobile Mismatch';

                    $update_response = Transactions::UpdateRetries($select_result['transaction_id']
                                    , $select_result['receipt_number']
                                    , $final_status
                                    , $narration
                                    , 'Request Failed'
                                    , $select_result['id']
                                    , $data_back);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | Data Is Compromised .. Paid Mobile Mismatch."
                            . " | Transactions::UpdateRetries =>$update_response");
                    continue;
                }

                if (!$account_number) {
                    $final_status = 504;
                    $narration = 'Data Is Compromised .. Paid Mobile Mismatch';

                    $update_response = Transactions::UpdateRetries($select_result['transaction_id']
                                    , $select_result['receipt_number']
                                    , $final_status
                                    , $narration
                                    , 'Request Failed'
                                    , $select_result['id']
                                    , $data_back);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | Data Is Compromised .. Account Mismatch."
                            . " | Transactions::UpdateRetries =>$update_response");

                    //update here
                    continue;
                }

                $postData = [
                    'apiKey' => $this->settings['ServiceApiKey'],
                    'unique_id' => "MADFUN" . $select_result['transaction_id'] . "_" . $select_result['retries'],
                    'amount' => $select_result['amount'] + floor(0.03 * $select_result['amount']),
                    'msisdn' => $account_number,
                    'provider' => "",
                    'callback' => "http://35.187.164.231/ticket-bay-api/api/utility/v1/callback",];

//                if ($postData) {
//                    $this->infologger->addInfo(__LINE__ . ":" . __CLASS__
//                            . " | Retry Request Request"
//                            . " | CallBackId:" . $select_result['id']
//                            . " | TrxnId:" . $select_result['transaction_id']
//                            . " | Source:" . $select_result['source']
//                            . " | Request:" . json_encode($postData));
//                    continue;
//                }

                $result = $this->sendJsonPostData($this->settings['Rewards']['VasproAirtimeUrl'], $postData);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Retry Request Request"
                        . " | CallBackId:" . $select_result['id']
                        . " | TrxnId:" . $select_result['transaction_id']
                        . " | Source:" . $select_result['source']
                        . " | Request:" . json_encode($postData)
                        . " | Fulfil Airtime Response::" . json_encode($result));

                $res = json_decode($result['response']);

                if ($result['statusCode'] != 200) {
                    $this->infologger->addAlert(__LINE__ . ":" . __CLASS__
                            . " | Retry Request Request"
                            . " | CallBackId:" . $select_result['id']
                            . " | TrxnId:" . $select_result['transaction_id']
                            . " | Source:" . $select_result['source']
                            . " | ReQueue The Request");
                }

                $response_description = $res->statusDescription;
                $data_response = isset($res->data) ? $res->data : false;
                $receipt_number = isset($data_response->data->transaction_id) ?
                        $data_response->data->transaction_id :
                        "Failed#" . $select_result['transaction_id'];

                $narration = isset($data_response->message) ?
                        $data_response->message : $response_description;
                $response_code = isset($data_response->code) ?
                        $data_response->code : $result['statusCode'];

                $update_response = Transactions::UpdateRetries($select_result['transaction_id']
                                , $receipt_number
                                , $response_code
                                , $narration
                                , $response_description
                                , $select_result['id']
                                , $data_back);
                $this->infologger->addAlert(__LINE__ . ":" . __CLASS__
                        . " | Retry Request Request"
                        . " | CallBackId:" . $select_result['id']
                        . " | TrxnId:" . $select_result['transaction_id']
                        . " | Source:" . $select_result['source']
                        . " | Update Response:" . $update_response);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, 'Response is Successful'
                            , ['code' => '200', 'message' => 'Completed Successfully']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Retry Request Request"
                    . " | Exception Code:" . $ex->getCode()
                    . " | Exception String:" . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , "Internal Server Error.");
        }
    }

    /**
     * viewAccounts
     * @return type
     */
    public function viewAccounts() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Transaction Request:" . json_encode($request->getJsonRawBody()));

        $service_id = isset($data->service_id) ? $data->service_id : null;
        $source = isset($data->source) ? strtoupper($data->source) : null;
        $source_address = isset($data->source_address) ? $data->source_address : null;

        if (!$source_address || !$service_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        $headers = $request->getHeaders();

        $auth['bearer_token'] = isset($headers['X-Authorization']) ? $headers['X-Authorization'] : false;
        $auth['app_token'] = isset($headers['X-App-Key']) ? $headers['X-App-Key'] : false;
        $auth['requested_with'] = isset($headers['X-Requested-With']) ? $headers['X-Requested-With'] : false;
        $auth['origin'] = isset($headers['Origin']) ? $headers['Origin'] : false;

        $bearer = explode('Bearer', $auth['bearer_token']);
        $token = isset($bearer[1]) ? preg_replace('/[\t\n\r\s]+/', '', $bearer[1]) : false;

        $accessToken = isset($auth['app_token']) ? preg_replace('/[\t\n\r\s]+/', '', $auth['app_token']) : false;

        if (!$token || !$accessToken) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$auth['requested_with'] || $auth['requested_with'] != 'XMLHttpRequest') {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed. Invalid Request!!']);
        }
        try {
            $auth = new Authenticate();
            $auth_app = $auth->AutheticateRequest($source, preg_replace('/\s+/', '', $token));
            if (!$auth_app) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failed!!');
            }
            $auth_response = $auth->QuickTokenAuthenticate($accessToken);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User Authentication Failure.');
            }
            $services = $this->selectQuery("SELECT * FROM services WHERE service_id=:service_id "
                    . "AND status=:status"
                    , [':service_id' => $service_id, ':status' => 1]);

            if (!$services) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Service Error'
                                , ['code' => 400, 'message' => 'Request Failed. Invalid Service Specified!!']);
            }
            $ip_address = $this->getClientIPServer();
            if (!in_array($ip_address, ['35.187.93.149', '197.248.63.121'])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__ . ":"
                                . json_encode($request->getJsonRawBody())
                                , 'Un-authorised source!' . $ip_address .
                                '. UA:' . $request->getUserAgent());
            }

            $whereArray = [
                'profile_account.profile_id' => $auth_response['profile_id'],
                'profile_account.service_id' => $service_id];

            $searchQuery = $this->whereQuery($whereArray, "");
            $sql = "SELECT * FROM profile_account $searchQuery";
            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_end = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_end Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stop_end = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket Types results ($stop_end Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:"
                    . " | " . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , "Internal Server Error !!");
        }
    }

}
