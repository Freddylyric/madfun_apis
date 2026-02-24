<?php

defined('APP_PATH') || define('APP_PATH', realpath('.'));



$connection = [
    'adapter'  => $_ENV['DB_ADAPTER'] ?? 'mysql',
    'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
    'dbname'   => $_ENV['DB_NAME'] ?? 'madfun',
    'charset'  => 'utf8mb4',
    "options"  => [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, \PDO::ATTR_PERSISTENT => true]
];

$connection2 = [
    'adapter'  => $_ENV['DB2_ADAPTER'] ?? 'mysql',
    'host'     => $_ENV['DB2_HOST'] ?? '127.0.0.1',
    'username' => $_ENV['DB2_USER'] ?? 'root',
    'password' => $_ENV['DB2_PASS'] ?? '',
    'dbname'   => $_ENV['DB2_NAME'] ?? 'madfun',
    'charset'  => 'utf8mb4',
    "options"  => [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
];


$logPath = [
    'location' => "/var/www/logs/madfun/",
    "dateFormat" => "Y-m-d H:i:s",
    "output" => "%datetime% - [%level_name%] - %message%\n",
    "systemName" => "madfunV1"];

$APIServer = '35.195.83.76';

return new \Phalcon\Config\Config([
    'database' => $connection,
    'db2' => $connection2,
    'application' => [
        'controllersDir' => APP_PATH . '/app/controllers/',
        'modelsDir' => APP_PATH . '/app/models/',
        'cacheDir' => APP_PATH . '/app/cache/',
        'baseUri' => '/cadbury/',],
    'logPath' => $logPath,
    #RabbitMQ settings
    'mQueue' => [
        'rabbitServer' => "35.187.112.31",
        'rabbitVHost' => "/",
        'rabbitUser' => "southwell",
        'rabbitPass' => "southwell",
        'rabbitPortNo' => "5672",
        'QueueName' => "_QUEUE",
        'QueueExchange' => "_EXCHANGE",
        'QueueRoute' => "_ROUTE",],
    #settings
    'settings' => [
        'appName' => 'Madfun',
        'ticketSystemAPI' => '4ba0e1aae090cdefc1887d2689b25e3f',
        'Helpline' => '0115555000',
        'ContactEmail' => 'info@madfun.com',
        'revenueShare' => 4,
        'QueueSMS'=>false,
        'VAT' => 0.16,
        'Stream' => [
            'api_key' => 'apc-xwyurGEL0DsK',
            'api_code' => 'apc-vJwuvHvuJtDt'
        ],
        'invoice' =>[
            'minimumAmount'=> 500,
            
        ],
        'DPO' => [
            'endpoint' => 'https://secure.3gdirectpay.com/API/v6/', //'https://secure.3gdirectpay.com/API/v6/',
            'companyToken' => '70816CF2-E0DA-48FD-BC4A-47C6C53008B5',
            'servicesType' => '71999',
            'backUrl' => 'https://gigs.madfun.com/event/',
            'paymentHourLimit' => 3,
            'processingFee' => 0,
            'redirectURL' => 'https://gigs.madfun.com/event/dpo/payment',
            'redirectStreamURL' => 'https://gigs.madfun.com/streams/dpo/payment'
        ],
        'PESAPAL' => [
            'ISLive' => TRUE,
            'baseURLLive' => 'https://pay.pesapal.com/v3',
            'baseURLDemo' => 'https://cybqa.pesapal.com/pesapalv3',
            'consumerKeyLive' => 'wYwqhCCrSR1sn4XAfFfLyqAM3JcYw691',
            'consumerSecretLive' => 'CKJbmP9NHsdBJbgf/JtNr+UWcwc=',
            'processingFee' => 0.0406,
            'consumerKeyDemo' => 'wYwqhCCrSR1sn4XAfFfLyqAM3JcYw691',
            'consumerSecretDemo' => 'CKJbmP9NHsdBJbgf/JtNr+UWcwc=',
            'callBackURL' => 'https://gigs.madfun.com/event/confirm',
            'notificationId' => '9cd549cc-7a25-40b3-b69a-dc8624a76eac' //Refers to ID for IPN Url Registered
        ],
        'DefaultCode' => '9174',
        'connectTimeout' => 20,
        'timeoutDuration' => 20,
        'MinAmount' => 5,
        'MaxAmount' => 1000,
        'CodeLength' => 8,
        'SelectRecordLimit' => 20,
        'CampaignEarly' => 3, //2,1
        'AdminWebURL' => 'http://35.187.93.149/ticketBay/',
        'MailerWebURL' => 'https://api.vaspro.co.ke/v3/callback/mailer/alert',
        'TicketBaseURL' => 'https://gigs.madfun.com/event/ticket',
        'EventBaseURL' => 'https://gigs.madfun.com/event/',
        'EventFreeBaseURL' => 'https://gigs.madfun.com/launch/',
        'RecordsLimit' => 150,
        'TicketPrice' => 100,
        'MaxRedeemptionPointsRequired' => 30,
        'ServiceApiKey' => '49ff5527754f679911a1175851e0b1a7',
        'AuthenticatedChannels' => ['WEB', 'USSD', 'MOBILE_APP', 'MOBILE'],
        'TemplateTypes' => ['TICKET_PURCHASED', 'TICKET_REDEEMED'],
        'uploadDir' => [
            'blastSize' => 50000,
            'uploadDelay' => 1,
            'uploadImage' => '/var/www/html/mgs_club/uploads/images/',
            'uploadFile' => '/var/www/html/mgs_club/uploads/files/',
            'uploadAudio' => '/var/www/html/mgs_club/uploads/audios/',
            'uploadVideo' => '/var/www/html/mgs_club/uploads/videos/'
        ],
        'BlacklistedMsisdn' => ['254704050143', '254704034898', '254708672796 ', '254728832123', '254725560980'],
        'Authentication' => [
            'SecretKey' => 'ewnjnkjsnreuqbyrbjvanibubibg129enf89438hf93928f99h89328h89dj2',
            'recommendedPasswordXters' => 6,
            'failedAttemptsInterval' => 10,],
        'mnoApps' => [
            'DefaultDialCode' => '254',
            'DefaultShortCode' => '40400',
            'DefaultSenderId' => 'MADFUN_INFO', //'TOTAL_LUBES',
            'DefaultSenderIdOTP' => 'MADFUN_OTP',
            'DefaultSenderIdAT'=>'MADFUN',
            'ATToken'=>"atsk_6510998bc127ba8190c189af9dc155fb71f4fb093a1ba388620b741dbeab5c856f4e25a7",
            'ATURL'=>'https://api.africastalking.com/version1/messaging/bulk',
            'BlastSmsApi' => 'https://sms.vaspro.co/v3/blast/sms/broadcast',
            'BulkSMSAPI' => 'https://sms.vaspro.co/v3/BulkSMS/api/create', //'http://35.195.83.76:8081/v3/BulkSMS/create',
            'BulkNestedSMSAPI' => 'https://sms.vaspro.co/v3/BulkSMS/bulk/nested',
            'OndemandSmsAPI' => 'https://sms.vaspro.co/v3/BulkSMS/premium/create',
            'OndemandSmsCallback' => "https://api.v1.interactive.madfun.com/v1/api/main/sms/dlr",
        ],
        'Rewards' => [
            'ElectricityUrl' => 'https://sms.vaspro.co/v3/rewards/electricity',
            'VasproElectricityUrl' => 'https://sms.vaspro.co/v3/rewards/electricity/query',
            'VasproAirtimeUrl' => 'https://sms.vaspro.co/v3/rewards/airtime'
        ],
        'aws' => [
            'client' => [
                'version' => 'latest',
                'region' => 'af-south-1',
                'credentials' => [
                    'key' => 'AKIA45N2D5VFZ7I3PXK3',
                    'secret' => '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'
                ]
            ],
            'bucket' => 'madfun',
            'temp_location' => '/var/www/html/madfun-temp/',
            'cloudfront' => 'https://dxvp4gxhxhyvg.cloudfront.net',
            'folder' => 'Events'
        ],
        'Mpesa' => [
            'DefaultPaybillNumber' => '6999900',
            'CheckOutUrl' => 'https://api.vaspro.co.ke/v3/checkout/mpesa',
            'CheckOutCallback' => 'https://api.vaspro.co.ke/v3/checkout/mpesa',],
        'Queues' => [
            'Ticket' => [
                'Route' => 'MADFUN_TICKETS',
                'Queue' => 'MADFUN_TICKETS',
                'Exchange' => 'MADFUN_TICKETS',
            ],
            'SMS' => [
                'Route' => 'MADFUN_SMS',
                'Queue' => 'MADFUN_SMS',
                'Exchange' => 'MADFUN_SMS',
            ],
        ],
        'StatusCodes' => [
            'LearnEntry' => 201,
            'ReferralEntry' => 800,
            'InvalidCodeEntry' => 400,
            'ValidCodeEntry' => 200,
            'EnquiryEntry' => 100,
            'HelpEntry' => 300,
            'OptinEntry' => 500,
            'OpoutEntry' => 600,
            'UsedEntry' => 700,
            'RedeemPointsEntry' => 900,
            'BlackListedInValidEntry' => 402,
            'BlackListedValidEntry' => 401,
            'BlackListedWinnerEntry' => 403,
            'CampaignLimitEntry' => 202,
            'ProfilingEntry' => 203,
            'EarlyEntry' => 204,
            'CampaignClosedEntry' => 205,
        ],
        'MailSettings' => [
            'AdminSender' => 'AKIAUA5GC7KBNPG7GRHZ', //'email-smtp.eu-west-2.amazonaws.com',//'stats@southwell.io',
            'AdminPass' => 'BE6GbqjjpCPmeqgIXY4hAAq8eRIVfVhG5/I8UCQrdGC0', //'MmeJK>999',
            'AdminName' => 'Core System Mailer',
            'WebMaster' => '',
            'SupportMaster' => '',
            'TechMaster' => '',
            'MtMaster' => '',
            'VasWebMaster' => '',
            'SalesMaster' => '',
        ],
        'Messages' => [
            'OptOut' => 'You will no longer receive Bulk SMS/Promotional Messages. You can still continue participating. Helpline {Helpline}.',
            'OptIn' => 'Opted in to receive Bulk SMS. Continue participating. Helpline {Helpline}.',
            'Help' => "BUY the following CADBURY SKUs: scratch & send unique code to 40400. There is INSTANT AIRTIME. T&Cs Apply. Helpline {Helpline}.\n- 125g, 225g or 450g Cadbury 2-in-1 Drinking Chocolate;\n- 500g Cadbury 3-in-1 Hot Cocoa;and/or\n- 230g Cadbury Eclairs",
            'BlackListed' => 'Thank you for participating in Cadbury promo. Share a Sweet Moment. Helpline {Helpline}.',
            'CampaignLimit' => "Your submission has not been successful. Kindly contact customer service on this number. Helpline {Helpline}.",
            'CampaignEarly' => 'Sorry, the promotion has not started. Keep the code and send when the promotion starts. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'CampaignClosed' => 'Sorry, the promotion is closed. Thank you for being our customer. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'InvalidCodeEntry' => 'Sorry,this code is invalid. Please confirm that you have entered correctly and try again. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'RedeemedEntry' => 'This code has already been redeemed. Please use a different code and try again. Cadbury Share a Sweet Moment. T&Cs Apply. Helpline {Helpline}.',
            'BlackListedWinnerEntry' => 'Your submission has not been successful. Kindly contact customer service on this number. Helpline {Helpline}.',
            'NonWinnerEntry' => 'Thank you for participating in Cadbury promo. T&Cs Apply. Helpline {Helpline}.',
            'WinnerEntry' => '{praise}! You have won Airtime from Cadbury Share a Sweet Moment. You will receive your reward shortly. T&Cs Apply. Helpline {Helpline}.',
        ],
    ],]);
