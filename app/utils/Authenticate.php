<?php

/**
 * Description of Authenticate
 *
 * @author User
 */
use Phalcon\Mvc\Controller;
use ControllerBase as base;
use Carbon\Carbon;
use Phalcon\Mvc\Model\Transaction\Failed as TxFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class Authenticate extends Controller {

    /**
     * profileAuthenticate
     * @param type $profile_attributeId
     * @param type $pin
     * @return type
     * @throws Exception
     */
    public function QueryUserUsingMobileOrEmail($mobile) {
        $base = new base();
        try {
            $selectSql = "SELECT user.`user_id`,profile.profile_id,profile.msisdn, user.status"
                    . ",user.`email`,profile_attribute.`first_name`,"
                    . "profile_attribute.`surname`,user.`password`, profile_attribute.city"
                    . ", user.`last_login`,user.api_token,user.created FROM `user` "
                    . "join profile on user.profile_id = profile.profile_id join"
                    . " profile_attribute on profile_attribute.profile_id =  profile.profile_id "
                    . "WHERE profile.msisdn=:msisdn OR user.email=:email_address";

            $result = $base->rawSelect($selectSql, [':msisdn' => $mobile
                , ':email_address' => $mobile]);
            return isset($result[0]) ? $result[0] : false;
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | QueryUserUsingMobileOrEmail Exception::" . $ex->getMessage());
        }

        return false;
    }
    public function QueryUserUsingMobile($mobile) {
        $base = new base();
        try {
            $selectSql = "SELECT ifnull(clients.client_name,'') as organization, user.`user_id`,profile.profile_id,profile.msisdn, user.status,user.role_id"
                    . ",user.`email`,profile_attribute.`first_name`,profile_attribute.city, user.`last_login`,user.api_token,user.created FROM `user` "
                    . "join profile on user.profile_id = profile.profile_id join"
                    . " profile_attribute on profile_attribute.profile_id =  profile.profile_id "
                    . "left join user_client_map on user_client_map.user_id = user.user_id left "
                    . "join clients on clients.client_id = user_client_map.client_id    "
                    . "WHERE profile.msisdn=:msisdn OR user.email=:email_address";

            $result = $base->rawSelect($selectSql, [':msisdn' => $mobile
                , ':email_address' => $mobile]);
            return isset($result[0]) ? $result[0] : false;
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | QueryUserUsingMobileOrEmail Exception::" . $ex->getMessage());
        }

        return false;
    }

    /**
     * logFailedLoginAttempt
     * @param type $user_id
     * @param type $status
     * @return boolean
     */
    public function logLoginAttempt($user_id, $status = null) {
        $base = new base();
        $state = false;

        try {
            $query = "";
            if ($status == null) {
                $query = 'failed_attempts=failed_attempts+1'
                        . ',cumlative_failed_attempts=cumlative_failed_attempts+1'
                        . ',last_failed_attempt=NOW() ';
            }

            if ($status == 1) {
                $query = 'successful_attempts=successful_attempts+1'
                        . ',last_successful_date=NOW()';
                $update_sql = " UPDATE user set last_login= NOW()"
                        . " WHERE user_id=:user_id LIMIT 1";
                $insert_params = [
                    ":user_id" => $user_id,];

                $res = $base->rawInsert($update_sql, $insert_params);
            }

            if ($status == 2) {
                $query = 'successful_attempts=successful_attempts+1'
                        . ',last_successful_date=NOW()'
                        . ',failed_attempts=0';
                $update_sql = " UPDATE user set last_login=NOW()"
                        . " WHERE user_id=:user_id LIMIT 1";
                $insert_params = [
                    ":user_id" => $user_id,];

                $res = $base->rawInsert($update_sql, $insert_params);
            }

            $insert_sql = "UPDATE `user_login` SET $query "
                    . "WHERE user_id=:user_id LIMIT 1";
            
            $insert_params = [
                ":user_id" => $user_id,];

            $res = $base->rawInsert($insert_sql, $insert_params);
            if ($res > 0) {
                return true;
            }
        } catch (Exception $ex) {
            $base->getLogFile('error')->addError(__LINE__ . ":" . __CLASS__
                    . " | Exception >>> " . json_encode($ex->getCode()));
        }

        return $state;
    }

    /**
     *  check if user passes login rules
     * @param type $user_id
     * @return boolean|int
     */
    public function canLogin($user_id) {
        $base = new base();

        $sql = "SELECT user_login_id,failed_attempts,IFNULL(last_failed_attempt,0) "
                . "AS last_failed_attempt FROM user_login WHERE user_id = $user_id ";
        try {
            $login = $base->rawSelect($sql);
            if (!empty($login) || count($login) > 0) {
                $user_login_id = $login[0]['user_login_id'];
                $failed_attempts = $login[0]['failed_attempts'];
                $last_failed_attempt = isset($login[0]['last_failed_attempt']) ?
                        $login[0]['last_failed_attempt'] : 0;

                if ($failed_attempts < 5 || $last_failed_attempt == 0) {
                    $this->logLoginAttempt($user_id, 2);

                    return 1;
                } else {
                    $last_failed = Carbon
                            ::createFromFormat('Y-m-d H:i:s'
                                    , $last_failed_attempt);
                    $now = Carbon::now();
                    $minutes = $last_failed->diffInMinutes($now);
                    $interval = $base->settings['Authentication']['failedAttemptsInterval'];

                    if ($minutes > $interval) {
                        $this->logLoginAttempt($user_id, 2);

                        return 1;
                    } else {
                        return 1;
                    }
                }

                $this->logLoginAttempt($user_id, 1);

                return 1;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            $base->getLogFile('error')->addError(__LINE__ . ":" . __CLASS__
                    . " | Exception >>> " . json_encode($ex->getCode()));

            return false;
        }

        return 1;
    }

    /**
     * QueryUserUsingUserId
     * @param type $userId
     * @return array
     */
    public function QueryUserUsingUserId($userId) {
        $base = new base();

        try {
            $selectSql = "SELECT user.user_id,user.api_token"
                    . ",profile.msisdn,user.email,user.role_id,user_role.role_name"
                    . ",profile_attribute.first_name,profile_attribute.last_name,user_login.successful_attempts,user_login.failed_attempts"
                    . ",user_login.cumlative_failed_attempts,user_login.last_failed_attempt"
                    . ",user_login.last_failed_attempt,user.created FROM `user` "
                    . " join profile on user.profile_id = profile.profile_id JOIN "
                    . "profile_attribute on profile_attribute.profile_id = profile.profile_id JOIN user_login "
                    . "ON user.user_id=user_login.user_id JOIN user_role ON "
                    . "user.role_id=user_role.user_role_id "
                    . "WHERE user.user_id=:user_id";

            $result = $base->rawSelect($selectSql, [':user_id' => $userId]);
            return isset($result[0]) ? $result[0] : false;
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | QueryUserUsingUserId Exception::" . $ex->getMessage());
        }

        return false;
    }

    /**
     * QuickTokenAuthenticate
     * @param type $tokenKey
     * @return type
     * @throws Exception
     */
    public function QuickTokenAuthenticate($tokenKey) {
        $base = new base();
        try {
            $sql = "SELECT user.user_id,profile.msisdn,user.email,user.role_id"
                    . ",user_login.login_code,profile.profile_id,user_client_map.user_mapId,user_client_map.client_id"
                    . ",profile_attribute.first_name, profile_attribute.last_name,user_login.successful_attempts"
                    . ",user_login.failed_attempts,user_login.cumlative_failed_attempts"
                    . ",user_login.last_failed_attempt,user_login.last_failed_attempt"
                    . ",user.created,user.role_id as userRole, user_role.role_name "
                    . "FROM profile join `user` ON profile.profile_id = user.profile_id  "
                    . "join profile_attribute on profile_attribute.profile_id = profile.profile_id  "
                    . "JOIN user_login ON user.user_id=user_login.user_id "
                    . "JOIN user_role ON user.role_id = user_role.user_role_id LEFT JOIN  "
                    . "user_client_map on user_client_map.user_id = user.user_id "
                    . "WHERE user.status=:uStatus AND user.api_token=:apiToken limit 1";
            $params = [
                ':uStatus' => 1,
                ':apiToken' => trim($tokenKey),];

            $x = $base->rawSelect($sql, $params);

            return empty($x) ? false : $x[0];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * QueryVerificationCode
     * @param type $v_code
     * @param type $user_id
     */
    public function QueryVerificationCode($v_code, $user_id) {
        $base = new base();
        try {
            $selectSql = "SELECT user_login.`user_login_id` FROM "
                    . "`user_login` WHERE "
                    . "user_login.`login_code`=:verification_code "
                    . "AND user_login.`user_id`=:user_id";

            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | $v_code>>>>>>>>>>>$user_id");
            $result = $base->rawSelect($selectSql, [':verification_code' => md5($v_code)
                , ':user_id' => $user_id]);
            return isset($result[0]) ? $result[0] : false;
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | QueryVerificationCode Exception::" . $ex->getMessage());
        }

        return false;
    }

    /**
     * resetPassword
     * @param type $user_id
     * @param type $new_password
     * @param type $verification_code
     * @return boolean
     */
    public function resetPassword($user_id, $new_password, $v_code) {
        $base = new base();

        try {
            $password = $this->security->hash(md5($new_password));
            $token_api = md5("$password$v_code" . date('yyyyMMddHHmmss'));
            $verification_code = md5($v_code);

            $updateLoginSql = "UPDATE `user_login` "
                    . "SET `login_code`='$verification_code' "
                    . "WHERE `user_id`=$user_id LIMIT 1";
            if ($base->rawUpdate($updateLoginSql) > 0) {
                $updatePassSql = "UPDATE `user` "
                        . "SET `password`='$password', "
                        . "`api_token`='$token_api'"
                        . "WHERE `user_id`=$user_id LIMIT 1";
                if ($base->rawUpdate($updatePassSql) > 0) {
                    return TRUE;
                }
                return FALSE;
            }
            return false;
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | resetPassword Exception::" . $ex->getMessage());
            return false;
        }
    }
    
    /**
     * This function encrypts the data passed into it and returns the cipher data 
     * with the IV embedded within it.The initialization vector (IV) is appended 
     * to the cipher data with the use of two colons serve to delimited between the two.
     * @param type $ClearTextData
     * @return type
     */
    public function Encrypt($ClearTextData) {
        try {
            $EncryptionKey = base64_decode($this->settings['Authentication']['SecretKey']);
            $InitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
            $EncryptedText = openssl_encrypt($ClearTextData, 'AES-256-CBC', $EncryptionKey, 0, $InitializationVector);
            return base64_encode($EncryptedText . '::' . $InitializationVector);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * This function decrypts the cipher data (with the IV embedded within) 
      passed into it  and returns the clear text (unencrypted) data. The
      initialization vector (IV) is appended to the cipher data by the Encrypt.
      This function (see above). There are two colons that serve to delimited
      between the cipher data and the IV.
     * @param type $CipherData
     * @return type
     */
    public function Decrypt($CipherData) {
        try {
            $EncryptionKey = base64_decode($this->settings['Authentication']['SecretKey']);
            list($Encrypted_Data, $InitializationVector ) = array_pad(explode('::', base64_decode($CipherData), 2), 2, null);
            return openssl_decrypt($Encrypted_Data, 'AES-256-CBC', $EncryptionKey, 0, $InitializationVector);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * AlphaNumericIdGenerator
     * @param type $input
     * @return type
     */
    public function AlphaNumericIdGenerator($input) {
        $alpha_array = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L"
            , "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z"];
        $number_array = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"];
        $output = "";

        for ($i = 0; $i <= 5; $i++) {
            if ($i >= 4) {
                $divisor = pow(26, $i - 3) * pow(10, 3);
            } else {
                $divisor = pow(10, $i);
            }

            $pos = floor($input / $divisor);
            if ($i >= 3) {
                $digit = $pos % 26;
                $output .= $alpha_array[$digit];
            } else {
                $digit = $pos % 10;
                $output .= $number_array[$digit];
            }
        }

        return strrev($output);
    }
    
    /**
     * AutheticateRequest
     * @throws Exception
     */
    public static function AutheticateRequest($source, $token) {
        $base = new base();
        try {
            $statement = "SELECT * "
                    . "FROM `tokens` "
                    . "WHERE token=:token "
                    . "AND "
                    . "source=:source "
                    . "AND "
                    . "status=1";

            $params = [
                ":token" => $token,
                ':source' => $source];

            $result = $base->rawSelect($statement, $params);

            return isset($result[0]) ? $result[0] : false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
}
