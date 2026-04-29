<?php

/**
 * Description of DashboardController
 *
 * @author kevinkmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class DashboardController extends ControllerBase {

    protected $payload;
    protected $infologger;
    protected $errorlogger;
    protected $moduleName;

    function onConstruct() {
        $this->moduleName = substr(__CLASS__, 0, (strlen(__CLASS__) - 10));
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
    }

    public function viewAffiliatorSales() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Affiliator Sales Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $days = isset($data->days) ? $data->days : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($days) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!$offset) {
            $offset = 1;
        }
        if (!$limit) {
            $limit = $this->settings['RecordsLimit'];
        }
        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "affiliator_sales.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'totalTickets';
            $order = 'DESC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);
            if (!$checkEvents) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event Id']);
            }
            $searchQuery2 = " WHERE  "
                    . "affiliator_event_map.eventId=" . $eventID;

            $searchQuery = " WHERE  "
                    . "affiliator_event_map.eventId=" . $eventID . ""
                    . "";

            ///if($eventID != 92){
            $searchQuery .= " AND event_profile_tickets_state.status = 1 ";
            //}

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_profile_tickets.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created)>='$start'";
            }
            if ($stop == null && $start == null && $days != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) between (DATE(NOW()) - INTERVAL $days "
                        . " DAY) AND DATE(NOW()) ";
                if ($days == 0) {
                    $searchQuery .= " AND date(event_profile_tickets.created) = DATE(NOW()) ";
                }
            }
            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            if ($checkEvents->hasMultipleShow == 1) {
                $sql = "select affiliator_event_map.`code`,profile_attribute.first_name "
                        . "as affiliator, ticket_types.ticket_type,affiliator_event_map.discount, "
                        . "IFNULL((affiliator_event_map.discount * count(DISTINCT affiliator_sales.id)),0) as "
                        . "totalDiscount, affiliator_event_map.status,count(DISTINCT affiliator_sales.id)"
                        . " as totalTickets from affiliator_sales join "
                        . "affiliator_event_map on affiliator_sales.affiliator_event_map_id"
                        . " = affiliator_event_map.id join affiliators on "
                        . "affiliator_event_map.affilator_id = affiliators.affilator_id "
                        . "join user on affiliators.user_id = user.user_id join"
                        . " profile_attribute on user.profile_id = profile_attribute.profile_id"
                        . " join event_profile_tickets on affiliator_sales.event_profile_ticket_id "
                        . "= event_profile_tickets.event_profile_ticket_id join"
                        . " event_show_tickets_type on event_profile_tickets.event_ticket_id "
                        . "= event_show_tickets_type.event_ticket_show_id join ticket_types"
                        . " on event_show_tickets_type.typeId = ticket_types.typeId join"
                        . " event_profile_tickets_state on event_profile_tickets.event_profile_ticket_id "
                        . "=  event_profile_tickets_state.event_profile_ticket_id "
                        . "join user_event_map on affiliator_event_map.eventId ="
                        . " user_event_map.eventID $searchQuery group by "
                        . "affiliator_sales.affiliator_event_map_id, "
                        . "ticket_types.ticket_type $sorting";
            } else {
                $sql = "select affiliator_event_map.`code`,affiliator_event_map.discount,(select CONCAT(profile_attribute.first_name,' ',"
                        . "profile_attribute.surname,' ',profile_attribute.last_name)"
                        . " from affiliator_event_map join affiliators on "
                        . "affiliator_event_map.affilator_id = affiliators.affilator_id"
                        . " join `user` on affiliators.user_id  = `user`.user_id "
                        . "join profile on user.profile_id  =  profile.profile_id "
                        . "join profile_attribute on profile_attribute.profile_id  = "
                        . "profile.profile_id where affiliator_event_map.id  = "
                        . "affiliator_sales.affiliator_event_map_id and "
                        . "affiliator_event_map.eventId = event_tickets_type.eventId ) "
                        . "as affiliator, COUNT(DISTINCT affiliator_sales.id) AS totalTickets,"
                        . "ticket_types.ticket_type, affiliator_event_map.discount "
                        . "as totalDiscount, affiliator_event_map.status"
                        . " from affiliator_sales join event_profile_tickets on"
                        . " affiliator_sales.event_profile_ticket_id =  "
                        . "event_profile_tickets.event_profile_ticket_id join"
                        . " event_tickets_type on event_profile_tickets.event_ticket_id "
                        . " = event_tickets_type.event_ticket_id join ticket_types"
                        . " on event_tickets_type.typeId = ticket_types.typeId "
                        . "join event_profile_tickets_state on "
                        . "event_profile_tickets.event_profile_ticket_id =  "
                        . "event_profile_tickets_state.event_profile_ticket_id "
                        . "join affiliator_event_map on affiliator_sales.affiliator_event_map_id "
                        . "= affiliator_event_map.id $searchQuery group "
                        . "by affiliator, ticket_types.ticket_type $sorting";
            }


            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Affiliators :" .
                    json_encode($request->getJsonRawBody()) . " SQL: " . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $endTime = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no"
                            . " Records for Events Types ( $endTime Seconds)"
                            , 'data' => []], true);
            }

            $endTime = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events Types results ($endTime Seconds)"
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * summaryDashboardStats
     * @return type
     */
    public function summaryDashboardStats() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Summary Events Tickets Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $days = isset($data->days) ? $data->days : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($days) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!$offset) {
            $offset = 1;
        }
        if (!$limit) {
            $limit = $this->settings['RecordsLimit'];
        }
        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "event_tickets_type.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_tickets_type.event_ticket_id';
            $order = 'ASC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $searchQuery = " WHERE 1 ";

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(e.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(e.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(e.created)>='$start'";
            }
            if ($stop == null && $start == null && $days != null) {
                $searchQuery .= " AND date(e.created) between (DATE(NOW()) - INTERVAL $days "
                        . " DAY) AND DATE(NOW()) ";
                if ($days == 0) {
                    $searchQuery .= " AND date(e.created) = DATE(NOW()) ";
                }
            }

            $sql = "select (select count(*) from events e where 1 "
                    . " and e.status = 1) as activeEvent,(select "
                    . "count(event_profile_tickets.event_profile_ticket_id) "
                    . "from event_profile_tickets join event_profile_tickets_state as e "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "e.event_profile_ticket_id $searchQuery and  e.status = 1)"
                    . " as totalTickets, (select count(*) from event_profile_tickets"
                    . " as e $searchQuery and e.isRemmend = 1) as totalRedemmed,"
                    . " (select count(*) from profile as e $searchQuery) as totalCustomer,"
                    . "(select sum(e.mpesa_amount) from mpesa_transaction as e $searchQuery) as totalDeposit";

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stopTm = $this->getMicrotime() - $start;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no"
                            . " Records for Events Types ( $stopTm Seconds)"
                            , 'data' => []], true);
            }

            $stopTm = $this->getMicrotime() - $start;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events Types results ($stopTm Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result[0]]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * summaryEventsTickets
     * @return type
     */
    public function summaryEventsTickets() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Summary Events Tickets Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $days = isset($data->days) ? $data->days : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($days) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!$offset) {
            $offset = 1;
        }
        if (!$limit) {
            $limit = $this->settings['RecordsLimit'];
        }
        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "event_tickets_type.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_tickets_type.event_ticket_id';
            $order = 'ASC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $searchQuery = " WHERE user_event_map.user_mapId = " . $auth_response['user_mapId'];
            if ($eventID != null) {
                if ($eventID == 306) {
                    
                }
                $searchQuery = " WHERE events.eventID='$eventID'";
            }

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(events.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(events.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(events.created)>='$start'";
            }
            if ($stop == null && $start == null && $days != null) {
                $searchQuery .= " AND date(events.created) between (DATE(NOW()) - INTERVAL $days "
                        . " DAY) AND DATE(NOW()) ";
                if ($days == 0) {
                    $searchQuery .= " AND date(events.created) = DATE(NOW()) ";
                }
            }

            if ($event_show_venue_id) {
                $searchQuery2 = " WHERE event_show_tickets_type.event_show_venue_id = " . $event_show_venue_id;
                $searchQuery3 = " WHERE event_show_venue.event_show_venue_id = " . $event_show_venue_id;

                $sql = "SELECT (SELECT `events`.currency FROM `events` JOIN  "
                        . "event_shows on `events`.eventID =event_shows.eventID "
                        . "JOIN event_show_venue on event_shows.event_show_id = "
                        . " event_show_venue.event_show_id $searchQuery3 limit 1 ) as currency, "
                        . "(SELECT SUM(ticket_purchased/group_ticket_quantity) "
                        . "FROM event_show_tickets_type $searchQuery2) as totalTicketRefund,"
                        . "(SELECT SUM((ticket_purchased/group_ticket_quantity) * amount) "
                        . "FROM event_show_tickets_type $searchQuery2) AS totalAmountEvent,"
                        . "(SELECT SUM((total_refund/group_ticket_quantity) * amount) "
                        . "FROM event_show_tickets_type $searchQuery2) AS totalRefund,"
                        . "(SELECT SUM(ticket_purchased) FROM event_show_tickets_type"
                        . " $searchQuery2) as purchased, (SELECT SUM(ticket_redeemed)"
                        . " FROM event_show_tickets_type $searchQuery2) as redeemed,"
                        . " (SELECT SUM(total_tickets) FROM event_show_tickets_type "
                        . " $searchQuery2) as allTickets,(SELECT IFNULL(SUM(event_profile_tickets.discount),0)"
                        . " FROM event_profile_tickets JOIN event_profile_tickets_state"
                        . " on event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id "
                        . "JOIN event_show_tickets_type on event_profile_tickets.event_ticket_id"
                        . " = event_show_tickets_type.event_ticket_show_id JOIN ticket_types "
                        . "ON event_show_tickets_type.typeId = ticket_types.typeId"
                        . " JOIN event_show_venue ON event_show_tickets_type.event_show_venue_id"
                        . " = event_show_venue.event_show_venue_id JOIN event_shows"
                        . " on event_show_venue.event_show_id = event_shows.event_show_id"
                        . " WHERE event_profile_tickets_state.`status` = 1 AND "
                        . "event_show_tickets_type.event_show_venue_id ="
                        . " " . $event_show_venue_id . " and event_profile_tickets.isShowTicket=1) as totalDiscount";
            } else {
                $sql = "select events.eventID,events.posterURL,events.eventName,events.currency,"
                        . "(select SUM(event_tickets_type.total_refund/event_tickets_type.group_ticket_quantity)"
                        . " from event_tickets_type where event_tickets_type.eventId = events.eventID)"
                        . " as totalTicketRefund, "
                        . "events.status,events.revenueShare, events.start_date,"
                        . "(select sum(event_tickets_type.total_tickets * event_tickets_type.group_ticket_quantity) "
                        . "from event_tickets_type join ticket_types on "
                        . "ticket_types.typeId = event_tickets_type.typeId where "
                        . "event_tickets_type.eventId =  events.eventID ) as allTickets,"
                        . "(select sum(event_tickets_type.ticket_purchased) from  "
                        . "event_tickets_type where event_tickets_type.eventId = events.eventID)"
                        . " as purchased,(SELECT count(*) FROM event_profile_tickets "
                        . "where event_ticket_id in (SELECT event_ticket_id from "
                        . "event_tickets_type where eventId = events.eventID) and isRemmend =1) as redeemed,"
                        . "(select sum(IFNULL(((event_tickets_type.ticket_purchased / "
                        . "event_tickets_type.group_ticket_quantity)*event_tickets_type.amount),0))"
                        . "  FROM event_tickets_type "
                        . "where event_tickets_type.eventId = events.eventID) as "
                        . "totalAmountEvent,(select sum(IFNULL(((event_tickets_type.total_refund /"
                        . "event_tickets_type.group_ticket_quantity)*event_tickets_type.amount),0)) "
                        . " FROM event_tickets_type "
                        . "where event_tickets_type.eventId = events.eventID) as totalRefund, "
                        . "0 as totalAmountDPO,(select count(events.eventID) "
                        . "from events join user_event_map on user_event_map.eventID =  "
                        . "events.eventID  $searchQuery) AS total,(select count(DISTINCT "
                        . "event_profile_tickets.profile_id) from event_profile_tickets "
                        . "join event_profile_tickets_state on event_profile_tickets_state.event_profile_ticket_id "
                        . "=  event_profile_tickets.event_profile_ticket_id JOIN "
                        . "event_tickets_type ON event_tickets_type.event_ticket_id = "
                        . "event_profile_tickets.event_ticket_id WHERE event_tickets_type.eventId "
                        . "= events.eventID and event_profile_tickets_state.status = 1) as totalMember,"
                        . "(SELECT SUM(event_profile_tickets.discount) FROM "
                        . "event_profile_tickets JOIN event_profile_tickets_state"
                        . " on event_profile_tickets.event_profile_ticket_id ="
                        . " event_profile_tickets_state.event_profile_ticket_id"
                        . " JOIN event_tickets_type on event_profile_tickets.event_ticket_id"
                        . " = event_tickets_type.event_ticket_id JOIN ticket_types"
                        . " ON event_tickets_type.typeId = ticket_types.typeId"
                        . " WHERE event_profile_tickets_state.`status` = 1 and"
                        . " event_tickets_type.eventId = `events`.eventID) as totalDiscount  "
                        . "from events join user_event_map on user_event_map.eventID =  "
                        . "events.eventID  $searchQuery LIMIT $limit";
            }



            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | SQL Function:" . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop = $this->getMicrotime() - $start;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no"
                            . " Records for Events Types ( $stop Seconds)"
                            , 'data' => []], true);
            }

            $stop = $this->getMicrotime() - $start;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events Types results ($stop Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * summaryEventsTickets
     * @return type
     */
    public function summaryEventsShowTickets() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Summary Events Tickets Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $event_show_id = isset($data->event_show_id) ? $data->event_show_id : null;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $days = isset($data->days) ? $data->days : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($event_show_id) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($days) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source || !$event_show_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!$offset) {
            $offset = 1;
        }
        if (!$limit) {
            $limit = $this->settings['RecordsLimit'];
        }
        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "event_tickets_type.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_tickets_type.event_ticket_id';
            $order = 'ASC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $searchQuery = " WHERE user_event_map.user_mapId = " . $auth_response['user_mapId'] . " AND event_shows.event_show_id=" . $event_show_id;
            if ($eventID != null) {
                $searchQuery .= " AND events.eventID='$eventID'";
            }

            if ($event_show_venue_id != null) {
                $searchQuery .= " AND event_show_venue.event_show_venue_id=$event_show_venue_id";
            }
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(events.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(events.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(events.created)>='$start'";
            }
            if ($stop == null && $start == null && $days != null) {
                $searchQuery .= " AND date(events.created) between (DATE(NOW()) - INTERVAL $days "
                        . " DAY) AND DATE(NOW()) ";
                if ($days == 0) {
                    $searchQuery .= " AND date(events.created) = DATE(NOW()) ";
                }
            }
            $sql = "SELECT event_shows.show,events.eventID,events.currency, events.posterURL,"
                    . "(select sum(event_show_tickets_type.total_tickets * "
                    . "event_show_tickets_type.group_ticket_quantity) "
                    . "FROM event_show_tickets_type WHERE event_show_tickets_type.event_show_venue_id "
                    . "= event_show_venue.event_show_venue_id)"
                    . " as  allTickets	,events.eventName, events.status,"
                    . " events.revenueShare,(select sum(event_show_tickets_type.ticket_purchased)"
                    . "	 from  event_show_tickets_type JOIN event_show_venue on"
                    . " event_show_tickets_type.event_show_venue_id =  "
                    . "event_show_venue.event_show_venue_id where "
                    . "event_show_venue.event_show_id  = event_shows.event_show_id ) "
                    . "as purchased, 	 events.start_date,(select "
                    . "sum(event_show_tickets_type.ticket_redeemed) from "
                    . "event_show_tickets_type 	 where event_show_tickets_type.event_show_venue_id "
                    . "= event_show_venue.event_show_venue_id) AS redeemed,"
                    . " (select SUM(IFNULL((event_show_tickets_type.ticket_purchased / event_show_tickets_type.group_ticket_quantity) * event_show_tickets_type.amount,0))"
                    . "	FROM  event_show_tickets_type JOIN event_show_venue on "
                    . "event_show_tickets_type.event_show_venue_id = event_show_venue.event_show_venue_id  "
                    . "WHERE event_show_venue.event_show_id  = event_shows.event_show_id) "
                    . "AS totalAmountEvent,0 AS totalAmountDPO,(SELECT count( events.eventID) "
                    . "FROM event_shows JOIN user_event_map ON user_event_map.eventID = "
                    . "event_shows.eventID  JOIN events on events.eventID = event_shows.eventID"
                    . "	$searchQuery) AS total,( select sum(event_show_tickets_type.ticket_purchased)"
                    . " from  event_show_tickets_type where event_show_tickets_type.event_show_venue_id "
                    . "= event_show_venue.event_show_venue_id) AS totalMember, "
                    . "IFNULL((SELECT SUM(event_profile_tickets.discount) FROM "
                    . "event_profile_tickets JOIN event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id  join "
                    . "event_show_tickets_type on event_profile_tickets.event_ticket_id "
                    . "= event_show_tickets_type.event_ticket_show_id JOIN event_show_venue "
                    . "on event_show_tickets_type.event_show_venue_id =  "
                    . "event_show_venue.event_show_venue_id WHERE event_show_venue.event_show_id "
                    . " = event_shows.event_show_id and "
                    . "event_profile_tickets_state.`status` = 1 and event_profile_tickets.isShowTicket=1),0) as totalDiscount,(select "
                    . "SUM((event_show_tickets_type.total_refund/event_show_tickets_type.group_ticket_quantity) * event_show_tickets_type.amount) from "
                    . "event_show_tickets_type 	 where event_show_tickets_type.event_show_venue_id "
                    . "= event_show_venue.event_show_venue_id) AS totalRefund,(select "
                    . "SUM(event_show_tickets_type.total_refund/event_show_tickets_type.group_ticket_quantity) from "
                    . "event_show_tickets_type 	 where event_show_tickets_type.event_show_venue_id "
                    . "= event_show_venue.event_show_venue_id) AS totalTicketRefund  FROM "
                    . "event_shows JOIN user_event_map ON user_event_map.eventID = "
                    . "event_shows.eventID JOIN events on events.eventID= event_shows.eventID "
                    . "JOIN event_show_venue on event_show_venue.event_show_id = event_shows.event_show_id "
                    . " $searchQuery";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | SQLFunction:" . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop = $this->getMicrotime() - $start;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no"
                            . " Records for Events Types ( $stop Seconds)"
                            , 'data' => []], true);
            }

            $stop = $this->getMicrotime() - $start;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events Types results ($stop Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * dashTicketPurchaseGraph
     * @return type
     */
    public function dashTicketPurchaseGraph() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Tickets Graph Action:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $this->payload['days'] = isset($data->days) ? $data->days : null;
        $this->payload['start'] = isset($data->start) ? $data->start : null;
        $this->payload['end'] = isset($data->end) ? $data->end : null;
        $this->payload['source'] = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($this->payload['days']) || $this->checkForMySQLKeywords($this->payload['start']) || $this->checkForMySQLKeywords($this->payload['end']) || $this->checkForMySQLKeywords($this->payload['source'])) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }


        try {
            $auth = new Authenticate();
            if ($token) {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
                if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7]))) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'User doesn\'t have permissions to perform this action.');
                }
            }

            $whereArray = [
                'event_profile_tickets_state.status' => 1];

            $searchQuery = $this->whereQuery($whereArray, "");

            $sort = 'a.Date';
            $order = 'ASC';
            $sorting = "ORDER BY $sort $order";

            $searchQuery2 = "WHERE 1 ";
            if ($this->payload['end'] != null && $this->payload['start'] != null) {
                $searchQuery2 .= " AND a.Date BETWEEN '" . $this->payload['start']
                        . "' AND '" . $this->payload['end'] . "'";
            } else if ($this->payload['end'] != null && $this->payload['start'] == null) {
                $searchQuery2 .= " AND date(a.Date)<='" . $this->payload['end'] . "'";
            } else if ($this->payload['end'] == null && $this->payload['start'] != null) {
                $searchQuery2 .= " AND date(a.Date)>='" . $this->payload['start'] . "'";
            } else if ($this->payload['start'] == null && $this->payload['end'] == null &&
                    $this->payload['days'] != null) {

                $days = $this->payload['days'];
                if ($days == 0) {
                    $searchQuery2 .= " AND a.Date = DATE(NOW())";
                } else {
                    $searchQuery2 .= " AND a.Date between (DATE(NOW()) - INTERVAL $days "
                            . " DAY) AND DATE(NOW()) ";
                }
            } else {
                $searchQuery2 .= " AND a.Date = DATE(NOW())";
            }

            if ($this->payload['start'] == null && $this->payload['end'] == null &&
                    $this->payload['days'] == null) {
                $searchQuery2 .= " AND a.Date = DATE(NOW())";
                $sorting = $this->tableQueryBuilder($sort, $order, 0, 7);
            }

            $sql = "Select DATE_FORMAT(a.Date,'%M %e') as Date, COALESCE(( select"
                    . " count(event_profile_tickets_state.id) from event_profile_tickets_state"
                    . " join event_profile_tickets on event_profile_tickets_state.event_profile_ticket_id"
                    . " = event_profile_tickets.event_profile_ticket_id "
                    . " $searchQuery and event_profile_tickets.isComplimentary = 0 and  event_profile_tickets_state.created between "
                    . "CONCAT(a.Date,' 00:00:00') and CONCAT(a.Date,' 23:59:59')), 0) "
                    . "as COUNT from ( select curdate() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) "
                    . "DAY as Date from (select 0 as a union all select 1 union all select 2 "
                    . "union all select 3 union all select 4 union all select 5 union all "
                    . "select 6 union all select 7 union all select 8 union all select 9) "
                    . " as a cross join (select 0 as a union all select 1 union all select 2 union all "
                    . " select 3 union all select 4 union all select 5 union all select 6 union all "
                    . " select 7 union all select 8 union all select 9) as b cross "
                    . "join (select 0 as a union all select 1 union all select 2 "
                    . "union all select 3 union all select 4 union all select 5 union all "
                    . " select 6 union all select 7 union all select 8 union all "
                    . "select 9) as c ) as a $searchQuery2 $sorting";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Tickets Graph Action:" . $sql);

            $result = $this->rawSelect("$sql");

            if (empty($result)) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'No Ticket Purhcase Graph Found'
                                , [
                            'code' => 404,
                            'message' => "Query returned no results ( $stop Seconds)",
                            'data' => [], 'record_count' => 0], true);
            }

            $stop = $this->getMicrotime() - $start_time;
            return $this->successLarge(__LINE__ . ":" . __CLASS__
                            , 'Ok', [
                        'code' => 200,
                        'message' => "Query for Ticket Purhcase Graph returned results ( $stop Seconds)",
                        'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error!');
        }
    }

    /**
     * ticketPurchaseGraph
     * @return type
     */
    public function ticketPurchaseGraph() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Tickets Graph Action:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $this->payload['days'] = isset($data->days) ? $data->days : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $this->payload['start'] = isset($data->start) ? $data->start : null;
        $this->payload['end'] = isset($data->end) ? $data->end : null;
        $this->payload['source'] = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($this->payload['days']) || $this->checkForMySQLKeywords($this->payload['start']) || $this->checkForMySQLKeywords($this->payload['end']) || $this->checkForMySQLKeywords($this->payload['source'])) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID,],]);
            if (!$checkEvents) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'No Ticket Purhcase Graph Found'
                                , [
                            'code' => 404,
                            'message' => "Query returned no results ( $stop Seconds)",
                            'data' => [], 'record_count' => 0], true);
            }
            if ($eventID == 306) {
                $eventID = 252;
            }
            if ($checkEvents->hasMultipleShow == 1) {
                $whereArray = [
                    'event_profile_tickets_state.status' => 1,
                    'event_profile_tickets.isShowTicket' => 1,
                    'event_shows.eventID' => $eventID];
            } else {
                $whereArray = [
                    'event_profile_tickets_state.status' => 1,
                    'event_tickets_type.eventId' => $eventID];
            }


            $searchQuery = $this->whereQuery($whereArray, "");

            $searchQuery .= " AND ticket_types.typeId != 11";
            if ($event_show_venue_id && $checkEvents->hasMultipleShow == 1) {
                $searchQuery .= " AND  event_show_tickets_type.event_show_venue_id= " . $event_show_venue_id;
            }


            $sort = 'a.Date';
            $order = 'ASC';
            $sorting = "ORDER BY $sort $order";

            $searchQuery2 = "WHERE 1 ";
            if ($this->payload['end'] != null && $this->payload['start'] != null) {
                $searchQuery2 .= " AND a.Date BETWEEN '" . $this->payload['start']
                        . "' AND '" . $this->payload['end'] . "'";
            } else if ($this->payload['end'] != null && $this->payload['start'] == null) {
                $searchQuery2 .= " AND date(a.Date)<='" . $this->payload['end'] . "'";
            } else if ($this->payload['end'] == null && $this->payload['start'] != null) {
                $searchQuery2 .= " AND date(a.Date)>='" . $this->payload['start'] . "'";
            } else if ($this->payload['start'] == null && $this->payload['end'] == null &&
                    $this->payload['days'] != null) {

                $days = $this->payload['days'];
                if ($days == 0) {
                    $searchQuery2 .= " AND a.Date = DATE(NOW())";
                } else {
                    $searchQuery2 .= " AND a.Date between (DATE(NOW()) - INTERVAL $days "
                            . " DAY) AND DATE(NOW()) ";
                }
            } else {
                $searchQuery2 .= " AND a.Date = DATE(NOW())";
            }

            if ($this->payload['start'] == null && $this->payload['end'] == null &&
                    $this->payload['days'] == null) {
                $searchQuery2 .= " AND a.Date = DATE(NOW())";
                $sorting = $this->tableQueryBuilder($sort, $order, 0, 7);
            }

            if ($checkEvents->hasMultipleShow == 1) {
                $sql = "Select DATE_FORMAT(a.Date,'%M %e') as Date, COALESCE(( select"
                        . " count(event_profile_tickets_state.id) from event_profile_tickets_state"
                        . " join event_profile_tickets on event_profile_tickets_state.event_profile_ticket_id"
                        . " = event_profile_tickets.event_profile_ticket_id join event_show_tickets_type "
                        . "on event_show_tickets_type.event_ticket_show_id = event_profile_tickets.event_ticket_id "
                        . "join ticket_types on ticket_types.typeId = event_show_tickets_type.typeId join "
                        . "event_show_venue on event_show_venue.event_show_venue_id = "
                        . "event_show_tickets_type.event_show_venue_id join event_shows on"
                        . " event_shows.event_show_id = event_show_venue.event_show_id "
                        . " $searchQuery and event_profile_tickets.isComplimentary = 0 and"
                        . " date(event_profile_tickets_state.created) > '2021-12-09' and "
                        . " event_profile_tickets_state.created between "
                        . "CONCAT(a.Date,' 00:00:00') and CONCAT(a.Date,' 23:59:59')), 0) "
                        . "as COUNT from ( select curdate() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) "
                        . "DAY as Date from (select 0 as a union all select 1 union all select 2 "
                        . "union all select 3 union all select 4 union all select 5 union all "
                        . "select 6 union all select 7 union all select 8 union all select 9) "
                        . " as a cross join (select 0 as a union all select 1 union all select 2 union all "
                        . " select 3 union all select 4 union all select 5 union all select 6 union all "
                        . " select 7 union all select 8 union all select 9) as b cross "
                        . "join (select 0 as a union all select 1 union all select 2 "
                        . "union all select 3 union all select 4 union all select 5 union all "
                        . " select 6 union all select 7 union all select 8 union all "
                        . "select 9) as c ) as a $searchQuery2 $sorting";
            } else {
                $sql = "Select DATE_FORMAT(a.Date,'%M %e') as Date, COALESCE(( select"
                        . " count(event_profile_tickets_state.id) from event_profile_tickets_state"
                        . " join event_profile_tickets on event_profile_tickets_state.event_profile_ticket_id"
                        . " = event_profile_tickets.event_profile_ticket_id join event_tickets_type "
                        . "on event_tickets_type.event_ticket_id = event_profile_tickets.event_ticket_id "
                        . "join ticket_types on ticket_types.typeId = event_tickets_type.typeId "
                        . " $searchQuery and event_profile_tickets.isComplimentary = 0 and date(event_profile_tickets_state.created) > '2021-12-09' and  event_profile_tickets_state.created between "
                        . "CONCAT(a.Date,' 00:00:00') and CONCAT(a.Date,' 23:59:59')), 0) "
                        . "as COUNT from ( select curdate() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) "
                        . "DAY as Date from (select 0 as a union all select 1 union all select 2 "
                        . "union all select 3 union all select 4 union all select 5 union all "
                        . "select 6 union all select 7 union all select 8 union all select 9) "
                        . " as a cross join (select 0 as a union all select 1 union all select 2 union all "
                        . " select 3 union all select 4 union all select 5 union all select 6 union all "
                        . " select 7 union all select 8 union all select 9) as b cross "
                        . "join (select 0 as a union all select 1 union all select 2 "
                        . "union all select 3 union all select 4 union all select 5 union all "
                        . " select 6 union all select 7 union all select 8 union all "
                        . "select 9) as c ) as a $searchQuery2 $sorting";
            }

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Tickets Graph Action:" . $sql);

            $result = $this->rawSelect("$sql");

            if (empty($result)) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'No Ticket Purhcase Graph Found'
                                , [
                            'code' => 404,
                            'message' => "Query returned no results ( $stop Seconds)",
                            'data' => [], 'record_count' => 0], true);
            }

            $stop = $this->getMicrotime() - $start_time;
            return $this->successLarge(__LINE__ . ":" . __CLASS__
                            , 'Ok', [
                        'code' => 200,
                        'message' => "Query for Ticket Purhcase Graph returned results ( $stop Seconds)",
                        'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error!');
        }
    }

    /**
     * ticketTypesEvents
     * @return type
     */
    public function ticketTypesShowsEvents() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Summary Events Tickets Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7, 8]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $whereArray = [
                'event_shows.eventID' => $eventID, 'ev.event_show_venue_id' => $event_show_venue_id];

            $searchQuery = $this->whereQuery($whereArray, "");
            $sql = "SELECT etp.event_ticket_show_id as event_ticket_id,etp.total_refund,"
                    . "etp.color_code,etp.main_color_code,etp.discount,etp.currency, "
                    . "ticket_types.ticket_type,etp.status, ticket_types.caption, "
                    . "sum( etp.amount ) AS amount, sum( etp.total_tickets ) AS "
                    . "total_tickets, etp.total_complimentary, etp.issued_complimentary, "
                    . "etp.group_ticket_quantity,(select SUM(IFNULL((etp.ticket_purchased "
                    . "/ etp.group_ticket_quantity) * etp.amount,0))) AS amountPurchase,0 "
                    . "AS amountDPO,etp.ticket_purchased,( SELECT sum( amount_received )  "
                    . "FROM event_keywords  WHERE event_keywords.eventId = event_shows.eventId  )"
                    . " AS keywordsAmount  FROM event_show_tickets_type etp JOIN ticket_types"
                    . " ON etp.typeId = ticket_types.typeId  JOIN event_show_venue ev on "
                    . "ev.event_show_venue_id = etp.event_show_venue_id JOIN event_shows ON "
                    . "event_shows.event_show_id = ev.event_show_id $searchQuery"
                    . "  GROUP BY etp.event_ticket_show_id";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Summary Events Tickets Request:" .
                    json_encode($request->getJsonRawBody()) . " SQL: " . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no"
                            . " Records for Events Types ( $stop Seconds)"
                            , 'data' => []], true);
            }

            $stop = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events Types results ($stop Seconds)"
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * ticketTypesEvents
     * @return type
     */
    public function ticketTypesEvents() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Summary Events Tickets Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 3, 6, 7, 8]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $sql = "select etp.total_refund,etp.event_ticket_id,etp.discount,ticket_types.ticket_type,ticket_types.caption, sum(etp.amount) as amount,"
                    . "sum(etp.total_tickets) as totalTickets,etp.total_complimentary,etp.issued_complimentary,etp.group_ticket_quantity,"
                    . "etp.ticket_purchased as totalPurchase,etp.currency,"
                    . "sum(etp.ticket_redeemed) as totalRedeemed,etp.status,"
                    . " ((etp.ticket_purchased / etp.group_ticket_quantity)  * etp.amount) "
                    . "as amountPurchase,"
                    . " 0 as amountDPO,(select "
                    . "sum(amount_received) from event_keywords "
                    . " where event_keywords.eventId = etp.eventId ) as keywordsAmount,"
                    . "IFNULL((SELECT SUM(event_profile_tickets.discount) FROM "
                    . "event_profile_tickets JOIN event_profile_tickets_state"
                    . " on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id WHERE "
                    . "event_profile_tickets.event_ticket_id = etp.event_ticket_id "
                    . "AND event_profile_tickets_state.`status` = 1),0) as totalDiscount "
                    . " from event_tickets_type etp join ticket_types on "
                    . "etp.typeId = ticket_types.typeId join "
                    . "events on events.eventID = etp.eventId WHERE events.status = 1  group "
                    . "by etp.event_ticket_id order by totalTickets desc  limit 5";

            if ($eventID != null) {
                $whereArray = [
                    'etp.eventId' => $eventID];

                $searchQuery = $this->whereQuery($whereArray, "");
                $searchQuery2 = " AND event_tickets_type.eventId = $eventID ";

                $sql = "select etp.total_refund,etp.event_ticket_id,etp.discount,ticket_types.ticket_type,etp.color_code,etp.main_color_code,"
                        . "ticket_types.caption,  sum(etp.amount) as amount,etp.currency,"
                        . "sum(etp.total_tickets) as total_tickets,etp.total_complimentary, "
                        . "etp.issued_complimentary,etp.group_ticket_quantity,"
                        . "etp.ticket_purchased,sum(etp.ticket_redeemed) as "
                        . "totalRedeemed,etp.status, ((etp.ticket_purchased / etp.group_ticket_quantity) * amount) "
                        . "as amountPurchase,0 as amountDPO, "
                        . "(select sum(amount_received) from event_keywords  "
                        . "where event_keywords.eventId = etp.eventId ) as  keywordsAmount,"
                        . "IFNULL((SELECT SUM(event_profile_tickets.discount) FROM "
                        . "event_profile_tickets JOIN event_profile_tickets_state"
                        . " on event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id WHERE "
                        . "event_profile_tickets.event_ticket_id = etp.event_ticket_id "
                        . "AND event_profile_tickets_state.`status` = 1),0) as totalDiscount "
                        . "  from event_tickets_type etp join ticket_types on "
                        . "etp.typeId = ticket_types.typeId  $searchQuery group"
                        . " by etp.event_ticket_id";
            }

            if ($event_show_venue_id) {
                $sql = "select etp.total_refund,etp.event_ticket_show_id as event_ticket_id,etp.discount,ticket_types.ticket_type,"
                        . "ticket_types.caption,  etp.amount,etp.color_code,etp.main_color_code,"
                        . "sum(etp.total_tickets) as total_tickets,etp.total_complimentary, "
                        . "etp.issued_complimentary,etp.group_ticket_quantity,etp.currency,"
                        . "(etp.ticket_purchased/etp.group_ticket_quantity) as ticket_purchased,"
                        . "sum(etp.ticket_redeemed) as "
                        . "totalRedeemed,etp.status, ((etp.ticket_purchased / etp.group_ticket_quantity) * amount) "
                        . "as amountPurchase,0 as amountDPO, "
                        . "0 as  keywordsAmount,IFNULL((SELECT SUM(event_profile_tickets.discount)"
                        . " FROM event_profile_tickets JOIN event_profile_tickets_state on"
                        . " event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id"
                        . " WHERE event_profile_tickets.event_ticket_id = etp.event_ticket_show_id and event_profile_tickets.isShowTicket=1),0) as totalDiscount "
                        . "  from event_show_tickets_type etp join ticket_types on "
                        . "etp.typeId = ticket_types.typeId  where "
                        . "etp.event_show_venue_id = " . $event_show_venue_id . " group"
                        . " by etp.event_ticket_show_id";
            }


            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Summary Events Tickets Request:" .
                    json_encode($request->getJsonRawBody()) . " SQL: " . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no"
                            . " Records for Events Types ( $stop Seconds)"
                            , 'data' => []], true);
            }

            $stop = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events Types results ($stop Seconds)"
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * customersAction
     * @return type
     */
    public function customersAction() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | customersAction:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $days = isset($data->days) ? $data->days : null;
        $offset = isset($data->offset) ? $data->offset : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($start)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$offset) {
            $offset = 1;
        }

        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "profile.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'profile.profile_id';
            $order = 'DESC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 3])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }

            $searchQuery = "WHERE 1";
            $searchQuery1 = "WHERE 1";

            if ($stop != null && $start != null) {
                $searchQuery .= " AND profile.created BETWEEN '$start' AND '$stop' ";
                $searchQuery1 .= " AND profile.created BETWEEN '$start' AND '$stop' ";
            } else if ($stop != null && $start == null) {
                $searchQuery .= " AND date(profile.created)<='$stop'";
                $searchQuery1 .= " AND date(profile.created)<='$stop'";
            } else if ($stop == null && $start != null) {
                $searchQuery .= " AND date(profile.created)>='$start'";
                $searchQuery1 .= " AND date(profile.created)>='$start'";
            } else if ($stop == null && $start == null &&
                    $days != null) {


                $searchQuery1 .= " AND date(profile.created) between (DATE(NOW()) - INTERVAL ($days + $days) "
                        . " DAY) AND (DATE(NOW()) - INTERVAL $days "
                        . " DAY) AND DATE(NOW()) ";

                $searchQuery .= " AND date(profile.created) between (DATE(NOW()) - INTERVAL $days "
                        . " DAY) AND DATE(NOW()) ";

                if ($days == 0) {
                    $searchQuery .= " AND date(profile.created) = DATE(NOW()) ";

                    $searchQuery1 .= " AND date(profile.created) between (DATE(NOW()) - INTERVAL 1 "
                            . " DAY) AND  DATE(NOW()) ";
                }
            } else {
                $searchQuery .= " AND date(profile.created) = DATE(NOW()) ";

                $searchQuery1 .= " AND date(profile.created) between (DATE(NOW()) - INTERVAL 1 "
                        . " DAY) AND  DATE(NOW()) ";
            }

            $sql = "SELECT ( select count(profile.profile_id) from profile "
                    . "$searchQuery) AS totalNew, (select count(profile.profile_id) "
                    . "from profile $searchQuery1 and profile.profile_id in (select profile.profile_id "
                    . "from profile  $searchQuery)) as totalreturn";

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no customer Results ( $stop_time Seconds)"
                            , 'data' => []], true);
            }

            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried customer results ($stop_time Seconds)",
                        'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewTicketPurchased
     * @return type
     */
    public function viewTicketPurchased() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $regex = '/"api_key":"[^"]*?"/';
        $string = (preg_replace($regex, '"api_key":***'
                        , json_encode($request->getJsonRawBody())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Verify "
                . "Request::$string");

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        
        $isRemmend = isset($data->isRemmend) ? $data->isRemmend : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($start)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$offset) {
            $offset = 1;
        }
        if (!$limit) {
            $limit = $this->settings['RecordsLimit'];
        }
        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "event_profile_tickets.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_profile_tickets.event_profile_ticket_id';
            $order = 'DESC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            $searchQuery = "WHERE event_profile_tickets_state.status = 1 ";
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_profile_tickets.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created)>='$start'";
            }
            
            if ($isRemmend !== null) {
                $searchQuery .= " AND event_profile_tickets.isRemmend = '$isRemmend'";
            }
            $hasMultipleShow = 0;

            if ($eventID != null) {
                if ($eventID == 306) {
                    $eventID = 252;
                }
                $searchQuery .= " AND events.eventID ='$eventID'";
                $checkEvents = Events::findFirst([
                            "eventID =:eventID: ",
                            "bind" => [
                                "eventID" => $eventID,],]);
                if (!$checkEvents) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ID']);
                }
                $hasMultipleShow = $checkEvents->hasMultipleShow;
                if ($hasMultipleShow == 1) {
                    $searchQuery .= " AND event_profile_tickets.isShowTicket =1";
                }
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = "select event_profile_tickets.event_profile_ticket_id,event_profile_tickets.ticketInfo,"
                    . "profile.msisdn,user.email, profile_attribute.first_name,"
                    . "profile_attribute.surname,profile_attribute.last_name,"
                    . "events.eventName,events.venue, event_tickets_type.amount,ticket_types.ticket_type,"
                    . " event_profile_tickets.barcode, event_profile_tickets.barcodeURL,"
                    . " event_profile_tickets.isRemmend, "
                    . " event_profile_tickets_state.status, event_profile_tickets.created,"
                    . "(select count(event_profile_tickets.event_profile_ticket_id) FROM "
                    . " event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "profile on profile.profile_id  = event_profile_tickets.profile_id "
                    . "join profile_attribute on  profile_attribute.profile_id = "
                    . "profile.profile_id join event_tickets_type ON "
                    . "event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id JOIN events on "
                    . "events.eventID  = event_tickets_type.eventId JOIN "
                    . "user on user.profile_id = profile.profile_id $searchQuery) as total_count"
                    . " FROM event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "profile on profile.profile_id  = event_profile_tickets.profile_id "
                    . "join profile_attribute on  profile_attribute.profile_id = "
                    . "profile.profile_id join event_tickets_type ON "
                    . "event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id JOIN events on "
                    . "events.eventID  = event_tickets_type.eventId "
                    . "JOIN user on user.profile_id = profile.profile_id join"
                    . " ticket_types on ticket_types.typeId = event_tickets_type.typeId $searchQuery $sorting";

            if ($hasMultipleShow == 1) {
                $sql = "select event_profile_tickets.event_profile_ticket_id,"
                        . "profile.msisdn,user.email, profile_attribute.first_name,"
                        . "profile_attribute.surname,profile_attribute.last_name,"
                        . "events.eventName,events.venue, event_show_tickets_type.amount,"
                        . "ticket_types.ticket_type,event_profile_tickets.barcode, "
                        . "event_profile_tickets.barcodeURL,"
                        . "event_profile_tickets.isRemmend, "
                        . "event_profile_tickets_state.status, "
                        . "event_profile_tickets.created,(select "
                        . "count(event_profile_tickets.event_profile_ticket_id) "
                        . "FROM event_profile_tickets join event_profile_tickets_state "
                        . "on event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id "
                        . "join profile on profile.profile_id  = event_profile_tickets.profile_id "
                        . "join profile_attribute on  profile_attribute.profile_id "
                        . "= profile.profile_id join event_show_tickets_type ON "
                        . "event_show_tickets_type.event_ticket_show_id = "
                        . "event_profile_tickets.event_ticket_id JOIN "
                        . "event_show_venue on event_show_venue.event_show_venue_id"
                        . " = event_show_tickets_type.event_show_venue_id JOIN "
                        . "event_shows on event_shows.event_show_id = "
                        . "event_show_venue.event_show_id JOIN events on "
                        . "events.eventID  = event_shows.eventID JOIN user on "
                        . "user.profile_id = profile.profile_id $searchQuery) as "
                        . "total_count FROM  event_profile_tickets join "
                        . "event_profile_tickets_state on event_profile_tickets.event_profile_ticket_id "
                        . "= event_profile_tickets_state.event_profile_ticket_id "
                        . "join  profile on profile.profile_id  = "
                        . "event_profile_tickets.profile_id join profile_attribute "
                        . "on  profile_attribute.profile_id = profile.profile_id "
                        . "join event_show_tickets_type ON event_show_tickets_type.event_ticket_show_id"
                        . " = event_profile_tickets.event_ticket_id JOIN event_show_venue "
                        . "on event_show_venue.event_show_venue_id ="
                        . " event_show_tickets_type.event_show_venue_id JOIN "
                        . "event_shows on event_shows.event_show_id = "
                        . "event_show_venue.event_show_id JOIN events on events.eventID"
                        . "  = event_shows.eventID JOIN  user on user.profile_id = "
                        . "profile.profile_id JOIN ticket_types on ticket_types.typeId"
                        . " = event_show_tickets_type.typeId $searchQuery $sorting";
            }

            $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Verify "
                    . "SQL::" . $sql);
            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_time Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket Types results ($stop_time Seconds)"
                        , 'record_count' => $result[0]['total_count'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewMpesaDepo
     * @return type
     */
    public function viewMpesaDepo() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $regex = '/"api_key":"[^"]*?"/';
        $string = (preg_replace($regex, '"api_key":***'
                        , json_encode($request->getJsonRawBody())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | viewMpesaDepo ::$string");

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $discount = isset($data->discount) ? $data->discount : 0;
        $hasRefunded = isset($data->hasRefunded) ? $data->hasRefunded : 0;
        $sort = isset($data->sort) ? $data->sort : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$offset) {
            $offset = 1;
        }
        if (!$limit) {
            $limit = $this->settings['RecordsLimit'];
        }
        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "mpesa_transaction.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'mpesa_transaction.id';
            $order = 'DESC';
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            $searchQuery = "WHERE  1 ";
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(mpesa_transaction.mpesa_time) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(mpesa_transaction.mpesa_time)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(mpesa_transaction.mpesa_time)>='$start'";
            }
            if ($eventID != null) {

                if ($eventID == 306) {
                    $eventID = 252;
                }


                $checkEvents = Events::findFirst([
                            "eventID =:eventID: ",
                            "bind" => [
                                "eventID" => $eventID,],]);
                if (!$checkEvents) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event ID']);
                }
                $hasMultipleShow = $checkEvents->hasMultipleShow;
                if ($hasMultipleShow == 1) {
                    $searchQuery .= " AND event_shows.eventID ='$eventID'";
                    $searchQuery .= " AND event_profile_tickets.isShowTicket =1";
                } else {
                    $searchQuery .= " AND event_tickets_type.eventId ='$eventID'";
                }
            }
            if ($discount == 1) {
                $searchQuery .= " AND event_profile_tickets.discount > 0";
            }
            if ($hasRefunded == 1) {
                $searchQuery .= " AND event_profile_tickets.hasRefunded = 1";
            }
            $searchQuery .= " AND (select date(created) from events where eventID = '$eventID') <= date(mpesa_transaction.mpesa_time)";
            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);

            $sql = "select transaction.transaction_id, mpesa_transaction.mpesa_code,"
                    . "mpesa_transaction.mpesa_msisdn,mpesa_transaction.mpesa_sender,"
                    . "mpesa_transaction.mpesa_amount,mpesa_transaction.mpesa_account,event_profile_tickets.discount,"
                    . "mpesa_transaction.mpesa_time,(select count(distinct mpesa_transaction.id)"
                    . " from mpesa_transaction join transaction on mpesa_transaction.id"
                    . " = transaction.reference_id join transaction_initiated on "
                    . "transaction_initiated.transaction_id = transaction.extra_data->>'$.account_number'"
                    . " join event_profile_tickets on event_profile_tickets.reference_id = transaction_initiated.reference_id"
                    . " join event_tickets_type on event_tickets_type.event_ticket_id = event_profile_tickets.event_ticket_id"
                    . " join ticket_types on ticket_types.typeId = event_tickets_type.typeId $searchQuery)"
                    . " as total_count from mpesa_transaction join transaction on mpesa_transaction.id = "
                    . "transaction.reference_id join transaction_initiated on transaction_initiated.transaction_id"
                    . " = transaction.extra_data->>'$.account_number' join event_profile_tickets on"
                    . " event_profile_tickets.reference_id = transaction_initiated.reference_id "
                    . "join event_tickets_type on event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id join ticket_types on "
                    . "ticket_types.typeId = event_tickets_type.typeId   $searchQuery group by mpesa_transaction.id $sorting";

            if ($hasMultipleShow == 1) {
                $sql = "select transaction.transaction_id, mpesa_transaction.mpesa_code,
                    mpesa_transaction.mpesa_msisdn,mpesa_transaction.mpesa_sender,event_profile_tickets.discount,
                    mpesa_transaction.mpesa_amount,mpesa_transaction.mpesa_account,
                    mpesa_transaction.mpesa_time,(select count(mpesa_transaction.id)
                     from mpesa_transaction join transaction on mpesa_transaction.id
                     = transaction.reference_id join transaction_initiated on 
                     transaction_initiated.transaction_id = transaction.extra_data->>'$.account_number'
                     join event_profile_tickets on event_profile_tickets.reference_id = transaction_initiated.reference_id
                     join event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = event_profile_tickets.event_ticket_id
		     join event_show_venue on event_show_venue.event_show_venue_id = event_show_tickets_type.event_show_venue_id
		     join event_shows on event_shows.event_show_id = event_show_venue.event_show_id
                     join ticket_types on ticket_types.typeId = event_show_tickets_type.typeId $searchQuery)
                     as total_count from mpesa_transaction join transaction on mpesa_transaction.id
                     = transaction.reference_id join transaction_initiated on 
                    transaction_initiated.transaction_id = transaction.extra_data->>'$.account_number'
                     join event_profile_tickets on event_profile_tickets.reference_id = transaction_initiated.reference_id
                     join event_show_tickets_type on event_show_tickets_type.event_ticket_show_id = event_profile_tickets.event_ticket_id
		     join event_show_venue on event_show_venue.event_show_venue_id = event_show_tickets_type.event_show_venue_id
		     join event_shows on event_shows.event_show_id = event_show_venue.event_show_id
                     join ticket_types on ticket_types.typeId = event_show_tickets_type.typeId $searchQuery
			group by mpesa_transaction.id $sorting";
            }

            $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | viewMpesaDepo SQL ::$sql");

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_time Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket Types results ($stop_time Seconds)"
                        , 'record_count' => $result[0]['total_count'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * editTicketTypes
     * @return type
     * @throws Exception
     */
    public function editTicketTypes() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $regex = '/"api_key":"[^"]*?"/';
        $string = (preg_replace($regex, '"api_key":***'
                        , json_encode($request->getJsonRawBody())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | editTicketTypes ::$string");
        $token = isset($data->api_key) ? $data->api_key : null;
        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        $quantity = isset($data->quantity) ? $data->quantity : null;
        $complimentQyt = isset($data->complimentQyt) ? $data->complimentQyt : null;
        $groupTicket = isset($data->groupTicket) ? $data->groupTicket : null;
        $ticketType = isset($data->ticketType) ? $data->ticketType : null;
        $caption = isset($data->captions) ? $data->captions : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($event_ticket_id) || $this->checkForMySQLKeywords($quantity) || $this->checkForMySQLKeywords($complimentQyt) || $this->checkForMySQLKeywords($groupTicket) || $this->checkForMySQLKeywords($ticketType) || $this->checkForMySQLKeywords($caption)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$event_ticket_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        $checkEventType = EventTicketsType::findFirst(["event_ticket_id =:event_ticket_id:",
                    "bind" => ["event_ticket_id" => $event_ticket_id]]);

        if (!$checkEventType) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Ticket Type']);
        }

        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if ((!in_array($auth_response['role_id'], [1, 2, 6]))) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $checkEventType->setTransaction($dbTrxn);
                if ($quantity != null) {
                    $checkEventType->total_tickets = $quantity;
                }
                if ($complimentQyt != null) {
                    $checkEventType->total_complimentary = $complimentQyt;
                }
                if ($groupTicket != null) {
                    $checkEventType->group_ticket_quantity = $groupTicket;
                }
                if ($checkEventType->save() === false) {
                    $errors = [];
                    $messages = $checkEventType->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Update Event Tickets Type failed " . json_encode($errors));
                }
                $dbTrxn->commit();
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Ok'
                                , ['code' => 200
                            , 'message' => "Successfully Update Ticket"
                            . " Types results ($stop_time Seconds)"]);
            } catch (Exception $ex) {
                throw $ex;
            }
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * generateEventReports
     * @return bool
     * @throws Exception
     */
    public function generateEventReports() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $eventID = $this->request->get('eventID') ? $this->request->get('eventID') : '';
            $mode = $this->request->get('mode') ? $this->request->get('mode') : "C2B";
            $export = (int) $this->request->get('export') ? $this->request->get('export') : 0;

            if (
                    $this->checkForMySQLKeywords($currentPage) ||
                    $this->checkForMySQLKeywords($perPage) ||
                    $this->checkForMySQLKeywords($filter) ||
                    $this->checkForMySQLKeywords($start) ||
                    $this->checkForMySQLKeywords($end)) {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }

            if (!$eventID || !$mode) {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }

            $checkEvents = Events::findFirst([
                        "eventID =:eventID:  ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }

            if ($perPage == 100) {
                $perPage = 1000;
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }
            $groupByDay = " ";
            if ($checkEvents->hasMultipleShow == 0) {
                if ($mode == "CARD") {
                    $selectQuery = "select profile.msisdn, user.email, "
                            . "profile_attribute.first_name, "
                            . "profile_attribute.last_name, "
                            . "profile_attribute.surname,ticket_types.ticket_type,"
                            . "count(*) as totalTickets, "
                            . "event_profile_tickets.barcode,"
                            . "py.TransID,et.amount, "
                            . "py.created ";
                    $countQuery = " select count(py.id) as totalTransactions  ";
                    $baseQuery = " from dpo_transaction as py join "
                            . "dpo_transaction_initiated on py.TransactionToken = "
                            . "dpo_transaction_initiated.TransactionToken join "
                            . "transaction_initiated on dpo_transaction_initiated.transaction_id"
                            . " = transaction_initiated.transaction_id join "
                            . "event_profile_tickets on event_profile_tickets.reference_id"
                            . "  = transaction_initiated.reference_id join "
                            . "event_tickets_type as et on event_profile_tickets.event_ticket_id"
                            . " = et.event_ticket_id join "
                            . "profile on event_profile_tickets.profile_id  = "
                            . "profile.profile_id JOIN `user` on `profile`.profile_id"
                            . " = user.profile_id join profile_attribute on "
                            . "`profile`.profile_id = profile_attribute.profile_id"
                            . " join ticket_types on et.typeId  = "
                            . "ticket_types.typeId ";

                    $groupByDay = "py.TransID";
                } elseif ($mode == "B2B") {
                    $selectQuery = "select profile.msisdn,user.email, "
                            . "profile_attribute.first_name, "
                            . "profile_attribute.last_name, "
                            . "profile_attribute.surname, "
                            . "ticket_types.ticket_type,count(*) as totalTickets,"
                            . "event_profile_tickets.barcode,"
                            . "py.mpesa_code,"
                            . "py.mpesa_amount, "
                            . "py.created ";
                    $countQuery = " select count(py.id) as totalTransactions  ";
                    $baseQuery = "from mpesa_transaction_b2b as py join transaction_initiated"
                            . " on transaction_initiated.transaction_id  = "
                            . "SUBSTRING(py.mpesa_account,4) "
                            . "join event_profile_tickets on event_profile_tickets.reference_id "
                            . " = transaction_initiated.reference_id  "
                            . "join event_tickets_type as et on event_profile_tickets.event_ticket_id "
                            . "= et.event_ticket_id join profile"
                            . " on event_profile_tickets.profile_id  = profile.profile_id  "
                            . "JOIN `user` on `profile`.profile_id = user.profile_id "
                            . "join profile_attribute on `profile`.profile_id = "
                            . "profile_attribute.profile_id join ticket_types on "
                            . "et.typeId  = ticket_types.typeId ";

                    $groupByDay = "py.mpesa_code";
                } else {
                    $selectQuery = "select profile.msisdn,user.email,"
                            . " profile_attribute.first_name, "
                            . "profile_attribute.last_name, "
                            . "profile_attribute.surname,ticket_types.ticket_type, "
                            . "event_profile_tickets.barcode, "
                            . "py.mpesa_code,count(*) as totalTickets,"
                            . " py.mpesa_amount,py.created"
                            . " ";
                    $countQuery = " select count(py.id) as totalTransactions  ";
                    $baseQuery = "from event_profile_tickets join event_tickets_type as et "
                            . " on event_profile_tickets.event_ticket_id = "
                            . "et.event_ticket_id  join "
                            . "event_profile_tickets_state on "
                            . "event_profile_tickets.event_profile_ticket_id = "
                            . "event_profile_tickets_state.event_profile_ticket_id "
                            . "join transaction_initiated on event_profile_tickets.reference_id "
                            . " = transaction_initiated.reference_id join "
                            . "transaction on  transaction.description  = "
                            . "transaction_initiated.reference_id join  mpesa_transaction as py "
                            . "on transaction.reference_id  = py.id"
                            . " join profile on event_profile_tickets.profile_id "
                            . " =  profile.profile_id  LEFT JOIN `user` on "
                            . "`profile`.profile_id = user.profile_id LEFT join "
                            . "profile_attribute on `profile`.profile_id = "
                            . "profile_attribute.profile_id join ticket_types on "
                            . "et.typeId  = ticket_types.typeId ";
                    $groupByDay = "py.mpesa_code";
                }
            } else {
                if ($mode == "CARD") {
                    $selectQuery = "select profile.msisdn,ticket_types.ticket_type,"
                            . "CONCAT(et.eventName,' - ',event_shows.show) as eventName, event_profile_tickets.barcode,"
                            . "py.TransID, count(*) as totalTickets,"
                            . "(event_show_tickets_type.amount * count(*)) AS "
                            . "totalAmount,event_shows.show,py.created ";
                    $countQuery = " select count(py.id) as totalTransactions  ";
                    $baseQuery = " from event_profile_tickets join event_show_tickets_type"
                            . " on event_profile_tickets.event_ticket_id = "
                            . "event_show_tickets_type.event_ticket_show_id "
                            . "join event_show_venue on event_show_tickets_type.event_show_venue_id"
                            . " = event_show_venue.event_show_venue_id join "
                            . "event_shows on event_show_venue.event_show_id = "
                            . "event_shows.event_show_id  join event_profile_tickets_state "
                            . "on event_profile_tickets.event_profile_ticket_id "
                            . "= event_profile_tickets_state.event_profile_ticket_id"
                            . " join transaction_initiated on event_profile_tickets.reference_id"
                            . "  = transaction_initiated.reference_id join "
                            . "transaction on transaction_initiated.transaction_id "
                            . "= transaction.extra_data->>'$.account_number' join "
                            . " dpo_transaction_initiated ON transaction_initiated.transaction_id "
                            . "= dpo_transaction_initiated.transaction_id  JOIN"
                            . " dpo_transaction as py ON "
                            . "dpo_transaction_initiated.TransactionToken =  "
                            . "py.TransactionToken join profile on event_profile_tickets.profile_id "
                            . " =  profile.profile_id join ticket_types on "
                            . "event_show_tickets_type.typeId  = ticket_types.typeId "
                            . "join events as et on event_shows.eventID= et.eventID ";
                    $groupByDay = "py.TransID";
                } elseif ($mode == "B2B") {
                    $selectQuery = "select profile.msisdn,ticket_types.ticket_type,"
                            . "CONCAT(et.eventName,' - ',event_shows.show) as eventName, event_profile_tickets.barcode,"
                            . "py.mpesa_code, count(*) as totalTickets,"
                            . "py.mpesa_amount,event_shows.show,"
                            . "py.created ";
                    $countQuery = " select count(py.id) as totalTransactions  ";
                    $baseQuery = "from event_profile_tickets join event_show_tickets_type"
                            . " on event_profile_tickets.event_ticket_id = "
                            . "event_show_tickets_type.event_ticket_show_id join"
                            . " event_show_venue on event_show_tickets_type.event_show_venue_id "
                            . "= event_show_venue.event_show_venue_id join "
                            . "event_shows on event_show_venue.event_show_id = "
                            . "event_shows.event_show_id  join event_profile_tickets_state"
                            . " on event_profile_tickets.event_profile_ticket_id "
                            . "= event_profile_tickets_state.event_profile_ticket_id "
                            . "join transaction_initiated on event_profile_tickets.reference_id"
                            . "  = transaction_initiated.reference_id join "
                            . "transaction on transaction_initiated.transaction_id"
                            . " = transaction.extra_data->>'$.account_number' "
                            . "join  mpesa_transaction_b2b as py on transaction.reference_id"
                            . "  = py.id join profile on event_profile_tickets.profile_id  "
                            . "=  profile.profile_id join ticket_types on event_show_tickets_type.typeId"
                            . "  = ticket_types.typeId join events as et on "
                            . "event_shows.eventID = et.eventID ";
                    $groupByDay = "py.mpesa_code";
                } else {
                    $selectQuery = "select profile.msisdn,ticket_types.ticket_type,"
                            . "CONCAT(et.eventName,' - ',event_shows.show) as eventName, event_profile_tickets.barcode,"
                            . "user.email,profile_attribute.first_name,"
                            . "profile_attribute.last_name, py.mpesa_code,"
                            . " count(*) as totalTickets,py.mpesa_amount,"
                            . "event_shows.show,py.created  ";

                    $countQuery = " select count(py.id) as totalTransactions  ";

                    $baseQuery = " from event_profile_tickets join "
                            . "event_show_tickets_type on event_profile_tickets.event_ticket_id"
                            . " = event_show_tickets_type.event_ticket_show_id"
                            . " join event_show_venue on event_show_tickets_type.event_show_venue_id"
                            . " = event_show_venue.event_show_venue_id join"
                            . " event_shows on event_show_venue.event_show_id = "
                            . "event_shows.event_show_id  join event_profile_tickets_state"
                            . " on event_profile_tickets.event_profile_ticket_id "
                            . "= event_profile_tickets_state.event_profile_ticket_id"
                            . " join transaction_initiated on event_profile_tickets.reference_id"
                            . "  = transaction_initiated.reference_id join "
                            . "transaction on transaction_initiated.transaction_id "
                            . "= transaction.extra_data->>'$.account_number' "
                            . "join  mpesa_transaction as py on transaction.reference_id  "
                            . "= py.id join profile on "
                            . "event_profile_tickets.profile_id  =  profile.profile_id"
                            . " join user on user.profile_id =`profile`.profile_id"
                            . " join profile_attribute on `profile`.profile_id = "
                            . "profile_attribute.profile_id join ticket_types on "
                            . "event_show_tickets_type.typeId  = ticket_types.typeId "
                            . "join events as et on event_shows.eventID= et.eventID ";
                    $groupByDay = "py.mpesa_code";
                }
            }


            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end],
                'eventID' => $eventID
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['user.email', 'ticket_types.ticket_type', 'profile.msisdn'];

                    $valueString = "";
                    foreach ($searchColumns as $searchColumn) {
                        $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                    }
                    $valueString = chop($valueString, " ||");
                    if ($valueString) {
                        $valueString = "(" . $valueString;
                        $valueString .= ") AND ";
                    }
                    $whereQuery .= $valueString;
                } else if ($key == "eventID") {
                    $whereQuery .= "et.eventID = " . $value;
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(py.created) BETWEEN '$value[0]' AND '$value[1]'";
                        $whereQuery .= $valueString;
                    }
                } else {
                    $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                    $whereQuery .= $valueString;
                }
            }

            if ($whereQuery) {
                $whereQuery = chop($whereQuery, " AND ");
            }


            $whereQuery = $whereQuery ? "WHERE $whereQuery AND  DATE(py.created) >= DATE('" . $checkEvents->created . "')" : " WHERE DATE(py.created) >= DATE('" . $checkEvents->created . "')";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by " . $groupByDay;

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Query:" . $selectQuery);

            if ($export == 0) {
                $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
                $selectQuery .= $queryBuilder;
            }

            $count = $this->rawSelect($countQuery, [], 'db2');
            $matches = $this->rawSelect($selectQuery, [], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalTransactions'];
            $data->matches = $matches;

            $result = ['success' => 'matches', 'data' => $data];

            if (isset($result['success'])) {
                $dataObject = $result['data'];
                $totalItems = $dataObject->totalMatches;
                $data = $dataObject->matches;

                $from = ($currentPage - 1) * $perPage + 1;

                $rem = (int) ($totalItems % $perPage);
                if ($rem !== 0) {
                    $lastPage = (int) ($totalItems / $perPage) + 1;
                } else {
                    $lastPage = (int) ($totalItems / $perPage);
                }

                if ($currentPage == $lastPage) {
                    $to = $totalItems;
                } else {
                    $to = ($from + $perPage) - 1;
                }

                $next_url = $currentPage + 1;

                $prev_url = null;

                if ($currentPage >= 2) {
                    $n = $currentPage - 1;
                    $prev_url = "https://api.v1.interactive.madfun.com/v1/api/dashboard/generate/report?eventID=" . $eventID . "&view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "https://api.v1.interactive.madfun.com/v1/api/dashboard/generate/report?eventID=" . $eventID . "&view?page=$next_url";
                $pagination->prev_page_url = $prev_url;
                $links->pagination = $pagination;

                $response = [
                    'links' => $links,
                    'data' => $data
                ];
            } else {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = $perPage;
                $pagination->current_page = $currentPage;
                $pagination->last_page = 0;
                $pagination->from = 0;
                $pagination->to = 0;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;

                $response = [
                    'links' => $links,
                    'data' => [],
                ];
            }

            $this->successVueTable($response);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error. ' . $ex->getMessage());

            $links = new stdClass();
            $pagination = new stdClass();
            $pagination->total = 0;
            $pagination->per_page = 10;
            $pagination->current_page = 1;
            $pagination->last_page = 1;
            $pagination->from = 1;
            $pagination->to = 1;
            $pagination->next_page_url = null;
            $pagination->prev_page_url = null;
            $links->pagination = $pagination;
            $response = [
                'links' => $links,
                'data' => [],
            ];
            $this->successVueTable($response);
        }
    }

    /**
     * generateComplimentaryReports
     * @return bool
     * @throws Exception
     */
    public function generateComplimentaryReports() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $eventID = $this->request->get('eventID') ? $this->request->get('eventID') : '';
            $mode = $this->request->get('mode') ? $this->request->get('mode') : "C2B";
            $export = (int) $this->request->get('export') ? $this->request->get('export') : 0;

            if (
                    $this->checkForMySQLKeywords($currentPage) ||
                    $this->checkForMySQLKeywords($perPage) ||
                    $this->checkForMySQLKeywords($filter) ||
                    $this->checkForMySQLKeywords($start) ||
                    $this->checkForMySQLKeywords($end)) {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }

            if (!$eventID) {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID:  ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }
            if ($perPage == 100) {
                $perPage = 1000;
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }
            $groupByDay = " ";
            if ($checkEvents->hasMultipleShow == 0) {
                $selectQuery = "SELECT `profile`.msisdn, profile_attribute.first_name,"
                        . " profile_attribute.last_name, profile_attribute.surname,"
                        . " event_profile_tickets.barcode, event_profile_tickets.alias_name,`user`.email,ticket_types.ticket_type,"
                        . " event_profile_tickets.created ";

                $countQuery = " select count(event_profile_tickets.event_profile_ticket_id)"
                        . " as totalProfileComplimentary  ";

                $baseQuery = "FROM event_profile_tickets JOIN `profile` on"
                        . " event_profile_tickets.profile_id = profile.profile_id "
                        . "LEFT JOIN user on profile.profile_id = user.profile_id JOIN "
                        . "profile_attribute on profile_attribute.profile_id = "
                        . "profile.profile_id JOIN event_tickets_type as et ON "
                        . "event_profile_tickets.event_ticket_id = et.event_ticket_id "
                        . "JOIN ticket_types on et.typeId = "
                        . "ticket_types.typeId ";
            } else {

                $selectQuery = "SELECT `profile`.msisdn, profile_attribute.first_name,
                    profile_attribute.last_name, profile_attribute.surname, 
                    event_profile_tickets.barcode, et.`show`, 
                    `user`.email, ticket_types.ticket_type,
                    event_profile_tickets.created ";

                $countQuery = " select count(event_profile_tickets.event_profile_ticket_id)"
                        . " as totalProfileComplimentary  ";

                $baseQuery = "FROM event_profile_tickets JOIN `profile` on "
                        . "event_profile_tickets.profile_id = profile.profile_id"
                        . " LEFT JOIN user on profile.profile_id = user.profile_id "
                        . "JOIN profile_attribute on profile_attribute.profile_id "
                        . "= profile.profile_id join event_show_tickets_type "
                        . "on event_profile_tickets.event_ticket_id  = "
                        . "event_show_tickets_type.event_ticket_show_id JOIN "
                        . "event_show_venue on event_show_tickets_type.event_show_venue_id"
                        . " = event_show_venue.event_show_venue_id join "
                        . "event_shows as et on event_show_venue.event_show_id = "
                        . "et.event_show_id join ticket_types on "
                        . "event_show_tickets_type.typeId = ticket_types.typeId ";
            }

            //WHERE event_profile_tickets.isComplimentary = 1 and event_tickets_type.eventId = 266;
            //WHERE event_profile_tickets.isComplimentary = 1 AND event_shows.eventID=299


            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end],
                'eventID' => $eventID
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['user.email', 'ticket_types.ticket_type', 'profile.msisdn'];

                    $valueString = "";
                    foreach ($searchColumns as $searchColumn) {
                        $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                    }
                    $valueString = chop($valueString, " ||");
                    if ($valueString) {
                        $valueString = "(" . $valueString;
                        $valueString .= ") AND ";
                    }
                    $whereQuery .= $valueString;
                } else if ($key == "eventID") {
                    $whereQuery .= "et.eventID = " . $value;
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(event_profile_tickets.created) BETWEEN '$value[0]' AND '$value[1]'";
                        $whereQuery .= $valueString;
                    }
                } else {
                    $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                    $whereQuery .= $valueString;
                }
            }

            if ($whereQuery) {
                $whereQuery = chop($whereQuery, " AND ");
            }


            $whereQuery = $whereQuery ? "WHERE $whereQuery AND event_profile_tickets.isComplimentary =1 " : " WHERE event_profile_tickets.isComplimentary =1 ";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            if ($checkEvents->hasMultipleShow == 1) {
                $whereQuery .= " AND event_profile_tickets.isShowTicket = 1";
            }
            $selectQuery = $selectQuery . $baseQuery . $whereQuery;

            if ($export == 0) {
                $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
                $selectQuery .= $queryBuilder;
            }

            $count = $this->rawSelect($countQuery, [], 'db2');
            $matches = $this->rawSelect($selectQuery, [], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalProfileComplimentary'];
            $data->matches = $matches;

            $result = ['success' => 'matches', 'data' => $data];

            if (isset($result['success'])) {
                $dataObject = $result['data'];
                $totalItems = $dataObject->totalMatches;
                $data = $dataObject->matches;

                $from = ($currentPage - 1) * $perPage + 1;

                $rem = (int) ($totalItems % $perPage);
                if ($rem !== 0) {
                    $lastPage = (int) ($totalItems / $perPage) + 1;
                } else {
                    $lastPage = (int) ($totalItems / $perPage);
                }

                if ($currentPage == $lastPage) {
                    $to = $totalItems;
                } else {
                    $to = ($from + $perPage) - 1;
                }

                $next_url = $currentPage + 1;

                $prev_url = null;

                if ($currentPage >= 2) {
                    $n = $currentPage - 1;
                    $prev_url = "https://api.v1.interactive.madfun.com/v1/api/dashboard/complimentary/report?eventID=" . $eventID . "&view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "https://api.v1.interactive.madfun.com/v1/api/dashboard/complimentary/report?eventID=" . $eventID . "&view?page=$next_url";
                $pagination->prev_page_url = $prev_url;
                $links->pagination = $pagination;

                $response = [
                    'links' => $links,
                    'data' => $data
                ];
            } else {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = $perPage;
                $pagination->current_page = $currentPage;
                $pagination->last_page = 0;
                $pagination->from = 0;
                $pagination->to = 0;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;

                $response = [
                    'links' => $links,
                    'data' => [],
                ];
            }

            $this->successVueTable($response);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error. ' . $ex->getMessage());

            $links = new stdClass();
            $pagination = new stdClass();
            $pagination->total = 0;
            $pagination->per_page = 10;
            $pagination->current_page = 1;
            $pagination->last_page = 1;
            $pagination->from = 1;
            $pagination->to = 1;
            $pagination->next_page_url = null;
            $pagination->prev_page_url = null;
            $links->pagination = $pagination;
            $response = [
                'links' => $links,
                'data' => [],
            ];
            $this->successVueTable($response);
        }
    }

    /**
     * generateRefundReports
     * @return bool
     * @throws Exception
     */
    public function generateRefundReports() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $eventID = $this->request->get('eventID') ? $this->request->get('eventID') : '';
            $export = (int) $this->request->get('export') ? $this->request->get('export') : 0;

            if (
                    $this->checkForMySQLKeywords($currentPage) ||
                    $this->checkForMySQLKeywords($perPage) ||
                    $this->checkForMySQLKeywords($filter) ||
                    $this->checkForMySQLKeywords($start) ||
                    $this->checkForMySQLKeywords($end)) {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }

            if (!$eventID) {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }
            $checkEvents = Events::findFirst([
                        "eventID =:eventID:  ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = 10;
                $pagination->current_page = 1;
                $pagination->last_page = 1;
                $pagination->from = 1;
                $pagination->to = 1;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;
                $response = [
                    'links' => $links,
                    'message' => 'Mandtory Fields Required',
                    'data' => [],
                ];
                $this->successVueTable($response);
                return true;
            }
            if ($perPage == 100) {
                $perPage = 1000;
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }
            $groupByDay = " ";
            if ($checkEvents->hasMultipleShow == 0) {
                $selectQuery = "SELECT `profile`.msisdn, profile_attribute.first_name,"
                        . " profile_attribute.last_name, profile_attribute.surname,"
                        . " event_profile_tickets.barcode, event_profile_tickets.alias_name,"
                        . "`user`.email,ticket_types.ticket_type,event_profile_tickets.refundPurpose,"
                        . " event_profile_tickets.created ";

                $countQuery = " select count(event_profile_tickets.event_profile_ticket_id)"
                        . " as totalProfileRefund  ";

                $baseQuery = "FROM event_profile_tickets JOIN `profile` on"
                        . " event_profile_tickets.profile_id = profile.profile_id "
                        . "LEFT JOIN user on profile.profile_id = user.profile_id JOIN "
                        . "profile_attribute on profile_attribute.profile_id = "
                        . "profile.profile_id JOIN event_tickets_type as et ON "
                        . "event_profile_tickets.event_ticket_id = et.event_ticket_id "
                        . "JOIN ticket_types on et.typeId = "
                        . "ticket_types.typeId ";
            } else {

                $selectQuery = "SELECT `profile`.msisdn, profile_attribute.first_name,
                    profile_attribute.last_name, profile_attribute.surname, 
                    event_profile_tickets.barcode, et.`show`, 
                    `user`.email, ticket_types.ticket_type,
                    event_profile_tickets.created ";

                $countQuery = " select count(event_profile_tickets.event_profile_ticket_id)"
                        . " as totalProfileComplimentary  ";

                $baseQuery = "FROM event_profile_tickets JOIN `profile` on "
                        . "event_profile_tickets.profile_id = profile.profile_id"
                        . " LEFT JOIN user on profile.profile_id = user.profile_id "
                        . "JOIN profile_attribute on profile_attribute.profile_id "
                        . "= profile.profile_id join event_show_tickets_type "
                        . "on event_profile_tickets.event_ticket_id  = "
                        . "event_show_tickets_type.event_ticket_show_id JOIN "
                        . "event_show_venue on event_show_tickets_type.event_show_venue_id"
                        . " = event_show_venue.event_show_venue_id join "
                        . "event_shows as et on event_show_venue.event_show_id = "
                        . "et.event_show_id join ticket_types on "
                        . "event_show_tickets_type.typeId = ticket_types.typeId";
            }

            //WHERE event_profile_tickets.isComplimentary = 1 and event_tickets_type.eventId = 266;
            //WHERE event_profile_tickets.isComplimentary = 1 AND event_shows.eventID=299


            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end],
                'eventID' => $eventID
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['user.email', 'ticket_types.ticket_type', 'profile.msisdn'];

                    $valueString = "";
                    foreach ($searchColumns as $searchColumn) {
                        $valueString .= $value ? "" . $searchColumn . " REGEXP '" . $value . "' ||" : "";
                    }
                    $valueString = chop($valueString, " ||");
                    if ($valueString) {
                        $valueString = "(" . $valueString;
                        $valueString .= ") AND ";
                    }
                    $whereQuery .= $valueString;
                } else if ($key == "eventID") {

                    $whereQuery .= "et.eventID = " . $value;
                } else if ($key == "isRefunded") {
                    $whereQuery .= "event_profile_tickets.hasRefunded = " . $value;
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(event_profile_tickets.created) BETWEEN '$value[0]' AND '$value[1]'";
                        $whereQuery .= $valueString;
                    }
                } else {
                    $valueString = $value ? "" . $key . "=" . $value . " AND " : "";
                    $whereQuery .= $valueString;
                }
            }

            if ($whereQuery) {
                $whereQuery = chop($whereQuery, " AND ");
            }


            $whereQuery = $whereQuery ? "WHERE $whereQuery AND event_profile_tickets.hasRefunded =1 " : " WHERE event_profile_tickets.hasRefunded =1";

//            if ($checkEvents->hasMultipleShow == 0) {
//                $whereQuery .= " AND event_profile_tickets.isShowTicket = 1";
//            }
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery;

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Select Query Request:" . $selectQuery);

            if ($export == 0) {
                $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
                $selectQuery .= $queryBuilder;
            }

            $count = $this->rawSelect($countQuery, [], 'db2');
            $matches = $this->rawSelect($selectQuery, [], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalProfileRefund'];
            $data->matches = $matches;

            $result = ['success' => 'matches', 'data' => $data];

            if (isset($result['success'])) {
                $dataObject = $result['data'];
                $totalItems = $dataObject->totalMatches;
                $data = $dataObject->matches;

                $from = ($currentPage - 1) * $perPage + 1;

                $rem = (int) ($totalItems % $perPage);
                if ($rem !== 0) {
                    $lastPage = (int) ($totalItems / $perPage) + 1;
                } else {
                    $lastPage = (int) ($totalItems / $perPage);
                }

                if ($currentPage == $lastPage) {
                    $to = $totalItems;
                } else {
                    $to = ($from + $perPage) - 1;
                }

                $next_url = $currentPage + 1;

                $prev_url = null;

                if ($currentPage >= 2) {
                    $n = $currentPage - 1;
                    $prev_url = "https://api.v1.interactive.madfun.com/v1/api/dashboard/complimentary/report?eventID=" . $eventID . "&view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "https://api.v1.interactive.madfun.com/v1/api/dashboard/complimentary/report?eventID=" . $eventID . "&view?page=$next_url";
                $pagination->prev_page_url = $prev_url;
                $links->pagination = $pagination;

                $response = [
                    'links' => $links,
                    'data' => $data
                ];
            } else {
                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = 0;
                $pagination->per_page = $perPage;
                $pagination->current_page = $currentPage;
                $pagination->last_page = 0;
                $pagination->from = 0;
                $pagination->to = 0;
                $pagination->next_page_url = null;
                $pagination->prev_page_url = null;
                $links->pagination = $pagination;

                $response = [
                    'links' => $links,
                    'data' => [],
                ];
            }

            $this->successVueTable($response);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error. ' . $ex->getMessage());

            $links = new stdClass();
            $pagination = new stdClass();
            $pagination->total = 0;
            $pagination->per_page = 10;
            $pagination->current_page = 1;
            $pagination->last_page = 1;
            $pagination->from = 1;
            $pagination->to = 1;
            $pagination->next_page_url = null;
            $pagination->prev_page_url = null;
            $links->pagination = $pagination;
            $response = [
                'links' => $links,
                'data' => [],
            ];
            $this->successVueTable($response);
        }
    }

    /**
     * upgradeTicketFunction
     * @return type
     * @throws Exception
     */
    public function upgradeTicketFunction() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Upgrade Ticket Action:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $isShowTicket = isset($data->isShowTicket) ? $data->isShowTicket : 0;
        $barcode = isset($data->barcode) ? $data->barcode : null;
        $mpesa_receipt = isset($data->mpesa_receipt) ? $data->mpesa_receipt : null;

        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($barcode) ||
                $this->checkForMySQLKeywords($event_ticket_id) ||
                $this->checkForMySQLKeywords($mpesa_receipt)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$barcode || !$event_ticket_id || !$mpesa_receipt) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
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

            $results = $this->rawSelect("SELECT event_profile_tickets.event_profile_ticket_id,"
                    . "event_profile_tickets.profile_id,"
                    . " event_profile_tickets.event_ticket_id,event_profile_tickets_state.upgrade_status, "
                    . "event_profile_tickets.profile_id, event_profile_tickets.reference_id,"
                    . " event_profile_tickets_state.`status` FROM "
                    . "event_profile_tickets JOIN event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id "
                    . "WHERE event_profile_tickets.barcode = :barcode",
                    [':barcode' => $barcode]);
            if (!$results) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                . 'profile ticket not found', [
                            'code' => 402
                            , 'message' => "'The event profile ticket not found. "
                            . "Kindly check the barcode ", 'data' => []
                            , 'record_count' => 0], true);
            }
            $result = $results[0];

            if ($result['status'] != 1) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'Failed. No Initial Payment was made', [
                            'code' => 402
                            , 'message' => "No Initial Payment was made.", 'data' => []
                            , 'record_count' => 0], true);
            }

            if ($result['upgrade_status'] == 1) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'Ticket has already been upgraded', [
                            'code' => 402
                            , 'message' => "Ticket has already been upgraded", 'data' => []
                            , 'record_count' => 0], true);
            }

            if ($mpesa_receipt != "DPO") {
                $checkMpesa = $this->rawSelect("SELECT mpesa_code, mpesa_amount "
                        . "FROM mpesa_transaction WHERE"
                        . " mpesa_transaction.mpesa_code = :mpesa_code",
                        [':mpesa_code' => $mpesa_receipt]);

                if (!$checkMpesa) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Payment not found', [
                                'code' => 402
                                , 'message' => "Payment not found. Kindly check "
                                . "payment reference:  $mpesa_receipt", 'data' => []
                                , 'record_count' => 0], true);
                }
                $amountPaid = $checkMpesa[0]['mpesa_amount'];
            }




            $checProfile = Profile::findFirst([
                        "profile_id =:profile_id: ",
                        "bind" => [
                            "profile_id" => $result['profile_id']],]);

            $msisdn = $checProfile->msisdn;

            $eventID = "";
            if ($isShowTicket == 1) {
                $currentTicketType = EventShowTicketsType::findFirst([
                            "event_ticket_show_id =:event_ticket_show_id: ",
                            "bind" => [
                                "event_ticket_show_id" => $result['event_ticket_id']],]);
                if (!$currentTicketType) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                $currentShowVenue = EventShowVenue::findFirst([
                            "event_show_venue_id =:event_show_venue_id: ",
                            "bind" => [
                                "event_show_venue_id" =>
                                $currentTicketType->event_show_venue_id],]);

                if (!$currentShowVenue) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }


                $currentShow = EventShows::findFirst([
                            "event_show_id =:event_show_id: ",
                            "bind" => [
                                "event_show_id" =>
                                $currentShowVenue->event_show_id],]);

                if (!$currentShow) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                $eventID = $currentShow->eventID;

                $upgradeTicketType = EventShowTicketsType::findFirst([
                            "event_ticket_show_id =:event_ticket_show_id: ",
                            "bind" => [
                                "event_ticket_show_id" => $event_ticket_id],]);

                if (!$upgradeTicketType) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                if ($upgradeTicketType->event_show_venue_id != $currentTicketType->event_show_venue_id) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket has to been on the same event show', [
                                'code' => 402
                                , 'message' => "Ticket has to been on "
                                . "the same event show"], true);
                }
            } else {
                $currentTicketType = EventTicketsType::findFirst([
                            "event_ticket_id =:event_ticket_id: ",
                            "bind" => [
                                "event_ticket_id" => $result['event_ticket_id']],]);
                if (!$currentTicketType) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                $upgradeTicketType = EventTicketsType::findFirst([
                            "event_ticket_id =:event_ticket_id: ",
                            "bind" => [
                                "event_ticket_id" => $event_ticket_id],]);

                if (!$upgradeTicketType) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                if ($upgradeTicketType->eventId != $currentTicketType->eventId) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Failed Ticket has to belong to same Event', [
                                'code' => 402
                                , 'message' => "Failed Ticket has to"
                                . " belong to same Event"], true);
                }

                $eventID = $currentTicketType->eventId;
            }

            if ($currentTicketType->amount >= $upgradeTicketType->amount) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                . 'Failed', [
                            'code' => 402
                            , 'message' => "Kindly select ticket with higher price for upgrade"], true);
            }

            if ($mpesa_receipt != "DPO") {
                if ($upgradeTicketType->amount > ($currentTicketType->amount + $amountPaid)) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Failed', [
                                'code' => 402
                                , 'message' => "Insufficient Amount"], true);
                }
            }



            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event does not exist', [
                            'code' => 404
                            , 'message' => "The event does not exist ", 'data' => []
                            , 'record_count' => 0], true);
            }

            if ($checkEvents->status != 1) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event not active', [
                            'code' => 403
                            , 'message' => "The event not active ", 'data' => []
                            , 'record_count' => 0], true);
            }

            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $currentTicketType->setTransaction($dbTrxn);
                $currentTicketType->ticket_purchased = $currentTicketType->ticket_purchased - 1;
                $currentTicketType->updated = $this->now();
                if ($currentTicketType->save() === false) {
                    $errors = [];
                    $messages = $currentTicketType->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Update Tickets Type failed " . json_encode($errors));
                }

                $upgradeTicketType->setTransaction($dbTrxn);
                $upgradeTicketType->ticket_purchased = $upgradeTicketType->ticket_purchased + 1;
                $upgradeTicketType->updated = $this->now();
                if ($upgradeTicketType->save() === false) {
                    $errors = [];
                    $messages = $upgradeTicketType->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Update Tickets Type failed " . json_encode($errors));
                }


                $t = time();
                $QRCode = rand(1000000, 99999999999999) . "" . $t;
                $barCode = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $QRCode . '&choe=UTF-8';

                $sqlUpdate = "UPDATE event_profile_tickets SET "
                        . "barcode=:barcode,barcodeURL=:barcodeURL, event_ticket_id=:event_ticket_id "
                        . "WHERE event_profile_ticket_id = :event_profile_ticket_id ";
                $selectParamsUpdate = [
                    ':event_profile_ticket_id' => $result['event_profile_ticket_id'],
                    ':barcode' => $QRCode,
                    ':event_ticket_id' => $event_ticket_id,
                    ':barcodeURL' => $barCode
                ];

                $this->rawUpdateWithParams($sqlUpdate, $selectParamsUpdate);

                $this->rawUpdateWithParams("UPDATE event_profile_tickets_state "
                        . "set upgrade_status=:upgrade_status WHERE"
                        . " event_profile_ticket_id = "
                        . ":event_profile_ticket_id LIMIT 1",
                        [':upgrade_status' => 1,
                            ':event_profile_ticket_id' =>
                            $result['event_profile_ticket_id']]);

                $dbTrxn->commit();

                $sms = "Hello, Your " . $checkEvents->eventName . " ticket "
                        . "has been upgraded successful.\nCode: " . $QRCode . ".\nView your ticket from "
                        . $this->settings['TicketBaseURL'] . "?evtk=" . $QRCode . "."
                        . " Madfun! For Queries call "
                        . "" . $this->settings['Helpline'];

                $paramsSMS = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $sms,
                    "profile_id" => Profiling::Profile($msisdn),
                    "created_by" => 'UPGRADETICKET_' . $auth_response['user_id'],
                    "is_bulk" => false,
                    "link_id" => ""];

                $message = new Messaging();
                $message->LogOutbox($paramsSMS);

                $responseData = [
                    "Message" => "Ticket has been upgraded Successful",
                    "code" => 200
                ];
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket has been upgraded Successful"
                                , $responseData);
            } catch (Exception $ex) {
                throw $ex;
            }
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function upgradeListTicketType() {

        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Upgrade Ticket Action:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $isShowTicket = isset($data->isShowTicket) ? $data->isShowTicket : 0;
        $barcode = isset($data->barcode) ? $data->barcode : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($isShowTicket) ||
                $this->checkForMySQLKeywords($barcode)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$barcode) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
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

            $sql = "SELECT event_profile_tickets.event_profile_ticket_id,"
                    . "event_profile_tickets.profile_id,"
                    . " event_profile_tickets.event_ticket_id,event_profile_tickets_state.upgrade_status, "
                    . "event_profile_tickets.profile_id, event_profile_tickets.reference_id,"
                    . " event_profile_tickets_state.`status` FROM "
                    . "event_profile_tickets JOIN event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id "
                    . "WHERE event_profile_tickets.barcode = $barcode";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Upgrade Ticket Action:" . $sql);

            $results = $this->rawSelect($sql);

            if (!$results) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422
                            , 'message' => 'Tickets not found']);
            }
            $result = $results[0];
            if ($isShowTicket == 1) {
                $currentTicketType = EventShowTicketsType::findFirst([
                            "event_ticket_show_id =:event_ticket_show_id: ",
                            "bind" => [
                                "event_ticket_show_id" => $result['event_ticket_id']],]);
                if (!$currentTicketType) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                $sql = "SELECT ticket_types.ticket_type, "
                        . "event_show_tickets_type.event_ticket_show_id as "
                        . "event_ticket_id, event_show_tickets_type.amount "
                        . "from event_show_tickets_type JOIN ticket_types ON "
                        . "event_show_tickets_type.typeId =ticket_types.typeId   "
                        . "WHERE event_show_tickets_type.`status` = :status and"
                        . " event_show_venue_id=:Id";

                $paramsSQL = [
                    ':status' => 1,
                    ':Id' => $currentTicketType->event_show_venue_id
                ];
            } else {
                $currentTicketType = EventTicketsType::findFirst([
                            "event_ticket_id =:event_ticket_id: ",
                            "bind" => [
                                "event_ticket_id" => $result['event_ticket_id']],]);
                if (!$currentTicketType) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'The event '
                                    . 'Ticket not configured', [
                                'code' => 402
                                , 'message' => "Ticket not configured. Contact "
                                . "System Admin"], true);
                }

                $sql = "SELECT ticket_types.ticket_type, event_tickets_type.event_ticket_id,"
                        . " event_tickets_type.amount FROM event_tickets_type "
                        . "JOIN ticket_types on event_tickets_type.typeId = ticket_types.typeId "
                        . "WHERE event_tickets_type.eventId = :Id and "
                        . "event_tickets_type.`status` = :status";

                $paramsSQL = [
                    ':status' => 1,
                    ':Id' => $currentTicketType->eventId
                ];
            }

            $resultTickets = $this->rawSelect($sql, $paramsSQL);
            if (!$resultTickets) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Active '
                                . 'Tickets Not Found', [
                            'code' => 404
                            , 'message' => "No Active Tickets"
                            . " Avaliable for Upgrade"], true);
            }

            $responseData = [
                "code" => 200,
                "Message" => "Tickets Found",
                "data" => $resultTickets
            ];
            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Ticket has been upgraded Successful"
                            , $responseData);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
}
