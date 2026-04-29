<?php

defined('APP_PATH') || define('APP_PATH', realpath('.'));

$host = gethostname();
$prodHostname = $_ENV['PROD_HOSTNAME'] ?? 'ke-pr-core-1';

if ($host !== $prodHostname) {
    $connection = [
        'adapter'  => $_ENV['DB_ADAPTER']  ?? 'mysql',
        'host'     => $_ENV['DB_HOST']     ?? 'db',
        'username' => $_ENV['DB_USER']     ?? 'madfun_user',
        'password' => $_ENV['DB_PASS']     ?? '',
        'dbname'   => $_ENV['DB_NAME']     ?? 'madfun',
        'charset'  => 'utf8mb4',
    ];

    $connection2 = [
        'adapter'  => $_ENV['DB2_ADAPTER'] ?? 'mysql',
        'host'     => $_ENV['DB2_HOST']    ?? 'db',
        'username' => $_ENV['DB2_USER']    ?? 'madfun_user',
        'password' => $_ENV['DB2_PASS']    ?? '',
        'dbname'   => $_ENV['DB2_NAME']    ?? 'madfun',
        'charset'  => 'utf8mb4',
        'options'  => [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true],
    ];
} else {
    $connection = [
        'adapter'  => 'mysql',
        'host'     => $_ENV['PROD_DB_HOST'] ?? '35.187.90.51',
        'username' => $_ENV['PROD_DB_USER'] ?? 'madfun_user',
        'password' => $_ENV['PROD_DB_PASS'] ?? '',
        'dbname'   => $_ENV['DB_NAME']      ?? 'madfun',
        'charset'  => 'utf8mb4',
        'options'  => [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, \PDO::ATTR_PERSISTENT],
    ];

    $connection2 = [
        'adapter'  => 'mysql',
        'host'     => $_ENV['PROD_DB_HOST'] ?? '35.187.90.51',
        'username' => $_ENV['PROD_DB_USER'] ?? 'madfun_user',
        'password' => $_ENV['PROD_DB_PASS'] ?? '',
        'dbname'   => $_ENV['DB_NAME']      ?? 'madfun',
        'charset'  => 'utf8mb4',
        'options'  => [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true],
    ];
}

$logPath = [
    'location'   => '/var/www/logs/madfun/',
    'dateFormat' => 'Y-m-d H:i:s',
    'output'     => "%datetime% - [%level_name%] - %message%\n",
    'systemName' => 'madfunV1',
];

$APIServer = $_ENV['API_SERVER'] ?? '35.195.83.76';

return new \Phalcon\Config\Config([
    'database' => $connection,
    'db2'      => $connection2,
    'application' => [
        'controllersDir' => APP_PATH . '/app/controllers/',
        'modelsDir'      => APP_PATH . '/app/models/',
        'cacheDir'       => APP_PATH . '/app/cache/',
        'baseUri'        => '/cadbury/',
    ],
    'logPath' => $logPath,

    # RabbitMQ
    'mQueue' => [
        'rabbitServer'   => $_ENV['RABBIT_SERVER']   ?? '35.187.112.31',
        'rabbitVHost'    => $_ENV['RABBIT_VHOST']    ?? '/',
        'rabbitUser'     => $_ENV['RABBIT_USER']     ?? 'southwell',
        'rabbitPass'     => $_ENV['RABBIT_PASS']     ?? 'southwell',
        'rabbitPortNo'   => $_ENV['RABBIT_PORT']     ?? '5672',
        'QueueName'      => '_QUEUE',
        'QueueExchange'  => '_EXCHANGE',
        'QueueRoute'     => '_ROUTE',
    ],

    'settings' => [
        'appName'           => 'Madfun',
        'ticketSystemAPI'   => $_ENV['TICKET_SYSTEM_API'] ?? '',
        'Helpline'          => '0115555000',
        'ContactEmail'      => 'info@madfun.com',
        'revenueShare'      => 4,
        'QueueSMS'          => false,
        'VAT'               => 0.16,
        'Stream' => [
            'api_key'  => $_ENV['STREAM_API_KEY']  ?? '',
            'api_code' => $_ENV['STREAM_API_CODE'] ?? '',
        ],
        'invoice' => [
            'minimumAmount' => 500,
        ],
        'DPO' => [
            'endpoint'          => $_ENV['DPO_ENDPOINT']           ?? 'https://secure.3gdirectpay.com/API/v6/',
            'companyToken'      => $_ENV['DPO_COMPANY_TOKEN']      ?? '',
            'servicesType'      => $_ENV['DPO_SERVICES_TYPE']      ?? '71999',
            'backUrl'           => $_ENV['DPO_BACK_URL']           ?? 'https://gigs.madfun.com/event/',
            'paymentHourLimit'  => 3,
            'processingFee'     => 0,
            'redirectURL'       => $_ENV['DPO_REDIRECT_URL']       ?? 'https://gigs.madfun.com/event/dpo/payment',
            'redirectStreamURL' => $_ENV['DPO_REDIRECT_STREAM_URL'] ?? 'https://gigs.madfun.com/streams/dpo/payment',
        ],
        'PESAPAL' => [
            'ISLive'              => filter_var($_ENV['PESAPAL_IS_LIVE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'baseURLLive'         => $_ENV['PESAPAL_BASE_URL_LIVE']          ?? 'https://pay.pesapal.com/v3',
            'baseURLDemo'         => $_ENV['PESAPAL_BASE_URL_DEMO']          ?? 'https://cybqa.pesapal.com/pesapalv3',
            'consumerKeyLive'     => $_ENV['PESAPAL_CONSUMER_KEY_LIVE']      ?? '',
            'consumerSecretLive'  => $_ENV['PESAPAL_CONSUMER_SECRET_LIVE']   ?? '',
            'processingFee'       => (float)($_ENV['PESAPAL_PROCESSING_FEE'] ?? 0.0406),
            'consumerKeyDemo'     => $_ENV['PESAPAL_CONSUMER_KEY_DEMO']      ?? '',
            'consumerSecretDemo'  => $_ENV['PESAPAL_CONSUMER_SECRET_DEMO']   ?? '',
            'callBackURL'         => $_ENV['PESAPAL_CALLBACK_URL']           ?? 'https://gigs.madfun.com/event/confirm',
            'notificationId'      => $_ENV['PESAPAL_NOTIFICATION_ID']        ?? '',
        ],
        'DefaultCode'           => '9174',
        'connectTimeout'        => 20,
        'timeoutDuration'       => 20,
        'MinAmount'             => 5,
        'MaxAmount'             => 1000,
        'CodeLength'            => 8,
        'SelectRecordLimit'     => 20,
        'CampaignEarly'         => 3,
        'AdminWebURL'           => $_ENV['ADMIN_WEB_URL']   ?? 'http://35.187.93.149/ticketBay/',
        'MailerWebURL'          => $_ENV['MAILER_WEB_URL']  ?? 'https://api.vaspro.co.ke/v3/callback/mailer/alert',
        'TicketBaseURL'         => $_ENV['TICKET_BASE_URL'] ?? 'https://gigs.madfun.com/event/ticket',
        'EventBaseURL'          => $_ENV['EVENT_BASE_URL']  ?? 'https://gigs.madfun.com/event/',
        'EventFreeBaseURL'      => $_ENV['EVENT_FREE_BASE_URL'] ?? 'https://gigs.madfun.com/launch/',
        'RecordsLimit'          => 150,
        'TicketPrice'           => 100,
        'MaxRedeemptionPointsRequired' => 30,
        'ServiceApiKey'         => $_ENV['SERVICE_API_KEY'] ?? '',
        'AuthenticatedChannels' => ['WEB', 'USSD', 'MOBILE_APP', 'MOBILE'],
        'TemplateTypes'         => ['TICKET_PURCHASED', 'TICKET_REDEEMED'],
        'uploadDir' => [
            'blastSize'   => 50000,
            'uploadDelay' => 1,
            'uploadImage' => '/var/www/html/mgs_club/uploads/images/',
            'uploadFile'  => '/var/www/html/mgs_club/uploads/files/',
            'uploadAudio' => '/var/www/html/mgs_club/uploads/audios/',
            'uploadVideo' => '/var/www/html/mgs_club/uploads/videos/',
        ],
        'BlacklistedMsisdn' => ['254704050143', '254704034898', '254708672796 ', '254728832123', '254725560980'],
        'Authentication' => [
            'SecretKey'                  => $_ENV['AUTH_SECRET_KEY'] ?? '',
            'recommendedPasswordXters'   => 6,
            'failedAttemptsInterval'     => 10,
        ],
        'mnoApps' => [
            'DefaultDialCode'      => '254',
            'DefaultShortCode'     => '40400',
            'DefaultSenderId'      => 'MADFUN_INFO',
            'DefaultSenderIdOTP'   => 'MADFUN_OTP',
            'DefaultSenderIdAT'    => 'MADFUN',
            'ATToken'              => $_ENV['AT_TOKEN']               ?? '',
            'ATURL'                => $_ENV['AT_URL']                 ?? 'https://api.africastalking.com/version1/messaging/bulk',
            'BlastSmsApi'          => $_ENV['BLAST_SMS_API']          ?? 'https://sms.vaspro.co/v3/blast/sms/broadcast',
            'BulkSMSAPI'           => $_ENV['BULK_SMS_API']           ?? 'https://sms.vaspro.co/v3/BulkSMS/api/create',
            'BulkNestedSMSAPI'     => $_ENV['BULK_NESTED_SMS_API']    ?? 'https://sms.vaspro.co/v3/BulkSMS/bulk/nested',
            'OndemandSmsAPI'       => $_ENV['ONDEMAND_SMS_API']       ?? 'https://sms.vaspro.co/v3/BulkSMS/premium/create',
            'OndemandSmsCallback'  => $_ENV['ONDEMAND_SMS_CALLBACK']  ?? 'https://api.v1.interactive.madfun.com/v1/api/main/sms/dlr',
        ],
        'Rewards' => [
            'ElectricityUrl'       => $_ENV['ELECTRICITY_URL']         ?? 'https://sms.vaspro.co/v3/rewards/electricity',
            'VasproElectricityUrl' => $_ENV['VASPRO_ELECTRICITY_URL']  ?? 'https://sms.vaspro.co/v3/rewards/electricity/query',
            'VasproAirtimeUrl'     => $_ENV['VASPRO_AIRTIME_URL']      ?? 'https://sms.vaspro.co/v3/rewards/airtime',
        ],
        'aws' => [
            'client' => [
                'version' => 'latest',
                'region'  => $_ENV['AWS_REGION'] ?? 'af-south-1',
                'credentials' => [
                    'key'    => $_ENV['AWS_KEY']    ?? '',
                    'secret' => $_ENV['AWS_SECRET'] ?? '',
                ],
            ],
            'bucket'        => $_ENV['AWS_BUCKET']        ?? 'madfun',
            'temp_location' => $_ENV['AWS_TEMP_LOCATION'] ?? '/var/www/html/madfun-temp/',
            'cloudfront'    => $_ENV['AWS_CLOUDFRONT']    ?? '',
            'folder'        => $_ENV['AWS_FOLDER']        ?? 'Events',
        ],
        'Mpesa' => [
            'DefaultPaybillNumber' => $_ENV['MPESA_PAYBILL']           ?? '6999900',
            'CheckOutUrl'          => $_ENV['MPESA_CHECKOUT_URL']      ?? 'https://api.vaspro.co.ke/v3/checkout/mpesa',
            'CheckOutCallback'     => $_ENV['MPESA_CHECKOUT_CALLBACK'] ?? 'https://api.vaspro.co.ke/v3/checkout/mpesa',
        ],
        'Queues' => [
            'Ticket' => [
                'Route'    => 'MADFUN_TICKETS',
                'Queue'    => 'MADFUN_TICKETS',
                'Exchange' => 'MADFUN_TICKETS',
            ],
            'SMS' => [
                'Route'    => 'MADFUN_SMS',
                'Queue'    => 'MADFUN_SMS',
                'Exchange' => 'MADFUN_SMS',
            ],
        ],
        'StatusCodes' => [
            'LearnEntry'              => 201,
            'ReferralEntry'           => 800,
            'InvalidCodeEntry'        => 400,
            'ValidCodeEntry'          => 200,
            'EnquiryEntry'            => 100,
            'HelpEntry'               => 300,
            'OptinEntry'              => 500,
            'OpoutEntry'              => 600,
            'UsedEntry'               => 700,
            'RedeemPointsEntry'       => 900,
            'BlackListedInValidEntry' => 402,
            'BlackListedValidEntry'   => 401,
            'BlackListedWinnerEntry'  => 403,
            'CampaignLimitEntry'      => 202,
            'ProfilingEntry'          => 203,
            'EarlyEntry'              => 204,
            'CampaignClosedEntry'     => 205,
        ],
        'MailSettings' => [
            'AdminSender'    => $_ENV['MAIL_ADMIN_SENDER'] ?? '',
            'AdminPass'      => $_ENV['MAIL_ADMIN_PASS']   ?? '',
            'AdminName'      => 'Core System Mailer',
            'WebMaster'      => '',
            'SupportMaster'  => '',
            'TechMaster'     => '',
            'MtMaster'       => '',
            'VasWebMaster'   => '',
            'SalesMaster'    => '',
        ],
        'Messages' => [
            'OptOut'                  => 'You will no longer receive Bulk SMS/Promotional Messages. You can still continue participating. Helpline {Helpline}.',
            'OptIn'                   => 'Opted in to receive Bulk SMS. Continue participating. Helpline {Helpline}.',
            'Help'                    => "BUY the following CADBURY SKUs: scratch & send unique code to 40400. There is INSTANT AIRTIME. T&Cs Apply. Helpline {Helpline}.\n- 125g, 225g or 450g Cadbury 2-in-1 Drinking Chocolate;\n- 500g Cadbury 3-in-1 Hot Cocoa;and/or\n- 230g Cadbury Eclairs",
            'BlackListed'             => 'Thank you for participating in Cadbury promo. Share a Sweet Moment. Helpline {Helpline}.',
            'CampaignLimit'           => 'Your submission has not been successful. Kindly contact customer service on this number. Helpline {Helpline}.',
            'CampaignEarly'           => 'Sorry, the promotion has not started. Keep the code and send when the promotion starts. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'CampaignClosed'          => 'Sorry, the promotion is closed. Thank you for being our customer. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'InvalidCodeEntry'        => 'Sorry,this code is invalid. Please confirm that you have entered correctly and try again. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'RedeemedEntry'           => 'This code has already been redeemed. Please use a different code and try again. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'BlackListedWinnerEntry'  => 'Your submission has not been successful. Kindly contact customer service on this number. Helpline {Helpline}.',
            'NonWinnerEntry'          => 'Thank you for participating in Cadbury promo. T&Cs Apply. Helpline {Helpline}.',
            'WinnerEntry'             => '{praise}! You have won Airtime from Cadbury Share a Sweet Moment. You will receive your reward shortly. T&Cs Apply. Helpline {Helpline}.',
        ],
    ],
]);