<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\Collection as MicroCollection;
use Phalcon\Events\Manager;

define('APP_PATH', realpath(''));
define('PHALCON_VERSION', 5);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("date.timezone", "Africa/Nairobi");
ini_set('default_socket_timeout', 160);
error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Read auto-loader
 */
include APP_PATH . "/vendor/autoload.php";


/**
 * Read the configuration
 */
$config = include APP_PATH . "/app/config/config.php";

/**
 * Read auto-loader
 */
include APP_PATH . "/app/config/loader.php";

/**
 * Read services
 */
include APP_PATH . "/app/config/services.php";

$di = new FactoryDefault();

/**
 * Create a new Events Manager.
 */
$manager = new Manager();


/**
 * create and bind the DI to the application
 */
$app = new Micro($di);

/**
 * @FA
 */
$main = new MicroCollection();
$main->setPrefix('/v1/api/main/');
$main->setHandler('IndexController', true);
$main->mapVia('ticket/purchase', 'ticketPurchaseAction', ['POST']);
$main->mapVia('ticket/purchase/queue', 'ticketPurchaseMultQueue', ['POST']);
$main->mapVia('ticket/purchase/multiple', 'ticketPurchaseMultiAction', ['POST']);
$main->mapVia('ticket/purchase/new/multiple', 'ticketPurchaseMultiNewAction', ['POST']);
$main->mapVia('ticket/payment', 'paymentsAction', ['POST']);
$main->mapVia('payment/retries', 'mpesaPaymentRetries', ['POST']);
$main->mapVia('redeemed/ticket', 'redeemedTicket', ['POST']);
$main->mapVia('redeemed/linked/ticket', 'linkredeemedTicket', ['POST']);
$main->mapVia('complimentary/ticket', 'complimentTickets', ['POST']);
$main->mapVia('check/payment', 'checkPaymentStatus', ['POST']);
$main->mapVia('ticket/lpo', 'buyTicketLPO', ['POST']);
$main->mapVia('payment/b2b', 'paymentsB2BAction', ['POST']);
$main->mapVia('sms/dlr', 'smsDLRAction', ['POST']);
$main->mapVia('dpo/ticket/payment', 'dpoPaymentsAction', ['POST','GET']);
$main->mapVia('ticket/change', 'changeEvents', ['POST']);
$main->mapVia('stream/purchase', 'streamingPurchaseAction', ['POST']);
$main->mapVia('stream/payment/check', 'checkStreamPayment', ['POST']);
$main->mapVia('stream/callback', 'callbackStream', ['POST']);
$main->mapVia('pesapal/ipn', 'IPNPesapal', ['POST']);
$main->mapVia('queried/linked/ticket', 'queryLinkedTicket', ['POST']);
$main->mapVia('queried/qrCode/ticket', 'queryQrCodeTicket', ['POST']);
$main->mapVia('queried/dpo/payment', 'QueryDPOPaymentStatus', ['POST']);
/**
 * Dashboard
 */
$dashboard = new MicroCollection();
$dashboard->setPrefix('/v1/api/dashboard/');
$dashboard->setHandler('DashboardController', true);
$dashboard->mapVia('summary', 'summaryDashboardStats', ['POST','GET']);
$dashboard->mapVia('graph', 'dashTicketPurchaseGraph', ['POST','GET']);
$dashboard->mapVia('events/summary', 'summaryEventsTickets', ['POST','GET']);
$dashboard->mapVia('events/show/summary', 'summaryEventsShowTickets', ['POST','GET']);
$dashboard->mapVia('tickets/graph', 'ticketPurchaseGraph', ['POST','GET']);
$dashboard->mapVia('tickets/show/types', 'ticketTypesShowsEvents', ['POST','GET']);
$dashboard->mapVia('tickets/types', 'ticketTypesEvents', ['POST','GET']);
$dashboard->mapVia('customer', 'customersAction', ['POST','GET']);
$dashboard->mapVia('tickets/purchased', 'viewTicketPurchased', ['POST','GET']);
$dashboard->mapVia('view/mpesa', 'viewMpesaDepo', ['POST','GET']);
$dashboard->mapVia('tickets/types/edit', 'editTicketTypes', ['POST','GET']);
$dashboard->mapVia('view/affiliators/events', 'viewAffiliatorSales', ['POST','GET']);
$dashboard->mapVia('generate/report', 'generateEventReports', ['GET']);
$dashboard->mapVia('complimentary/report', 'generateComplimentaryReports', ['POST','GET']);
$dashboard->mapVia('refunded/report', 'generateRefundReports', ['POST','GET']);
$dashboard->mapVia('ticket/upgraded/view', 'upgradeTicketFunction', ['POST','GET']);
$dashboard->mapVia('refunded/report', 'upgradeListTicketType', ['POST','GET']);

/**
 * User
 */
$user = new MicroCollection();
$user->setPrefix('/api/user/');
$user->setHandler('UserController', true);
$user->mapVia('v1/create', 'createAction', ['POST']);
$user->mapVia('v1/verify', 'verifySignin', ['POST']);
$user->mapVia('v1/login', 'loginAction', ['POST']);
$user->mapVia('v1/forgot', 'forgotPassword', ['POST']);
$user->mapVia('v1/view', 'viewUsersAction', ['GET']);
$user->mapVia('v1/profile', 'viewUserProfile', ['POST']);
$user->mapVia('v1/profiles/view', 'viewProfilesAction', ['GET']);
$user->mapVia('v1/view/members', 'viewMembers', ['POST']);
$user->mapVia('v1/edit', 'editUsers', ['POST']);

/**
 * Profile
 */
$profile = new MicroCollection();
$profile->setPrefix('/api/profile/');
$profile->setHandler('ProfileController', true);
$profile->mapVia('v1/login', 'loginAction', ['POST']);
$profile->mapVia('v1/create', 'signUpAction', ['POST']);
$profile->mapVia('v1/verify', 'verifySignin', ['POST']);
$profile->mapVia('v1/view/tickets', 'viewMyTickets', ['POST']);
$profile->mapVia('v1/view/tickets/event', 'viewMyTicketGroupByEvents', ['POST']);
$profile->mapVia('v1/event/organizer', 'changeEventOragnizer', ['POST']);
$profile->mapVia('v1/ticket/share', 'shareTicket', ['POST']);
$profile->mapVia('v1/edit', 'editProfile', ['POST']);
$profile->mapVia('v1/payments/view', 'viewMyPayments', ['POST']);



$code = new MicroCollection();
$code->setPrefix('/api/code/');
$code->setHandler('IndexController', true);
$code->mapVia('v1/query', 'viewCodeStatus', ['POST']);
$code->mapVia('v1/ticket/issue', 'redemmedCodeTicket', ['POST']);


/**
 * Events
 */
$event = new MicroCollection();
$event->setPrefix('/v1/api/event/');
$event->setHandler('EventsController', true);
$event->mapVia('ticket/type/create', 'addTicketTypeAction', ['POST']);
$event->mapVia('ticket/type/view', 'viewTicketTypeAction', ['POST']);
$event->mapVia('ticket/type/dashboard/view', 'viewTicketTypeDashboardAction', ['POST','GET']);
$event->mapVia('create', 'addEvents', ['POST']);
$event->mapVia('updated', 'updateEvents', ['POST']);
$event->mapVia('app/create', 'createEvent', ['POST']);
$event->mapVia('upload/poster', 'uploadImage', ['POST']);
$event->mapVia('add/element/form', 'addEventElementForm', ['POST']);
$event->mapVia('view/element/form', 'viewEventElementForm', ['POST']);
$event->mapVia('edit', 'editEvent', ['POST']);
$event->mapVia('view', 'viewEvents', ['POST']);
$event->mapVia('view/country', 'viewCountries', ['POST','GET']);
$event->mapVia('dashboard/view', 'viewEventsDashboard', ['GET']);
$event->mapVia('type/create', 'addEventTicketType', ['POST']);
$event->mapVia('type/edit', 'editEventTicketType', ['POST']);
$event->mapVia('type/view', 'viewEventTicketType', ['GET']);
$event->mapVia('ticket/purcahse/view', 'viewEventTicketPurchase', ['GET']);
$event->mapVia('mpesa/transaction', 'viewMpesaTransactions', ['GET']);
$event->mapVia('ticket/resend', 'resendTicketInformation', ['POST']);
$event->mapVia('email/template/add', 'addEmailTemplate', ['POST']);
$event->mapVia('email/resend', 'resendEmailTicketToAll', ['POST']);
$event->mapVia('view/ticket', 'viewTicketDetails', ['POST','GET']);
$event->mapVia('view/summary', 'viewEventSum', ['POST','GET']);
$event->mapVia('ussd', 'getEventUSSD', ['POST']);
$event->mapVia('ticket/shared', 'shareTicket', ['POST']);
$event->mapVia('add/category', 'addCategory', ['POST']);
$event->mapVia('edit/category', 'editCategory', ['POST']);
$event->mapVia('view/category', 'viewCategory', ['POST']);
$event->mapVia('view/dash/category', 'viewDashboardCategories', ['GET']);
$event->mapVia('view/user/tickets', 'viewUserTickets', ['GET']);
$event->mapVia('redemeed/keyword/tickets', 'redemeedKeywordsAmount', ['POST']);
$event->mapVia('add/show', 'addEventShows', ['POST']);
$event->mapVia('view/show', 'viewEventShows', ['POST']);
$event->mapVia('view/show/venue', 'viewEventShowVenue', ['POST']);
$event->mapVia('add/tickets/venue/types', 'addEventVenueTicketTypes', ['POST']);
$event->mapVia('view/tickets/venue/types', 'viewTicketTypeShowAction', ['POST']);
$event->mapVia('map/user', 'mapUserToEvent', ['POST']);
$event->mapVia('map/affiliator', 'addAffiliatorToEvent', ['POST']);
$event->mapVia('map/affiliator/view', 'viewAffiliator', ['POST']);
$event->mapVia('map/affiliator/edit', 'editAffiliator', ['POST']);
$event->mapVia('green/job', 'addGreenJobCustomers', ['POST']);
$event->mapVia('ticket/refund', 'refundTickets', ['POST']);



$utility = new MicroCollection();
$utility->setPrefix('/api/utility/');
$utility->setHandler('UtilitiesController', true);
$utility->mapVia('v1/purchase', 'utilityPurchaseAction', ['POST']);
$utility->mapVia('v1/callback', 'utilityCallback', ['POST']);
$utility->mapVia('v1/retry', 'utilityRetryTransactionAction', ['POST']);
$utility->mapVia('v1/view/accounts', 'viewAccounts', ['POST']);

/**
 * Customer
 */
$customer = new MicroCollection();
$customer->setPrefix('/api/customer/');
$customer->setHandler('CustomerController', true);
$customer->mapVia('v1/info', 'customerInfoAction', ['POST']);
$customer->mapVia('v1/payment', 'depositnAction', ['POST']);
$customer->mapVia('v1/outbox', 'outboxAction', ['GET']);

/**
 * Authentication
 */
$auth = new MicroCollection();
$auth->setPrefix('/v2/auth/');
$auth->setHandler('AuthController', true);
$auth->mapVia('login', 'loginAction', ['POST']);
$auth->mapVia('signup', 'signUpAction', ['POST']);
$auth->mapVia('verify', 'verifySignin', ['POST']);
$auth->mapVia('reset', 'forgotPassword', ['POST']);
$auth->mapVia('update/profile', 'updateAccount', ['POST']);



$payments = new MicroCollection();
$payments->setPrefix('/v1/api/payments/');
$payments->setHandler('PaymentsController', true);
$payments->mapVia('view/banks', 'viewBanks', ['GET']);
$payments->mapVia('add/banks', 'addBank', ['POST']);
$payments->mapVia('view/invoice/type', 'viewInvoiceType', ['GET']);
$payments->mapVia('add/invoice/type', 'addInvoiceType', ['POST']);
$payments->mapVia('create/invoice', 'createInvoice', ['POST']);
$payments->mapVia('create/invoice', 'viewInvoices', ['GET']);
$payments->mapVia('view/invoice/info', 'ViewInvoiceDetails', ['POST']);
$payments->mapVia('view/invoice/summary', 'InvoiceSummary', ['POST']);



/**
 * turnstile
 */
$turnstile = new MicroCollection();
$turnstile->setPrefix('/api/turnstile/');
$turnstile->setHandler('TurnstileController', true);
$turnstile->mapVia('v1/heartBeat', 'heartBeat', ['POST']);
$turnstile->mapVia('v1/access/request', 'accessRequest', ['POST']);



/**
 * Mount points
 */
$app->mount($user);
$app->mount($customer);
$app->mount($event);
$app->mount($main);
$app->mount($dashboard);
$app->mount($profile);
$app->mount($utility);
$app->mount($code);
$app->mount($auth);
$app->mount($payments);
$app->mount($turnstile);

try {
    if ($app->request->getMethod() == "OPTIONS") {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: X-App-Key,X-Consumer-Key, X-Token-Key,X-Authorization,X-Hash-Key"
                . ",X-Authorization-Key, Origin, X-Requested-With, Content-Type"
                . ", Accept, Access-Control-Request-Method,Access-Control-Request-Headers"
                . ", Authorization,x-stp-token,x-stp-api-key,x-stp-version");
        header("HTTP/1.1 200 OK");
        die();
    }
    $app->after(function () use ($app) {
        $app->response
                ->setHeader("Accept", "*/*")
                ->setHeader("Accept-Encoding", "gzip")
                ->setHeader("Accept-Charset", "utf-8")
                ->setHeader("Access-Control-Allow-Credentials", true)
                ->setHeader("Access-Control-Allow-Methods", 'GET,POST,OPTIONS')
                ->setHeader("Access-Control-Allow-Origin", '*')
                ->setHeader('Access-Control-Allow-Headers',
                        'X-App-Key,X-Hash-Key, X-Token-Key,X-Consumer-Key,X-Authorization,X-Authorization-Key'
                        . ',Authorization,X-Requested-With, Content-Disposition'
                        . ', Origin, accept, client-security-token, host'
                        . ', Content-Disposition, append,delete,entries,foreach'
                        . 'get,has,keys,set,values')
                ->setHeader('Access-Control-Expose-Headers', 'Content-Length,X-JSON');
        $app->response->sendHeaders();
        return true;
    });

    /**
     * Not Found URLs
     */
    $app->notFound(function () use ($app) {
        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = "Application link not found";
        $res->data = ['code' => 404, 'message' => 'The route you are looking for does not exists.'];

        $app->response->setHeader("Content-Type", "application/json");
        $app->response->setStatusCode(404, "NOT FOUND");
        $app->response->setContent(json_encode($res));
        return $app->response->send();
    });

// Handle the request
    $di->setShared('eventsManager', $manager);
    header('Access-Control-Allow-Origin:*');

    if (PHALCON_VERSION == 4 || PHALCON_VERSION == 5) {
        $response = $app->handle($_SERVER['REQUEST_URI']);  // very key for phalcon 4 or 5 to work
    } else {
        $response = $app->handle();
    }
} catch (\Exception $e) {
    $res = new \stdClass();
    $res->code = "Error";
    $res->statusDescription = "Request is not successful";
    $res->data = [
        'code' => 500,
        'message' => '[SEVERE ERROR]: P.V' . PHALCON_VERSION . ' An exception condition occured.' . $e->getTraceAsString()
    ];

    header("Content-Type: application/json;charset=utf-8");
    header('Access-Control-Allow-Origin:*');

    $phpSapiName = substr(php_sapi_name(), 0, 3);
    if ($phpSapiName == 'cgi' || $phpSapiName == 'fpm') {
        http_response_code(200);
    } else {
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
        http_response_code(200);
    }

    echo json_encode($res);
}