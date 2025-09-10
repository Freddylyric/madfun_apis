<?php

/**
 * Description of EntriesController
 *
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class EventsController extends ControllerBase {

    protected $payload;
    protected $infologger;
    protected $errorlogger;
    protected $moduleName;

    function onConstruct() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
    }

    /**
     * 
     * @return type
     */
    public function addCategory() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | addCategory:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $category = isset($data->category) ? $data->category : null;
        $description = isset($data->description) ? $data->description : null;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($category) ||
                $this->checkForMySQLKeywords($description) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$category) {
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
            $checkQuery = $this->selectQuery("SELECT  * FROM event_category WHERE"
                    . " category REGEXP :category", [':category' => $category]);
            if ($checkQuery) {
                $data_array = [
                    'code' => 202,
                    'message' => 'Record exist'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Add Event Category Failed"
                                , $data_array, true);
            }
            $sql = "INSERT INTO event_category (category,desciption,created)"
                    . " VALUES (:category,:desciption,:created)";
            $params = [
                ':category' => $category,
                ':desciption' => $description,
                ':created' => $this->now()
            ];
            $result = $this->rawInsert($sql, $params);

            if (!$result) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Add Event Category successful"
                                , [
                            'code' => 202,
                            'message' => 'Failed insert record'], true);
            }
            $data_array = [
                'code' => 200,
                'message' => 'Successful insert record'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Add Event Category Success"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
    
     /**
     * 
     * @return type
     */
    public function editCategory() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | addCategory:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $categoryID = isset($data->categoryID) ? $data->categoryID : null;
        $category = isset($data->category) ? $data->category : null;
        $description = isset($data->description) ? $data->description : null;
        $status = isset($data->status) ? $data->status : 1;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($categoryID) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$category) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
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
            $checkEventCategory = EventCategory::findFirst([
                        "id =:id: ",
                        "bind" => [
                            "id" => $categoryID]]);
            if (!$checkEventCategory) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Category']);
            }
            
            $checkEventCategory->setTransaction($dbTrxn);
            $checkEventCategory->updated = $this->now();
            if ($category) {
                $checkEventCategory->category = $category;
            }
            if ($description) {
                $checkEventCategory->desciption = $description;
            }
            
          
            $checkEventCategory->status = $status;
            
            
             if ($checkEventCategory->save() === false) {
                $errors = [];
                $messages = $checkEventCategory->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Update Event Category failed " . json_encode($errors));
            }
            $dbTrxn->commit();
           
            $data_array = [
                'code' => 200,
                'message' => 'Successful updated record'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Update Event Category Success"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * 
     * @return type
     */
    public function addEventElementForm() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | addCategory:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $element_id = isset($data->element_id) ? $data->element_id : null;
        $element_tag = isset($data->element_tag) ? $data->element_tag : null;
        $element_label = isset($data->element_label) ? $data->element_label : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($element_id) ||
                $this->checkForMySQLKeywords($element_tag) ||
                $this->checkForMySQLKeywords($element_label) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$eventID || !$element_id || !$element_tag || !$element_label || !$source) {
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

            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event does not exist', [
                            'code' => 402
                            , 'message' => "The event does not exist ", 'data' => []
                            , 'record_count' => 0], true);
            }

            $checkElementForm = $this->selectQuery("SELECT  * FROM form_element WHERE"
                    . " element_id = :element_id", [':element_id' => $element_id]);
            if (!$checkElementForm) {
                $data_array = [
                    'code' => 404,
                    'message' => 'Element form not found'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Element form not found"
                                , $data_array, true);
            }


            $checkQuery = $this->selectQuery("SELECT  * FROM event_form_elements WHERE"
                    . " eventID=:eventID and element_id=:element_id AND "
                    . "element_label REGEXP :element_label",
                    [':element_label' => $element_label, ':eventID' => $eventID,
                        ':element_id' => $element_id]);
            if ($checkQuery) {
                $data_array = [
                    'code' => 202,
                    'message' => 'Record exist'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Add Event Category Failed"
                                , $data_array, true);
            }


            $sql = "INSERT INTO event_form_elements (eventID,element_id,element_tag,element_label,created)"
                    . " VALUES (:eventID,:element_id,:element_tag,:element_label,:created)";
            $params = [
                ':eventID' => $eventID,
                ':element_id' => $element_id,
                ':element_tag' => $element_tag,
                ':element_label' => $element_label,
                ':created' => $this->now()
            ];
            $result = $this->rawInsert($sql, $params);

            if (!$result) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Add Event Category successful"
                                , [
                            'code' => 202,
                            'message' => 'Failed insert record'], true);
            }
            $data_array = [
                'code' => 200,
                'message' => 'Successful insert record'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Add Event Form Success"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * 
     * @return type
     */
    public function viewEventElementForm() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | viewCategory Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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
            $searchQuery = " WHERE (event_form_elements.eventID = '$eventID' || events.eventTag= '$eventID')";

            $sql = "select event_form_elements.form_element_id,"
                    . "form_element.form_element, event_form_elements.element_tag,"
                    . "event_form_elements.element_label,(select "
                    . "count(event_form_elements.form_element_id) from event_form_elements "
                    . "join form_element on event_form_elements.element_id = "
                    . "form_element.element_id join events on "
                    . "event_form_elements.eventID = events.eventID$searchQuery) as"
                    . " total from event_form_elements "
                    . "join form_element on event_form_elements.element_id = "
                    . "form_element.element_id join events on "
                    . "event_form_elements.eventID = events.eventID $searchQuery";
            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_end = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Event Form Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_end Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stop_end = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Event Form results ($stop_end Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
    
    /**
     * viewBanks
     * @return type
     * @throws Exception
     */
    public function viewDashboardCategories() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Banks Request:" . json_encode($this->request->get()));
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $role = $this->request->get('role') ? $this->request->get('role') : '1,2,3,4,6';

            if ($this->checkForMySQLKeywords($currentPage) || $this->checkForMySQLKeywords($perPage) || $this->checkForMySQLKeywords($filter) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($end) || $this->checkForMySQLKeywords($role)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "SELECT event_category.id, event_category.category,event_category.desciption, event_category.`status`, "
                    . "event_category.created ";

            $countQuery = "select count(event_category.id) as totalevent_category  ";

            $baseQuery = "from event_category ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['event_category.category', 'event_category.desciption'];

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
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(event_category.created) BETWEEN '$value[0]' AND '$value[1]'";
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


            $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . "";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalevent_category'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/mpesa/transaction?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/mpesa/transaction?page=$next_url";
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
                            , 'Internal Server Error.');

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
     * 
     * @return type
     */
    public function viewCategory() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | viewCategory Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $category = isset($data->category) ? $data->category : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($category) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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
            $searchQuery = " WHERE status = 1";
            if ($category != null) {
                $searchQuery .= " AND category REGEXP '$category' ";
            }

            $sql = "SELECT  * FROM event_category $searchQuery";
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
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * getEventUSSD
     * @return type
     * @throws Exception
     */
    public function getEventUSSD() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Get Event USSDt:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $accessPoint = isset($data->accessPoint) ? $data->accessPoint : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($accessPoint) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$accessPoint) {
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
            }
            $searchQuery = "WHERE events.status = 1 AND "
                    . "events.`ussd_access_point` = '$accessPoint'";

            $sql = "select ussd_access_point,eventID,isPublic,eventName,company,venue,isPublic,status,isFree,posterURL,bannerURL,currency,min_price,start_date,revenueShare,"
                    . "end_date,created,( select IFNULL(sum(event_tickets_type.ticket_redeemed),0) "
                    . "from event_tickets_type where eventId =events.eventID ) as totalRedemmed, aboutEvent"
                    . " from events $searchQuery";
            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Records for Events ( $stop Seconds)"
                            , 'data' => []], true);
            }

            $stop = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events results ($stop Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result[0]]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * addTicketTypeAction
     * @return type
     * @throws Exception
     */
    public function addTicketTypeAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $ticket_type = isset($data->ticket_type) ? $data->ticket_type : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($ticket_type)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$ticket_type) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }

            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $checkQuery = $this->selectQuery("SELECT  * FROM ticket_types WHERE"
                    . " ticket_type REGEXP :ticket_type", [':ticket_type' => $ticket_type]);
            if ($checkQuery) {
                $data_array = [
                    'code' => 202,
                    'message' => 'Record exist'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket type Action Failed"
                                , $data_array, true);
            }

            $sql = "INSERT INTO ticket_types (ticket_type,created) VALUES (:ticket_type,:created)";
            $params = [
                ':ticket_type' => $ticket_type,
                ':created' => $this->now()
            ];
            $result = $this->rawInsert($sql, $params);
            $data_array = [
                'code' => 202,
                'message' => 'Failed insert record'];

            if (!$result) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket type Action successful"
                                , $data_array, true);
            }
            $data_array = [
                'code' => 200,
                'message' => 'Successful insert record'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Ticket type Action Failed"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * resendEmailTicketToAll
     * @return type
     */
    public function resendEmailTicketToAll() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $profile_id = isset($data->profile_id) ? $data->profile_id : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($profile_id) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source || !$eventID) {
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
            $whereArray = [
                'event_tickets_type.eventId' => $eventID, 'event_profile_tickets_state.status' => 1];

            $searchQuery = $this->whereQuery($whereArray, "");

            if ($profile_id != null) {
                $searchQuery .= " AND user.profile_id = '$profile_id' ";
            }

            $sql = "select  profile_attribute.first_name, profile_attribute.surname,"
                    . " profile_attribute.last_name, user.email,event_profile_tickets.barcodeURL,"
                    . " event_profile_tickets.barcode, events.eventName,events.isFree, "
                    . "event_tickets_type.amount, ticket_types.ticket_type "
                    . "from event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id  "
                    . "join event_tickets_type on event_tickets_type.event_ticket_id "
                    . "=event_profile_tickets.event_ticket_id join "
                    . "profile_attribute on profile_attribute.profile_id = "
                    . "event_profile_tickets.profile_id  join events on "
                    . "events.eventID = event_tickets_type.eventId  join "
                    . "ticket_types on ticket_types.typeId = event_tickets_type.typeId "
                    . "join user on user.profile_id = profile_attribute.profile_id  "
                    . " $searchQuery ";

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Purchase Found for event ID: ' . $eventID, [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            foreach ($result as $data) {
                $paramsEmail = [
                    "name" => $data['first_name'] . " "
                    . "" . $data['surname'] . " " . $data['last_name'],
                    "eventName" => $data['eventName'],
                    "eventAmount" => $data['amount'],
                    'eventType' => $data['ticket_type'],
                    'QRcodeURL' => $data['barcodeURL'],
                    'QRcode' => $data['barcode']
                ];
                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $data['email'],
                    "cc" => "",
                    "subject" => "Ticket for Event: " . $data['eventName'],
                    "content" => "Ticket information",
                    "extrac" => $paramsEmail
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailTickets Response::" . json_encode($mailResponse));
            }
            $stop = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Request processed successful ($stop Seconds)"]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewTicketTypeAction
     * @return type
     */
    public function viewTicketTypeAction() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($start) ||
                $this->checkForMySQLKeywords($stop) ||
                $this->checkForMySQLKeywords($limit) ||
                $this->checkForMySQLKeywords($offset) ||
                $this->checkForMySQLKeywords($sort) ||
                $this->checkForMySQLKeywords($source)) {
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
            $sort = "event_tickets_type.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_tickets_type.amount';
            $order = 'ASC';
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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


            $searchQuery = " WHERE (event_tickets_type.eventId ='$eventID' ||  events.eventTag='$eventID')";

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_tickets_type.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_tickets_type.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_tickets_type.created)>='$start'";
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = "select event_tickets_type.currency, event_tickets_type.numbers,event_tickets_type.hasOption,event_tickets_type.event_ticket_id,event_tickets_type.discount,ticket_types.typeId,ticket_types.ticket_type,ifnull(ticket_types.caption,'') as caption,"
                    . "events.eventName,events.isFree,events.start_date,events.venue,event_tickets_type.amount,event_tickets_type.currency,"
                    . "event_tickets_type.status,event_tickets_type.total_tickets,(event_tickets_type.total_tickets "
                    . "- event_tickets_type.ticket_purchased) as avaliableTickets,"
                    . "event_tickets_type.maxCap,event_tickets_type.isPublic,"
                    . "event_tickets_type.description, event_tickets_type.created,"
                    . "(select count(event_tickets_type.event_ticket_id) from"
                    . " event_tickets_type join ticket_types on event_tickets_type.typeId"
                    . "  = ticket_types.typeId join events on"
                    . " events.eventID  = event_tickets_type.eventId "
                    . "$searchQuery) as total,(SELECT GROUP_CONCAT(event_tickets_type_option.option)"
                    . " FROM event_tickets_type_option WHERE "
                    . "event_tickets_type_option.event_ticket_id = event_tickets_type.event_ticket_id )"
                    . " as options FROM event_tickets_type "
                    . "join ticket_types on event_tickets_type.typeId  = "
                    . "ticket_types.typeId join events on events.eventID "
                    . " = event_tickets_type.eventId  $searchQuery $sorting";
            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stopTime = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stopTime Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stopTime = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket Types results ($stopTime Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewEventsDashboard
     */
    public function viewTicketTypeDashboardAction() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';

            if (
                    $this->checkForMySQLKeywords($currentPage) ||
                    $this->checkForMySQLKeywords($perPage) ||
                    $this->checkForMySQLKeywords($filter) ||
                    $this->checkForMySQLKeywords($start) ||
                    $this->checkForMySQLKeywords($end)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
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

            $selectQuery = "SELECT ticket_types.typeId,ticket_types.ticket_type,"
                    . "ticket_types.status,ticket_types.created";

            $countQuery = " select count(ticket_types.typeId) as totalEvents  ";

            $baseQuery = " from ticket_types ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['ticket_types.ticket_type'];

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
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(ticket_types.created) BETWEEN '$value[0]' AND '$value[1]'";
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


            $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by ticket_types.typeId";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery, [], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalEvents'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/ticket/type/dashboard/view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/ticket/type/dashboard/view?page=$next_url";
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
                            , 'Internal Server Error.');

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
     * createEvent
     * @return type
     */
    public function createEvent() {
        $start_time = $this->getMicrotime();
        $request = new Request();

        $regex = '/"token":"[^"]*?"/';
        $string = (preg_replace($regex, '"api_key":***', json_encode($this->request->getPost())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                . " | Request:" . $string . " TicketTypes: " . json_decode($request->getPost('ticketTypes')));

        $token = $request->getPost('api_key');
        $eventName = $request->getPost('eventName');
        $company = $request->getPost('company');
        $eventType = $request->getPost('eventType');
        $venue = $request->getPost('venue');
        $ageLimit = $request->getPost('ageLimit');
        $start_date_app = $request->getPost('start_date');
        $description = $request->getPost('description');
        $currency = $request->getPost('currency');
        $revenueShare = $request->getPost('revenueShare');
        $isPublic = $request->getPost('isPublic');
        $target = $request->getPost('target');
        $categoryID = $request->getPost('categoryID');
        $end_date_app = $request->getPost('end_date');
        $hasMultpleShow = $request->getPost('hasMultpleShow');
        $showData = json_decode($request->getPost('showData'));
        $ticketTypes = json_decode($request->getPost('ticketTypes'));

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventName) ||
                $this->checkForMySQLKeywords($company) ||
                $this->checkForMySQLKeywords($eventType) ||
                $this->checkForMySQLKeywords($venue) ||
                $this->checkForMySQLKeywords($ageLimit) ||
                $this->checkForMySQLKeywords($start_date_app) ||
                $this->checkForMySQLKeywords($description) ||
                $this->checkForMySQLKeywords($currency) ||
                $this->checkForMySQLKeywords($revenueShare) ||
                $this->checkForMySQLKeywords($isPublic) ||
                $this->checkForMySQLKeywords($target) ||
                $this->checkForMySQLKeywords($categoryID) ||
                $this->checkForMySQLKeywords($end_date_app) ||
                $this->checkForMySQLKeywords($hasMultpleShow) ||
                $this->checkForMySQLKeywords($showData) ||
                $this->checkForMySQLKeywords($ticketTypes)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$eventName || !$company || !$start_date_app || !$end_date_app) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if ($hasMultpleShow == 1 && !$showData) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if ($hasMultpleShow != 1 && !$ticketTypes) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$request->hasFiles()) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$revenueShare) {
            $revenueShare = $this->settings['revenueShare'];
        }
        $start_date_app_1 = strtotime($start_date_app);
        $end_date_app_1 = strtotime($end_date_app);
        $start_date = date('Y-m-d H:i:s', $start_date_app_1);
        $end_date = date('Y-m-d H:i:s', $end_date_app_1);
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $files = $request->getUploadedFiles();
            $file = isset($files[0]) ? $files[0] : false;
            $maxFileSize = 10 * 1024 * 1024;
            $allowedExtensions = array("image/jpg", "image/jpeg", "image/png", "image/gif");
            if ($file->getSize() > $maxFileSize) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Service Request Error'
                                , ['code' => 400
                            , 'message' => "Upload Request Failed. File"
                            . " has to be less than 10MB"]);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mtype = finfo_file($finfo, $file->getTempName());
//            if (!in_array($mtype, $allowedExtensions)) {
//                finfo_close($finfo);
//
//                return $this->BadRequest(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
//                                , 'Service Request Error'
//                                , ['code' => 400
//                            , 'message' => "Failed the file has to be JPG, JPEG, PNG or GIF"]);
//            }
            $checkEvents = Events::findFirst([
                        "company =:company: AND eventName=:eventName: AND "
                        . "start_date=:start_date: ",
                        "bind" => [
                            "company" => $company, 'eventName' => $eventName,
                            "start_date" => $start_date],]);

            if ($checkEvents) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'Event already created.', [
                            'code' => 402
                            , 'message' => "The event already created", 'data' => []
                            , 'record_count' => 1], true);
            }

            $posterURL = Tickets::uploadFileToAwsFromRequest($this->settings['aws']['temp_location'],
                            $request->getUploadedFiles(),
                            str_replace(" ", "_", $eventName));

            $events = new Events();
            $events->setTransaction($dbTrxn);
            $events->company = $company;
            $events->venue = $venue;
            $events->ageLimit = $ageLimit;
            $events->eventName = $eventName;
            $events->category_id = $categoryID;
            $events->target = $target;
            $events->status = 4;
            if ($isPublic != 1) {
                $events->isPublic = 0;
            }
            $events->posterURL = $posterURL;
            $events->aboutEvent = $description;
            $events->currency = $currency;
            $events->revenueShare = $revenueShare;
            $events->eventType = $eventType;
            $events->start_date = $start_date;
            $events->end_date = $end_date;
            $events->hasMultipleShow = $hasMultpleShow;
            $events->created = $this->now();
            if ($events->save() === false) {
                $errors = [];
                $messages = $events->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Create Events failed " . json_encode($errors));
            }


            if ($auth_response['userRole'] == 5) {
                $checkUser = User::findFirst(['user_id=:user_id:'
                            , 'bind' => ['user_id' => $auth_response['user_id']]]);
                if ($checkUser) {
                    $checkUser->setTransaction($dbTrxn);
                    $checkUser->role_id = 6;
                    $checkUser->updated = $this->now();
                    if ($checkUser->save() === false) {
                        $errors = [];
                        $messages = $checkUser->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Update Users failed. Reason" . json_encode($errors));
                    }
                }

                $checkClients = Clients::findFirst(['client_name=:client_name:'
                            , 'bind' => ['client_name' => $company]]);
                if (!$checkClients) {
                    $clients = new Clients();
                    $clients->setTransaction($dbTrxn);
                    $clients->client_name = $company;
                    $clients->description = "Event Orginazer for Client " . $company;
                    $clients->created_by = $auth_response['user_id'];
                    $clients->created_at = $this->now();
                    if ($clients->save() === false) {
                        $errors = [];
                        $messages = $clients->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create Clients failed. Reason" . json_encode($errors));
                    }

                    $userClientMap = new UserClientMap;
                    $userClientMap->setTransaction($dbTrxn);
                    $userClientMap->client_id = $clients->client_id;
                    $userClientMap->user_id = $auth_response['user_id'];
                    $userClientMap->created_by = $auth_response['user_id'];
                    $userClientMap->created_at = $this->now();
                    if ($userClientMap->save() === false) {
                        $errors = [];
                        $messages = $userClientMap->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create User Client Map failed. Reason" . json_encode($errors));
                    }
                } else {
                    $dbTrxn->rollback("The user exit" . json_encode($errors));
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'The organizer exist');
                }
            }

            $userEventMap = new UserEventMap();
            $userEventMap->setTransaction($dbTrxn);
            $userEventMap->eventID = $events->eventID;
            $userEventMap->user_mapId = $auth_response['user_mapId'];
            $userEventMap->created = $this->now();
            if ($userEventMap->save() === false) {
                $errors = [];
                $messages = $userEventMap->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Create User Event Map failed " . json_encode($errors));
            }
            $totalTicketsSet = 0;
            if ($hasMultpleShow == 1) {
                foreach ($showData as $show) {
                    $eventShow = new EventShows();
                    $eventShow->setTransaction($dbTrxn);
                    $eventShow->eventID = $events->eventID;
                    $eventShow->show = $show->show_name;
                    $eventShow->show_status = 1;
                    $eventShow->start_date = $show->start_date;
                    $eventShow->end_date = $show->end_date;
                    $eventShow->created = $this->now();
                    if ($eventShow->save() === false) {
                        $errors = [];
                        $messages = $eventShow->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Event Shows failed " . json_encode($errors));
                    }

                    foreach ($show->venue as $venueD) {
                        $eventShowVenue = new EventShowVenue();
                        $eventShowVenue->setTransaction($dbTrxn);
                        $eventShowVenue->venue = $venueD->venue;
                        $eventShowVenue->event_show_id = $eventShow->event_show_id;
                        $eventShowVenue->created = $this->now();
                        if ($eventShowVenue->save() === false) {
                            $errors = [];
                            $messages = $eventShowVenue->getMessages();
                            foreach ($messages as $message) {
                                $e["statusDescription"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                array_push($errors, $e);
                            }
                            $dbTrxn->rollback("Create Event Shows Venue failed " . json_encode($errors));
                        }
                        foreach ($venueD->tickets as $ticket) {
                            $eventShowTicketsType = new EventShowTicketsType();
                            $eventShowTicketsType->setTransaction($dbTrxn);
                            $eventShowTicketsType->typeId = $ticket->typeId;
                            $eventShowTicketsType->event_show_venue_id = $eventShowVenue->event_show_venue_id;
                            $eventShowTicketsType->amount = $ticket->amount;
                            $eventShowTicketsType->discount = $ticket->discount;
                            $eventShowTicketsType->group_ticket_quantity = $ticket->groupTickets;
                            $eventShowTicketsType->total_complimentary = $ticket->total_complimentary;
                            $eventShowTicketsType->total_tickets = $ticket->total_tickets;
                            $eventShowTicketsType->description = $ticket->description;
                            $eventShowTicketsType->status = 1;
                            $eventShowTicketsType->created = $this->now();
                            if ($eventShowTicketsType->save() === false) {
                                $errors = [];
                                $messages = $eventShowTicketsType->getMessages();
                                foreach ($messages as $message) {
                                    $e["statusDescription"] = $message->getMessage();
                                    $e["field"] = $message->getField();
                                    array_push($errors, $e);
                                }
                                $dbTrxn->rollback("Create Event Show Tickets Type failed " . json_encode($errors));
                            }
                        }
                    }
                }
            }

            if ($hasMultpleShow == 0) {
                foreach ($ticketTypes as $ticketType) {
                    $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            . " | Request:" . $ticketType->typeId . "  ");

                    $eventTicketType = new EventTicketsType();
                    $eventTicketType->setTransaction($dbTrxn);
                    $eventTicketType->typeId = $ticketType->typeId;
                    $eventTicketType->eventId = $events->eventID;
                    $eventTicketType->amount = $ticketType->amount;
                    $eventTicketType->discount = $ticketType->discount;
                    $eventTicketType->group_ticket_quantity = $ticketType->groupTickets;
                    $eventTicketType->total_complimentary = $ticketType->total_complimentary;
                    $eventTicketType->total_tickets = $ticketType->total_tickets;
                    $eventTicketType->description = $ticketType->description;
                    $eventTicketType->status = 1;
                    $eventTicketType->created = $this->now();
                    if ($eventTicketType->save() === false) {
                        $errors = [];
                        $messages = $eventTicketType->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Events failed " . json_encode($errors));
                    }

                    $totalTicketsSet = $totalTicketsSet + $ticketType->total_tickets;
                }
            }

            $eventStatistics = new EventsStatistics();
            $eventStatistics->setTransaction($dbTrxn);
            $eventStatistics->eventID = $events->eventID;
            $eventStatistics->total_tickets = $totalTicketsSet;
            $eventStatistics->created = $this->now();
            if ($eventStatistics->save() === false) {
                $errors = [];
                $messages = $eventStatistics->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Create Events Statistics failed " . json_encode($errors));
            }

            $dbTrxn->commit();
            $data_array = [
                'code' => 200,
                'eventID' => $events->eventID,
                'message' => 'Event Created successful'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Event Action Successfully"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * uploadImage
     * @return type
     */
    public function uploadImage() {
        $start_time = $this->getMicrotime();
        $request = new Request();

        $regex = '/"token":"[^"]*?"/';
        $string = (preg_replace($regex, '"api_key":***', json_encode($this->request->getPost())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                . " | Request:" . $string . " TicketTypes: " . json_decode($request->getPost('ticketTypes')));

        $token = $request->getPost('api_key');
        $eventName = $request->getPost('eventName');
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventName)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$eventName) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $files = $request->getUploadedFiles();
            $file = isset($files[0]) ? $files[0] : false;
            $maxFileSize = 10 * 1024 * 1024;
            $allowedExtensions = array("image/jpg", "image/jpeg", "image/png", "image/gif");
            if ($file->getSize() > $maxFileSize) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Service Request Error'
                                , ['code' => 400
                            , 'message' => "Upload Request Failed. File"
                            . " has to be less than 10MB"]);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mtype = finfo_file($finfo, $file->getTempName());

            $posterURL = Tickets::uploadFileToAwsFromRequest($this->settings['aws']['temp_location'],
                            $request->getUploadedFiles(),
                            str_replace(" ", "_", $eventName));

            return $this->success(__LINE__ . ":" . __CLASS__, 'Event already created.', [
                        'code' => 200
                        , 'message' => "pOSTER uPLOAD sUCCESSFUL", 'data' => []
                        , 'URL' => $posterURL]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * addEvents
     * @return type
     * @throws Exception
     */
    public function addEvents() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventName = isset($data->eventName) ? $data->eventName : NULL;
        $company = isset($data->company) ? $data->company : NULL;
        $eventType = isset($data->eventType) ? $data->eventType : NULL;
        $venue = isset($data->venue) ? $data->venue : NULL;
        $ageLimit = isset($data->ageLimit) ? $data->ageLimit : 18;
        $start_date_app = isset($data->start_date) ? $data->start_date : NULL;
        $end_date_app = isset($data->end_date) ? $data->end_date : NULL;
        $posterURL = isset($data->posterURL) ? $data->posterURL : NULL;
        $bannerURL = isset($data->bannerURL) ? $data->bannerURL : NULL;
        $description = isset($data->description) ? $data->description : NULL;
        $currency = isset($data->currency) ? $data->currency : "KES";
        $revenueShare = isset($data->revenueShare) ? $data->revenueShare : 8;
        $isPublic = isset($data->isPublic) ? $data->isPublic : 1;
        $target = isset($data->target) ? $data->target : 1000;
        $categoryID = isset($data->categoryID) ? $data->categoryID : 4;
        $hasMultipleShow = isset($data->hasMultipleShow) ? $data->hasMultipleShow : 0;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventName) ||
                $this->checkForMySQLKeywords($company) ||
                $this->checkForMySQLKeywords($eventType) ||
                $this->checkForMySQLKeywords($venue) ||
                $this->checkForMySQLKeywords($ageLimit) ||
                $this->checkForMySQLKeywords($start_date_app) ||
                $this->checkForMySQLKeywords($end_date_app) ||
                $this->checkForMySQLKeywords($posterURL) ||
                $this->checkForMySQLKeywords($bannerURL) ||
                $this->checkForMySQLKeywords($currency) ||
                $this->checkForMySQLKeywords($revenueShare) ||
                $this->checkForMySQLKeywords($isPublic) ||
                $this->checkForMySQLKeywords($target) ||
                $this->checkForMySQLKeywords($categoryID) ||
                $this->checkForMySQLKeywords($hasMultipleShow)
        ) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$eventName || !$company || !$start_date_app || !$end_date_app) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        $start_date_app_1 = strtotime($start_date_app);
        $end_date_app_1 = strtotime($end_date_app);
        $start_date = date('Y-m-d H:i:s', $start_date_app_1);
        $end_date = date('Y-m-d H:i:s', $end_date_app_1);
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $checkEvents = Events::findFirst([
                            "company =:company: AND eventName=:eventName: AND "
                            . "start_date=:start_date: ",
                            "bind" => [
                                "company" => $company, 'eventName' => $eventName,
                                "start_date" => $start_date],]);

                if ($checkEvents) {
                    return $this->success(__LINE__ . ":" . __CLASS__, 'Event already created.', [
                                'code' => 402
                                , 'message' => "The event already created", 'data' => []
                                , 'record_count' => 1], true);
                }

                $events = new Events();
                $events->setTransaction($dbTrxn);
                $events->company = $company;
                $events->venue = $venue;
                $events->ageLimit = $ageLimit;
                $events->eventName = $eventName;
                $events->bannerURL = $bannerURL;
                $events->category_id = $categoryID;
                $events->target = $target;
                $events->status = 4;
                if ($isPublic != 1) {
                    $events->isPublic = 0;
                }
                $events->hasMultipleShow = $hasMultipleShow;
                $events->posterURL = $posterURL;
                $events->aboutEvent = $description;
                $events->currency = $currency;
                $events->revenueShare = $revenueShare;
                $events->eventType = $eventType;
                $events->start_date = $start_date;
                $events->end_date = $end_date;
                $events->created = $this->now();
                if ($events->save() === false) {
                    $errors = [];
                    $messages = $events->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Events failed " . json_encode($errors));
                }

                $eventStatistics = new EventsStatistics();
                $eventStatistics->setTransaction($dbTrxn);
                $eventStatistics->eventID = $events->eventID;
                $eventStatistics->created = $this->now();
                if ($eventStatistics->save() === false) {
                    $errors = [];
                    $messages = $eventStatistics->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Events Statistics failed " . json_encode($errors));
                }
                if ($auth_response['userRole'] == 5) {
                    $checkUser = User::findFirst(['user_id=:user_id:'
                                , 'bind' => ['user_id' => $auth_response['user_id']]]);
                    if ($checkUser) {
                        $checkUser->setTransaction($dbTrxn);
                        $checkUser->role_id = 6;
                        $checkUser->updated = $this->now();
                        if ($checkUser->save() === false) {
                            $errors = [];
                            $messages = $checkUser->getMessages();
                            foreach ($messages as $message) {
                                $e["statusDescription"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                array_push($errors, $e);
                            }

                            $dbTrxn->rollback("Update Users failed. Reason" . json_encode($errors));
                        }
                    }

                    $checkClients = Clients::findFirst(['client_name=:client_name:'
                                , 'bind' => ['client_name' => $company]]);
                    if (!$checkClients) {
                        $clients = new Clients();
                        $clients->setTransaction($dbTrxn);
                        $clients->client_name = $company;
                        $clients->description = "Event Orginazer for Client " . $company;
                        $clients->created_by = $auth_response['user_id'];
                        $clients->created_at = $this->now();
                        if ($clients->save() === false) {
                            $errors = [];
                            $messages = $clients->getMessages();
                            foreach ($messages as $message) {
                                $e["statusDescription"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                array_push($errors, $e);
                            }

                            $dbTrxn->rollback("Create Clients failed. Reason" . json_encode($errors));
                        }

                        $userClientMap = new UserClientMap;
                        $userClientMap->setTransaction($dbTrxn);
                        $userClientMap->client_id = $clients->client_id;
                        $userClientMap->user_id = $auth_response['user_id'];
                        $userClientMap->created_by = $auth_response['user_id'];
                        $userClientMap->created_at = $this->now();
                        if ($userClientMap->save() === false) {
                            $errors = [];
                            $messages = $userClientMap->getMessages();
                            foreach ($messages as $message) {
                                $e["statusDescription"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                array_push($errors, $e);
                            }

                            $dbTrxn->rollback("Create User Client Map failed. Reason" . json_encode($errors));
                        }
                    } else {
                        $dbTrxn->rollback("The user exit" . json_encode($errors));
                        return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                        , 'The organizer exist');
                    }
                }

                $userEventMap = new UserEventMap();
                $userEventMap->setTransaction($dbTrxn);
                $userEventMap->eventID = $events->eventID;
                $userEventMap->user_mapId = $auth_response['user_mapId'];
                $userEventMap->created = $this->now();
                if ($userEventMap->save() === false) {
                    $errors = [];
                    $messages = $userEventMap->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create User Event Map failed " . json_encode($errors));
                }

                $dbTrxn->commit();
                $data_array = [
                    'code' => 200,
                    'eventID' => $events->eventID,
                    'message' => 'Event Created successful'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Action Successfully"
                                , $data_array);
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
     * updateEvents
     * @return type
     */
    public function updateEvents() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : NULL;
        $status = isset($data->status) ? $data->status : NULL;
        $start_date_app = isset($data->start_date) ? $data->start_date : NULL;
        $end_date_app = isset($data->end_date) ? $data->end_date : NULL;
        $posterURL = isset($data->posterURL) ? $data->posterURL : NULL;
        $bannerURL = isset($data->bannerURL) ? $data->bannerURL : NULL;
        $description = isset($data->description) ? $data->description : NULL;
        $venue = isset($data->venue) ? $data->venue : NULL;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($status) || $this->checkForMySQLKeywords($start_date_app) || $this->checkForMySQLKeywords($end_date_app) || $this->checkForMySQLKeywords($posterURL) || $this->checkForMySQLKeywords($bannerURL) || $this->checkForMySQLKeywords($description) || $this->checkForMySQLKeywords($venue)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }

            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event does not exist', [
                            'code' => 402
                            , 'message' => "The event does not exist ", 'data' => []
                            , 'record_count' => 0], true);
            }
            $checkEvents->setTransaction($dbTrxn);
            if ($status) {
                $checkEvents->status = $status;
            }
            if ($start_date_app) {
                $checkEvents->start_date = $start_date_app;
            }
            if ($venue) {
                $checkEvents->venue = $venue;
            }
            if ($end_date_app) {
                $checkEvents->end_date = $end_date_app;
            }

            if ($posterURL) {
                $checkEvents->posterURL = $posterURL;
            }
            if ($bannerURL) {
                $checkEvents->bannerURL = $bannerURL;
            }
            if ($description) {
                $checkEvents->aboutEvent = $description;
            }
            if ($venue) {
                $checkEvents->$venue = $venue;
            }
            $dbTrxn->commit();
            $data_array = [
                'code' => 200,
                'message' => 'Event Updated successful'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Event Action Successfully"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * addEventShows
     * @return type
     */
    public function addEventShows() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $show = isset($data->show) ? $data->show : null;
        $venue = isset($data->venue) ? $data->venue : null;
        $start_date_app = isset($data->start_date) ? $data->start_date : NULL;
        $end_date_app = isset($data->end_date) ? $data->end_date : NULL;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($show) || $this->checkForMySQLKeywords($start_date_app) || $this->checkForMySQLKeywords($end_date_app) || $this->checkForMySQLKeywords($venue)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$eventID || !$show) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        $start_date_app_1 = strtotime($start_date_app);
        $end_date_app_1 = strtotime($end_date_app);
        $start_date = date('Y-m-d H:i:s', $start_date_app_1);
        $end_date = date('Y-m-d H:i:s', $end_date_app_1);

        $venueList = explode(',', $venue);
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }

            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);

            if (!$checkEvents) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'The event does not exist', [
                            'code' => 402
                            , 'message' => "The event does not exist ", 'data' => []
                            , 'record_count' => 0], true);
            }

            if ($checkEvents->hasMultipleShow != 1) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Event has to be confirm for Multiple Shows. Contact Admin']);
            }

            $checkEventShows = EventShows::findFirst([
                        "eventID =:eventID: AND show=:show: AND start_date =:start_date: ",
                        "bind" => [
                            "eventID" => $eventID, 'show' => $show, "start_date" => $start_date]]);

            if ($checkEventShows) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'Event already created.', [
                            'code' => 402
                            , 'message' => "The event show already created", 'data' => $checkEventShows
                            , 'record_count' => 1], true);
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $eventShow = new EventShows();
                $eventShow->setTransaction($dbTrxn);
                $eventShow->eventID = $eventID;
                $eventShow->show = $show;
                $eventShow->show_status = 1;
                $eventShow->start_date = $start_date;
                $eventShow->end_date = $end_date;
                $eventShow->created = $this->now();
                if ($eventShow->save() === false) {
                    $errors = [];
                    $messages = $eventShow->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Event Shows failed " . json_encode($errors));
                }

                foreach ($venueList as $venueD) {
                    $eventShowVenue = new EventShowVenue();
                    $eventShowVenue->setTransaction($dbTrxn);
                    $eventShowVenue->venue = $venueD;
                    $eventShowVenue->event_show_id = $eventShow->event_show_id;
                    $eventShowVenue->created = $this->now();
                    if ($eventShowVenue->save() === false) {
                        $errors = [];
                        $messages = $eventShowVenue->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Event Shows Venue failed " . json_encode($errors));
                    }
                }

                $dbTrxn->commit();
                $data_array = [
                    'code' => 200,
                    'eventShowID' => $eventShow->event_show_id,
                    'message' => 'Event Created successful'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Action Successfully"
                                , $data_array);
            } catch (Exception $ex) {
                return $this->serverError(__LINE__ . ":" . __CLASS__
                                , 'Internal Server Error.');
            }
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewEventShows
     * @return type
     */
    public function viewEventShows() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $source = isset($data->source) ? $data->source : null;
        $showAvailable = isset($data->showAvailable) ? $data->showAvailable : 0;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($source) || $this->checkForMySQLKeywords($showAvailable)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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

            $searchQuery = " WHERE (event_shows.eventID = '$eventID' || events.eventTag= '$eventID')";

            if ($showAvailable == 1) {
                $searchQuery = "WHERE (event_shows.eventID = '$eventID' || events.eventTag= '$eventID') AND event_shows.show_status= 1 ";
            }
            // $searchQuery .= " AND date(event_shows.start_date) >= date(now()) ";
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_shows.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_shows.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_shows.created)>='$start'";
            }
            $sql = "select event_shows.event_show_id,event_shows.show, event_shows.show_status from "
                    . "event_shows join events  on events.eventID = event_shows.eventID $searchQuery";

            if ($showAvailable == 1) {
                $sql = "select event_shows.event_show_id,event_shows.show, event_shows.show_status from "
                        . "event_shows join events  on events.eventID = event_shows.eventID $searchQuery AND event_show_id in (select "
                        . "event_show_venue.event_show_id from event_show_venue"
                        . " join event_show_tickets_type on event_show_venue.event_show_venue_id"
                        . " = event_show_tickets_type.event_show_venue_id where "
                        . "event_show_tickets_type.total_tickets >  "
                        . "event_show_tickets_type.ticket_purchased) ";
            }

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Add Ticket Types Request:" . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stoptime = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stoptime Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stoptime = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket Types results ($stoptime Seconds)"
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewEventShowVenue
     * @return type
     */
    public function viewEventShowVenue() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Event Show Venue:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_show_id = isset($data->event_show_id) ? $data->event_show_id : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $source = isset($data->source) ? $data->source : null;
        $showAvailable = isset($data->showAvailable) ? $data->showAvailable : 0;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($event_show_id) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($source) || $this->checkForMySQLKeywords($showAvailable)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source || !$event_show_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
//        if(!is_numeric($event_show_id)){
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
//        }
//        if(!$this->validateDate($start) || $this->validateDate($stop)){
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
//        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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
            $whereArray = [
                'event_show_venue.event_show_id' => $event_show_id,
                'event_show_venue.status' => 1];

            $searchQuery = $this->whereQuery($whereArray, "");

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_show_venue.created) "
                        . "BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_show_venue.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_show_venue.created)>='$start'";
            }
            $sql = "select event_show_venue.venue, event_show_venue.event_show_venue_id,"
                    . "event_show_venue.event_show_id,"
                    . "event_show_venue.status from event_show_venue  $searchQuery";

            if ($showAvailable == 1) {
                $sql = "select event_show_venue.venue, event_show_venue.event_show_venue_id,"
                        . "event_show_venue.event_show_id,event_show_tickets_type.currency, "
                        . "event_show_venue.status from event_show_venue join"
                        . " event_show_tickets_type on event_show_venue.event_show_venue_id "
                        . "= event_show_tickets_type.event_show_venue_id $searchQuery"
                        . " AND event_show_tickets_type.total_tickets > "
                        . "event_show_tickets_type.ticket_purchased ";
            }



            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stoptime = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'Event Show Venue', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stoptime Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $sqlShow = "select * from event_shows where event_show_id = " . $result[0]['event_show_id'];
            $resultShow = $this->rawSelect($sqlShow);
            if (!$resultShow) {
                $stoptime = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'Event Show Venue', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stoptime Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stoptime = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Event Show Venue results ($stoptime Seconds)"
                        , 'record_count' => count($result), 'eventDateTime' => $resultShow[0]['start_date'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * 
     * @return type
     * @throws Exception
     */
    public function addEventVenueTicketTypes() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Event Venue Tickets Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $typeId = isset($data->typeId) ? $data->typeId : null;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $amount = isset($data->amount) ? $data->amount : null;
        $total_tickets = isset($data->total_tickets) ? $data->total_tickets : null;
        $discount = isset($data->discount) ? $data->discount : 0;
        $groupTickets = isset($data->groupTickets) ? $data->groupTickets : 1;
        $total_complimentary = isset($data->total_complimentary) ? $data->total_complimentary : 0;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($total_complimentary) || $this->checkForMySQLKeywords($groupTickets) || $this->checkForMySQLKeywords($discount) || $this->checkForMySQLKeywords($total_tickets) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($typeId) || $this->checkForMySQLKeywords($amount)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$typeId || !$event_show_venue_id || !$amount || !$total_tickets || !$groupTickets) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if(!is_numeric($event_show_venue_id)){
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if(!is_numeric($typeId)){
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
       
        if ($amount <= 0) {
            return $this->dataError(
                            __LINE__ . ":" . __CLASS__, 'Validation Error', ['code' => 422, 'message' => 'Invalid Amount']
            );
        }
        if ($total_tickets <= 0) {
            return $this->dataError(
                            __LINE__ . ":" . __CLASS__, 'Validation Error', ['code' => 422, 'message' => 'Invalid Amount']
            );
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $checkEventShowVenue = EventShowVenue::findFirst([
                            "event_show_venue_id =:event_show_venue_id: ",
                            "bind" => [
                                "event_show_venue_id" => $event_show_venue_id],]);
                if (!$checkEventShowVenue) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event Show Venue Id']);
                }
                $checkEventShows = EventShows::findFirst([
                            "event_show_id =:event_show_id:",
                            "bind" => [
                                "event_show_id" => $checkEventShowVenue->event_show_id]]);

                if (!$checkEventShows) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__, 'Event Show Not Configured', [
                                'code' => 402
                                , 'message' => "Event Show Not Configured"], true);
                }
                $checkQuery = $this->selectQuery("SELECT  * FROM ticket_types WHERE"
                        . " typeId = :typeId", [':typeId' => $typeId]);
                if (!$checkQuery) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Ticket Type Id']);
                }
                $checkEventShowTicketsType = EventShowTicketsType::findFirst([
                            "typeId =:typeId: AND event_show_venue_id=:event_show_venue_id: ",
                            "bind" => [
                                "typeId" => $typeId, "event_show_venue_id" => $event_show_venue_id],]);
                if ($checkEventShowTicketsType) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "Event Show tickets type Exist"
                                    , ["typeId" => $typeId, 'event_show_venue_id' => $event_show_venue_id], true);
                }

                $eventShowTicketsType = new EventShowTicketsType();
                $eventShowTicketsType->setTransaction($dbTrxn);
                $eventShowTicketsType->typeId = $typeId;
                $eventShowTicketsType->event_show_venue_id = $event_show_venue_id;
                $eventShowTicketsType->amount = $amount;
                $eventShowTicketsType->discount = $discount;
                $eventShowTicketsType->group_ticket_quantity = $groupTickets;
                $eventShowTicketsType->total_complimentary = $total_complimentary;
                $eventShowTicketsType->total_tickets = $total_tickets;
                if ($typeId == 11) {
                    $eventShowTicketsType->status = 5;
                } else {
                    $eventShowTicketsType->status = 1;
                }
                $eventShowTicketsType->created = $this->now();
                if ($eventShowTicketsType->save() === false) {
                    $errors = [];
                    $messages = $eventShowTicketsType->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Event Show Tickets Type failed " . json_encode($errors));
                }
                $checkEventStats = EventsStatistics::findFirst([
                            "eventID=:eventId: ",
                            "bind" => ['eventId' => $checkEventShows->eventID],]);
                if (!$checkEventStats) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'System Error'
                                    , ['code' => 405, 'message' => 'Event Stats does not exist']);
                }
                $checkEventStats->setTransaction($dbTrxn);
                $checkEventStats->total_tickets = $checkEventStats->total_tickets + $total_tickets;
                $checkEventStats->updated = $this->now();
                if ($checkEventStats->save() === false) {
                    $errors = [];
                    $messages = $checkEventStats->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Update Events Stats failed " . json_encode($errors));
                }

                $dbTrxn->commit();
                $data_array = [
                    'code' => 200,
                    'message' => 'Event Show Tickets Type Created successful'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Action Successfully"
                                , $data_array);
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
     * viewTicketTypeShowAction
     * @return type
     */
    public function viewTicketTypeShowAction() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Ticket Types Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_show_venue_idD = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($event_show_venue_idD) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source || !$event_show_venue_idD) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if(!is_numeric($event_show_venue_idD)){
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
            $sort = "event_show_tickets_type.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_show_tickets_type.amount';
            $order = 'ASC';
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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
            $whereArray = [
                'event_show_tickets_type.event_show_venue_id' => $event_show_venue_idD];

            $searchQuery = $this->whereQuery($whereArray, "");

            // $searchQuery .= " AND date(event_shows.start_date) >= date(now()) ";

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_show_tickets_type.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_show_tickets_type.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_show_tickets_type.created)>='$start'";
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = "select event_show_tickets_type.currency, event_show_tickets_type.close_sale_date, event_show_tickets_type.open_sale_date,"
                    . "event_show_tickets_type.gate_open_date,"
                    . "event_show_tickets_type.hasOption,event_show_tickets_type.event_ticket_show_id,"
                    . "event_show_tickets_type.discount,ticket_types.typeId,ticket_types.ticket_type,ifnull(ticket_types.caption,'') as caption,"
                    . "events.eventName,events.isFree,event_shows.start_date,event_show_venue.venue,"
                    . "event_show_tickets_type.amount,event_show_tickets_type.currency,"
                    . "event_show_tickets_type.status,event_show_tickets_type.total_tickets,event_show_tickets_type.maxCap,"
                    . "(event_show_tickets_type.total_tickets "
                    . "- event_show_tickets_type.ticket_purchased) as avaliableTickets, "
                    . "event_show_tickets_type.created,"
                    . "(select count(event_show_tickets_type.event_ticket_show_id) from"
                    . " event_show_tickets_type join ticket_types on event_show_tickets_type.typeId"
                    . "  = ticket_types.typeId JOIN event_show_venue on event_show_venue.event_show_venue_id "
                    . "= event_show_tickets_type.event_show_venue_id  JOIN event_shows on "
                    . "event_shows.event_show_id = event_show_venue.event_show_id join events on"
                    . " events.eventID  = event_shows.eventID  "
                    . "$searchQuery) as total FROM event_show_tickets_type "
                    . "join ticket_types on event_show_tickets_type.typeId  = "
                    . "ticket_types.typeId JOIN event_show_venue on event_show_venue.event_show_venue_id "
                    . "= event_show_tickets_type.event_show_venue_id  JOIN event_shows on "
                    . "event_shows.event_show_id = event_show_venue.event_show_id join events on"
                    . " events.eventID  = event_shows.eventID  $searchQuery $sorting";

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stopTime = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stopTime Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stopTime = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket Types results ($stopTime Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * addGreenJobCustomers
     * @return type
     */
    public function addGreenJobCustomers() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create Green Job Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $full_name = isset($data->full_name) ? $data->full_name : null;
        $email = isset($data->email) ? $data->email : null;
        $organisation = isset($data->organisation) ? $data->organisation : null;
        $title = isset($data->title) ? $data->title : null;
        $preferred_workstreams = isset($data->preferred_workstreams) ? $data->preferred_workstreams : null;
        $county = isset($data->county) ? $data->county : null;
        $category = isset($data->category) ? $data->category : null;
        $gender = isset($data->gender) ? $data->gender : null;
        $age = isset($data->age) ? $data->age : null;
        $customCategory = isset($data->customCategory) ? $data->customCategory : null;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($phone) || $this->checkForMySQLKeywords($full_name) || $this->checkForMySQLKeywords($email) || $this->checkForMySQLKeywords($organisation) || $this->checkForMySQLKeywords($title) || $this->checkForMySQLKeywords($preferred_workstreams) || $this->checkForMySQLKeywords($county) || $this->checkForMySQLKeywords($category) || $this->checkForMySQLKeywords($gender) || $this->checkForMySQLKeywords($age) || $this->checkForMySQLKeywords($customCategory) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$phone || !$full_name || !$title || !$preferred_workstreams || !$county || !$email) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if(!$this->validateEmail($email)){
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($category == "Other") {
            if (!$customCategory) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            } else {
                $category = $customCategory;
            }
        }
        $msisdn = $this->formatMobileNumber($phone, "254");
        $network = $this->getMobileNetwork($msisdn, "254");
        if ($network == "UNKNOWN") {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Request Failed.Invalid Number']);
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
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
            $checkGreenJobClient = GreenJobClient::findFirst(["profile_id=:profile_id:",
                        "bind" => ["profile_id" => $profile_id],]);

            if ($checkGreenJobClient) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'Already Submitted your request  (' . $stop_time . ' Seconds)', [
                            'code' => 202
                            , 'message' => "Already Submitted."], true);
            }
            $GreenJobClient = new GreenJobClient();
            $GreenJobClient->setTransaction($dbTrxn);
            $GreenJobClient->profile_id = $profile_id;
            $GreenJobClient->category = $category;
            $GreenJobClient->full_name = $full_name;
            $GreenJobClient->county = $county;
            $GreenJobClient->email = $email;
            $GreenJobClient->age = $age;
            $GreenJobClient->gender = $gender;
            $GreenJobClient->organisation = $organisation;
            $GreenJobClient->preferred_workstreams = $preferred_workstreams;
            $GreenJobClient->title = $title;
            $GreenJobClient->created = $this->now();
            if ($GreenJobClient->save() === false) {
                $errors = [];
                $messages = $GreenJobClient->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
            }
            $dbTrxn->commit();
            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok  (' . $stop_time . ' Seconds)'
                            , ['code' => 200
                        , 'message' => "Successfully Created Record"]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * redemeedKeywordsAmount
     * @return type
     */
    public function redemeedKeywordsAmount() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $email = isset($data->email) ? $data->email : 1;
        $quantity = isset($data->quantity) ? $data->quantity : 1;
        $reference = isset($data->reference) ? $data->reference : "MADFUN_KEYWORD";
        $unique_id = isset($data->unique_id) ? $data->unique_id : null;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($event_ticket_id) || $this->checkForMySQLKeywords($phone) || $this->checkForMySQLKeywords($email) || $this->checkForMySQLKeywords($quantity) || $this->checkForMySQLKeywords($reference) || $this->checkForMySQLKeywords($unique_id) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$event_ticket_id || !$quantity || !$phone) {
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
            if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $checkEventTicketID = EventTicketsType::findFirst(["event_ticket_id=:event_ticket_id:",
                        "bind" => ["event_ticket_id" => $event_ticket_id],]);
            if (!$checkEventTicketID) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event ticket Id']);
            }
            if ($checkEventTicketID->ticket_purchased >= $checkEventTicketID->total_tickets) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Sorry but you cannot '
                            . 'purchase ticket as the Event Ticket is sold out']);
            }

            $eventData = Events::findFirst(["eventID=:eventID:",
                        "bind" => ["eventID" => $checkEventTicketID->eventId],]);

            $select_keywords = "SELECT * FROM `event_keywords` WHERE "
                    . "`eventId`=:eventId LIMIT 1";
            $check_keywords = $this->rawSelect($select_keywords,
                    [':eventId' => $checkEventTicketID->eventId]);
            if (!$check_keywords) {
                return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                                , 'Keyword Not Found', ['code' => 404
                            , 'message' => 'The Keyword not found']);
            }
            if (($quantity * $checkEventTicketID->amount) >
                    ($check_keywords[0]['amount_received'] - $check_keywords[0]['amount_redemeed'])) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Insufficient Fund. '
                            . 'Current Balance for event is.'
                            . ' KES' . ($check_keywords[0]['amount_received'] - $check_keywords[0]['amount_redemeed'])]);
            }

            $sms = "";
            for ($i = 1; $i <= $quantity; $i++) {

                $t = time();
                $len = rand(1000000, 9999999) . "" . $t;
                $paramsTickets = [
                    'profile_id' => Profiling::Profile($msisdn),
                    'event_ticket_id' => $event_ticket_id,
                    'reference_id' => $unique_id,
                    'reference' => $reference,
                    'barcode' => $len,
                    'discount' => $checkEventTicketID->discount,
                    'barcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8'
                ];

                $tickets = new Tickets();
                $event_profile_ticket_id = $tickets->CreateTicketProfile($paramsTickets);

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
                $this->rawUpdateWithParams("UPDATE `event_keywords`"
                        . " SET `amount_redemeed` = `amount_redemeed` + :amount "
                        . "WHERE  `event_keyword_id` = :event_keyword_id LIMIT 1 ",
                        [':amount' => $checkEventTicketID->amount, ':event_keyword_id' =>
                            $check_keywords[0]['event_keyword_id']]);

                $sms .= "Dear, Your " . $eventData->eventName . " ticket "
                        . "is " . $len . ". View your ticket from "
                        . $this->settings['TicketBaseURL'] . "?evtk=" . $len . " \n";
                if ($email != null) {
                    $paramsEmail = [
                        "eventID" => $eventData->eventID,
                        "type" => "TICKET_PURCHASED",
                        "name" => "",
                        "eventDate" => $eventData->start_date,
                        "eventName" => $eventData->eventName,
                        "eventAmount" => $checkEventTicketID->amount,
                        'eventType' => $eventData->eventType,
                        'QRcodeURL' => 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $len . '&choe=UTF-8',
                        'QRcode' => $len,
                        'posterURL' => $eventData->posterURL,
                        'venue' => $eventData->venue
                    ];
                    $postData = [
                        "api_key" => $this->settings['ServiceApiKey'],
                        "to" => $email,
                        "cc" => "",
                        "subject" => "Ticket for Event: " . $eventData->eventName,
                        "content" => "Ticket information",
                        "extrac" => $paramsEmail
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailWithoutAttachments Response::" . json_encode($mailResponse));
                }
            }

            $params = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                "msisdn" => $msisdn,
                "message" => $sms . ". Madfun! For Queries call "
                . "" . $this->settings['Helpline'],
                "profile_id" => Profiling::Profile($msisdn),
                "created_by" => 'REDEEMED_TICKETS',
                "is_bulk" => false,
                "link_id" => ""];

            $message = new Messaging();
            $message->LogOutbox($params);
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Ticket Sent Successful', ['code' => 200
                        , 'success' => "Tickets Sent Successful"]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * editEvent
     * @return type
     */
    public function editEvent() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Edit Event Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventTag = isset($data->eventTag) ? $data->eventTag : NULL;
        $eventID = isset($data->eventID) ? $data->eventID : NULL;
        $eventName = isset($data->eventName) ? $data->eventName : NULL;
        $company = isset($data->company) ? $data->company : NULL;
        $eventType = isset($data->eventType) ? $data->eventType : NULL;
        $venue = isset($data->venue) ? $data->venue : NULL;
        $ageLimit = isset($data->ageLimit) ? $data->ageLimit : NULL;
        $start_date_app = isset($data->start_date) ? $data->start_date : NULL;
        $end_date_app = isset($data->end_date) ? $data->end_date : NULL;
        $posterURL = isset($data->posterURL) ? $data->posterURL : NULL;
        $bannerURL = isset($data->bannerURL) ? $data->bannerURL : NULL;
        $description = isset($data->description) ? $data->description : NULL;
        $currency = isset($data->currency) ? $data->currency : NULL;
        $revenueShare = isset($data->revenueShare) ? $data->revenueShare : NULL;
        $isPublic = isset($data->isPublic) ? $data->isPublic : 1;
        $status = isset($data->status) ? $data->status : NULL;
        $target = isset($data->target) ? $data->target : NULL;
        $isFeatured = isset($data->isFeatured) ? $data->isFeatured : 0;
        $showOnSlide = isset($data->showOnSlide) ? $data->showOnSlide : 0;

        $hasAffiliator = isset($data->hasAffiliator) ? $data->hasAffiliator : 0;
        $categoryID = isset($data->categoryID) ? $data->categoryID : NULL;

//        if($this->checkForMySQLKeywords($token)
//                || $this->checkForMySQLKeywords($eventTag)
//                || $this->checkForMySQLKeywords($eventID)
//                || $this->checkForMySQLKeywords($eventName)
//                || $this->checkForMySQLKeywords($company)
//                || $this->checkForMySQLKeywords($eventType)
//                || $this->checkForMySQLKeywords($venue)
//                || $this->checkForMySQLKeywords($ageLimit)
//                || $this->checkForMySQLKeywords($start_date_app)
//                || $this->checkForMySQLKeywords($end_date_app)
//                || $this->checkForMySQLKeywords($posterURL)
//                || $this->checkForMySQLKeywords($bannerURL)
//                || $this->checkForMySQLKeywords($description)
//                || $this->checkForMySQLKeywords($currency)
//                || $this->checkForMySQLKeywords($revenueShare)
//                || $this->checkForMySQLKeywords($isPublic)
//                || $this->checkForMySQLKeywords($status)
//                || $this->checkForMySQLKeywords($target)
//                || $this->checkForMySQLKeywords($hasAffiliator)){
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
//        }
        if (!$token || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        $auth = new Authenticate();
        $auth_response = $auth->QuickTokenAuthenticate($token);

        if (!$auth_response) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                            , 'Authentication Failure.');
        }
        if (!in_array($auth_response['userRole'], [1, 2, 5, 6])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                            , 'User doesn\'t have permissions to perform this action.');
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID]]);
            if (!$checkEvents) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid EventID']);
            }
            $checkEvents->setTransaction($dbTrxn);
            $checkEvents->updated = $this->now();
            $checkEvents->hasAffiliator = $hasAffiliator;
            if ($eventName) {
                $checkEvents->eventName = $eventName;
            }
            if ($company) {
                $checkEvents->company = $company;
            }
            if ($eventType) {
                $checkEvents->eventType = $eventType;
            }
            if ($eventTag) {
                $checkEvents->eventTag = $eventTag;
            }
            if ($venue) {
                $checkEvents->venue = $venue;
            }
            if ($ageLimit) {
                $checkEvents->ageLimit = $ageLimit;
            }
            if ($start_date_app) {
                $start_date_app_1 = strtotime($start_date_app);
                $start_date = date('Y-m-d H:i:s', $start_date_app_1);
                $checkEvents->start_date = $start_date;
            }
            if ($end_date_app) {
                $end_date_app_1 = strtotime($end_date_app);
                $end_date = date('Y-m-d H:i:s', $end_date_app_1);
                $checkEvents->end_date = $end_date;
            }
            if ($posterURL) {
                $checkEvents->posterURL = $posterURL;
            }
            if ($bannerURL) {
                $checkEvents->bannerURL = $bannerURL;
            }
            if ($description) {
                $checkEvents->aboutEvent = $description;
            }

      
            if ($currency) {
                $checkEvents->currency = $currency;
            }
            if ($revenueShare && in_array($auth_response['userRole'], [1, 2])) {
                $checkEvents->revenueShare = $revenueShare;
            }
            if ($status) {
                $checkEvents->status = $status;
            }

            $checkEvents->isFeatured = $isFeatured;
            $checkEvents->showOnSlide = $showOnSlide;

            if ($isPublic && in_array($isPublic, [1, 0])) {
                $checkEvents->isPublic = $isPublic;
            }
            if ($target && is_numeric($target)) {
                $checkEvents->target = $target;
            }
            if ($categoryID) {
                $checkQuery = $this->selectQuery("SELECT  * FROM event_category WHERE"
                        . " id = :id", [':id' => $categoryID]);
                if ($checkQuery) {
                    $checkEvents->category_id = $categoryID;
                }
            }
            if ($checkEvents->save() === false) {
                $errors = [];
                $messages = $checkEvents->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Update Events failed " . json_encode($errors));
            }
            $dbTrxn->commit();
            $data_array = [
                'code' => 200,
                'eventID' => $eventID,
                'message' => 'Event Updated successful'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Event Action Successfully"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     *  viewEventSum
     * @return type
     */
    public function viewEventSum() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort)) {
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
            $sort = "events.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'events.start_date';
            $order = 'DESC';
        }
        try {

            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            $searchQueryInner = "WHERE 1";
            if ($auth_response['user_mapId'] != null) {
                $whereArray = [
                    'user_event_map.user_mapId' => $auth_response['user_mapId'],];

                $searchQuery = $this->whereQuery($whereArray, "");
            } else {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Events Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_time Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }


            if ($eventID != null) {
                $searchQuery = " WHERE events.eventID='$eventID'";
                $searchQueryInner = " WHERE events.eventID='$eventID' ";
            }

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(events.start_date) BETWEEN '$start' AND '$stop' ";
                $searchQueryInner .= " AND date(events.start_date) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(events.start_date)<='$stop'";
                $searchQueryInner .= " AND date(events.start_date)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(events.start_date)>='$start'";
                $searchQueryInner .= " AND date(events.start_date)>='$start'";
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = "SELECT events.eventID,events.posterURL,events.eventName,events.venue,"
                    . "events.status,events.isFree, events.revenueShare,events.currency,"
                    . "events.start_date,IF (events.hasMultipleShow=1, "
                    . "(SELECT sum(event_show_tickets_type.total_tickets*event_show_tickets_type.group_ticket_quantity) "
                    . " FROM event_show_tickets_type JOIN event_show_venue "
                    . "ON event_show_venue.event_show_venue_id=event_show_tickets_type.event_show_venue_id  "
                    . "JOIN event_shows ON event_shows.event_show_id=event_show_venue.event_show_id "
                    . "WHERE   event_shows.eventId=events.eventID),(SELECT"
                    . " sum(event_tickets_type.total_tickets*event_tickets_type.group_ticket_quantity)"
                    . "   FROM event_tickets_type WHERE event_tickets_type.eventId=events.eventID))"
                    . " AS totalTickets,  IF(events.hasMultipleShow=1,( select"
                    . " sum(event_show_tickets_type.ticket_purchased)   FROM "
                    . "event_show_tickets_type JOIN event_show_venue ON   "
                    . "event_show_venue.event_show_venue_id=event_show_tickets_type.event_show_venue_id "
                    . "   JOIN event_shows ON event_shows.event_show_id=event_show_venue.event_show_id"
                    . "   WHERE event_shows.eventId= events.eventID),  "
                    . "(SELECT SUM(event_tickets_type.ticket_purchased) FROM "
                    . "event_tickets_type WHERE   event_tickets_type.eventID = events.eventID)) "
                    . "AS totalPurchase,IF(events.hasMultipleShow=1,( select "
                    . "sum(event_show_tickets_type.total_refund / event_show_tickets_type.group_ticket_quantity)  FROM "
                    . "event_show_tickets_type JOIN event_show_venue ON  "
                    . " event_show_venue.event_show_venue_id= "
                    . "event_show_tickets_type.event_show_venue_id    "
                    . "JOIN event_shows ON event_shows.event_show_id= "
                    . "event_show_venue.event_show_id   "
                    . "WHERE event_shows.eventId= events.eventID),  "
                    . "(SELECT SUM(event_tickets_type.total_refund / event_tickets_type.group_ticket_quantity) "
                    . "FROM event_tickets_type WHERE   event_tickets_type.eventID "
                    . "= events.eventID)) AS totalRefund,IF(events.hasMultipleShow=1,"
                    . "( select sum( event_show_tickets_type.amount * "
                    . "(event_show_tickets_type.total_refund/ "
                    . "event_show_tickets_type.group_ticket_quantity))  "
                    . "FROM event_show_tickets_type JOIN event_show_venue ON   "
                    . "event_show_venue.event_show_venue_id="
                    . "event_show_tickets_type.event_show_venue_id    "
                    . "JOIN event_shows ON event_shows.event_show_id= "
                    . "event_show_venue.event_show_id   "
                    . "WHERE event_shows.eventId= events.eventID),  "
                    . "(SELECT SUM( event_tickets_type.amount * "
                    . "(event_tickets_type.total_refund/event_tickets_type.group_ticket_quantity)"
                    . " ) FROM event_tickets_type WHERE   "
                    . "event_tickets_type.eventID = events.eventID)) AS totalAmountRefund,"
                    . "IF (events.hasMultipleShow=1,  (select "
                    . "sum((event_show_tickets_type.ticket_purchased / event_show_tickets_type.group_ticket_quantity) * event_show_tickets_type.amount)"
                    . "   FROM event_show_tickets_type JOIN event_show_venue ON  "
                    . " event_show_venue.event_show_venue_id=event_show_tickets_type.event_show_venue_id"
                    . " JOIN event_shows ON event_shows.event_show_id=event_show_venue.event_show_id"
                    . "  WHERE event_shows.eventId=events.eventID),(SELECT "
                    . "SUM((event_tickets_type.ticket_purchased / event_tickets_type.group_ticket_quantity) * event_tickets_type.amount)"
                    . "   FROM event_tickets_type WHERE event_tickets_type.eventID = events.eventID)) "
                    . "AS totalRevenue,IF(events.hasMultipleShow=1,"
                    . "(SELECT sum(transaction_initiated.extra_data->> '$.amount') "
                    . "FROM dpo_transaction JOIN   dpo_transaction_initiated ON "
                    . "dpo_transaction.TransID=dpo_transaction_initiated.TransactionToken"
                    . "   JOIN transaction_initiated ON "
                    . "transaction_initiated.transaction_id=dpo_transaction_initiated.transaction_id "
                    . " WHERE transaction_initiated.reference_id IN ( "
                    . "SELECT DISTINCT event_profile_tickets.reference_id   "
                    . "FROM event_profile_tickets JOIN event_profile_tickets_state "
                    . "ON event_profile_tickets.event_profile_ticket_id=event_profile_tickets_state.event_profile_ticket_id  "
                    . " JOIN event_show_tickets_type ON event_show_tickets_type.event_ticket_show_id"
                    . "=event_profile_tickets.event_ticket_id   JOIN "
                    . "event_show_venue ON event_show_venue.event_show_venue_id="
                    . "event_show_tickets_type.event_show_venue_id   JOIN event_shows "
                    . "ON event_shows.event_show_id=event_show_venue.event_show_id WHERE"
                    . "   event_shows.eventID=events.eventID AND event_profile_tickets_state.status=1"
                    . " AND event_profile_tickets.isComplimentary !=1 AND "
                    . "event_profile_tickets.isShowTicket = 1)),  ( SELECT "
                    . "sum(transaction_initiated.extra_data->> '$.amount') FROM "
                    . "dpo_transaction JOIN dpo_transaction_initiated   ON dpo_transaction.TransID"
                    . "=dpo_transaction_initiated.TransactionToken JOIN transaction_initiated"
                    . "   ON transaction_initiated.transaction_id=dpo_transaction_initiated.transaction_id"
                    . " WHERE   transaction_initiated.reference_id IN ( "
                    . "SELECT DISTINCT event_profile_tickets.reference_id  "
                    . " FROM event_profile_tickets JOIN event_profile_tickets_state "
                    . "ON   event_profile_tickets_state.event_profile_ticket_id="
                    . "event_profile_tickets.event_profile_ticket_id   JOIN "
                    . "event_tickets_type ON event_tickets_type.event_ticket_id=event_profile_tickets.event_ticket_id"
                    . "   WHERE event_tickets_type.eventId=events.eventID))) "
                    . "AS totalDPO,( SELECT count(events.eventID)   FROM events $searchQueryInner) "
                    . "AS total FROM events JOIN user_event_map ON "
                    . "user_event_map.eventID=events.eventID  $searchQuery"
                    . " group by events.eventID  $sorting";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ViewEventSQL:" . $sql);

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
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewEvents
     * @return type
     * @throws Exception
     */
    public function viewEvents() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $isFreeEvent = isset($data->isFreeEvent) ? $data->isFreeEvent : 0;

        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $accessPoint = isset($data->accessPoint) ? $data->accessPoint : null;
        $pastEvent = isset($data->pastEvent) ? $data->pastEvent : 0;
        $isStagingOnly = isset($data->isStagingOnly) ? $data->isStagingOnly : 0;
        $excludeEvent = isset($data->excludeEvent) ? $data->excludeEvent : null;
        $groupEvent = isset($data->groupEvent) ? $data->groupEvent : null;
        $source = isset($data->source) ? $data->source : null;
        $isFeatured = isset($data->isFeatured) ? $data->isFeatured : 0;
         $showOnSlide = isset($data->showOnSlide) ? $data->showOnSlide : 0;
        if ($this->checkForMySQLKeywords($isFeatured) || $this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($offset) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($isFreeEvent) || $this->checkForMySQLKeywords($accessPoint) || $this->checkForMySQLKeywords($pastEvent) || $this->checkForMySQLKeywords($isStagingOnly) || $this->checkForMySQLKeywords($excludeEvent) || $this->checkForMySQLKeywords($groupEvent) || $this->checkForMySQLKeywords($source)) {
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
            $sort = "events.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'events.start_date';
            $order = 'asc';
        }

        if ($isFeatured == 1) {
            $sort = 'events.featured';
            $order = 'asc';
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB', 'MOBILE'])) {
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
            $searchQuery = "WHERE (events.status = 1 || events.status = 6) ";

            if ($eventID != null) {
                $searchQuery = " WHERE events.eventID='$eventID' ||  events.eventTag='$eventID'";
            }
            if ($excludeEvent != null && $eventID != null) {
                $searchQuery = " WHERE (events.eventID != '$eventID' ||  events.eventTag='$eventID') AND (events.status = 1 || events.status = 6) ";
            }
            if ($pastEvent == 1) {
                $searchQuery = " WHERE date(events.start_date) < date(now()) AND events.status = 3";
            }
            if ($isStagingOnly == 1) {
                $searchQuery = "WHERE (events.status = 1 || events.status = 4) ";
            }

            if ($source == "USSD") {
                $searchQuery .= " AND events.showOnUssd=1";
            }

            if ($accessPoint != null) {
                $searchQuery .= " AND events.ussd_access_point =  '$accessPoint'";
            }
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(events.start_date) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(events.start_date)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(events.start_date)>='$start'";
            }
            if ($groupEvent != null) {
                $searchQuery .= " AND events.groupEvent=" . $groupEvent;
            }

            if ($isFeatured) {
                $searchQuery .= " AND events.isFeatured=" . $isFeatured;
            }
            
            if($showOnSlide){
                $searchQuery .= " AND events.showOnSlide=" .$showOnSlide;
            }

            //$searchQuery .= " AND events.isFree=".$isFreeEvent;


            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);

            $sql = "select ussd_access_point,accept_mpesa_payment,isFeeOnOrganizer,showOnSlide,eventID,hasLinkingTag,hasArena,isContactOnly,min_price,totalTickets,`contact`,"
                    . "hasMultipleShow,hasAffiliator,isPublic,isFree,eventName,custom_name_show,"
                    . "category_id,target,company,venue,hiddenVenue,status,"
                    . "posterURL,bannerURL,soldOut,if(events.hasMultipleShow=1,"
                    . "(select amount from event_show_tickets_type join event_show_venue "
                    . "on event_show_tickets_type.event_show_venue_id = "
                    . "event_show_venue.event_show_venue_id join event_shows"
                    . " on event_show_venue.event_show_id = event_shows.event_show_id "
                    . "where event_shows.eventID = events.eventID order by amount limit 1),"
                    . "(select amount from event_tickets_type where eventId = events.eventID"
                    . " order by amount limit 1)) as minAmount,"
                    . "currency,min_price,start_date,DATE_FORMAT(start_date, '%Y-%m-%d') "
                    . "as startDate,revenueShare,"
                    . "end_date,googlelink,eventType,ageLimit,created,(SELECT "
                    . "COUNT(eventID) from events $searchQuery)"
                    . " as total,( select IFNULL(sum(event_tickets_type.ticket_redeemed),0) "
                    . "from event_tickets_type where eventId =events.eventID ) "
                    . "as totalRedemmed,aboutEvent"
                    . " from events $searchQuery $sorting";
            
            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Records for Events ( $stop_time Seconds)"
                            , 'data' => []], true);
            }

            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Events results ($stop_time Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {

            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
    
    /**
     * viewCountries
     * @return type
     * @throws Exception
     */
    public function viewCountries() {
        $start_time = $this->getMicrotime();
        $request = new Request();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Countries:" . json_encode($request->getJsonRawBody()));

        try {
            
            $searchQuery = "WHERE country.status = 1 ";

            $sql = "select * from country $searchQuery";

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Records for Country ( $stop_time Seconds)"
                            , 'data' => []], true);
            }

            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Country Results ($stop_time Seconds)"
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {

            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewEventsDashboard
     */
    public function viewEventsDashboard() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';

            if ($this->checkForMySQLKeywords($currentPage) || $this->checkForMySQLKeywords($perPage) || $this->checkForMySQLKeywords($filter) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($end)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "Select eventID,showOnSlide,isFeatured,eventTag,hasAffiliator,"
                    . "eventName,company,posterURL,aboutEvent,revenueShare,"
                    . "category_id, target,hasMultipleShow,isPublic,venue,"
                    . "status,start_date,end_date,created ";

            $countQuery = " select count(events.eventID) as totalEvents  ";

            $baseQuery = " from events ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['events.eventName', 'events.venue',
                        'events.company'];

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
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(events.created) BETWEEN '$value[0]' AND '$value[1]'";
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


            $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by events.eventID";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalEvents'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/dashboard/view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/dashboard/view?page=$next_url";
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
                            , 'Internal Server Error.');

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
     * addEventTicketType
     * @return type
     * @throws Exception
     */
    public function addEventTicketType() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $typeId = isset($data->typeId) ? $data->typeId : null;
        $eventId = isset($data->eventId) ? $data->eventId : null;
        $amount = isset($data->amount) ? $data->amount : null;
        $total_tickets = isset($data->total_tickets) ? $data->total_tickets : null;
        $discount = isset($data->discount) ? $data->discount : 0;
        $groupTickets = isset($data->groupTickets) ? $data->groupTickets : 1;
        $total_complimentary = isset($data->total_complimentary) ? $data->total_complimentary : 0;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $isPublic = isset($data->isPublic) ? $data->isPublic : 1;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($typeId) || $this->checkForMySQLKeywords($eventId) || $this->checkForMySQLKeywords($amount) || $this->checkForMySQLKeywords($total_tickets) || $this->checkForMySQLKeywords($discount) || $this->checkForMySQLKeywords($groupTickets) || $this->checkForMySQLKeywords($total_complimentary) || $this->checkForMySQLKeywords($event_show_venue_id) || $this->checkForMySQLKeywords($discount)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$typeId || !$eventId) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        
        if ($total_tickets <= 0) {
            return $this->dataError(
                            __LINE__ . ":" . __CLASS__, 'Validation Error', ['code' => 422, 'message' => 'Invalid Amount']
            );
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $checkEvents = Events::findFirst([
                            "eventID =:eventID: ",
                            "bind" => [
                                "eventID" => $eventId],]);
                if (!$checkEvents) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Event Id']);
                }
                $checkQuery = $this->selectQuery("SELECT  * FROM ticket_types WHERE"
                        . " typeId = :typeId", [':typeId' => $typeId]);
                if (!$checkQuery) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Ticket Type Id']);
                }
                if ($event_show_venue_id) {
                    $checkEventType = EventShowTicketsType::findFirst([
                                "typeId =:typeId: AND event_show_venue_id=:event_show_venue_id: ",
                                "bind" => [
                                    "typeId" => $typeId, "event_show_venue_id" => $event_show_venue_id],]);
                    if ($checkEventType) {
                        return $this->success(__LINE__ . ":" . __CLASS__
                                        , "Event ticket type Exist"
                                        , ["typeId" => $typeId, 'eventId' => $eventId], true);
                    }

                    $eventTicketType = new EventShowTicketsType();
                    $eventTicketType->setTransaction($dbTrxn);
                    $eventTicketType->typeId = $typeId;
                    $eventTicketType->event_show_venue_id = $event_show_venue_id;
                    $eventTicketType->amount = $amount;
                    $eventTicketType->discount = $discount;
                    $eventTicketType->group_ticket_quantity = $groupTickets;
                    $eventTicketType->total_complimentary = $total_complimentary;
                    $eventTicketType->isPublic = $isPublic;
                    $eventTicketType->total_tickets = $total_tickets;
                    if ($typeId == 11) {
                        $eventTicketType->status = 5;
                    } else {
                        $eventTicketType->status = 1;
                    }
                    $eventTicketType->created = $this->now();
                    if ($eventTicketType->save() === false) {
                        $errors = [];
                        $messages = $eventTicketType->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Events failed " . json_encode($errors));
                    }
                } else {
                    $checkEventType = EventTicketsType::findFirst([
                                "typeId =:typeId: AND eventId=:eventId: ",
                                "bind" => [
                                    "typeId" => $typeId, "eventId" => $eventId],]);
                    if ($checkEventType) {
                        return $this->success(__LINE__ . ":" . __CLASS__
                                        , "Event ticket type Exist"
                                        , ["typeId" => $typeId, 'eventId' => $eventId], true);
                    }
                    $eventTicketType = new EventTicketsType();
                    $eventTicketType->setTransaction($dbTrxn);
                    $eventTicketType->typeId = $typeId;
                    $eventTicketType->eventId = $eventId;
                    $eventTicketType->amount = $amount;
                    $eventTicketType->discount = $discount;
                    $eventTicketType->group_ticket_quantity = $groupTickets;
                    $eventTicketType->isPublic = $isPublic;
                    $eventTicketType->total_complimentary = $total_complimentary;
                    $eventTicketType->total_tickets = $total_tickets;
                    if ($typeId == 11) {
                        $eventTicketType->status = 5;
                    } else {
                        $eventTicketType->status = 1;
                    }
                    $eventTicketType->created = $this->now();
                    if ($eventTicketType->save() === false) {
                        $errors = [];
                        $messages = $eventTicketType->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Events failed " . json_encode($errors));
                    }
                }

                $checkEventStats = EventsStatistics::findFirst([
                            "eventID=:eventId: ",
                            "bind" => ['eventId' => $eventId],]);
                if (!$checkEventStats) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'System Error'
                                    , ['code' => 405, 'message' => 'Event Stats does not exist']);
                }
                $checkEventStats->setTransaction($dbTrxn);
                $checkEventStats->total_tickets = $checkEventStats->total_tickets + $total_tickets;
                $checkEventStats->updated = $this->now();
                if ($checkEventStats->save() === false) {
                    $errors = [];
                    $messages = $checkEventStats->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Update Events Stats failed " . json_encode($errors));
                }

                $dbTrxn->commit();
                $data_array = [
                    'code' => 200,
                    'message' => 'Event Ticket Type Created successful'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Action Successfully"
                                , $data_array);
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
     * editEventTicketType
     * @return type
     * @throws Exception
     */
    public function editEventTicketType() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $typeId = isset($data->typeId) ? $data->typeId : null;
        $event_ticket_id = isset($data->event_ticket_id) ? $data->event_ticket_id : null;
        $color_code = isset($data->color_code) ? $data->color_code : "#B59404";
        $main_color_code = isset($data->main_color_code) ? $data->main_color_code : "#F6D02A";
        $amount = isset($data->amount) ? $data->amount : null;
        $total_tickets = isset($data->total_tickets) ? $data->total_tickets : null;
        $discount = isset($data->discount) ? $data->discount : 0;
        $groupTickets = isset($data->groupTickets) ? $data->groupTickets : 1;
        $total_complimentary = isset($data->total_complimentary) ? $data->total_complimentary : 0;
        $status = isset($data->status) ? $data->status : 0;
        $event_show_venue_id = isset($data->event_show_venue_id) ? $data->event_show_venue_id : null;
        $isFeatured = isset($data->isFeatured) ? $data->isFeatured : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($typeId) || $this->checkForMySQLKeywords($event_ticket_id) || $this->checkForMySQLKeywords($color_code) || $this->checkForMySQLKeywords($main_color_code) || $this->checkForMySQLKeywords($amount) || $this->checkForMySQLKeywords($total_tickets) || $this->checkForMySQLKeywords($discount) || $this->checkForMySQLKeywords($groupTickets) || $this->checkForMySQLKeywords($total_complimentary) || $this->checkForMySQLKeywords($event_show_venue_id)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$event_ticket_id) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                if ($typeId) {
                    $checkQuery = $this->selectQuery("SELECT  * FROM ticket_types WHERE"
                            . " typeId = :typeId", [':typeId' => $typeId]);
                    if (!$checkQuery) {
                        return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                        , 'Validation Error'
                                        , ['code' => 422, 'message' => 'Invalid Ticket Type Id']);
                    }
                }
                $checkEventType = EventTicketsType::findFirst([
                            "event_ticket_id=:event_ticket_id: ",
                            "bind" => [
                                "event_ticket_id" => $event_ticket_id],]);
                if ($event_show_venue_id) {
                    $checkEventType = EventShowTicketsType::findFirst([
                                "event_ticket_show_id=:event_ticket_show_id: ",
                                "bind" => [
                                    "event_ticket_show_id" => $event_ticket_id],]);
                }
                if (!$checkEventType) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Ticket Type Id']);
                }
                $checkEventType->setTransaction($dbTrxn);
                if ($status) {
                    $checkEventType->status = $status;
                }
                if ($color_code) {
                    $checkEventType->color_code = $color_code;
                }
                if ($main_color_code) {
                    $checkEventType->main_color_code = $main_color_code;
                }
                if ($typeId) {
                    $checkEventType->typeId = $typeId;
                }
                if ($amount) {
                    $checkEventType->amount = $amount;
                }
                if ($discount) {
                    $checkEventType->discount = $discount;
                }
                if ($groupTickets) {
                    $checkEventType->group_ticket_quantity = $groupTickets;
                }
                if ($total_complimentary) {
                    $checkEventType->total_complimentary = $total_complimentary;
                }
                if ($total_tickets) {
                    $checkEventType->total_tickets = $total_tickets;
                }
                if ($isFeatured) {
                    $checkEventType->isFeatured = $isFeatured;
                }
                $checkEventType->updated = $this->now();
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
                $data_array = [
                    'code' => 200,
                    'message' => 'Event Ticket Type Update successful'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Event Action Successfully"
                                , $data_array);
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
     * viewEventTicketType
     * @return type
     */
    public function viewEventTicketType() {
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

            if ($this->checkForMySQLKeywords($currentPage) || $this->checkForMySQLKeywords($perPage) || $this->checkForMySQLKeywords($filter) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($end) || $this->checkForMySQLKeywords($eventID)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "select event_tickets_type.event_ticket_id,ticket_types.ticket_type,"
                    . "events.eventName,event_tickets_type.amount,event_tickets_type.`numbers`,"
                    . "event_tickets_type.total_tickets,event_tickets_type.ticket_purchased,event_tickets_type.ticket_redeemed,"
                    . "event_tickets_type.status, event_tickets_type.created";

            $countQuery = " select count(event_tickets_type.event_ticket_id) as totalEventsTicketType  ";

            $baseQuery = " FROM event_tickets_type "
                    . "join ticket_types on event_tickets_type.typeId  = "
                    . "ticket_types.typeId join events on events.eventID "
                    . " = event_tickets_type.eventId ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['events.eventName', 'events.venue',
                        'events.company', 'ticket_types.ticket_type', 'event_tickets_type.eventId'];

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
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(ticket_types.created) BETWEEN '$value[0]' AND '$value[1]'";
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
            $whereQuery = "";

            $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by event_tickets_type.event_ticket_id";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalEventsTicketType'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/type/view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/type/view?page=$next_url";
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
                            , 'Internal Server Error.');

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
     * viewMpesaTransactions
     * @return type
     */
    public function viewMpesaTransactions() {
        //$this->view->disable();
        //
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $role = $this->request->get('role') ? $this->request->get('role') : '1,2,3,4,6';

            if ($this->checkForMySQLKeywords($currentPage) || $this->checkForMySQLKeywords($perPage) || $this->checkForMySQLKeywords($filter) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($end) || $this->checkForMySQLKeywords($role)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "select mpesa_transaction.mpesa_code, mpesa_transaction.mpesa_amount,"
                    . "mpesa_transaction.mpesa_msisdn,mpesa_transaction.mpesa_sender,"
                    . "mpesa_transaction.mpesa_account,mpesa_transaction.mpesa_time,"
                    . "mpesa_transaction.org_balance,mpesa_transaction.paybill,"
                    . "mpesa_transaction.created ";

            $countQuery = "select count(mpesa_transaction.id) as totalMpesa  ";

            $baseQuery = "from mpesa_transaction ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['mpesa_transaction.mpesa_code', 'mpesa_transaction.mpesa_msisdn',
                        'mpesa_transaction.mpesa_sender', 'mpesa_transaction.mpesa_account'];

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
                } else if ($key == 't.status' && $value == 404) {
                    $valueString = "" . $key . "=0" . " AND ";
                    $whereQuery .= $valueString;
                } else if ($key == 'date') {
                    if (!empty($value[0]) && !empty($value[1])) {
                        $valueString = " DATE(user.created) BETWEEN '$value[0]' AND '$value[1]'";
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


            $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . "";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalMpesa'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/mpesa/transaction?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/mpesa/transaction?page=$next_url";
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
                            , 'Internal Server Error.');

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
     * viewTicketDetails
     * @return type
     */
    public function viewTicketDetails() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " |viewTicketDetails:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $barcodeURL = isset($data->ticketID) ? $data->ticketID : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($limit) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($sort) || $this->checkForMySQLKeywords($barcodeURL) || $this->checkForMySQLKeywords($source)) {
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
            $sort = "events.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'events.created';
            $order = 'DESC';
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
                if (!$barcodeURL) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__);
                }
            }
            $isShowTicket = 0;
            $searchQuery = "WHERE 1 ";
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_profile_tickets.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created)>='$start'";
            }
            if ($barcodeURL != null) {
                $searchQuery .= " AND event_profile_tickets.barcode='$barcodeURL'";
                $eventProfileTicket = $this->rawSelect("select isShowTicket from "
                        . "event_profile_tickets $searchQuery");
                if (!$eventProfileTicket) {
                    $stop_time = $this->getMicrotime() - $start_time;
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , 'Failed'
                                    , ['code' => 404
                                , 'message' => "Query returned no Records for Ticket ( $stop_time Seconds)"
                                , 'data' => []], true);
                }
                $isShowTicket = $eventProfileTicket[0]['isShowTicket'];
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);

            $sql = "select event_profile_tickets.refunded_date, event_profile_tickets.hasRefunded,profile.profile_id,event_tickets_type.color_code,event_tickets_type.main_color_code,event_profile_tickets.event_profile_ticket_id,event_profile_tickets.isShowTicket, event_tickets_type_option.option, events.status as eventStatus,"
                    . "profile.msisdn, profile_attribute.first_name,event_profile_tickets.alias_name,event_profile_tickets.isComplimentary,event_tickets_type.group_ticket_quantity,"
                    . "profile_attribute.surname,profile_attribute.last_name,ifnull(ticket_types.caption,'') as caption,"
                    . "events.eventName,events.venue,events.eventID, event_tickets_type.amount,"
                    . "ticket_types.ticket_type,events.start_date,event_tickets_type.currency,"
                    . " event_profile_tickets.barcode, event_profile_tickets.barcodeURL,events.posterURL,events.hasCustomTicket,"
                    . " event_profile_tickets_state.status, event_profile_tickets.created,"
                    . "(select count(event_profile_tickets.event_profile_ticket_id) "
                    . "FROM  event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "profile on profile.profile_id  = event_profile_tickets.profile_id "
                    . "join profile_attribute on  profile_attribute.profile_id = "
                    . "profile.profile_id join event_tickets_type ON "
                    . "event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id JOIN events on "
                    . "events.eventID  = event_tickets_type.eventId "
                    . "join ticket_types on ticket_types.typeId = event_tickets_type.typeId left "
                    . "join event_tickets_type_option on event_profile_tickets.event_tickets_option_id ="
                    . " event_tickets_type_option.event_tickets_option_id $searchQuery) "
                    . "as total FROM  event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "profile on profile.profile_id  = event_profile_tickets.profile_id "
                    . "left join profile_attribute on  profile_attribute.profile_id = "
                    . "profile.profile_id join event_tickets_type ON "
                    . "event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id JOIN events on "
                    . "events.eventID  = event_tickets_type.eventId "
                    . "join ticket_types on ticket_types.typeId = event_tickets_type.typeId"
                    . " left join event_tickets_type_option on event_profile_tickets.event_tickets_option_id "
                    . "= event_tickets_type_option.event_tickets_option_id $searchQuery $sorting";

          if ($isShowTicket == 1) {
                $sql = "select event_profile_tickets.refunded_date, event_profile_tickets.hasRefunded,profile.profile_id,event_show_tickets_type.color_code,event_show_tickets_type.main_color_code,event_profile_tickets.event_profile_ticket_id,event_profile_tickets.alias_name,"
                        . "event_profile_tickets.isShowTicket,events.hasCustomTicket,"
                        . "event_tickets_type_option.option,events.eventID, events.status as"
                        . " eventStatus,profile.msisdn, profile_attribute.first_name,"
                        . "event_profile_tickets.isComplimentary,event_shows.show,"
                        . "event_show_tickets_type.group_ticket_quantity,event_show_tickets_type.currency,"
                        . "profile_attribute.surname,profile_attribute.last_name,"
                        . "ifnull(ticket_types.caption,'') as caption,"
                        . "events.eventName,event_show_venue.venue, "
                        . "event_show_tickets_type.amount,ticket_types.ticket_type,"
                        . "event_shows.start_date,event_profile_tickets.barcode,"
                        . " event_profile_tickets.barcodeURL,events.posterURL, "
                        . "event_profile_tickets_state.status, "
                        . "event_profile_tickets.created,(SELECT "
                        . "count(event_profile_tickets.event_profile_ticket_id)"
                        . " FROM  event_profile_tickets join event_profile_tickets_state"
                        . " on event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id "
                        . "join profile on profile.profile_id  = "
                        . "event_profile_tickets.profile_id left join profile_attribute "
                        . "on  profile_attribute.profile_id = profile.profile_id "
                        . "join event_show_tickets_type ON event_show_tickets_type.event_ticket_show_id "
                        . "= event_profile_tickets.event_ticket_id JOIN "
                        . "event_show_venue on event_show_venue.event_show_venue_id "
                        . "= event_show_tickets_type.event_show_venue_id JOIN "
                        . "event_shows on event_shows.event_show_id = "
                        . "event_show_venue.event_show_id JOIN events on "
                        . "events.eventID  = event_shows.eventID join ticket_types"
                        . " on ticket_types.typeId = event_show_tickets_type.typeId "
                        . "left join event_tickets_type_option on "
                        . "event_profile_tickets.event_tickets_option_id = "
                        . "event_tickets_type_option.event_tickets_option_id $searchQuery)"
                        . " as total FROM  event_profile_tickets"
                        . " join event_profile_tickets_state on "
                        . "event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id "
                        . "join profile on profile.profile_id  = "
                        . "event_profile_tickets.profile_id left join "
                        . "profile_attribute on  profile_attribute.profile_id "
                        . "= profile.profile_id join event_show_tickets_type "
                        . "ON event_show_tickets_type.event_ticket_show_id = "
                        . "event_profile_tickets.event_ticket_id JOIN "
                        . "event_show_venue on event_show_venue.event_show_venue_id"
                        . " = event_show_tickets_type.event_show_venue_id "
                        . "JOIN event_shows on event_shows.event_show_id = "
                        . "event_show_venue.event_show_id JOIN events on events.eventID"
                        . "  = event_shows.eventID join ticket_types on "
                        . "ticket_types.typeId = event_show_tickets_type.typeId"
                        . " left join event_tickets_type_option on "
                        . "event_profile_tickets.event_tickets_option_id = "
                        . "event_tickets_type_option.event_tickets_option_id "
                        . "$searchQuery $sorting";
           }




            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Records for Ticket ( $stop_time Seconds)"
                            , 'data' => []], true);
            }
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | viewTicketDetailsSQL:" . $sql . " " . $result[0]['eventID']);

            $customFields = $this->rawSelect("SELECT event_profile_event_form.element_value"
                    . " FROM event_profile_event_form JOIN event_form_elements"
                    . " on event_profile_event_form.form_element_id = event_form_elements.form_element_id "
                    . "WHERE event_profile_event_form.profile_id =:profile_id AND"
                    . " event_form_elements.eventID = :eventID and "
                    . "event_form_elements.linked_ticket = 1 limit 1",
                    [":profile_id" => $result[0]['profile_id'],
                        ":eventID" => $result[0]['eventID']]);
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " |viewTicketDetailsSQL:" . json_encode($customFields));
            $customerFieldName = "";
            if ($customFields) {
                $customerFieldName = $customFields[0]['element_value'];
            }
            if ($barcodeURL != null && $customerFieldName != "") {
                $result[0]['alias_name'] = $customerFieldName;
            }

            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket results ($stop_time Seconds)"
                        , 'record_count' => $result[0]['total'],
                        'data' => $result, 'custom_field' => $customerFieldName]);
        } catch (Exception $ex) {

            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewUserTickets
     * @return type
     */
    public function viewUserTickets() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Redeemed Ticket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $msisdnNew = isset($data->msisdn) ? $data->msisdn : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : 1;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($msisdnNew) || $this->checkForMySQLKeywords($eventID) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$eventID || !$msisdnNew || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        $msisdn = $this->formatMobileNumber($msisdnNew);
        if (!$this->validateMobile($msisdn)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
        }
        try {
            if (!in_array($source, ['USSD', 'WEB'])) {
                $auth = new Authenticate();
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
                if (!in_array($auth_response['userRole'], [1, 6, 7, 8])) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'User doesn\'t have permissions to perform this action.');
                }
            } else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }

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
            $checkProfile = Profile::findFirst(["msisdn=:msisdn:",
                        "bind" => ["msisdn" => $msisdn],]);
            $profile_id = isset($checkProfile->profile_id) ?
                    $checkProfile->profile_id : false;
            if (!$profile_id) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "User Not Found"
                                , ['code' => 404, 'Message' => 'No Tickets Found'
                            . ' .User Not Found'], true);
            }
            $select_ticket_profile = "select event_profile_tickets.isRemmend, "
                    . "event_profile_tickets.isComplimentary, "
                    . "event_profile_tickets.barcode,event_profile_tickets_state.status,"
                    . " event_profile_tickets.event_ticket_id,"
                    . "event_tickets_type.eventId,(select "
                    . "count(event_profile_tickets.event_profile_ticket_id) "
                    . "from event_profile_tickets"
                    . " join profile on event_profile_tickets.profile_id "
                    . " =  profile.profile_id join event_tickets_type on"
                    . " event_tickets_type.event_ticket_id  ="
                    . " event_profile_tickets.event_ticket_id join"
                    . " event_profile_tickets_state on "
                    . "event_profile_tickets_state.event_profile_ticket_id = "
                    . "event_profile_tickets.event_profile_ticket_id where"
                    . " profile.msisdn =:msisdn and event_tickets_type.eventId "
                    . "= :eventId) as total from event_profile_tickets"
                    . " join profile on event_profile_tickets.profile_id "
                    . " =  profile.profile_id join event_tickets_type on"
                    . " event_tickets_type.event_ticket_id  ="
                    . " event_profile_tickets.event_ticket_id join"
                    . " event_profile_tickets_state on "
                    . "event_profile_tickets_state.event_profile_ticket_id = "
                    . "event_profile_tickets.event_profile_ticket_id where"
                    . " event_profile_tickets.isRemmend != 1 AND "
                    . "event_profile_tickets_state.status=1 AND "
                    . "profile.msisdn =:msisdn and "
                    . "event_tickets_type.eventId = :eventId";

            $result = $this->rawSelect($select_ticket_profile,
                    [':msisdn' => $msisdn, ':eventId' => $eventID]);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Records for Ticket ( $stop_time Seconds)"
                            , 'data' => []], true);
            }

            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Ticket results ($stop_time Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewEventTicketPurchase
     * @return type
     */
    public function viewEventTicketPurchase() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $export = $this->request->get('export') ? $this->request->get('export') : 0;
            $status = $this->request->get('status') ? $this->request->get('status') : 1;

            if ($this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($currentPage) || $this->checkForMySQLKeywords($perPage) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($end) || $this->checkForMySQLKeywords($export) || $this->checkForMySQLKeywords($status)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }
            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = " select transaction_initiated.transaction_id,event_profile_tickets.event_profile_ticket_id,"
                    . "profile.msisdn, profile_attribute.first_name,user.email,"
                    . "profile_attribute.surname,profile_attribute.last_name,"
                    . "events.eventName,events.venue, event_tickets_type.amount,"
                    . " event_profile_tickets.barcode, event_profile_tickets.barcodeURL,"
                    . " event_profile_tickets_state.status, event_profile_tickets.created";

            $countQuery = " select count(event_profile_tickets.event_profile_ticket_id) as totalEventsTicketType  ";

            $baseQuery = " FROM  event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "profile on profile.profile_id  = event_profile_tickets.profile_id "
                    . "join profile_attribute on  profile_attribute.profile_id = "
                    . "profile.profile_id join user on profile.profile_id =  user.profile_id "
                    . "join event_tickets_type ON "
                    . "event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id JOIN events on "
                    . "events.eventID  = event_tickets_type.eventId join transaction_initiated "
                    . "on transaction_initiated.reference_id = event_profile_tickets.reference_id ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['events.eventName', 'events.venue',
                        'events.company', 'profile.msisdn', 'event_profile_tickets.barcode',
                        'event_profile_tickets_state.status', 'user.email',
                        'event_tickets_type.amount', 'event_profile_tickets.created',
                        'transaction_initiated.transaction_id'];

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

            $statusQuery = "event_profile_tickets_state.status = $status and event_profile_tickets.isShowTicket = 0 ";
            if ($status == 2) {
                $statusQuery = "event_profile_tickets_state.status = 0 and event_profile_tickets.isShowTicket = 0 ";
            }

            $whereQuery = $whereQuery ? "WHERE $whereQuery AND $statusQuery" : " WHERE $statusQuery";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by "
                    . "event_profile_tickets.event_profile_ticket_id ";
            if ($export == 0) {
                $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
                $selectQuery .= $queryBuilder;
            }

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalEventsTicketType'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/ticket/purcahse/view?status=$status&page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/ticket/purcahse/view?status=$status&page=$next_url";
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
                            , 'Internal Server Error.');

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
     * viewEventTicketPurchase
     * @return type
     */
    public function viewEventShowTicketPurchase() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        try {
            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';
            $export = $this->request->get('export') ? $this->request->get('export') : 0;
            $status = $this->request->get('status') ? $this->request->get('status') : 1;

            if ($this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($currentPage) || $this->checkForMySQLKeywords($perPage) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($end) || $this->checkForMySQLKeywords($export) || $this->checkForMySQLKeywords($status)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = " select transaction_initiated.transaction_id,event_profile_tickets.event_profile_ticket_id,"
                    . "profile.msisdn, profile_attribute.first_name,"
                    . "profile_attribute.surname,profile_attribute.last_name,"
                    . "events.eventName,events.venue, event_tickets_type.amount,"
                    . " event_profile_tickets.barcode, event_profile_tickets.barcodeURL,"
                    . " event_profile_tickets_state.status, event_profile_tickets.created";

            $countQuery = " select count(event_profile_tickets.event_profile_ticket_id) as totalEventsTicketType  ";

            $baseQuery = " FROM  event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "profile on profile.profile_id  = event_profile_tickets.profile_id "
                    . "join profile_attribute on  profile_attribute.profile_id = "
                    . "profile.profile_id join event_tickets_type ON "
                    . "event_tickets_type.event_ticket_id = "
                    . "event_profile_tickets.event_ticket_id JOIN events on "
                    . "events.eventID  = event_tickets_type.eventId join transaction_initiated "
                    . "on transaction_initiated.reference_id = event_profile_tickets.reference_id ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = "";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['events.eventName', 'events.venue',
                        'events.company', 'profile.msisdn',
                        'event_profile_tickets_state.status',
                        'event_tickets_type.amount', 'event_profile_tickets.created'];

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

            $statusQuery = "event_profile_tickets_state.status = $status and event_profile_tickets.isShowTicket = 0 ";
            if ($status == 2) {
                $statusQuery = "event_profile_tickets_state.status = 0 and event_profile_tickets.isShowTicket = 0 ";
            }

            $whereQuery = $whereQuery ? "WHERE $whereQuery AND $statusQuery" : " WHERE $statusQuery";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by "
                    . "event_profile_tickets.event_profile_ticket_id ";
            if ($export == 0) {
                $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
                $selectQuery .= $queryBuilder;
            }

            $count = $this->rawSelect($countQuery, [], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalEventsTicketType'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/ticket/purcahse/view?status=$status&page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/v1/api/event/ticket/purcahse/view?status=$status&page=$next_url";
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
                            , 'Internal Server Error.');

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
     * resendTicketInformation
     * @return type Description
     */
    public function resendTicketInformation() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventprofileID = isset($data->eventprofileID) ? $data->eventprofileID : null;
        $msisdn = isset($data->msisdn) ? $data->msisdn : NULL;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($eventprofileID) ||
                $this->checkForMySQLKeywords($msisdn) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$eventprofileID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($msisdn != null) {
            $msisdn = $this->formatMobileNumber($msisdn);
            if (!$this->validateMobile($msisdn)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Mobile Number']);
            }
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
            $select_ticket_profile = "select event_profile_tickets.barcode,event_tickets_type.eventId,"
                    . "event_profile_tickets.barcodeURL,ticket_types.ticket_type,events.posterURL,"
                    . " event_profile_tickets_state.status,event_tickets_type.amount,"
                    . "events.start_date,events.venue,events.eventName,events.dateInfo,"
                    . "event_profile_tickets.profile_id from event_profile_tickets join "
                    . "event_profile_tickets_state on "
                    . "event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "event_tickets_type on event_tickets_type.event_ticket_id =  "
                    . "event_profile_tickets.event_ticket_id join events on "
                    . "events.eventID = event_tickets_type.eventId  join "
                    . "ticket_types on event_tickets_type.typeId = ticket_types.typeId WHERE"
                    . " event_profile_tickets.event_profile_ticket_id=:event_profile_ticket_id";

            $check_trxn_profile = $this->rawSelect($select_ticket_profile,
                    [':event_profile_ticket_id' => $eventprofileID]);

            if (!$check_trxn_profile) {

                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event Profile Ticket ID']);
            }

            if ($check_trxn_profile[0]['status'] == 0) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Payment status Error'
                                , ['code' => 422, 'message' => 'Failed to'
                            . ' resend message as payment has not been done. Kindly '
                            . 'advice client to make payment']);
            }
            $sentPhoneNumber = Profiling::QueryMobile($check_trxn_profile[0]['profile_id']);
            $profileAttribute = Profiling::QueryProfileMobile($sentPhoneNumber);
            if ($msisdn != null) {
                $sentPhoneNumber = $msisdn;
            }
            foreach ($check_trxn_profile as $succ) {
                $sms .= "Dear " . $profileAttribute['first_name'] . " " . $profileAttribute['last_name'] . ", Your " . $succ['eventName'] . " ticket "
                        . "is " . $succ['barcode'] . ". View your ticket from "
                        . $this->settings['TicketBaseURL'] . "?evtk=" . $succ['barcode'] . " \n";

                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $sentPhoneNumber,
                    "message" => $sms,
                    "profile_id" => $check_trxn_profile[0]['profile_id'],
                    "created_by" => 'CUSTOMER_' . $source,
                    "is_bulk" => false,
                    "link_id" => ""];

                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($params);

                $paramsEmail = [
                    "eventID" => $succ['eventId'],
                    "type" => "TICKET_PURCHASED",
                    "name" => $profileAttribute['first_name'] . " "
                    . "" . $profileAttribute['surname'] . " " . $profileAttribute['last_name'],
                    "eventDate" => $succ['start_date'],
                    "eventName" => $succ['eventName'],
                    "eventAmount" => $succ['amount'],
                    'eventType' => $succ['ticketType'],
                    'QRcodeURL' => $succ['barcodeURL'],
                    'QRcode' => $succ['barcode'],
                    'posterURL' => $succ['posterURL'],
                    'venue' => $succ['venue']
                ];
                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $profileAttribute['email'],
                    "cc" => "",
                    "subject" => "Ticket for Event: " . $succ['eventName'],
                    "content" => "Ticket information",
                    "extrac" => $paramsEmail
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailWithoutAttachments Response::" . json_encode($mailResponse));
            }
            return $this->success(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                            , 'Ticket Sent Successful', ['code' => 200
                        , 'message' => 'Ticket Sent successful']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * addEmailTemplate
     * @return type
     */
    public function addEmailTemplate() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | addEmailTemplate Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $template = isset($data->template) ? $data->template : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $templateType = isset($data->templateType) ? $data->templateType : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($template) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($templateType) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$source || !$eventID || !$template || !$templateType) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!in_array($templateType, $this->settings['TemplateTypes'])) {
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
            $checkEvents = Events::findFirst([
                        "eventID =:eventID: ",
                        "bind" => [
                            "eventID" => $eventID],]);
            if (!$checkEvents) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Event Id']);
            }
            $checkEmailTemplate = "SELECT * FROM event_email_template WHERE eventId=:eventId AND type=:type";
            $resultCheck = $this->rawSelect($checkEmailTemplate, [':eventId' => $eventID, ':type' => $templateType]);
            if (!$resultCheck) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'The template existx']);
            }
            $sql = "INSERT INTO event_email_template (eventId,template,type,created)"
                    . " VALUES (:eventId,:template,:type,:created)";
            $params = [
                ':eventId' => $eventID,
                ':template' => $template,
                ':type' => $templateType,
                ':created' => $this->now()
            ];
            $result = $this->rawInsert($sql, $params);
            $data_array = [
                'code' => 202,
                'message' => 'Failed insert record'];
            if (!$result) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket type Action failed"
                                , $data_array, true);
            }
            $data_array = [
                'code' => 200,
                'message' => 'Successful insert record'];

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Event Template Action successful"
                            , $data_array);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * shareTicket
     * @return type
     */
    public function shareTicket() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | shareTicket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $phone = isset($data->msisdn) ? $data->msisdn : null;
        $name = isset($data->name) ? $data->name : null;
        $email = isset($data->email) ? $data->email : null;
        $barcode = isset($data->barcode) ? $data->barcode : null;
        $source = isset($data->source) ? $data->source : null;
        $show = isset($data->show) ? $data->show : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($phone) ||
                $this->checkForMySQLKeywords($name) ||
                $this->checkForMySQLKeywords($email) ||
                $this->checkForMySQLKeywords($barcode) ||
                $this->checkForMySQLKeywords($show) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$phone || !$barcode || !$name || !$show) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($name) {
            $names = explode(" ", $name);
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
            if (!in_array($auth_response['userRole'], [1, 2])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }


            if ($show == 1) {
                $sql = "SELECT "
                        . "event_profile_tickets.event_profile_ticket_id,"
                        . "event_profile_tickets.event_ticket_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.profile_id,event_profile_tickets.barcode,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.barcodeURL FROM event_profile_tickets "
                        . " join event_profile_tickets_state on "
                        . "event_profile_tickets_state.event_profile_ticket_id = "
                        . "event_profile_tickets.event_profile_ticket_id JOIN"
                        . " event_show_tickets_type on event_show_tickets_type.event_ticket_show_id"
                        . " = event_profile_tickets.event_ticket_id join ticket_types"
                        . " on ticket_types.typeId = event_show_tickets_type.typeId "
                        . " WHERE event_profile_tickets.barcode=:barcode  "
                        . "AND event_profile_tickets_state.status = 1 AND "
                        . "event_profile_tickets.isShowTicket = 1";
            } else {
                $sql = "SELECT "
                        . "event_profile_tickets.event_profile_ticket_id,"
                        . "event_profile_tickets.event_ticket_id,ticket_types.ticket_type,"
                        . "event_profile_tickets.profile_id,event_profile_tickets.barcode,"
                        . "event_profile_tickets.isRemmend,event_profile_tickets.barcodeURL FROM event_profile_tickets "
                        . " join event_profile_tickets_state on "
                        . "event_profile_tickets_state.event_profile_ticket_id = "
                        . "event_profile_tickets.event_profile_ticket_id JOIN"
                        . " event_tickets_type on event_tickets_type.event_ticket_id"
                        . " = event_profile_tickets.event_ticket_id join ticket_types"
                        . " on ticket_types.typeId = event_tickets_type.typeId "
                        . " WHERE event_profile_tickets.barcode=:barcode  "
                        . "AND event_profile_tickets_state.status = 1";
            }
            $eventProfileTickets = $this->selectQuery($sql
                    , [':barcode' => $barcode]);

            if (!$eventProfileTickets) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Event Ticket Not Found'
                                , ['code' => 404, 'message' => 'Request Failed. Event ticket not found!!']);
            }
//            if ($eventProfileTickets[0]['profile_id'] != $auth_response['profile_id']) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'You are not authorised to share this ticket');
//            }
            if ($eventProfileTickets[0]['isRemmend']) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Event Ticket has been redemmed'
                                , ['code' => 402, 'message' => 'Request Failed. Event Ticket has been redemmed!!']);
            }
            if ($show == 1) {
                $checkEventTicketIDNew = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                            "bind" => ["event_ticket_show_id" => $eventProfileTickets[0]['event_ticket_id']],]);

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
                $eventID = Tickets::queryEventTicketType(['event_ticket_id' => $eventProfileTickets[0]['event_ticket_id']]);
            }

            if (!$eventID) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Event Ticket Not Found'
                                , ['code' => 404, 'message' => 'Request Failed.'
                            . ' Event ticket not found!!']);
            }
            $checkEvents = Events::findFirst(["eventID=:eventID:",
                        "bind" => ["eventID" => $eventID],]);
            if (!$checkEvents) {

                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Event Not Found'
                                , ['code' => 404, 'message' => 'Request Failed.'
                            . ' Event not found!!']);
            }
            if ($checkEvents->status != 1) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Event is not active. You cannot share the ticket'
                                , ['code' => 404, 'message' => 'Request Failed. '
                            . 'Event is not active. You cannot share the ticket!!']);
            }
            if ($this->now() > $checkEvents->end_date) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Event is Closed. You cannot share ticket'
                                , ['code' => 404, 'message' => 'Request Failed. '
                            . 'Event is Closed. You cannot share ticket!!']);
            }

            $profile_id = Profiling::Profile($msisdn);

            $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id = :profile_id:'
                        , 'bind' => ['profile_id' => $profile_id]]);
            $verification_code = rand(1000, 9999);
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();

            if (!$checkProfileAttrinute) {
                $profileAttribute = new ProfileAttribute();
                $profileAttribute->network = $this->getMobileNetwork($msisdn);
                $profileAttribute->pin = md5($verification_code);
                $profileAttribute->profile_id = $profile_id;
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
            $t = time();
            $QRCode = rand(1000000, 99999999999999) . "" . $t;
            $barCode = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . $QRCode . '&choe=UTF-8';

            $params = [
                'event_profile_ticket_id' => $eventProfileTickets[0]['event_profile_ticket_id'],
                'user_id' => $auth_response['user_id'],
                'profile_id' => $profile_id,
                'barcode' => $QRCode,
                'barcodeURL' => $barCode
            ];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | share params:" . json_encode($params));

            $sharedID = Tickets::shareTickets($params);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | addEmailTemplate SHAREDID:" . $sharedID);
            if (!$sharedID) {
                return $this->dataError(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Unbale to share ticket. Contact Madfun System Admin']);
            }
            $sms = "Dear " . $name . ", Your " . $checkEvents->eventName . " ticket "
                    . "is " . $eventProfileTickets[0]['barcode'] . ". View your ticket from "
                    . $this->settings['TicketBaseURL'] . "?evtk=" . $eventProfileTickets[0]['barcode'] . "."
                    . " Madfun! For Queries call "
                    . "" . $this->settings['Helpline'];

            $paramsSMS = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                "msisdn" => $msisdn,
                "message" => $sms,
                "profile_id" => Profiling::Profile($msisdn),
                "created_by" => 'SHARETICKET_' . $auth_response['user_id'],
                "is_bulk" => false,
                "link_id" => ""];

            $message = new Messaging();
            $queueMessageResponse = $message->LogOutbox($paramsSMS);
            $smsStatus = false;
            if ($queueMessageResponse) {
                $smsStatus = true;
            }
            if ($email != null) {

                $paramsEmail = [
                    "eventID" => $eventID,
                    "type" => "TICKET_SHARED",
                    "name" => $name,
                    "eventDate" => $checkEvents->start_date,
                    "eventName" => $checkEvents->eventName,
                    "eventAmount" => "0",
                    'eventType' => $eventProfileTickets[0]['ticket_type'],
                    'QRcodeURL' => $eventProfileTickets[0]['barcodeURL'],
                    'QRcode' => $eventProfileTickets[0]['barcode'],
                    'posterURL' => $checkEvents->posterURL,
                    'venue' => $checkEvents->venue
                ];
                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $email,
                    "cc" => "",
                    "subject" => "Ticket for Event: " . $checkEvents->eventName,
                    "content" => "Ticket information",
                    "extrac" => $paramsEmail
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailTickets Response::" . json_encode($mailResponse));
            }
            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Ticket Has been shared successful"
                            , ["sharedID" => $sharedID]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function mapUserToEvent() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $fname = isset($data->fname) ? $data->fname : NULL;
        $sname = isset($data->sname) ? $data->sname : NULL;
        $lname = isset($data->lname) ? $data->lname : NULL;
        $email = isset($data->email) ? $data->email : NULL;
        $eventID = isset($data->eventID) ? $data->eventID : NULL;
        $msisdnNew = isset($data->msisdn) ? $data->msisdn : NULL;
        $clientId = isset($data->clientId) ? $data->clientId : 1;
        $role_id = isset($data->role_id) ? $data->role_id : NULL;
        $source = isset($data->source) ? $data->source : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($fname) ||
                $this->checkForMySQLKeywords($sname) ||
                $this->checkForMySQLKeywords($email) ||
                $this->checkForMySQLKeywords($lname) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($clientId) ||
                $this->checkForMySQLKeywords($msisdnNew) ||
                $this->checkForMySQLKeywords($role_id) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$email || !$msisdnNew || !$role_id || !$eventID || !$fname) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!in_array($role_id, [2, 6, 7, 8])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Invalid Role');
        }
        $msisdn = $this->formatMobileNumber($msisdnNew);
//        if (!$this->validateMobile($msisdn)) {
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                            , 'Validation Error'
//                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
//        }
        if ($email != null && !$this->validateEmail($email)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Client Email']);
        }
        try {
//            $auth = new Authenticate();
//            $auth_response = $auth->QuickTokenAuthenticate($token);
//            if (!$auth_response) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'Authentication Failure.');
//            }
//            if (!in_array($auth_response['userRole'], [1, 2, 6, 7, 8])) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'User doesn\'t have permissions to perform this action.');
//            }
//            if ($auth_response['userRole'] == 6 && ($role_id == 2 || $role_id == 1)) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'User doesn\'t have permissions to add this user');
//            }
            $eventData = Events::findFirst(["eventID=:eventID:",
                        "bind" => ["eventID" => $eventID],]);

            if (!$eventData) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 404, 'message' => 'Event does not exist']);
            }

            $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
            $len = rand(1000, 999999);
            $payloadToken = ['data' => $len . "" . $this->now()];
            $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
            $verification_code = rand(1000, 9999);
            $password = $this->security->hash(md5($verification_code));

            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Create User MSISDN:" . $msisdn);
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
                if ($checkProfileAttrinute) {
                    $checkProfileAttrinute->setTransaction($dbTrxn);
                    $checkProfileAttrinute->first_name = $fname;
                    $checkProfileAttrinute->surname = $sname;
                    $checkProfileAttrinute->last_name = $lname;
                    if ($checkProfileAttrinute->save() === false) {
                        $errors = [];
                        $messages = $checkProfileAttrinute->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Update Profile Attribute failed " . json_encode($errors));
                    }
                } else {
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
                    $user->role_id = $role_id;
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
                    if($checkUser->role_id != 1){
                       $checkUser->role_id = $role_id; 
                    }
                    $checkUser->updated = $this->now();
                    if ($checkUser->save() === false) {
                        $errors = [];
                        $messages = $checkUser->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Update User failed " . json_encode($errors));
                    }
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
                }
                $checkUserClientMap = UserClientMap::findFirst(['client_id=:client_id: AND user_id =:user_id:'
                            , 'bind' => ['client_id' => $clientId, 'user_id' => $user_id]]);

                $user_mapId = isset($checkUserClientMap->user_mapId) ?
                        $checkUserClientMap->user_mapId : false;

                if (!$checkUserClientMap) {
                    $userClientMap = new UserClientMap;
                    $userClientMap->setTransaction($dbTrxn);
                    $userClientMap->client_id = $clientId;
                    $userClientMap->user_id = $user_id;
                    $userClientMap->created_by = 1;
                    $userClientMap->created_at = $this->now();
                    if ($userClientMap->save() === false) {
                        $errors = [];
                        $messages = $userClientMap->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create User Client Map failed. Reason" . json_encode($errors));
                    }
                    $user_mapId = $userClientMap->user_mapId;
                }

                $checkUserEventMap = UserEventMap::findFirst(['user_mapId=:user_mapId: AND eventID =:eventID:'
                            , 'bind' => ['user_mapId' => $user_mapId, 'eventID' => $eventID]]);
                if ($checkUserEventMap) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , 'User Already Mapped'
                                    , ['code' => 200, 'message' => 'User'
                                . ' Already Mapped to event: ' . $eventData->eventName], true);
                }
                $userEventMap = new UserEventMap();
                $userEventMap->setTransaction($dbTrxn);
                $userEventMap->eventID = $eventID;
                $userEventMap->user_mapId = $user_mapId;
                $userEventMap->created = $this->now();
                if ($userEventMap->save() === false) {
                    $errors = [];
                    $messages = $userEventMap->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create User Event Map failed " . json_encode($errors));
                }

                $dbTrxn->commit();

                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'User  Mapped  Successful'
                                , ['code' => 200, 'message' => 'User'
                            . ' Mapped  Successful to event: ' . $eventData->eventName]);
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

    public function addAffiliatorToEvent() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $fname = isset($data->fname) ? $data->fname : NULL;
        $sname = isset($data->sname) ? $data->sname : NULL;
        $lname = isset($data->lname) ? $data->lname : NULL;
        $email = isset($data->email) ? $data->email : NULL;
        $eventID = isset($data->eventID) ? $data->eventID : NULL;
        $msisdnNew = isset($data->msisdn) ? $data->msisdn : NULL;
        $clientId = isset($data->clientId) ? $data->clientId : 1;
        $role_id = isset($data->role_id) ? $data->role_id : 9;
        $source = isset($data->source) ? $data->source : null;
        $discount = isset($data->discount) ? $data->discount : 0;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($fname) ||
                $this->checkForMySQLKeywords($sname) ||
                $this->checkForMySQLKeywords($email) ||
                $this->checkForMySQLKeywords($lname) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($clientId) ||
                $this->checkForMySQLKeywords($msisdnNew) ||
                $this->checkForMySQLKeywords($role_id) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$email || !$msisdnNew || !$role_id || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!in_array($role_id, [9])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Invalid Role');
        }
        $msisdn = $this->formatMobileNumber($msisdnNew);
        if (!$this->validateMobile($msisdn)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
        }
        if ($email != null && !$this->validateEmail($email)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Client Email']);
        }
        try {
//            $auth = new Authenticate();
//            $auth_response = $auth->QuickTokenAuthenticate($token);
//            if (!$auth_response) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'Authentication Failure.');
//            }
//            if (!in_array($auth_response['userRole'], [1, 2, 6, 7, 8])) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'User doesn\'t have permissions to perform this action.');
//            }
//            if ($auth_response['userRole'] == 6 && ($role_id == 2 || $role_id == 1)) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'User doesn\'t have permissions to add this user');
//            }
            $eventData = Events::findFirst(["eventID=:eventID:",
                        "bind" => ["eventID" => $eventID],]);

            if (!$eventData) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 404, 'message' => 'Event does not exist']);
            }
//            if ($eventData->hasAffiliator != 1) {
//                return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                                , 'Affiliator not Enable Event'
//                                , ['code' => 404, 'message' => 'Affiliator not Enable Event']);
//            }

            $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
            $len = rand(1000, 999999);
            $payloadToken = ['data' => $len . "" . $this->now()];
            $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
            $verification_code = rand(1000, 9999);
            $password = $this->security->hash(md5($verification_code));

            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
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
                    $user->role_id = $role_id;
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

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | Create User Request:" . $user_id);
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
                }
                $checkUserClientMap = UserClientMap::findFirst(['client_id=:client_id: AND user_id =:user_id:'
                            , 'bind' => ['client_id' => $clientId, 'user_id' => $user_id]]);

                $user_mapId = isset($checkUserClientMap->user_mapId) ?
                        $checkUserClientMap->user_mapId : false;

                if (!$checkUserClientMap) {
                    $userClientMap = new UserClientMap;
                    $userClientMap->setTransaction($dbTrxn);
                    $userClientMap->client_id = $clientId;
                    $userClientMap->user_id = $user_id;
                    $userClientMap->created_by = $user_id;
                    $userClientMap->created_at = $this->now();
                    if ($userClientMap->save() === false) {
                        $errors = [];
                        $messages = $userClientMap->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }

                        $dbTrxn->rollback("Create User Client Map failed. Reason" . json_encode($errors));
                    }
                    $user_mapId = $userClientMap->user_mapId;
                }

                $checkUserEventMap = UserEventMap::findFirst(['user_mapId=:user_mapId: AND eventID =:eventID:'
                            , 'bind' => ['user_mapId' => $user_mapId, 'eventID' => $eventID]]);
                if (!$checkUserEventMap) {
                    $userEventMap = new UserEventMap();
                    $userEventMap->setTransaction($dbTrxn);
                    $userEventMap->eventID = $eventID;
                    $userEventMap->user_mapId = $user_mapId;
                    $userEventMap->created = $this->now();
                    if ($userEventMap->save() === false) {
                        $errors = [];
                        $messages = $userEventMap->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create User Event Map failed " . json_encode($errors));
                    }
                }

                $checkAffiliator = Affiliators::findFirst(['user_id=:user_id:'
                            , 'bind' => ['user_id' => $user_id]]);
                $affiliatorID = isset($checkAffiliator->affilator_id) ?
                        $checkAffiliator->affilator_id : false;
                if (!$affiliatorID) {
                    $affiliator = new Affiliators();
                    $affiliator->setTransaction($dbTrxn);
                    $affiliator->user_id = $user_id;
                    $affiliator->status = 1;
                    $affiliator->created = $this->now();
                    if ($affiliator->save() === false) {
                        $errors = [];
                        $messages = $affiliator->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Create Affiliator failed " . json_encode($errors));
                    }
                    $affiliatorID = $affiliator->affilator_id;
                }
                $code = $this->AlphaNumericIdGenerator($this->now('YmdGis') . "" . rand(10, 99));

                $checkAffiliatorEventMap = AffiliatorEventMap::findFirst(['affilator_id=:affilator_id: AND eventId=:eventId:'
                            , 'bind' => ['affilator_id' => $affiliatorID, 'eventId' => $eventID]]);
                if ($checkAffiliatorEventMap) {
                    $checkAffiliatorEventMap->setTransaction($dbTrxn);
                    $checkAffiliatorEventMap->discount = $discount;
                    $checkAffiliatorEventMap->status = 1;
                    $checkAffiliatorEventMap->code = $code;
                    $checkAffiliatorEventMap->updated = $this->now();
                    if ($checkAffiliatorEventMap->save() === false) {
                        $errors = [];
                        $messages = $checkAffiliatorEventMap->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Update Affiliator failed " . json_encode($errors));
                    }
                } else {
                    $AffiliatorEventMap = new AffiliatorEventMap();
                    $AffiliatorEventMap->setTransaction($dbTrxn);
                    $AffiliatorEventMap->code = $code;
                    $AffiliatorEventMap->status = 1;
                    $AffiliatorEventMap->affilator_id = $affiliatorID;
                    $AffiliatorEventMap->eventId = $eventID;
                    $AffiliatorEventMap->discount = $discount;
                    $AffiliatorEventMap->created = $this->now();
                    if ($AffiliatorEventMap->save() === false) {
                        $errors = [];
                        $messages = $AffiliatorEventMap->getMessages();
                        foreach ($messages as $message) {
                            $e["statusDescription"] = $message->getMessage();
                            $e["field"] = $message->getField();
                            array_push($errors, $e);
                        }
                        $dbTrxn->rollback("Created Affiliator Event Map failed " . json_encode($errors));
                    }
                }
                $dbTrxn->commit();
                if ($eventData->eventTag) {
                    $eventID = $eventData->eventTag;
                }

                $endpointUrl = $this->settings['EventBaseURL'] . "" . $eventID;
                if ($eventData->isFree == 1) {

                    $endpointUrl = $this->settings['EventFreeBaseURL'] . "" . $eventID;
                }
                $shareLink = $endpointUrl . "/" . $code;
                $smsMessage = "Hello " . $fname . ", Your affiliator account for "
                        . "event " . $eventData->eventName . " has been configured successfully. "
                        . "Find the link below to share.\n\nLink: " . $shareLink;
                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderId'],
                    "msisdn" => $msisdn,
                    "message" => $smsMessage,
                    "profile_id" => $profile_id,
                    "created_by" => 'AFFILIATOR',
                    "is_bulk" => true,
                    "link_id" => ""];

                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($params);
                if (!$queueMessageResponse) {

                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , 'Affiliator  Mapped  Successful. Failed to Send SMS'
                                    , ['code' => 200, 'message' => 'Affiliator'
                                . ' Mapped  Successful to event: ' . $eventData->eventName], TRUE);
                }
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Affiliator  Mapped  AND sms Sent Successful'
                                , ['code' => 200, 'message' => 'Affiliator'
                            . ' Mapped  Successful to event: ' . $eventData->eventName]);
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

    public function viewAffiliator() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Redeemed Ticket Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $code = isset($data->code) ? $data->code : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $source = isset($data->source) ? $data->source : 1;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($code) ||
                $this->checkForMySQLKeywords($eventID) ||
                $this->checkForMySQLKeywords($source) ||
                $this->checkForMySQLKeywords($limit) ||
                $this->checkForMySQLKeywords($offset) ||
                $this->checkForMySQLKeywords($sort)) {
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
            $sort = "affiliator_event_map.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'affiliator_event_map.id';
            $order = 'DESC';
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['USSD', 'WEB'])) {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }
            $searchQuery = "WHERE 1";
            if ($code != NULL) {
                $code = strtoupper($this->cleanString($code));
                $searchQuery .= " AND affiliator_event_map.code = '$code' ";
            }
            if ($eventID) {
                //$eventID = (INT) $eventID;
                $searchQuery .= " AND (events.eventID = '$eventID' || events.eventTag regexp '$eventID') ";
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = "select profile.msisdn, profile_attribute.first_name,"
                    . "profile_attribute.surname,profile_attribute.last_name,"
                    . "user.email,events.eventName,affiliator_event_map.code,"
                    . "affiliator_event_map.id, affiliator_event_map.status, "
                    . "affiliator_event_map.discount,affiliators.created,(select"
                    . " count(affiliator_event_map.id) from affiliator_event_map "
                    . "join  affiliators on affiliators.affilator_id = "
                    . "affiliator_event_map.affilator_id join user on"
                    . " user.user_id = affiliators.user_id join profile on"
                    . " user.profile_id = profile.profile_id join "
                    . "profile_attribute on profile_attribute.profile_id "
                    . "= profile.profile_id join events on events.eventID"
                    . " = affiliator_event_map.eventId $searchQuery)"
                    . " as total from affiliator_event_map join affiliators "
                    . "on affiliators.affilator_id = affiliator_event_map.affilator_id "
                    . "join user on user.user_id = affiliators.user_id join "
                    . "profile on user.profile_id = profile.profile_id join"
                    . " profile_attribute on profile_attribute.profile_id = "
                    . "profile.profile_id join events on events.eventID = "
                    . "affiliator_event_map.eventId $searchQuery $sorting";
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ViewAffiator SQL:" . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_end = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Affiliator Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_end Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stop_end = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Affiliators results ($stop_end Seconds)"
                        , 'record_count' => $result[0]['total'], 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function editAffiliator() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $affiliatorEventMapId = isset($data->affiliatorEventMapId) ? $data->affiliatorEventMapId : NULL;
        $status = isset($data->status) ? $data->status : NULL;
        $discount = isset($data->discount) ? $data->discount : null;
        $source = isset($data->source) ? $data->source : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($affiliatorEventMapId) ||
                $this->checkForMySQLKeywords($status) ||
                $this->checkForMySQLKeywords($discount) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$affiliatorEventMapId || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($discount != null && !is_numeric($discount)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Discount Amount. Has to numeric']);
        }
        if ($status != null && !in_array($status, [1, 0, 2])) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Status']);
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $checkAffiliatorEventMap = AffiliatorEventMap::findFirst(['id=:affiliatorEventMapId:'
                        , 'bind' => ['affiliatorEventMapId' => $affiliatorEventMapId]]);
            if (!$checkAffiliatorEventMap) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Not Found'
                                , ['code' => 404, 'message' => 'Affilator Event Map ID Not found ']);
            }
            $checkAffiliatorEventMap->setTransaction($dbTrxn);
            if ($discount != null) {
                $checkAffiliatorEventMap->discount = $discount;
            }

            $checkAffiliatorEventMap->status = $status;

            $checkAffiliatorEventMap->updated = $this->now();
            if ($checkAffiliatorEventMap->save() === false) {
                $errors = [];
                $messages = $checkAffiliatorEventMap->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Update Affiliator failed " . json_encode($errors));
            }
            $dbTrxn->commit();
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Affiliator  Updated Successful'
                            , ['code' => 200, 'message' => 'Affiliator  Updated Successful']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * refundTickets
     * @return type
     */
    public function refundTickets() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Refund Tickets Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $event_profile_ticket_id = isset($data->event_profile_ticket_id) ? $data->event_profile_ticket_id : NULL;
        $refunded_date = isset($data->refunded_date) ? $data->refunded_date : null;
        $purpose = isset($data->purpose) ? $data->purpose : null;
        $source = isset($data->source) ? $data->source : null;

        if (
                $this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($event_profile_ticket_id) ||
                $this->checkForMySQLKeywords($purpose) ||
                $this->checkForMySQLKeywords($refunded_date) ||
                $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$source || !$event_profile_ticket_id || !$refunded_date) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        $start_date_app_1 = strtotime($refunded_date);
        $refunded_date_final = date('Y-m-d H:i:s', $start_date_app_1);

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
            $sqlProfile = "SELECT * from event_profile_tickets WHERE "
                    . "event_profile_ticket_id = " . $event_profile_ticket_id;

            $eventProfileTickets = $this->rawSelect($sqlProfile);

            if (!$eventProfileTickets) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Profile Tickets']);
            }

            if ($eventProfileTickets[0]['hasRefunded'] == 1) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Account has been '
                            . 'refunded already']);
            }

            $update_stream_profile = "update event_profile_tickets set"
                    . " hasRefunded = 1, refundPurpose=:refundPurpose,"
                    . " refunded_date=:refunded_date WHERE "
                    . " event_profile_ticket_id=:event_profile_ticket_id";

            $this->rawUpdateWithParams($update_stream_profile,
                    [':event_profile_ticket_id' => $event_profile_ticket_id,
                        ':refunded_date' => $refunded_date_final,
                        ':refundPurpose' => $purpose]);

            if ($eventProfileTickets[0]['isShowTicket'] == 1) {
                $sql = "UPDATE event_show_tickets_type set "
                        . "ticket_purchased = ticket_purchased - 1 WHERE "
                        . "event_ticket_show_id=:event_ticket_id LIMIT 1";
            } else {
                $sql = "UPDATE event_tickets_type set "
                        . "total_refund = total_refund + 1 WHERE "
                        . "event_ticket_id=:event_ticket_id LIMIT 1";
            }

            $result = $this->rawUpdateWithParams($sql,
                    [':event_ticket_id' => $eventProfileTickets[0]['event_ticket_id']]);

            if (!$result) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Failed to update refund']);
            }

            $this->rawUpdateWithParams("UPDATE event_profile_tickets_state set "
                    . "status = 5 where event_profile_ticket_id=:event_profile_ticket_id "
                    . "limit 1", [':event_profile_ticket_id' => $event_profile_ticket_id]);

            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Refund  Updated Successful'
                            , ['code' => 200, 'message' => 'Refund  Updated Successful']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
}
