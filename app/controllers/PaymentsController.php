<?php

/**
 * Description of PaymentsController
 *
 * @author kevinmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class PaymentsController extends ControllerBase {

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
     * viewBanks
     * @return type
     * @throws Exception
     */
    public function viewBanks() {
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

            $selectQuery = "SELECT bank.bank_id, bank.bank,bank.bankCode, bank.`status`, "
                    . "bank.paybill,bank.created ";

            $countQuery = "select count(bank.bank_id) as totalBanks  ";

            $baseQuery = "from bank ";

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
                    $searchColumns = ['bank.bank', 'bank.paybill'];

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
                        $valueString = " DATE(bank.created) BETWEEN '$value[0]' AND '$value[1]'";
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
            $data->totalMatches = $count[0]['totalBanks'];
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
     * addBank
     * @return type
     * @throws Exception
     */
    public function addBank() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Bank:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $bankName = isset($data->bankName) ? $data->bankName : null;
        $paybillNumber = isset($data->paybillNumber) ? $data->paybillNumber : null;
        $address = isset($data->address) ? $data->address : null;
        $bankCode = isset($data->bankCode) ? $data->bankCode : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($bankName) || $this->checkForMySQLKeywords($paybillNumber) || $this->checkForMySQLKeywords($address) || $this->checkForMySQLKeywords($bankCode)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$bankName || !$paybillNumber || !$bankCode) {
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
            $checkQuery = $this->selectQuery("SELECT  * FROM bank WHERE"
                    . " bank REGEXP :bank", [':bank' => $bankName]);
            if ($checkQuery) {
                $data_array = [
                    'code' => 202,
                    'message' => 'Record exist'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket type Action Failed"
                                , $data_array, true);
            }

            $sql = "INSERT INTO bank (bank,address,bankCode,paybill,created) "
                    . "VALUES (:bank,:address,:bankCode,:paybill,:created)";
            $params = [
                ':bank' => $bankName,
                ':address' => $address,
                ':bankCode' => $bankCode,
                ':paybill' => $paybillNumber,
                ':created' => $this->now()
            ];
            $result = $this->rawInsert($sql, $params);

            if (!$result) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket type Action successful"
                                , [
                            'code' => 202,
                            'message' => 'Failed insert record'], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Ticket type Action Failed"
                            , [
                        'code' => 200,
                        'message' => 'Successful insert record']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * addInvoiceType
     * @return type
     * @throws Exception
     */
    public function addInvoiceType() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Add Bank:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $invoice_type = isset($data->invoice_type) ? $data->invoice_type : null;
        $description = isset($data->description) ? $data->description : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($invoice_type) || $this->checkForMySQLKeywords($description)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$invoice_type) {
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
            $checkQuery = $this->selectQuery("SELECT  * FROM invoice_type WHERE"
                    . " invoice_type REGEXP :invoice_type", [':invoice_type' => $invoice_type]);
            if ($checkQuery) {
                $data_array = [
                    'code' => 202,
                    'message' => 'Record exist'];

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Ticket type Action Failed"
                                , $data_array, true);
            }

            $sql = "INSERT INTO invoice_type (invoice_type,description,created) "
                    . "VALUES (:invoice_type,:description,:created)";
            $params = [
                ':invoice_type' => $invoice_type,
                ':description' => $description,
                ':created' => $this->now()
            ];
            $result = $this->rawInsert($sql, $params);

            if (!$result) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "Invoice type Action successful"
                                , [
                            'code' => 202,
                            'message' => 'Failed insert record'], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Invoice type Action Failed"
                            , [
                        'code' => 200,
                        'message' => 'Successful insert record']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewInvoiceType
     * @return type
     * @throws Exception
     */
    public function viewInvoiceType() {
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

            $selectQuery = "SELECT invoice_type.invoice_type_id, "
                    . "invoice_type.invoice_type, invoice_type.description, "
                    . "invoice_type.`status`, invoice_type.created  ";

            $countQuery = "select count(invoice_type.invoice_type_id) as totalInvoiceType  ";

            $baseQuery = "from invoice_type ";

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
                    $searchColumns = ['invoice_type.invoice_type_id',
                        'invoice_type.invoice_type'];

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
                        $valueString = " DATE(invoice_type.created) BETWEEN '$value[0]' AND '$value[1]'";
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
            $data->totalMatches = $count[0]['totalInvoiceType'];
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
                    $prev_url = "https://api.v1.interactive.madfun.com/v1/api/payments/view/invoice/type?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "https://api.v1.interactive.madfun.com/v1/api/payments/view/invoice/type?page=$next_url";
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
     * createInvoice
     * @return type
     * @throws Exception
     */
    public function createInvoice() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create Invoice:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $invoice_payment_type_id = isset($data->invoice_payment_type_id) ? $data->invoice_payment_type_id : false;
        $invoice_items = isset($data->invoice_items) ? $data->invoice_items : false;
        $invoice_description = isset($data->invoice_description) ? $data->invoice_description : null;
        $invoiceDate = isset($data->invoice_date) ? $data->invoice_date : null;
        $invoiceDueDate = isset($data->due_date) ? $data->due_date : null;
        $invoice_type_id = isset($data->invoice_type_id) ? $data->invoice_type_id : null;
        $beneficiary_reference = isset($data->beneficiary_reference) ? $data->beneficiary_reference : null;
        $invoice_to_client_id = isset($data->invoice_to_client_id) ? $data->invoice_to_client_id : null;
        $beneficary_data = isset($data->beneficary_data) ? $data->beneficary_data : null;
        $invoice_referenceID = isset($data->invoice_referenceID) ? $data->invoice_referenceID : null; // this can eventID

        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($invoice_description) ||
                $this->checkForMySQLKeywords($invoiceDate) ||
                $this->checkForMySQLKeywords($invoiceDueDate) ||
                $this->checkForMySQLKeywords($invoice_type_id) ||
                $this->checkForMySQLKeywords($beneficiary_reference) ||
                $this->checkForMySQLKeywords($invoice_to_client_id) ||
                $this->checkForMySQLKeywords($invoice_referenceID) ||
                $this->checkForMySQLKeywords($invoice_payment_type_id) ||
                $this->checkForMySQLKeywords($beneficary_data)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$invoice_items || !$invoice_description ||
                !$invoiceDate || !$invoiceDueDate || !$invoice_type_id ||
                !$invoice_to_client_id || !$invoice_referenceID || !$invoice_payment_type_id
        ) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$beneficiary_reference && !$beneficary_data) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
//        if (!$this->CheckValidateDate($invoiceDate) || !$this->CheckValidateDate($invoiceDueDate)) {
//            return $this->BadRequest(__LINE__ . ":" . __CLASS__
//                            , 'Validation Error'
//                            , ['code' => 422
//                        , 'message' => 'Invalid invoice date or due date']);
//        }
        if (!is_array($invoice_items)) {
            return $this->BadRequest(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422
                        , 'message' => 'Invalid invoices items. Kindly enter '
                        . 'correct format for invoice items']);
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
            $balanceAmount = 0;
            $eventName = "";
            if ($invoice_type_id == 2) {
                $checkEvents = Events::findFirst([
                            "eventID =:eventID: ",
                            "bind" => [
                                "eventID" => $invoice_referenceID]]);

                if (!$checkEvents) {
                    return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422
                                , 'message' => 'Invalid event ID']);
                }
                $eventName = $checkEvents->eventName;
                $totalAmount = 0;
                if ($checkEvents->hasMultipleShow == 1) {
                    $sqlEv = "SELECT sum(event_show_tickets_type.amount * "
                            . "(event_show_tickets_type.ticket_purchased/event_show_tickets_type.group_ticket_quantity))"
                            . " as totalTicets  FROM event_show_tickets_type "
                            . "JOIN event_show_venue on event_show_tickets_type.event_show_venue_id "
                            . "= event_show_venue.event_show_venue_id JOIN "
                            . "event_shows on event_show_venue.event_show_id = "
                            . "event_shows.event_show_id WHERE "
                            . "event_shows.eventID = :eventId";
                } else {
                    $sqlEv = "SELECT 
                        sum(amount * (ticket_purchased/group_ticket_quantity)) 
                        as totalAmount from event_tickets_type WHERE eventId =:eventId";
                }
                $resultAmount = $this->rawSelectOneRecord($sqlEv,
                        [":eventId" => $invoice_referenceID]);
                if (!$resultAmount) {
                    return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422
                                , 'message' => 'Tickets not set']);
                }
                if ($resultAmount['totalAmount'] <= $this->settings['invoice']['minimumAmount']) {
                    return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422
                                , 'message' => 'Failed. Minimum amount to'
                                . ' withdraw is '.$this->settings['invoice']['minimumAmount'].'. Currently sales is'
                                . ' ' . $resultAmount['totalAmount']]);
                }

//                $balanceAmount = $resultAmount['totalAmount'];

                $revenue = (float) $resultAmount['totalAmount'];
                $commissionRate = (float) $checkEvents->revenueShare / 100;
                $madfunCommission = $revenue * $commissionRate;
                $netRevenue = $revenue - $madfunCommission;

                if ($checkEvents->status == 1) {
                    $maxWithdrawalAllowed = $revenue * 0.50; 
                } else {
                    $maxWithdrawalAllowed = $netRevenue; 
                }

                $alreadyPaid = (float) $resultInvoicePayments['amountPaid'];
                $currentAvailableBalance = $maxWithdrawalAllowed - $alreadyPaid;

                $balanceAmount = max(0, $currentAvailableBalance);

                $checkUserEventMap = UserEventMap::findFirst([
                            "user_mapId =:user_mapId: AND eventID=:eventID: ",
                            "bind" => [
                                "user_mapId" => $auth_response['user_mapId'],
                                'eventID' => $invoice_referenceID],]);

                if (!$checkUserEventMap && $auth_response['userRole'] == 6) {
                    return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422
                                , 'message' => 'You are not authorised to create '
                                . 'invoice ']);
                }


                $checkInvoicePayment = Invoice::findFirst([
                            "invoice_reference_id =:invoice_reference_id: "
                            . "AND invoice_type_id=:invoice_type_id: AND invoice_status=2 ",
                            "bind" => [
                                "invoice_reference_id" => $invoice_referenceID,
                                'invoice_type_id' => 2],]);

                if ($checkInvoicePayment) {
                    return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422
                                , 'message' => 'You already have a pending invoice.'
                                . ' Once paid you can request '
                                . 'another payment']);
                }

                $resultInvoicePayments = $this->rawSelectOneRecord("select "
                        . "IFNULL(sum(IFNULL(invoice_amount_paid,0)),0) as amountPaid"
                        . " from invoices where invoice_reference_id = "
                        . ":invoice_reference_id and invoice_type_id = "
                        . ":invoice_type_id and invoice_status = "
                        . ":invoice_status", [
                    ":invoice_reference_id" => $invoice_referenceID,
                    ":invoice_type_id" => 2,
                    ":invoice_status" => 1
                ]);
                $balanceAmount = $balanceAmount - (FLOAT) $resultInvoicePayments['amountPaid'];
            }
            if ($beneficiary_reference) {
                $checkClients = Clients::findFirst([
                            "client_id =:client_id: ",
                            "bind" => [
                                "client_id" => $beneficiary_reference]]);

                if (!$checkClients) {
                    return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422
                                , 'message' => 'Invalid benefericiary reference']);
                }
            } else {
                $checkClients = Clients::findFirst(['client_name=:client_name:'
                            , 'bind' => ['client_name' => $beneficary_data->company]]);

                if (!$checkClients) {
                    $clients = new Clients();
                    $clients->setTransaction($dbTrxn);
                    $clients->client_name = $beneficary_data->company;
                    $clients->address = $beneficary_data->address;
                    $clients->email_address = $beneficary_data->email_address;
                    $clients->msisdn = $beneficary_data->msisdn;
                    $clients->description = "Event Orginazer for Client " . $beneficary_data->company;
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

                    $checkUserClientMap = UserClientMap::findFirst(['user_mapId=:user_mapId:'
                                , 'bind' => ['user_mapId' => $auth_response['user_mapId']]]);
                    if ($checkUserClientMap) {
                        $checkUserClientMap->setTransaction($dbTrxn);
                        $checkUserClientMap->client_id = $clients->client_id;
                        if ($checkUserClientMap->save() === false) {
                            $errors = [];
                            $messages = $clients->getMessages();
                            foreach ($messages as $message) {
                                $e["statusDescription"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                array_push($errors, $e);
                            }

                            $dbTrxn->rollback("Update User Client Map."
                                    . " Reason" . json_encode($errors));
                        }
                    }
                    $beneficiary_reference = $clients->client_id;
                } else {
                    $checkUserClientMap = UserClientMap::findFirst(['user_mapId=:user_mapId:'
                                , 'bind' => ['user_mapId' => $auth_response['user_mapId']]]);
                    if ($checkUserClientMap) {
                        $checkUserClientMap->setTransaction($dbTrxn);
                        $checkUserClientMap->client_id = $checkClients->client_id;
                        if ($checkUserClientMap->save() === false) {
                            $errors = [];
                            $messages = $clients->getMessages();
                            foreach ($messages as $message) {
                                $e["statusDescription"] = $message->getMessage();
                                $e["field"] = $message->getField();
                                array_push($errors, $e);
                            }

                            $dbTrxn->rollback("Update User Client Map."
                                    . " Reason" . json_encode($errors));
                        }
                    }
                    $beneficiary_reference = $checkClients->client_id;
                }
            }

            $checkInvoicePaymentType = InvoicePaymentType::findFirst(
                            ['invoice_payment_type_id=:invoice_payment_type_id:'
                                , 'bind' => ['invoice_payment_type_id' =>
                                    $invoice_payment_type_id]]);

            if (!$checkInvoicePaymentType) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422
                            , 'message' => 'Invalid invoice payment type']);
            }
            if ($checkInvoicePaymentType->status != 1) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422
                            , 'message' => 'Invoice Payment not enable '
                            . 'contant Madfun Administrator']);
            }


            $invoices = new Invoices();

            $paramsInvoices = [
                'invoice_type_id' => $invoice_type_id,
                'invoice_reference_id' => $invoice_referenceID,
                'invoice_to_client_id' => $invoiceDueDate,
                'invoice_payment_type_id' => $invoice_payment_type_id,
                'invoice_billing_reference' => $beneficiary_reference,
                'invoice_to_client_id' => $invoice_to_client_id,
                'invoice_fee' => $checkInvoicePaymentType->invoice_fee,
                'invoice_amount' => 0.00,
                'invoice_amount_paid' => 0.00,
                'invoice_note' => $invoice_description,
                'invoice_issued_date' => $invoiceDate,
                'invoice_due_date' => $invoiceDueDate
            ];
            $invoiceData = $invoices->createInvoices($paramsInvoices);

            if ($invoiceData['status'] != 200) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => $invoiceData['status']
                            , 'message' => 'Failed! ' . $invoiceData['message']]);
            }
            $resultInvoicesItems = $invoices->createInvoiceItems($invoice_items,
                    $invoiceData['invoice_id'], $balanceAmount);

            if ($resultInvoicesItems['status'] != 200) {
                return $this->BadRequest(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => $resultInvoicesItems['status']
                            , 'message' => 'Failed! ' . $resultInvoicesItems['message']]);
            }
            $dbTrxn->commit();

            $postData = [
                "api_key" => $this->settings['ServiceApiKey'],
                "to" => "kevin.mwando@southwell.io",
                "from" => "noreply@madfun.com",
                "cc" => "",
                "subject" => "Invoice - " . $eventName,
                "content" => "<p>" . $resultInvoicesItems['message'] . "</p>",
                "extrac" => null
            ];
            $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                    $postData, $this->settings['ServiceApiKey'], 3);

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | SendInvoice Templates Response::" . json_encode($mailResponse));

            return $this->success(__LINE__ . ":" . __CLASS__
                            . ": Took " . $this->CalculateTAT($start_time) . " sec"
                            , 'Invoice Created successful',
                            ['code' => 200,
                                'message' => "Invoice Created successful!",
            ]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * viewInvoices
     * @return type
     * @throws Exception
     */
    public function viewInvoices() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $regex = '/"token":"[^"]*?"/';
        $string = (preg_replace($regex, '"token":***', json_encode($this->request->getJsonRawBody())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . ":" . __FUNCTION__
                . " | Request:" . $string);

        $headers = $this->request->getHeaders();
        $accessKey = isset($headers['X-Authorization-Key']) ? $headers['X-Authorization-Key'] : false;
        $auth['requested_with'] = isset($headers['X-Requested-With']) ? $headers['X-Requested-With'] : false;

        if (!$accessKey) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if ($auth['requested_with'] != 'XMLHttpRequest') {
            return $this->dataError(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'X-Requested-With is Invalid!']);
        }



        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Invoices Request:" . json_encode($this->request->get()));
        try {

            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($accessKey);

            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }

            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }

            $sortCriteria = $this->request->get('sort') ? $this->request->get('sort') : '';
            $currentPage = (int) $this->request->get('page') ? $this->request->get('page') : 1;
            $perPage = (int) $this->request->get('per_page') ? $this->request->get('per_page') : 10;
            $filter = $this->request->get('filter') ? $this->request->get('filter') : '';
            $start = $this->request->get('start') ? $this->request->get('start') : '';
            $end = $this->request->get('end') ? $this->request->get('end') : '';

            if ($this->checkForMySQLKeywords($currentPage) 
                    || $this->checkForMySQLKeywords($perPage) 
                    || $this->checkForMySQLKeywords($filter) 
                    || $this->checkForMySQLKeywords($start) 
                    || $this->checkForMySQLKeywords($end) ) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "select invoices.invoice_id,invoices.reference,"
                    . "invoice_payment_type.invoice_payment_type, "
                    . "invoice_type.invoice_type, events.eventName, "
                    . "clients.client_name, invoices.invoice_status, "
                    . "invoices.invoice_notes, invoices.invoice_amount, "
                    . "invoices.invoice_fee, invoices.invoice_issued_date,"
                    . "invoices.invoice_due_date, invoices.created  ";

            $countQuery = "select count(distinct invoices.invoice_id) as totalInvoices  ";

            $baseQuery = " from invoices join invoice_type on "
                    . "invoices.invoice_type_id = invoice_type.invoice_type_id "
                    . "join invoice_payment_type on invoices.invoice_payment_type_id"
                    . " = invoice_payment_type.invoice_payment_type_id join "
                    . "events on invoices.invoice_reference_id = events.eventID "
                    . "join clients on invoices.invoice_from_client_id = "
                    . "clients.client_id left join "
                    . "user_event_map on user_event_map.eventID = events.eventID ";

            if ($filter) {
                $start = $end = null;
            }

            $whereArray = [
                'filter' => $filter,
                'date' => [$start, $end]
            ];

            $whereQuery = " ";

            foreach ($whereArray as $key => $value) {

                if ($key == 'filter') {
                    $searchColumns = ['invoices.invoice_id',
                        'invoice_type.invoice_type', 'events.eventName', 'clients.client_name'];

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
                        $valueString = " DATE(invoice_type.created) BETWEEN '$value[0]' AND '$value[1]'";
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

            if ($auth_response['userRole'] == 6) {
                $whereQuery = $whereQuery ? "WHERE $whereQuery  AND "
                        . "user_event_map.user_mapId=" . $auth_response['user_mapId'] : 
                    " WHERE user_event_map.user_mapId=" . $auth_response['user_mapId'];
            }
            $countQuery = $countQuery . $baseQuery . $whereQuery ;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by invoices.invoice_id ";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalInvoices'];
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
                    $prev_url = "https://api.v1.interactive.madfun.com/v1/api/payments/view/invoice?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "https://api.v1.interactive.madfun.com/v1/api/payments/view/invoice?page=$next_url";
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
     * ViewInvoiceDetails
     * @return type
     * @throws Exception
     */
    public function ViewInvoiceDetails() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ViewInvoiceDetails:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $invoiceID = isset($data->invoiceID) ? $data->invoiceID : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($invoiceID)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$token || !$invoiceID) {
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

            $searchQuery = " WHERE invoices.invoice_id = :invoice_id";
            $searchParams[':invoice_id'] = $invoiceID;

            $sql = "select invoices.invoice_id,invoices.reference,"
                    . "invoice_payment_type.invoice_payment_type, "
                    . "invoice_type.invoice_type, events.eventName,invoices.total_items, "
                    . "clients.client_name,clients.address, clients.msisdn,"
                    . "clients.email_address, invoices.invoice_status, "
                    . "invoices.invoice_notes, invoices.invoice_amount, "
                    . "invoices.invoice_fee, invoices.invoice_issued_date,"
                    . "invoices.invoice_due_date, invoices.created, (select"
                    . " count(invoices.invoice_id) from invoices join invoice_type on "
                    . "invoices.invoice_type_id = invoice_type.invoice_type_id "
                    . "join invoice_payment_type on invoices.invoice_payment_type_id"
                    . " = invoice_payment_type.invoice_payment_type_id join "
                    . "events on invoices.invoice_reference_id = events.eventID "
                    . "join clients on invoices.invoice_from_client_id = "
                    . "clients.client_id $searchQuery) as total,(SELECT IFNULL(SUM(amount),0.00)"
                    . " from invoice_payments WHERE invoice_payments.invoice_id = "
                    . "invoices.invoice_id) as totalPayments from invoices join invoice_type on "
                    . "invoices.invoice_type_id = invoice_type.invoice_type_id "
                    . "join invoice_payment_type on invoices.invoice_payment_type_id"
                    . " = invoice_payment_type.invoice_payment_type_id join "
                    . "events on invoices.invoice_reference_id = events.eventID "
                    . "join clients on invoices.invoice_from_client_id = "
                    . "clients.client_id  $searchQuery";
            $results = $this->selectQuery($sql, $searchParams);
            if (!$results) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                . ": Took " . $this->CalculateTAT($start_time) . " sec"
                                , 'Request is not successful'
                                , ['code' => 404, 'message' => "There are no record found!!"], true);
            }
            $invoicesData = [];
            foreach ($results as $result) {
                $sqlItems = "SELECT invoice_items.invoice_item_id,invoice_items.product_name, "
                        . "invoice_items.product_description, "
                        . "invoice_items.quantity, invoice_items.unit_price, "
                        . "invoice_items.line_total FROM invoice_items"
                        . " WHERE invoice_items.invoice_id=:invoice_id";
                $resultItems = $this->selectQuery($sqlItems, [':invoice_id' => $result['invoice_id']]);
                $invData = [
                    'invoice_id' => $result['invoice_id'],
                    'invoice_token' => $this->Encrypt($result['invoice_id'], true),
                    'invoice_date' => $result['invoice_date'],
                    'invoice_due_data' => $result['invoice_due_data'],
                    'invoice_status' => $result['status'],
                    'invoice_total_amount' => $result['invoice_amount'],
                    'invoice_total_items' => $result['total_items'],
                    'invoice_organizer' => $result['client_name'],
                    'invoice_invoice_organizer_address' => $result['address'],
                    'invoice_invoice_organizer_email' => $result['email_address'],
                    'invoice_invoice_organizer_email' => $result['msisdn'],
                    'invoice_note' => $result['invoice_note'],
                    'invoice_items' => $resultItems,
                    'invoice_payments' => $result['totalPayments'],
                    'invoice_created_at' => $result['created']
                ];
                array_push($invoicesData, $invData);
            }
            return $this->successLarge(__LINE__ . ":" . __CLASS__
                            . ": Took " . $this->CalculateTAT($start_time) . " sec"
                            , 'Request is successful',
                            ['code' => 200,
                                'message' => "Successfully queried " .
                                $results[0]['total'] . " Invoices Records.",
                                'total' => $results[0]['total'],
                                'data' => $invoicesData]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * InvoiceSummary
     * @return type
     * @throws Exception
     */
    public function InvoiceSummary() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ViewInvoiceDetails:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $clientId = isset($data->clientId) ? $data->clientId : null;
        $start = isset($data->start) ? $data->start : null;
        $end = isset($data->end) ? $data->end : null;
        if ($this->checkForMySQLKeywords($token) ||
                $this->checkForMySQLKeywords($clientId)
                || $this->checkForMySQLKeywords($start)
                || $this->checkForMySQLKeywords($end)) {
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
            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }

            $searchQuery = " WHERE 1";
            $searchParams = [];

            if ($auth_response['userRole'] ==6) {
                $searchQuery = " WHERE user_event_map.user_mapId== :user_mapId";
                $searchParams[':user_mapId'] = $auth_response['user_mapId'];
            }

            if (($stop != null) && ($start != null)) {
                $searchQuery .= " AND invoices.created BETWEEN :start AND :stop ";
                $searchParams[':start'] = "$start 00:00:00";
                $searchParams[':stop'] = "$stop 23:59:59";
            } elseif (($stop != null) && ($start == null)) {
                $searchQuery .= " AND invoices.created <=:stop";
                $searchParams[':stop'] = "$stop 23:59:59";
            } elseif ($stop == null && $start != null) {
                $searchQuery .= " AND invoices.created >=:start";
                $searchParams[':start'] = "$start 00:00:00";
            } else {

                $searchQuery .= " ";
            }

            $sql = "SELECT (SELECT IFNULL(count(*),0) FROM invoices left join "
                    . "user_event_map on user_event_map.eventID = "
                    . "invoices.invoice_reference_id  $searchQuery) "
                    . "as totalInvoice,((select sum(abs(invoices.invoice_amount)) "
                    . "from invoices left join user_event_map on "
                    . "user_event_map.eventID = invoices.invoice_reference_id"
                    . " $searchQuery and client_invoices.status != 5) - "
                    . "(SELECT IFNULL(SUM(amount),0.00) from invoice_payments JOIN "
                    . "invoices on invoice_payments.invoice_id = "
                    . "invoices.invoice_id join user_event_map on "
                    . "user_event_map.eventID = invoices.invoice_reference_id"
                    . " $searchQuery)) as pendingAmount, "
                    . "(SELECT IFNULL(SUM(amount),0.00) from invoice_payments JOIN "
                    . "invoices on invoice_payments.invoice_id = "
                    . "invoices.invoice_id join user_event_map on "
                    . "user_event_map.eventID = invoices.invoice_reference_id"
                    . " $searchQuery) as paidAmount";

            $results = $this->rawSelectOneRecord($sql, $searchParams);
            if (!$results) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                . ": Took " . $this->CalculateTAT($start_time) . " sec"
                                , 'Request is not successful'
                                , ['code' => 404, 'message' => "There are no record found!!"], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__
                            . ": Took " . $this->CalculateTAT($start_time) . " sec"
                            , 'Request successful'
                            , ['code' => 200, 'message' => "Record Found!", "data" => $results]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
}
