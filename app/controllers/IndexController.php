<?php

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class IndexController extends ControllerBase {

    protected $payload;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->request = new Request();
    }

    /**
     * IPNPesapal
     * @return type
     */
    public function IPNPesapal() {
        $request = new Request();
        $start_time = $this->getMicrotime();

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | IPNPesapal:" . json_encode($this->request->get()) .
                "IPAddress:" . $this->getClientIPAddress());

        $OrderTrackingId = $this->request->get('OrderTrackingId');
        $OrderNotificationType = $this->request->get('OrderNotificationType');
        $OrderMerchantReference = $this->request->get('OrderMerchantReference');

        if (!$OrderTrackingId || !$OrderNotificationType || !$OrderMerchantReference) {
            $res = new \stdClass();
            $res->orderNotificationType = $OrderNotificationType;
            $res->orderTrackingId = $OrderTrackingId;
            $res->orderMerchantReference = $OrderMerchantReference;
            $res->status = 500;
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        }

        $duplicate = "SELECT id FROM dpo_transaction WHERE TransID=:TransID";

        $check_duplicate = $this->rawSelect($duplicate, [':TransID' => $OrderTrackingId]);
        if ($check_duplicate) {
            $res = new \stdClass();
            $res->orderNotificationType = $OrderNotificationType;
            $res->orderTrackingId = $OrderTrackingId;
            $res->orderMerchantReference = $OrderMerchantReference;
            $res->status = 500;
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        }

        try {
            if (!in_array($this->getClientIPAddress(), ['35.187.93.149', '135.181.143.126', '207.182.142.18'])) {
                $res = new \stdClass();
                $res->orderNotificationType = $OrderNotificationType;
                $res->orderTrackingId = $OrderTrackingId;
                $res->orderMerchantReference = $OrderMerchantReference;
                $res->status = 500;
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }

            $pesapal = new Pesapal();
            if (!$pesapal->queryPaymentStatus($OrderTrackingId)) {
                $res = new \stdClass();
                $res->orderNotificationType = $OrderNotificationType;
                $res->orderTrackingId = $OrderTrackingId;
                $res->orderMerchantReference = $OrderMerchantReference;
                $res->status = 500;
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }


            $dpoTransactionQuery = "INSERT INTO dpo_transaction (TransID,CCDapproval,"
                    . "account,TransactionToken,created) VALUES (:TransID,:CCDapproval,"
                    . ":account,:TransactionToken,NOW())";

            $paramsDPOtrans = [
                ':TransID' => $OrderTrackingId,
                ':CCDapproval' => $OrderNotificationType,
                ':account' => $OrderMerchantReference . "_" . $this->getClientIPAddress(),
                ':TransactionToken' => $OrderTrackingId
            ];
            $dpo_trxnId = $this->rawInsert($dpoTransactionQuery, $paramsDPOtrans);

            $ccountType = substr($OrderMerchantReference, 0, 3);
            $hasEventShows = 0;
            if (strtoupper($ccountType) == 'MOD') {
                $hasEventShows = 1;
            }

            $accountNumber = substr($OrderMerchantReference, 3);

            $select_trxn_initiated = "SELECT transaction_initiated.extra_data->>'$.amount' as amount, "
                    . "transaction_id,profile_id,service_id,reference_id,"
                    . "source,description,created FROM `transaction_initiated` WHERE "
                    . "`transaction_id`=:transaction_id LIMIT 1";
            $check_trxn = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $accountNumber]);

            if (!$check_trxn) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $OrderTrackingId
                        . " | PESAPAL Transaction Id:" . $dpo_trxnId
                        . " | DIRECT_DEPOSIT Transactions Empty Account "
                );
                $res = new \stdClass();
                $res->orderNotificationType = $OrderNotificationType;
                $res->orderTrackingId = $OrderTrackingId;
                $res->orderMerchantReference = $OrderMerchantReference;
                $res->status = 500;
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }

            $extra1 = [
                'paid_msisdn' => Profiling::QueryMobile($check_trxn[0]['profile_id']),
                'account_number' => $accountNumber,];

            $trx_params = [
                'amount' => $check_trxn[0]['amount'],
                'service_id' => $check_trxn[0]['service_id'],
                'profile_id' => $check_trxn[0]['profile_id'],
                'reference_id' => $dpo_trxnId,
                'source' => $check_trxn[0]['source'],
                'description' => $check_trxn[0]['description'],
                'extra_data' => json_encode($extra1),];

            Transactions::CreateTransaction($trx_params);

            $referenceID = $check_trxn[0]['reference_id'];

            if (strtoupper($ccountType) == 'STR') {

                $select_stream_profile = "select stream_profile_request.id, "
                        . "stream_profile_request.profile_id,stream_profile_request.order_key,"
                        . "stream_profile_request.currency,stream_profile_request.reference_id,"
                        . "stream_profile_request.returnURL,stream_profile_request.cancelURL,"
                        . "stream_profile_request.status,stream_profile_request.created, "
                        . "profile_attribute.first_name, profile_attribute.last_name, "
                        . "user.email from stream_profile_request join profile_attribute "
                        . "on stream_profile_request.profile_id  =profile_attribute.profile_id "
                        . "join user on stream_profile_request.profile_id  =  user.profile_id WHERE"
                        . " stream_profile_request.reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_stream_profile,
                        [':reference_id' => $referenceID]);

                if (!$check_trxn_profile) {
                    $res = new \stdClass();
                    $res->orderNotificationType = $OrderNotificationType;
                    $res->orderTrackingId = $OrderTrackingId;
                    $res->orderMerchantReference = $OrderMerchantReference;
                    $res->status = 500;
                    return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
                }

                $update_stream_profile = "update stream_profile_request set status = 1  WHERE"
                        . " id=:id";

                $this->rawUpdateWithParams($update_stream_profile,
                        [':id' => $check_trxn_profile[0]['id']]);
                $res = new \stdClass();
                $res->orderNotificationType = $OrderNotificationType;
                $res->orderTrackingId = $OrderTrackingId;
                $res->orderMerchantReference = $OrderMerchantReference;
                $res->status = 200;
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }

            $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                    . " reference_id=:reference_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':reference_id' => $referenceID]);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Payment Action Tickets Request:" . json_encode($check_trxn_profile));

            if (!$check_trxn_profile) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $OrderTrackingId
                        . " | DPO Transaction Id:" . $dpo_trxnId
                        . " | Event Ticket Profile Empty Account "
                );
                $res = new \stdClass();
                $res->orderNotificationType = $OrderNotificationType;
                $res->orderTrackingId = $OrderTrackingId;
                $res->orderMerchantReference = $OrderMerchantReference;
                $res->status = 500;
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }
            $amountPaid = $check_trxn[0]['amount'];
            $error = [];
            $success = [];
            foreach ($check_trxn_profile as $profileTrans) {
                if ($hasEventShows == 1) {
                    $check_evnt_type = $this->rawSelect("SELECT event_show_tickets_type.amount,"
                            . "event_show_tickets_type.discount,events.posterURL,"
                            . "event_show_tickets_type.group_ticket_quantity, "
                            . "event_show_tickets_type.status,ticket_types.ticket_type,"
                            . "event_show_tickets_type.event_show_venue_id,events.eventID AS eventId FROM "
                            . "event_show_tickets_type join event_show_venue on"
                            . " event_show_tickets_type.event_show_venue_id =  "
                            . "event_show_venue.event_show_venue_id join event_shows"
                            . " on event_show_venue.event_show_id  = "
                            . "event_shows.event_show_id join events on "
                            . "event_shows.eventID = events.eventID JOIN "
                            . "ticket_types ON ticket_types.typeId = event_show_tickets_type.typeId"
                            . " WHERE "
                            . "event_show_tickets_type.event_ticket_show_id"
                            . " = :event_ticket_show_id", [":event_ticket_show_id"
                        => $profileTrans['event_ticket_id']]);
                } else {
                    $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,event_tickets_type.discount,events.posterURL,event_tickets_type.group_ticket_quantity, "
                            . "event_tickets_type.status,ticket_types.ticket_type,event_tickets_type.eventId FROM"
                            . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                            . " = event_tickets_type.typeId JOIN events ON "
                            . "event_tickets_type.eventId = events.eventID WHERE event_tickets_type.event_ticket_id"
                            . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                }

                if (!$check_evnt_type) {
                    array_push($error, ['message' => 'There is no such Event '
                        . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if (($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount'])) > $amountPaid) {
                    array_push($error, ['message' => 'Failed to activate ticket, '
                        . 'Reason: Insufficient Fund', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if ($check_evnt_type[0]['group_ticket_quantity'] == 1) {
                    $amountPaid = $amountPaid - ($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount']));
                }
                $check_trn_profile_state = $this->rawSelect("select * from "
                        . "event_profile_tickets_state where "
                        . "event_profile_ticket_id =:event_profile_ticket_id",
                        [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                if (!$check_trn_profile_state) {
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | UniqueId:" . $OrderTrackingId
                            . " | DPO Transaction Id:" . $dpo_trxnId
                            . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                            . " | Record Not Found, Creating new record "
                            . "for Event Profile Ticket State "
                    );
                }
                $tickets = new Tickets();
                $paramsState = [
                    'status' => 1,
                    'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                ];

                $eventState = $tickets->ProfileTicketState($paramsState);
                if (!$eventState) {
                    array_push($error, ['message' => 'Failed. The ticket '
                        . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                $paramsUpdate = [
                    'event_ticket_id' => $profileTrans['event_ticket_id'],
                    'ticket_purchased' => 1,
                    'ticket_redeemed' => 0
                ];
                $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $profileTrans['event_tickets_option_id'], $hasEventShows);
                $paramEvent = [
                    'eventID' => $check_evnt_type[0]['eventId']
                ];

                $eventData = $tickets->queryEvent($paramEvent);

                array_push($success, [
                    'message' => 'Ticket Activated Successsful',
                    'QRCode' => $profileTrans['barcode'],
                    'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                    'eventName' => $eventData['eventName'],
                    'venue' => $eventData['venue'],
                    'start_date' => $eventData['dateStart'],
                    'QRCodeURL' => $profileTrans['barcodeURL'],
                    'posterURL' => $check_evnt_type[0]['posterURL'],
                    'ticketType' => $check_evnt_type[0]['ticket_type'],
                    'amount' => ($check_evnt_type[0]['amount'] - $check_evnt_type[0]['discount'])]);
            }

            $purchase_type = 'Beneficiary';
            if ($check_trxn_profile[0]['profile_id'] != $check_trxn[0]['profile_id']) {
                $purchase_type = 'Sponsor';
            }
            if (!$success) {

                $callback_data = [
                    'purchase_type' => $purchase_type,
                    'transaction_id' => $dpo_trxnId,
                    'response_code' => 402,
                    'response_description' => 'Processed Failed',
                    'extra_data' => json_encode($error),
                    'narration' => 'Failed to update event profile ticket state',
                    'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                $callback_id = Transactions::CreateTransactionCallback($callback_data);

                $res = new \stdClass();
                $res->orderNotificationType = $OrderNotificationType;
                $res->orderTrackingId = $OrderTrackingId;
                $res->orderMerchantReference = $OrderMerchantReference;
                $res->status = 500;
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }

            $callback_data = [
                'purchase_type' => $purchase_type,
                'transaction_id' => $dpo_trxnId,
                'response_code' => 200,
                'response_description' => 'Processed Successfully',
                'extra_data' => json_encode($success),
                'narration' => 'Processed Successfully',
                'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
            $callback_id = Transactions::CreateTransactionCallback($callback_data);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | UniqueId:" . $OrderTrackingId
                    . " | DPO Transaction Id:" . $dpo_trxnId
                    . " | Account:" . $accountNumber
                    . " | CreateTransactionCallback:$callback_id");

            $profileAttribute = Profiling::QueryProfileProfileId($check_trxn[0]['profile_id']);

            $sms = "";
            $ticketsData = [];
            foreach ($success as $succ) {

                $sms .= "Dear " . $profileAttribute['first_name'] . " " . $profileAttribute['last_name'] . ", Your " . $succ['eventName'] . " ticket "
                        . "is " . $succ['QRCode'] . ". View your ticket from "
                        . $succ['ticketURL'] . " \n";

                if ($profileAttribute['email'] != null) {
                    // sent email to clients
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Profile Attribute::" . json_encode($profileAttribute));

                    $ticketsIn = [
                        'ticketName' => $succ['ticketType'],
                        'currency' => 'KES',
                        'amount' => $succ['amount'],
                        'QrCode' => $succ['QRCode']
                    ];

                    array_push($ticketsData, $ticketsIn);
                }
            }
            if ($profileAttribute['email'] != null) {
                $paramsEmail = [
                    "eventID" => $success[0]['eventId'],
                    "orderNumber" => $OrderTrackingId,
                    "paymentMode" => "Card",
                    "name" => $profileAttribute['first_name'] . " "
                    . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
                    "eventDate" => $success[0]['start_date'],
                    "eventName" => $success[0]['eventName'],
                    "amountPaid" => $success[0]['amount'],
                    'msisdn' => $profileAttribute['msisdn'],
                    'ticketsArray' => $ticketsData,
                    'posterURL' => $success[0]['posterURL'],
                    'venue' => $success[0]['venue'],
                    'eventTicketInfo' => $success[0]['event_ticket_info'],
                ];
                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $profileAttribute['email'],
                    "from" => "noreply@madfun.com",
                    "cc" => "",
                    "subject" => "Ticket for Event: " . $success[0]['eventName'],
                    "content" => "Ticket information",
                    "extrac" => $paramsEmail
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                        $postData, $this->settings['ServiceApiKey'], 3);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailWithoutAttachments Response::" .
                        json_encode($mailResponse) . " Payload::" .
                        json_encode($postData));
            }
            $params = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                "msisdn" => $profileAttribute['msisdn'],
                "message" => $sms . ". Madfun! For Queries call "
                . "" . $this->settings['Helpline'],
                "profile_id" => $check_trxn[0]['profile_id'],
                "created_by" => 'DPO_PAYMENT',
                "is_bulk" => false,
                "link_id" => ""];

            $message = new Messaging();
            $message->LogOutbox($params);
            $res = new \stdClass();
            $res->orderNotificationType = $OrderNotificationType;
            $res->orderTrackingId = $OrderTrackingId;
            $res->orderMerchantReference = $OrderMerchantReference;
            $res->status = 200;
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:" . $ex->getCode()
                    . " | Exception Code:" . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Internal Server Error.', ['code' => 500
                        , 'message' => $ex->getMessage()], true);
        }
    }

    /**
     * callbackStream
     * @return type
     */
    public function callbackStream() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | CallbackStream:" . json_encode($request->getJsonRawBody()));

        return $this->success(__LINE__ . ":" . __CLASS__
                        , "Event ID Not Found"
                        , ['code' => 200, 'Message' =>
                    'EventId Not Found']);
    }

    /**
     * viewCodeStatus
     * @return type
     */
    public function viewCodeStatus() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger - info(__LINE__ . ":" . __CLASS__
                        . " | View Code Request:" . json_encode($request->getJsonRawBody()));

        $code = isset($data->code) ? $data->code : null;
        $eventId = isset($data->eventId) ? $data->eventId : null;
        $source = isset($data->source) ? strtoupper($data->source) : null;

        if (!$source) {
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

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$auth['requested_with'] || $auth['requested_with'] != 'XMLHttpRequest') {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed. Invalid Request!!']);
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
            if ($accessToken) {
                $auth_response = $auth->QuickTokenAuthenticate($accessToken);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'User Authentication Failure.');
                }
            }
            if ($code != null) {
                $codeResponse = Codes::queryCode($code);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | View Code Request:" . json_encode($codeResponse));

                if ($codeResponse['response_code'] != 200) {

                    $stop_end = $this->getMicrotime() - $start_time;
                    return $this->success(__LINE__ . ":" . __CLASS__, $codeResponse['message'], [
                                'code' => $codeResponse['response_code']
                                , 'message' => "Query returned no results "
                                . "( $stop_end Seconds)", 'data' => $codeResponse
                                , 'record_count' => 0], true);
                }
                $stop_end = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Ok'
                                , ['code' => $codeResponse['response_code']
                            , 'message' => "Successfully Queried Code Types results ($stop_end Seconds)"
                            , 'record_count' => 1, 'data' => $codeResponse['data']]);
            } else {

                $checkEvents = Events::findFirst([
                            "eventID =:eventID: OR eventTag=:eventID: ",
                            "bind" => [
                                "eventID" => $eventId],]);
                if (!$checkEvents) {
                    $stop_end = $this->getMicrotime() - $start_time;
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event ID Not Found"
                                    , ['code' => 404, 'Message' =>
                                'EventId Not Found', 'record_count' => 0,
                                'data' => []], true);
                }
                if ($checkEvents->status != 1) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event ID Not Found"
                                    , ['code' => 404, 'Message' =>
                                'EventId Not Found', 'record_count' => 0,
                                'data' => []], true);
                }
                if (!in_array($checkEvents->eventID, [295])) {
                    $stop_end = $this->getMicrotime() - $start_time;
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event ID Not Found"
                                    , ['code' => 404, 'Message' =>
                                'EventId Not Found', 'record_count' => 1,
                                'data' => []], true);
                }
                $stop_end = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Ok'
                                , ['code' => 200
                            , 'message' => "Successfully Queried events results ($stop_end Seconds)"
                            , 'record_count' => 1, 'event_name' => $checkEvents->eventName, 'isSoldOut' => $checkEvents->soldOut,
                            'aboutEvent' => $checkEvents->aboutEvent, 'posterURL' => $checkEvents->posterURL,
                            'event_image' => $checkEvents->posterURL]);
            }
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:"
                    . " | " . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , "Internal Server Error !!");
        }
    }

    /**
     * redemmedCodeTicket
     * @return type
     * @throws Exception
     */
    public function redemmedCodeTicket() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Code Request:" . json_encode($request->getJsonRawBody()));

        $code = isset($data->code) ? $data->code : null;
        $eventId = isset($data->eventId) ? $data->eventId : null;
        $phone = isset($data->phone) ? $data->phone : null;
        $email = isset($data->email) ? $data->email : null;
        $name = isset($data->name) ? $data->name : null;
        $ticketInfo = isset($data->ticketInfo) ? $data->ticketInfo : null;
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $source = isset($data->source) ? strtoupper($data->source) : null;
        $service_id = isset($data->service_id) ? $data->service_id : 6;
        $affiliatorCode = isset($data->affiliatorCode) ? $data->affiliatorCode : null;
        if (!$source || !$phone || !$email || !$name) {
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

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$auth['requested_with'] || $auth['requested_with'] != 'XMLHttpRequest') {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed. Invalid Request!!']);
        }

        $ip_address = $this->getClientIPServer();
        if (!in_array($ip_address, ['35.187.93.149', '197.248.63.121'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__ . ":"
                            . json_encode($request->getJsonRawBody())
                            , 'Un-authorised source!' . $ip_address .
                            '. UA:' . $request->getUserAgent());
        }

        $names = explode(" ", $name);

        $msisdn = $this->formatMobileNumber($phone, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
        if ($network == "UNKNOWN") {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
        }
        try {
            $auth = new Authenticate();
            $auth_app = $auth->AutheticateRequest($source, preg_replace('/\s+/', '', $token));
            if (!$auth_app) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failed!!');
            }
            if ($accessToken) {
                $auth_response = $auth->QuickTokenAuthenticate($accessToken);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'User Authentication Failure.');
                }
            }

            if ($code != null) {
                $codeResponse = Codes::queryCode($code);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | View Code Request:" . json_encode($codeResponse));

                if ($codeResponse['response_code'] != 200) {

                    $stop_end = $this->getMicrotime() - $start_time;
                    return $this->error(__LINE__ . ":" . __CLASS__, $codeResponse['message'], [
                                'code' => $codeResponse['response_code']
                                , 'message' => "Query returned no results ( $stop_end Seconds)", 'data' => $codeResponse
                                , 'record_count' => 0]);
                }
                $event_ticket_id = $codeResponse['data']['event_ticket_id'];
                $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                            "bind" => ["event_ticket_id" => $event_ticket_id],]);
                if (!$checkEventTicketID) {

                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
                }
                if ($checkEventTicketID->total_ticket_code <= 0) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__, "No Codes Mapped to event ticket Type"
                                    , ['code' => 422, 'message' => 'Kindly contact system admin for guidance']
                    );
                }
                if ($checkEventTicketID->issued_ticket_code >= $checkEventTicketID->total_ticket_code) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__, "All Codes have been issued."
                                    , ['code' => 422, 'message' => 'There no more codes to issue']
                    );
                }
            }
            if ($eventId != null) {

                $checkEvents = Events::findFirst([
                            "eventID =:eventID: OR eventTag=:eventID: ",
                            "bind" => [
                                "eventID" => $eventId],]);
                if (!$checkEvents) {
                    $stop_end = $this->getMicrotime() - $start_time;
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event ID Not Found"
                                    , ['code' => 404, 'Message' =>
                                'EventId Not Found', 'record_count' => 0, 'data' => []], true);
                }

                $checkEventTicketID = EventTicketsType::findFirst(["eventId=:eventId:",
                            "bind" => ["eventId" => $checkEvents->eventID]]);
                if ($checkEventTicketID->total_tickets <= 0) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__, "No Tickets Mapped to event ticket Type"
                                    , ['code' => 422, 'message' => 'Kindly contact system admin for guidance']
                    );
                }

                if ($checkEvents->eventID != 295) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event ID Not Found"
                                    , ['code' => 404, 'Message' =>
                                'EventId Not Found  ' . $checkEvents->eventID, 'record_count' => 0, 'data' => []], true);
                }
                if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__, "All Tickets have been issued."
                                    , ['code' => 422, 'message' => 'There no more codes to issue']
                    );
                }
                $sql = "SELECT * FROM event_profile_tickets WHERE profile_id=:profile_id AND"
                        . " event_ticket_id=:event_ticket_id";
                $result = $this->rawSelect($sql, [':profile_id' => Profiling::Profile($msisdn),
                    ':event_ticket_id' => $checkEventTicketID->event_ticket_id]);
                if ($result) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__, "You "
                                    . "already have ticket issued. Kindly login to www.madfun.com"
                                    . " to view your ticket."
                                    , ['code' => 422, 'message' => 'You already '
                                . 'have ticket issued. Kindly login to '
                                . 'www.madfun.com to view your ticket']
                    );
                }
            }

            $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => Profiling::Profile($msisdn)]]);
            $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
            $len = rand(1000000, 99999999999);
            $payloadToken = ['data' => $len . "" . $this->now()];
            $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));

            $verification_code = rand(1000, 9999);
            $password = $this->security->hash(md5($verification_code));
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                if (!$checkProfileAttrinute) {
                    $profileAttribute = new ProfileAttribute();
                    $profileAttribute->network = $this->getMobileNetwork($msisdn);
                    $profileAttribute->pin = md5($verification_code);
                    $profileAttribute->profile_id = Profiling::Profile($msisdn);
                    $profileAttribute->first_name = $names[0];
                    if (count($names) > 1) {
                        $profileAttribute->last_name = $names[1];
                    }
                    $profileAttribute->created = $this->now();
                    $profileAttribute->created_by = 1;
                    if ($profileAttribute->save() === false) {
                        $errors = [];
                        $messages = $profileAttribute->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
                    }
                }
                $checkUser = User::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => Profiling::Profile($msisdn)]]);
                $user_id = isset($checkUser->user_id) ?
                        $checkUser->user_id : false;
                if (!$user_id) {
                    $user = new User();
                    $user->setTransaction($dbTrxn);
                    $user->profile_id = Profiling::Profile($msisdn);
                    $user->email = $email;
                    $user->role_id = 5;
                    $user->api_token = $newToken;
                    $user->password = $password;
                    $user->status = 1;
                    $user->created = $this->now();
                    if ($user->save() === false) {
                        $errors = [];
                        $messages = $user->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create User failed " . json_encode($errors));
                    }
                    $user_id = $user->user_id;
                }


                $checkUserLogin = UserLogin::findFirst(['user_id=:user_id:'
                            , 'bind' => ['user_id' => $user_id]]);
                if (!$checkUserLogin) {
                    $UserLogin = new UserLogin();
                    $UserLogin->setTransaction($dbTrxn);
                    $UserLogin->created = $this->now();
                    $UserLogin->user_id = $user_id;
                    $UserLogin->login_code = md5($verification_code);
                    if ($UserLogin->save() === false) {
                        $errors = [];
                        $messages = $UserLogin->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                    }
                    $userState = 1;
                } else {
                    $userState = 0;
                }
                $dbTrxn->commit();
            } catch (Exception $ex) {
                throw $ex;
            }
            if ($checkEventTicketID->isPartialPay == 1) {

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
                                    , ['code' => 400, 'message' => 'Request Failed. Duplicate UniqueId Found!!'], true);
                }
                $error = [];
                $purchase_amount = 0;
                foreach ($eventData as $event) {
                    $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                                "bind" => ["event_ticket_id" => $event->id],]);
                    if (!$checkEventTicketID) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Invalid Event ticket Id"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    if (!is_numeric($event->quantity)) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Quantity has to be numeric"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Event Ticket Sold Out"
                        ];
                        array_push($error, $errorMessage);
                        continue;
                    }
                    $quantity = $event->quantity * $checkEventTicketID->group_ticket_quantity;

                    $purchase_amount = $purchase_amount + ($event->quantity * ($checkEventTicketID->amount - $checkEventTicketID->discount));

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Ticket Quanity:" . $quantity);

                    for ($i = 1; $i <= $quantity; $i++) {
                        $t = time();
                        $len = rand(1000000, 9999999) . "" . $t;
                        $paramsTickets = [
                            'profile_id' => Profiling::Profile($msisdn),
                            'event_ticket_id' => $event->id,
                            'reference_id' => $unique_id,
                            'reference' => $codeResponse["data"]["id"],
                            'barcode' => $len,
                            'discount' => $checkEventTicketID->discount,
                            'barcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8'
                        ];

                        $tickets = new Tickets();
                        $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets, 0, null, null, 0, $ticketInfo);

                        if (!$event_profile_ticket_id) {
                            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                            , 'Validation Error'
                                            , ['code' => 422, 'message' => 'Failed to create ticket']);
                        }

                        $paramsState = [
                            'status' => 0,
                            'event_profile_ticket_id' => $event_profile_ticket_id,
                        ];

                        $tickets->ProfileTicketState($paramsState);
                    }
                }
                if (count($error) > 0) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'error' => $error]);
                }

                $ip_address = $this->getClientIPServer();

                $xt = ['amount' => $purchase_amount,
                    'unique_id' => $unique_id,
                    'ip' => $ip_address,];

                $params = [
                    'service_id' => $service_id,
                    'profile_id' => Profiling::Profile($msisdn),
                    'reference_id' => $unique_id,
                    'source' => $source,
                    'description' => $services[0]['service_description'],
                    'extra_data' => json_encode($xt),];

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
                $accNumber = "MAD" . $transactionId;

                if ($network != 'SAFARICOM') {

                    return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                    , ['code' => 200, 'message' => "Initiating Mobile not Safaricom."
                                , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                    );
                }
                $checkout_payload = [
                    "apiKey" => $this->settings['ServiceApiKey'],
                    "amount" => "$purchase_amount",
                    "phoneNumber" => "$msisdn",
                    "callbackURL" => $this->settings['Mpesa']['CheckOutCallback'],
                    "paybillNumber" => $this->settings['Mpesa']['DefaultPaybillNumber'],
                    "transactionDesc" => "$accNumber",];

                $sts = $this->getMicrotime();
                $response = $this->sendJsonPostData($this->settings['Mpesa']['CheckOutUrl'], $checkout_payload);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Took $stopped Seconds"
                        . " | UniqueId:" . $unique_id
                        . " | Mobile:$msisdn"
                        . " | " . $services[0]['service_name']
                        . " | TransactionId:" . $transactionId
                        . " | Account Encrpty: " . $accNumber
                        . " | sendPostData Reponse:" . json_encode($response));

                $iserror = false;

                if ($response['statusCode'] != 200) {//|| $source == 'USSD') {
                    $sms = [
                        'created_by' => $source,
                        'profile_id' => $params['profile_id'],
                        'msisdn' => $msisdn,
                        'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                        'message' => "Make Payment Via Mpesa Use below "
                        . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                        . "\nAmount:$purchase_amount\nAccount Number:$accNumber",
                        'is_bulk' => true,
                        'link_id' => '',];
                    $sts = $this->getMicrotime();
                    $message = new Messaging();
                    $queueMessageResponse = $message->LogOutbox($sms);
                    $stopped = $this->getMicrotime() - $sts;
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | Took $stopped Seconds"
                            . " | UniqueId:" . $unique_id
                            . " | Mobile:$msisdn"
                            . " | " . $services[0]['service_name']
                            . " | TransactionId:" . $transactionId
                            . " | Account Encrpty: " . $auth->Encrypt($transactionId)
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
                                , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                    , $iserror);
                }

                return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                , ['code' => $response['statusCode'], 'message' => $statusDesc
                            , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                            , 'payment_info' => "Make Payment Via Mpesa Use below "
                            . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                , $iserror);
            } else {
                if ($code != null) {
                    $extra1 = [
                        'msisdn' => $msisdn,
                        'code' => $code,];
                    $trx_params = [
                        'amount' => 0,
                        'service_id' => 6,
                        'profile_id' => Profiling::Profile($msisdn),
                        'reference_id' => $codeResponse["data"]["id"],
                        'source' => $source,
                        'description' => "TRANS_TICKET_" . $code,
                        'extra_data' => json_encode($extra1),];
                } else {
                    $extra1 = [
                        'msisdn' => $msisdn,
                        'eventId' => $eventId,];
                    $trx_params = [
                        'amount' => 0,
                        'service_id' => 6,
                        'profile_id' => Profiling::Profile($msisdn),
                        'reference_id' => Profiling::Profile($msisdn) . "" . rand(1000000, 99999999999999),
                        'source' => $source,
                        'description' => "TRANS_TICKET_EVENT_" . $eventId,
                        'extra_data' => json_encode($extra1),];
                }


                $transactionID = Transactions::CreateTransaction($trx_params);

                $sqlEventType = "SELECT ticket_types.ticket_type from ticket_types where typeId=:typeId";
                $paramsData = [
                    ':typeId' => $checkEventTicketID->typeId
                ];
                $eventType = $this->rawSelect($sqlEventType, $paramsData);
                $t = time();
                $QRCode = rand(1000000, 99999999999999) . "" . $t;
                $barCode = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $QRCode . '&choe=UTF-8';
                $paramsTickets = [
                    'profile_id' => Profiling::Profile($msisdn),
                    'event_ticket_id' => $checkEventTicketID->event_ticket_id,
                    'reference_id' => Profiling::Profile($msisdn) . "" . $QRCode,
                    'reference' => Profiling::Profile($msisdn) . "" . rand(1000000, 99999999999999),
                    'barcode' => $QRCode,
                    'barcodeURL' => $barCode
                ];

                $tickets = new Tickets();
                $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets, 0, null, null, 0, $ticketInfo);
                if (!$event_profile_ticket_id) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Failed to create ticket']);
                }
                $paramsState = [
                    'status' => 1,
                    'event_profile_ticket_id' => $event_profile_ticket_id,
                ];

                $tickets->ProfileTicketState($paramsState);

                $paramsUpdate = [
                    'event_ticket_id' => $checkEventTicketID->event_ticket_id,
                    'ticket_purchased' => 1,
                    'ticket_redeemed' => 0
                ];
                $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate);
                if (!$eventUpdateTicket) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Failed to update ticket status.Kindly contact admin'
                                    , ['code' => 422, 'message' => 'Failed to create ticket']);
                }
//                if ($code != null) {
//                    $paramsRedemeed = [
//                        "code_id" => $codeResponse["data"]["id"],
//                        "transaction_id" => $transactionID,
//                        "event_ticket_id" => $codeResponse['data']['event_ticket_id']
//                    ];
//
//                    Codes::redemeedCode($paramsRedemeed);
//                }

                if ($affiliatorCode != null) {
                    $checkAffiliator = AffiliatorEventMap::findFirst(['code=:code: AND eventId=:eventId:'
                                , 'bind' => ['code' => $affiliatorCode, 'eventId' => $checkEventTicketID->eventId]]);
                    if ($checkAffiliator) {
                        $paramsAffiliator = [
                            'event_profile_ticket_id' => $event_profile_ticket_id,
                            'affiliator_event_map_id' => $checkAffiliator->id
                        ];
                        $tickets->affiliatorSales($paramsAffiliator);
                    }
                }

                $paramEvent = [
                    'eventID' => $checkEventTicketID->eventId
                ];

                $eventData = $tickets->queryEvent($paramEvent);

                $sms = "Dear " . $name . ",\nFind ticket(s) for" . $eventData['eventName'] . ""
                        . "\n\n1: Link:" . $this->settings['TicketBaseURL'] . "\nCode:" . $QRCode . ""
                        . "\nHelpline " . $this->settings['Helpline'];

                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $sms,
                    "profile_id" => Profiling::Profile($msisdn),
                    "created_by" => 'REDEEMED_CODE',
                    "is_bulk" => false,
                    "link_id" => ""];

                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($params);
                $smsStatus = false;
                if ($queueMessageResponse) {
                    $smsStatus = true;
                }

                if ($email != null) {
                    $ticketType = TicketTypes::findFirst(["typeId=:typeId:",
                                "bind" => ["typeId" => $checkEventTicketID->typeId],]);

                    $checkEvents = Events::findFirst(["eventID=:eventID:",
                                "bind" => ["eventID" => $checkEventTicketID->eventId],]);
                    $paramsEmail = [
                        "eventID" => $checkEventTicketID->eventId,
                        "type" => "TICKET_REDEEMED_CODE",
                        "name" => $name,
                        "eventDate" => $checkEvents->start_date,
                        "eventName" => $checkEvents->eventName,
                        "eventAmount" => $checkEventTicketID->amount,
                        'eventType' => $ticketType->ticket_type,
                        'QRcodeURL' => $barCode,
                        'QRcode' => $QRCode,
                        'posterURL' => $checkEvents->posterURL,
                        'venue' => $checkEvents->venue
                    ];
                    $postData = [
                        "api_key" => $this->settings['ServiceApiKey'],
                        "to" => $email,
                        "from" => "noreply@madfun.com",
                        "cc" => "",
                        "subject" => "Ticket for Event: " . $checkEvents->eventName,
                        "content" => "Ticket information",
                        "extrac" => $paramsEmail
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailTickets Response::" . json_encode($mailResponse) . "" . $checkEventTicketID->typeId . "- " . $ticketType->ticket_type);
                }
                $paramsTicket = [
                    'event_ticket_id' => $checkEventTicketID->event_ticket_id
                ];
                $ticketInfo = Tickets::queryEventTicketType($paramsTicket);
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 200
                            , 'message' => "Ticket sent successful", 'event' => $checkEvents,
                            'ticketInfo' => [$ticketInfo]]);
            }
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:"
                    . " | " . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , "Internal Server Error !!");
        }
    }

    /**
     * changeEvents
     * @return type
     */
    public function changeEvents() {

        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Change Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_ticket_id_current = isset($data->event_ticket_id_current) ? $data->event_ticket_id_current : null;
        $event_ticket_id_new = isset($data->event_ticket_id_new) ? $data->event_ticket_id_new : null;
        $hasCurrentEventTicketShow = isset($data->hasCurrentEventTicketShow) ? $data->hasCurrentEventTicketShow : false;
        $hasNewEventTicketShow = isset($data->hasNewEventTicketShow) ? $data->hasNewEventTicketShow : false;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $barcode = isset($data->barcode) ? $data->barcode : null;
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $source = isset($data->source) ? $data->source : null;
        if (!$token || !$source || !$event_ticket_id_current || !$phone || !$unique_id || !$event_ticket_id_new) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, ['WEB'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        $msisdn = $this->formatMobileNumber($phone, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
        if ($network == "UNKNOWN") {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => Profiling::Profile($msisdn)]]);
            if (!$checkProfileAttrinute) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Phone number not registred']);
            }
            if ($hasCurrentEventTicketShow) {
                $checkEventTicketID = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                            "bind" => ["event_ticket_show_id" => $event_ticket_id_current],]);
                if (!$checkEventTicketID) {

                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
                }
            } else {
                $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                            "bind" => ["event_ticket_id" => $event_ticket_id_current],]);
                if (!$checkEventTicketID) {

                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
                }
            }
            if ($hasNewEventTicketShow) {

                $checkEventTicketIDNew = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                            "bind" => ["event_ticket_show_id" => $event_ticket_id_new],]);

                if (!$checkEventTicketIDNew) {

                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid New Event ticket Id']);
                }
                $checkEventShowVenue = EventShowVenue::findFirst(["event_show_venue_id=:event_show_venue_id:",
                            "bind" => ["event_show_venue_id" => $checkEventTicketIDNew->event_show_venue_id],]);
                if (!$checkEventShowVenue) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Event show venue  '
                                . 'not configured properly consult system admin']);
                }
                $checkEventShow = EventShows::findFirst(["event_show_id=:event_show_id:",
                            "bind" => ["event_show_id" => $checkEventShowVenue->event_show_id],]);
                if (!$checkEventShow) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Event show   '
                                . 'not configured properly consult system admin']);
                }
                $eventID = $checkEventShow->eventID;
            } else {

                $checkEventTicketIDNew = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                            "bind" => ["event_ticket_id" => $event_ticket_id_new],]);

                if (!$checkEventTicketIDNew) {

                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid New Event ticket Id']);
                }

                $eventID = $checkEventTicketIDNew->eventId;
            }


            $paramEvent = [
                'eventID' => $eventID
            ];
            $tickets = new Tickets();

            $eventData = $tickets->queryEvent($paramEvent);

            if ($barcode) {
                $statement_barcode = "SELECT * FROM event_profile_tickets WHERE "
                        . "event_profile_tickets.barcode = :barcode";

                $statement_param_barcode = [
                    ":barcode" => $barcode];

                $resultBar = $this->rawSelect($statement_barcode, $statement_param_barcode);
                if (!$resultBar) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Barcode']);
                }
                $sqlUpdate = "UPDATE event_profile_tickets SET event_ticket_id =:event_ticket_id, isShowTicket =0 "
                        . "WHERE barcode = :barcode limit 1";

                if ($hasNewEventTicketShow) {
                    $sqlUpdate = "UPDATE event_profile_tickets SET event_ticket_id =:event_ticket_id, isShowTicket=1 "
                            . "WHERE barcode = :barcode limit 1";
                }


                $selectParamsOld = [
                    ':barcode' => $barcode,
                    ':event_ticket_id' => $event_ticket_id_new
                ];

                $updateStatus = $this->rawUpdateWithParams($sqlUpdate, $selectParamsOld);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Change Ticket Types Request:" . json_encode($selectParamsOld));
                if ($updateStatus) {
                    $sms = "Dear " . $checkProfileAttrinute->first_name . ", Your ticket has been change to event: " . $eventData['eventName'] . "  "
                            . "Find below ticket\n 1: " . $this->settings['TicketBaseURL'] . "?evtk=" . $barcode . ".\nFor Queries call "
                            . "" . $this->settings['Helpline'];

                    $params = [
                        "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                        "msisdn" => $msisdn,
                        "message" => $sms,
                        "profile_id" => Profiling::Profile($msisdn),
                        "created_by" => 'CHANGE',
                        "is_bulk" => false,
                        "link_id" => ""];

                    $message = new Messaging();
                    $message->LogOutbox($params);

                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Ticket Updated Successful', ['code' => 200
                                , 'message' => "Ticket Updated Successful"]);
                }
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Failed '
                                , ['code' => 403, 'message' => 'Failed to update ticket']);
            }

            $statement = "SELECT event_profile_tickets.event_profile_ticket_id, event_profile_tickets.barcode"
                    . " from event_profile_tickets WHERE"
                    . " event_profile_tickets.profile_id = :profile_id AND "
                    . "event_profile_tickets.event_ticket_id = :event_ticket_id ";

            $statement_param = [
                ":profile_id" => Profiling::Profile($msisdn),
                ":event_ticket_id" => $event_ticket_id_current];

            $result = $this->rawSelect($statement, $statement_param);
            if (!$result) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 404, 'message' => 'No Ticket Found']);
            }
            foreach ($result as $data) {
                if ($hasNewEventTicketShow) {
                    $sqlUpdate = "UPDATE event_profile_tickets SET event_ticket_id =:event_ticket_id,"
                            . " isShowTicket=1  "
                            . "WHERE event_profile_ticket_id = :event_profile_ticket_id ";
                    $selectParamsOld = [
                        ':event_profile_ticket_id' => $data['event_profile_ticket_id'],
                        ':event_ticket_id' => $event_ticket_id_new
                    ];
                } else {
                    $sqlUpdate = "UPDATE event_profile_tickets SET event_ticket_id =:event_ticket_id "
                            . "WHERE event_profile_ticket_id = :event_profile_ticket_id ";
                    $selectParamsOld = [
                        ':event_profile_ticket_id' => $data['event_profile_ticket_id'],
                        ':event_ticket_id' => $event_ticket_id_new
                    ];
                }
            }


            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Add Ticket Types Request:" . json_encode($selectParamsOld));
            $updateStatus = $this->rawUpdateWithParams($sqlUpdate, $selectParamsOld);

            if ($updateStatus) {
                /// send sms
                $sms = "Dear " . $checkProfileAttrinute->first_name . ", Your " . $eventData['eventName'] . " ticket "
                        . " has been changed. View your ticket from "
                        . $this->settings['TicketBaseURL'] . "?evtk=" . $result[0]['barcode'] . "  Madfun! For Queries call "
                        . "" . $this->settings['Helpline'];

                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $sms,
                    "profile_id" => Profiling::Profile($msisdn),
                    "created_by" => 'COMPLIMENTARY',
                    "is_bulk" => false,
                    "link_id" => ""];

                $message = new Messaging();
                $message->LogOutbox($params);

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Updated Successful', ['code' => 200
                            , 'message' => "Ticket Updated Successful"]);
            }
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Failed '
                            , ['code' => 403, 'message' => 'Failed to update ticket']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * complimentTickets
     * @return type
     */
    public function complimentTickets() {

        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $name = isset($data->name) ? $data->name : null;
        $company = isset($data->company) ? $data->company : null;
        $email = isset($data->email) ? $data->email : null;
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $source = isset($data->source) ? $data->source : null;
        $reference = isset($data->reference) ? $data->reference : "MADFUN";
        $sendSMSEmail = isset($data->sendSMSEmail) ? $data->sendSMSEmail : 1;
        $quantity = isset($data->quantity) ? $data->quantity : 1;
        $show = isset($data->show) ? $data->show : 0;
        if (!$token || !$source || !$event_ticket_id || !$phone || !$unique_id || !$name || !$quantity) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, ['WEB'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        $names = explode(" ", $name);

        $msisdn = $this->formatMobileNumber($phone, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
        if ($network == "UNKNOWN") {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
        }
        try {
            $auth = new Authenticate();
            $tickets = new Tickets();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['role_id'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'You are not authorised to send complimentarty');
            }
            if ($show == 1) {
                $checkEventTicketID = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                            "bind" => ["event_ticket_show_id" => $event_ticket_id],]);
                if (!$checkEventTicketID) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
                }
                $checkEventShowVenue = EventShowVenue::findFirst(["event_show_venue_id=:event_show_venue_id:",
                            "bind" => ["event_show_venue_id" => $checkEventTicketID->event_show_venue_id],]);
                if (!$checkEventShowVenue) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
                }
                $checkEventShow = EventShows::findFirst(["event_show_id=:event_show_id:",
                            "bind" => ["event_show_id" => $checkEventShowVenue->event_show_id],]);
                $paramEvent = [
                    'eventID' => $checkEventShow->eventID
                ];

                $eventData = $tickets->queryEvent($paramEvent);

                $eventVenue = $checkEventShowVenue->venue;
                $eventName = $eventData['eventName'] . " - " . $checkEventShow->show;
                $eventPosterURL = $eventData['posterURL'];
                $eventStartTime = $checkEventShow->start_date;
                $eventID = $checkEventShow->eventID;
            } else {
                $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                            "bind" => ["event_ticket_id" => $event_ticket_id],]);
                $paramEvent = [
                    'eventID' => $checkEventTicketID->eventId
                ];

                $eventData = $tickets->queryEvent($paramEvent);
                $eventName = $eventData['eventName'];
                $eventStartTime = $eventData['dateStart'];
                $eventID = $checkEventTicketID->eventId;
                $eventVenue = $eventData['venue'];
                $eventPosterURL = $eventData['posterURL'];
            }


            if (!$checkEventTicketID) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
            }
            if ($auth_response['role_id'] == 6) {
                $checkUserEventMap = UserEventMap::findFirst(["user_mapId=:user_mapId: AND eventID=:eventID:",
                            "bind" => ["user_mapId" => $auth_response['user_mapId'],
                                'eventID' => $checkEventTicketID->eventId],]);
                if (!$checkUserEventMap) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'You are not authorised to send complimentarty');
                }
            } else {
                if ($checkEventTicketID->total_complimentary <= 0) {
                    return $this->success(__LINE__ . ":" . __CLASS__, "No Event Complimentary Ticket Avaliable"
                                    , ['code' => 202, 'message' => 'Sorry but you cannot '
                                . 'issue complimentary ticket as there is no complementay ticket available']
                                    , true);
                }
                if ($checkEventTicketID->issued_complimentary >= $checkEventTicketID->total_complimentary) {
                    return $this->success(__LINE__ . ":" . __CLASS__, "Event Complimentary Ticket Issued Out"
                                    , ['code' => 202, 'message' => 'Sorry but you cannot '
                                . 'issue complimentary ticket as the Event Ticket is Issued out']
                                    , true);
                }
                if (($checkEventTicketID->issued_complimentary + $quantity) > $checkEventTicketID->total_complimentary) {
                    return $this->success(__LINE__ . ":" . __CLASS__, "Event Complimentary Ticket Issued Out"
                                    , ['code' => 202, 'message' => 'Sorry but you cannot '
                                . 'issue complimentary ticket as the current avaliable tickets is '
                                . $checkEventTicketID->total_complimentary - $checkEventTicketID->issued_complimentary]
                                    , true);
                }
            }

            $event_tickets_option_id = null;

            $sqlEventType = "SELECT ticket_types.ticket_type from ticket_types where typeId=:typeId";
            $paramsData = [
                ':typeId' => $checkEventTicketID->typeId
            ];
            $eventType = $this->rawSelect($sqlEventType, $paramsData);
            $errorMessage = [];
            $ticketQRcode = [];

            if ($quantity >= 1) {
                for ($i = 1; $i <= $quantity; $i++) {
                    $t = time();
                    $QRCode = rand(1000000, 99999999999999) . "" . $t;
                    $barCode = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $QRCode . '&choe=UTF-8';
                    $paramsTickets = [
                        'profile_id' => Profiling::Profile($msisdn),
                        'event_ticket_id' => $event_ticket_id,
                        'reference_id' => $unique_id,
                        'reference' => $reference,
                        'barcode' => $QRCode,
                        'barcodeURL' => $barCode
                    ];

                    $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets, 1, $company, $event_tickets_option_id, $show, null, 50, 1, $name);

                    if (!$event_profile_ticket_id) {
                        array_push($errorMessage, 'Failed to create ticket');
                        continue;
                    }
                    $paramsState = [
                        'status' => 1,
                        'event_profile_ticket_id' => $event_profile_ticket_id,
                    ];

                    $tickets->ProfileTicketState($paramsState);

                    $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                                , 'bind' => ['profile_id' => Profiling::Profile($msisdn)]]);
                    $verification_code = rand(1000, 9999);
                    $transactionManager = new TransactionManager();
                    $dbTrxn = $transactionManager->get();
                    try {
                        if (!$checkProfileAttrinute) {
                            $profileAttribute = new ProfileAttribute();
                            $profileAttribute->network = $this->getMobileNetwork($msisdn);
                            $profileAttribute->pin = md5($verification_code);
                            $profileAttribute->profile_id = Profiling::Profile($msisdn);
                            $profileAttribute->first_name = $names[0];
                            if (count($names) > 1) {
                                $profileAttribute->last_name = $names[1];
                            }
                            $profileAttribute->created = $this->now();
                            $profileAttribute->created_by = 1;
                            if ($profileAttribute->save() === false) {
                                $errors = [];
                                $messages = $profileAttribute->getMessages();
                                foreach ($messages as $message) {
                                    $e["statusDescription"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    array_push($errors, $e);
                                }
                                $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
                            }
                        }
                        $dbTrxn->commit();
                    } catch (Exception $ex) {
                        array_push($errorMessage, $ex->getMessage());
                        continue;
                    }
                    array_push($ticketQRcode, $QRCode);

                    $paramsUpdate = [
                        'event_ticket_id' => $event_ticket_id,
                        'ticket_purchased' => 1,
                        'ticket_redeemed' => 0
                    ];
                    $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, true, false, null, $show);

                    if (!$eventUpdateTicket) {
                        array_push($errorMessage, 'Failed to update ticket status.Kindly contact admin');
                        continue;
                    }
                }
            }




            if ($sendSMSEmail == 1) {
                if ($quantity <= 5) {
                    $sms = "Dear " . $name . ", Your " . $eventName . " ticket is as follows\n ";
                    $count = 1;
                    foreach ($ticketQRcode as $QRCode) {
                        $sms .= $count . ". " . $this->settings['TicketBaseURL'] . "?evtk=" . $QRCode . " \n";
                        $count++;
                    }

                    $sms .= " Helpline" . $this->settings['Helpline'];
                } else {
                    $sms = "Dear " . $name . ", We have sent  " . $quantity . " "
                            . " tickets  for event " . $eventName
                            . ". Login to www.madfun.com to view tickets. Madfun! For Queries call "
                            . "" . $this->settings['Helpline'];
                }


                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $sms,
                    "profile_id" => Profiling::Profile($msisdn),
                    "created_by" => 'COMPLIMENTARY',
                    "is_bulk" => false,
                    "link_id" => ""];

                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($params);
                $smsStatus = false;
                if ($queueMessageResponse) {
                    $smsStatus = true;
                }
                if ($email != null && $quantity <= 10) {

                    $ticketType = TicketTypes::findFirst(["typeId=:typeId:",
                                "bind" => ["typeId" => $checkEventTicketID->typeId],]);

                    $ticketsData = [];

                    foreach ($ticketQRcode as $QRCode) {

                        $ticketsIn = [
                            'ticketName' => $ticketType->ticket_type,
                            'currency' => 'KES',
                            'amount' => 0,
                            'QrCode' => $QRCode
                        ];

                        array_push($ticketsData, $ticketsIn);
                    }

                    $paramsEmail = [
                        "eventID" => $eventID,
                        "orderNumber" => $unique_id,
                        "paymentMode" => "Invited",
                        "name" => $name,
                        "eventDate" => $eventStartTime,
                        "eventName" => $eventName,
                        "amountPaid" => "0",
                        'msisdn' => $msisdn,
                        'ticketsArray' => $ticketsData,
                        'posterURL' => $eventPosterURL,
                        'venue' => $eventVenue,
                        'eventTicketInfo' => "",
                    ];
                    $postData = [
                        "api_key" => $this->settings['ServiceApiKey'],
                        "to" => $email,
                        "from" => "noreply@madfun.com",
                        "cc" => "",
                        "subject" => "Ticket for Event: " . $eventName,
                        "content" => "Ticket information",
                        "extrac" => $paramsEmail
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                            $postData, $this->settings['ServiceApiKey'], 3);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailWithoutAttachments Response::" .
                            json_encode($mailResponse) . " Payload::" .
                            json_encode($postData));
                }
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 200
                            , 'message' => 'Ticket Sent successful']);
            }



            $ticketData = [
                "eventDate" => $eventStartTime,
                "eventName" => $eventName,
                "name" => $name,
                'QRcode(s)' => $ticketQRcode,
            ];
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Ticket Created Successful. Ticket Details were not sent', ['code' => 200
                        , 'message' => "Ticket Created Successful "
                        . "and Ticket Details were not sent", 'data' => $ticketData,
                        'error' => $errorMessage]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * ticketPurchaseMultQueue
     * @return type
     */
    public function ticketPurchaseMultQueue() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ticketPurchaseMultiAction:" . json_encode($request->getJsonRawBody()));

        $payload['token'] = isset($data->api_key) ? $data->api_key : "";
        $payload['eventData'] = isset($data->eventData) ? $data->eventData : "";
        $payload['eventForm'] = isset($data->eventForm) ? $data->eventForm : "";
        $payload['msisdn'] = isset($data->msisdn) ? $data->msisdn : "";
        $payload['isDPOPayment'] = isset($data->isDPOPayment) ? $data->isDPOPayment : false;
        $payload['email'] = isset($data->email) ? $data->email : "";
        $payload['source'] = isset($data->source) ? $data->source : "";
        $payload['reference'] = isset($data->reference) ? $data->reference : "Madfun";
        $payload['unique_id'] = isset($data->unique_id) ? $data->unique_id : "";
        $payload['service_id'] = isset($data->service_id) ? $data->service_id : 1;
        $payload['hasEventShows'] = isset($data->hasEventShows) ? $data->hasEventShows : 0;
        $payload['affiliatorCode'] = isset($data->affiliatorCode) ? $data->affiliatorCode : "";

        if (!$payload['token'] || !$payload['source'] || !$payload['eventData'] || !$payload['msisdn'] || !$payload['unique_id']) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Mandorty Field Requires', ['code' => 404
                        , 'message' => 'Mandorty Field Requires'], true);
        }
        try {
            $auth = new Authenticate();
            if ($payload['source'] != 'WEB') {
                $auth_response = $auth->QuickTokenAuthenticate($payload['token']);
                if (!$auth_response) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Authentication Failure.', ['code' => 401
                                , 'message' => 'Authentication Failure.'], true);
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $payload['token']) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Authentication Failure.', ['code' => 401
                                , 'message' => 'Authentication Failure.'], true);
                }
            }


            $routeKey = $this->settings['Queues']['Ticket']['Route'];
            $queueName = $this->settings['Queues']['Ticket']['Queue'];
            $exchangeKey = $this->settings['Queues']['Ticket']['Exchange'];

            $queue = new Queue();
            $res = $queue
                    ->ConnectAndPublishToQueue($payload
                    , $queueName
                    , $exchangeKey
                    , $routeKey);
            if ($res->code != 200) {

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Request is not successful'
                                , ['code' => $res->code
                            , 'message' => "Failed to Initiate Payment "
                            . "" . $res->statusDescription]);
            }

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Request is successful'
                            , ['code' => 200
                        , 'message' => "Request Queued Successful"]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Request is successful'
                            , ['code' => 500
                        , 'message' => "Exception: " . $ex->getMessage()]);
        }
    }

    /**
     * 
     * @return type
     * @throws Exception
     */
    public function ticketPurchaseMultiAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ticketPurchaseMultiAction:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventData = isset($data->eventData) ? $data->eventData : null;
        $eventForm = isset($data->eventForm) ? $data->eventForm : null;
        $mobile = isset($data->msisdn) ? $data->msisdn : null;
        $isDPOPayment = isset($data->isDPOPayment) ? $data->isDPOPayment : false;
        $isPesapalPayment = isset($data->isPesapalPayment) ? $data->isPesapalPayment : false;
        $email = isset($data->email) ? $data->email : null;
        $source = isset($data->source) ? $data->source : null;
        $reference = isset($data->reference) ? $data->reference : "Madfun";
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $service_id = isset($data->service_id) ? $data->service_id : 1;
        $hasEventShows = isset($data->hasEventShows) ? $data->hasEventShows : 0;
        $affiliatorCode = isset($data->affiliatorCode) ? $data->affiliatorCode : null;

        if (!$token || !$source || !$eventData || !$mobile || !$unique_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        $msisdn = $this->formatMobileNumber($mobile, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
//        if ($network == "UNKNOWN") {
//            return $this->dataError(__LINE__ . ":" . __CLASS__
//                            , 'Validation Error'
//                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
//        }

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | msisdn:" . $msisdn);

        try {
            $auth = new Authenticate();
            if ($source != 'WEB') {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }

            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {

                $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
                $len = rand(1000, 999999);
                $payloadToken = ['data' => $len . "" . $this->now()];
                $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
                $verification_code = rand(1000, 9999);
                $password = $this->security->hash(md5($verification_code));
                $checkProfile = Profile::findFirst(["msisdn=:msisdn:",
                            "bind" => ["msisdn" => $msisdn],]);

                $profile_id = isset($checkProfile->profile_id) ?
                        $checkProfile->profile_id : false;

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | ticketPurchaseMultiAction:" . $profile_id);
                if (!$profile_id) {
                    $profile = new Profile();
                    $profile->setTransaction($dbTrxn);
                    $profile->msisdn = $msisdn;
                    $profile->created = $this->now();
                    if ($profile->save() === false) {
                        $errors = [];
                        $messages = $profile->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Profile failed " . json_encode($errors));
                    }
                    $profile_id = $profile->profile_id;
                }

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | ticketPurchaseMultiAction:" . $profile_id);

                $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $profile_id]]);
                if (!$checkProfileAttrinute) {
                    $profileAttribute = new ProfileAttribute();
                    $profileAttribute->setTransaction($dbTrxn);
                    $profileAttribute->network = $this->getMobileNetwork($msisdn);
                    $profileAttribute->pin = md5($verification_code);
                    $profileAttribute->profile_id = $profile_id;
                    $profileAttribute->token = $newToken;
                    $profileAttribute->created = $this->now();
                    $profileAttribute->created_by = 1; //$auth_response['user_id'];
                    if ($profileAttribute->save() === false) {
                        $errors = [];
                        $messages = $profileAttribute->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
                    }
                }

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | ticketPurchaseMultiAction:" . $profile_id);

                $checkUser = User::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $profile_id]]);
                $user_id = isset($checkUser->user_id) ?
                        $checkUser->user_id : false;
                if (!$user_id) {
                    $user = new User();
                    $user->setTransaction($dbTrxn);
                    $user->profile_id = $profile_id;
                    $user->email = $email;
                    $user->role_id = 5;
                    $user->api_token = $newToken;
                    $user->password = $password;
                    $user->status = 1;
                    $user->created = $this->now();
                    if ($user->save() === false) {
                        $errors = [];
                        $messages = $user->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create User failed " . json_encode($errors));
                    }
                    $user_id = $user->user_id;
                } else {
                    $checkUser->setTransaction($dbTrxn);
                    $checkUser->email = $email;
                    $checkUser->updated = $this->now();
                    if ($checkUser->save() === false) {
                        $errors = [];
                        $messages = $checkUser->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Updated User failed " . json_encode($errors));
                    }
                }

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | ticketPurchaseMultiAction:" . $profile_id);

                $checkUserLogin = UserLogin::findFirst(['user_id=:user_id:'
                            , 'bind' => ['user_id' => $user_id]]);
                if (!$checkUserLogin) {
                    $UserLogin = new UserLogin();
                    $UserLogin->setTransaction($dbTrxn);
                    $UserLogin->created = $this->now();
                    $UserLogin->user_id = $user_id;
                    $UserLogin->login_code = md5($verification_code);
                    if ($UserLogin->save() === false) {
                        $errors = [];
                        $messages = $UserLogin->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                    }
                    $userState = 1;
                } else {
                    $userState = 0;
                }
            } catch (Exception $ex) {
                throw $ex;
            }
            $dbTrxn->commit();
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ticketPurchaseMultiAction:" . $profile_id);
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
                                , ['code' => 400, 'message' => 'Request Failed. Duplicate UniqueId Found!!'], true);
            }
            $error = [];
            $purchase_amount = 0;
            $eventID = 0;
            foreach ($eventData as $event) {

                $ticketCap = 50;

                if ($hasEventShows == 1) {
                    $checkEventTicketID = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                                "bind" => ["event_ticket_show_id" => $event->id],]);
                    if (!$checkEventTicketID) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Invalid Event Show ticket Id"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $ticketCap = $checkEventTicketID->maxCap;
                    $eventShowVenue = EventShowVenue::findFirst(["event_show_venue_id=:event_show_venue_id:",
                                "bind" => ["event_show_venue_id" => $checkEventTicketID->event_show_venue_id],]);
                    if (!$eventShowVenue) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Show Venue Not Configured"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventShow = EventShows::findFirst(["event_show_id=:event_show_id:",
                                "bind" => ["event_show_id" => $eventShowVenue->event_show_id],]);
                    if (!$eventShow) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Show Not Configured"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventID = $eventShow->eventID;
                    $eventTicketID = $checkEventTicketID->event_ticket_show_id;
                } else {
                    $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                                "bind" => ["event_ticket_id" => $event->id],]);
                    if (!$checkEventTicketID) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Invalid Event ticket Id"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventID = $checkEventTicketID->eventId;
                    $eventTicketID = $checkEventTicketID->event_ticket_id;
                    $ticketCap = $checkEventTicketID->maxCap;
                }

                $checkEvent = Events::findFirst(["eventID=:eventID: AND status=:status:",
                            "bind" => ["eventID" => $eventID, "status" => 1],]);
                if (!$checkEvent) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Service Error'
                                    , ['code' => 400, 'message' => 'Request Failed. '
                                . 'Kindly try again.']);
                }

                if ($checkEvent->accept_mpesa_payment != 1) {
                    $isDPOPayment = true;
                }

                $discountAffiliator = 0;
                $affiliatorMapId = "";
                if ($affiliatorCode != null) {
                    $checkAffiliator = AffiliatorEventMap::findFirst(['code=:code: AND eventId=:eventId:'
                                , 'bind' => ['code' => $affiliatorCode, 'eventId' => $eventID]]);
                    if (!$checkAffiliator) {
                        $discountAffiliator = 0;
                    } else if ($checkAffiliator->status != 1) {
                        $discountAffiliator = 0;
                    } else {
                        $discountAffiliator = $checkAffiliator->discount;
                        $affiliatorMapId = $checkAffiliator->id;
                    }
                }


                $event_tickets_option_id = null;
                if ($event->options && $hasEventShows != 1) {
                    $eventTicketOption = $this->selectQuery("SELECT * FROM event_tickets_type_option WHERE event_ticket_id=:event_ticket_id "
                            . "AND `option`regexp :options AND status = 1"
                            , [':event_ticket_id' => $checkEventTicketID->event_ticket_id, ':options' => $event->options]);
                    if ($eventTicketOption) {
                        $event_tickets_option_id = $eventTicketOption[0]['event_tickets_option_id'];
                    }

//                    if ($eventTicketOption[0]['ticket_purchased'] >= $eventTicketOption[0]['total_tickets']) {
//
//                        return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                                        , 'Validation Error'
//                                        , ['code' => 422, 'message' => 'Event Ticket Sold Out']);
//                    }
                }

                if (!is_numeric($event->quantity)) {
                    $errorMessage = [
                        'error_code' => 422,
                        'message' => "Quantity has to be numeric"
                    ];

                    array_push($error, $errorMessage);
                    continue;
                }

                if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {

                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Event Ticket Sold Out']);
                }
                $quantity = $event->quantity * $checkEventTicketID->group_ticket_quantity;
//                if(($checkEventTicketID->ticket_purchased + $quantity) >= $checkEventTicketID->total_tickets) {
//                    $errorMessage = [
//                        'error_code' => 422,
//                        'message' => "Kindly reduces the quantity"
//                    ];
//                    array_push($error, $errorMessage);
//                    continue;
//                }
                $feeAmount = 0;

                $purchase_amount = $purchase_amount + ($event->quantity *
                        ($checkEventTicketID->amount - ($checkEventTicketID->discount + $discountAffiliator)));
                if ($checkEvent->isFeeOnOrganizer == 2) {
                    $feeAmount = (INT) $checkEvent->revenueShare;
                    $purchase_amount = $purchase_amount + ($purchase_amount * ($feeAmount / 100));
                }
                $error = [];
                $isFree = 0;
                $smsFree = "Dear Friend, Find ticket(s) for ";

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | ticketPurchaseMultiAction:" . $profile_id);

                for ($i = 1; $i <= $quantity; $i++) {
                    $t = time();
                    $len = rand(1000000, 9999999) . "" . $t;
                    $paramsTickets = [
                        'profile_id' => $profile_id,
                        'event_ticket_id' => $event->id,
                        'reference_id' => $unique_id,
                        'reference' => $reference,
                        'barcode' => $len,
                        'discount' => $checkEventTicketID->discount + $discountAffiliator,
                        'barcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8',
                    ];
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | ticketPurchaseMultiAction:" . $profile_id);

                    $tickets = new Tickets();
                    $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets, 0, null, $event_tickets_option_id, $hasEventShows, $ticketCap, $quantity);

                    if (!$event_profile_ticket_id) {
                        return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                        , 'Validation Error'
                                        , ['code' => 422, 'message' => 'You have reached'
                                    . ' maxmum number of ticket to purchase']);
                    }

                    if ($discountAffiliator > 0 && $checkEventTicketID->amount > 0) {
                        $paramsAffiliator = [
                            'event_profile_ticket_id' => $event_profile_ticket_id,
                            'affiliator_event_map_id' => $affiliatorMapId
                        ];
                        $tickets->affiliatorSales($paramsAffiliator);
                    }
                    $paramsState = [
                        'status' => 0,
                        'event_profile_ticket_id' => $event_profile_ticket_id,
                    ];

                    $eventState = $tickets->ProfileTicketState($paramsState);
                    if ($checkEventTicketID->amount == 0) {
                        $isFree = 1;
                        $paramEvent = [
                            'eventID' => $checkEventTicketID->eventId
                        ];

                        $eventData = $tickets->queryEvent($paramEvent);

                        $tickets = new Tickets();
                        $paramsState = [
                            'status' => 1,
                            'event_profile_ticket_id' => $event_profile_ticket_id,
                        ];

                        $eventState = $tickets->ProfileTicketState($paramsState);

                        if (!$eventState) {
                            array_push($error, ['message' => 'Failed. The ticket '
                                . 'has been paid.', 'eventTicketID' => $eventTicketID]);
                            continue;
                        }
                        $paramsUpdate = [
                            'event_ticket_id' => $eventTicketID,
                            'ticket_purchased' => 1,
                            'ticket_redeemed' => 0
                        ];
                        $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $event_profile_ticket_id, $hasEventShows);

                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | Profile EventData::" . json_encode($eventState));

                        if ($email != null) {

                            $paramsEmail = [
                                "eventID" => $checkEventTicketID->eventId,
                                "type" => "TICKET_PURCHASED",
                                "name" => "Friend",
                                "eventDate" => $eventData['dateStart'],
                                "eventName" => $eventData['eventName'],
                                "eventAmount" => $checkEventTicketID->amount,
                                'eventType' => 'Regular',
                                'QRcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8',
                                'QRcode' => $len,
                                'posterURL' => $eventData['posterURL'],
                                'venue' => $eventData['venue']
                            ];
                            $postData = [
                                "api_key" => $this->settings['ServiceApiKey'],
                                "to" => $email,
                                "cc" => "",
                                "from" => "noreply@madfun.com",
                                "subject" => "Ticket for Event: " . $eventData['eventName'],
                                "content" => "Ticket information",
                                "extrac" => $paramsEmail
                            ];
                            $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
                            $this->infologger->info(__LINE__ . ":" . __CLASS__
                                    . " | SendEmailWithoutAttachments Response::" . json_encode($mailResponse));
                        }

                        if ($i == 1) {
                            $smsFree .= $eventData['eventName'] . "\n";
                        }


                        $smsFree .= " Link:" . $this->settings['TicketBaseURL'] . "?evtk=" . $len . " \n";
                    }
                }
            }

            if ($eventForm != null) {
                foreach ($eventForm as $form) {
                    $checkElementForm = $this->selectQuery("SELECT  * FROM event_form_elements WHERE"
                            . " form_element_id = :form_element_id", [':form_element_id' => $form->form_element_id]);
                    if (!$checkElementForm) {
                        array_push($error, ['message' => 'Failed. form_element_id not found', 'ID' => $form->form_element_id]);
                        continue;
                    }
                    $sql = "INSERT INTO event_profile_event_form (form_element_id,profile_id,eventID,element_value,created)"
                            . " VALUES (:form_element_id,:profile_id,:eventID,:element_value,:created)";
                    $params = [
                        ':form_element_id' => $form->form_element_id,
                        ':profile_id' => $profile_id,
                        ':eventID' => $eventID,
                        ':element_value' => $form->element_value,
                        ':created' => $this->now()
                    ];
                    $this->rawInsert($sql, $params);
                }
            }
            if (!$quantity > 5 && $isFree == 1) {
                $smsFree = "Hello Friend, we have sent you " . $quantity . " to your account. "
                        . "Kindly login on madfun.com to view tickets";
            }
            if ($isFree == 1) {
                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $smsFree . "\n. Helpline "
                    . "" . $this->settings['Helpline'],
                    "profile_id" => Profiling::Profile($msisdn),
                    "created_by" => 'FREE_TICKET',
                    "is_bulk" => false,
                    "link_id" => ""];
                $message = new Messaging();
                $message->LogOutbox($params);

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 203
                            , 'success' => "Ticket Sent Successful", 'error' => $error]);
            }
            if (count($error) > 0) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'error' => $error]);
            }


            $ip_address = $this->getClientIPServer();

            $xt = ['amount' => $purchase_amount,
                'unique_id' => $unique_id,
                'ip' => $ip_address,];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ticketPurchaseMultiAction:" . $profile_id);

            $params = [
                'service_id' => $service_id,
                'profile_id' => $profile_id,
                'reference_id' => $unique_id,
                'source' => $source,
                'description' => $services[0]['service_description'],
                'extra_data' => json_encode($xt),];

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
            if ($hasEventShows == 1) {
                $accNumber = "MOD" . $transactionId;
            } else {
                $accNumber = "MAD" . $transactionId;
            }

            /*
              $accNumber = $auth->AlphaNumericIdGenerator($transactionId);
              $paramsUpdate = [
              'transaction_id'=>$transactionId,
              'reference_id'=>$accNumber
              ];
              if(!Transactions::UpdateInitiate($paramsUpdate)){
              return $this->success(__LINE__ . ":" . __CLASS__, "Account Reference failed. Try Again"
              , ['code' => 202, 'message' => 'Account Reference failed. Try Again'], true);
              }
             * *
             */



            if (!$isDPOPayment && !$isPesapalPayment) {
                if ($network != 'SAFARICOM') {

                    return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                    , ['code' => 200, 'message' => "Initiating Mobile not Safaricom."
                                , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                    );
                }
                $checkout_payload = [
                    "apiKey" => $this->settings['ServiceApiKey'],
                    "amount" => "$purchase_amount",
                    "phoneNumber" => "$msisdn",
                    "callbackURL" => $this->settings['Mpesa']['CheckOutCallback'],
                    "paybillNumber" => $this->settings['Mpesa']['DefaultPaybillNumber'],
                    "transactionDesc" => "$accNumber",];

                $sts = $this->getMicrotime();
                $response = $this->sendJsonPostData($this->settings['Mpesa']['CheckOutUrl'], $checkout_payload);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Took $stopped Seconds"
                        . " | UniqueId:" . $unique_id
                        . " | Mobile:$msisdn"
                        . " | " . $services[0]['service_name']
                        . " | TransactionId:" . $transactionId
                        . " | Account Encrpty: " . $accNumber
                        . " | sendPostData Reponse:" . json_encode($response));

                $iserror = false;

                if ($response['statusCode'] != 200) {//|| $source == 'USSD') {
                    $sms = [
                        'created_by' => $source,
                        'profile_id' => $params['profile_id'],
                        'msisdn' => $msisdn,
                        'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                        'message' => "Make Payment Via Mpesa Use below "
                        . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                        . "\nAmount:$purchase_amount\nAccount Number:$accNumber",
                        'is_bulk' => true,
                        'link_id' => '',];
                    $sts = $this->getMicrotime();
                    $message = new Messaging();
                    $queueMessageResponse = $message->LogOutbox($sms);
                    $stopped = $this->getMicrotime() - $sts;
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | Took $stopped Seconds"
                            . " | UniqueId:" . $unique_id
                            . " | Mobile:$msisdn"
                            . " | " . $services[0]['service_name']
                            . " | TransactionId:" . $transactionId
                            . " | Account Encrpty: " . $auth->Encrypt($transactionId)
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
                                , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                    , $iserror);
                }

                return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                , ['code' => $response['statusCode'], 'message' => $statusDesc
                            , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                            , 'payment_info' => "Make Payment Via Mpesa Use below "
                            . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                , $iserror);
            }



            $events = Events::findFirst(['eventID=:eventID:'
                        , 'bind' => ['eventID' => $eventID]]);
            $checkProfileAttrinuteDPO = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => $profile_id]]);
            if ($isPesapalPayment) {
                $Pesapal = new Pesapal();
                $finalAmount = $purchase_amount + ($purchase_amount * $this->settings['PESAPAL']['processingFee']);

                $paramsPesapal = [
                    'amount' => $finalAmount,
                    'account' => $accNumber,
                    'eventId' => $eventID,
                    'unquie' => $unique_id,
                    'notification_id' => $this->settings['PESAPAL']['notificationId'],
                    'description' => 'Ticket for Event: ' . $events->eventName,
                    'first_name' => $checkProfileAttrinuteDPO->first_name,
                    'middle_name' => $checkProfileAttrinuteDPO->surname,
                    'last_name' => $checkProfileAttrinuteDPO->last_name,
                    'eventName' => $events->eventName,
                    'email' => $email,
                    'phone' => $msisdn,
                ];
                $pesapalResult = $Pesapal->submitOrder($paramsPesapal);
                if ($pesapalResult['status'] != 200) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => $pesapalResult['status'],
                                'error' => $pesapalResult['message']]);
                }

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | ticketPurchaseMultiAction:" . $profile_id);

                $paramsDPOInititated = [
                    'transaction_id' => $transactionId,
                    'TransactionToken' => $pesapalResult['order_tracking_id']
                ];
                $stDPO = $this->getMicrotime();
                $DPOResultInitiated = Transactions::dpoInititate($paramsDPOInititated);
                $stopDPO = $this->getMicrotime() - $stDPO;

                $DPOArray = [
                    'API3G' => [
                        'Result' => '000',
                        'ResultExplanation' => 'Transaction created',
                        'TransRef' => $accNumber,
                        'TransToken' => $pesapalResult['order_tracking_id']
                    ]
                ];

                return $this->success(__LINE__ . ":" . __CLASS__, "Transaction Created"
                                , ['code' => 200, 'message' => 'Transaction Created', 'data' => $DPOArray]);
            }
            $DPOPayments = new DPOCardProcessing();
            $finalAmount = $purchase_amount + ($purchase_amount * $this->settings['DPO']['processingFee']);

            $paramsDPO = [
                'amount' => $finalAmount,
                'account' => $accNumber,
                'eventId' => $eventID,
                'unquie' => $unique_id,
                'description' => 'Ticket for Event: ' . $events->eventName,
                'first_name' => $checkProfileAttrinuteDPO->first_name,
                'last_name' => $checkProfileAttrinuteDPO->last_name,
                'email' => $email,
                'phone' => $msisdn,
            ];

            $country = Country::findFirst(['currency=:currency:'
                        , 'bind' => ['currency' => $events->currency]]);

            if ($events->accept_mpesa_payment == 1) {
                $DPOResult = $DPOPayments->createToken($paramsDPO, $events->currency, $country->isoCode2);
            } else {

                $msisdn = $this->formatMobileNumber($mobile, substr($country->mobile_prefix, 1));

                $paramsDPO = [
                    'amount' => $finalAmount,
                    'account' => $accNumber,
                    'eventId' => $eventID,
                    'unquie' => $unique_id,
                    'description' => 'Ticket for Event: ' . $events->eventName,
                    'first_name' => $checkProfileAttrinuteDPO->first_name,
                    'last_name' => $checkProfileAttrinuteDPO->last_name,
                    'email' => $email,
                    'phone' => $msisdn,
                ];

                $DPOResult = $DPOPayments->createMobileToken($paramsDPO, $events->currency, $country->isoCode2, $country->country_name);
            }


            $DPOReponse = print_r($DPOResult, true);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | DPO::Initiate Reponse:" . ($DPOReponse));

            $xml = simplexml_load_string($DPOReponse, "SimpleXMLElement", LIBXML_NOCDATA);
            $jsonString = json_encode($xml, JSON_PRETTY_PRINT);

            $dataJ = json_decode($jsonString, true);

            // $DPOArray = $DPOXMLData->getArray();


            $payloadResponse = [
                'API3G' => [
                    'Result' => $dataJ['Result'],
                    'ResultExplanation' => $dataJ['ResultExplanation'],
                    'TransToken' => $dataJ['TransToken'],
                    'TransRef' => $accNumber
                ]
            ];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | DPO::Initiate Reponse:" . json_encode($payloadResponse));

            $paramsDPOInititated = [
                'transaction_id' => $transactionId,
                'TransactionToken' => $dataJ['TransToken']
            ];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | DPO::Initiate Reponse:" . json_encode($paramsDPOInititated));
            $stDPO = $this->getMicrotime();
            $DPOResultInitiated = Transactions::dpoInititate($paramsDPOInititated);
            $stopDPO = $this->getMicrotime() - $stDPO;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stopDPO Seconds"
                    . " | UniqueId:" . $DPOResultInitiated
                    . " | Mobile:$msisdn"
                    . " | " . $services[0]['service_name']
                    . " | Transactions::Initiate Reponse:" . json_encode($DPOResultInitiated));
            if (!$DPOResultInitiated) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Duplicate DPO Info"
                                , ['code' => 202, 'message' => 'Transaction is '
                            . 'a Duplicate', 'data' => $payloadResponse], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, "Transaction Created"
                            , ['code' => 200, 'message' => 'Transaction Created', 'data' => $payloadResponse]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * 
     * @return type
     * @throws Exception
     */
    public function ticketPurchaseMultiNewAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ticketPurchaseMultiAction:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventData = isset($data->eventData) ? $data->eventData : null;
        $msisdn = isset($data->msisdn) ? $data->msisdn : null;
        $nationalID = isset($data->nationalID) ? $data->nationalID : null;
        $isDPOPayment = isset($data->isDPOPayment) ? $data->isDPOPayment : false;
        $email = isset($data->email) ? $data->email : null;
        $source = isset($data->source) ? $data->source : null;
        $reference = isset($data->reference) ? $data->reference : "Madfun";
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $service_id = isset($data->service_id) ? $data->service_id : 1;
        $hasEventShows = isset($data->hasEventShows) ? $data->hasEventShows : 0;
        $affiliatorCode = isset($data->affiliatorCode) ? $data->affiliatorCode : null;

        if (!$token || !$source || !$eventData || !$msisdn || !$unique_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        $t2 = time();

        $msisdn = $this->formatMobileNumber($msisdn, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
//        if ($network == "UNKNOWN") {
//            return $this->dataError(__LINE__ . ":" . __CLASS__
//                            , 'Validation Error'
//                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
//        }

        try {
            $auth = new Authenticate();
            if ($source != 'WEB') {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }

            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {

                $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
                $len = rand(1000, 999999);
                $payloadToken = ['data' => $len . "" . $this->now()];
                $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
                $verification_code = rand(1000, 9999);
                $password = $this->security->hash(md5($verification_code));
                $checkProfile = Profile::findFirst(["msisdn=:msisdn:",
                            "bind" => ["msisdn" => $msisdn],]);
                $profile_id = isset($checkProfile->profile_id) ?
                        $checkProfile->profile_id : false;
                if (!$profile_id) {
                    $profile = new Profile();
                    $profile->setTransaction($dbTrxn);
                    $profile->msisdn = $msisdn;
                    $profile->created = $this->now();
                    if ($profile->save() === false) {
                        $errors = [];
                        $messages = $profile->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Profile failed " . json_encode($errors));
                    }
                    $profile_id = $profile->profile_id;
                }
                $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $profile_id]]);
                if (!$checkProfileAttrinute) {
                    $profileAttribute = new ProfileAttribute();

                    $profileAttribute->network = $this->getMobileNetwork($msisdn);
                    $profileAttribute->pin = md5($verification_code);
                    $profileAttribute->profile_id = $profile_id;
                    $profileAttribute->idNumber = $nationalID;
                    $profileAttribute->token = $newToken;
                    $profileAttribute->created = $this->now();
                    $profileAttribute->created_by = 1; //$auth_response['user_id'];
                    if ($profileAttribute->save() === false) {
                        $errors = [];
                        $messages = $profileAttribute->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
                    }
                }

                $checkUser = User::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $profile_id]]);
                $user_id = isset($checkUser->user_id) ?
                        $checkUser->user_id : false;
                if (!$user_id) {
                    $user = new User();
                    $user->setTransaction($dbTrxn);
                    $user->profile_id = $profile_id;
                    $user->email = $email;
                    $user->role_id = 5;
                    $user->api_token = $newToken;
                    $user->password = $password;
                    $user->status = 1;
                    $user->created = $this->now();
                    if ($user->save() === false) {
                        $errors = [];
                        $messages = $user->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create User failed " . json_encode($errors));
                    }
                    $user_id = $user->user_id;
                }


                $checkUserLogin = UserLogin::findFirst(['user_id=:user_id:'
                            , 'bind' => ['user_id' => $user_id]]);
                if (!$checkUserLogin) {
                    $UserLogin = new UserLogin();
                    $UserLogin->setTransaction($dbTrxn);
                    $UserLogin->created = $this->now();
                    $UserLogin->user_id = $user_id;
                    $UserLogin->login_code = md5($verification_code);
                    if ($UserLogin->save() === false) {
                        $errors = [];
                        $messages = $UserLogin->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                    }
                    $userState = 1;
                } else {
                    $userState = 0;
                }
            } catch (Exception $ex) {
                throw $ex;
            }
            $dbTrxn->commit();
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
                                , ['code' => 400, 'message' => 'Request Failed. Duplicate UniqueId Found!!'], true);
            }
            $error = [];
            $purchase_amount = 0;
            $eventID = 0;
            foreach ($eventData as $event) {

                if ($hasEventShows == 1) {
                    $checkEventTicketID = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                                "bind" => ["event_ticket_show_id" => $event->id],]);
                    if (!$checkEventTicketID) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Invalid Event Show ticket Id"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventShowVenue = EventShowVenue::findFirst(["event_show_venue_id=:event_show_venue_id:",
                                "bind" => ["event_show_venue_id" => $checkEventTicketID->event_show_venue_id],]);
                    if (!$eventShowVenue) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Show Venue Not Configured"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventShow = EventShows::findFirst(["event_show_id=:event_show_id:",
                                "bind" => ["event_show_id" => $eventShowVenue->event_show_id],]);
                    if (!$eventShow) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Show Not Configured"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventID = $eventShow->eventID;
                    $eventTicketID = $checkEventTicketID->event_ticket_show_id;
                } else {
                    $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                                "bind" => ["event_ticket_id" => $event->id],]);
                    if (!$checkEventTicketID) {
                        $errorMessage = [
                            'error_code' => 422,
                            'message' => "Invalid Event ticket Id"
                        ];

                        array_push($error, $errorMessage);
                        continue;
                    }
                    $eventID = $checkEventTicketID->eventId;
                    $eventTicketID = $checkEventTicketID->event_ticket_id;
                }
                $checkEventStatus = Events::findFirst(["eventID=:eventID: AND status =:status:",
                            "bind" => ["eventID" => $eventID, "status" => 1],]);
                if (!$checkEventStatus) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , 'Duplicate Error'
                                    , ['code' => 400, 'message' => 'Event Closed/Not '
                                . 'Found. Check and Try Again!'], true);
                }

                $discountAffiliator = 0;
                $affiliatorMapId = "";
                if ($affiliatorCode != null) {
                    $checkAffiliator = AffiliatorEventMap::findFirst(['code=:code: AND eventId=:eventId:'
                                , 'bind' => ['code' => $affiliatorCode, 'eventId' => $eventID]]);
                    if (!$checkAffiliator) {
                        $discountAffiliator = 0;
                    } else if ($checkAffiliator->status != 1) {
                        $discountAffiliator = 0;
                    } else {
                        $discountAffiliator = $checkAffiliator->discount;
                        $affiliatorMapId = $checkAffiliator->id;
                    }
                }


                $event_tickets_option_id = null;
                if ($event->options && $hasEventShows != 1) {
                    $eventTicketOption = $this->selectQuery("SELECT * FROM event_tickets_type_option WHERE event_ticket_id=:event_ticket_id "
                            . "AND `option`regexp :options AND status = 1"
                            , [':event_ticket_id' => $checkEventTicketID->event_ticket_id, ':options' => $event->options]);
                    if ($eventTicketOption) {
                        $event_tickets_option_id = $eventTicketOption[0]['event_tickets_option_id'];
                    }

//                    if ($eventTicketOption[0]['ticket_purchased'] >= $eventTicketOption[0]['total_tickets']) {
//
//                        return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                                        , 'Validation Error'
//                                        , ['code' => 422, 'message' => 'Event Ticket Sold Out']);
//                    }
                }

                if (!is_numeric($event->quantity)) {
                    $errorMessage = [
                        'error_code' => 422,
                        'message' => "Quantity has to be numeric"
                    ];

                    array_push($error, $errorMessage);
                    continue;
                }

                if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {
                    $errorMessage = [
                        'error_code' => 422,
                        'message' => "Sold Out"
                    ];

                    array_push($error, $errorMessage);
                    continue;
                }
                $quantity = $event->quantity * $checkEventTicketID->group_ticket_quantity;
                if (($checkEventTicketID->ticket_purchased + $quantity) >= $checkEventTicketID->total_tickets) {
                    $errorMessage = [
                        'error_code' => 422,
                        'message' => "Kindly reduces the quantity"
                    ];
                    array_push($error, $errorMessage);
                    continue;
                }
                $purchase_amount = $purchase_amount + ($event->quantity * ($checkEventTicketID->amount - ($checkEventTicketID->discount + $discountAffiliator)));

                $error = [];
                $isFree = 0;
                $smsFree = "Dear Friend, Find ticket(s) for ";

                for ($i = 1; $i <= $quantity; $i++) {

                    $profileId = Profiling::Profile($event->msisdn);

                    $attributes['profile_id'] = $profileId;
                    $attributes['first_name'] = $event->fname;
                    $attributes['last_name'] = $event->lname;
                    $attributes['surname'] = "";
                    $attributes['network'] = $this->getMobileNetwork($event->msisdn);
                    $attributes['source'] = $source;
                    Profiling::ProfileAttribution($attributes);

                    $transactionManager = new TransactionManager();
                    $dbTrxn = $transactionManager->get();
                    try {

                        $secretKey = "55abe029fdebae5e1d417e2ffb2a09knuhnib03klkhka0cd8b54763051cef08bc55abe029";
                        $len = rand(1000, 999999);
                        $payloadToken = ['data' => $len . "" . $this->now()];
                        $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
                        $verification_code = rand(1000, 9999);
                        $password = $this->security->hash(md5($verification_code));
                        $checkUser = User::findFirst(['profile_id=:profile_id:'
                                    , 'bind' => ['profile_id' => $profileId]]);
                        $user_id = isset($checkUser->user_id) ?
                                $checkUser->user_id : false;
                        if (!$user_id) {
                            $user = new User();
                            $user->setTransaction($dbTrxn);
                            $user->profile_id = $profileId;
                            $user->email = $event->email;
                            $user->role_id = 5;
                            $user->api_token = $newToken;
                            $user->password = $password;
                            $user->status = 1;
                            $user->created = $this->now();
                            if ($user->save() === false) {
                                $errors = [];
                                $messages = $user->getMessages();
                                foreach ($messages as $message) {
                                    $e["statusDescription"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    array_push($errors, $e);
                                }
                                $dbTrxn->rollback("Create User failed " . json_encode($errors));
                            }
                            $user_id = $user->user_id;
                        }


                        $checkUserLogin = UserLogin::findFirst(['user_id=:user_id:'
                                    , 'bind' => ['user_id' => $user_id]]);
                        if (!$checkUserLogin) {
                            $UserLogin = new UserLogin();
                            $UserLogin->setTransaction($dbTrxn);
                            $UserLogin->created = $this->now();
                            $UserLogin->user_id = $user_id;
                            $UserLogin->login_code = md5($verification_code);
                            if ($UserLogin->save() === false) {
                                $errors = [];
                                $messages = $UserLogin->getMessages();
                                foreach ($messages as $message) {
                                    $e["statusDescription"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    array_push($errors, $e);
                                }

                                $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                            }
                            $userState = 1;
                        } else {
                            $userState = 0;
                        }
                        $dbTrxn->commit();
                    } catch (Exception $ex) {
                        throw ex;
                    }

                    $t = time();
                    $len = rand(1000000, 9999999) . "" . $t . "" . rand(20000000, 99999999);
                    $paramsTickets = [
                        'profile_id' => $profileId,
                        'event_ticket_id' => $event->id,
                        'reference_id' => $unique_id,
                        'reference' => $reference,
                        'barcode' => $len,
                        'discount' => $checkEventTicketID->discount + $discountAffiliator,
                        'barcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8',
                    ];

                    $tickets = new Tickets();
                    $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets, 0, null, $event_tickets_option_id, $hasEventShows);

                    if (!$event_profile_ticket_id) {
                        return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                        , 'Validation Error'
                                        , ['code' => 422, 'message' => 'Failed to create ticket']);
                    }
                    if ($discountAffiliator > 0 && $checkEventTicketID->amount > 0) {
                        $paramsAffiliator = [
                            'event_profile_ticket_id' => $event_profile_ticket_id,
                            'affiliator_event_map_id' => $affiliatorMapId
                        ];
                        $tickets->affiliatorSales($paramsAffiliator);
                    }
                    $paramsState = [
                        'status' => 0,
                        'event_profile_ticket_id' => $event_profile_ticket_id,
                    ];

                    $eventState = $tickets->ProfileTicketState($paramsState);
                    if ($checkEventTicketID->amount == 0) {
                        $isFree = 1;
                        $paramEvent = [
                            'eventID' => $checkEventTicketID->eventId
                        ];

                        $eventData = $tickets->queryEvent($paramEvent);

                        $tickets = new Tickets();
                        $paramsState = [
                            'status' => 1,
                            'event_profile_ticket_id' => $event_profile_ticket_id,
                        ];

                        $eventState = $tickets->ProfileTicketState($paramsState);

                        if (!$eventState) {
                            array_push($error, ['message' => 'Failed. The ticket '
                                . 'has been paid.', 'eventTicketID' => $eventTicketID]);
                            continue;
                        }
                        $paramsUpdate = [
                            'event_ticket_id' => $eventTicketID,
                            'ticket_purchased' => 1,
                            'ticket_redeemed' => 0
                        ];
                        $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $event_profile_ticket_id, $hasEventShows);

                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | Profile EventData::" . json_encode($eventState));

                        if ($email != null) {

                            $paramsEmail = [
                                "eventID" => $checkEventTicketID->eventId,
                                "type" => "TICKET_PURCHASED",
                                "name" => "Friend",
                                "eventDate" => $eventData['dateStart'],
                                "eventName" => $eventData['eventName'],
                                "eventAmount" => $checkEventTicketID->amount,
                                'eventType' => 'Regular',
                                'QRcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8',
                                'QRcode' => $len,
                                'posterURL' => $eventData['posterURL'],
                                'venue' => $eventData['venue']
                            ];
                            $postData = [
                                "api_key" => $this->settings['ServiceApiKey'],
                                "to" => $email,
                                "cc" => "",
                                "from" => "noreply@madfun.com",
                                "subject" => "Ticket for Event: " . $eventData['eventName'],
                                "content" => "Ticket information",
                                "extrac" => $paramsEmail
                            ];
                            $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
                            $this->infologger->info(__LINE__ . ":" . __CLASS__
                                    . " | SendEmailWithoutAttachments Response::" . json_encode($mailResponse));
                        }

                        if ($i == 1) {
                            $smsFree .= $eventData['eventName'] . "\n";
                        }


                        $smsFree .= " Link:" . $this->settings['TicketBaseURL'] . "?evtk=" . $len . " \n";
                    }
                }
            }
            if (!$quantity > 5 && $isFree == 1) {
                $smsFree = "Hello Friend, we have sent you " . $quantity . " to your account. "
                        . "Kindly login on madfun.com to view tickets";
            }
            if ($isFree == 1) {
                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $smsFree . "\n. Helpline "
                    . "" . $this->settings['Helpline'],
                    "profile_id" => Profiling::Profile($msisdn),
                    "created_by" => 'FREE_TICKET',
                    "is_bulk" => false,
                    "link_id" => ""];
                $message = new Messaging();
                $message->LogOutbox($params);

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 203
                            , 'success' => "Ticket Sent Successful", 'error' => $error]);
            }
            if (count($error) > 0) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'error' => $error]);
            }


            $ip_address = $this->getClientIPServer();

            $xt = ['amount' => $purchase_amount,
                'unique_id' => $unique_id,
                'ip' => $ip_address,];

            $params = [
                'service_id' => $service_id,
                'profile_id' => Profiling::Profile($msisdn),
                'reference_id' => $unique_id,
                'source' => $source,
                'description' => $services[0]['service_description'],
                'extra_data' => json_encode($xt),];

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
            if ($hasEventShows == 1) {
                $accNumber = "MOD" . $transactionId;
            } else {
                $accNumber = "MAD" . $transactionId;
            }

            /*
              $accNumber = $auth->AlphaNumericIdGenerator($transactionId);
              $paramsUpdate = [
              'transaction_id'=>$transactionId,
              'reference_id'=>$accNumber
              ];
              if(!Transactions::UpdateInitiate($paramsUpdate)){
              return $this->success(__LINE__ . ":" . __CLASS__, "Account Reference failed. Try Again"
              , ['code' => 202, 'message' => 'Account Reference failed. Try Again'], true);
              }
             * *
             */

            if (!$isDPOPayment) {
                if ($network != 'SAFARICOM') {

                    return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                    , ['code' => 200, 'message' => "Initiating Mobile not Safaricom."
                                , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                    );
                }
                $checkout_payload = [
                    "apiKey" => $this->settings['ServiceApiKey'],
                    "amount" => "$purchase_amount",
                    "phoneNumber" => "$msisdn",
                    "callbackURL" => $this->settings['Mpesa']['CheckOutCallback'],
                    "paybillNumber" => $this->settings['Mpesa']['DefaultPaybillNumber'],
                    "transactionDesc" => "$accNumber",];

                $sts = $this->getMicrotime();
                $response = $this->sendJsonPostData($this->settings['Mpesa']['CheckOutUrl'], $checkout_payload);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Took $stopped Seconds"
                        . " | UniqueId:" . $unique_id
                        . " | Mobile:$msisdn"
                        . " | " . $services[0]['service_name']
                        . " | TransactionId:" . $transactionId
                        . " | Account Encrpty: " . $accNumber
                        . " | sendPostData Reponse:" . json_encode($response));

                $iserror = false;

                if ($response['statusCode'] != 200) {//|| $source == 'USSD') {
                    $sms = [
                        'created_by' => $source,
                        'profile_id' => $params['profile_id'],
                        'msisdn' => $msisdn,
                        'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                        'message' => "Make Payment Via Mpesa Use below "
                        . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                        . "\nAmount:$purchase_amount\nAccount Number:$accNumber",
                        'is_bulk' => true,
                        'link_id' => '',];
                    $sts = $this->getMicrotime();
                    $message = new Messaging();
                    $queueMessageResponse = $message->LogOutbox($sms);
                    $stopped = $this->getMicrotime() - $sts;
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | Took $stopped Seconds"
                            . " | UniqueId:" . $unique_id
                            . " | Mobile:$msisdn"
                            . " | " . $services[0]['service_name']
                            . " | TransactionId:" . $transactionId
                            . " | Account Encrpty: " . $auth->Encrypt($transactionId)
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
                                , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                    , $iserror);
                }

                return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                , ['code' => $response['statusCode'], 'message' => $statusDesc
                            , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                            , 'payment_info' => "Make Payment Via Mpesa Use below "
                            . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                , $iserror);
            }

            $events = Events::findFirst(['eventID=:eventID:'
                        , 'bind' => ['eventID' => $eventID]]);
            $checkProfileAttrinuteDPO = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => $profile_id]]);
            $DPOPayments = new DPOCardProcessing();
            $finalAmount = $purchase_amount + ($purchase_amount * $this->settings['DPO']['processingFee']);

            $paramsDPO = [
                'amount' => $finalAmount,
                'account' => $accNumber,
                'eventId' => $eventID,
                'unquie' => $unique_id,
                'description' => 'Ticket for Event: ' . $events->eventName,
                'first_name' => $checkProfileAttrinuteDPO->first_name,
                'last_name' => $checkProfileAttrinuteDPO->last_name,
                'email' => $email,
                'phone' => $msisdn,
            ];
            $DPOResult = $DPOPayments->createToken($paramsDPO);
            $DPOReponse = print_r($DPOResult, true);
            $DPOXMLData = new XMLToArrayUtils($DPOReponse, array(), array('story' => 'array'), true, false);

            $DPOArray = $DPOXMLData->getArray();

            $TransToken = $DPOArray['API3G']['TransToken'];
            $paramsDPOInititated = [
                'transaction_id' => $transactionId,
                'TransactionToken' => $TransToken
            ];
            $stDPO = $this->getMicrotime();
            $DPOResultInitiated = Transactions::dpoInititate($paramsDPOInititated);
            $stopDPO = $this->getMicrotime() - $stDPO;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stopDPO Seconds"
                    . " | UniqueId:" . $DPOResultInitiated
                    . " | Mobile:$msisdn"
                    . " | " . $services[0]['service_name']
                    . " | Transactions::Initiate Reponse:" . json_encode($DPOResultInitiated));
            if (!$DPOResultInitiated) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Duplicate DPO Info"
                                , ['code' => 202, 'message' => 'Transaction is '
                            . 'a Duplicate', 'data' => $DPOArray], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, "Transaction Created"
                            , ['code' => 200, 'message' => 'Transaction Created', 'data' => $DPOArray]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * ticketPurchaseAction
     * @return type
     */
    public function ticketPurchaseAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        $msisdn = isset($data->msisdn) ? $data->msisdn : null;
        $quantity = isset($data->quantity) ? $data->quantity : 1;
        $source = isset($data->source) ? $data->source : null;
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $reference = isset($data->reference) ? $data->reference : "MADFUN";
        $service_id = isset($data->service_id) ? $data->service_id : 1;
        $affiliatorCode = isset($data->affiliatorCode) ? $data->affiliatorCode : null;

        if (!$token || !$source || !$event_ticket_id || !$quantity || !$msisdn || !$unique_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!is_numeric($quantity)) {

            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Quantity']);
        }
        if (strlen($msisdn) <= 12) {
            $msisdn = $this->formatMobileNumber($msisdn, "254");
            $network = $this->getMobileNetwork($msisdn, "254");
            if ($network == "UNKNOWN") {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
            }
        }

        try {
            $auth = new Authenticate();
            if ($source != 'USSD') {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }

            $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                        "bind" => ["event_ticket_id" => $event_ticket_id],]);
            if (!$checkEventTicketID) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
            }
            $discountAffiliator = 0;
            $affiliatorMapId = "";
            if ($affiliatorCode != null) {
                $checkAffiliator = AffiliatorEventMap::findFirst(['code=:code: AND eventId=:eventId:'
                            , 'bind' => ['code' => $affiliatorCode, 'eventId' => $checkEventTicketID->eventId]]);
                if (!$checkAffiliator) {
                    $discountAffiliator = 0;
                } else if ($checkAffiliator->status != 1) {
                    $discountAffiliator = 0;
                } else {
                    $discountAffiliator = $checkAffiliator->discount;
                    $affiliatorMapId = $checkAffiliator->id;
                }
            }

            if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Event Ticket Sold Out"
                                , ['code' => 202, 'message' => 'Sorry but you cannot '
                            . 'purchase ticket as the Event Ticket is sold out']
                                , true);
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
                                , ['code' => 400, 'message' => 'Request Failed. Duplicate UniqueId Found!!'], true);
            }
            $purchase_amount = $quantity * $checkEventTicketID->amount;

            for ($i = 1; $i <= $quantity; $i++) {
                $t = time();
                $len = rand(1000000, 9999999) . "" . $t;
                $source = isset($data->source) ? $data->source : NULL;

                $paramsTickets = [
                    'profile_id' => Profiling::Profile($msisdn),
                    'event_ticket_id' => $event_ticket_id,
                    'reference_id' => $unique_id,
                    'reference' => $reference,
                    'barcode' => $len,
                    'discount' => $discountAffiliator,
                    'barcodeURL' => 'https://chart.googleapis.com/chart?chs=350x350&cht=qr&chl=' . $len . '&choe=UTF-8'
                ];

                $tickets = new Tickets();
                $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets);

                if (!$event_profile_ticket_id) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Failed to create ticket']);
                }
                if ($discountAffiliator > 0) {
                    $paramsAffiliator = [
                        'event_profile_ticket_id' => $event_profile_ticket_id,
                        'affiliator_event_map_id' => $affiliatorMapId
                    ];
                    $tickets->affiliatorSales($paramsAffiliator);
                }

                $paramsState = [
                    'status' => 0,
                    'event_profile_ticket_id' => $event_profile_ticket_id,
                ];

                $eventState = $tickets->ProfileTicketState($paramsState);
            }
            $ip_address = $this->getClientIPServer();

            $xt = ['amount' => $purchase_amount,
                'unique_id' => $unique_id,
                'ip' => $ip_address,];

            $params = [
                'service_id' => $service_id,
                'profile_id' => Profiling::Profile($msisdn),
                'reference_id' => $unique_id,
                'source' => $source,
                'description' => $services[0]['service_description'],
                'extra_data' => json_encode($xt),];

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
            $accNumber = "MAD" . $transactionId;
            /*
              $accNumber = $auth->AlphaNumericIdGenerator($transactionId);
              $paramsUpdate = [
              'transaction_id'=>$transactionId,
              'reference_id'=>$accNumber
              ];
              if(!Transactions::UpdateInitiate($paramsUpdate)){
              return $this->success(__LINE__ . ":" . __CLASS__, "Account Reference failed. Try Again"
              , ['code' => 202, 'message' => 'Account Reference failed. Try Again'], true);
              }
             * *
             */
            if ($network != 'SAFARICOM') {
                return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                , ['code' => 200, 'message' => "Initiating Mobile not Safaricom."
                            , 'payment_info' => 'Make Payment Via Mpesa Use below '
                            . "Instructions\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]);
            }
            $checkout_payload = [
                "apiKey" => $this->settings['ServiceApiKey'],
                "amount" => "$purchase_amount",
                "phoneNumber" => "$msisdn",
                "callbackURL" => $this->settings['Mpesa']['CheckOutCallback'],
                "paybillNumber" => $this->settings['Mpesa']['DefaultPaybillNumber'],
                "transactionDesc" => "$accNumber",];

            $sts = $this->getMicrotime();
            $response = $this->sendJsonPostData($this->settings['Mpesa']['CheckOutUrl'], $checkout_payload);
            $stopped = $this->getMicrotime() - $sts;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stopped Seconds"
                    . " | UniqueId:" . $unique_id
                    . " | Mobile:$msisdn"
                    . " | " . $services[0]['service_name']
                    . " | TransactionId:" . $transactionId
                    . " | Account Encrpty: " . $accNumber
                    . " | sendPostData Reponse:" . json_encode($response));

            $iserror = false;

            if ($response['statusCode'] != 200) {
                $sms = [
                    'created_by' => $source,
                    'profile_id' => $params['profile_id'],
                    'msisdn' => $msisdn,
                    'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                    'message' => "Make Payment Via Mpesa Use below "
                    . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                    . "\nAmount:$purchase_amount\nAccount Number:$accNumber",
                    'is_bulk' => true,
                    'link_id' => '',];
                $sts = $this->getMicrotime();
                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($sms);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | Took $stopped Seconds"
                        . " | UniqueId:" . $unique_id
                        . " | Mobile:$msisdn"
                        . " | " . $services[0]['service_name']
                        . " | TransactionId:" . $transactionId
                        . " | Account Encrpty: " . $auth->Encrypt($transactionId)
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
                            , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                            , 'payment_info' => "Make Payment Via Mpesa Use below "
                            . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                                , $iserror);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                            , ['code' => $response['statusCode'], 'message' => $statusDesc
                        , 'account_number' => $accNumber, 'amount' => $purchase_amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                        , 'payment_info' => "Make Payment Via Mpesa Use below "
                        . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                        . "\nAmount:$purchase_amount\nAccount Number:$accNumber"]
                            , $iserror);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * streamingPurchaseAction
     * @return type
     */
    public function streamingPurchaseAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Streaming Purchase Action:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $order_key = isset($data->order_key) ? $data->order_key : null;
        $msisdn = isset($data->msisdn) ? $data->msisdn : null;
        $currency = isset($data->currency) ? $data->currency : "KES";
        $item_name = isset($data->item_name) ? $data->item_name : null;
        $returnURL = isset($data->returnURL) ? $data->returnURL : null;
        $cancelURL = isset($data->cancelURL) ? $data->cancelURL : null;
        $email = isset($data->email) ? $data->email : 1;
        $fname = isset($data->fname) ? $data->fname : null;
        $lname = isset($data->lname) ? $data->lname : null;
        $sname = isset($data->sname) ? $data->sname : null;
        $amount = isset($data->amount) ? (INT) $data->amount : null;
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $source = isset($data->source) ? $data->source : "WEB";
        $isDPOPayment = isset($data->isDPOPayment) ? $data->isDPOPayment : false;

        if (!$token || !$source || !$order_key || !$currency || !$msisdn || !$unique_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!is_numeric($amount)) {

            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Amount']);
        }
        if (strlen($msisdn) <= 12) {
            $msisdn = $this->formatMobileNumber($msisdn, "254");
            $network = $this->getMobileNetwork($msisdn, "254");
            if ($network != "SAFARICOM" && !$isDPOPayment) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Failed. Kindly use safaricom line ']);
            }
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB'])) {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }
            $check_duplicate = $this->rawSelect("SELECT transaction_id FROM transaction_initiated"
                    . " WHERE `source`=:source AND `service_id`=:service_id "
                    . "AND `reference_id`=:reference_id", [':reference_id' => $unique_id
                , ':service_id' => 8, ':source' => $source]);
            if ($check_duplicate) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Duplicate Error'
                                , ['code' => 400, 'message' => 'Request Failed.'
                            . ' Duplicate UniqueId Found!!'], true);
            }
            $checkStreamProfileRequest = StreamProfileRequest::findFirst(['reference_id=:reference_id:'
                        , 'bind' => ['reference_id' => $unique_id]]);
            if ($checkStreamProfileRequest) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Duplicate Error'
                                , ['code' => 400, 'message' => 'Request Failed.'
                            . ' Duplicate UniqueId Found!!'], true);
            }

            $checkProfile = Profile::findFirst(["msisdn=:msisdn:",
                        "bind" => ["msisdn" => $msisdn],]);
            $profile_id = isset($checkProfile->profile_id) ?
                    $checkProfile->profile_id : false;
            if (!$profile_id) {
                $profile = new Profile();
                $profile->setTransaction($dbTrxn);
                $profile->msisdn = $msisdn;
                $profile->created = $this->now();
                if ($profile->save() === false) {
                    $errors = [];
                    $messages = $profile->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Profile failed " . json_encode($errors));
                }
                $profile_id = $profile->profile_id;
            }
            $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
            $len = rand(1000, 999999);
            $payloadToken = ['data' => $len . "" . $this->now()];
            $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
            $verification_code = rand(1000, 9999);
            $password = $this->security->hash(md5($verification_code));
            $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => $profile_id]]);
            if (!$checkProfileAttrinute) {
                $profileAttribute = new ProfileAttribute();
                $profileAttribute->setTransaction($dbTrxn);
                $profileAttribute->first_name = $fname;
                $profileAttribute->surname = $sname;
                $profileAttribute->last_name = $lname;
                $profileAttribute->network = $this->getMobileNetwork($msisdn);
                $profileAttribute->pin = md5($verification_code);
                $profileAttribute->profile_id = $profile_id;
                $profileAttribute->token = $newToken;
                $profileAttribute->created = $this->now();
                $profileAttribute->created_by = 1; //$auth_response['user_id'];
                if ($profileAttribute->save() === false) {
                    $errors = [];
                    $messages = $profileAttribute->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
                }
            }
            $checkUser = User::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => $profile_id]]);
            $user_id = isset($checkUser->user_id) ?
                    $checkUser->user_id : false;
            if (!$user_id) {
                $user = new User();
                $user->setTransaction($dbTrxn);
                $user->profile_id = $profile_id;
                $user->email = $email;
                $user->role_id = 5;
                $user->api_token = $newToken;
                $user->password = $password;
                $user->status = 1;
                $user->created = $this->now();
                if ($user->save() === false) {
                    $errors = [];
                    $messages = $user->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create User failed " . json_encode($errors));
                }
                $user_id = $user->user_id;
            }

            $checkUserLogin = UserLogin::findFirst(['user_id=:user_id:'
                        , 'bind' => ['user_id' => $user_id]]);
            if (!$checkUserLogin) {
                $UserLogin = new UserLogin();
                $UserLogin->setTransaction($dbTrxn);
                $UserLogin->created = $this->now();
                $UserLogin->user_id = $user_id;
                $UserLogin->login_code = md5($verification_code);
                if ($UserLogin->save() === false) {
                    $errors = [];
                    $messages = $UserLogin->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                }
                $userState = 1;
            } else {
                $userState = 0;
            }
            $StreamProfileRequest = new StreamProfileRequest();
            $StreamProfileRequest->setTransaction($dbTrxn);
            $StreamProfileRequest->amount = $amount;
            $StreamProfileRequest->item_name = $item_name;
            $StreamProfileRequest->order_key = $order_key;
            $StreamProfileRequest->returnURL = $returnURL;
            $StreamProfileRequest->cancelURL = $cancelURL;
            $StreamProfileRequest->profile_id = $profile_id;
            $StreamProfileRequest->currency = $currency;
            $StreamProfileRequest->reference_id = $unique_id;
            $StreamProfileRequest->created = $this->now();
            if ($StreamProfileRequest->save() === false) {
                $errors = [];
                $messages = $StreamProfileRequest->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }

                $dbTrxn->rollback("Create Stream Profile failed. Reason" . json_encode($errors));
            }
            $ip_address = $this->getClientIPServer();
            $xt = ['amount' => $amount,
                'unique_id' => $unique_id,
                'ip' => $ip_address,];

            $params = [
                'service_id' => 8,
                'profile_id' => Profiling::Profile($msisdn),
                'reference_id' => $unique_id,
                'source' => $source,
                'description' => 'Platform for Video Streaming',
                'extra_data' => json_encode($xt),];

            $st = $this->getMicrotime();
            $transactionId = Transactions::Initiate($params);
            $stop = $this->getMicrotime() - $st;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stop Seconds"
                    . " | UniqueId:" . $unique_id
                    . " | Mobile:$msisdn"
                    . " | Platform for Video Streaming"
                    . " | Transactions::Initiate Reponse:" . json_encode($transactionId));

            if (!$transactionId) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Duplicate Info"
                                , ['code' => 202, 'message' => 'Transaction is a Duplicate'], true);
            }
            $dbTrxn->commit();

            $accNumber = "STR" . $transactionId;

            if (!$isDPOPayment) {
                if ($network != 'SAFARICOM') {
                    return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                    , ['code' => 200, 'message' => "Initiating Mobile not Safaricom."
                                , 'payment_info' => 'Make Payment Via Mpesa Use below '
                                . "Instructions\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$amount\nAccount Number:$accNumber"]);
                }
                $checkout_payload = [
                    "apiKey" => $this->settings['ServiceApiKey'],
                    "amount" => "$amount",
                    "phoneNumber" => "$msisdn",
                    "callbackURL" => $this->settings['Mpesa']['CheckOutCallback'],
                    "paybillNumber" => $this->settings['Mpesa']['DefaultPaybillNumber'],
                    "transactionDesc" => "$accNumber",];

                $sts = $this->getMicrotime();
                $response = $this->sendJsonPostData($this->settings['Mpesa']['CheckOutUrl'], $checkout_payload);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Took $stopped Seconds"
                        . " | UniqueId:" . $unique_id
                        . " | Mobile:$msisdn"
                        . " | Video Streaming APP"
                        . " | TransactionId:" . $transactionId
                        . " | Account Encrpty: " . $accNumber
                        . " | sendPostData Reponse:" . json_encode($response));

                $iserror = false;

                if ($response['statusCode'] != 200) {
                    $sms = [
                        'created_by' => $source,
                        'profile_id' => $params['profile_id'],
                        'msisdn' => $msisdn,
                        'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                        'message' => "Make Payment Via Mpesa Use below "
                        . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                        . "\nAmount:$amount\nAccount Number:$accNumber",
                        'is_bulk' => true,
                        'link_id' => '',];
                    $sts = $this->getMicrotime();
                    $message = new Messaging();
                    $message->LogOutbox($sms);
                    $stopped = $this->getMicrotime() - $sts;
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | Took $stopped Seconds"
                            . " | UniqueId:" . $unique_id
                            . " | Mobile:$msisdn"
                            . " | Video Streaming APP"
                            . " | TransactionId:" . $transactionId
                            . " | Account Encrpty: " . $auth->Encrypt($transactionId)
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
                                , 'account_number' => $accNumber, 'amount' => $amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                                , 'payment_info' => "Make Payment Via Mpesa Use below "
                                . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                                . "\nAmount:$amount\nAccount Number:$accNumber"]
                                    , $iserror);
                }

                return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                , ['code' => $response['statusCode'], 'message' => $statusDesc
                            , 'account_number' => $accNumber, 'amount' => $amount, 'paybill' => $this->settings['Mpesa']['DefaultPaybillNumber']
                            , 'payment_info' => "Make Payment Via Mpesa Use below "
                            . "Instructions.\nPaybill Number:" . $this->settings['Mpesa']['DefaultPaybillNumber']
                            . "\nAmount:$amount\nAccount Number:$accNumber"]
                                , $iserror);
            }

            $Pesapal = new Pesapal();

            $finalAmount = $amount + ($amount * $this->settings['DPO']['processingFee']);

            $paramsPesapal = [
                'amount' => $finalAmount,
                'account' => $accNumber,
                'eventId' => "",
                'unquie' => $unique_id,
                'notification_id' => $this->settings['PESAPAL']['notificationId'],
                'description' => 'Live Stream Payme: ' . $unique_id,
                'first_name' => $fname,
                'middle_name' => "",
                'last_name' => $lname,
                'eventName' => "",
                'email' => $email,
                'phone' => $msisdn,
            ];
            $pesapalResult = $Pesapal->submitOrder($paramsPesapal);
            if ($pesapalResult['status'] != 200) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => $pesapalResult['status'],
                            'error' => $pesapalResult['message']]);
            }

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ticketPurchaseMultiAction:" . $profile_id);

            $paramsDPOInititated = [
                'transaction_id' => $transactionId,
                'TransactionToken' => $pesapalResult['order_tracking_id']
            ];

            $stDPO = $this->getMicrotime();
            $DPOResultInitiated = Transactions::dpoInititate($paramsDPOInititated);
            $stopDPO = $this->getMicrotime() - $stDPO;
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took $stopDPO Seconds"
                    . " | UniqueId:" . $DPOResultInitiated
                    . " | Mobile:$msisdn"
                    . " | Transactions::Initiate Reponse:" . json_encode($DPOResultInitiated));

            $DPOArray = [
                'API3G' => [
                    'Result' => '000',
                    'ResultExplanation' => 'Transaction created',
                    'TransRef' => $accNumber,
                    'TransToken' => $pesapalResult['order_tracking_id']
                ]
            ];
            if (!$DPOResultInitiated) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Duplicate DPO Info"
                                , ['code' => 202, 'message' => 'Transaction is '
                            . 'a Duplicate', 'data' => $DPOArray], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, "Transaction Created"
                            , ['code' => 200, 'message' => 'Transaction Created', 'data' => $DPOArray]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * checkPaymentStatus
     * @return type
     */
    public function checkPaymentStatus() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $accNo = isset($data->accNo) ? $data->accNo : null;
        $source = isset($data->source) ? $data->source : null;

        if (!$token || !$source || !$accNo) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        try {
            $auth = new Authenticate();
            if ($source != 'WEB') {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }

            $duplicate = "SELECT id FROM mpesa_transaction WHERE mpesa_account=:mpesa_account";

            $check_duplicate = $this->rawSelect($duplicate, [':mpesa_account' => $accNo]);
            if (!$check_duplicate) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Payment does not exist'
                                , ['code' => 404, 'message' => 'Payment does not exist']);
            }
            return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                            , ['code' => 200, 'message' => "Payment Confirmed"
                        , 'payment_info' => 'Your payment has been received successful']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * checkStreamPayment
     * @return type
     */
    public function checkStreamPayment() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $source = isset($data->source) ? $data->source : null;

        if (!$token || !$source || !$unique_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB'])) {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }
            $checkStreamProfileRequest = StreamProfileRequest::findFirst(['reference_id=:reference_id:'
                        , 'bind' => ['reference_id' => $unique_id]]);
            if (!$checkStreamProfileRequest) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Request Not Found'
                                , ['code' => 404, 'message' => 'Request Not Found']);
            }
            if ($checkStreamProfileRequest->status == 1) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                                , ['code' => 200, 'message' => "Payment Confirmed"
                            , 'payment_info' => 'Your payment has been received successful']);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, "Success"
                            , ['code' => 202, 'message' => "Pending Payment"
                        , 'payment_info' => 'Pending Payment...']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * paymentsAction
     */
    public function paymentsB2BAction() {

        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Payment Action Request:" . json_encode($request->getJsonRawBody()));

        $TransType = isset($data->TransType) ? $data->TransType : null;
        $TransID = isset($data->TransID) ? $data->TransID : null;
        $TransTime = isset($data->TransTime) ? $data->TransTime : null;
        $TransAmount = isset($data->TransAmount) ? $data->TransAmount : null;
        $BusinessShortCode = isset($data->BusinessShortCode) ? $data->BusinessShortCode : null;
        $BillRefNumber2 = isset($data->BillRefNumber) ? $data->BillRefNumber : null;
        $Organization = isset($data->KYCInfo->KYCValue) ? $data->KYCInfo->KYCValue : null;
        $token = isset($data->token) ? $data->token : null;
        $OrgAccountBalance = isset($data->OrgAccountBalance) ? $data->OrgAccountBalance : null;
        /**
         * {"TransType":"Organization To Organization Transfer",
         * "TransID":"OAT8OSRJQ4","TransTime":"20200129112019",
         * "TransAmount":"497.50","BusinessShortCode":"985750",
         * "InvoiceNumber":"0","OrgAccountBalance":"12101515.38",
         * "KYCInfo":{"KYCName":"Organization Name","KYCValue":"TIMB DETERGENT CHEMICALS 2"},"token":"IMw2c3W5KWLFN1sBH1befeFocdWUs0Sd"}
         */
        try {
            if ($BusinessShortCode != $this->settings['Mpesa']['DefaultPaybillNumber']) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Duplicate', ['code' => 202, 'message' => 'Invalid Organisation Paybill']);
            }
            $duplicate = "SELECT id FROM mpesa_transaction_b2b WHERE mpesa_code=:mpesa_code";

            $check_duplicate = $this->rawSelect($duplicate, [':mpesa_code' => $TransID]);
            if ($check_duplicate) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Duplicate', ['code' => 202, 'message' => 'The Transaction is a duplicate']);
            }
            $sqlb2b = "INSERT INTO `mpesa_transaction_b2b`( `mpesa_code`"
                    . ", `mpesa_sender`, `mpesa_amount`, `mpesa_account`, `mpesa_time`"
                    . ", `org_balance`, `paybill`, `created_by`, `created`) "
                    . "VALUES (:mpesa_code,:mpesa_sender,:mpesa_amount"
                    . ",:mpesa_account,:mpesa_time,:org_balance,:paybill,:created_by,NOW());";

            $BillRefNumber3 = trim(preg_replace('/[\t\n\r\s]+/', '', $BillRefNumber2));
            $BillRefNumber = str_replace("+", "", $BillRefNumber3);

            $paramsb2b = [
                ':mpesa_account' => $BillRefNumber,
                ':mpesa_code' => $TransID,
                ':mpesa_sender' => trim($Organization),
                ':mpesa_amount' => $TransAmount,
                ':mpesa_time' => $TransTime,
                ':org_balance' => $OrgAccountBalance,
                ':paybill' => $BusinessShortCode,
                ':created_by' => $TransType,];

            $this->rawInsert($sqlb2b, $paramsb2b);

            $accountNumber = substr($BillRefNumber2, 3);

            if (strtoupper($accountNumber) == 'MF-') {
                ///Send Post data
                $resultStaging = $this->sendJsonPostData("https://stage-204.ridgeways.xyz/api/payment/Confirm", $data);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Response Staging Payload:" . json_encode($resultStaging));
            }

            $select_trxn_initiated = "SELECT * FROM `transaction_initiated` WHERE "
                    . "`transaction_id`=:transaction_id LIMIT 1";
            $check_trxn = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $accountNumber]);

            if (!$check_trxn) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $TransID
                        . " | Account:" . $accountNumber
                        . " | DIRECT_DEPOSIT Transactions Empty Account "
                );
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Transaction Not Found', ['code' => 404
                            , 'message' => 'The Transaction not found'], true);
            }

            $msisdn = Profiling::QueryMobile($check_trxn[0]['profile_id']);

            $sql = "INSERT INTO `mpesa_transaction`( `mpesa_code`, `mpesa_msisdn`"
                    . ", `mpesa_sender`, `mpesa_amount`, `mpesa_account`, `mpesa_time`"
                    . ", `org_balance`, `paybill`, `created_by`, `created`) "
                    . "VALUES (:mpesa_code,:mpesa_msisdn,:mpesa_sender,:mpesa_amount"
                    . ",:mpesa_account,:mpesa_time,:org_balance,:paybill,:created_by,NOW());";

            $params = [
                ':mpesa_account' => $BillRefNumber,
                ':mpesa_code' => $TransID,
                ':mpesa_msisdn' => $msisdn,
                ':mpesa_sender' => trim($Organization),
                ':mpesa_amount' => $TransAmount,
                ':mpesa_time' => $TransTime,
                ':org_balance' => $OrgAccountBalance,
                ':paybill' => $BusinessShortCode,
                ':created_by' => $TransType,];

            $mpesa_trxnId = $this->rawInsert($sql, $params);

            $attributes['profile_id'] = Profiling::Profile($msisdn);
            $attributes['network'] = $this->getMobileNetwork($msisdn);
            $attributes['source'] = $check_trxn[0]['source'];
            $attributes['profile_attribute_id'] = Profiling::ProfileAttribution($attributes);

            $extra1 = [
                'paid_msisdn' => $msisdn,
                'account_number' => $accountNumber,];
            $trx_params = [
                'amount' => $TransAmount,
                'service_id' => $check_trxn[0]['service_id'],
                'profile_id' => $attributes['profile_id'],
                'reference_id' => $mpesa_trxnId, //,
                'source' => $check_trxn[0]['source'],
                'description' => $check_trxn[0]['description'],
                'extra_data' => json_encode($extra1),];

            $pay_reference_id = Transactions::CreateTransaction($trx_params);

            $referenceID = $check_trxn[0]['reference_id'];

            if ($check_trxn[0]['service_id'] == 8 && strtoupper($accountNumber) == 'STR') {

                $select_stream_profile = "SELECT * FROM stream_profile_request WHERE"
                        . " reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_stream_profile,
                        [':reference_id' => $check_trxn[0]['reference_id']]);

                if (!$check_trxn_profile) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Streaming Profile Request not Found', ['code' => 404
                                , 'message' => 'Streaming Profile Request not Found']);
                }

                $update_stream_profile = "update stream_profile_request set status = 1  WHERE"
                        . " id=:id";

                $this->rawUpdateWithParams($update_stream_profile,
                        [':id' => $check_trxn_profile[0]['id']]);
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Payment for Video Successful', ['code' => 200
                            , 'message' => 'Payment for Video Successful']);
            } else if (in_array($check_trxn[0]['service_id'], [3, 4, 5])) {
                $extra_data = json_decode($check_trxn[0]['extra_data']);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Utilities:" . $extra_data->account_number);
                if ($check_trxn[0]['service_id'] == 3) {
                    //Airtime
                    $postData = [
                        'apiKey' => $this->settings['ServiceApiKey'],
                        'unique_id' => "MADFUN" . $pay_reference_id,
                        'amount' => $TransAmount,
                        'msisdn' => $extra_data->account_number,
                        'callback' => "http://35.187.164.231/ticket-bay-api/api/utility/v1/callback",
                        'provider' => ""];

                    if (!$postData['msisdn']) {
                        $postData['msisdn'] = $msisdn;
                    }
                    $result = $this->sendJsonPostData($this->settings['Rewards']['VasproAirtimeUrl'], $postData);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $TransID
                            . " | $msisdn "
                            . " | ServiceId:" . $check_trxn[0]['service_id']
                            . " | PayID:$pay_reference_id"
                            . " | Request::" . json_encode($postData)
                            . " | Fulfil Airtime::" . json_encode($result));

                    $res = json_decode($result['response']);

                    $response_description = $res->statusDescription;
                    $data_response = isset($res->data) ? $res->data : false;
                    $receipt_number = isset($data_response->data->transaction_id) ? $data_response->data->transaction_id : "Failed#$pay_reference_id";

                    $narration = isset($data_response->message) ? $data_response->message : $response_description;
                    $response_code = isset($data_response->code) ? $data_response->code : $result['statusCode'];

                    if ($response_code == 502) {
                        $response_description = 'Request TimedOut';
                        $narration = ( $result['error'] != null) ? $result['error'] : 'Connection Timed out.';
                    }

                    $callback_data = [
                        'purchase_type' => ($extra_data->account_number == $msisdn) ? 'Beneficiary' : 'Sponsor',
                        'transaction_id' => $pay_reference_id,
                        'response_code' => $response_code,
                        'response_description' => $response_description,
                        'narration' => $narration,
                        'receipt_number' => $receipt_number,];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $TransID
                            . " | $msisdn"
                            . " | Service: Airtime Purchase"
                            . " | PayID:$pay_reference_id"
                            . " | CreateTransactionCallback:$callback_id");

                    return $this->success(__LINE__ . ":" . __CLASS__, 'Ok', ['code' => $result['statusCode'], 'message' => $narration]);
                }
                if (in_array($check_trxn[0]['service_id'], [4, 5])) {
                    $service_name = "";

                    $account_type = 0;
                    if ($check_trxn[0]['service_id'] == 4) {
                        $account_type = 1;
                        $service_name = "PrePaid Tokens";
                    } else {
                        $account_type = 2;
                        $service_name = "PostPaid Electricity";
                    }

                    $postData = [
                        'apiKey' => $this->settings['ServiceApiKey'],
                        'unique_id' => "MADFUN" . $pay_reference_id,
                        'amount' => $TransAmount,
                        'msisdn' => $msisdn,
                        'account_type' => "$account_type",
                        'bill_reference' => $extra_data->account_number,
                        'callback' => 'http://35.187.164.231/ticket-bay-api/api/utility/v1/callback'
                    ];

                    if (!$postData['msisdn']) {
                        $postData['msisdn'] = $msisdn;
                    }

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $TransID
                            . " | $msisdn"
                            . " | Service:$service_name"
                            . " | PayID:$pay_reference_id"
                            . " | Eletricity Payload::" . json_encode($postData));

                    $result = $this->sendJsonPostData($this->settings['Rewards']['ElectricityUrl'], $postData);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $TransID
                            . " | $msisdn"
                            . " | Service:$service_name"
                            . " | PayID:$pay_reference_id"
                            . " | Fulfil Eletricity::" . json_encode($result));

                    $res = json_decode($result['response']);

                    if ($result['statusCode'] != 200) {
                        //Requeue  Request
                        $this->infologger->addAlert(__LINE__ . ":" . __CLASS__
                                . " | MpesaCode:" . $TransID
                                . " | $msisdn"
                                . " | Service:$service_name"
                                . " | PayID:$pay_reference_id"
                                . " | ReQueue The Request");
                    }

                    $response_description = $res->statusDescription;
                    $data_response = isset($res->data) ? $res->data : false;
                    $receipt_number = isset($data_response->data->transaction_id) ? $data_response->data->transaction_id : "Failed#$pay_reference_id";
                    $account_details = isset($data_response->data->account_details) ? $data_response->data->account_details : "";
                    $token = isset($data_response->data->token) ? $data_response->data->token : "";

                    $narration = isset($data_response->message) ? $data_response->message : $response_description;
                    $response_code = isset($data_response->code) ? $data_response->code : $result['statusCode'];

                    if ($response_code == 200) {
                        $id = Profiling::saveProfileAccounts(['service_id' => $check_trxn[0]['service_id']
                                    , 'profile_id' => $attributes['profile_id']
                                    , "accounts" => $extra_data->account_number
                                    , "account_details" => $account_details]);

                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | MpesaCode:" . $TransID
                                . " | $msisdn"
                                . " | Service:$service_name"
                                . " | PayID:$pay_reference_id"
                                . " | Profiling::saveProfileAccounts::" . json_encode($id));

                        if ($account_type == 1) {
                            $narration .= ". With Madfun $token";
                        }
                    }

                    $callback_data = [
                        'purchase_type' => 'Beneficiary',
                        'transaction_id' => $pay_reference_id,
                        'response_code' => $response_code,
                        'response_description' => $response_description,
                        'narration' => $narration,
                        'receipt_number' => $receipt_number,];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $TransID
                            . " | $msisdn"
                            . " | Service:$service_name"
                            . " | PayID:$pay_reference_id"
                            . " | CreateTransactionCallback:$callback_id");

                    return $this->success(__LINE__ . ":" . __CLASS__, 'Ok', ['code' => $result['statusCode'], 'message' => $narration]);
                }
            } else {
                $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                        . " reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                        [':reference_id' => $referenceID]);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Payment Action Tickets Request:" . json_encode($check_trxn_profile));

                if (!$check_trxn_profile) {

                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | UniqueId:" . $TransID
                            . " | Mobile:" . $msisdn
                            . " | Event Ticket Profile Empty Account "
                    );
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Event Ticket Profile Not Found', ['code' => 404
                                , 'message' => 'The Event Ticket Profile not found']);
                }
                $amountPaid = $TransAmount;
                $error = [];
                $success = [];
                foreach ($check_trxn_profile as $profileTrans) {
                    $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,event_tickets_type.discount,events.posterURL,event_tickets_type.group_ticket_quantity, "
                            . "event_tickets_type.status,ticket_types.ticket_type,event_tickets_type.eventId FROM"
                            . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                            . " = event_tickets_type.typeId JOIN events ON "
                            . "event_tickets_type.eventId = events.eventID WHERE event_tickets_type.event_ticket_id"
                            . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                    if (!$check_evnt_type) {
                        array_push($error, ['message' => 'There is no such Event '
                            . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    if (($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount'])) > $amountPaid) {
                        array_push($error, ['message' => 'Failed to activate ticket, '
                            . 'Reason: Insufficient Fund', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    if ($check_evnt_type[0]['group_ticket_quantity'] == 1) {
                        $amountPaid = $amountPaid - ($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount']));
                    }
                    $check_trn_profile_state = $this->rawSelect("select * from "
                            . "event_profile_tickets_state where "
                            . "event_profile_ticket_id =:event_profile_ticket_id",
                            [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                    if (!$check_trn_profile_state) {
                        $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                . " | UniqueId:" . $TransID
                                . " | Mobile:" . $msisdn
                                . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                                . " | Record Not Found, Creating new record "
                                . "for Event Profile Ticket State "
                        );
                    }
                    $tickets = new Tickets();
                    $paramsState = [
                        'status' => 1,
                        'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                    ];

                    $eventState = $tickets->ProfileTicketState($paramsState);
                    if (!$eventState) {
                        array_push($error, ['message' => 'Failed. The ticket '
                            . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    $paramsUpdate = [
                        'event_ticket_id' => $profileTrans['event_ticket_id'],
                        'ticket_purchased' => 1,
                        'ticket_redeemed' => 0
                    ];
                    $tickets->EventTicketTypeUpdate($paramsUpdate, false, false,
                            $profileTrans[0]['event_tickets_option_id']);
                    $paramEvent = [
                        'eventID' => $check_evnt_type[0]['eventId']
                    ];

                    $eventData = $tickets->queryEvent($paramEvent);

                    array_push($success, [
                        'message' => 'Ticket Activated Successsful',
                        'QRCode' => $profileTrans['barcode'],
                        'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                        'eventName' => $eventData['eventName'],
                        'venue' => $eventData['venue'],
                        'start_date' => $eventData['dateStart'],
                        'QRCodeURL' => $profileTrans['barcodeURL'],
                        'posterURL' => $check_evnt_type[0]['posterURL'],
                        'ticketType' => $check_evnt_type[0]['ticket_type'],
                        'amount' => ($check_evnt_type[0]['amount'] - $check_evnt_type[0]['discount'])]);
                }
                $purchase_type = 'Beneficiary';
                if ($check_trxn_profile[0]['profile_id'] != $attributes['profile_id']) {
                    $purchase_type = 'Sponsor';
                }
                if (!$success) {

                    $callback_data = [
                        'purchase_type' => $purchase_type,
                        'transaction_id' => $pay_reference_id,
                        'response_code' => 402,
                        'response_description' => 'Processed Failed',
                        'extra_data' => json_encode($error),
                        'narration' => 'Failed to update event profile ticket state',
                        'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Failed to update event profile ticket state', ['code' => 404
                                , 'message' => $error]);
                }

                $callback_data = [
                    'purchase_type' => $purchase_type,
                    'transaction_id' => $pay_reference_id,
                    'response_code' => 200,
                    'response_description' => 'Processed Successfully',
                    'extra_data' => json_encode($success),
                    'narration' => 'Processed Successfully',
                    'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                $callback_id = Transactions::CreateTransactionCallback($callback_data);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | MpesaCode:" . $TransID
                        . " | $msisdn - "
                        . " | Account:" . $accountNumber
                        . " | CreateTransactionCallback:$callback_id");

                $profileAttribute = Profiling::QueryProfileMobile($msisdn);

                foreach ($success as $succ) {
                    if (count($success) <= 10) {
                        $sms = "Dear " . $profileAttribute['first_name'] . ", Find ticket(s) ";
                        $count = 1;
                        foreach ($success as $succ) {
                            if ($count == 1) {
                                $sms .= "for " . $succ['eventName'] . "\n\n";
                            }
                            $sms .= "" . $count . ": Link:" . $succ['ticketURL'] . " \nCode:" . $succ['QRCode'];
                            if ($profileAttribute['email'] != null) {
                                // sent email to clients
                                $this->infologger->info(__LINE__ . ":" . __CLASS__
                                        . " | Profile Attribute::" . json_encode($profileAttribute));
                                $paramsEmail = [
                                    "eventID" => $succ['eventId'],
                                    "type" => "TICKET_PURCHASED",
                                    "name" => $profileAttribute['first_name'] . " "
                                    . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
                                    "eventDate" => $succ['start_date'],
                                    "eventName" => $succ['eventName'],
                                    "eventAmount" => $succ['amount'],
                                    'eventType' => $succ['ticketType'],
                                    'QRcodeURL' => $succ['QRCodeURL'],
                                    'QRcode' => $succ['barcode'],
                                    'posterURL' => $succ['posterURL'],
                                    'venue' => $succ['venue']
                                ];
                                $postData = [
                                    "api_key" => $this->settings['ServiceApiKey'],
                                    "to" => $profileAttribute['email'],
                                    "from" => "noreply@madfun.com",
                                    "cc" => "",
                                    "subject" => "Ticket for Event: " . $succ['eventName'],
                                    "content" => "Ticket information",
                                    "extrac" => $paramsEmail
                                ];
                                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
                                $this->infologger->info(__LINE__ . ":" . __CLASS__
                                        . " | SendEmailWithoutAttachments Response::" . json_encode($mailResponse));
                            }
                            $count++;
                        }
                    } else {
                        $sms = 'Your Purchased of ' . count($success) . ' tickets'
                                . ' for Event' . $success[0]['eventName'] . ' at KES ' . $amountPaid . ''
                                . ' is successful. Click here to view tickets https://madfun.com/account';
                    }

                    $params = [
                        "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                        "msisdn" => $msisdn,
                        "message" => $sms,
                        "profile_id" => $attributes['profile_id'],
                        "created_by" => 'MPESA_PAYMENT',
                        "is_bulk" => false,
                        "link_id" => ""];

                    $message = new Messaging();
                    $message->LogOutbox($params);
                }
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 200
                            , 'success' => $success, 'error' => $error]);
            }

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Payment Received Invalid Services Successful', ['code' => 200]);
        } catch (Exception $ex) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Exception Error', ['code' => 500
                        , 'success' => "", 'error' => $ex->getMessage()], true);
        }
    }

    /**
     * paymentsAction
     * @return type
     */
    public function paymentsAction() {

        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Payment Action Request:" . json_encode($request->getJsonRawBody()));

        $payload = isset($data->payload->payment_data) ? $data->payload->payment_data : null;
        if (!$payload) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        $payload->mobile = $this->formatMobileNumber(preg_replace('~\D~', '', $payload->mobile));

        $object = new stdClass();
        $object->token = $payload->signature;
        $object->TransType = $payload->origin;
        $object->TransID = $payload->reciept_number;
        $object->TransTime = $payload->date; //date('Y-m-d H:i:s');
        $object->TransAmount = $payload->amount_paid;
        $object->BusinessShortCode = $payload->paybill;
        $object->BillRefNumber = trim($payload->account_number);
        $object->OrgAccountBalance = $payload->org_balance;
        $object->MSISDN = $payload->mobile;
        $KYCInfo[] = array("KYCName" => "[Personal Details][First Name]", "KYCValue" => $payload->fname);
        $KYCInfo[] = array("KYCName" => "[Personal Details][Middle Name]", "KYCValue" => $payload->surname);
        $KYCInfo[] = array("KYCName" => "[Personal Details][Last Name]", "KYCValue" => $payload->lname);
        $object->KYCInfo = $KYCInfo;

        try {
            if ($object->BusinessShortCode != $this->settings['Mpesa']['DefaultPaybillNumber']) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Duplicate', ['code' => 202, 'message' => 'Invalid Organisation Paybill']);
            }
            $duplicate = "SELECT id FROM mpesa_transaction WHERE mpesa_code=:mpesa_code";

            $check_duplicate = $this->rawSelect($duplicate, [':mpesa_code' => $object->TransID]);
            if ($check_duplicate) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Duplicate', ['code' => 202, 'message' => 'The Transaction is a duplicate']);
            }
            $sql = "INSERT INTO `mpesa_transaction`( `mpesa_code`, `mpesa_msisdn`"
                    . ", `mpesa_sender`, `mpesa_amount`, `mpesa_account`, `mpesa_time`"
                    . ", `org_balance`, `paybill`, `created_by`, `created`) "
                    . "VALUES (:mpesa_code,:mpesa_msisdn,:mpesa_sender,:mpesa_amount"
                    . ",:mpesa_account,:mpesa_time,:org_balance,:paybill,:created_by,NOW());";

            $BillRefNumber = trim(preg_replace('/[\t\n\r\s]+/', '', $object->BillRefNumber));
            $object->BillRefNumber = str_replace("+", "", $BillRefNumber);

            $params = [
                ':mpesa_account' => $object->BillRefNumber,
                ':mpesa_code' => $object->TransID,
                ':mpesa_msisdn' => $object->MSISDN,
                ':mpesa_sender' => trim($payload->fname . " " . $payload->surname . " " . $payload->lname),
                ':mpesa_amount' => $object->TransAmount,
                ':mpesa_time' => $object->TransTime,
                ':org_balance' => $object->OrgAccountBalance,
                ':paybill' => $object->BusinessShortCode,
                ':created_by' => $object->TransType,];

            $mpesa_trxnId = $this->rawInsert($sql, $params);

            $auth = new Authenticate();

            $hasEventShows = 0;
            $ccountType = substr($object->BillRefNumber, 0, 3);

            if (strtoupper($ccountType) == 'MOD') {
                $hasEventShows = 1;
            }
            if (strtoupper($ccountType) == 'MF-') {
                ///Send Post data
                $resultStaging = $this->sendJsonPostData("https://stage-204.ridgeways.xyz/api/payment/Confirm", $data);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Response Staging Payload:" . json_encode($resultStaging));
            }

            $accountNumber = substr($object->BillRefNumber, 3);

            $select_trxn_initiated = "select transaction_initiated.transaction_id,"
                    . "transaction_initiated.profile_id,transaction_initiated.service_id,"
                    . "transaction_initiated.reference_id,transaction_initiated.source,"
                    . "transaction_initiated.description,transaction_initiated.extra_data,"
                    . "profile.msisdn,transaction_initiated.created from "
                    . "transaction_initiated join profile on "
                    . "transaction_initiated.profile_id  = profile.profile_id  WHERE "
                    . " transaction_initiated.transaction_id=:transaction_id LIMIT 1";
            $check_trxn = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $accountNumber]);

            if (!$check_trxn) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $object->TransID
                        . " | Mobile:" . $object->MSISDN
                        . " | DIRECT_DEPOSIT Transactions Empty Account "
                );
                $accountNumber = strtoupper($object->BillRefNumber);
                $select_keywords = "SELECT * FROM `event_keywords` WHERE "
                        . "`keyword`=:keyword LIMIT 1";
                $check_keywords = $this->rawSelect($select_keywords,
                        [':keyword' => $accountNumber]);
                if (!$check_keywords) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Transaction Not Found', ['code' => 404
                                , 'message' => 'The Transaction not found']);
                }

                if (strlen($object->MSISDN) > 12) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Transaction Not Found', ['code' => 404
                                , 'message' => 'The Transaction not found']);
                }

                $updateKeywords = $this->rawUpdateWithParams("UPDATE `event_keywords`"
                        . " SET `amount_received` = `amount_received` + :amount "
                        . "WHERE  `event_keyword_id` = :event_keyword_id LIMIT 1 ",
                        [':amount' => $object->TransAmount, ':event_keyword_id' =>
                            $check_keywords[0]['event_keyword_id']]);

                if ($updateKeywords) {
                    $extra1 = [
                        'paid_msisdn' => $object->MSISDN,
                        'account_number' => $accountNumber,];

                    $trx_params = [
                        'amount' => $object->TransAmount,
                        'service_id' => $check_keywords[0]['event_keyword_id'],
                        'profile_id' => Profiling::Profile($object->MSISDN),
                        'reference_id' => $mpesa_trxnId, //,
                        'source' => $check_keywords[0]['keyword'] . "_EVENTID:" . $check_keywords[0]['keyword'],
                        'description' => "Direct Deposit using Keywords" . $check_keywords[0]['keyword'] . "_EVENTID:" . $check_keywords[0]['keyword'],
                        'extra_data' => json_encode($extra1),];

                    $pay_reference_id = Transactions::CreateTransaction($trx_params);
                }
//                $sms = $check_keywords[0]['sms'];
//                $params = [
//                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
//                    "msisdn" => $object->MSISDN,
//                    "message" => $sms . ".\n Madfun! For Queries call "
//                    . "" . $this->settings['Helpline'],
//                    "profile_id" => Profiling::Profile($object->MSISDN),
//                    "created_by" => 'MPESA_PAYMENT',
//                    "is_bulk" => false,
//                    "link_id" => ""];
//
//                $message = new Messaging();
//                $message->LogOutbox($params);
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Transaction Not Found. But Keyword Found', ['code' => 200
                            , 'message' => 'Transaction Not Found. But Keyword Found']);
            }


            $attributes['profile_id'] = $check_trxn[0]['profile_id'];

            $attributes['first_name'] = str_replace("`", "", $payload->fname);
            $attributes['surname'] = "";
            $attributes['last_name'] = "";
            $attributes['network'] = $this->getMobileNetwork($check_trxn[0]['msisdn']);
            $attributes['source'] = $check_trxn[0]['source'];
            $attributes['profile_attribute_id'] = Profiling::ProfileAttribution($attributes);

            $extra1 = [
                'paid_msisdn' => $check_trxn[0]['msisdn'],
                'account_number' => $accountNumber,];

            $trx_params = [
                'amount' => $object->TransAmount,
                'service_id' => $check_trxn[0]['service_id'],
                'profile_id' => $attributes['profile_id'],
                'reference_id' => $mpesa_trxnId, //,
                'source' => $check_trxn[0]['source'],
                'description' => $check_trxn[0]['reference_id'],
                'extra_data' => json_encode($extra1),];

            $pay_reference_id = Transactions::CreateTransaction($trx_params);

            if ($check_trxn[0]['service_id'] == 8 && strtoupper($ccountType) == 'STR') {

                $select_stream_profile = "SELECT * FROM stream_profile_request WHERE"
                        . " reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_stream_profile,
                        [':reference_id' => $check_trxn[0]['reference_id']]);

                if (!$check_trxn_profile) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Streaming Profile Request not Found', ['code' => 404
                                , 'message' => 'Streaming Profile Request not Found']);
                }

                $update_stream_profile = "update stream_profile_request set status = 1  WHERE"
                        . " id=:id";

                $this->rawUpdateWithParams($update_stream_profile,
                        [':id' => $check_trxn_profile[0]['id']]);
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Payment for Video Successful', ['code' => 200
                            , 'message' => 'Payment for Video Successful']);
            } else if (in_array($check_trxn[0]['service_id'], [3, 4, 5])) {


                $extra_data = json_decode($check_trxn[0]['extra_data']);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Utilities:" . $extra_data->account_number);
                if ($check_trxn[0]['service_id'] == 3) {
                    //Airtime
                    $postData = [
                        'apiKey' => $this->settings['ServiceApiKey'],
                        'unique_id' => "MADFUN" . $pay_reference_id,
                        'amount' => $object->TransAmount,
                        'msisdn' => $extra_data->account_number,
                        'callback' => "http://35.187.164.231/madfun-api/api/utility/v1/callback",
                        'provider' => ""];

                    if (!$postData['msisdn']) {
                        $postData['msisdn'] = $check_trxn[0]['msisdn'];
                    }
                    $result = $this->sendJsonPostData($this->settings['Rewards']['VasproAirtimeUrl'], $postData);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $object->TransID
                            . " | " . $check_trxn[0]['msisdn']
                            . " | ServiceId:" . $check_trxn[0]['service_id']
                            . " | PayID:$pay_reference_id"
                            . " | Request::" . json_encode($postData)
                            . " | Fulfil Airtime::" . json_encode($result));

                    $res = json_decode($result['response']);

                    $response_description = $res->statusDescription;
                    $data_response = isset($res->data) ? $res->data : false;
                    $receipt_number = isset($data_response->data->transaction_id) ? $data_response->data->transaction_id : "Failed#$pay_reference_id";

                    $narration = isset($data_response->message) ? $data_response->message : $response_description;
                    $response_code = isset($data_response->code) ? $data_response->code : $result['statusCode'];

                    if ($response_code == 502) {
                        $response_description = 'Request TimedOut';
                        $narration = ( $result['error'] != null) ? $result['error'] : 'Connection Timed out.';
                    }

                    $callback_data = [
                        'purchase_type' => ($extra_data->account_number == $check_trxn[0]['msisdn']) ? 'Beneficiary' : 'Sponsor',
                        'transaction_id' => $pay_reference_id,
                        'response_code' => $response_code,
                        'response_description' => $response_description,
                        'narration' => $narration,
                        'receipt_number' => $receipt_number,];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $object->TransID
                            . " | " . $check_trxn[0]['msisdn']
                            . " | Service: Airtime Purchase"
                            . " | PayID:$pay_reference_id"
                            . " | CreateTransactionCallback:$callback_id");

                    return $this->success(__LINE__ . ":" . __CLASS__, 'Ok', ['code' => $result['statusCode'], 'message' => $narration]);
                }
                if (in_array($check_trxn[0]['service_id'], [4, 5])) {
                    $service_name = "";

                    $account_type = 0;
                    if ($check_trxn[0]['service_id'] == 4) {
                        $account_type = 1;
                        $service_name = "PrePaid Tokens";
                    } else {
                        $account_type = 2;
                        $service_name = "PostPaid Electricity";
                    }

                    $postData = [
                        'apiKey' => $this->settings['ServiceApiKey'],
                        'unique_id' => "MADFUN" . $pay_reference_id,
                        'amount' => $object->TransAmount,
                        'msisdn' => $check_trxn[0]['msisdn'],
                        'account_type' => "$account_type",
                        'bill_reference' => $extra_data->account_number,
                        'callback' => 'http://35.187.164.231/madfun-api/api/utility/v1/callback'
                    ];

                    if (!$postData['msisdn']) {
                        $postData['msisdn'] = $check_trxn[0]['msisdn'];
                    }

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $object->TransID
                            . " | " . $check_trxn[0]['msisdn']
                            . " | Service:$service_name"
                            . " | PayID:$pay_reference_id"
                            . " | Eletricity Payload::" . json_encode($postData));

                    $result = $this->sendJsonPostData($this->settings['Rewards']['ElectricityUrl'], $postData);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $object->TransID
                            . " | " . $check_trxn[0]['msisdn']
                            . " | Service:$service_name"
                            . " | PayID:$pay_reference_id"
                            . " | Fulfil Eletricity::" . json_encode($result));

                    $res = json_decode($result['response']);

                    if ($result['statusCode'] != 200) {
                        //Requeue  Request
                        $this->infologger->addAlert(__LINE__ . ":" . __CLASS__
                                . " | MpesaCode:" . $object->TransID
                                . " | $object->MSISDN  - $source"
                                . " | Service:$service_name"
                                . " | PayID:$pay_reference_id"
                                . " | ReQueue The Request");
                    }

                    $response_description = $res->statusDescription;
                    $data_response = isset($res->data) ? $res->data : false;
                    $receipt_number = isset($data_response->data->transaction_id) ? $data_response->data->transaction_id : "Failed#$pay_reference_id";
                    $account_details = isset($data_response->data->account_details) ? $data_response->data->account_details : "";
                    $token = isset($data_response->data->token) ? $data_response->data->token : "";

                    $narration = isset($data_response->message) ? $data_response->message : $response_description;
                    $response_code = isset($data_response->code) ? $data_response->code : $result['statusCode'];

                    if ($response_code == 200) {
                        $id = Profiling::saveProfileAccounts(['service_id' => $check_trxn[0]['service_id']
                                    , 'profile_id' => $attributes['profile_id']
                                    , "accounts" => $extra_data->account_number
                                    , "account_details" => $account_details]);

                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | MpesaCode:" . $object->TransID
                                . " | $object->MSISDN  - $source"
                                . " | Service:$service_name"
                                . " | PayID:$pay_reference_id"
                                . " | Profiling::saveProfileAccounts::" . json_encode($id));

                        if ($account_type == 1) {
                            $narration .= ". With Token $token";
                        }
                    }

                    $callback_data = [
                        'purchase_type' => 'Beneficiary',
                        'transaction_id' => $pay_reference_id,
                        'response_code' => $response_code,
                        'response_description' => $response_description,
                        'narration' => $narration,
                        'receipt_number' => $receipt_number,];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | MpesaCode:" . $object->TransID
                            . " | $object->MSISDN  - $source"
                            . " | Service:$service_name"
                            . " | PayID:$pay_reference_id"
                            . " | CreateTransactionCallback:$callback_id");

                    return $this->success(__LINE__ . ":" . __CLASS__, 'Ok', ['code' => $result['statusCode'], 'message' => $narration]);
                }
            } else {
                $referenceID = $check_trxn[0]['reference_id'];

                $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                        . " reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                        [':reference_id' => $referenceID]);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Payment Action Tickets Request:" . json_encode($check_trxn_profile));

                if (!$check_trxn_profile) {

                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | UniqueId:" . $object->TransID
                            . " | Mobile:" . $object->MSISDN
                            . " | Event Ticket Profile Empty Account "
                    );
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Event Ticket Profile Not Found', ['code' => 404
                                , 'message' => 'The Event Ticket Profile not found']);
                }
                $amountPaid = $object->TransAmount;
                $error = [];
                $success = [];
                $isPreOrder = 0;
                $preOrderMessage = "";
                foreach ($check_trxn_profile as $profileTrans) {
                    if ($hasEventShows == 1) {
                        $check_evnt_type = $this->rawSelect("SELECT event_show_tickets_type.amount,"
                                . "event_show_tickets_type.isPreOrder,"
                                . "event_show_tickets_type.preOrderCustomMessage,"
                                . "event_show_tickets_type.maxCap,"
                                . "event_show_tickets_type.discount,events.posterURL,"
                                . "event_shows.show,DATE_FORMAT(event_shows.start_date, '%d %M %Y') as startDate,"
                                . "event_show_tickets_type.group_ticket_quantity,"
                                . "event_show_venue.venue,events.event_ticket_info, "
                                . "event_show_tickets_type.status,ticket_types.ticket_type,"
                                . "event_show_tickets_type.event_show_venue_id,events.eventID AS eventId FROM "
                                . "event_show_tickets_type join event_show_venue on"
                                . " event_show_tickets_type.event_show_venue_id =  "
                                . "event_show_venue.event_show_venue_id join event_shows"
                                . " on event_show_venue.event_show_id  = "
                                . "event_shows.event_show_id join events on "
                                . "event_shows.eventID = events.eventID JOIN ticket_types"
                                . " ON ticket_types.typeId = event_show_tickets_type.typeId WHERE "
                                . "event_show_tickets_type.event_ticket_show_id"
                                . " = :event_ticket_show_id", [":event_ticket_show_id"
                            => $profileTrans['event_ticket_id']]);
                    } else {
                        $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,"
                                . "event_tickets_type.discount,events.posterURL,"
                                . "event_tickets_type.group_ticket_quantity, "
                                . "event_tickets_type.status,ticket_types.ticket_type,"
                                . "event_tickets_type.eventId,events.venue, "
                                . "event_tickets_type.isPreOrder,events.event_ticket_info,"
                                . "event_tickets_type.preOrderCustomMessage,event_tickets_type.maxCap FROM"
                                . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                                . " = event_tickets_type.typeId JOIN events ON "
                                . "event_tickets_type.eventId = events.eventID WHERE event_tickets_type.event_ticket_id"
                                . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                    }



                    if (!$check_evnt_type) {
                        array_push($error, ['message' => 'There is no such Event '
                            . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    if (($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount'])) > $amountPaid) {
                        array_push($error, ['message' => 'Failed to activate ticket, '
                            . 'Reason: Insufficient Fund', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    if ($check_evnt_type[0]['group_ticket_quantity'] == 1) {
                        $amountPaid = $amountPaid - ($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount']));
                    }
                    $check_trn_profile_state = $this->rawSelect("select * from "
                            . "event_profile_tickets_state where "
                            . "event_profile_ticket_id =:event_profile_ticket_id",
                            [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                    if (!$check_trn_profile_state) {
                        $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                . " | UniqueId:" . $object->TransID
                                . " | Mobile:" . $object->MSISDN
                                . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                                . " | Record Not Found, Creating new record "
                                . "for Event Profile Ticket State "
                        );
                    }
                    $tickets = new Tickets();

                    if ($check_evnt_type[0]['discount'] > 0) {
                        $paramsDiscount = [
                            'event_ticket_id' => $profileTrans['event_ticket_id'],
                            'hasMultipleShow' => $hasEventShows,
                            'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                            'discount' => $check_evnt_type[0]['discount']
                        ];
                        $tickets->addDiscount($paramsDiscount);
                    }
                    $paramsState = [
                        'status' => 1,
                        'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                    ];

                    $eventState = $tickets->ProfileTicketState($paramsState);
                    if (!$eventState) {
                        array_push($error, ['message' => 'Failed. The ticket '
                            . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    $isPreOrder = $check_evnt_type[0]['isPreOrder'];
                    $preOrderMessage = $check_evnt_type[0]['preOrderCustomMessage'];
                    if ($isPreOrder == 1) {
                        $paramsVisible = [
                            'isTicketVisible' => 0,
                            'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id']
                        ];
                        $tickets->EventTicketVisible($paramsVisible);
                    }
                    $paramsUpdate = [
                        'event_ticket_id' => $profileTrans['event_ticket_id'],
                        'ticket_purchased' => 1,
                        'ticket_redeemed' => 0
                    ];
                    $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $profileTrans['event_tickets_option_id'], $hasEventShows);
                    $paramEvent = [
                        'eventID' => $check_evnt_type[0]['eventId']
                    ];

                    $eventData = $tickets->queryEvent($paramEvent);
                    $eventStartDate = $eventData['dateStart'];
                    $eventName = $eventData['eventName'];
                    if ($hasEventShows == 1) {
                        $eventStartDate = $check_evnt_type[0]['startDate'];
                        $eventName = $eventData['eventName'] . " - " . $check_evnt_type[0]['show'];
                    }



                    array_push($success, [
                        'message' => 'Ticket Activated Successsful',
                        'event_ticket_info' => $check_evnt_type[0]['event_ticket_info'],
                        'eventId' => $check_evnt_type[0]['eventId'],
                        'QRCode' => $profileTrans['barcode'],
                        'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                        'eventName' => $eventName,
                        'venue' => $check_evnt_type[0]['venue'],
                        'start_date' => $eventStartDate,
                        'QRCodeURL' => $profileTrans['barcodeURL'],
                        'posterURL' => $check_evnt_type[0]['posterURL'],
                        'ticketType' => $check_evnt_type[0]['ticket_type'],
                        'amount' => ($check_evnt_type[0]['amount'] - $check_evnt_type[0]['discount'])]);
                }
                $purchase_type = 'Beneficiary';
                if ($check_trxn_profile[0]['profile_id'] != $attributes['profile_id']) {
                    $purchase_type = 'Sponsor';
                }
                if (!$success) {

                    $callback_data = [
                        'purchase_type' => $purchase_type,
                        'transaction_id' => $pay_reference_id,
                        'response_code' => 402,
                        'response_description' => 'Processed Failed',
                        'extra_data' => json_encode($error),
                        'narration' => 'Failed to update event profile ticket state',
                        'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Failed to update event profile ticket state', ['code' => 404
                                , 'message' => $error]);
                }

                $callback_data = [
                    'purchase_type' => $purchase_type,
                    'transaction_id' => $pay_reference_id,
                    'response_code' => 200,
                    'response_description' => 'Processed Successfully',
                    'extra_data' => json_encode($success),
                    'narration' => 'Processed Successfully',
                    'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                $callback_id = Transactions::CreateTransactionCallback($callback_data);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | MpesaCode:" . $object->TransID
                        . " | $object->MSISDN - "
                        . " | Account:" . $accountNumber
                        . " | CreateTransactionCallback:$callback_id");

                $profileAttribute = Profiling::QueryProfileMobile($object->MSISDN);
                $ticketsData = [];

                if (count($success) <= 10) {
                    $sms = "Dear " . $payload->fname . ",\nFind ticket(s) ";
                    $count = 1;
                    foreach ($success as $succ) {
                        if ($count == 1) {
                            $sms .= "for " . $succ['eventName'] . "\n\n";
                        }
                        $sms .= "" . $count . ": Link:" . $succ['ticketURL'] . " \nCode:" . $succ['QRCode'] . "\n";
                        if ($profileAttribute['email'] != null && $isPreOrder == 0) {
                            // sent email to clients
                            $this->infologger->info(__LINE__ . ":" . __CLASS__
                                    . " | Profile Attribute::" . json_encode($profileAttribute));
                            $ticketsIn = [
                                'ticketName' => $succ['ticketType'],
                                'currency' => 'KES',
                                'amount' => $succ['amount'],
                                'QrCode' => $succ['QRCode']
                            ];

                            array_push($ticketsData, $ticketsIn);
//                            $paramsEmail = [
//                                "eventID" => $succ['eventId'],
//                                "type" => "TICKET_PURCHASED",
//                                "name" => $profileAttribute['first_name'] . " "
//                                . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
//                                "eventDate" => $succ['start_date'],
//                                "eventName" => $succ['eventName'],
//                                "eventAmount" => $succ['amount'],
//                                'eventType' => $succ['ticketType'],
//                                'QRcodeURL' => $succ['QRCodeURL'],
//                                'QRcode' => $succ['QRCode'],
//                                'posterURL' => $succ['posterURL'],
//                                'venue' => $succ['venue']
//                            ];
//                            $postData = [
//                                "api_key" => $this->settings['ServiceApiKey'],
//                                "to" => $profileAttribute['email'],
//                                "from" => "noreply@madfun.com",
//                                "cc" => "",
//                                "subject" => "Ticket for Event: " . $succ['eventName'],
//                                "content" => "Ticket information",
//                                "extrac" => $paramsEmail
//                            ];
//                            $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
//                            $this->infologger->info(__LINE__ . ":" . __CLASS__
//                                    . " | SendEmailWithoutAttachments Response::" . json_encode($mailResponse) . " Payload::" . json_encode($postData));
                        }
                        $count++;
                    }
                } else {
                    $sms = 'Your Purchased of ' . count($success) . ' tickets'
                            . ' for Event' . $success[0]['eventName'] . ' at KES ' . $amountPaid . ''
                            . ' is successful. Click here to view tickets https://madfun.com/account';
                }

                if ($profileAttribute['email'] != null && $isPreOrder == 0) {
                    $paramsEmail = [
                        "eventID" => $success[0]['eventId'],
                        "orderNumber" => $object->BillRefNumber,
                        "paymentMode" => "M-PESA",
                        "name" => $profileAttribute['first_name'] . " "
                        . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
                        "eventDate" => $success[0]['start_date'],
                        "eventName" => $success[0]['eventName'],
                        "amountPaid" => $object->TransAmount,
                        'msisdn' => $check_trxn[0]['msisdn'],
                        'ticketsArray' => $ticketsData,
                        'posterURL' => $success[0]['posterURL'],
                        'venue' => $success[0]['venue'],
                        'eventTicketInfo' => $success[0]['event_ticket_info'],
                    ];
                    $postData = [
                        "api_key" => $this->settings['ServiceApiKey'],
                        "to" => $profileAttribute['email'],
                        "from" => "noreply@madfun.com",
                        "cc" => "",
                        "subject" => "Ticket for Event: " . $success[0]['eventName'],
                        "content" => "Ticket information",
                        "extrac" => $paramsEmail
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                            $postData, $this->settings['ServiceApiKey'], 3);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailWithoutAttachments Response::" .
                            json_encode($mailResponse) . " Payload::" .
                            json_encode($postData));
                }

                if ($isPreOrder == 1 && $preOrderMessage != "") {
                    $sms = $preOrderMessage;
                }
                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $check_trxn[0]['msisdn'],
                    "message" => $sms . "\nHelpline "
                    . "" . $this->settings['Helpline'],
                    "profile_id" => $attributes['profile_id'],
                    "created_by" => 'MPESA_PAYMENT',
                    "is_bulk" => false,
                    "link_id" => ""];
                $message = new Messaging();
                $message->LogOutbox($params);

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 200
                            , 'success' => $success, 'error' => $error]);
            }
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Payment Received Invalid Services Successful', ['code' => 200]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:" . $ex->getCode()
                    . " | Exception Code:" . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Internal Server Error.', ['code' => 500
                        , 'message' => $ex->getMessage()], true);
        }
    }

    public function dpoCallbackAction() {
        $rawXml = $this->request->getRawBody();

        

        if (empty($rawXml)) {
            // Debug: see what was actually received
           return $this->dpoXMLResponse();
        }

        file_put_contents("/tmp/callback_debug.txt", $rawXml . "\n---\n", FILE_APPEND);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawXml, "SimpleXMLElement", LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

           return $this->dpoXMLResponse();
        }

        $data = json_decode(json_encode($xml), true);

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | dpoCallbackAction:" . json_encode($data)." IP::".$this->getClientIPAddress());
        
        if (!in_array($this->getClientIPAddress(), ['34.250.168.72'])) {

            return $this->dpoXMLResponse();
        }

        $Result = $data['Result'] ?? null;
        $ResultExplanation = $data['ResultExplanation'] ?? null;
        $TransactionToken = $data['TransactionToken'] ?? null;
        $TransID = $data['TransactionRef'] ?? null;
        $CustomerName = $data['CustomerName'] ?? null;
        $CustomerCredit = $data['CustomerCredit'] ?? null;
        $CCDapproval = $data['TransactionApproval'] ?? null;
        $TransactionCurrency = $data['TransactionCurrency'] ?? null;
        $TransactionAmount = $data['TransactionAmount'] ?? null;
        $FraudAlert = $data['FraudAlert'] ?? null;
        $FraudExplanation = $data['FraudExplnation'] ?? null; // note XML key spelling
        $TransactionNetAmount = $data['TransactionNetAmount'] ?? null;
        $TransactionSettlementDate = $data['TransactionSettlementDate'] ?? null;
        $TransactionRollingReserveAmt = $data['TransactionRollingReserveAmount'] ?? null;
        $TransactionRollingReserveDate = $data['TransactionRollingReserveDate'] ?? null;
        $CustomerPhone = $data['CustomerPhone'] ?? null;
        $CustomerCountry = $data['CustomerCountry'] ?? null;
        $CustomerAddress = $data['CustomerAddress'] ?? null;
        $CustomerCity = $data['CustomerCity'] ?? null;
        $CustomerZip = $data['CustomerZip'] ?? null;
        $MobilePaymentRequest = $data['MobilePaymentRequest'] ?? null;
        $CompanyRef = $data['AccRef'] ?? null;

        if (!$TransID || !$CCDapproval || !$TransactionToken || !$CompanyRef || !$FraudAlert) {

            return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
        }



        $duplicate = "SELECT id FROM dpo_transaction WHERE TransID=:TransID";

        $check_duplicate = $this->rawSelect($duplicate, [':TransID' => $TransID]);
        if ($check_duplicate) {
            return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
        }

        try {

            $fraudCode = $data['FraudAlert'] ?? "000";

            switch ($fraudCode) {
                case "000": $fraudStatus = "Genuine transaction";
                    break;
                case "001": $fraudStatus = "Low Risk (Not checked)";
                    break;
                case "002": $fraudStatus = "Suspected Fraud Alert";
                    break;
                case "003": $fraudStatus = "Fraud alert cleared (Merchant marked as clear)";
                    break;
                case "004": $fraudStatus = "Suspect Fraud Alert";
                    break;
                case "005": $fraudStatus = "Fraud alert cleared (Genuine transaction)";
                    break;
                case "006": $fraudStatus = "Black - Fraudulent transaction";
                    break;
                default: $fraudStatus = "Unknown Code";
                    break;
            }

            $dpoTransactionQuery = "INSERT INTO dpo_transaction (TransID,CCDapproval,"
                    . "account,TransactionToken,description,status,created) VALUES (:TransID,:CCDapproval,"
                    . ":account,:TransactionToken,:description,:status,NOW())";

            $paramsDPOtrans = [
                ':TransID' => $TransID,
                ':CCDapproval' => $CCDapproval,
                ':description' => $fraudStatus,
                ':status' => $fraudCode,
                ':account' => $CompanyRef,
                ':TransactionToken' => $TransactionToken
            ];
            $dpo_trxnId = $this->rawInsert($dpoTransactionQuery, $paramsDPOtrans);

            if ($fraudCode != "000") {
                return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
            }

            $ccountType = substr($CompanyRef, 0, 3);
            $hasEventShows = 0;
            if (strtoupper($ccountType) == 'MOD') {
                $hasEventShows = 1;
            }

            $accountNumber = substr($CompanyRef, 3);

            $select_trxn_initiated = "SELECT transaction_initiated.extra_data->>'$.amount' as amount, "
                    . "transaction_id,profile_id,service_id,reference_id,"
                    . "source,description,created FROM `transaction_initiated` WHERE "
                    . "`transaction_id`=:transaction_id LIMIT 1";
            $check_trxn = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $accountNumber]);

            if (!$check_trxn) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $TransID
                        . " | DPO Transaction Id:" . $dpo_trxnId
                        . " | DIRECT_DEPOSIT Transactions Empty Account "
                );
                return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
            }

            $extra1 = [
                'paid_msisdn' => Profiling::QueryMobile($check_trxn[0]['profile_id']),
                'account_number' => $accountNumber,];

            $trx_params = [
                'amount' => $check_trxn[0]['amount'],
                'service_id' => $check_trxn[0]['service_id'],
                'profile_id' => $check_trxn[0]['profile_id'],
                'reference_id' => $dpo_trxnId,
                'source' => $check_trxn[0]['source'],
                'description' => $check_trxn[0]['description'],
                'extra_data' => json_encode($extra1),];

            Transactions::CreateTransaction($trx_params);

            $referenceID = $check_trxn[0]['reference_id'];

            if (strtoupper($ccountType) == 'STR') {

                $select_stream_profile = "select stream_profile_request.id, "
                        . "stream_profile_request.profile_id,stream_profile_request.order_key,"
                        . "stream_profile_request.currency,stream_profile_request.reference_id,"
                        . "stream_profile_request.returnURL,stream_profile_request.cancelURL,"
                        . "stream_profile_request.status,stream_profile_request.created, "
                        . "profile_attribute.first_name, profile_attribute.last_name, "
                        . "user.email from stream_profile_request join profile_attribute "
                        . "on stream_profile_request.profile_id  =profile_attribute.profile_id "
                        . "join user on stream_profile_request.profile_id  =  user.profile_id WHERE"
                        . " stream_profile_request.reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_stream_profile,
                        [':reference_id' => $referenceID]);

                if (!$check_trxn_profile) {

                    return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
                }

                $update_stream_profile = "update stream_profile_request set status = 1  WHERE"
                        . " id=:id";

                $this->rawUpdateWithParams($update_stream_profile,
                        [':id' => $check_trxn_profile[0]['id']]);

                return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
            }



            $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                    . " reference_id=:reference_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':reference_id' => $referenceID]);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | dpo Payment Action Tickets Request:" . json_encode($check_trxn_profile));

            if (!$check_trxn_profile) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $TransID
                        . " | DPO Transaction Id:" . $dpo_trxnId
                        . " | Event Ticket Profile Empty Account "
                );
                return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
            }
            $amountPaid = $check_trxn[0]['amount'];
            $error = [];
            $success = [];
            foreach ($check_trxn_profile as $profileTrans) {
                if ($hasEventShows == 1) {
                    $check_evnt_type = $this->rawSelect("SELECT event_show_tickets_type.amount,"
                            . "event_show_tickets_type.discount,events.posterURL,"
                            . "event_show_tickets_type.group_ticket_quantity, "
                            . "event_show_tickets_type.status,ticket_types.ticket_type,"
                            . "event_show_tickets_type.event_show_venue_id,events.eventID AS eventId FROM "
                            . "event_show_tickets_type join event_show_venue on"
                            . " event_show_tickets_type.event_show_venue_id =  "
                            . "event_show_venue.event_show_venue_id join event_shows"
                            . " on event_show_venue.event_show_id  = "
                            . "event_shows.event_show_id join events on "
                            . "event_shows.eventID = events.eventID JOIN "
                            . "ticket_types ON ticket_types.typeId = event_show_tickets_type.typeId"
                            . " WHERE "
                            . "event_show_tickets_type.event_ticket_show_id"
                            . " = :event_ticket_show_id", [":event_ticket_show_id"
                        => $profileTrans['event_ticket_id']]);
                } else {
                    $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,event_tickets_type.discount,events.posterURL,event_tickets_type.group_ticket_quantity, "
                            . "event_tickets_type.status,ticket_types.ticket_type,event_tickets_type.eventId FROM"
                            . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                            . " = event_tickets_type.typeId JOIN events ON "
                            . "event_tickets_type.eventId = events.eventID WHERE event_tickets_type.event_ticket_id"
                            . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                }

                if (!$check_evnt_type) {
                    array_push($error, ['message' => 'There is no such Event '
                        . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if (($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount'])) > $amountPaid) {
                    array_push($error, ['message' => 'Failed to activate ticket, '
                        . 'Reason: Insufficient Fund', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if ($check_evnt_type[0]['group_ticket_quantity'] == 1) {
                    $amountPaid = $amountPaid - ($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount']));
                }
                $check_trn_profile_state = $this->rawSelect("select * from "
                        . "event_profile_tickets_state where "
                        . "event_profile_ticket_id =:event_profile_ticket_id",
                        [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                if (!$check_trn_profile_state) {
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | UniqueId:" . $TransID
                            . " | DPO Transaction Id:" . $dpo_trxnId
                            . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                            . " | Record Not Found, Creating new record "
                            . "for Event Profile Ticket State "
                    );
                }
                $tickets = new Tickets();
                $paramsState = [
                    'status' => 1,
                    'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                ];

                $eventState = $tickets->ProfileTicketState($paramsState);
                if (!$eventState) {
                    array_push($error, ['message' => 'Failed. The ticket '
                        . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                $paramsUpdate = [
                    'event_ticket_id' => $profileTrans['event_ticket_id'],
                    'ticket_purchased' => 1,
                    'ticket_redeemed' => 0
                ];
                $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $profileTrans['event_tickets_option_id'], $hasEventShows);
                $paramEvent = [
                    'eventID' => $check_evnt_type[0]['eventId']
                ];

                $eventData = $tickets->queryEvent($paramEvent);

                array_push($success, [
                    'eventId' => $check_evnt_type[0]['eventId'],
                    'message' => 'Ticket Activated Successsful',
                    'QRCode' => $profileTrans['barcode'],
                    'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                    'eventName' => $eventData['eventName'],
                    'venue' => $eventData['venue'],
                    'start_date' => $eventData['dateStart'],
                    'QRCodeURL' => $profileTrans['barcodeURL'],
                    'posterURL' => $check_evnt_type[0]['posterURL'],
                    'ticketType' => $check_evnt_type[0]['ticket_type'],
                    'amount' => ($check_evnt_type[0]['amount'] - $check_evnt_type[0]['discount'])]);
            }

            $purchase_type = 'Beneficiary';
            if ($check_trxn_profile[0]['profile_id'] != $check_trxn[0]['profile_id']) {
                $purchase_type = 'Sponsor';
            }
            if (!$success) {

                $callback_data = [
                    'purchase_type' => $purchase_type,
                    'transaction_id' => $dpo_trxnId,
                    'response_code' => 402,
                    'response_description' => 'Processed Failed',
                    'extra_data' => json_encode($error),
                    'narration' => 'Failed to update event profile ticket state',
                    'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                $callback_id = Transactions::CreateTransactionCallback($callback_data);

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Failed to update event profile ticket state', ['code' => 404
                            , 'message' => $error]);
            }

            $callback_data = [
                'purchase_type' => $purchase_type,
                'transaction_id' => $dpo_trxnId,
                'response_code' => 200,
                'response_description' => 'Processed Successfully',
                'extra_data' => json_encode($data),
                'narration' => 'Processed Successfully',
                'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
            $callback_id = Transactions::CreateTransactionCallback($callback_data);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | UniqueId:" . $TransID
                    . " | DPO Transaction Id:" . $dpo_trxnId
                    . " | Account:" . $accountNumber
                    . " | CreateTransactionCallback:$callback_id");

            $profileAttribute = Profiling::QueryProfileProfileId($check_trxn[0]['profile_id']);

            $sms = "";
            $ticketsData = [];
            foreach ($success as $succ) {

                $sms .= "Dear " . $profileAttribute['first_name'] . " " . $profileAttribute['last_name'] . ", Your " . $succ['eventName'] . " ticket "
                        . "is " . $succ['QRCode'] . ". View your ticket from "
                        . $succ['ticketURL'] . " \n";

                if ($profileAttribute['email'] != null) {
                    // sent email to clients
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Profile Attribute::" . json_encode($profileAttribute));

                    $ticketsIn = [
                        'ticketName' => $succ['ticketType'],
                        'currency' => $succ['currency'],
                        'amount' => $succ['amount'],
                        'QrCode' => $succ['QRCode']
                    ];

                    array_push($ticketsData, $ticketsIn);
                }
            }

            if ($profileAttribute['email'] != null) {
                $paramsEmail = [
                    "eventID" => $success[0]['eventId'],
                    "orderNumber" => $dpo_trxnId,
                    "paymentMode" => "DPO-PAYMENT",
                    "name" => $profileAttribute['first_name'] . " "
                    . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
                    "eventDate" => $success[0]['start_date'],
                    "eventName" => $success[0]['eventName'],
                    "amountPaid" => $success[0]['amount'],
                    'msisdn' => $check_trxn[0]['msisdn'],
                    'ticketsArray' => $ticketsData,
                    'posterURL' => $success[0]['posterURL'],
                    'venue' => $success[0]['venue'],
                    'eventTicketInfo' => $success[0]['event_ticket_info'],
                ];
                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $profileAttribute['email'],
                    "from" => "noreply@madfun.com",
                    "cc" => "",
                    "subject" => "Ticket for Event: " . $success[0]['eventName'],
                    "content" => "Ticket information",
                    "extrac" => $paramsEmail
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                        $postData, $this->settings['ServiceApiKey'], 3);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailWithoutAttachments Response::" .
                        " | UniqueId:" . $profileAttribute['msisdn'] . " profileID::" . $check_trxn[0]['profile_id'] . " " .
                        json_encode($mailResponse) . " Payload::" .
                        json_encode($postData));
            }

            $params = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                "msisdn" => $profileAttribute['msisdn'],
                "message" => $sms . ". Madfun! For Queries call "
                . "" . $this->settings['Helpline'],
                "profile_id" => $check_trxn[0]['profile_id'],
                "created_by" => 'DPO_PAYMENT',
                "is_bulk" => false,
                "link_id" => ""];

            $message = new Messaging();
            $message->LogOutbox($params);
            return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:" . $ex->getCode()
                    . " | Exception Code:" . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->dpoXMLResponse($CompanyRef, $CompanyRef);
        }
    }

    /**
     * dpoPaymentsAction
     * @return type
     */
    public function dpoPaymentsAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | dpo Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $TransID = isset($data->TransID) ? $data->TransID : null;
        $CCDapproval = isset($data->CCDapproval) ? $data->CCDapproval : null;
        $TransactionToken = isset($data->TransactionToken) ? $data->TransactionToken : null; //Token generated
        $CompanyRef = isset($data->CompanyRef) ? $data->CompanyRef : null; //Account

        if (!$token || !$TransID || !$CCDapproval || !$TransactionToken || !$CompanyRef) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        $duplicate = "SELECT id FROM dpo_transaction WHERE TransID=:TransID";

        $check_duplicate = $this->rawSelect($duplicate, [':TransID' => $TransID]);
        if ($check_duplicate) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Duplicate', ['code' => 202, 'message' => 'The Transaction is a duplicate']);
        }

        try {
            if ($this->settings['ticketSystemAPI'] != $token) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($this->getClientIPAddress(), ['35.187.93.149', '197.248.63.121', '54.86.50.139'])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__ . ":" . json_encode($request->getJsonRawBody())
                                , 'Un-authorised source!' . $this->getClientIPAddress() . '. UA:' . $request->getUserAgent());
            }
            $dpoTransactionQuery = "INSERT INTO dpo_transaction (TransID,CCDapproval,"
                    . "account,TransactionToken,created) VALUES (:TransID,:CCDapproval,"
                    . ":account,:TransactionToken,NOW())";

            $paramsDPOtrans = [
                ':TransID' => $TransID,
                ':CCDapproval' => $CCDapproval,
                ':account' => $CompanyRef,
                ':TransactionToken' => $TransactionToken
            ];
            $dpo_trxnId = $this->rawInsert($dpoTransactionQuery, $paramsDPOtrans);

            $ccountType = substr($CompanyRef, 0, 3);
            $hasEventShows = 0;
            if (strtoupper($ccountType) == 'MOD') {
                $hasEventShows = 1;
            }

            $accountNumber = substr($CompanyRef, 3);

            $select_trxn_initiated = "SELECT transaction_initiated.extra_data->>'$.amount' as amount, "
                    . "transaction_id,profile_id,service_id,reference_id,"
                    . "source,description,created FROM `transaction_initiated` WHERE "
                    . "`transaction_id`=:transaction_id LIMIT 1";
            $check_trxn = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $accountNumber]);

            if (!$check_trxn) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $TransID
                        . " | DPO Transaction Id:" . $dpo_trxnId
                        . " | DIRECT_DEPOSIT Transactions Empty Account "
                );
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Transaction Not Found', ['code' => 404
                            , 'message' => 'The Transaction not found']);
            }

            $extra1 = [
                'paid_msisdn' => Profiling::QueryMobile($check_trxn[0]['profile_id']),
                'account_number' => $accountNumber,];

            $trx_params = [
                'amount' => $check_trxn[0]['amount'],
                'service_id' => $check_trxn[0]['service_id'],
                'profile_id' => $check_trxn[0]['profile_id'],
                'reference_id' => $dpo_trxnId,
                'source' => $check_trxn[0]['source'],
                'description' => $check_trxn[0]['description'],
                'extra_data' => json_encode($extra1),];

            Transactions::CreateTransaction($trx_params);

            $referenceID = $check_trxn[0]['reference_id'];

            if (strtoupper($ccountType) == 'STR') {

                $select_stream_profile = "select stream_profile_request.id, "
                        . "stream_profile_request.profile_id,stream_profile_request.order_key,"
                        . "stream_profile_request.currency,stream_profile_request.reference_id,"
                        . "stream_profile_request.returnURL,stream_profile_request.cancelURL,"
                        . "stream_profile_request.status,stream_profile_request.created, "
                        . "profile_attribute.first_name, profile_attribute.last_name, "
                        . "user.email from stream_profile_request join profile_attribute "
                        . "on stream_profile_request.profile_id  =profile_attribute.profile_id "
                        . "join user on stream_profile_request.profile_id  =  user.profile_id WHERE"
                        . " stream_profile_request.reference_id=:reference_id";

                $check_trxn_profile = $this->rawSelect($select_stream_profile,
                        [':reference_id' => $referenceID]);

                if (!$check_trxn_profile) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Streaming Profile Request not Found', ['code' => 404
                                , 'message' => 'Streaming Profile Request not Found']);
                }

                $update_stream_profile = "update stream_profile_request set status = 1  WHERE"
                        . " id=:id";

                $this->rawUpdateWithParams($update_stream_profile,
                        [':id' => $check_trxn_profile[0]['id']]);
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Payment for Video Successful', ['code' => 200
                            , 'message' => 'Payment for Video Successful',
                            'data' => $check_trxn_profile[0]]);
            }



            $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                    . " reference_id=:reference_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':reference_id' => $referenceID]);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | dpo Payment Action Tickets Request:" . json_encode($check_trxn_profile));

            if (!$check_trxn_profile) {

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $TransID
                        . " | DPO Transaction Id:" . $dpo_trxnId
                        . " | Event Ticket Profile Empty Account "
                );
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Event Ticket Profile Not Found', ['code' => 404
                            , 'message' => 'The Event Ticket Profile not found']);
            }
            $amountPaid = $check_trxn[0]['amount'];
            $error = [];
            $success = [];
            foreach ($check_trxn_profile as $profileTrans) {
                if ($hasEventShows == 1) {
                    $check_evnt_type = $this->rawSelect("SELECT event_show_tickets_type.amount,"
                            . "event_show_tickets_type.discount,events.posterURL,"
                            . "event_show_tickets_type.group_ticket_quantity, "
                            . "event_show_tickets_type.status,ticket_types.ticket_type,"
                            . "event_show_tickets_type.event_show_venue_id,events.eventID AS eventId FROM "
                            . "event_show_tickets_type join event_show_venue on"
                            . " event_show_tickets_type.event_show_venue_id =  "
                            . "event_show_venue.event_show_venue_id join event_shows"
                            . " on event_show_venue.event_show_id  = "
                            . "event_shows.event_show_id join events on "
                            . "event_shows.eventID = events.eventID JOIN "
                            . "ticket_types ON ticket_types.typeId = event_show_tickets_type.typeId"
                            . " WHERE "
                            . "event_show_tickets_type.event_ticket_show_id"
                            . " = :event_ticket_show_id", [":event_ticket_show_id"
                        => $profileTrans['event_ticket_id']]);
                } else {
                    $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,event_tickets_type.discount,events.posterURL,event_tickets_type.group_ticket_quantity, "
                            . "event_tickets_type.status,ticket_types.ticket_type,event_tickets_type.eventId FROM"
                            . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                            . " = event_tickets_type.typeId JOIN events ON "
                            . "event_tickets_type.eventId = events.eventID WHERE event_tickets_type.event_ticket_id"
                            . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                }

                if (!$check_evnt_type) {
                    array_push($error, ['message' => 'There is no such Event '
                        . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if (($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount'])) > $amountPaid) {
                    array_push($error, ['message' => 'Failed to activate ticket, '
                        . 'Reason: Insufficient Fund', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if ($check_evnt_type[0]['group_ticket_quantity'] == 1) {
                    $amountPaid = $amountPaid - ($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount']));
                }
                $check_trn_profile_state = $this->rawSelect("select * from "
                        . "event_profile_tickets_state where "
                        . "event_profile_ticket_id =:event_profile_ticket_id",
                        [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                if (!$check_trn_profile_state) {
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | UniqueId:" . $TransID
                            . " | DPO Transaction Id:" . $dpo_trxnId
                            . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                            . " | Record Not Found, Creating new record "
                            . "for Event Profile Ticket State "
                    );
                }
                $tickets = new Tickets();
                $paramsState = [
                    'status' => 1,
                    'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                ];

                $eventState = $tickets->ProfileTicketState($paramsState);
                if (!$eventState) {
                    array_push($error, ['message' => 'Failed. The ticket '
                        . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                $paramsUpdate = [
                    'event_ticket_id' => $profileTrans['event_ticket_id'],
                    'ticket_purchased' => 1,
                    'ticket_redeemed' => 0
                ];
                $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $profileTrans['event_tickets_option_id'], $hasEventShows);
                $paramEvent = [
                    'eventID' => $check_evnt_type[0]['eventId']
                ];

                $eventData = $tickets->queryEvent($paramEvent);

                array_push($success, [
                    'eventId' => $check_evnt_type[0]['eventId'],
                    'message' => 'Ticket Activated Successsful',
                    'QRCode' => $profileTrans['barcode'],
                    'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                    'eventName' => $eventData['eventName'],
                    'venue' => $eventData['venue'],
                    'start_date' => $eventData['dateStart'],
                    'QRCodeURL' => $profileTrans['barcodeURL'],
                    'posterURL' => $check_evnt_type[0]['posterURL'],
                    'ticketType' => $check_evnt_type[0]['ticket_type'],
                    'amount' => ($check_evnt_type[0]['amount'] - $check_evnt_type[0]['discount'])]);
            }

            $purchase_type = 'Beneficiary';
            if ($check_trxn_profile[0]['profile_id'] != $check_trxn[0]['profile_id']) {
                $purchase_type = 'Sponsor';
            }
            if (!$success) {

                $callback_data = [
                    'purchase_type' => $purchase_type,
                    'transaction_id' => $dpo_trxnId,
                    'response_code' => 402,
                    'response_description' => 'Processed Failed',
                    'extra_data' => json_encode($error),
                    'narration' => 'Failed to update event profile ticket state',
                    'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                $callback_id = Transactions::CreateTransactionCallback($callback_data);

                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Failed to update event profile ticket state', ['code' => 404
                            , 'message' => $error]);
            }

            $callback_data = [
                'purchase_type' => $purchase_type,
                'transaction_id' => $dpo_trxnId,
                'response_code' => 200,
                'response_description' => 'Processed Successfully',
                'extra_data' => json_encode($success),
                'narration' => 'Processed Successfully',
                'receipt_number' => "A" . $accountNumber . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
            $callback_id = Transactions::CreateTransactionCallback($callback_data);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | UniqueId:" . $TransID
                    . " | DPO Transaction Id:" . $dpo_trxnId
                    . " | Account:" . $accountNumber
                    . " | CreateTransactionCallback:$callback_id");

            $profileAttribute = Profiling::QueryProfileProfileId($check_trxn[0]['profile_id']);

            $sms = "";
            $ticketsData = [];
            foreach ($success as $succ) {

                $sms .= "Dear " . $profileAttribute['first_name'] . " " . $profileAttribute['last_name'] . ", Your " . $succ['eventName'] . " ticket "
                        . "is " . $succ['QRCode'] . ". View your ticket from "
                        . $succ['ticketURL'] . " \n";

                if ($profileAttribute['email'] != null) {
                    // sent email to clients
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | Profile Attribute::" . json_encode($profileAttribute));

                    $ticketsIn = [
                        'ticketName' => $succ['ticketType'],
                        'currency' => $succ['currency'],
                        'amount' => $succ['amount'],
                        'QrCode' => $succ['QRCode']
                    ];

                    array_push($ticketsData, $ticketsIn);
                }
            }

            if ($profileAttribute['email'] != null) {
                $paramsEmail = [
                    "eventID" => $success[0]['eventId'],
                    "orderNumber" => $dpo_trxnId,
                    "paymentMode" => "DPO-PAYMENT",
                    "name" => $profileAttribute['first_name'] . " "
                    . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
                    "eventDate" => $success[0]['start_date'],
                    "eventName" => $success[0]['eventName'],
                    "amountPaid" => $success[0]['amount'],
                    'msisdn' => $check_trxn[0]['msisdn'],
                    'ticketsArray' => $ticketsData,
                    'posterURL' => $success[0]['posterURL'],
                    'venue' => $success[0]['venue'],
                    'eventTicketInfo' => $success[0]['event_ticket_info'],
                ];
                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $profileAttribute['email'],
                    "from" => "noreply@madfun.com",
                    "cc" => "",
                    "subject" => "Ticket for Event: " . $success[0]['eventName'],
                    "content" => "Ticket information",
                    "extrac" => $paramsEmail
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                        $postData, $this->settings['ServiceApiKey'], 3);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailWithoutAttachments Response::" .
                        " | UniqueId:" . $profileAttribute['msisdn'] . " profileID::" . $check_trxn[0]['profile_id'] . " " .
                        json_encode($mailResponse) . " Payload::" .
                        json_encode($postData));
            }

            $params = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                "msisdn" => $profileAttribute['msisdn'],
                "message" => $sms . ". Madfun! For Queries call "
                . "" . $this->settings['Helpline'],
                "profile_id" => $check_trxn[0]['profile_id'],
                "created_by" => 'DPO_PAYMENT',
                "is_bulk" => false,
                "link_id" => ""];

            $message = new Messaging();
            $message->LogOutbox($params);
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Ticket Sent Successful', ['code' => 200
                        , 'success' => $success, 'error' => $error]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:" . $ex->getCode()
                    . " | Exception Code:" . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Internal Server Error.', ['code' => 500
                        , 'message' => $ex->getMessage()], true);
        }
    }

    /**
     * mpesaPaymentRetries
     * @return type
     */
    public function mpesaPaymentRetries() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Mpesa payment retries:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $mpesaReference = isset($data->mpesaReference) ? $data->mpesaReference : null;
        $source = isset($data->source) ? $data->source : null;
        if (!$token || !$source || !$mpesaReference) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }

            if (!in_array($auth_response['userRole'], [1, 2])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }

            $trans = "SELECT * FROM mpesa_transaction WHERE mpesa_code=:mpesa_code";

            $check_transactions = $this->rawSelect($trans, [':mpesa_code' => $mpesaReference]);
            if (!$check_transactions) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Mpesa Transaction Code']);
            }

            $accountNumber = substr($check_transactions[0]['mpesa_account'], 4);

            $select_trxn_initiated = "SELECT * FROM `transaction_initiated` WHERE "
                    . "`transaction_id`=:transaction_id LIMIT 1";
            $check_trxn = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $accountNumber]);

            if (!$check_trxn) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Mpesa Account Number']);
            }

            $referenceID = $check_trxn[0]['reference_id'];

            $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                    . " reference_id=:reference_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':reference_id' => $referenceID]);

            if (!$check_trxn_profile) {

                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Event Ticket Profile Not Found']);
            }
            $attributes['profile_id'] = Profiling::Profile($check_transactions[0]['mpesa_msisdn']);
            $amountPaid = $check_transactions[0]['mpesa_amount'];
            $error = [];
            $success = [];
            foreach ($check_trxn_profile as $profileTrans) {
                $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,"
                        . "event_tickets_type.status,ticket_types.ticket_type,ticket_types.caption, event_tickets_type.eventId FROM"
                        . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                        . " = event_tickets_type.typeId WHERE event_tickets_type.event_ticket_id"
                        . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                if (!$check_evnt_type) {
                    array_push($error, ['message' => 'There is no such Event '
                        . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                if ($check_evnt_type[0]['amount'] > $amountPaid) {
                    array_push($error, ['message' => 'Failed to activate ticket, '
                        . 'Reason: Insufficient Fund', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                $amountPaid = $amountPaid - $check_evnt_type[0]['amount'];

                $check_trn_profile_state = $this->rawSelect("select * from "
                        . "event_profile_tickets_state where "
                        . "event_profile_ticket_id =:event_profile_ticket_id",
                        [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                if (!$check_trn_profile_state) {
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                            . " | Record Not Found, Creating new record "
                            . "for Event Profile Ticket State "
                    );
                }
                $tickets = new Tickets();
                $paramsState = [
                    'status' => 1,
                    'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                ];

                $eventState = $tickets->ProfileTicketState($paramsState);
                if (!$eventState) {
                    array_push($error, ['message' => 'Failed. The ticket '
                        . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                }
                $paramsUpdate = [
                    'event_ticket_id' => $profileTrans['event_ticket_id'],
                    'ticket_purchased' => 1,
                    'ticket_redeemed' => 0
                ];
                $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $check_trn_profile_state['event_tickets_option_id']);
                $paramEvent = [
                    'eventID' => $check_evnt_type[0]['eventId']
                ];

                $eventData = $tickets->queryEvent($paramEvent);

                array_push($success, ['message' => 'Ticket Activated Successsful',
                    'QRCode' => $profileTrans['barcode'],
                    'eventName' => $eventData['eventName'],
                    'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                    'venue' => $eventData['venue'], 'start_date' => $eventData['dateInfo'], 'QRCodeURL' =>
                    $profileTrans['barcodeURL'],
                    'ticketType' => $check_evnt_type[0]['ticket_type'],
                    'amount' => $check_evnt_type[0]['amount']]);
            }
            $purchase_type = 'Beneficiary';
            if ($check_trxn_profile[0]['profile_id'] != $attributes['profile_id']) {
                $purchase_type = 'Sponsor';
            }
            if (!$success) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Failed to update event profile ticket state', ['code' => 404
                            , 'message' => $error]);
            }
            $profile = new Profiling();
            $profileAttribute = $profile->QueryProfileAttribution($attributes['profile_id']);
            foreach ($success as $succ) {

                $sms = "Dear " . $profileAttribute['first_name'] . " " . $profileAttribute['last_name'] . ", Your " . $succ['eventName'] . " ticket "
                        . "is " . $succ['QRCode'] . ". View your ticket from "
                        . $succ['ticketURL'] . " Madfun! For Queries call "
                        . "" . $this->settings['Helpline'];

                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $check_transactions[0]['mpesa_msisdn'],
                    "message" => $sms,
                    "profile_id" => $attributes['profile_id'],
                    "created_by" => 'TICKET_RESEND_' . $source,
                    "is_bulk" => false,
                    "link_id" => ""];

                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($params);
            }
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Ticket Sent Successful', ['code' => 200
                        , 'success' => $success, 'error' => $error]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * redeemedTicket
     * @return type
     */
    public function redeemedTicket() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Redeemed Ticket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $user_api_key = isset($data->user_api_key) ? $data->user_api_key : null;
        $qrCode = isset($data->QrCode) ? $data->QrCode : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : 1;

        if (!$token || !$qrCode || !$user_api_key || !$source || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'MOBILE_APP'])) {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication System Failure.');
                }
            } else {

                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication System Failure.');
                }
                $auth_response = $auth->QuickTokenAuthenticate($user_api_key);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication User Failure.');
                }
            }
//            if (!in_array($auth_response['userRole'], [1,2,3,4,5, 6, 7, 8])) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'User doesn\'t have permissions to perform this action.');
//            }
            if ($eventID != null) {
                $checkEvents = Events::findFirst([
                            "eventID =:eventID: ",
                            "bind" => [
                                "eventID" => $eventID],]);
                if (!$checkEvents) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event ID Not Found"
                                    , ['code' => 404, 'Message' => 'Failed to validate'
                                . ' QRcode. Event ID Not Found'], true);
                }
                if ($checkEvents->hasLinkingTag == 1) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event has linked tag enabled. Kindly contact system admin"
                                    , ['code' => 404, 'Message' => 'Event has linked tag enabled.'
                                . ' Kindly contact system admin'], true);
                }
                if ($checkEvents->hasMultipleShow == 1) {
                    $checkQRCode = $this->selectQuery("select event_profile_tickets.barcode,event_profile_tickets.profile_id,ticket_types.ticket_type,"
                            . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,event_show_tickets_type.event_ticket_show_id, "
                            . "event_profile_tickets_state.status,event_profile_tickets_state.created,"
                            . "event_profile_tickets.event_tickets_option_id,event_show_tickets_type.amount,"
                            . "event_show_tickets_type.event_show_venue_id "
                            . " from event_profile_tickets join event_profile_tickets_state"
                            . " on  event_profile_tickets.event_profile_ticket_id = "
                            . "event_profile_tickets_state.event_profile_ticket_id join "
                            . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = "
                            . "event_profile_tickets.event_ticket_id join ticket_types on event_show_tickets_type.typeId = ticket_types.typeId WHERE "
                            . "event_profile_tickets.barcode =:barcode AND event_profile_tickets.isShowTicket = :isShowTicket"
                            , [':barcode' => $qrCode, ':isShowTicket' => 1]);
                } else {
                    $checkQRCode = $this->selectQuery("select event_profile_tickets.barcode,event_profile_tickets.profile_id,ticket_types.ticket_type,"
                            . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,event_tickets_type.event_ticket_id, "
                            . "event_profile_tickets_state.status,event_profile_tickets_state.created,event_profile_tickets.event_tickets_option_id,event_tickets_type.amount,event_tickets_type.eventId "
                            . " from event_profile_tickets join event_profile_tickets_state"
                            . " on  event_profile_tickets.event_profile_ticket_id = "
                            . "event_profile_tickets_state.event_profile_ticket_id join "
                            . "event_tickets_type on event_tickets_type.event_ticket_id = "
                            . "event_profile_tickets.event_ticket_id join ticket_types on event_tickets_type.typeId = ticket_types.typeId WHERE "
                            . "event_profile_tickets.barcode =:barcode AND event_tickets_type.eventId = :eventId"
                            , [':barcode' => $qrCode, ':eventId' => $eventID]);
                }
            } else {

                $checkQRCode = $this->selectQuery("select event_profile_tickets.barcode,event_profile_tickets.profile_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,event_tickets_type.event_ticket_id, "
                        . "event_profile_tickets_state.status,event_profile_tickets_state.created,event_profile_tickets.event_tickets_option_id,event_tickets_type.amount,event_tickets_type.eventId "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_tickets_type on event_tickets_type.event_ticket_id = "
                        . "event_profile_tickets.event_ticket_id join ticket_types on event_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.barcode =:barcode"
                        , [':barcode' => $qrCode]);
            }



            if (!$checkQRCode) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Not Found"
                                , ['code' => 404, 'Message' => 'Failed to validate'
                            . ' QRcode. Not Found'], true);
            }
            $checkEventsDetails = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if ($checkQRCode[0]['status'] != 1) {

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Not Active for event: " . $checkEventsDetails->eventName
                                , ['code' => 402, 'Message' => 'QRCode is not active for event: ' . $checkEventsDetails->eventName . '. Kindly '
                            . ' make payment for the QRCode:' . $qrCode], true);
            }
            if ($checkQRCode[0]['isRemmend'] == 1) {


                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Used for event: " . $checkEventsDetails->eventName
                                , ['code' => 402, 'Message' => 'QRCode is used for event: ' . $checkEventsDetails->eventName . '. Kindly '
                            . ' send a different QRCode:' . $qrCode], true);
            }
            $updateQRcodeSQL = "UPDATE event_profile_tickets set isRemmend = 1, isRedemmedBy=:isRedemmedBy "
                    . " WHERE event_profile_ticket_id=:event_profile_ticket_id";
            $params = [":event_profile_ticket_id" => $checkQRCode[0]['event_profile_ticket_id'],
                ':isRedemmedBy' => $auth_response['user_id']];

            $resultQRcode = $this->rawUpdateWithParams($updateQRcodeSQL, $params);

            $profile_id = $checkQRCode[0]['profile_id'];

            $msisdn = Profiling::QueryMobile($profile_id);
            $profileAttributed = Profiling::QueryProfileAttribution($profile_id);

            if ($checkEvents->hasMultipleShow == 1) {
                $paramsUpdate = [
                    'event_ticket_id' => $checkQRCode[0]['event_ticket_show_id'],
                    'ticket_purchased' => 0,
                    'ticket_redeemed' => 1
                ];
            } else {
                $paramsUpdate = [
                    'event_ticket_id' => $checkQRCode[0]['event_ticket_id'],
                    'ticket_purchased' => 0,
                    'ticket_redeemed' => 1
                ];
            }

            $tickets = new Tickets();
            $eventUpdateTicket = $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $checkQRCode[0]['event_tickets_option_id'], $checkEvents->hasMultipleShow);

            if ($resultQRcode) {
                $sms = [
                    'created_by' => $source,
                    'profile_id' => $profile_id,
                    'msisdn' => $msisdn,
                    'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                    'message' => "Hello " . $profileAttributed['first_name'] . " "
                    . "" . $profileAttributed['last_name'] . ", your ticket for "
                    . "the Event: " . $checkEventsDetails->eventName . " has been validated successful.",
                    'is_bulk' => true,
                    'link_id' => '',];

                $sts = $this->getMicrotime();
                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($sms);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | Took $stopped Seconds"
                        . " | ProfleID:" . $profile_id
                        . " | Mobile:$msisdn"
                        . " | messaging::LogOutbox Reponse:" . json_encode($queueMessageResponse));

                $data = [
                    'name' => $profileAttributed['first_name'] . " " . $profileAttributed['last_name'],
                    'TicketType' => $checkQRCode[0]['ticket_type'],
                    'phone' => $msisdn,
                    'ticketNo' => 1,
                    'amount' => $checkQRCode[0]['amount'],
                    'purchaseDate' => $checkQRCode[0]['created'],
                    'event' => $checkEventsDetails->eventName
                ];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode validated successful"
                                , ['code' => 200, 'Message' => 'QRCode has '
                            . 'been validated successful for event. ' . $checkEventsDetails->eventName, 'data' => $data]);
            }
            return $this->success(__LINE__ . ":" . __CLASS__
                            , "QRCode validated failed"
                            , ['code' => 202, 'Message' => 'QRCode has '
                        . 'failed to validated. Kindly contact '
                        . 'system admin!'], true);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function linkredeemedTicket() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Redeemed Ticket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $user_api_key = isset($data->user_api_key) ? $data->user_api_key : null;
        $qrCode = isset($data->QrCode) ? $data->QrCode : null;
        $tagCode = isset($data->tagCode) ? $data->tagCode : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : 1;

        if (!$token || !$qrCode || !$user_api_key || !$source || !$eventID || !$tagCode) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if ($this->settings['ticketSystemAPI'] != $token) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication System Failure.');
            }
            $auth_response = $auth->QuickTokenAuthenticate($user_api_key);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication User Failure.');
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);
            if ($checkEvents->hasLinkingTag != 1) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event linked tag not enabled"
                                , ['code' => 404, 'Message' => 'Event linked tag not enabled'], true);
            }
            if (!$checkEvents) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event ID Not Found"
                                , ['code' => 404, 'Message' => 'Failed to validate'
                            . ' QRcode. Event ID Not Found'], true);
            }
            if ($checkEvents->hasMultipleShow == 1) {
                $checkQRCode = $this->selectQuery("select event_profile_tickets.event_ticket_id,"
                        . "event_profile_tickets.barcode,event_profile_tickets.profile_id,"
                        . "ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,"
                        . "event_show_tickets_type.event_ticket_show_id, "
                        . "event_profile_tickets_state.status,"
                        . "event_profile_tickets_state.created,"
                        . "event_profile_tickets.event_tickets_option_id,"
                        . "event_show_tickets_type.amount,"
                        . "event_show_tickets_type.event_show_venue_id "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = "
                        . "event_profile_tickets.event_ticket_id join "
                        . "ticket_types on event_show_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.barcode =:barcode AND "
                        . "event_profile_tickets.isShowTicket = :isShowTicket"
                        , [':barcode' => $qrCode, ':isShowTicket' => 1]);
            } else {
                $checkQRCode = $this->selectQuery("select event_profile_tickets.event_ticket_id,"
                        . "event_profile_tickets.barcode,"
                        . "event_profile_tickets.profile_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,"
                        . "event_profile_tickets.event_profile_ticket_id,"
                        . "event_tickets_type.event_ticket_id, "
                        . "event_profile_tickets_state.status,"
                        . "event_profile_tickets_state.created,"
                        . "event_profile_tickets.event_tickets_option_id,"
                        . "event_tickets_type.amount,event_tickets_type.eventId "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_tickets_type on event_tickets_type.event_ticket_id = "
                        . "event_profile_tickets.event_ticket_id join ticket_types"
                        . " on event_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.barcode =:barcode AND "
                        . "event_tickets_type.eventId = :eventId"
                        , [':barcode' => $qrCode, ':eventId' => $eventID]);
            }

            if (!$checkQRCode) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Not Found"
                                , ['code' => 404, 'Message' => 'Failed to validate'
                            . ' QRcode. Not Found'], true);
            }

            if ($checkQRCode[0]['status'] != 1) {

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Not Active for event: " . $checkEvents->eventName
                                , ['code' => 402, 'Message' => 'QRCode is not active '
                            . 'for event: ' . $checkEvents->eventName . '. Kindly '
                            . ' make payment for the QRCode:' . $qrCode], true);
            }
            if ($checkQRCode[0]['isRemmend'] == 1) {


                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Used for event: " . $checkEvents->eventName
                                , ['code' => 402, 'Message' => 'QRCode is used '
                            . 'for event: ' . $checkEvents->eventName . '. Kindly '
                            . ' send a different QRCode:' . $qrCode], true);
            }

            $resultEventTag = $this->rawSelectOneRecord("SELECT event_tag_code.code,"
                    . " event_tag_code.event_ticket_id, event_tag_code.event_tag_id,"
                    . " event_tag_code.event_ticket_id_extrac FROM event_tag_code WHERE"
                    . " event_tag_code.code_hash =:code AND status=:status",
                    [':code' => $tagCode, ':status' => 1]);

            if (!$resultEventTag) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Tag not configured. Try another Tag "
                                , ['code' => 402, 'Message' => 'Event Tag not '
                            . 'configured. Try another Tag '], true);
            }
            $IDExtracarray = [];
            if ($resultEventTag['event_ticket_id_extrac']) {
                $IDExtracarray = explode(",", $resultEventTag['event_ticket_id_extrac']);
            }

            if ($resultEventTag['event_ticket_id'] != $checkQRCode[0]['event_ticket_id'] && !in_array($checkQRCode[0]['event_ticket_id'], $IDExtracarray)) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket can't be mapped to the tag. Try another Tag "
                                , ['code' => 402, 'Message' => "Ticket can't be"
                            . " mapped to the tag. Try another Tag " . $resultEventTag['event_ticket_id'] . " " . $checkQRCode[0]['event_ticket_id']], true);
            }

            $resultCheckCode = $this->rawSelectOneRecord("SELECT event_redeemed_tags.id"
                    . " FROM event_redeemed_tags WHERE"
                    . " event_redeemed_tags.event_tag_id =:event_tag_id",
                    [':event_tag_id' => $resultEventTag['event_tag_id']]);

            if ($resultCheckCode) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Failed. Tag has been mapped. Try another Tag "
                                , ['code' => 402, 'Message' => "Failed. Tag has"
                            . " been mapped. Try another Tag "], true);
            }

            $updateQRcodeSQL = "UPDATE event_profile_tickets set isRemmend = 1, isRedemmedBy=:isRedemmedBy "
                    . " WHERE event_profile_ticket_id=:event_profile_ticket_id";
            $params = [":event_profile_ticket_id" => $checkQRCode[0]['event_profile_ticket_id'],
                ':isRedemmedBy' => $auth_response['user_id']];

            $resultQRcode = $this->rawUpdateWithParams($updateQRcodeSQL, $params);

            $insertRedemeedCode = "INSERT INTO event_redeemed_tags (event_tag_id,"
                    . "event_profile_ticket_id,redeemed_on,created) VALUES "
                    . "(:event_tag_id,:event_profile_ticket_id,NOW(), NOW())";

            $this->rawInsert($insertRedemeedCode, [
                ':event_tag_id' => $resultEventTag['event_tag_id'],
                ':event_profile_ticket_id' => $checkQRCode[0]['event_profile_ticket_id']]);

            $profile_id = $checkQRCode[0]['profile_id'];

            $msisdn = Profiling::QueryMobile($profile_id);
            $profileAttributed = Profiling::QueryProfileAttribution($profile_id);

            if ($checkEvents->hasMultipleShow == 1) {
                $paramsUpdate = [
                    'event_ticket_id' => $checkQRCode[0]['event_ticket_show_id'],
                    'ticket_purchased' => 0,
                    'ticket_redeemed' => 1
                ];
            } else {
                $paramsUpdate = [
                    'event_ticket_id' => $checkQRCode[0]['event_ticket_id'],
                    'ticket_purchased' => 0,
                    'ticket_redeemed' => 1
                ];
            }

            if ($resultQRcode) {
                $sms = [
                    'created_by' => $source,
                    'profile_id' => $profile_id,
                    'msisdn' => $msisdn,
                    'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
                    'message' => "Hello " . $profileAttributed['first_name'] . " "
                    . "" . $profileAttributed['last_name'] . ", your ticket for "
                    . "the Event: " . $checkEvents->eventName . " has been validated successful.",
                    'is_bulk' => true,
                    'link_id' => '',];

                $sts = $this->getMicrotime();
                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($sms);
                $stopped = $this->getMicrotime() - $sts;
                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | Took $stopped Seconds"
                        . " | ProfleID:" . $profile_id
                        . " | Mobile:$msisdn"
                        . " | messaging::LogOutbox Reponse:" .
                        json_encode($queueMessageResponse));

                $data = [
                    'name' => $profileAttributed['first_name'] . " " .
                    $profileAttributed['last_name'],
                    'TicketType' => $checkQRCode[0]['ticket_type'],
                    'phone' => $msisdn,
                    'ticketNo' => 1,
                    'amount' => $checkQRCode[0]['amount'],
                    'purchaseDate' => $checkQRCode[0]['created'],
                    'event' => $checkEvents->eventName
                ];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode validated successful"
                                , ['code' => 200, 'Message' => 'QRCode has '
                            . 'been validated successful for event. ' .
                            $checkEvents->eventName, 'data' => $data]);
            }
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function queryLinkedTicket() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Redeemed Ticket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $user_api_key = isset($data->user_api_key) ? $data->user_api_key : null;
        $tagCode = isset($data->tagCode) ? $data->tagCode : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : 1;

        if (!$token || !$user_api_key || !$source || !$eventID || !$tagCode) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if ($this->settings['ticketSystemAPI'] != $token) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication System Failure.');
            }
            $auth_response = $auth->QuickTokenAuthenticate($user_api_key);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication User Failure.');
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);
            if ($checkEvents->hasLinkingTag != 1) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event linked tag not enabled"
                                , ['code' => 404, 'Message' => 'Event linked tag not enabled'], true);
            }

            $resultEventTag = $this->rawSelectOneRecord("SELECT event_tag_code.code,"
                    . " event_tag_code.event_ticket_id, event_tag_code.event_tag_id FROM event_tag_code WHERE"
                    . " event_tag_code.code_hash =:code AND status=:status",
                    [':code' => $tagCode, ':status' => 1]);

            if (!$resultEventTag) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Tag not configured. Try another Tag "
                                , ['code' => 402, 'Message' => 'Event Tag not '
                            . 'configured. Try another Tag '], true);
            }

            $resultCheckCode = $this->rawSelectOneRecord("SELECT event_redeemed_tags.id, "
                    . "event_redeemed_tags.event_profile_ticket_id "
                    . " FROM event_redeemed_tags WHERE"
                    . " event_redeemed_tags.event_tag_id =:event_tag_id",
                    [':event_tag_id' => $resultEventTag['event_tag_id']]);

            if (!$resultCheckCode) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Failed. Tag Not Mapped "
                                , ['code' => 402, 'Message' => "Failed. Tag Not Mapped "], true);
            }

            if ($checkEvents->hasMultipleShow == 1) {
                $checkQRCode = $this->selectQuery("select event_profile_tickets.event_ticket_id,"
                        . "event_profile_tickets.barcode,event_profile_tickets.profile_id,"
                        . "ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,"
                        . "event_show_tickets_type.event_ticket_show_id, "
                        . "event_profile_tickets_state.status,"
                        . "event_profile_tickets_state.created,"
                        . "event_profile_tickets.event_tickets_option_id,"
                        . "event_show_tickets_type.amount,"
                        . "event_show_tickets_type.event_show_venue_id "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = "
                        . "event_profile_tickets.event_ticket_id join "
                        . "ticket_types on event_show_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.event_profile_ticket_id = :event_profile_ticket_id AND "
                        . "event_profile_tickets.isShowTicket = :isShowTicket"
                        , [':event_profile_ticket_id' => $resultCheckCode['event_profile_ticket_id'],
                    ':isShowTicket' => 1]);
            } else {
                $checkQRCode = $this->selectQuery("select event_profile_tickets.event_ticket_id,"
                        . "event_profile_tickets.barcode,"
                        . "event_profile_tickets.profile_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,"
                        . "event_profile_tickets.event_profile_ticket_id,"
                        . "event_tickets_type.event_ticket_id, "
                        . "event_profile_tickets_state.status,"
                        . "event_profile_tickets_state.created,"
                        . "event_profile_tickets.event_tickets_option_id,"
                        . "event_tickets_type.amount,event_tickets_type.eventId "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_tickets_type on event_tickets_type.event_ticket_id = "
                        . "event_profile_tickets.event_ticket_id join ticket_types"
                        . " on event_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.event_profile_ticket_id = :event_profile_ticket_id AND "
                        . "event_tickets_type.eventId = :eventId"
                        , [':event_profile_ticket_id' => $resultCheckCode['event_profile_ticket_id'],
                    ':eventId' => $eventID]);
            }

            if (!$checkQRCode) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Not Found"
                                , ['code' => 404, 'Message' => 'Ticket not found'], true);
            }

            $profile_id = $checkQRCode[0]['profile_id'];

            $msisdn = Profiling::QueryMobile($profile_id);
            $profileAttributed = Profiling::QueryProfileAttribution($profile_id);
            $dataRes = [
                'name' => $profileAttributed['first_name'] . " " .
                $profileAttributed['last_name'],
                'TicketType' => $checkQRCode[0]['ticket_type'],
                'phone' => $msisdn,
                'ticketNo' => 1,
                'amount' => $checkQRCode[0]['amount'],
                'purchaseDate' => $checkQRCode[0]['created'],
                'event' => $checkEvents->eventName
            ];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "successful"
                            , ['code' => 200, 'Message' => 'Queried successful for event. ' .
                        $checkEvents->eventName, 'data' => $dataRes]);
        } catch (Exception $ex) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Exception Error', ['code' => 500
                        , 'success' => "", 'error' => $ex->getMessage()], true);
        }
    }

    public function queryQrCodeTicket() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Redeemed Ticket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $user_api_key = isset($data->user_api_key) ? $data->user_api_key : null;
        $barcode = isset($data->barcode) ? $data->barcode : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : 1;

        if (!$token || !$user_api_key || !$source || !$eventID || !$barcode) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if ($this->settings['ticketSystemAPI'] != $token) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication System Failure.');
            }
            $auth_response = $auth->QuickTokenAuthenticate($user_api_key);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication User Failure.');
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);
            if ($checkEvents->hasLinkingTag != 1) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event linked tag not enabled"
                                , ['code' => 404, 'Message' => 'Event linked tag not enabled'], true);
            }

            if ($checkEvents->hasMultipleShow == 1) {
                $checkQRCode = $this->selectQuery("select event_profile_tickets.event_ticket_id,"
                        . "event_profile_tickets.barcode,event_profile_tickets.profile_id,"
                        . "ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,"
                        . "event_show_tickets_type.event_ticket_show_id, "
                        . "event_profile_tickets_state.status,"
                        . "event_profile_tickets_state.created,"
                        . "event_profile_tickets.event_tickets_option_id,"
                        . "event_show_tickets_type.amount,"
                        . "event_show_tickets_type.event_show_venue_id "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = "
                        . "event_profile_tickets.event_ticket_id join "
                        . "ticket_types on event_show_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.barcode =:barcode AND "
                        . "event_profile_tickets.isShowTicket = :isShowTicket"
                        , [':barcode' => $barcode, ':isShowTicket' => 1]);
            } else {
                $checkQRCode = $this->selectQuery("select event_profile_tickets.event_ticket_id,"
                        . "event_profile_tickets.barcode,"
                        . "event_profile_tickets.profile_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,"
                        . "event_profile_tickets.event_profile_ticket_id,"
                        . "event_tickets_type.event_ticket_id, "
                        . "event_profile_tickets_state.status,"
                        . "event_profile_tickets_state.created,"
                        . "event_profile_tickets.event_tickets_option_id,"
                        . "event_tickets_type.amount,event_tickets_type.eventId "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_tickets_type on event_tickets_type.event_ticket_id = "
                        . "event_profile_tickets.event_ticket_id join ticket_types"
                        . " on event_tickets_type.typeId = ticket_types.typeId WHERE "
                        . "event_profile_tickets.barcode =:barcode AND "
                        . "event_tickets_type.eventId = :eventId"
                        , [':barcode' => $barcode, ':eventId' => $eventID]);
            }

            if (!$checkQRCode) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "QRCode Not Found"
                                , ['code' => 404, 'Message' => 'Ticket not found'], true);
            }

            $profile_id = $checkQRCode[0]['profile_id'];

            $msisdn = Profiling::QueryMobile($profile_id);
            $profileAttributed = Profiling::QueryProfileAttribution($profile_id);
            $dataRes = [
                'name' => $profileAttributed['first_name'] . " " .
                $profileAttributed['last_name'],
                'TicketType' => $checkQRCode[0]['ticket_type'],
                'IsRedeemed' => $checkQRCode[0]['isRemmend'],
                'phone' => $msisdn,
                'ticketNo' => 1,
                'amount' => $checkQRCode[0]['amount'],
                'purchaseDate' => $checkQRCode[0]['created'],
                'event' => $checkEvents->eventName
            ];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "successful"
                            , ['code' => 200, 'Message' => 'Queried successful for event. ' .
                        $checkEvents->eventName, 'data' => $dataRes]);
        } catch (Exception $ex) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Exception Error', ['code' => 500
                        , 'success' => "", 'error' => $ex->getMessage()], true);
        }
    }

    /**
     * buyTicketLPO
     */
    public function buyTicketLPO() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | buyTicketLPO:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventData = isset($data->eventData) ? $data->eventData : null;
        $source = isset($data->source) ? $data->source : null;
        $company = isset($data->company) ? $data->company : null;
        $LPONumber = isset($data->LPONumber) ? $data->LPONumber : null;
        $reference = isset($data->reference) ? $data->reference : "MADFUN";
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $quantity = isset($data->quantity) ? $data->quantity : 1;
        $service_id = isset($data->service_id) ? $data->service_id : 1;
        $hasShowsEvent = isset($data->hasShowsEvent) ? $data->hasShowsEvent : 0;
        $affiliatorCode = isset($data->affiliatorCode) ? $data->affiliatorCode : null;

        if (!$token || !$source || !$eventData || !$unique_id || !$company || !$LPONumber || !$phone) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!is_numeric($quantity)) {

            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Quantity']);
        }
        $msisdn = $this->formatMobileNumber($phone, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
        if ($network == "UNKNOWN") {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }

            if ($hasShowsEvent == 1) {
                $checkEventTicketID = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                            "bind" => ["event_ticket_show_id" => $event_ticket_id],]);
            } else {
                $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                            "bind" => ["event_ticket_id" => $event_ticket_id],]);
            }

            if (!$checkEventTicketID) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
            }
            $discountAffiliator = 0;
            $affiliatorMapId = "";
            if ($affiliatorCode != null) {
                $checkAffiliator = AffiliatorEventMap::findFirst(['code=:code: AND eventId=:eventId:'
                            , 'bind' => ['code' => $affiliatorCode, 'eventId' => $checkEventTicketID->eventId]]);
                if (!$checkAffiliator) {
                    $discountAffiliator = 0;
                } else if ($checkAffiliator->status != 1) {
                    $discountAffiliator = 0;
                } else {
                    $discountAffiliator = $checkAffiliator->discount;
                    $affiliatorMapId = $checkAffiliator->id;
                }
            }
            if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Event Ticket Sold Out"
                                , ['code' => 202, 'message' => 'Sorry but you cannot '
                            . 'purchase ticket as the Event Ticket is sold out']
                                , true);
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
                                , ['code' => 400, 'message' => 'Request Failed. Duplicate UniqueId Found!!'], true);
            }
            $purchase_amount = $quantity * $checkEventTicketID->amount;
            for ($i = 1; $i <= $quantity; $i++) {
                $len = rand(100000000, 9999999999999999999999);
                $source = isset($data->source) ? $data->source : NULL;

                $paramsTickets = [
                    'profile_id' => Profiling::Profile($msisdn),
                    'event_ticket_id' => $event_ticket_id,
                    'reference_id' => $unique_id,
                    'reference' => $reference,
                    'barcode' => $len,
                    'discount' => $discountAffiliator,
                    'barcodeURL' => 'https://chart.googleapis.com/chart?chs=350x350&cht=qr&chl=' . $len . '&choe=UTF-8'
                ];

                $tickets = new Tickets();
                $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets, 0, $company);

                if (!$event_profile_ticket_id) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Failed to create ticket']);
                }
                if ($discountAffiliator > 0) {
                    $paramsAffiliator = [
                        'event_profile_ticket_id' => $event_profile_ticket_id,
                        'affiliator_event_map_id' => $affiliatorMapId
                    ];
                    $tickets->affiliatorSales($paramsAffiliator);
                }
                $paramsState = [
                    'status' => 1,
                    'event_profile_ticket_id' => $event_profile_ticket_id,
                ];

                $eventState = $tickets->ProfileTicketState($paramsState);
            }
            $ip_address = $this->getClientIPServer();

            $xt = ['amount' => $purchase_amount,
                'unique_id' => $unique_id,
                'ip' => $ip_address,];

            $params = [
                'service_id' => $service_id,
                'profile_id' => Profiling::Profile($msisdn),
                'reference_id' => $unique_id,
                'source' => $source,
                'description' => $services[0]['service_description'],
                'extra_data' => json_encode($xt),];

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

            $paramsLPO = [
                'transaction_id' => $transactionId,
                'lpo_number' => $LPONumber,
                'company' => $company,
                'amount' => $purchase_amount
            ];
            $lpoID = Transactions::addLPO($paramsLPO);
            if (!$lpoID) {
                return $this->success(__LINE__ . ":" . __CLASS__, "Duplicate Info"
                                , ['code' => 202, 'message' => 'LPO Number is a Duplicate'], true);
            }

            // Create transaction for LPO
            $extra1 = [
                'paid_msisdn' => $msisdn,
                'account_number' => $transactionId,];

            $trx_params = [
                'amount' => $purchase_amount,
                'service_id' => $service_id,
                'profile_id' => Profiling::Profile($msisdn),
                'reference_id' => $lpoID, //,
                'source' => $source,
                'description' => $services[0]['service_description'],
                'extra_data' => json_encode($extra1),];

            $pay_reference_id = Transactions::CreateTransaction($trx_params);

            $callback_data = [
                'purchase_type' => "Sponsor",
                'transaction_id' => $pay_reference_id,
                'response_code' => 200,
                'response_description' => 'Processed Successfully',
                'extra_data' => json_encode($extra1),
                'narration' => 'Processed Successfully',
                'receipt_number' => "A" . $transactionId . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];

            $callback_id = Transactions::CreateTransactionCallback($callback_data);

            $paramsUpdate = [
                'event_ticket_id' => $event_ticket_id,
                'ticket_purchased' => 1,
                'ticket_redeemed' => 0
            ];
            if ($hasShowsEvent == 1) {
                $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, null, 1);
            } else {
                $tickets->EventTicketTypeUpdate($paramsUpdate);
            }

            $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                    . " reference_id=:reference_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':reference_id' => $reference]);

            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Ticket Generated Successful', ['code' => 200
                        , 'callBackid' => $callback_id, 'data' => $check_trxn_profile]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * smsDLRAction
     * @return type
     */
    public function smsDLRAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | SMS DLR:" . json_encode($data));

        $correlator = isset($data->correlator) ? $data->correlator : null;

        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $deliveryStatus = isset($data->deliveryStatus) ? $data->deliveryStatus : null;
        $receivedOn = isset($data->requestTimeStamp) ? $data->requestTimeStamp : null;

        try {

            $dlr = "SELECT outbox_id FROM outbox WHERE outbox_id=:correlator";

            $check_dlr = $this->rawSelect($dlr, [':correlator' => $unique_id]);
            if (!$check_dlr) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'NO Message', ['code' => 404, 'message' => 'No  Message']);
            }

            $outboxDlr = $this->rawSelectOneRecord("SELECT * FROM outbox_dlr WHERE outbox_id=:outbox_id",
                    [':outbox_id' => $unique_id]);
            if ($outboxDlr) {
                $updatedlr = "UPDATE outbox_dlr set status= :status,description=:description,"
                        . "dlr_date=:dlr_date WHERE outbox_id=:correlator";
                $paramsDLR = [
                    ':status' => 400,
                    ':description' => $deliveryStatus,
                    ':dlr_date' => addslashes($receivedOn),
                    ':correlator' => $unique_id
                ];

                $this->rawUpdateWithParams($updatedlr, $paramsDLR);

                return $this->success(__LINE__ . ":" . __CLASS__,
                                'Request is not successful',
                                ['code' => 200, 'message' => 'Request saved successfuly']);
            }

            $this->rawInsertBulk("outbox_dlr",
                    ['correlator' => addslashes($correlator),
                        'description' => addslashes($deliveryStatus),
                        'status' => 400,
                        'dlr_date' => addslashes($receivedOn),
                        'created' => $this->now(),
                        'outbox_id' => addslashes($unique_id)]);

            return $this->success(__LINE__ . ":" . __CLASS__,
                            'Request is not successful',
                            ['code' => 200, 'message' => 'Request saved successfuly']);
        } catch (Exception $ex) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Exception Error', ['code' => 500
                        , 'success' => "", 'error' => $ex->getMessage()], true);
        }
    }

    /**
     * QueryDPOPaymentStatus
     * @return type
     */
    public function QueryDPOPaymentStatus() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " :" . __FUNCTION__
                . " | QueryDPOPaymentStatus:" . json_encode($request->getJsonRawBody()));

        $transaction_id = isset($data->transaction_id) ? $data->transaction_id : null;
        if ($this->checkForMySQLKeywords($transaction_id)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$transaction_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        try {

            $select_trxn_initiated = "SELECT dpo_transaction_initiated.TransactionToken,transaction_initiated.extra_data->'$.amount' AS amount,"
                    . " event_profile_tickets.isShowTicket, event_profile_tickets.reference_id, profile.msisdn,"
                    . "event_profile_tickets_state.`status`, event_profile_tickets.profile_id,transaction_initiated.transaction_id "
                    . " FROM dpo_transaction_initiated "
                    . "join transaction_initiated on dpo_transaction_initiated.transaction_id"
                    . " = transaction_initiated.transaction_id JOIN event_profile_tickets on "
                    . "transaction_initiated.profile_id =event_profile_tickets.profile_id"
                    . " JOIN event_profile_tickets_state on event_profile_tickets.event_profile_ticket_id"
                    . " = event_profile_tickets_state.event_profile_ticket_id  JOIN "
                    . "profile on event_profile_tickets.profile_id = profile.profile_id "
                    . "WHERE transaction_initiated.transaction_id = :transaction_id"
                    . " AND event_profile_tickets_state.`status` != 1 ";
            $check_trxns = $this->rawSelect($select_trxn_initiated,
                    [':transaction_id' => $transaction_id]);

            if (!$check_trxns) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'No Pending Payments', ['code' => 201
                            , 'message' => "No Pending Payments"], true);
            }
            $check_trxn = $check_trxns[0];
            $DPOPayments = new DPOCardProcessing();

            $DPOResult = $DPOPayments->verifyToken($check_trxn['TransactionToken']);

            $this->infologger->info(__LINE__ . ":" . __CLASS__ . " :" . __FUNCTION__
                    . " | DPOResult" . json_encode($DPOResult));

            if (!$DPOResult) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Failed to Query Payments', ['code' => 402
                            , 'message' => "Failed to Query Payments"], true);
            }

            $select_ticket_profile = "SELECT * FROM event_profile_tickets WHERE"
                    . " reference_id=:reference_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':reference_id' => $check_trxn['reference_id']]);

            if (!$check_trxn_profile) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Failed to Query Payments', ['code' => 402
                            , 'message' => "Failed to Query Payments"], true);
            }


            $DPOReponse = print_r($DPOResult, true);
            $DPOXMLData = new XMLToArrayUtils($DPOReponse, array(), array('story' => 'array'), true, false);

            $DPOArray = $DPOXMLData->getArray();

            $this->infologger->info(__LINE__ . ":" . __CLASS__ . " :" . __FUNCTION__
                    . " | Query DPO " . $transaction_id . ":" . json_encode($DPOArray));

            $ResultCode = $DPOArray['API3G']['Result'];
            $ResultExplanation = $DPOArray['API3G']['ResultExplanation'];
            $CustomerName = $DPOArray['API3G']['CustomerName'];
            $TransactionApproval = $DPOArray['API3G']['TransactionApproval'];
            $TransactionCurrency = $DPOArray['API3G']['TransactionCurrency'];
            $TransactionAmount = $DPOArray['API3G']['TransactionAmount'];
            $TransactionFinalAmount = $DPOArray['API3G']['TransactionFinalAmount'];
            $amountPaid = $check_trxn['amount'];
            $error = [];
            $success = [];
            $tickets = new Tickets();

            foreach ($check_trxn_profile as $profileTrans) {

                if ($ResultCode != "000") {
                    if ($ResultCode != "900") {
                        $paramsState = [
                            'status' => -3,
                            'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                        ];

                        $eventState = $tickets->ProfileTicketState($paramsState);
                    }

                    array_push($error, ['message' => $ResultExplanation, 'eventTicketID' => $profileTrans['event_ticket_id']]);
                    continue;
                } else {
                    if ($check_trxn['isShowTicket'] == 1) {

                        $check_evnt_type = $this->rawSelect("SELECT event_show_tickets_type.amount,"
                                . "event_show_tickets_type.discount,events.posterURL,"
                                . "event_show_tickets_type.group_ticket_quantity, event_show_tickets_type.currency,"
                                . "event_show_tickets_type.status,ticket_types.ticket_type,"
                                . "event_show_tickets_type.event_show_venue_id,events.eventID AS eventId FROM "
                                . "event_show_tickets_type join event_show_venue on"
                                . " event_show_tickets_type.event_show_venue_id =  "
                                . "event_show_venue.event_show_venue_id join event_shows"
                                . " on event_show_venue.event_show_id  = "
                                . "event_shows.event_show_id join events on "
                                . "event_shows.eventID = events.eventID JOIN "
                                . "ticket_types ON ticket_types.typeId = event_show_tickets_type.typeId"
                                . " WHERE "
                                . "event_show_tickets_type.event_ticket_show_id"
                                . " = :event_ticket_show_id", [":event_ticket_show_id"
                            => $profileTrans['event_ticket_id']]);
                    } else {
                        $check_evnt_type = $this->rawSelect("SELECT event_tickets_type.amount,event_tickets_type.discount,events.posterURL,event_tickets_type.group_ticket_quantity, "
                                . "event_tickets_type.status,ticket_types.ticket_type,event_tickets_type.eventId,event_tickets_type.currency FROM"
                                . " event_tickets_type JOIN ticket_types ON ticket_types.typeId"
                                . " = event_tickets_type.typeId JOIN events ON "
                                . "event_tickets_type.eventId = events.eventID WHERE event_tickets_type.event_ticket_id"
                                . " = :event_ticket_id", [":event_ticket_id" => $profileTrans['event_ticket_id']]);
                    }

                    if (!$check_evnt_type) {
                        array_push($error, ['message' => 'There is no such Event '
                            . 'Ticket Id', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }

                    if ($check_evnt_type[0]['group_ticket_quantity'] == 1) {
                        $amountPaid = $amountPaid - ($check_evnt_type[0]['amount'] - ($check_evnt_type[0]['discount'] + $profileTrans['discount']));
                    }
                    $check_trn_profile_state = $this->rawSelect("select * from "
                            . "event_profile_tickets_state where "
                            . "event_profile_ticket_id =:event_profile_ticket_id",
                            [":event_profile_ticket_id" => $profileTrans['event_profile_ticket_id']]);

                    if (!$check_trn_profile_state) {
                        $this->infologger->addInfo(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                . " | UniqueId:" . $transaction_id
                                . " | DPO Transaction Token:" . $check_trxn['TransactionToken']
                                . " | event_profile_ticket_id:" . $profileTrans['event_profile_ticket_id']
                                . " | Record Not Found, Creating new record "
                                . "for Event Profile Ticket State "
                        );
                    }



                    $paramsState = [
                        'status' => 1,
                        'event_profile_ticket_id' => $profileTrans['event_profile_ticket_id'],
                    ];

                    $eventState = $tickets->ProfileTicketState($paramsState);
                    if (!$eventState) {
                        array_push($error, ['message' => 'Failed. The ticket '
                            . 'has been paid.', 'eventTicketID' => $profileTrans['event_ticket_id']]);
                        continue;
                    }
                    $paramsUpdate = [
                        'event_ticket_id' => $profileTrans['event_ticket_id'],
                        'ticket_purchased' => 1,
                        'ticket_redeemed' => 0
                    ];
                    $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $profileTrans['event_tickets_option_id']);
                    $paramEvent = [
                        'eventID' => $check_evnt_type[0]['eventId']
                    ];

                    $eventData = $tickets->queryEvent($paramEvent);

                    array_push($success, [
                        'eventId' => $check_evnt_type[0]['eventId'],
                        'currency' => $check_evnt_type[0]['currency'],
                        'message' => 'Ticket Activated Successsful',
                        'QRCode' => $profileTrans['barcode'],
                        'ticketURL' => $this->settings['TicketBaseURL'] . "?evtk=" . $profileTrans['barcode'],
                        'eventName' => $eventData['eventName'],
                        'venue' => $eventData['venue'],
                        'start_date' => $eventData['dateStart'],
                        'QRCodeURL' => $profileTrans['barcodeURL'],
                        'posterURL' => $check_evnt_type[0]['posterURL'],
                        'ticketType' => $check_evnt_type[0]['ticket_type'],
                        'amount' => ($check_evnt_type[0]['amount'] - $check_evnt_type[0]['discount'])]);
                }
            }

            if ($ResultCode != "000") {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Failed to Query Payments', ['code' => 402
                            , 'message' => "Failed to Query Payments:::::" . $ResultExplanation], true);
            } else {
                $dpoTransactionQuery = "INSERT INTO dpo_transaction (TransID,CCDapproval,"
                        . "account,TransactionToken,created) VALUES (:TransID,:CCDapproval,"
                        . ":account,:TransactionToken,NOW())";

                $paramsDPOtrans = [
                    ':TransID' => $check_trxn['TransactionToken'],
                    ':CCDapproval' => $TransactionApproval,
                    ':account' => "MAD" . $check_trxn['transaction_id'],
                    ':TransactionToken' => $check_trxn['TransactionToken']
                ];
                $dpoResult = $this->rawInsert($dpoTransactionQuery, $paramsDPOtrans);

                if (!$dpoResult) {
                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Failed to Record Payments', ['code' => 402
                                , 'message' => "Failed to Record Payments:::::" . $ResultExplanation], true);
                }


                $purchase_type = 'Beneficiary';
                if ($check_trxn_profile[0]['profile_id'] != $check_trxn['profile_id']) {
                    $purchase_type = 'Sponsor';
                }
                if (!$success) {

                    $callback_data = [
                        'purchase_type' => $purchase_type,
                        'transaction_id' => $transaction_id,
                        'response_code' => 402,
                        'response_description' => 'Processed Failed',
                        'extra_data' => json_encode($error),
                        'narration' => 'Failed to update event profile ticket state',
                        'receipt_number' => "A" . $transaction_id . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                    $callback_id = Transactions::CreateTransactionCallback($callback_data);

                    return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                    , 'Failed to update event profile ticket state', ['code' => 404
                                , 'message' => $error]);
                }

                $callback_data = [
                    'purchase_type' => $purchase_type,
                    'transaction_id' => $transaction_id,
                    'response_code' => 200,
                    'response_description' => 'Processed Successfully',
                    'extra_data' => json_encode($success),
                    'narration' => 'Processed Successfully',
                    'receipt_number' => "A" . $transaction_id . '$' . $this->now('YmdHis') . "" . $this->randStrGen(30),];
                $callback_id = Transactions::CreateTransactionCallback($callback_data);

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | DPO Transaction Id:" . $transaction_id
                        . " | CreateTransactionCallback:$callback_id");

                $profileAttribute = Profiling::QueryProfileProfileId($check_trxn['profile_id']);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | UniqueId:" . $profileAttribute['msisdn'] . " profileID::" . $check_trxn['profile_id']
                        . " | DPO Transaction Id:" . $transaction_id);

                $sms = "";
                $ticketsData = [];
                foreach ($success as $succ) {

                    $sms .= "Dear " . $CustomerName . ", Your " . $succ['eventName'] . " ticket "
                            . "is " . $succ['QRCode'] . ". View your ticket from "
                            . $succ['ticketURL'] . " \n";

                    if ($profileAttribute['email'] != null) {
                        // sent email to clients
                        $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                . " | Profile Attribute::" . json_encode($profileAttribute));

                        $ticketsIn = [
                            'ticketName' => $succ['ticketType'],
                            'currency' => $succ['currency'],
                            'amount' => $succ['amount'],
                            'QrCode' => $succ['QRCode']
                        ];

                        array_push($ticketsData, $ticketsIn);
                    }
                }

                if ($profileAttribute['email'] != null) {
                    $paramsEmail = [
                        "eventID" => $success[0]['eventId'],
                        "orderNumber" => $TransactionApproval,
                        "paymentMode" => "DPO-PAYMENT",
                        "name" => $CustomerName,
                        "eventDate" => $success[0]['start_date'],
                        "eventName" => $success[0]['eventName'],
                        "amountPaid" => $success[0]['amount'],
                        'msisdn' => $check_trxn['msisdn'],
                        'ticketsArray' => $ticketsData,
                        'posterURL' => $success[0]['posterURL'],
                        'venue' => $success[0]['venue'],
                        'eventTicketInfo' => $success[0]['ticketType'],
                    ];
                    $postData = [
                        "api_key" => $this->settings['ServiceApiKey'],
                        "to" => $profileAttribute['email'],
                        "from" => "noreply@madfun.com",
                        "cc" => "",
                        "subject" => "Ticket for Event: " . $success[0]['eventName'],
                        "content" => "Ticket information",
                        "extrac" => $paramsEmail
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                            $postData, $this->settings['ServiceApiKey'], 3);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailWithoutAttachments Response::" .
                            " | UniqueId:" . $profileAttribute['msisdn'] . " profileID::" . $check_trxn['profile_id'] . " " .
                            json_encode($mailResponse) . " Payload::" .
                            json_encode($postData));
                }

                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $profileAttribute['msisdn'],
                    "message" => $sms . ". Madfun! For Queries call "
                    . "" . $this->settings['Helpline'],
                    "profile_id" => $check_trxn['profile_id'],
                    "created_by" => 'DPO_PAYMENT',
                    "is_bulk" => false,
                    "link_id" => ""];

                $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                        . " | UniqueId:" . $profileAttribute['msisdn'] . " profileID::" . $check_trxn['profile_id']
                        . " | DPO Transaction Id:" . $transaction_id);

                $message = new Messaging();
                $message->LogOutbox($params);
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Ticket Sent Successful', ['code' => 200
                            , 'success' => $success, 'error' => $error]);
            }
        } catch (Exception $ex) {
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Exception Error', ['code' => 500
                        , 'success' => "", 'error' => $ex->getMessage()], true);
        }
    }
}
