<?php

/**
 * Description of ProfileController
 *
 * @author kevinkmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class ProfileController extends ControllerBase {

    protected $payload;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
    }

    /**
     * loginAction
     * @return type
     */
    public function loginAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $msisdnN = isset($data->msisdn) ? $data->msisdn : NULL;
        $source = isset($data->source) ? $data->source : NULL;

        $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
        $len = rand(1000, 999999);
        $payloadToken = ['data' => $len . "" . $this->now()];
        $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
        $verification_code = rand(1000, 9999);
        $password = $this->security->hash(md5($verification_code));

        if (!$msisdnN || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        $msisdn = $this->formatMobileNumber($msisdnN);
        if (!$this->validateMobile($msisdn)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
        }
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
            $email = null;
            if (!$user_id) {
                $user = new User();
                $user->setTransaction($dbTrxn);
                $user->profile_id = $profile_id;
                $user->role_id = 5;
                $user->api_token = $newToken;
                $user->password = $password;
                $user->status = 2;
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
                $email = $checkUser->email;
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
            } else {
                $checkUserLogin->setTransaction($dbTrxn);
                $checkUserLogin->login_code = md5($verification_code);
                if ($checkUserLogin->save() === false) {
                    $errors = [];
                    $messages = $checkUserLogin->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                }
            }

            $message = "Hello, Your verification code is: $verification_code";
            $dbTrxn->commit();
            $params = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderIdOTP'],
                "msisdn" => $msisdn,
                "message" => $message,
                "profile_id" => $profile_id,
                "created_by" => 'USER_CREATE',
                "is_bulk" => true,
                "link_id" => ""];

            $messageSMS = new Messaging();

            if ($email != null) {

                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $email,
                    "cc" => "",
                    "subject" => "Authentication Login",
                    "content" => $message,
                    "extrac" => null
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                        $postData, $this->settings['ServiceApiKey'], 3);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailTickets Response::" . json_encode($mailResponse));
            }

            $queueMessageResponse = false;
            if ($source != 'USSD') {
                $queueMessageResponse = $messageSMS->LogOutbox($params);
            }
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | email Response::" . $email);

            if (!$queueMessageResponse) {

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "User OTP Failed sent"
                                , []);
            }

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "User OTP Successfully sent"
                            , []);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * loginAction
     * @return type
     */
    public function signUpAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $msisdnN = isset($data->msisdn) ? $data->msisdn : NULL;
        $source = isset($data->source) ? $data->source : NULL;

        $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
        $len = rand(1000, 999999);
        $payloadToken = ['data' => $len . "" . $this->now()];
        $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));

        $verification_code = rand(1000, 9999);
        $password = $this->security->hash(md5($verification_code));

        if (!$msisdnN || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($this->validateEmail($msisdnN)) {
            $checkUser = User::findFirst(['email=:email:'
                        , 'bind' => ['email' => $msisdnN]]);
            if (!$checkUser) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Mobile Number']);
            }
            $checkProfile = Profile::findFirst(["profile_id=:profile_id:",
                        "bind" => ["profile_id" => $checkUser->profile_id],]);

            $msisdn = $checkProfile->msisdn;
        } else {
            $msisdn = $this->formatMobileNumber($msisdnN);
            if (!$this->validateMobile($msisdn)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Mobile Number']);
            }
        }

        if ($msisdn == "254725560980") {
            $verification_code = $this->settings['DefaultCode'];
        }
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
            $email = null;
            if (!$user_id) {
                $user = new User();
                $user->setTransaction($dbTrxn);
                $user->profile_id = $profile_id;
                $user->role_id = 5;
                $user->api_token = $newToken;
                $user->password = $password;
                $user->status = 2;
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
                $email = $checkUser->email;
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
            } else {
                $checkUserLogin->setTransaction($dbTrxn);
                $checkUserLogin->login_code = md5($verification_code);
                if ($checkUserLogin->save() === false) {
                    $errors = [];
                    $messages = $checkUserLogin->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
                }
            }

            $message = "Hello, Your verification code is: $verification_code";
            $dbTrxn->commit();

            if ($email != null) {

                $postData = [
                    "api_key" => $this->settings['ServiceApiKey'],
                    "to" => $email,
                    "from" => "noreply@madfun.com",
                    "cc" => "",
                    "subject" => "Authentication Login",
                    "content" => $message,
                    "extrac" => null
                ];
                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                        $postData, $this->settings['ServiceApiKey'], 3);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | SendEmailTickets Response::" . json_encode($mailResponse) . " Email" . $email);
            }

            $params = [
                "short_code" => $this->settings['mnoApps']['DefaultSenderIdOTP'],
                "msisdn" => $msisdn,
                "message" => $message,
                "profile_id" => $profile_id,
                "created_by" => 'USER_CREATE',
                "is_bulk" => true,
                "link_id" => ""];

            $messageSMS = new Messaging();
            $queueMessageResponse = false;
            if ($source != 'USSD') {
                $queueMessageResponse = $messageSMS->LogOutbox($params);
            }
            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | email Response::" . $email);

            if (!$queueMessageResponse) {

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "User OTP Failed sent"
                                , []);
            }

            return $this->success(__LINE__ . ":" . __CLASS__
                            , "User OTP Successfully sent"
                            , []);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function changeEventOragnizer() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $name = isset($data->name) ? $data->name : null;
        $organization = isset($data->organization) ? $data->organization : null;
        $country = isset($data->country) ? $data->country : null;
        $city = isset($data->city) ? $data->city : null;
        $email = isset($data->email) ? $data->email : null;
        $source = isset($data->source) ? $data->source : null;
        if (!$token || !$source || !$email) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!',
                            ['code' => 403, 'message' => 'Request Sources Unverified!!']);
        }
        if (!$this->validateEmail($email)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Client Email']);
        }
        $names = [];
        if ($name != null) {
            $names = explode(" ", $name);
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.', ['code' => 403, 'message' => 'Authentication Failure!!']);
            }
            $user = User::findFirst(['user_id=:user_id:'
                        , 'bind' => ['user_id' => $auth_response['user_id']]]);
            if (!$user) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }
            $user->setTransaction($dbTrxn);
            $user->role_id = 6;
            if ($email) {
                $user->email = $email;
            }
            if ($user->save() === false) {
                $errors = [];
                $messages = $user->getMessages();
                foreach ($messages as $message) {
                    $e["statusDescription"] = $message->getMessage();
                    $e["field"] = $message->getField();
                    array_push($errors, $e);
                }
                $dbTrxn->rollback("Updare User failed " . json_encode($errors));
            }
            $profileAttributeData = ProfileAttribute::findFirst(['profile_id =:profile_id:'
                        , 'bind' => ['profile_id' => $auth_response['profile_id']]]);
            if (!$profileAttributeData) {
                $verification_code = rand(1000, 9999);
                $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
                $len = rand(1000, 999999);
                $payloadToken = ['data' => $len . "" . $this->now()];
                $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
                $profileAttribute = new ProfileAttribute();
                $profileAttribute->setTransaction($dbTrxn);
                $profileAttribute->first_name = $names[0];
                $profileAttribute->last_name = $names[1];
                $profileAttribute->network = $this->getMobileNetwork($auth_response['msisdn']);
                $profileAttribute->pin = md5($verification_code);
                $profileAttribute->city = $city;
                $profileAttribute->country = $country;
                $profileAttribute->profile_id = $auth_response['profile_id'];
                $profileAttribute->token = $newToken;
                $profileAttribute->created = $this->now();
                $profileAttribute->created_by = $auth_response['user_id'];
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
            } else {
                $profileAttributeData->setTransaction($dbTrxn);
                if ($names) {
                    $profileAttributeData->first_name = $names[0];
                    $profileAttributeData->last_name = $names[1];
                }
                if ($city) {
                    $profileAttributeData->city = $city;
                }
                if ($country) {
                    $profileAttributeData->country = $country;
                }
                if ($profileAttributeData->save() === false) {
                    $errors = [];
                    $messages = $profileAttributeData->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }
                    $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
                }
            }
            $checkUserClientMap = UserClientMap::findFirst(['user_id =:user_id:'
                        , 'bind' => ['user_id' => $auth_response['user_id']]]);
            if (!$checkUserClientMap) {
                $clients = new Clients();
                $clients->setTransaction($dbTrxn);
                $clients->client_name = $organization;
                $clients->description = "Event Orginazer for Client " . $organization;
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
            }
            $dbTrxn->commit();
            return $this->success(__LINE__ . ":" . __CLASS__
                            , "User Account updated successful"
                            , ['code' => 200, 'message' => 'User Account updated successful']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * verifySignin
     * @return type
     * @throws Exception
     */
    public function verifySignin() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $regex = '/"password":"[^"]*?"/';
        $string = (preg_replace($regex, '"password":***'
                        , json_encode($request->getJsonRawBody())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Verify "
                . "Request::$string");

        $this->payload['salt'] = isset($data->salt_token) ? $data->salt_token : NULL;
        $this->payload['user_name'] = isset($data->user_name) ? $data->user_name : NULL;
        $this->payload['verification_code'] = isset($data->verification_code) ? $data->verification_code : NULL;
        $this->payload['password'] = isset($data->password) ? $data->password : NULL;

        if (!$this->payload['verification_code'] || !$this->payload['user_name'] || !$this->payload['salt']) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        try {
            if (is_numeric($this->payload['user_name'])) {
                $this->payload['user_name'] = $this->formatMobileNumber($this->payload['user_name']);
            }
            $auth = new Authenticate();
            $result = $auth
                    ->QueryUserUsingMobileOrEmail($this->payload['user_name']);

            if (!$result) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'User DOES NOT Exist(s)!');
            }

            $verify_code = $auth
                    ->QueryVerificationCode($this->payload['verification_code']
                    , $result['user_id']);
            if (!$verify_code) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, "Verification Failure"
                                . ". Check the Verifiction again and try again.");
            }
            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();
            try {
                $user = User::findFirst([
                            "user_id =:user_id:",
                            "bind" => [
                                "user_id" => $result['user_id']],]);

                if ($user->status == 1) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , 'Request Successful'
                                    , ['code' => 202
                                , 'message' => 'User is already verified Successfully'
                                , 'data' => ['key' => $user->api_token, 'data' => $result, 'role_id' => $user->role_id]]);
                }

                $user->setTransaction($dbTransaction);
                $user->status = 1;
                $user->password = $this->security->hash(md5($this->payload['password']));
                if ($user->save() === false) {
                    $errors = [];
                    $messages = $user->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTransaction->rollback("Create user client mapping"
                            . " failed. Reason" . json_encode($errors));
                }
                $dbTransaction->commit();

                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Request Successful'
                                , ['code' => 200
                            , 'message' => 'User has been verified Successfully'
                            , 'data' => ['key' => $user->api_token, 'data' => $result,
                                'role_id' => $user->role_id]]);
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
     * viewMyTickets
     * @return type
     */
    public function viewMyTickets() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ViewMyTickets:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        $status = isset($data->status) ? $data->status : null;
        $uniqueReference = isset($data->uniqueReference) ? $data->uniqueReference : null;
        $eventId = isset($data->eventId) ? $data->eventId : null;
        $eventType = isset($data->eventType) ? $data->eventType : null;
        $eventprofileID = isset($data->eventprofileID) ? $data->eventprofileID : null;
        $hasShow = isset($data->hasShow) ? $data->hasShow : 0;
        $msisdn = isset($data->msisdn) ? $data->msisdn : null;
        if (!$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!in_array($status, [1, 2, 3]) && $status != null) {
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
            $sort = "event_profile_tickets.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'event_profile_tickets.event_profile_ticket_id';
            $order = 'DESC';
        }
        try {
            if ($token) {
                $auth = new Authenticate();
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
                $profileId = $auth_response['profile_id'];
            } else {
                if (!$msisdn || !$uniqueReference) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__);
                }
                $mobile = $this->formatMobileNumber($msisdn);
                if (!$this->validateMobile($mobile)) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Mobile Number']);
                }
                $profileId = Profiling::Profile($mobile);
            }


            $whereArray = [
                'event_profile_tickets.profile_id' => $profileId,
                'event_profile_tickets_state.status' => 1];

            $searchQuery = $this->whereQuery($whereArray, "");

            if ($status != null) {
                $searchQuery .= " AND events.status = $status";
            }
            if ($eventType != null) {
                if ($eventType == 1) {
                    $searchQuery .= " AND DATE(events.start_date) >= DATE(NOW())";
                } else {
                    $searchQuery .= " AND DATE(events.start_date) < DATE(NOW())";
                }
            }
            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_profile_tickets.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created)>='$start'";
            }
            if ($eventprofileID != null) {
                $searchQuery .= " AND event_profile_tickets.event_profile_ticket_id ='$eventprofileID'";
            }
            if ($uniqueReference != null) {
                $searchQuery .= " AND event_profile_tickets.reference_id ='$uniqueReference'";
            }
            if ($eventId != null) {
                $searchQuery .= " AND events.eventID=$eventId ";
            }
            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = "select events.eventID,profile_attribute.first_name,profile_attribute.last_name,events.eventName,events.start_date,events.posterURL,events.aboutEvent,events.status,"
                    . "event_profile_tickets.event_profile_ticket_id,events.venue,event_profile_tickets.barcodeURL,event_profile_tickets.hasRefunded,"
                    . "event_profile_tickets.barcode,event_profile_tickets.isComplimentary,"
                    . "ticket_types.ticket_type, event_tickets_type.amount,event_tickets_type.currency, event_profile_tickets.isRemmend,"
                    . "event_profile_tickets.created,(select "
                    . "count(event_profile_tickets.event_profile_ticket_id)"
                    . " from  event_profile_tickets join event_profile_tickets_state "
                    . "on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "event_tickets_type on event_tickets_type.event_ticket_id "
                    . "= event_profile_tickets.event_ticket_id join events on "
                    . "events.eventID = event_tickets_type.eventId join ticket_types"
                    . " on ticket_types.typeId = event_tickets_type.typeId $searchQuery) as "
                    . "total_count from  event_profile_tickets join event_profile_tickets_state"
                    . " on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . "event_tickets_type on event_tickets_type.event_ticket_id "
                    . "= event_profile_tickets.event_ticket_id join events on"
                    . " events.eventID = event_tickets_type.eventId join ticket_types"
                    . " on ticket_types.typeId = event_tickets_type.typeId "
                    . " join profile_attribute on event_profile_tickets.profile_id = "
                    . "profile_attribute.profile_id $searchQuery $sorting";

            if ($hasShow == 1) {
                $sql = "select event_shows.show,event_shows.event_show_id,event_show_venue.event_show_venue_id,  profile_attribute.first_name,profile_attribute.last_name,events.eventName,events.start_date,events.posterURL,events.aboutEvent,events.status,"
                        . "event_profile_tickets.event_profile_ticket_id,events.venue,event_profile_tickets.barcodeURL,event_profile_tickets.hasRefunded,"
                        . "event_profile_tickets.barcode,event_profile_tickets.isComplimentary,"
                        . "ticket_types.ticket_type, event_show_tickets_type.amount, event_profile_tickets.isRemmend,"
                        . "event_profile_tickets.created,(select "
                        . "count(event_profile_tickets.event_profile_ticket_id)"
                        . " from  event_profile_tickets join event_profile_tickets_state "
                        . "on event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id "
                        . "= event_profile_tickets.event_ticket_id JOIN event_show_venue on event_show_venue.event_show_venue_id "
                        . "= event_show_tickets_type.event_show_venue_id  JOIN event_shows on "
                        . "event_shows.event_show_id = event_show_venue.event_show_id join events on"
                        . " events.eventID  = event_shows.eventID join ticket_types"
                        . " on ticket_types.typeId = event_show_tickets_type.typeId $searchQuery) as "
                        . "total_count from  event_profile_tickets join event_profile_tickets_state"
                        . " on event_profile_tickets.event_profile_ticket_id = "
                        . "event_profile_tickets_state.event_profile_ticket_id join "
                        . "event_show_tickets_type on event_show_tickets_type.event_ticket_show_id "
                        . "= event_profile_tickets.event_ticket_id JOIN event_show_venue on event_show_venue.event_show_venue_id "
                        . "= event_show_tickets_type.event_show_venue_id  JOIN event_shows on "
                        . "event_shows.event_show_id = event_show_venue.event_show_id join events on"
                        . " events.eventID  = event_shows.eventID join ticket_types"
                        . " on ticket_types.typeId = event_show_tickets_type.typeId join "
                        . "profile_attribute on event_profile_tickets.profile_id = profile_attribute.profile_id  "
                        . "$searchQuery  and isShowTicket = 1 $sorting";
            }


            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ViewMyTickets:" . $sql);

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
     * viewMyTickets
     * @return type
     */
    public function viewMyPayments() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | viewMyPayments:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        if (!$source) {
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
            $sort = "myPayments.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'myPayments.transaction_id';
            $order = 'DESC';
        }
        try {
            if ($token) {
                $auth = new Authenticate();
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
                $profileId = $auth_response['profile_id'];
            } else {
                if (!$msisdn || !$uniqueReference) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__);
                }
                $mobile = $this->formatMobileNumber($msisdn);
                if (!$this->validateMobile($mobile)) {
                    return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Invalid Mobile Number']);
                }
                $profileId = Profiling::Profile($mobile);
            }



            $searchQuery2 = " WHERE transaction_initiated.profile_id=" . $profileId;
            $searchQuery = " WHERE event_profile_tickets.profile_id=" . $profileId;

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) BETWEEN '$start' AND '$stop' ";
                $searchQuery2 .= " AND date(transaction_initiated.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_profile_tickets.created)<='$stop'";
                $searchQuery2 .= " AND date(transaction_initiated.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created)>='$start'";
                $searchQuery2 .= " AND date(transaction_initiated.created)>='$start'";
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
            $sql = " SELECT * FROM ( select transaction.transaction_id,'M-Pesa' COLLATE utf8mb3 "
                    . "as paymentType,transaction.description as unique_reference, "
                    . "mpesa_transaction.mpesa_code as pay_receipt, mpesa_transaction.mpesa_amount "
                    . "as amountPay,mpesa_transaction.mpesa_account as account,"
                    . "mpesa_transaction.mpesa_time as payDate  from transaction "
                    . "join mpesa_transaction on transaction.reference_id  = mpesa_transaction.id "
                    . "join event_profile_tickets on event_profile_tickets.reference_id"
                    . "  = transaction.description $searchQuery  UNION select "
                    . "dpo_transaction_initiated.transaction_id,'Card' COLLATE utf8mb3 as paymentType,"
                    . "transaction_initiated.extra_data->>'$.unique_id' as unique_reference,"
                    . " dpo_transaction.CCDapproval as pay_receipt,transaction_initiated.extra_data->>'$.amount' "
                    . "as amountPay, dpo_transaction.account, dpo_transaction.created as payDate "
                    . "from  dpo_transaction join dpo_transaction_initiated on "
                    . "dpo_transaction.TransactionToken = dpo_transaction_initiated.TransactionToken "
                    . "join transaction_initiated on dpo_transaction_initiated.transaction_id "
                    . "= transaction_initiated.transaction_id $searchQuery2) as"
                    . " myPayments $sorting";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ViewMyTickets:" . $sql);

            $result = $this->rawSelect($sql);
            if (empty($result)) {
                $stop_time = $this->getMicrotime() - $start_time;
                return $this->success(__LINE__ . ":" . __CLASS__, 'No Payments Found', [
                            'code' => 404
                            , 'message' => "Query returned no results ( $stop_time Seconds)", 'data' => []
                            , 'record_count' => 0], true);
            }
            $stop_time = $this->getMicrotime() - $start_time;
            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Ok'
                            , ['code' => 200
                        , 'message' => "Successfully Queried Payments results ($stop_time Seconds)"
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    public function viewMyTicketGroupByEvents() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ViewMyTickets:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        $status = isset($data->status) ? $data->status : null;
        $eventId = isset($data->eventId) ? $data->eventId : null;
        if (!$token || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if (!in_array($status, [1, 2, 3]) && $status != null) {
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
            $whereArray = [
                'event_profile_tickets.profile_id' => $auth_response['profile_id'],
                'event_profile_tickets_state.status' => 1];

            $searchQuery = $this->whereQuery($whereArray, "");

            if ($status != null) {
                $searchQuery .= " AND events.status = $status";
            }

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created) BETWEEN '$start' AND '$stop' ";
            }
            if ($stop != null && $start == null) {
                $searchQuery .= " AND date(event_profile_tickets.created)<='$stop'";
            }
            if ($stop == null && $start != null) {
                $searchQuery .= " AND date(event_profile_tickets.created)>='$start'";
            }

            if ($eventId != null) {
                $searchQuery .= " AND events.eventID=$eventId ";
            }

            $sql = "select events.eventID,events.eventName, events.venue,events.hasMultipleShow, "
                    . "events.posterURL, events.start_date,events.status,event_show_tickets_type.currency, "
                    . "count(event_profile_tickets.event_profile_ticket_id) "
                    . "AS totalTickets,  null as eventShows from event_profile_tickets join event_profile_tickets_state"
                    . " on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id  join "
                    . "event_tickets_type on event_profile_tickets.event_ticket_id = "
                    . "event_tickets_type.event_ticket_id join events on "
                    . "events.eventID  = event_tickets_type.eventId $searchQuery"
                    . " and event_profile_tickets.isShowTicket =0 group by events.eventName"
                    . "  UNION select events.eventID,events.eventName,events.venue,"
                    . "events.hasMultipleShow,events.posterURL,events.start_date,"
                    . "events.status, count(event_profile_tickets.event_profile_ticket_id)"
                    . " AS totalTickets,GROUP_CONCAT(event_shows.`show`"
                    . " ORDER BY event_shows.event_show_id SEPARATOR ',') as "
                    . "eventShows from event_profile_tickets join event_profile_tickets_state"
                    . " on event_profile_tickets.event_profile_ticket_id = "
                    . "event_profile_tickets_state.event_profile_ticket_id join "
                    . " event_show_tickets_type on event_profile_tickets.event_ticket_id "
                    . "= event_show_tickets_type.event_ticket_show_id join"
                    . " event_show_venue ON event_show_tickets_type.event_show_venue_id "
                    . "= event_show_venue.event_show_venue_id join event_shows "
                    . "on event_show_venue.event_show_id = event_shows.event_show_id"
                    . " join events on event_shows.eventID =  events.eventID $searchQuery"
                    . " and event_profile_tickets.isShowTicket =1 group"
                    . " by events.eventName";

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | ViewMyTickets:" . $sql);

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
                        , 'record_count' => count($result), 'data' => $result]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

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
        $show = isset($data->show) ? $data->show : 0;

        if (!$token || !$source || !$phone || !$barcode || !$name) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($name) {
            $names = explode(" ", $name);
        }

        if (!is_array($barcode)) {
            $barcode = [$barcode];
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

            $hasError = false;
            $errorTotal = 0;
            $errorMessage = [];
            $successMessage = [];

            foreach ($barcode as $bar) {

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
                            . " WHERE event_profile_tickets.barcode=:barcode  and "
                            . "event_profile_tickets.profile_id=:profile_id "
                            . "AND event_profile_tickets_state.status = 1 AND "
                            . "event_profile_tickets.isShowTicket = 1";
                } 
                else {
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
                            . " WHERE event_profile_tickets.barcode=:barcode AND "
                            . "event_profile_tickets.profile_id=:profile_id "
                            . "AND event_profile_tickets_state.status = 1";
                }
                $paramsSQL = [':barcode' => $bar,
                    ':profile_id' => $auth_response['profile_id']];

                $eventProfileTickets = $this->selectQuery($sql
                        , $paramsSQL);

                if (!$eventProfileTickets) {
                    $hasError = true;
                    $responseData = [
                        "Barcode" => $bar,
                        "Error" => "Request Failed. Event ticket not found!!"
                    ];
                    array_push($errorMessage, $responseData);
                    $errorTotal = $errorTotal + 1;
                    continue;
                }

                if ($eventProfileTickets[0]['isRemmend']) {
                    $hasError = true;
                    $responseData = [
                        "Barcode" => $bar,
                        "Error" => "Request Failed. Event Ticket has been redemmed!!"
                    ];
                    array_push($errorMessage, $responseData);
                    $errorTotal = $errorTotal + 1;
                    continue;
                }


                if ($show == 1) {
                    $checkEventTicketIDNew = EventShowTicketsType::findFirst(["event_ticket_show_id=:event_ticket_show_id:",
                                "bind" => ["event_ticket_show_id" => $eventProfileTickets[0]['event_ticket_id']],]);

                    if (!$checkEventTicketIDNew) {

                        $hasError = true;
                        $responseData = [
                            "Barcode" => $bar,
                            "Error" => "Invalid New Event ticket Id"
                        ];
                        array_push($errorMessage, $responseData);
                        continue;
                    }
                    $checkEventShowVenue = EventShowVenue::findFirst(["event_show_venue_id=:event_show_venue_id:",
                                "bind" => ["event_show_venue_id" => $checkEventTicketIDNew->event_show_venue_id],]);
                    if (!$checkEventShowVenue) {

                        $hasError = true;
                        $responseData = [
                            "Barcode" => $bar,
                            "Error" => "Event show venue not configured properly consult system admin"
                        ];
                        array_push($errorMessage, $responseData);
                        continue;
                    }
                    $checkEventShow = EventShows::findFirst(["event_show_id=:event_show_id:",
                                "bind" => ["event_show_id" => $checkEventShowVenue->event_show_id],]);
                    if (!$checkEventShow) {

                        $hasError = true;
                        $responseData = [
                            "Barcode" => $bar,
                            "Error" => "Event show not configured properly consult system admin'"
                        ];
                        array_push($errorMessage, $responseData);
                        continue;
                    }
                    $eventID = $checkEventShow->eventID;
                } else {
                    $dataEvent = Tickets::queryEventTicketType(['event_ticket_id' => $eventProfileTickets[0]['event_ticket_id']]);

                    $eventID = $dataEvent['eventId'];
                }



                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | shareTicket EventId:" . $eventID);

                if (!$eventID) {
                    $hasError = true;
                    $responseData = [
                        "Barcode" => $bar,
                        "Error" => "Request Failed. Event ticket not found!!"
                    ];
                    array_push($errorMessage, $responseData);
                    $errorTotal = $errorTotal + 1;
                    continue;
                }
                $checkEvents = Events::findFirst(["eventID=:eventID:",
                            "bind" => ["eventID" => $eventID],]);
                if (!$checkEvents) {

                    $hasError = true;
                    $responseData = [
                        "Barcode" => $bar,
                        "Error" => "Request Failed. Event not found"
                    ];
                    array_push($errorMessage, $responseData);
                    $errorTotal = $errorTotal + 1;
                    continue;
                }
                if ($checkEvents->status != 1) {

                    $hasError = true;
                    $responseData = [
                        "Barcode" => $bar,
                        "Error" => "Request Failed. Event is not active. You cannot share the ticket!!"
                    ];
                    array_push($errorMessage, $responseData);
                    $errorTotal = $errorTotal + 1;
                    continue;
                }
                if ($this->now() > $checkEvents->end_date) {
                    
                    $hasError = true;
                    $responseData = [
                        "Barcode" => $bar,
                        "Error" => "Request Failed. Event is Closed. You cannot share ticket!!"
                    ];
                    array_push($errorMessage, $responseData);
                    $errorTotal = $errorTotal + 1;
                    continue;
                }

                $profile_id = Profiling::Profile($msisdn);

                $this->infologger->info(__LINE__ . ":" . __CLASS__
                        . " | shareTicket ProfileId:" . $profile_id);

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

                $sharedID = Tickets::shareTickets($params);

                if (!$sharedID) {
                    return $this->dataError(__LINE__ . ":" . __CLASS__
                                    , 'Validation Error'
                                    , ['code' => 422, 'message' => 'Unbale to share '
                                . 'ticket. Contact Madfun System Admin']);
                }
                $sms = "Dear " . $name . ", Your " . $checkEvents->eventName . " ticket "
                        . "is " . $QRCode . ". View your ticket from "
                        . $this->settings['TicketBaseURL'] . "?evtk=" . $QRCode . "."
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
                        "subject" => "Ticket Purchase for Event: " . $checkEvents->eventName,
                        "content" => "Ticket information",
                        "extrac" => $paramsEmail
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'], $postData, $this->settings['ServiceApiKey'], 3);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailTickets Response::" . json_encode($mailResponse));
                }
                
                $succMessage= [
                   "sharedID"=> $sharedID,
                   "Barcode" => $bar,
                   "Message"=>  "Ticket Has been shared successful"
                ];
                array_push($successMessage, $succMessage);
            }
            $responseData = [
                "hasError"=> $hasError,
                "error"=>$errorMessage,
                "success"=> $successMessage
            ];
            return $this->success(__LINE__ . ":" . __CLASS__
                            , "Ticket Has been shared successful"
                            ,  $responseData);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exceptions:" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }

    /**
     * editUsers
     * @return type
     * @throws Exception
     */
    public function editProfile() {
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
            $checkUser = User::findFirst(['profile_id=:profile_id:'
                        , 'bind' => ['profile_id' => $auth_response['profile_id']]]);

            $profileID = $auth_response['profile_id'];

            if (!$checkUser) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Account does not exist']);
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {


                $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $profileID]]);
                $checkProfileAttrinute->setTransaction($dbTrxn);
                if ($fname != null) {
                    $checkProfileAttrinute->first_name = $fname;
                }
                if ($lname != null) {
                    $checkProfileAttrinute->last_name = $lname;
                }
                if ($sname != null) {
                    $checkProfileAttrinute->surname = $sname;
                }
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

                $dbTrxn->commit();
                return $this->success(__LINE__ . ":" . __CLASS__
                                , "User Account Updated Successful"
                                , []);
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
}
