<?php

/**
 * Description of Profiling
 *
 * @author User
 */
use ControllerBase as base;

class Profiling {

    /**
     * AddContactToUtilityList
     * @param type $contactDetails
     * @param type $listId
     * @return boolean
     */
    public static function AddContactToUtilityList($contactDetails, $listId = false) {
        $base = new base();

        try {
            if (!$listId) {
                $listId = self::CreateUtilityList($contactDetails['list_name']
                                , $contactDetails['profile_id']);

                if (!$listId) {
                    return ['code' => 500, 'message' => 'Failed to create Utility Contact List'];
                }
            }

            $insert_sql = "INSERT INTO `contact_list`(`list_name`, `status`"
                    . ", `created`) VALUES (:list_name,:status,NOW())";

            $insert_params = [
                ':list_name' => $listName,
                ':status' => 1,];

            return $base->rawInsert($insert_sql, $insert_params);
        } catch (Exception $ex) {
            $base->getLogFile("error")->addEmergency(_LINE_ . ":" . _CLASS_
                    . " | Exception:" . $ex->getMessage());

            return false;
        }
    }

    /**
     * CreateUtilityList
     * @param type $listName
     * @return boolean
     */
    public static function CreateUtilityList($listName, $profileId) {
        $base = new base();

        try {
            $statement = "SELECT id FROM `contact_list` WHERE list_name=:list_name "
                    . "AND profile_id=:profile_id";

            $statement_param = [
                ':profile_id' => $profileId,
                ':list_name' => $listName,];
            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return $result[0]['id'];
            }

            $insert_sql = "INSERT INTO `contact_list`(`list_name`, `profile_id`,`status`"
                    . ", `created`) VALUES (:list_name,:profile_id,:status,NOW())";

            $insert_params = [
                ':profile_id' => $profileId,
                ':list_name' => $listName,
                ':status' => 1,];

            return $base->rawInsert($insert_sql, $insert_params);
        } catch (Exception $ex) {
            $base->getLogFile("error")->addEmergency(_LINE_ . ":" . _CLASS_
                    . " | Exception:" . $ex->getMessage());

            return false;
        }
    }

    /**
     * saveProfileAccounts
     * @param type $service_id
     * @param type $profile_id
     * @param type $attributes
     * @return type
     */
    public static function saveProfileAccounts($attributes) {
        $base = new base();

        try {
            $statement = "SELECT id,service_id,accounts,account_details "
                    . "FROM `profile_account` WHERE profile_id=:profile_id "
                    . "AND service_id=:service_id";

            $statement_param = [
                ':service_id' => $attributes['service_id'],
                ':profile_id' => $attributes['profile_id']];
            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return $result[0];
            }

            $insert_sql = "INSERT INTO `profile_account`(`profile_id`, `service_id`"
                    . ", `accounts`, `account_details`, `created`) "
                    . "VALUES (:profile_id,:service_id,:accounts,:account_details,NOW())";

            $insert_params = [
                ':profile_id' => $attributes['profile_id'],
                ':service_id' => $attributes['service_id'],
                ':accounts' => $attributes['accounts'],
                ':account_details' => $attributes['account_details'],];

            return $base->rawInsert($insert_sql, $insert_params);
        } catch (Exception $ex) {
            $base->getLogFile("error")->addEmergency(_LINE_ . ":" . _CLASS_
                    . " | Exception:" . $ex->getMessage());

            return false;
        }
    }

    /**
     * 
     * @param type $attributes
     * @return type
     */
    public static function profileDevices($attributes) {
        $base = new base();

        try {
            $insert_sql = "INSERT INTO `profile_device`(`profile_id`, `device_used`, `browser`"
                    . ", `language`, `version`, `created`) VALUES (:profile_id"
                    . ",:device_used,:browser,:language,:version,NOW())";

            $insert_params = [
                ':profile_id' => $attributes['profile_id'],
                ':device_used' => $attributes['device_used'],
                ':browser' => $attributes['browser'],
                ':language' => $attributes['language'],
                ':version' => $attributes['version'],];

            return $base->rawInsert($insert_sql, $insert_params);
        } catch (Exception $ex) {
            $base->getLogFile("error")->addEmergency(_LINE_ . ":" . _CLASS_
                    . " | Exception:" . $ex->getMessage());
        }
    }

    /**
     * QueryMobile
     * @param type $msisdn
     */
    public static function QueryMobile($profile_id) {
        $base = new base();
        try {
            $statement = "SELECT msisdn FROM `profile` WHERE profile_id=:profile_id";

            $statement_param = [
                ':profile_id' => $profile_id];
            $result = $base->rawSelect($statement, $statement_param);

            if ($result) {
                return $result[0]['msisdn'];
            }

            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * QueryProfileAttribution
     * @param type $profile_id
     */
    public static function QueryProfileAttribution($profile_id) {
        $base = new base();
        try {
            $statement = "SELECT * FROM `profile_attribute` WHERE profile_id=:profile_id";

            $statement_param = [
                ':profile_id' => $profile_id];
            $result = $base->rawSelect($statement, $statement_param);

            if ($result) {
                return $result[0];
            }

            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Profile
     * @param type $msisdn
     */
    public static function Profile($msisdn) {
        $base = new base();
        try {
            $statement = "SELECT profile_id FROM `profile` WHERE msisdn=:msisdn";
            if(strlen($msisdn) > 12){
                $statement = "SELECT profile_id FROM `profile` WHERE msisdn_hash=:msisdn";
            }
            $statement_param = [
                ':msisdn' => $msisdn];
            $result = $base->rawSelect($statement, $statement_param);

            if ($result) {
                
                return $result[0]['profile_id'];
            }
            if(strlen($msisdn) > 12) {
                return false;
            }
            $insert_profile_init = "INSERT INTO `profile`( `msisdn`, `created`) "
                    . "VALUES (:msisdn,NOW())";

            $insert_profile_init_params = [
                ':msisdn' => $msisdn,];

            $profileID = $base->rawInsert($insert_profile_init, $insert_profile_init_params);

            $insert_profile_bal = "INSERT INTO profile_balance (profile_id,"
                    . "created_at) VALUES (:profile_id,now())";
            $insert_profile_bal_params = [
                ':profile_id' => $profileID,];

            $base->rawInsert($insert_profile_bal, $insert_profile_bal_params);

            return $profileID;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public static function updateBalance($params) {
        $base = new base();
        try {
            $statement = "SELECT profile_id FROM `profile_balance` WHERE profile_id=:profile_id";

            $statement_param = [
                ':profile_id' => $params['profile_id']];

            $result = $base->rawSelect($statement, $statement_param);

            if ($result) {
                $statement_update = "UPDATE profile_balance SET profile_balance=profile_balance+:balance"
                        . " WHERE profile_id=:profile_id LIMIT 1";
                $statement_update_param = [
                    ':balance' => $params['balance'],
                    ':profile_id' => $params['profile_id']];

                $base->rawUpdateWithParams($statement_update, $statement_update_param);
                return true;
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
    public static function updateSuspense($params) {
        $base = new base();
        try {
            $statement = "SELECT profile_id FROM `profile_balance` WHERE profile_id=:profile_id";

            $statement_param = [
                ':profile_id' => $params['profile_id']];

            $result = $base->rawSelect($statement, $statement_param);

            if ($result) {
                $statement_update = "UPDATE profile_balance SET suspense_balance=suspense_balance+:balance"
                        . " WHERE profile_id=:profile_id LIMIT 1";
                $statement_update_param = [
                    ':balance' => $params['balance'],
                    ':profile_id' => $params['profile_id']];

                $base->rawUpdateWithParams($statement_update, $statement_update_param);
                return true;
            }
            return false;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @param type $var
     * @return type
     */
    public static function validMySQL($var) {
        $var = stripslashes($var);
        $var = htmlentities($var);
        $var = strip_tags($var);
        return $var;
    }

    /**
     * ProfileAttribution
     * @param type $attributes
     */
    public static function ProfileAttribution($attributes) {
        $base = new base();

        try {
            $statement = "SELECT id,first_name,surname,last_name "
                    . "FROM `profile_attribute` "
                    . "WHERE profile_id=:profile_id";

            $statement_param = [
                ':profile_id' => $attributes['profile_id']];
            $result = $base->rawSelect($statement, $statement_param);

            if ($result) {
                $p = [':id' => $result[0]['id']];

                $statement_update = "UPDATE profile_attribute SET frequency=frequency+1"
                        . ",last_dial_date=NOW()";
                if (($result[0]['first_name'] == "") || ($result[0]['first_name'] == null)) {
                    if (strlen($attributes['first_name']) > 1) {
                        $statement_update .= ",first_name=IFNULL(first_name,:first_name)";

                        $p[':first_name'] = self::validMySQL($attributes['first_name']);
                    }
                }

                if (($result[0]['last_name'] == "") || ($result[0]['last_name'] == null)) {
                    if (strlen($attributes['last_name']) > 1) {
                        $statement_update .= ",last_name=IFNULL(last_name,:last_name)";
                        $p[':last_name'] = self::validMySQL($attributes['last_name']);
                    }

                    if (($result[0]['surname'] == "") || ($result[0]['surname'] == null)) {
                        if (strlen($attributes['surname']) > 1) {
                            $statement_update .= ",surname=IFNULL(surname,:surname)";
                            $p[':surname'] = self::validMySQL($attributes['surname']);
                        }
                    }
                }

                $statement_update .= " WHERE id=:id LIMIT 1";
                $base->rawUpdateWithParams($statement_update, $p);

                return $result[0]['id'];
            }

            $insert_profile_init = "INSERT INTO `profile_attribute`( `profile_id`"
                    . ", `first_name`, `surname`, `last_name`, `network`"
                    . ", `pin`,`status`, created_by,`last_dial_date`, `created`) "
                    . "VALUES (:profile_id,:first_name,:surname,:last_name,:network"
                    . ",:pin,1,:created_by,NOW(),NOW())";

            $insert_profile_init_params = [
                ':profile_id' => $attributes['profile_id'],
                ':first_name' => $attributes['first_name'],
                ':surname' => $attributes['surname'],
                ':last_name' => $attributes['last_name'],
                ':network' => $attributes['network'],
                ':created_by' => $attributes['source'],
                ':pin' => md5(123456),
            ];

            return $base->rawInsert($insert_profile_init, $insert_profile_init_params);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 
     * @param type $tokenKey
     * @return type
     * @throws Exception
     */
    public static function QuickAuthenticate($tokenKey) {
        $base = new base();
        try {
            $sql = "select profile_attribute.profile_id,profile_attribute.first_name,"
                    . "profile_attribute.surname,profile_attribute.last_name,"
                    . "profile_attribute.network,profile.msisdn from profile_attribute "
                    . "join profile on profile_attribute.profile_id = profile.profile_id "
                    . " WHERE profile_attribute.status = 1 "
                    . " AND profile_attribute.token='$tokenKey'";

            $x = $base->rawSelect($sql);

            return empty($x) ? false : $x[0];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * QueryProfileMobile
     * @param type $mobile
     * @return type
     * @throws Exception
     */
    public static function QueryProfileMobile($mobile) {
        $base = new base();
        try {
            $sql = "select profile_attribute.profile_id,profile_attribute.first_name,"
                    . "profile_attribute.status,profile_attribute.token,"
                    . "profile_attribute.surname,profile_attribute.last_name,profile_attribute.pin,user.email,"
                    . "profile_attribute.network,profile.msisdn from profile_attribute "
                    . "join profile on profile_attribute.profile_id = profile.profile_id "
                    . "left join user on user.profile_id = profile.profile_id "
                    . " WHERE profile.msisdn='$mobile'";

            $x = $base->rawSelect($sql);

            return empty($x) ? false : $x[0];
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
     /**
     * QueryProfileProfileId
     * @param type $profileId
     * @return type
     * @throws Exception
     */
    public static function QueryProfileProfileId($profileId) {
        $base = new base();
        try {
            $sql = "select profile_attribute.profile_id,profile_attribute.first_name,"
                    . "profile_attribute.status,profile_attribute.token,"
                    . "profile_attribute.surname,profile_attribute.last_name,profile_attribute.pin,user.email,"
                    . "profile_attribute.network,profile.msisdn from profile_attribute "
                    . "join profile on profile_attribute.profile_id = profile.profile_id "
                    . "left join user on user.profile_id = profile.profile_id "
                    . " WHERE profile.profile_id='$profileId'";

            $x = $base->rawSelect($sql);

            return empty($x) ? false : $x[0];
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * canLogin
     * @param type $profile_if
     * @return boolean|int
     */
    public static function canLogin($profile_if) {
        $base = new base();

        $sql = "SELECT failed_attempts,IFNULL(last_failed_attempt,0) "
                . "AS last_failed_attempt FROM profile_login WHERE profile_id = $profile_if ";
        try {
            $login = $base->rawSelect($sql);
            if (!empty($login) || count($login) > 0) {
                $failed_attempts = $login[0]['failed_attempts'];
                $last_failed_attempt = isset($login[0]['last_failed_attempt']) ?
                        $login[0]['last_failed_attempt'] : 0;

                if ($failed_attempts < 10 || $last_failed_attempt == 0) {
                    $base->logLoginAttempt($profile_if, 2);

                    return 1;
                } else {

                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $ex) {
            $base->getLogFile('error')->addError(_LINE_ . ":" . _CLASS_
                    . " | Exception >>> " . json_encode($ex->getCode()));

            return false;
        }

        return 1;
    }

}
