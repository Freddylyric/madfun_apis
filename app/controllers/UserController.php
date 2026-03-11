<?php

/**
 * Description of UserController
 *
 * @author kevinkmwando
 */
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class UserController extends ControllerBase {

    protected $infologger;
    protected $errorlogger;
    protected $payload;

    /**
     * createAction
     * @return type
     * @throws Exception
     */
    public function createAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | CreateUser Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $fname = isset($data->fname) ? $data->fname : NULL;
        $sname = isset($data->sname) ? $data->sname : NULL;
        $lname = isset($data->lname) ? $data->lname : NULL;
        $email = isset($data->email) ? $data->email : NULL;
        $msisdnNew = isset($data->msisdn) ? $data->msisdn : NULL;
        $role_id = isset($data->role_id) ? $data->role_id : NULL;
        $source = isset($data->source) ? $data->source : NULL;
        $client_name = isset($data->client_name) ? $data->client_name : NULL;
        $client_id = isset($data->client_id) ? $data->client_id : NULL;
        $eventID = isset($data->eventID) ? $data->eventID : NULL;

        $secretKey = "55abe029fdebae5e1d417e2ffb2a003klkhka0cd8b54763051cef08bc55abe029";
        $len = rand(1000, 999999);
        $payloadToken = ['data' => $len . "" . $this->now()];
        $newToken = md5($this->createNewAuthToken($payloadToken, $secretKey));
        $verification_code = rand(1000, 9999);
        $password = $this->security->hash(md5($verification_code));

        if (!$token || !$email || !$msisdnNew || !$role_id || !$source) {
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
        if (!$this->validateEmail($email)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Client Email']);
        }
        try {
            $auth = new Authenticate();
            if (!in_array($source, ['WEB', 'USSD'])) {
                $auth_response = $auth->QuickTokenAuthenticate($token);
                if (!$auth_response) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
                if ($auth_response['userRole'] != 1) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'User doesn\'t have permissions to perform this action.');
                }
            } 
            else {
                if ($this->settings['ticketSystemAPI'] != $token) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'Authentication Failure.');
                }
            }
            $checkUserRole = UserRole::findFirst(["user_role_id=:user_role_id:",
                        "bind" => ["user_role_id" => $role_id],]);
            if (!$checkUserRole) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid User Role']);
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
                    $userState = 1;
                } else {
                    $userState = 0;
                }

                if (in_array($role_id, [6, 7, 8]) && $userState == 1) {
                    if ($role_id == 6) {
                        if (!$client_name) {
                            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
                        }
                        $checkClients = Clients::findFirst(['client_name=:client_name:'
                                    , 'bind' => ['client_name' => $client_name]]);
                        if (!$checkClients) {
                            $clients = new Clients();
                            $clients->setTransaction($dbTrxn);
                            $clients->client_name = $client_name;
                            $clients->description = "Event Orginazer for Client " . $client_name;
                            $clients->created_by = $user_id;
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
                        } else {
                            $checkUserClientMap = UserClientMap::findFirst(['client_id=:client_id: AND user_id =:user_id:'
                                        , 'bind' => ['client_id' => $client_id, 'user_id' => $user_id]]);

                            if (!$checkUserClientMap) {
                                $userClientMap = new UserClientMap;
                                $userClientMap->setTransaction($dbTrxn);
                                $userClientMap->client_id = $clients->client_id;
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
                            } else {
                                $user_mapId = $checkUserClientMap->user_mapId;
                            }
                        }
                    }
                    else {
                        $checkClients = Clients::findFirst(['client_id=:client_id:'
                                    , 'bind' => ['client_id' => $client_id]]);
                        if (!$checkClients) {
                            return $this->unProcessable(__LINE__ . ":" . __CLASS__ . ""
                                            . " The client doesn't exist");
                        }
                        $checkUserClientMap = UserClientMap::findFirst(['client_id=:client_id: AND user_id =:user_id:'
                                    , 'bind' => ['client_id' => $client_id, 'user_id' => $user_id]]);

                        if (!$checkUserClientMap) {
                            $userClientMap = new UserClientMap;
                            $userClientMap->setTransaction($dbTrxn);
                            $userClientMap->client_id = $client_id;
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
                        } else {
                            $user_mapId = $checkUserClientMap->user_mapId;
                        }
                    }
                    
                }

                $message = "Hello $fname, Your account has been created. \n"
                        . "Your password is $verification_code.\n Verify your account "
                        . "to continue.\nURL:" . $this->settings['AdminWebURL'];
                $dbTrxn->commit();
                if ($userState == 0) {
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "User Already Exist"
                                    , []);
                }
                $params = [
                    "short_code" => $this->settings['mnoApps']['DefaultSenderIdOTP'],
                    "msisdn" => $msisdn,
                    "message" => $message,
                    "profile_id" => $profile_id,
                    "created_by" => 'USER_CREATE',
                    "is_bulk" => true,
                    "link_id" => ""];

                $message = new Messaging();
                $queueMessageResponse = false;
                if ($source != 'USSD') {
                    $queueMessageResponse = $message->LogOutbox($params);
                }

                $data = $auth->QueryUserUsingMobile($msisdn);

                $data_array = [
                    'code' => 200,
                    'data' => $data];
                if (!$queueMessageResponse) {
                    if ($source == 'USSD' || $source == 'WEB') {
                        return $this->success(__LINE__ . ":" . __CLASS__
                                        , "User Created Successfully via USSD"
                                        , $data_array);
                    }
                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , "User Created Successfully Failed to send SMS"
                                    , $data_array);
                }

                return $this->success(__LINE__ . ":" . __CLASS__
                                , "User Created Successfully"
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
     * verifySignin
     * @return type
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
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'User is NOT Exist(s)!');
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
                                , 'data' => ['key' => $user->api_token,'roleId'=>$user->role_id]]);
                }

                $user->setTransaction($dbTransaction);
                $user->status = 1;
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
                            , 'data' => ['key' => $user->api_token, 'roleId'=>$user->role_id]]);
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
     * loginAction
     * @return type
     * @throws Exception
     */
    public function loginAction() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Autheticate Request:" . json_encode($request->getJsonRawBody()));
        $user_name = isset($data->user_name) ? $data->user_name : null;
        $pass_word = isset($data->pass_word) ? $data->pass_word : null;
        $salt = isset($data->salt_token) ? $data->salt_token : null;
        $source = isset($data->source) ? $data->source : 'WEB';

        if (!$user_name || !$pass_word || !$salt || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        try {
            if (is_numeric($user_name)) {
                $user_name = $this->formatMobileNumber($user_name);
            }
            $auth = new Authenticate();
            $result = $auth
                    ->QueryUserUsingMobileOrEmail($user_name);
            if (!$result) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User Does NOT Exist!');
            }
            if ($result['status'] != 1) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User Account is NOT Active!');
            }
            if ($source == 'WEB') {
                $password = base64_decode($pass_word);
            } else {
                $password = $pass_word;
            }
            if (!$this->security->checkHash(md5($password), $result["password"])) {
                $auth->logLoginAttempt($result['user_id']);

                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'An Error Occured, Invalid Username or Password!');
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
                        , 'message' => 'Access Has Been Granted to your Account'
                        , 'data' => ['key' => $x['api_token'], 'role_id' => $x['role_id'], 'userId' => $x['user_id'], 'data' => $x]]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

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
        $this->payload['password'] = isset($data->password) ? $data->password : NULL;
        $this->payload['check'] = isset($data->check) ? $data->check : NULL;

        if ($this->payload['check'] == 1) {
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

                if (!$result) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'User Account is NOT Exist. Password reset NOT Possible!');
                }

                if ($result['status'] != 1) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'User Account is NOT Active. '
                                    . 'Contact System Administator for assistance.');
                }

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

                    $message = "Hello $File_Name,\nYour reset code is $verification_code Verify your account to continue.";

                    $params = [
                        "short_code" => $this->settings['mnoApps']['DefaultSenderIdOTP'],
                        "msisdn" => $result['msisdn'],
                        "message" => $message,
                        "profile_id" => $checkAccount->profile_id,
                        "created_by" => $result['user_id'],
                        "is_bulk" => true,
                        "link_id" => ""
                    ];
                    $message = new Messaging();
                    $queueMessageResponse = $message->LogOutbox($params);

                    if (!$queueMessageResponse) {
                        return $this->success(__LINE__ . ":" . __CLASS__
                                        , 'Reset Password Successful'
                                        , ['code' => 200, 'message' => 'Password Reset has been completed.User alert failed'
                                    , 'data' => ['verify_code' => $verification_code]], true);
                    }

                    return $this->success(__LINE__ . ":" . __CLASS__
                                    , 'Reset Password Successful and Verification Sent'
                                    , ['code' => 200, 'message' => 'Password Reset has been completed and verfication sent'
                                , 'data' => []]);
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

        if (!$this->payload['password'] || !$this->payload['user_name']) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if ((strlen($this->payload['password']) < $this->settings['Authentication']['recommendedPasswordXters']) &&
                (strlen($this->payload['password']) > 20)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Password Must contain '
                            . $this->settings['Authentication']['recommendedPasswordXters']
                            . ' characters.');
        }

        $transactionManager = new TransactionManager();
        $dbTransaction = $transactionManager->get();
        try {
            if (is_numeric($this->payload['user_name'])) {
                $this->payload['user_name'] = $this->formatMobileNumber($this->payload['user_name']);
            }

            $user = new Authenticate();
            $result = $user->QueryUserUsingMobileOrEmail($this->payload['user_name']);
            if (!$result) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                , 'User Account Does Not Exist. Password reset NOT Possible!');
            }

            if ($result['status'] != 1) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'User Account is NOT Active. '
                                . 'Contact System Administator for assistance. Status: ' . $result['status']);
            }

            $verification_code = rand(1000, 9999);
            if ($user->resetPassword($result['user_id']
                            , $this->payload['password'], $verification_code)) {

                $companyName = $result['first_name'];

                $x = $user->QueryUserUsingUserId($result['user_id']);
                if (!$x) {
                    return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                                    , 'An error occured while accessing user account.');
                }
                $dbTransaction->commit();

                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Reset Password Successful'
                                , ['code' => 200, 'message' => 'Password Reset has been completed'
                            , 'data' => ['key' => $x['api_token']]]);
            }

            return $this->unAuthorised(__LINE__ . ":" . __CLASS__
                            , 'Reset Password Failed');
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . " | "
                    . "Exception::" . $ex->getMessage());
            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error!');
        }
    }
    /**
     * viewMembers
     * @return type
     */
//    public function viewMembers() {
//        $start_time = $this->getMicrotime();
//        $request = new Request();
//        $data = $request->getJsonRawBody();
//
//        $this->infologger = $this->getLogFile('info');
//        $this->errorlogger = $this->getLogFile('error');
//        $this->infologger->info(__LINE__ . ":" . __CLASS__
//                . " | View Members Request:" . json_encode($request->getJsonRawBody()));
//
//        $token = isset($data->api_key) ? $data->api_key : null;
//        $start = isset($data->start) ? $data->start : null;
//        $stop = isset($data->end) ? $data->end : null;
//        $limit = isset($data->limit) ? $data->limit : null;
//        $offset = isset($data->page) ? $data->page : null;
//        $sort = isset($data->sort) ? $data->sort : null;
//        $source = isset($data->source) ? $data->source : null;
//        $eventID = isset($data->eventID) ? $data->eventID : null;
//        if (!$token || !$source || !$eventID) {
//            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
//        }
//        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
//            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
//        }
//        if (!$offset) {
//            $offset = 1;
//        }
//        if (!$limit) {
//            $limit = $this->settings['RecordsLimit'];
//        }
//        $order_arr = explode("|", $sort);
//        if (count($order_arr) > 1) {
//            $sort = "user.$order_arr[0]";
//            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
//        } else {
//            $sort = 'user.user_id';
//            $order = 'DESC';
//        }
//        try {
//            $auth = new Authenticate();
//            $auth_response = $auth->QuickTokenAuthenticate($token);
//            if (!$auth_response) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'Authentication Failure.');
//            }
//
//            $userClientMap = UserClientMap::findFirst(['user_id=:user_id:'
//                        , 'bind' => ['user_id' => $auth_response['user_id']]]);
//            if (!$userClientMap) {
//                return $this->unAuthorised(__LINE__ . ":" . __CLASS__
//                                , 'Authentication Failure.');
//            }
//
//            $whereArray = [
//                'clients.client_id' => $userClientMap->client_id,
//                'user_event_map.eventID' => $eventID];
//
//            $searchQuery = $this->whereQuery($whereArray, "");
//
//            if ($stop != null && $start != null) {
//                $searchQuery .= " AND date(user.created) BETWEEN '$start' AND '$stop' ";
//            }
//            if ($stop != null && $start == null) {
//                $searchQuery .= " AND date(user.created)<='$stop'";
//            }
//            if ($stop == null && $start != null) {
//                $searchQuery .= " AND date(user.created)>='$start'";
//            }
//
//            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);
//            $sql = "select profile.msisdn, profile_attribute.first_name,"
//                    . " profile_attribute.last_name, user.email, "
//                    . "user_role.role_name, clients.client_name, "
//                    . "user.created, (select count(DISTINCT user.user_id) from user join user_role on "
//                    . "user.role_id = user_role.user_role_id join "
//                    . "user_client_map on user.user_id  = user_client_map.user_id "
//                    . "join clients on clients.client_id  = user_client_map.client_id "
//                    . " join profile on profile.profile_id = user.profile_id "
//                    . "join profile_attribute on profile.profile_id  = profile_attribute.profile_id $searchQuery) as total from user join user_role on "
//                    . "user.role_id = user_role.user_role_id join "
//                    . "user_client_map on user.user_id  = user_client_map.user_id "
//                    . "join user_event_map on user_event_map.user_mapId = user_client_map.user_mapId  "
//                    . "join clients on clients.client_id  = user_client_map.client_id "
//                    . " join profile on profile.profile_id = user.profile_id "
//                    . "join profile_attribute on profile.profile_id  = profile_attribute.profile_id $searchQuery group by user.user_id  $sorting";
//            $result = $this->rawSelect($sql);
//            if (empty($result)) {
//                $stop_end = $this->getMicrotime() - $start_time;
//                return $this->success(__LINE__ . ":" . __CLASS__, 'No Ticket Types Found', [
//                            'code' => 404
//                            , 'message' => "Query returned no results ( $stop_end Seconds)", 'data' => []
//                            , 'record_count' => 0], true);
//            }
//            $stop_end = $this->getMicrotime() - $start_time;
//            return $this->success(__LINE__ . ":" . __CLASS__
//                            , 'Ok'
//                            , ['code' => 200
//                        , 'message' => "Successfully Queried Ticket Types results ($stop_end Seconds)"
//                        , 'record_count' => $result[0]['total'], 'data' => $result]);
//        } catch (Exception $ex) {
//            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . " | "
//                    . "Exception::" . $ex->getMessage());
//            return $this->serverError(__LINE__ . ":" . __CLASS__
//                            , 'Internal Server Error!');
//        }
//    }
//   
    
    
    public function viewMembers() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | View Members Request:" . json_encode($data));

        $token = isset($data->api_key) ? $data->api_key : null;
        $start = isset($data->start) ? $data->start : null;
        $stop = isset($data->end) ? $data->end : null;
        $limit = isset($data->limit) ? $data->limit : null;
        $offset = isset($data->page) ? $data->page : null;
        $sort = isset($data->sort) ? $data->sort : null;
        $source = isset($data->source) ? $data->source : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;

        if (!$token || !$source || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }

        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }

        $offset = $offset ? $offset : 1;
        $limit = $limit ? $limit : $this->settings['RecordsLimit'];

        $order_arr = explode("|", $sort);
        if (count($order_arr) > 1) {
            $sort = "user.$order_arr[0]";
            $order = isset($order_arr[1]) ? $order_arr[1] : 'DESC';
        } else {
            $sort = 'user.user_id';
            $order = 'DESC';
        }

        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failure.');
            }

            $userClientMap = UserClientMap::findFirst(['user_id=:user_id:', 'bind' => ['user_id' => $auth_response['user_id']]]);
            if (!$userClientMap) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failure.');
            }

            $whereArray = [
                'clients.client_id' => $userClientMap->client_id,
                'user_event_map.eventID' => $eventID
            ];

            $searchQuery = $this->whereQuery($whereArray, "");

            if ($stop != null && $start != null) {
                $searchQuery .= " AND date(user.created) BETWEEN '$start' AND '$stop' ";
            } elseif ($stop != null) {
                $searchQuery .= " AND date(user.created)<='$stop'";
            } elseif ($start != null) {
                $searchQuery .= " AND date(user.created)>='$start'";
            }

            $sorting = $this->tableQueryBuilder($sort, $order, $offset, $limit);

            $sql = "SELECT profile.msisdn, profile_attribute.first_name, profile_attribute.last_name, user.email, "
                    . "user_role.role_name, clients.client_name, user.created, "
                    . "(SELECT COUNT(DISTINCT user.user_id) FROM user "
                    . "JOIN user_role ON user.role_id = user_role.user_role_id "
                    . "JOIN user_client_map ON user.user_id = user_client_map.user_id "
                    . "JOIN user_event_map ON user_event_map.user_mapId = user_client_map.user_mapId " 
                    . "JOIN clients ON clients.client_id = user_client_map.client_id "
                    . "JOIN profile ON profile.profile_id = user.profile_id "
                    . "JOIN profile_attribute ON profile.profile_id = profile_attribute.profile_id $searchQuery) AS total "
                    . "FROM user "
                    . "JOIN user_role ON user.role_id = user_role.user_role_id "
                    . "JOIN user_client_map ON user.user_id = user_client_map.user_id "
                    . "JOIN user_event_map ON user_event_map.user_mapId = user_client_map.user_mapId "
                    . "JOIN clients ON clients.client_id = user_client_map.client_id "
                    . "JOIN profile ON profile.profile_id = user.profile_id "
                    . "JOIN profile_attribute ON profile.profile_id = profile_attribute.profile_id $searchQuery "
                    . "GROUP BY user.user_id $sorting";

            $result = $this->rawSelect($sql);

            $stop_end = $this->getMicrotime() - $start_time;

            if (empty($result)) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'No members found', [
                    'code' => 200, 
                    'message' => "Query returned no results ($stop_end Seconds)", 
                    'data' => [], 
                    'record_count' => 0
                ], true);
            }

            return $this->success(__LINE__ . ":" . __CLASS__, 'Ok', [
                'code' => 200, 
                'message' => "Successfully Queried results ($stop_end Seconds)", 
                'record_count' => $result[0]['total'], 
                'data' => $result
            ]);

        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . " | Exception::" . $ex->getMessage());
            return $this->serverError(__LINE__ . ":" . __CLASS__, 'Internal Server Error!');
        }
}
    
    
    /**
     * viewUsersAction
     * @return type
     */
    public function viewUsersAction() {
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

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "Select user.email,profile.msisdn,"
                    . "profile_attribute.first_name,profile_attribute.last_name,profile_attribute.surname,"
                    . "profile_attribute.network,user.last_login,"
                    . "user_role.role_name, user.created ";

            $countQuery = "select count(user.user_id) as totalUsers  ";

            $baseQuery = "from user join profile on user.profile_id =profile.profile_id "
                    . "join profile_attribute on profile_attribute.profile_id = profile.profile_id "
                    . "join user_role on user.role_id = user_role.user_role_id ";

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
                    $searchColumns = ['user.email', 'profile.msisdn',
                        'profile_attribute.first_name', 'profile_attribute.last_name', 'profile_attribute.surname'];

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


            $whereQuery = $whereQuery ? "WHERE $whereQuery AND  user.role_id in ($role) " : " WHERE user.role_id in ($role)";
            $countQuery = $countQuery . $baseQuery . $whereQuery;
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by user.user_id";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalUsers'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/api/user/v1/view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/api/user/v1/view?page=$next_url";
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
    /*
     * viewUserProfile
     * @return type
     */
    public function viewUserProfile() {
        $start_time = $this->getMicrotime();
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | ViewUserProfile:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $msisdn = isset($data->msisdn) ? $data->msisdn : null;
        $source = isset($data->source) ? $data->source : null;
        if (!$token || !$source) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if (!in_array($source, $this->settings['AuthenticatedChannels'])) {
            return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Request Sources Unverified!!');
        }
        $msisdn = $this->formatMobileNumber($msisdn);
        if (!$this->validateMobile($msisdn)) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__
                            , 'Validation Error'
                            , ['code' => 422, 'message' => 'Invalid Mobile Number']);
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
            }
            $stop = $this->getMicrotime() - $start_time;
            $data = $auth->QueryUserUsingMobile($msisdn);
            if (!$data) {
                return $this->success(__LINE__ . ":" . __CLASS__
                                , 'Failed'
                                , ['code' => 404
                            , 'message' => "Query returned no Results ( $stop Seconds)"
                            , 'data' => []], true);
            }
            return $this->successLarge(__LINE__ . ":" . __CLASS__
                            , 'Ok', [
                        'code' => 200,
                        'message' => "Query returned results ( $stop Seconds)",
                        'data' => $data]);
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return $this->serverError(__LINE__ . ":" . __CLASS__
                            , 'Internal Server Error.');
        }
    }
    /**
     * viewProfilesAction
     * @return type
     */
    public function viewProfilesAction() {
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

            if ($sortCriteria) {
                list($sortField, $orderBy) = explode('|', $sortCriteria);
            } else {
                $sortField = '';
                $orderBy = '';
            }

            $selectQuery = "Select profile.profile_id,profile.msisdn, "
                    . "profile_attribute.first_name,profile_attribute.surname,"
                    . "profile_attribute.last_name,profile_attribute.network, "
                    . "profile_attribute.frequency, profile.created ";

            $countQuery = "select count(profile.profile_id) as totalUsers  ";

            $baseQuery = "from profile join profile_attribute on "
                    . "profile.profile_id  = profile_attribute.profile_id ";

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
                    $searchColumns = ['profile.msisdn',
                        'profile_attribute.first_name', 'profile_attribute.last_name', 'profile_attribute.surname'];

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
                        $valueString = " DATE(profile.created) BETWEEN '$value[0]' AND '$value[1]'";
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
            $selectQuery = $selectQuery . $baseQuery . $whereQuery . " group by profile.profile_id";

            $queryBuilder = $this->tableQueryBuilder($sortField, $orderBy, $currentPage, $perPage, '');
            $selectQuery .= $queryBuilder;

            $count = $this->rawSelect($countQuery,[], 'db2');
            $matches = $this->rawSelect($selectQuery,[], 'db2');

            $data = new stdClass();
            $data->totalMatches = $count[0]['totalUsers'];
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
                    $prev_url = "http://35.187.164.231/ticket-bay-api/api/user/v1/profiles/view?page=$n";
                }

                $links = new stdClass();
                $pagination = new stdClass();
                $pagination->total = $totalItems;
                $pagination->per_page = 10;
                $pagination->current_page = $currentPage;
                $pagination->last_page = $lastPage;
                $pagination->from = $from;
                $pagination->to = $to;
                $pagination->next_page_url = "http://35.187.164.231/ticket-bay-api/api/user/v1/profiles/view?page=$next_url";
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
     * editUsers
     * @return type
     * @throws Exception
     */
    public function editUsers() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__
                . " | Create User Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $userID = isset($data->userID) ? $data->userID : NULL;
        $fname = isset($data->fname) ? $data->fname : NULL;
        $sname = isset($data->sname) ? $data->sname : NULL;
        $lname = isset($data->lname) ? $data->lname : NULL;
        $email = isset($data->email) ? $data->email : NULL;
        if (!$token) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        if ($email != null) {
            if (!$this->validateEmail($email)) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Invalid Client Email']);
            }
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

            if (in_array($auth_response['role_id'], [1, 2, 5]) && $userID != null) {
                $checkUser = User::findFirst(['user_id=:user_id:'
                            , 'bind' => ['user_id' => $userID]]);
                $profileID = $checkUser->profile_id;
            }

            if (!$checkUser) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__
                                , 'Validation Error'
                                , ['code' => 422, 'message' => 'Account does not exist']);
            }
            $transactionManager = new TransactionManager();
            $dbTrxn = $transactionManager->get();
            try {
                $checkUser->setTransaction($dbTrxn);
                if ($email != null) {
                    $checkUser->email = $email;
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
                    $dbTrxn->rollback("Update user failed " . json_encode($errors));
                }

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
    
    
    /**
     * removeEventStaff
     * @return type
     */
    public function removeEventStaff() {
        $request = new Request();
        $data = $request->getJsonRawBody();

        $this->infologger = $this->getLogFile('info');
        $this->errorlogger = $this->getLogFile('error');
        $this->infologger->info(__LINE__ . ":" . __CLASS__ . " | Remove Staff Request:" . json_encode($request->getJsonRawBody()));

        $token = isset($data->api_key) ? $data->api_key : null;
        $eventID = isset($data->eventID) ? $data->eventID : null;
        $msisdnNew = isset($data->msisdn) ? $data->msisdn : null;
        $source = isset($data->source) ? $data->source : null;

        if (!$token || !$source || !$msisdnNew || !$eventID) {
            return $this->unProcessable(__LINE__ . ":" . __CLASS__);
        }
        
        $msisdn = $this->formatMobileNumber($msisdnNew);

        try {
            $auth = new Authenticate();
            $auth_response = $auth->QuickTokenAuthenticate($token);
            if (!$auth_response) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Authentication Failure.');
            }
            if (!in_array($auth_response['userRole'], [1, 2, 6])) {
                return $this->unAuthorised(__LINE__ . ":" . __CLASS__, 'Not authorised to perform this action.');
            }

            $checkProfile = Profile::findFirst(["msisdn=:msisdn:", "bind" => ["msisdn" => $msisdn]]);
            if (!$checkProfile) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__, 'Validation Error', ['code' => 404, 'message' => 'Staff profile not found']);
            }

            $checkUser = User::findFirst(['profile_id=:profile_id:', 'bind' => ['profile_id' => $checkProfile->profile_id]]);
            if (!$checkUser) {
                return $this->unProcessable(__LINE__ . ":" . __CLASS__, 'Validation Error', ['code' => 404, 'message' => 'Staff user account not found']);
            }

            $checkUserClientMap = UserClientMap::findFirst(['user_id =:user_id:', 'bind' => ['user_id' => $checkUser->user_id]]);
            if (!$checkUserClientMap) {
                 return $this->unProcessable(__LINE__ . ":" . __CLASS__, 'Validation Error', ['code' => 404, 'message' => 'Staff is not mapped to any organization']);
            }

            $userEventMap = UserEventMap::findFirst(['user_mapId=:user_mapId: AND eventID =:eventID:', 
                'bind' => ['user_mapId' => $checkUserClientMap->user_mapId, 'eventID' => $eventID]]);
            
            if (!$userEventMap) {
                return $this->success(__LINE__ . ":" . __CLASS__, 'Success', ['code' => 200, 'message' => 'Staff is already not part of this event'], true);
            }

            if ($userEventMap->delete() === false) {
                return $this->serverError(__LINE__ . ":" . __CLASS__, 'Failed to remove staff member from event');
            }

            return $this->success(__LINE__ . ":" . __CLASS__, 'Success', ['code' => 200, 'message' => 'Staff member removed successfully from event']);

        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__ . " | Exceptions:" . $ex->getMessage());
            return $this->serverError(__LINE__ . ":" . __CLASS__, 'Internal Server Error.');
        }
    }

}
