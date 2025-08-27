<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TransactionsInitiated
 *
 * @author User
 */
use Aws\S3\S3Client;
use ControllerBase as base;

class Tickets {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }

    /**
     * Initiate
     * @throws Exception
     */
    public static function CreateTicketProfile($params, $isComplimentary = 0, 
            $company = null, $optionId = null, $hasEventShow = 0,
            $ticketInfo = null, $ticketCap = 50, $quantity = 1, $name= null) {
        $base = new base();
        try {

            $statement = "SELECT event_profile_tickets.event_profile_ticket_id"
                    . " from event_profile_tickets WHERE"
                    . " event_profile_tickets.profile_id = :profile_id AND "
                    . "event_profile_tickets.event_ticket_id = :event_ticket_id"
                    . " and barcode = :barcode AND isShowTicket=:isShowTicket";

            $statement_param = [
                ":profile_id" => $params['profile_id'],
                ":event_ticket_id" => $params['event_ticket_id'],
                ":barcode" => $params['barcode'],
                ':isShowTicket' => $hasEventShow];

            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return $result[0]['event_profile_ticket_id'];
            }
            
            $countTickets = "SELECT count(event_profile_tickets.event_profile_ticket_id) as totalProfile "
                    . " from event_profile_tickets WHERE event_profile_tickets.profile_id = :profile_id "
                    . "AND event_profile_tickets.event_ticket_id = :event_ticket_id"
                    . " AND isShowTicket=:isShowTicket";
            
            $statement_param_tickets = [
                ":profile_id" => $params['profile_id'],
                ":event_ticket_id" => $params['event_ticket_id'],
                ':isShowTicket' => $hasEventShow];
            
            $resultCount = $base->rawSelect($countTickets, $statement_param_tickets);
            $base->getLogFile('info')->emergency(__LINE__ . ":" . __CLASS__
                                . " | TicketCount URL: " .$resultCount[0]['totalProfile']. " quantity ".$quantity );
            if($resultCount && $params['event_ticket_id'] == 632){
                if(((INT)$resultCount[0]['totalProfile'] + (INT) $quantity) > $ticketCap){
                    
                    return false;
                }
            }
            
            $insert_statement = "INSERT INTO event_profile_tickets "
                    . "(alias_name,event_ticket_id,profile_id,ticketInfo,reference_id,barcode,barcodeURL,created,utmSource,discount,isComplimentary, company,event_tickets_option_id,isShowTicket) VALUES "
                    . "(:alias_name,:event_ticket_id,:profile_id,:ticketInfo,:reference_id,:barcode,:barcodeURL,:created,:utmSource,:discount,:isComplimentary,:company,:event_tickets_option_id,:isShowTicket)";
            $insert_params = [
                 ":alias_name"=> $name,
                ":profile_id" => $params['profile_id'],
                ":reference_id" => $params['reference_id'],
                ":event_ticket_id" => $params['event_ticket_id'],
                ":barcode" => $params['barcode'],
                ":barcodeURL" => $params['barcodeURL'],
                ":created" => $base->now(),
                ":utmSource" => $params['reference'],
                ":discount" => isset($params['discount']) ? $params['discount'] :0,
                ":isComplimentary" => $isComplimentary,
                ":company" => strtoupper($company),
                ":event_tickets_option_id" => $optionId,
                ":ticketInfo" => $ticketInfo,
                ':isShowTicket' => $hasEventShow
            ];
            $resultInsert = $base->rawInsert($insert_statement, $insert_params);

            return $resultInsert;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public static function GetProfileTicketState($params) {
        $base = new base();
        try {
            $statement = "SELECT * from event_profile_tickets_state "
                    . "WHERE event_profile_ticket_id = :event_profile_ticket_id";

            $selectParams = [
                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
            ];
            $result = $base->rawSelect($statement, $selectParams);
            if ($result) {
                return $result[0];
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
     public function GetProfileTickets($params) {
        $base = new base();
        try {
            $statement = "SELECT * from event_profile_tickets "
                    . "WHERE event_profile_ticket_id = :event_profile_ticket_id";

            $selectParams = [
                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
            ];
            $result = $base->rawSelect($statement, $selectParams);
            if ($result) {
                return $result[0];
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        } 
     }

    /**
     * 
     * @param type $folder
     * @param type $files
     * @return bool
     * @throws Exception
     */
    public static function uploadFileToAwsFromRequest($folder, $files, $customName) {
        $base = new base();
        try {
            $aws = $base->settings['aws']['client'];
            $bucket = $base->settings['aws']['bucket'];
            $temp_location = $base->settings['aws']['temp_location'];

            $s3Client = new S3Client($aws);

            // get tmp folder
            $location = $temp_location;
            foreach ($files as $file) {
                if ($file) {
                    if ($file->getSize() === 0) {
                        return false;
                    }

                    // get extension
                    $ext = $file->getExtension();
                    // set new name based on supplied title
                    $name = $customName . "_" . rand(100, 999) . "." . $ext;
                    // create local path in tmp folder
                    $location = rtrim($location, "/");
                    $location = "$location/$name";
                    // move uploade file to tmp location
                    //$file_tmp = $_FILES['image']['tmp_name'];
                    if (!$file->moveTo($location)) {
                        //die();
                        return false;
                    }
                    // LETS UPLOAD TO AWS=
                    // create gcoud metadata
                    $key = "$name";
                    try {
                        $result = $s3Client->putObject([
                            'Bucket' => $bucket,
                            'Key' => $key,
                            'Body' => fopen($location, 'r'),
                            'ACL' => 'public-read',
                        ]);
                        //delete the file
                        unlink($location);
                        $base->getLogFile('info')->emergency(__LINE__ . ":" . __CLASS__
                                . " | Response URL: " . $result->get('ObjectURL'));

                        return $result->get('ObjectURL'); //$cloudfront."/$key";
                    } catch (Aws\S3\Exception\S3Exception $e) {
                        $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__ . ": | AWS ERROR:" . $e->getMessage());
                    }
                    //delete the file
                    unlink($location);
                }
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @param type $params
     * @return type
     * @throws Exception
     */
    public static function ProfileTicketState($params) {
        $base = new base();
        try {
            $statement = "SELECT * from event_profile_tickets_state "
                    . "WHERE event_profile_ticket_id = :event_profile_ticket_id";

            $selectParams = [
                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
            ];
            $result = $base->rawSelect($statement, $selectParams);

            if ($result) {
                if ($result[0]['status'] == $params['status']) {
                    return false;
                }
                $sqlUpdate = "UPDATE event_profile_tickets_state SET status =:status "
                        . "WHERE event_profile_ticket_id = :event_profile_ticket_id ";
                $selectParams = [
                    ':event_profile_ticket_id' => $params['event_profile_ticket_id'],
                    ':status' => $params['status']
                ];
                $resultUpdate = $base->rawUpdateWithParams($sqlUpdate,
                        $selectParams);
                return $result[0]['id'];
            }
            $sqlInsert = "INSERT INTO event_profile_tickets_state "
                    . "(event_profile_ticket_id,status,created) VALUES (:event_profile_ticket_id,:status,:created)";
            $selectParams = [
                ':event_profile_ticket_id' => $params['event_profile_ticket_id'],
                ':status' => $params['status'],
                ':created' => $base->now()
            ];
            return $base->rawInsert($sqlInsert, $selectParams);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @param type $params
     * @return boolean
     * @throws Exception
     */
    public static function EventTicketTypeUpdate($params, $isComplement = false, $isRedemeedCode = false, $event_tickets_option_id = null, $hasMultipleShow = 0) {
        $base = new base();
        try {
            if ($hasMultipleShow == 1) {
                $statement = "SELECT event_show_tickets_type.event_ticket_show_id AS event_ticket_id,event_show_tickets_type.amount,event_show_tickets_type.hasOption,"
                        . "event_show_tickets_type.discount,events.posterURL,event_shows.eventID AS eventId,"
                        . "event_show_tickets_type.group_ticket_quantity, "
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
                        . " = :event_ticket_id";
            } else {
                $statement = "SELECT * from event_tickets_type "
                        . "WHERE event_tickets_type.event_ticket_id = :event_ticket_id";
            }
            $selectParams = [
                ':event_ticket_id' => $params['event_ticket_id']
            ];

            $result = $base->rawSelect($statement, $selectParams);

            if (!$result) {
                return false;
            }

            if ($hasMultipleShow == 1) {
                $statementUpdate = "UPDATE event_show_tickets_type SET "
                        . "event_show_tickets_type.ticket_purchased ="
                        . " event_show_tickets_type.ticket_purchased + :ticket_purchased,"
                        . " event_show_tickets_type.ticket_redeemed =  event_show_tickets_type.ticket_redeemed "
                        . "+ :ticket_redeemed  WHERE event_show_tickets_type.event_ticket_show_id = :event_ticket_id";

                $paramsUpdate = [
                    ':event_ticket_id' => $params['event_ticket_id'],
                    ':ticket_purchased' => $params['ticket_purchased'],
                    ':ticket_redeemed' => $params['ticket_redeemed']
                ];
            } else {
                $statementUpdate = "UPDATE event_tickets_type SET "
                        . "event_tickets_type.ticket_purchased ="
                        . " event_tickets_type.ticket_purchased + :ticket_purchased,"
                        . " event_tickets_type.ticket_redeemed =  event_tickets_type.ticket_redeemed "
                        . "+ :ticket_redeemed  WHERE event_tickets_type.event_ticket_id = :event_ticket_id";

                $paramsUpdate = [
                    ':event_ticket_id' => $params['event_ticket_id'],
                    ':ticket_purchased' => $params['ticket_purchased'],
                    ':ticket_redeemed' => $params['ticket_redeemed']
                ];
            }
            
            
            
            $isUpgrade = false;
            if(isset($params['isUpgrade'])){
               $isUpgrade  = $params['isUpgrade'];
            }
            

            if ($isUpgrade && $params['old_event_ticket_id'] != null) {
                if ($hasMultipleShow == 1) {
                    $statementUpdateUpgrade = "UPDATE event_show_tickets_type SET "
                            . "event_show_tickets_type.ticket_purchased ="
                            . " event_show_tickets_type.ticket_purchased - :ticket_purchased"
                            . " WHERE event_show_tickets_type.event_ticket_show_id = :event_ticket_id";
                    $paramsUpdateUpgrade = [
                        ':event_ticket_id' => $params['old_event_ticket_id'],
                        ':ticket_purchased' => $params['ticket_purchased']
                    ];
                } else {
                    $statementUpdateUpgrade = "UPDATE event_tickets_type SET "
                            . "event_tickets_type.ticket_purchased ="
                            . " event_tickets_type.ticket_purchased - :ticket_purchased"
                            . " WHERE event_tickets_type.event_ticket_id = :event_ticket_id";
                    $paramsUpdateUpgrade = [
                        ':event_ticket_id' => $params['old_event_ticket_id'],
                        ':ticket_purchased' => $params['ticket_purchased']
                    ];
                }

                $base->rawUpdateWithParams($statementUpdateUpgrade,
                        $paramsUpdateUpgrade);
            }
            $complimentaryAmount = 0;
            if ($isComplement) {
                if ($hasMultipleShow == 1) {
                    $statementUpdate = "UPDATE event_show_tickets_type SET "
                            . "event_show_tickets_type.issued_complimentary ="
                            . " event_show_tickets_type.issued_complimentary + :issued_complimentary,"
                            . " event_show_tickets_type.ticket_redeemed =  event_show_tickets_type.ticket_redeemed "
                            . "+ :ticket_redeemed  WHERE event_show_tickets_type.event_ticket_show_id = :event_ticket_id";

                    $paramsUpdate = [
                        ':event_ticket_id' => $params['event_ticket_id'],
                        ':issued_complimentary' => $params['ticket_purchased'],
                        ':ticket_redeemed' => $params['ticket_redeemed']
                    ];
                    $complimentaryAmount = $params['ticket_purchased'];
                } else {
                    $statementUpdate = "UPDATE event_tickets_type SET "
                            . "event_tickets_type.issued_complimentary ="
                            . " event_tickets_type.issued_complimentary + :issued_complimentary,"
                            . " event_tickets_type.ticket_redeemed =  event_tickets_type.ticket_redeemed "
                            . "+ :ticket_redeemed  WHERE event_tickets_type.event_ticket_id = :event_ticket_id";

                    $paramsUpdate = [
                        ':event_ticket_id' => $params['event_ticket_id'],
                        ':issued_complimentary' => $params['ticket_purchased'],
                        ':ticket_redeemed' => $params['ticket_redeemed']
                    ];
                    $complimentaryAmount = $params['ticket_purchased'];
                }
            }
            $redemeedAmount = 0;

            if ($isRedemeedCode) {
                if ($hasMultipleShow == 1) {
                    $statementUpdate = "UPDATE event_show_tickets_type SET "
                            . "event_show_tickets_type.issued_ticket_code ="
                            . " event_show_tickets_type.issued_ticket_code + :total_ticket_code,"
                            . " event_show_tickets_type.ticket_redeemed =  event_show_tickets_type.ticket_redeemed "
                            . "+ :ticket_redeemed  WHERE event_show_tickets_type.event_ticket_show_id = :event_ticket_id";

                    $paramsUpdate = [
                        ':event_ticket_id' => $params['event_ticket_id'],
                        ':total_ticket_code' => $params['ticket_purchased'],
                        ':ticket_redeemed' => $params['ticket_redeemed']
                    ];
                    $redemeedAmount = $params['ticket_purchased'];
                } else {
                    $statementUpdate = "UPDATE event_tickets_type SET "
                            . "event_tickets_type.issued_ticket_code ="
                            . " event_tickets_type.issued_ticket_code + :total_ticket_code,"
                            . " event_tickets_type.ticket_redeemed =  event_tickets_type.ticket_redeemed "
                            . "+ :ticket_redeemed  WHERE event_tickets_type.event_ticket_id = :event_ticket_id";

                    $paramsUpdate = [
                        ':event_ticket_id' => $params['event_ticket_id'],
                        ':total_ticket_code' => $params['ticket_purchased'],
                        ':ticket_redeemed' => $params['ticket_redeemed']
                    ];
                    $redemeedAmount = $params['ticket_purchased'];
                }
            }



            $resultUpdate = $base->rawUpdateWithParams($statementUpdate,
                    $paramsUpdate);

            $checkEventStats = "SELECT event_statistics.event_stats_id from "
                    . "event_statistics where event_statistics.eventID = :eventID";

            $selectParamsStats = [
                ':eventID' => $result[0]['eventId']
            ];
            $resultStats = $base->rawSelect($checkEventStats, $selectParamsStats);
            if ($resultStats) {
                // Get revenue share

                $checkEventRev = "SELECT events.revenueShare from "
                        . "events where events.eventID = :eventID";

                $selectParamsRev = [
                    ':eventID' => $result[0]['eventId']
                ];
                $resultRev = $base->rawSelect($checkEventRev, $selectParamsRev);

                $serivces_fee = 0;

                if ($resultRev[0]['revenueShare'] > 0) {
                    $serivces_fee = (float) $result[0]['amount'] * ($resultRev[0]['revenueShare'] / 100);
                }
                $statementUpdateStats = "UPDATE event_statistics SET "
                        . "event_statistics.total_tickets_collection = event_statistics.total_tickets_collection + :total_tickets_collection,"
                        . "event_statistics.ticket_purchased ="
                        . " event_statistics.ticket_purchased + :ticket_purchased,"
                        . " event_statistics.serivces_fee = event_statistics.serivces_fee + :serivces_fee, "
                        . " event_statistics.ticket_redeemed =  event_statistics.ticket_redeemed "
                        . "+ :ticket_redeemed, event_statistics.total_tickets_collection ="
                        . " event_statistics.total_tickets_collection + 1, "
                        . "event_statistics.issued_complimentary = "
                        . "event_statistics.issued_complimentary + :issued_complimentary,"
                        . "event_statistics.issued_ticket_code = event_statistics.issued_ticket_code "
                        . "+ :issued_ticket_code  "
                        . " WHERE event_statistics.eventID = :eventID";

                $ticketCol = $params['ticket_purchased'] * (float) $result[0]['amount'];

                $paramsUpdatestats = [
                    ':eventID' => $result[0]['eventId'],
                    ':serivces_fee' => $serivces_fee,
                    ':total_tickets_collection' => $ticketCol,
                    ':ticket_purchased' => $params['ticket_purchased'],
                    ':ticket_redeemed' => $params['ticket_redeemed'],
                    ':issued_complimentary' => $complimentaryAmount,
                    ':issued_ticket_code' => $redemeedAmount
                ];
                $resultUpdate = $base->rawUpdateWithParams($statementUpdateStats,
                        $paramsUpdatestats);
            }

            if ($resultUpdate) {
                return $result[0]['event_ticket_id'];
            }
            return $result[0]['event_ticket_id'];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * queryEventTicketType
     * @param type $params
     * @return boolean
     * @throws Exception
     */
    public static function queryEventTicketType($params) {
        $base = new base();
        try {
            $statement = "select event_tickets_type.typeId,ticket_types.ticket_type,"
                    . "event_tickets_type.eventId , event_tickets_type.amount, "
                    . "event_tickets_type.discount,event_tickets_type.currency,"
                    . "event_tickets_type.group_ticket_quantity,event_tickets_type.total_tickets,"
                    . "event_tickets_type.total_complimentary,event_tickets_type.ticket_purchased,"
                    . "event_tickets_type.issued_complimentary,event_tickets_type.ticket_redeemed,"
                    . "event_tickets_type.status"
                    . " from event_tickets_type join ticket_types on "
                    . " event_tickets_type.typeId = ticket_types.typeId "
                    . "WHERE event_tickets_type.event_ticket_id = :event_ticket_id ";

            $selectParams = [
                ':event_ticket_id' => $params['event_ticket_id']
            ];
            $result = $base->rawSelect($statement, $selectParams);
            if ($result) {
                return $result[0];
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @param type $param
     */
    public static function queryEvent($param) {
        $base = new base();
        try {
            $statement = "select eventName, venue , status, currency,"
                    . "DATE_FORMAT(start_date, '%a %e %M %Y, %h:%i %p')"
                    . " as dateStart,dateInfo,end_date,posterURL  from events "
                    . "WHERE events.eventID = :eventID";

            $selectParams = [
                ':eventID' => $param['eventID']
            ];
            $result = $base->rawSelect($statement, $selectParams);
            if ($result) {
                return $result[0];
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * shareTickets
     * @param type $params
     * @return type
     * @throws Exception
     */
    public static function shareTickets($params) {
        $base = new base();
        try {
//            $statement = "SELECT * from user_ticket_share_map "
//                    . "WHERE event_profile_ticket_id = :event_profile_ticket_id";
//
//            $selectParamsTicket = [
//                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
//            ];
//            $result = $base->rawSelect($statement, $selectParamsTicket);
//            if ($result) {
//                return false;
//            }
            $sqlInsert = "INSERT INTO user_ticket_share_map "
                    . "(user_id,event_profile_ticket_id,created) VALUES (:user_id,:event_profile_ticket_id,:created)";
            $selectParams = [
                ':event_profile_ticket_id' => $params['event_profile_ticket_id'],
                ':user_id' => $params['user_id'],
                ':created' => $base->now()
            ];

            $resultTicketID = $base->rawInsert($sqlInsert, $selectParams);

            $sqlUpdate = "UPDATE event_profile_tickets SET profile_id =:profile_id,"
                    . "barcode=:barcode,barcodeURL=:barcodeURL "
                    . "WHERE event_profile_ticket_id = :event_profile_ticket_id ";
            $selectParamsUpdate = [
                ':event_profile_ticket_id' => $params['event_profile_ticket_id'],
                ':barcode' => $params['barcode'],
                ':barcodeURL' => $params['barcodeURL'],
                ':profile_id' => $params['profile_id']
            ];
            $base->rawUpdateWithParams($sqlUpdate,
                    $selectParamsUpdate);

            return $resultTicketID;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public static function affiliatorSales($params) {
        $base = new base();
        try {
            $statement = "select * from affiliator_sales where"
                    . " affiliator_event_map_id=:affiliator_event_map_id"
                    . " and event_profile_ticket_id=:event_profile_ticket_id";

            $selectParams = [
                ':affiliator_event_map_id' => $params['affiliator_event_map_id'],
                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
            ];
            $result = $base->rawSelect($statement, $selectParams);
            if ($result) {
                return $result[0]['id'];
            }
            $insert_statement = "INSERT INTO affiliator_sales "
                    . "(affiliator_event_map_id,event_profile_ticket_id,created)"
                    . " VALUES (:affiliator_event_map_id,:event_profile_ticket_id,now())";
            $insert_params = [
                ':affiliator_event_map_id' => $params['affiliator_event_map_id'],
                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
            ];
            $resultInsert = $base->rawInsert($insert_statement, $insert_params);
            return $resultInsert;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public static function addDiscount($params) {
        $base = new base();
        try {
            $statement = "select * from event_discounts where"
                    . " event_ticket_id=:event_ticket_id"
                    . " and event_profile_ticket_id=:event_profile_ticket_id";

            $selectParams = [
                ':event_ticket_id' => $params['event_ticket_id'],
                ':event_profile_ticket_id' => $params['event_profile_ticket_id']
            ];
            $result = $base->rawSelect($statement, $selectParams);
            if ($result) {
                return $result[0]['event_tickets_discount_id'];
            }
            $insert_statement = "INSERT INTO event_discounts "
                    . "(event_ticket_id,hasMultipleShow,event_profile_ticket_id,"
                    . "discount,created)"
                    . " VALUES (:event_ticket_id,:hasMultipleShow,"
                    . ":event_profile_ticket_id,:discount,now())";
            $insert_params = [
                ':event_ticket_id' => $params['event_ticket_id'],
                ':hasMultipleShow' => $params['hasMultipleShow'],
                ':event_profile_ticket_id' => $params['event_profile_ticket_id'],
                ':discount' => $params['discount']
            ];
            $resultInsert = $base->rawInsert($insert_statement, $insert_params);
            return $resultInsert;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public static function EventTicketVisible($params) {
        $base = new base();
        try {
            $statementUpdate = "UPDATE event_profile_tickets SET "
                    . "isTicketVisible=:isTicketVisible WHERE "
                    . "event_profile_ticket_id=:event_profile_ticket_id";

            $paramsUpdatestats = [
                ':isTicketVisible' => $params['isTicketVisible'],
                ':event_profile_ticket_id' => $params['event_profile_ticket_id'],
            ];
            $base->rawUpdateWithParams($statementUpdate,
                        $paramsUpdatestats);
            return true;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
}
