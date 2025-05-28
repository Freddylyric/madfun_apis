<?php

/**
 * Description of TurnstileController
 *
 * @author kevinmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class TurnstileController extends ControllerBase {

    protected $payload;
    protected $infologger;
    protected $errorlogger;
    protected $moduleName;

    function onConstruct() {
        $this->moduleName = substr(__CLASS__, 0, (strlen(__CLASS__) - 10));
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
    }

    /**
     * heartBeat
     * This function is called after every 5
     *  Seconds to show status of the Turnstile
     * @return type
     * @throws Exception
     */
    public function heartBeat() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | HeartBeat:" . json_encode($request->getJsonRawBody()));

        $Serial = isset($data->Serial) ? $data->Serial : null;
        $Key = isset($data->Key) ? $data->Key : null;
        $Status = isset($data->Status) ? $data->Status : null;
        $IP = isset($data->IP) ? $data->IP : null;
        $MAC = isset($data->MAC) ? $data->MAC : null;
        $ID = isset($data->ID) ? $data->ID : null;
        if (!$Serial || !$Status || !$Key) {
            $res = new \stdClass();
            $res->AcsRes = "0";
            $res->Key = $Key;
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        }
//        if (!in_array($this->getClientIPAddress(), ['192.168.1.230'])) {
//            $res = new \stdClass();
//            $res->Key = $Key;
//            $res->AcsRes = 0;
//            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
//        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $checkTurnstile = TurnstileDevices::findFirst(["serial=:serial:",
                        "bind" => ["serial" => $Serial],]);
            if (!$checkTurnstile) {
                $turnstileDevices = new TurnstileDevices();
                $turnstileDevices->setTransaction($dbTrxn);
                $turnstileDevices->serial = $Serial;
                $turnstileDevices->idVal = $ID;
                $turnstileDevices->ipAddress = $IP;
                $turnstileDevices->macAddress = $MAC;
                $turnstileDevices->status = $Status;
                $turnstileDevices->created = $this->now();
                if ($turnstileDevices->save() === false) {
                    $errors = [];
                    $messages = $turnstileDevices->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create turnstile failed " . json_encode($errors));
                }
            } else {
                $checkTurnstile->setTransaction($dbTrxn);
                $checkTurnstile->idVal = $ID;
                $checkTurnstile->ipAddress = $IP;
                $checkTurnstile->status = $Status;
                if ($checkTurnstile->save() === false) {
                    $errors = [];
                    $messages = $checkTurnstile->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Update turnstile failed " . json_encode($errors));
                }
            }
            $dbTrxn->commit();
            $res = new \stdClass();
            $res->Key = $Key;
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                    . " | Exception Code:" . $ex->getCode()
                    . " | Exception Code:" . $ex->getTraceAsString()
                    . " | Message:" . $ex->getMessage());
            $res = new \stdClass();
            $res->Key = $Key;
            $res->AcsRes = 0;
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        }
    }

    /**
     * accessRequest
     * @return type
     */
    public function accessRequest() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | accessRequest:" . json_encode($request->getJsonRawBody()));

        $Serial = isset($data->Serial) ? $data->Serial : null;
        $Key = isset($data->Key) ? $data->Key : null;
        $type = isset($data->type) ? $data->type : null;
        $Card = isset($data->Card) ? $data->Card : null;

        $qrCode = trim(base64_decode($Card));

        if (!$Serial || !$qrCode) {
            $res = new \stdClass();
            $res->AcsRes = "0";
            $res->ActIndex = "0";
            $res->Time = "1";
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        }

        try {

            $checkEventProfileTickets = $this->rawSelect("select isShowTicket "
                    . "from event_profile_tickets WHERE event_profile_tickets.barcode"
                    . " =:barcode limit 1", [':barcode' => $qrCode]);
            
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | accessRequest Qr:" . $qrCode. " ". json_encode($checkEventProfileTickets));

            if (!$checkEventProfileTickets) {
                $res = new \stdClass();
                $res->AcsRes = "0";
                $res->ActIndex = "0";
                $res->Time = "1";
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }
            if ($checkEventProfileTickets[0]['isShowTicket'] == 1) {
                $checkQRCode = $this->rawSelect("select event_profile_tickets.barcode,event_profile_tickets.profile_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.event_profile_ticket_id,event_show_tickets_type.event_ticket_show_id, "
                        . "event_profile_tickets_state.status,event_profile_tickets_state.created,event_shows.eventID as eventId,"
                        . "event_profile_tickets.event_tickets_option_id,event_show_tickets_type.amount,"
                        . "event_show_tickets_type.event_show_venue_id "
                        . " from event_profile_tickets join event_profile_tickets_state"
                        . " on  event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = "
                        . "event_profile_tickets.event_ticket_id join ticket_types "
                        . "on event_show_tickets_type.typeId = ticket_types.typeId "
                        . "join event_show_venue on event_show_tickets_type.event_show_venue_id "
                        . "= event_show_venue.event_show_venue_id join event_shows on "
                        . " event_show_venue.event_show_id = event_shows.event_show_id  WHERE "
                        . "event_profile_tickets.barcode =:barcode AND event_profile_tickets.isShowTicket = :isShowTicket"
                        , [':barcode' => $qrCode, ':isShowTicket' => 1]);
            } else {
                $checkQRCode = $this->rawSelect("select event_profile_tickets.barcode,event_profile_tickets.profile_id,ticket_types.ticket_type,"
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
                $res = new \stdClass();
                $res->AcsRes = "0";
                $res->ActIndex = "0";
                $res->Time = "1";
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }

            if ($checkQRCode[0]['status'] != 1) {
                $res = new \stdClass();
                $res->AcsRes = "0";
                $res->ActIndex = "0";
                $res->Time = "1";
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }
            if ($checkQRCode[0]['isRemmend'] == 1) {
                $res = new \stdClass();
               $res->AcsRes = "0";
                $res->ActIndex = "0";
                $res->Time = "1";
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }
//            $updateQRcodeSQL = "UPDATE event_profile_tickets set isRemmend = 1, isRedemmedBy=:isRedemmedBy "
//                    . " WHERE event_profile_ticket_id=:event_profile_ticket_id";
//            $params = [":event_profile_ticket_id" => $checkQRCode[0]['event_profile_ticket_id'],
//                ':isRedemmedBy' => 1];
//
//            $resultQRcode = $this->rawUpdateWithParams($updateQRcodeSQL, $params);

            $profile_id = $checkQRCode[0]['profile_id'];

            $msisdn = Profiling::QueryMobile($profile_id);
            $profileAttributed = Profiling::QueryProfileAttribution($profile_id);
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $checkQRCode[0]['eventId']],]);

            if (!$checkEvents) {
                $res = new \stdClass();
               $res->AcsRes = "0";
                $res->ActIndex = "0";
                $res->Time = "1";
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
            }

            if ($checkEvents->hasMultipleShow == 1) {
                $paramsUpdate = [
                    'event_ticket_id' => $checkQRCode[0]['event_ticket_show_id'],
                    'ticket_purchased' => "0",
                    'ticket_redeemed' => "1"
                ];
            } else {
                $paramsUpdate = [
                    'event_ticket_id' => $checkQRCode[0]['event_ticket_id'],
                    'ticket_purchased' => "0",
                    'ticket_redeemed' => "1"
                ];
            }

            $tickets = new Tickets();
            $tickets->EventTicketTypeUpdate($paramsUpdate, false, false, $checkQRCode[0]['event_tickets_option_id'], $checkEvents->hasMultipleShow);

           // if ($resultQRcode) {
//
//                $sms = [
//                    'created_by' => "Turnstile",
//                    'profile_id' => $profile_id,
//                    'msisdn' => $msisdn,
//                    'short_code' => $this->settings['mnoApps']['DefaultSenderId'],
//                    'message' => "Hello " . $profileAttributed['first_name'] . " "
//                    . "" . $profileAttributed['last_name'] . ", your ticket for "
//                    . "the Event: " . $checkEvents->eventName . " has been validated successful.",
//                    'is_bulk' => true,
//                    'link_id' => '',];
//
//                $sts = $this->getMicrotime();
//                $message = new Messaging();
//                $queueMessageResponse = $message->LogOutbox($sms);
//                $stopped = $this->getMicrotime() - $sts;
//                $this->infologger->addInfo(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
//                        . " | Took $stopped Seconds"
//                        . " | ProfleID:" . $profile_id
//                        . " | Mobile:$msisdn"
//                        . " | messaging::LogOutbox Reponse:" . json_encode($queueMessageResponse));
                $res = new \stdClass();
                $res->AcsRes = "1";
                $res->ActIndex = "0";
                $res->Time = "1";
                $res->Voice ="Welcome to Madfun Xperience";
                return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
//            }
//            $res = new \stdClass();
//            $res->AcsRes = "0";
//            $res->ActIndex = "0";
//            $res->Time = "1";
//            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            $res = new \stdClass();
            $res->AcsRes = "0";
            $res->ActIndex = "0";
            $res->Time = "1";
            return $this->turnstileResponse(__LINE__ . ":" . __CLASS__, $res);
        }
    }
}
