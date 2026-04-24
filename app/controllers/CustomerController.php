<?php

/**
 * Description of CustomerController
 *
 * @author kevinkmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class CustomerController extends ControllerBase {

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
     * customerInfoAction
     * @return type
     * @throws Exception
     */
    public function customerInfoAction() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | customerInfoAction:" . json_encode($request->getJsonRawBody()));
        $token = isset($data->api_key) ? $data->api_key : null;
        $mobile = isset($data->msisdn) ? $data->msisdn : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $days = isset($data->days) ? $data->days : null;

        if ($this->checkForMySQLKeywords($token) || $this->checkForMySQLKeywords($mobile) || $this->checkForMySQLKeywords($start) || $this->checkForMySQLKeywords($stop) || $this->checkForMySQLKeywords($days)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$token || !$mobile) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        $msisdn = $this->formatMobileNumber($mobile);
        if (!$this->validateMobile($msisdn)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
        }
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 4, 3, 8])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User doesn\'t have permissions to perform this action.');
            }
            $whereArray = [
                'profile.msisdn' => $msisdn];
            $searchQuery = $this->whereQuery($whereArray, "");

            $sql = "select profile.msisdn, user.email, "
                    . "profile_attribute.first_name,profile_attribute.last_name,profile_attribute.surname, "
                    . "profile_attribute.network, profile_attribute.frequency,"
                    . "profile_attribute.created from profile join profile_attribute"
                    . " on profile.profile_id  = profile_attribute.profile_id "
                    . "left join user on user.profile_id = profile.profile_id"
                    . "  $searchQuery";

            $result = $this->rawSelect($sql,[], 'db2');
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Summary Results ( $stop_time Seconds)"
                            , 'data' => []], true);
            }

            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Summary results ($stop_time Seconds)",
                        'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * depositnAction
     * @return type
     * @throws Exception
     */
    public function depositnAction() {
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

            if ($this->checkForMySQLKeywords($sortCriteria) || 
                    $this->checkForMySQLKeywords($currentPage) || 
                    $this->checkForMySQLKeywords($perPage) ||
                    $this->checkForMySQLKeywords($filter) || 
                    $this->checkForMySQLKeywords($end) || 
                    $this->checkForMySQLKeywords($start)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }


            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = " select mpesa_transactions.mpesa_amount,"
                    . "mpesa_transactions.mpesa_code,mpesa_transactions.mpesa_account,"
                    . "mpesa_transactions.mpesa_msisdn,mpesa_transactions.mpesa_sender,"
                    . "mpesa_transactions.mpesa_amount,mpesa_transactions.created ";

            $countQuery = " select count(mpesa_transactions.id) as totalTransaction  ";

            $baseQuery = " from mpesa_transactions ";

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
                    $searchColumns = ['mpesa_transactions.mpesa_msisdn', 'mpesa_transactions.mpesa_code'];

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
                        $valueString = " DATE(mpesa_transactions.created) BETWEEN '$value[0]' AND '$value[1]'";
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
            $data->totalMatches = $count[0]['totalTransaction'];
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
                    $prev_url = "http://35.195.83.76/tc_ncp/api/customer/v1/deposit?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.195.83.76/tc_ncp/api/customer/v1/deposit?page=$next_url";
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
     * outboxAction
     * @return type
     * @throws Exception
     */
public function outboxAction() {
    $this->infologger = $this->getLogFile('info');
    $this->errorlogger = $this->getLogFile('error');
    try {
        $sortCriteria = $this->request->get('sort')    ?: '';
        $currentPage  = max(1, (int)($this->request->get('page')     ?: 1));
        $perPage      = max(1, (int)($this->request->get('per_page') ?: 10));
        $filter       = $this->request->get('filter') ?: '';
        $start        = $this->request->get('start')  ?: null;
        $end          = $this->request->get('end')    ?: null;

        if ($this->checkForMySQLKeywords($currentPage)
            || $this->checkForMySQLKeywords($perPage)
            || $this->checkForMySQLKeywords($filter)
            || $this->checkForMySQLKeywords($end)
            || $this->checkForMySQLKeywords($start)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        $allowedSort = [
            'created'    => 'outbox.created',
            'msisdn'     => 'profile.msisdn',
            'created_by' => 'outbox.created_by',
        ];
        $sortField = 'outbox.outbox_id';
        $orderBy   = 'DESC';
        if ($sortCriteria) {
            [$sf, $ob] = array_pad(explode('|', $sortCriteria, 2), 2, 'DESC');
            if (isset($allowedSort[$sf])) {
                $sortField = $allowedSort[$sf];
            }
            $orderBy = strtoupper($ob) === 'ASC' ? 'ASC' : 'DESC';
        }

        // When a text filter is active, ignore date range (matches original behaviour)
        if ($filter) {
            $start = $end = null;
        }

        // Build WHERE with bound params
        $whereParts = [];
        $params     = [];

        if ($filter) {
            // BUG FIX: LIKE instead of REGEXP (REGEXP ignores indexes, LIKE can use them)
            // BUG FIX: msisdn is BIGINT — cast to CHAR before pattern matching
            $like = '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $filter) . '%';
            $whereParts[] = "(CAST(profile.msisdn AS CHAR) LIKE :filter
                           OR profile_attribute.first_name LIKE :filter
                           OR outbox.created_by            LIKE :filter)";
            $params[':filter'] = $like;
        }

        if ($start && $end) {
            $whereParts[] = "DATE(outbox.created) BETWEEN :start AND :end";
            $params[':start'] = $start;
            $params[':end']   = $end;
        } elseif ($start) {
            $whereParts[] = "DATE(outbox.created) >= :start";
            $params[':start'] = $start;
        } elseif ($end) {
            $whereParts[] = "DATE(outbox.created) <= :end";
            $params[':end'] = $end;
        }

        $whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        $offset   = ($currentPage - 1) * $perPage;

        // BUG FIX: profile_attribute changed from INNER JOIN to LEFT JOIN.
        // System-generated messages (FREE_TICKET, MPESA callbacks etc.) are sent
        // with profile_ids that often have no profile_attribute row. An INNER JOIN
        // silently drops every one of those rows, producing zero results.
        // outbox_dlr is kept as LEFT JOIN (correct — DLR may not exist yet).
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS
                profile.msisdn,
                profile_attribute.first_name,
                outbox.message,
                outbox.created_by,
                outbox_dlr.description,
                outbox.created
            FROM outbox
            JOIN  profile           ON  profile.profile_id = outbox.profile_id
            LEFT JOIN profile_attribute
                                    ON  profile_attribute.profile_id = profile.profile_id
            LEFT JOIN outbox_dlr    ON  outbox_dlr.outbox_id = outbox.outbox_id
            $whereSQL
            ORDER BY $sortField $orderBy
            LIMIT $offset, $perPage
        ";

        $matches     = $this->rawSelect($sql, $params, 'db2');
        $countResult = $this->rawSelect("SELECT FOUND_ROWS() AS total", [], 'db2');
        $totalItems  = (int)($countResult[0]['total'] ?? 0);

        $lastPage = ($perPage > 0 && $totalItems > 0)
                  ? (int)ceil($totalItems / $perPage)
                  : 1;
        $from = $totalItems > 0 ? $offset + 1 : 0;
        $to   = min($offset + $perPage, $totalItems);

        $baseUrl = "/customer/outbox";
        $pagination = (object)[
            'total'         => $totalItems,
            'per_page'      => $perPage,
            'current_page'  => $currentPage,
            'last_page'     => $lastPage,
            'from'          => $from,
            'to'            => $to,
            'next_page_url' => $currentPage < $lastPage
                             ? "$baseUrl?page=" . ($currentPage + 1) : null,
            'prev_page_url' => $currentPage > 1
                             ? "$baseUrl?page=" . ($currentPage - 1) : null,
        ];

        $this->successVueTable([
            'links' => (object)['pagination' => $pagination],
            'data'  => $matches ?? [],
        ]);

    } catch (Exception $ex) {
        $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
            . " | Exception::" . $ex->getMessage());
        return $this->serverError(__LINE__ . ":" . __CLASS__, 'Internal Server Error.');
    }
}
    
    
    
    
        }
