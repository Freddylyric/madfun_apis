<?php

use Phalcon\Mvc\Controller;
use ControllerBase as base;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class Messaging extends Controller {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }

    /**
     * LogInbox
     * @param type $params
     */
    public function LogInbox($params) {
        try {
            $duplicate = $this->base->rawSelect("SELECT * FROM `inbox` WHERE `unique_id`=:unique_id"
                    , [':unique_id' => $params['link_id']]);
            if ($duplicate) {
                return false;
            }

            $phql = "INSERT INTO `inbox`( `profile_id`, `short_code`, `unique_id`"
                    . ", `message`, `source`, `extra_data`, `received_on`"
                    . ", `created`) VALUES (:profile_id,:short_code,:unique_id"
                    . ",:message,:source,:extra_data,:received_on,NOW())";

            $insert_params = [
                ':profile_id' => $params['profile_id'],
                ':short_code' => $params['short_code'],
                ':unique_id' => $params['link_id'],
                ':message' => $params['inbox_message'],
                ':source' => $params['created_by'],
                ':received_on' => $params['inbox_date'],
                ':extra_data' => json_encode(['vaspro_inbox_id' => $params['inbox_id']])];
            $unique_id = $this->base->rawInsert($phql, $insert_params);

            return $unique_id;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * LogOutbox
     * @param type $params
     */
    public function LogOutbox($params) {
        try {

            $start = $this->base->getMicrotime();
            $phql = "INSERT INTO `outbox`( `profile_id`, `message`, `created_by`"
                    . ", `created`) VALUES (:profile_id,:message,:created_by"
                    . ",NOW())";
            $insert_params = [
                ':profile_id' => $params['profile_id'],
                ':message' => $params['message'],
                ':created_by' => $params['created_by'],];
            $unique_id = $this->base->rawInsert($phql, $insert_params);

            $params['message'] = $this->base->SMSTemplate($params['message']
                    , ['{helpline}' => $this->base->settings['Helpline'], '{line}' => "\n"]);
            $params['unique_id'] = $unique_id;

            $network = $this->base->getMobileNetwork($params['msisdn']);
            if ($network == "MTN_UGX") {

                $sms = [
                    'msisdn' => $params['msisdn'],
                    'message' => $params['message'],
                    'uniqueId' => $unique_id,];

                $result = $this->SendAfricasTalkingMessage($sms);

                return $result;
            } else {
                if ($this->base->settings['QueueSMS']) {
                    $this->SendBulkMessageQueue($params);
                    return true;
                } else {
                    $sms = [
                        'recipient' => $params['msisdn'],
                        'enqueue' => 1,
                        'apiKey' => $this->base->settings['ServiceApiKey'],
                        'shortCode' => $params['short_code'],
                        'message' => $params['message'],
                        'callbackURL' => $this->base->settings['mnoApps']['OndemandSmsCallback'],
                        'uniqueId' => $unique_id,];

                    $result = false;
                    if (isset($params['is_bulk'])) {
                        $result = $this->SendBulkMessage($sms);
                        $end = $this->base->getMicrotime() - $start;
                    } else {
                        $sms['link_id'] = $params['link_id'];

                        $result = $this->SendOndemandMessage($sms);
                        $end = $this->base->getMicrotime() - $start;
                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | $unique_id - " . $params['link_id']
                                . " | Execution Time $end Sec SendOndemandMessage::$sms");
                    }

                    if (!$result) {
                        $this->infologger->info(__LINE__ . ":" . __CLASS__
                                . " | $unique_id - " . $params['msisdn'] . " payload " . json_encode($result)
                                . " | Failed SMS Submit REQUEUE");

                        return false;
                    }

                    return $result;
                }
            }
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
     /**
     * SendAfricasTalkingMessage
     * @param type $postData
     */
    public function SendAfricasTalkingMessage($params) {
        try {
            $postUrl = $this->base->settings['mnoApps']['ATURL'];

            $postData = [
                "username" => "MADFUN",
                "message" => $params['message'],
                "senderId" => $this->base->settings['mnoApps']['DefaultSenderIdAT'],
                "phoneNumbers" => [$params['msisdn']]
            ];

            $result = $this->base->sendJsonATPostData($postUrl, $postData,$this->base->settings['mnoApps']['ATToken']);
            
            $this->infologger->addInfo(__LINE__ . ":" . __CLASS__
                                . " | " . $params['msisdn'] . " Reponse " .
                    json_encode($result). " Payload". json_encode($postData));

            $respo = json_decode($result['response']);
            if ($result['statusCode'] == 200) {
                $MessageID = $respo->SMSMessageData->Recipients[0]->messageId;
                $reponseMsg = $respo->SMSMessageData->Recipients[0]->status;
                $dlr = "INSERT INTO `outbox_dlr`(  `outbox_id`, "
                        . "`correlator`,`campaign_id`"
                        . ", `status`, `description`,`created`) "
                        . "VALUES (:outbox_id,:correlator,:campaignId,:status,"
                        . ":description,:created"
                        . ")";
                $insert_params_dlr = [
                    ':outbox_id' => $postData['uniqueId'],
                    ':correlator' => $MessageID,
                    ':campaignId' => "",
                    ':status' => $respo->SMSMessageData->Recipients[0]->statusCode,
                    ':description' => $reponseMsg,
                    ':created' => $this->base->now(),];

                $this->base->rawInsert($dlr, $insert_params_dlr);

                return $MessageID;
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * SendOndemandMessage
     * @param type $postData
     */
    public function SendOndemandMessage($postData) {
        try {
            $postUrl = $this->base->settings['mnoApps']['OndemandSmsAPI'];

            $result = $this->base->sendJsonPostData($postUrl, $postData);

            $respo = json_decode($result['response']);
            if ($result['statusCode'] == 200) {
                $MessageID = $respo->data->data->sms_data->correlators;
                $campaign_id = $respo->data->data->sms_data->campaign_id;
                $reponseMsg = $respo->data->message;
                $dlr = "INSERT INTO `outbox_dlr`(  `outbox_id`, "
                        . "`correlator`,`campaign_id`"
                        . ", `status`, `description`,`created`) "
                        . "VALUES (:outbox_id,:correlator,:campaignId,:status,"
                        . ":description,:created"
                        . ")";
                $insert_params_dlr = [
                    ':outbox_id' => $postData['uniqueId'],
                    ':correlator' => $MessageID,
                    ':campaignId' => $campaign_id,
                    ':status' => 400,
                    ':description' => $reponseMsg,
                    ':created' => $this->base->now(),];

                $this->base->rawInsert($dlr, $insert_params_dlr);

                return $MessageID;
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * SendBulkMessage
     * Sends Bulk Messages
     * @param type $postData
     * @return boolean
     * @throws Exception
     */
    public function SendBulkMessage($postData) {
        try {
            $postUrl = $this->base->settings['mnoApps']['BulkSMSAPI'];
            $result = $this->base->sendJsonPostData($postUrl, $postData);
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | sendJsonPostData SendBulkMessage::" .
                    json_encode($result) . " payload " . json_encode($postData));

            $respo = json_decode($result['response']);
            if ($result['statusCode'] == 200) {
                $MessageID = $respo->data->data->sms_data->correlators;
                $campaign_id = $respo->data->data->sms_data->campaign_id;
                $reponseMsg = $respo->data->message;
                $dlr = "INSERT INTO `outbox_dlr`(  `outbox_id`, "
                        . "`correlator`,`campaign_id`"
                        . ", `status`, `description`,`created`) "
                        . "VALUES (:outbox_id,:correlator,:campaignId,:status,"
                        . ":description,:created"
                        . ")";
                $insert_params_dlr = [
                    ':outbox_id' => $postData['uniqueId'],
                    ':correlator' => $MessageID,
                    ':campaignId' => $campaign_id,
                    ':status' => 400,
                    ':description' => $reponseMsg,
                    ':created' => $this->base->now(),];

                $this->base->rawInsert($dlr, $insert_params_dlr);

                return $MessageID;
            }

            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @param type $postData
     * @return bool
     * @throws Exception
     */
    public function SendBulkMessageQueue($postData) {
        try {
            $routeKey = $this->base->settings['Queues']['SMS']['Route'];
            $queueName = $this->base->settings['Queues']['SMS']['Queue'];
            $exchangeKey = $this->base->settings['Queues']['SMS']['Exchange'];

            $queue = new Queue();
            $res = $queue
                    ->ConnectAndPublishToQueue($postData
                    , $queueName
                    , $exchangeKey
                    , $routeKey);
            if ($res->code != 200) {

                return false;
            }
            return true;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * SendBulkNestedMessage
     * Sends Bulk Messages
     * @param type $postData
     * @return boolean
     * @throws Exception
     */
    public function SendBulkNestedMessage($postData) {
        try {
            $postUrl = $this->base->settings['mnoApps']['BulkNestedSMSAPI'];
            $result = $this->base->sendJsonPostData($postUrl, $postData);
            $respo = json_decode($result['response']);
            if ($result['statusCode'] == 200) {
                $MessageID = $respo->data->data->sms_data->correlators;
                $campaign_id = $respo->data->data->sms_data->campaign_id;
                $reponseMsg = $respo->data->message;
                $dlr = "INSERT INTO `outbox_dlr`(  `outbox_id`, "
                        . "`correlator`,`campaign_id`"
                        . ", `status`, `description`,`created`) "
                        . "VALUES (:outbox_id,:correlator,:campaignId,:status,"
                        . ":description,:created"
                        . ")";
                $insert_params_dlr = [
                    ':outbox_id' => $postData['uniqueId'],
                    ':correlator' => $MessageID,
                    ':campaignId' => $campaign_id,
                    ':status' => 400,
                    ':description' => $reponseMsg,
                    ':created' => $this->base->now(),];

                $this->base->rawInsert($dlr, $insert_params_dlr);

                return $MessageID;
            }

            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * WhatsAppSendMessage
     * @param type $params
     */
    public static function WhatsAppSendMessage($params) {
        //type,msisdn,longitude,latitude,locationName,address,whatsMessage,url,authorization,shortcode
        $base = new base();
        try {
            $postUrl = $base->settings["whatsApp"]["infobipAdvanceURL"];
            if ($params['type'] == "LOCATION") {
                $postData = [
                    "scenarioKey" => $base->settings["whatsApp"]["scenarioKey"],
                    "destinations" => [
                        "to" => [
                            "phoneNumber" => $params["phoneNumber"],
                        ]
                    ],
                    "whatsApp" => [
                        "longitude" => $params["longitudeNo"],
                        "latitude" => $params["latitudeNo"],
                        "locationName" => $params["locName"],
                        "address" => $params["addressName"]
                    ],
                    "sms" => [
                        "text" => $params["whatsMessage"],
                    ]
                ];
            } else {
                $postData = [
                    "scenarioKey" => $base->settings["whatsApp"]["scenarioKey"],
                    "destinations" => [
                        "to" => [
                            "phoneNumber" => $params["phoneNumber"],
                        ]
                    ],
                    "whatsApp" => [
                        "text" => $params["whatsMessage"],
                        $params["urlWhatsApp"] => $params["url"]
                    ],
                    "sms" => [
                        "text" => $params["whatsMessage"],
                    ]
                ];
            }
            $authorisation = $base->settings["whatsApp"]["Authorization"];

            $result = $base->SendJsonPostAuthData($postUrl, $postData, $authorisation, 1);
            $base->getLogFile("info")->error(__LINE__ . ":" . __CLASS__
                    . "Authorization: " . $authorisation . " | WhatsApp Info Log" . json_encode($result));
            return ['status' => true,
                'response' => $result['response']
            ];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * WhatsAppInboundMessage
     * @param type $params 
     */
    public static function WhatsAppOutboundMessage($params) {
        $base = new base();
        //shortCode,user_mapId,smspages,billable_amount,network,mobile,mesg,callbackURL,credit_use,client_id,retry

        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {

            $outbox = new WhatsappOutbox();
            $outbox->setTransaction($dbTrxn);
            $outbox->profile_id = $params['profile_id'];
            $outbox->user_id = $params['user_id'];
            $outbox->from = $params["shortCode"];
            $outbox->message = $params["whatsMessage"];
            $outbox->url = $params['url'];
            $outbox->latitudeNo = $params['latitude'];
            $outbox->longitudeNo = $params['longitude'];
            $outbox->message_type = $params['type'];
            $outbox->caption = $params['caption'];
            $outbox->source = $params['source'];
            $outbox->created_at = $base->now();

            if ($outbox->save() === false) {
                $errors = [];
                $messages = $outbox->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }

                $dbTrxn->rollback("Create Outbox failed. Reason" . json_encode($errors));
            }

            $outboxID = $outbox->outbox_id;

            $dbTrxn->commit();

            $postUrl = $base->settings["whatsApp"]["infobipAdvanceURL"];
            if ($params['type'] == "LOCATION") {
                $postData = [
                    "scenarioKey" => $base->settings["whatsApp"]["scenarioKey"],
                    "destinations" => [
                        "to" => [
                            "phoneNumber" => $params["msisdn"],
                        ]
                    ],
                    "whatsApp" => [
                        "longitude" => $params["longitude"],
                        "latitude" => $params["latitude"],
                        "locationName" => $params["locationName"],
                        "address" => $params["address"]
                    ],
                    "sms" => [
                        "text" => $params["whatsMessage"],
                    ]
                ];
            } else {
                $postData = [
                    "scenarioKey" => $base->settings["whatsApp"]["scenarioKey"],
                    "destinations" => [
                        "to" => [
                            "phoneNumber" => $params["msisdn"],
                        ]
                    ],
                    "whatsApp" => [
                        "text" => $params["whatsMessage"],
                        $params["urlWhatsApp"] => $params["url"]
                    ],
                    "sms" => [
                        "text" => $params["whatsMessage"],
                    ]
                ];
            }
            $base->getLogFile("info")->info(__LINE__ . ":" . __CLASS__
                    . " | WhatsApp Payload " . json_encode($postData) . " url: " . $postUrl);

            $authorisation = $base->settings["whatsApp"]["Authorization"];
            $wahtsAppResponse = $base->SendJsonPostAuthData($postUrl, $postData, $authorisation, 1);
            $data = json_encode($wahtsAppResponse['response'], JSON_UNESCAPED_SLASHES);
            $base->getLogFile("info")->info(__LINE__ . ":" . __CLASS__
                    . " | WhatsApp Infobip Response" . $data);

            return ['status' => true,
                'outbox_id' => $outboxID,
                'alert_message' => "WhatsApp Message Queue"];
        } catch (Exception $ex) {
            throw $ex;
        }
    }
}
