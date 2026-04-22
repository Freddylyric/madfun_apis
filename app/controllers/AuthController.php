<?php

/**
 * Description of ProfileController
 *
 * @author kevinkmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class AuthController extends ControllerBase {

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

        $user_name = isset($data->user_name) ? $data->user_name : NULL;
        $source = isset($data->source) ? $data->source : NULL;
        $pass_word = isset($data->password) ? $data->password : NULL;
        if ($this->checkForMySQLKeywords($user_name) || $this->checkForMySQLKeywords($source) || $this->checkForMySQLKeywords($pass_word)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!$user_name || !$source || !$pass_word) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request '
                            . 'Sources Unverified!!');
        }

        if (is_numeric($user_name)) {
            $user_name = $this->formatMobileNumber($user_name);
            if (!$this->validateMobile($user_name)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Mobile Number']);
            }
        }
        try {
            $auth = new Authenticate();
            $result = $auth
                    ->QueryUserUsingMobileOrEmail($user_name);

            if (!$result) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 425, 'message' => 'You do not have an'
                                    . ' account. Kindly sign up to proceed']);
           
            }
            if ($result['status'] == -5) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 425, 'message' => 'Account has been disabled.'
                                    . ' Contact 0115555000 for assistance']);
            }
           
            if ($source == 'WEB') {
                $password = base64_decode($pass_word);
            } else {
                $password = $pass_word;
            }
            if (!$this->security->checkHash(md5($password), $result["password"])) {
                $auth->logLoginAttempt($result['user_id']);

                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 425, 'message' => 'An Error Occured,'
                                    . ' Invalid Username or Password!']);
                
            }

            $check = $auth->canLogin($result['user_id']);

            if (!$check) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , "Maximum number of retries reached. "
                                . "Try again after "
                                . $this->settings['Authentication']['failedAttemptsInterval']
                                . " Minutes");
            }
            $x = $auth->QueryUserUsingUserId($result['user_id']);
            if (!$x) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'An error occured while accessing user account.');
            }

            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Request Successful'
                            , ['code' => 200
                        , 'message' => 'User has been verified Successfully'
                        , 'data' => ['key' => $x['api_token'],
                            "msisdn" => $x['msisdn'],
                            "email" => $x['email'],
                            "first_name" => $x['first_name'],
                            "last_name" => $x['last_name'],
                            'role_id' => $x['role_id']]]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
    
    

    /**
     * signUpAction
     * @return type
     */
//    public function signUpAction() {
//        $request = new Request();
//        $data = $request->getJsonRawBody();
//
//        $this->infologger = $this->getLogFile('info');
//        $this->errorlogger = $this->getLogFile('error');
//        $this->infologger->info(__LINE__ . ":" . __CLASS__
//                . " | Create User Request:" . json_encode($request->getJsonRawBody()));
//
//        $msisdnN = isset($data->msisdn) ? $data->msisdn : NULL;
//        $first_name = isset($data->first_name) ? $data->first_name : NULL;
//        $last_name = isset($data->last_name) ? $data->last_name : NULL;
//        $emailAddress = isset($data->email) ? $data->email : NULL;
//        $role_id = isset($data->role_id) ? $data->role_id : 5;
//        $source = isset($data->source) ? $data->source : NULL;
//        $age_bracket = isset($data->age_bracket) ? $data->age_bracket : NULL;
//        $gender = isset($data->gender) ? $data->gender : NULL;
//        $passwordNew = isset($data->password) ? $data->password : NULL;
//
//        $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
//        $len = rand(1000, 999999);
//        $payloadToken = ['data' => $len . "" . $this->now()];
//        $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
//        $verification_code = $passwordNew;
//        if ($this->checkForMySQLKeywords($msisdnN) || $this->checkForMySQLKeywords($first_name) || $this->checkForMySQLKeywords($last_name) || $this->checkForMySQLKeywords($emailAddress) || $this->checkForMySQLKeywords($role_id) || $this->checkForMySQLKeywords($source) || $this->checkForMySQLKeywords($age_bracket) || $this->checkForMySQLKeywords($passwordNew) || $this->checkForMySQLKeywords($gender)) {
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
//        }
//        if (!$passwordNew) {
//            $verification_code = rand(1000, 9999);
//        }
//        $password = $this->security->hash(md5($verification_code));
//
//        if (!$msisdnN || !$source || !$emailAddress) {
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
//        }
//        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
//            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
//        }
//        if (!in_array($role_id, ['6', '5'])) {
//            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Role Unverified!!');
//        }
//        if ($gender != null) {
//            if (!in_array(strtoupper($gender), ["FEMALE", "MALE", "OTHER"])) {
//                return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                                , 'Validation Error'
//                                , ['code' => 422, 'message' => 'Invalid Gender Type']);
//            }
//        }
//        $msisdn = $this->formatMobileNumber($msisdnN);
//        if (!$this->validateMobile($msisdn)) {
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                            , 'Validation Error'
//                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
//        }
//        if (!$this->validateEmail($emailAddress)) {
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                            , 'Validation Error'
//                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
//        }
//        $transactionManager = new TransactionManager();
//        $dbTrxn = $transactionManager->get();
//        try {
//            $checkProfile = Profile::findFirst(["msisdn=:msisdn:",
//                        "bind" => ["msisdn" => $msisdn],]);
//            $profile_id = isset($checkProfile->profile_id) ?
//                    $checkProfile->profile_id : false;
//            if (!$profile_id) {
//                $profile = new Profile();
//                $profile->setTransaction($dbTrxn);
//                $profile->msisdn = $msisdn;
//                $profile->created = $this->now();
//                if ($profile->save() === false) {
//                    $errors = [];
//                    $messages = $profile->getMessages();
//                    foreach ($messages as $message) {
//                        $e["statusDescription"] = $message->getMessage();
//                        $e["field"] = $message->getField();
//                        array_push($errors, $e);
//                    }
//                    $dbTrxn->rollback("Create Profile failed " . json_encode($errors));
//                }
//                $profile_id = $profile->profile_id;
//            }
//            $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
//                        , 'bind' => ['profile_id' => $profile_id]]);
//            if (!$checkProfileAttrinute) {
//                $profileAttribute = new ProfileAttribute();
//                $profileAttribute->network = $this->getMobileNetwork($msisdn);
//                $profileAttribute->pin = md5($verification_code);
//                $profileAttribute->profile_id = $profile_id;
//                $profileAttribute->first_name = $first_name;
//                $profileAttribute->last_name = $last_name;
//                $profileAttribute->gender = $gender;
//                $profileAttribute->age_bracket = $age_bracket;
//                $profileAttribute->token = $newToken;
//                $profileAttribute->created = $this->now();
//                $profileAttribute->created_by = 1; //$auth_response['user_id'];
//                if ($profileAttribute->save() === false) {
//                    $errors = [];
//                    $messages = $profileAttribute->getMessages();
//                    foreach ($messages as $message) {
//                        $e["statusDescription"] = $message->getMessage();
//                        $e["field"] = $message->getField();
//                        array_push($errors, $e);
//                    }
//                    $dbTrxn->rollback("Create Profile Attribute failed " . json_encode($errors));
//                }
//            }
//
//            $checkUser = User::findFirst(['profile_id=:profile_id: OR email =:email:'
//                        , 'bind' => ['profile_id' => $profile_id, 'email'=> $emailAddress]]);
//            if ($checkUser) {
//
//                return $this->unProcessable(__LINE__ . ":" . __CLASS__
//                                , 'Validation Error'
//                                , ['code' => 422, 'message' => 'You already have an account. Kindly user forgot password option.']);
//            }
//
//            $user = new User();
//            $user->setTransaction($dbTrxn);
//            $user->profile_id = $profile_id;
//            $user->role_id = $role_id;
//            $user->api_token = $newToken;
//            $user->password = $password;
//            $user->email = $emailAddress;
//            $user->status = 2;
//            $user->created = $this->now();
//            if ($user->save() === false) {
//                $errors = [];
//                $messages = $user->getMessages();
//                foreach ($messages as $message) {
//                    $e["statusDescription"] = $message->getMessage();
//                    $e["field"] = $message->getField();
//                    array_push($errors, $e);
//                }
//                $dbTrxn->rollback("Create User failed " . json_encode($errors));
//            }
//            $user_id = $user->user_id;
//
//            $checkUserLogin = UserLogin::findFirst(['user_id=:user_id:'
//                        , 'bind' => ['user_id' => $user_id]]);
//
//            if (!$checkUserLogin) {
//                $UserLogin = new UserLogin();
//                $UserLogin->setTransaction($dbTrxn);
//                $UserLogin->created = $this->now();
//                $UserLogin->user_id = $user_id;
//                $UserLogin->login_code = md5($verification_code);
//                if ($UserLogin->save() === false) {
//                    $errors = [];
//                    $messages = $UserLogin->getMessages();
//                    foreach ($messages as $message) {
//                        $e["statusDescription"] = $message->getMessage();
//                        $e["field"] = $message->getField();
//                        array_push($errors, $e);
//                    }
//
//                    $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
//                }
//            } else {
//                $checkUserLogin->setTransaction($dbTrxn);
//                $checkUserLogin->login_code = md5($verification_code);
//                if ($checkUserLogin->save() === false) {
//                    $errors = [];
//                    $messages = $checkUserLogin->getMessages();
//                    foreach ($messages as $message) {
//                        $e["statusDescription"] = $message->getMessage();
//                        $e["field"] = $message->getField();
//                        array_push($errors, $e);
//                    }
//
//                    $dbTrxn->rollback("Create User Login failed. Reason" . json_encode($errors));
//                }
//            }
//
//            /**
//             * Send Email as well
//             */
//            $message = "Hello, Your verification code is: $verification_code";
//            $dbTrxn->commit();
//            $params = [
//                "short_code" => $this->settings['mnoApps']['DefaultSenderIdOTP'],
//                "msisdn" => $msisdn,
//                "message" => $message,
//                "profile_id" => $profile_id,
//                "created_by" => 'USER_CREATE',
//                "is_bulk" => true,
//                "link_id" => ""];
//
//            $messageSMS = new Messaging();
//            $queueMessageResponse = false;
//            if ($source != 'USSD') {
//                $queueMessageResponse = $messageSMS->LogOutbox($params);
//            }
//            $this->infologger->info(__LINE__ . ":" . __CLASS__
//                    . " | email Response::" . $emailAddress);
//            if ($emailAddress != null) {
//
//                $postData = [
//                    "api_key" => $this->settings['ServiceApiKey'],
//                    "to" => $emailAddress,
//                    "from" => "noreply@madfun.com",
//                    "cc" => "",
//                    "subject" => "Authentication Login",
//                    "content" => $message,
//                    "extrac" => null
//                ];
//                $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
//                        $postData, $this->settings['ServiceApiKey'], 3);
//
//                $this->infologger->info(__LINE__ . ":" . __CLASS__
//                        . " | SendEmailTickets Response::" . json_encode($mailResponse));
//            }
//            if (!$queueMessageResponse) {
//
//                return $this->success(__LINE__ . ":" . __CLASS__
//                                , "User OTP Failed sent"
//                                , []);
//            }
//
//            return $this->success(__LINE__ . ":" . __CLASS__
//                            , "User OTP Successfully sent"
//                            , []);
//        } catch (Exception $ex) {
//            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
//                    . " | Exceptions:" . $ex->getMessage());
//
//            return $this->serverError(__LINE__ . ":" . __CLASS__
//                            , 'Internal Server Error.');
//        }
//    }
//    
    
    
    
    
        public function signUpAction()
    {
        $request = new Request();
        $data    = $request->getJsonRawBody();
 
        $this->infologger->info(__METHOD__ . ' | signup | msisdn=' .
            ($data->msisdn ?? 'n/a') . ' source=' . ($data->source ?? 'n/a'));
 
        $msisdnRaw    = isset($data->msisdn)       ? trim($data->msisdn)       : null;
        $first_name   = isset($data->first_name)   ? trim($data->first_name)   : null;
        $last_name    = isset($data->last_name)     ? trim($data->last_name)    : null;
        $emailAddress = isset($data->email)         ? trim($data->email)        : null;
        $role_id      = isset($data->role_id)       ? (int)$data->role_id       : 5;
        $source       = isset($data->source)        ? trim($data->source)       : null;
        $age_bracket  = isset($data->age_bracket)   ? $data->age_bracket        : null;
        $gender       = isset($data->gender)        ? strtoupper(trim($data->gender)) : null;
        $passwordNew  = isset($data->password)      ? $data->password           : null;
 
        // ── Validation ──────────────────────────────────────────
        if (!$msisdnRaw || !$source || !$emailAddress) {
            return $this->unProcessable(__METHOD__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__METHOD__, 'Request source unverified.');
        }
        if (!in_array((string)$role_id, ['5', '6'])) {
            return $this->unAuthorised(__METHOD__, 'Role unverified.');
        }
        if ($gender !== null && !in_array($gender, ['FEMALE', 'MALE', 'OTHER'])) {
            return $this->unProcessable(__METHOD__, 'Validation Error',
                ['code' => 422, 'message' => 'Invalid gender. Accepted: FEMALE, MALE, OTHER.']);
        }
 
        $msisdn = $this->formatMobileNumber($msisdnRaw);
        if (!$this->validateMobile($msisdn)) {
            return $this->unProcessable(__METHOD__, 'Validation Error',
                ['code' => 422, 'message' => 'Invalid mobile number.']);
        }
        if (!$this->validateEmail($emailAddress)) {
            return $this->unProcessable(__METHOD__, 'Validation Error',
                ['code' => 422, 'message' => 'Invalid email address.']); // was: "Invalid Mobile Number" – bug fixed
        }
 
        // OTP: use supplied password or auto-generate a 4-digit code.
        $verification_code = $passwordNew ?: rand(1000, 9999);
        $hashedPassword    = $this->security->hash(md5($verification_code));
 
        
        $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";

                
        // Token for api_token and profile_attribute.token
        $tokenPayload = ['data' => rand(1000, 999999) . $this->now()];
        $newToken     = md5($this->createNewAuthToken(
            $tokenPayload,
            $secretKey
        ));
 
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
 
        try {
            // ── Duplicate-account guard FIRST (before writing anything) ──────
            // Bug fix: original checked for duplicate user AFTER creating profile/
            // profile_attribute, leaving orphan rows when the account already existed.
            $existingUser = User::findFirst([
                'profile_id IN (SELECT profile_id FROM profile WHERE msisdn=:msisdn:) OR email=:email:',
                'bind' => ['msisdn' => $msisdn, 'email' => $emailAddress],
            ]);
            if ($existingUser) {
                return $this->unProcessable(__METHOD__, 'Validation Error',
                    ['code' => 422, 'message' => 'Account already exists. Use the forgot password option.']);
            }
 
            $checkProfile = Profile::findFirst(['msisdn=:msisdn:', 'bind' => ['msisdn' => $msisdn]]);
            $profile_id   = $checkProfile ? (int)$checkProfile->profile_id : false;
 
            if (!$profile_id) {
                $profile          = new Profile();
                $profile->setTransaction($dbTrxn);
                $profile->msisdn  = $msisdn;
                $profile->created = $this->now();
 
                if ($profile->save() === false) {
                    $dbTrxn->rollback('Create Profile failed: ' . $this->collectOrmErrors($profile));
                }
                $profile_id = (int)$profile->profile_id;
            }
 
            $existingAttr = ProfileAttribute::findFirst([
                'profile_id=:profile_id:', 'bind' => ['profile_id' => $profile_id]
            ]);
            if (!$existingAttr) {
                $attr               = new ProfileAttribute();
                $attr->setTransaction($dbTrxn);
                $attr->profile_id   = $profile_id;
                $attr->network      = $this->getMobileNetwork($msisdn);
                $attr->pin          = md5($verification_code);
                $attr->first_name   = $first_name;
                $attr->last_name    = $last_name;
                $attr->gender       = $gender;
                $attr->age_bracket  = $age_bracket;
                $attr->token        = $newToken;
                $attr->created_by   = 1;
                $attr->created      = $this->now();
 
                if ($attr->save() === false) {
                    $dbTrxn->rollback('Create ProfileAttribute failed: ' . $this->collectOrmErrors($attr));
                }
            }
 
            $user             = new User();
            $user->setTransaction($dbTrxn);
            $user->profile_id = $profile_id;
            $user->role_id    = $role_id;
            $user->api_token  = $newToken;
            $user->password   = $hashedPassword;
            $user->email      = $emailAddress;
            $user->status     = 2; // pending verification
            $user->created    = $this->now();
 
            if ($user->save() === false) {
                $dbTrxn->rollback('Create User failed: ' . $this->collectOrmErrors($user));
            }
            $user_id = (int)$user->user_id;
 
            $checkLogin = UserLogin::findFirst(['user_id=:user_id:', 'bind' => ['user_id' => $user_id]]);
            if (!$checkLogin) {
                $loginRecord             = new UserLogin();
                $loginRecord->setTransaction($dbTrxn);
                $loginRecord->user_id    = $user_id;
                $loginRecord->login_code = md5($verification_code);
                $loginRecord->created    = $this->now();
 
                if ($loginRecord->save() === false) {
                    $dbTrxn->rollback('Create UserLogin failed: ' . $this->collectOrmErrors($loginRecord));
                }
            } else {
                $checkLogin->setTransaction($dbTrxn);
                $checkLogin->login_code = md5($verification_code);
 
                if ($checkLogin->save() === false) {
                    $dbTrxn->rollback('Update UserLogin failed: ' . $this->collectOrmErrors($checkLogin));
                }
            }
 
            $dbTrxn->commit();
 
            // ── Notifications (after commit – do not let send failures roll back) ──
            $otpMessage = "Hello, your verification code is: {$verification_code}";
 
            $smsSent = false;
            if ($source !== 'USSD') {
                $smsParams = [
                    'short_code' => $this->settings['mnoApps']['DefaultSenderIdOTP'],
                    'msisdn'     => $msisdn,
                    'message'    => $otpMessage,
                    'profile_id' => $profile_id,
                    'created_by' => 'USER_CREATE',
                    'is_bulk'    => true,
                    'link_id'    => '',
                ];
                $messaging = new Messaging();
                $smsSent   = (bool)$messaging->LogOutbox($smsParams);
            }
 
            if ($emailAddress) {
                $this->sendOtpEmail($emailAddress, $otpMessage);
            }
 
            $message = $smsSent ? 'OTP sent successfully.' : 'OTP dispatch queued.';
            return $this->success(__METHOD__, $message, []);
 
        } catch (\Exception $ex) {
            $this->errorlogger->emergency(__METHOD__ . ' | ' . $ex->getMessage());
            return $this->serverError(__METHOD__, 'Internal server error.');
        }
    }
 

    /**
     * updateAccount
     * @return type
     */
    public function updateAccount() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $api_key = isset($data->api_key) ? $data->api_key : NULL;
        $first_name = isset($data->first_name) ? $data->first_name : NULL;
        $last_name = isset($data->last_name) ? $data->last_name : NULL;
        $year = isset($data->year) ? $data->year : NULL;
        $gender = isset($data->gender) ? $data->gender : NULL;
        $source = isset($data->source) ? $data->source : NULL;
        $status = isset($data->status) ? $data->status : NULL;

        if ($this->checkForMySQLKeywords($api_key) || $this->checkForMySQLKeywords($first_name) || $this->checkForMySQLKeywords($last_name) || $this->checkForMySQLKeywords($year) || $this->checkForMySQLKeywords($gender) || $this->checkForMySQLKeywords($source)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$api_key || !$first_name || !$last_name || !$year || !$gender || !$source) {
            if (!$status) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__);
            }
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        if ($gender != null) {
            if (!in_array(strtoupper($gender), ["FEMALE", "MALE", "OTHER"])) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Gender Type']);
            }
        }
        $transactionManager = new TransactionManager();
        $dbTrxn = $transactionManager->get();
        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($api_key);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'Authentication Failure.');
            }

            if ($status != null) {
                $checkUser = User::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $auth_response['profile_id']]]);
                $checkUser->setTransaction($dbTrxn);
                $checkUser->status = $status;
                if ($checkUser->save() === false) {
                    $errors = [];
                    $messages = $checkUser->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTrxn->rollback("Update user failed. Reason" . json_encode($errors));
                }
            } else {

                $checkProfileAttrinute = ProfileAttribute::findFirst(['profile_id=:profile_id:'
                            , 'bind' => ['profile_id' => $auth_response['profile_id']]]);
                if (!$checkProfileAttrinute) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'User Not Found / Unverified!!');
                }
                $checkProfileAttrinute->setTransaction($dbTrxn);
                $checkProfileAttrinute->first_name = $first_name;
                $checkProfileAttrinute->last_name = $last_name;
                $checkProfileAttrinute->gender = $gender;
                $checkProfileAttrinute->year_of_birth = $year;
                if ($checkProfileAttrinute->save() === false) {
                    $errors = [];
                    $messages = $checkProfileAttrinute->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTrxn->rollback("Create Profile attribute failed. Reason" . json_encode($errors));
                }
            }
            $dbTrxn->commit();

            return $this->success(__LINE__ . ":" . __CLASS__
                            , 'Account Updated Successful'
                            , ['code' => 200, 'message' => 'Account'
                        . ' Updated Successful']);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

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

        if ($this->checkForMySQLKeywords($this->payload['salt']) || $this->checkForMySQLKeywords($this->payload['user_name']) || $this->checkForMySQLKeywords($this->payload['verification_code']) || $this->checkForMySQLKeywords($this->payload['password'])) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

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
                                , 'message' => 'User is already verified Successfully.Please proceed to login']);
                }

                $user->setTransaction($dbTransaction);
                $user->status = 1;
                if ($this->payload['password']) {
                    $user->password = $this->security->hash(md5($this->payload['password']));
                }

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

                $x = $auth->QueryUserUsingUserId($result['user_id']);
                if (!$x) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'An error occured while accessing user account.');
                }

                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Request Successful'
                                , ['code' => 200
                            , 'message' => 'User has been verified Successfully'
                            , 'data' => ['api_key' => $x['api_token'],]]);
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
     * forgotPassword
     * @return type
     */
    public function forgotPassword() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');

        $regex = '/"password":"[^"]*?"/';
        $string = (preg_replace($regex, '"password":***'
                        , json_encode($request->getJsonRawBody())) . PHP_EOL);
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Password Reset "
                . "Request::$string");

        $this->payload['user_name'] = isset($data->user_name) ? $data->user_name : NULL;
        $this->payload['check'] = isset($data->check) ? $data->check : NULL;

        if ($this->checkForMySQLKeywords($this->payload['user_name']) || $this->checkForMySQLKeywords($this->payload['check'])) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!$this->payload['user_name']) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        try {
            if (is_numeric($this->payload['user_name'])) {
                $this->payload['user_name'] = $this->formatMobileNumber($this->payload['user_name']);
            }

            $auth = new Authenticate();
            $result = $auth
                    ->QueryUserUsingMobileOrEmail($this->payload['user_name']);
            
              $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Password Reset "
                . "Request::". json_encode($result));

            if (!$result) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User Account is NOT Exist. Password reset NOT Possible!');
            }

//            if ($result['status'] != 1) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'User Account is NOT Active. '
//                                . 'Contact System Administator for assistance.');
//            }

            $transactionManager = new TransactionManager();
            $dbTransaction = $transactionManager->get();
            try {
                $checkAccount = User::findFirst([
                            "user_id =:user_id:",
                            "bind" => [
                                "user_id" => $result['user_id']],]);
                $checkAccount->setTransaction($dbTransaction);
                $checkAccount->status = 0;
                if ($checkAccount->save() === false) {
                    $errors = [];
                    $messages = $checkAccount->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTransaction->rollback("Update User Status failed. Reason" . json_encode($errors));
                }

                $verification_code = rand(1000, 9999);

                $checkLogin = UserLogin::findFirst([
                            "user_id =:user_id:",
                            "bind" => [
                                "user_id" => $result['user_id']],]);
                $checkLogin->setTransaction($dbTransaction);
                $checkLogin->login_code = md5($verification_code);
                if ($checkLogin->save() === false) {
                    $errors = [];
                    $messages = $checkLogin->getMessages();
                    foreach ($messages as $message) {
                        $e["statusDescription"] = $message->getMessage();
                        $e["field"] = $message->getField();
                        array_push($errors, $e);
                    }

                    $dbTransaction->rollback("User login failed. Reason" . json_encode($errors));
                }

                $File_Name = $result['first_name'];

                $dbTransaction->commit();

                $smsMessage = "Hello $File_Name,\nYour reset code is "
                        . "$verification_code Verify your account to continue.";
                
                 $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Password Reset "
                . "Request::". json_encode($smsMessage)." ".$result['msisdn']);

                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderIdOTP'],
                    "msisdn" => $result['msisdn'],
                    "message" => $smsMessage,
                    "profile_id" => $checkAccount->profile_id,
                    "created_by" => $result['user_id'],
                    "is_bulk" => true,
                    "link_id" => ""
                ];
                
              
                $message = new Messaging();
                $queueMessageResponse = $message->LogOutbox($params);
                
                
                
                  $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailTickets Response::" . $queueMessageResponse." ".$result['msisdn']);
                  
                  

                if ($result['email'] != null) {

                    $postData = [
                        "api_key" => $this->settings['ServiceApiKey'],
                        "to" => $result['email'],
                        "cc" => "",
                        "from"=> "noreply@madfun.com",
                        "subject" => "Authentication Login",
                        "content" => $smsMessage,
                        "extrac" => null
                    ];
                    $mailResponse = $this->SendJsonPostAuthData($this->settings['MailerWebURL'],
                            $postData, $this->settings['ServiceApiKey'], 3);

                    $this->infologger->info(__LINE__ . ":" . __CLASS__
                            . " | SendEmailTickets Response::" . json_encode($mailResponse));
                }

               
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Reset Password Successful and Verification Sent'
                                , ['code' => 200, 'message' => 'Password Reset '
                            . 'has been completed and verfication sent']);
            } catch (\Exception $ex) {
                throw $ex;
            }
        } catch (\Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . " | "
                    . "Exception::" . $ex->getMessage());
            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error!');
        }
    }
}
