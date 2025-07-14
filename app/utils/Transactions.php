<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TransactionsInitiated
 *
 * @author User
 */
use ControllerBase as base;

class Transactions {

    /**
     * UpdateRetries
     * @param type $transaction_id
     * @param type $receipt_number
     * @param type $response_code
     * @param type $narration
     * @param type $response_description
     * @param type $callback_id
     * @param type $params
     * @return boolean
     * @throws Exception
     */
    public static function UpdateRetries($transaction_id, $receipt_number, $response_code, $narration, $response_description, $callback_id, $params) {
        $base = new base();
        try {
            $update_sql = "UPDATE `transaction_callback` SET `retries`=`retries`+1"
                    . ",`response_code`='$response_code'"
                    . ",`response_description`='$response_description'"
                    . ",`narration`='$narration'"
                    . ",`receipt_number`='$receipt_number'"
                    . " WHERE `id`=$callback_id LIMIT 1";
            if (!$base->rawUpdate($update_sql)) {
                return false;
            }

            $insert_sql = 'INSERT INTO `transaction_retries`( `transaction_id`'
                    . ', previous_receipt_number,`previous_narration`, `previous_response_code`'
                    . ',`callback_id`, `created`) '
                    . 'VALUES (:transaction_id,:previous_receipt_number,:previous_narration'
                    . ',:previous_response_code,:callback_id,NOW());';

            $insert_params = [
                ':transaction_id' => $transaction_id,
                ':callback_id' => $callback_id,
                ':previous_receipt_number' => $params['previous_receipt_number'],
                ':previous_response_code' => $params['previous_response_code'],
                ':previous_narration' => $params['previous_narration'],
            ];

            return $base->rawInsert($insert_sql, $insert_params);
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__ . " | Execption::" . $ex->getCode()
                    . " | " . $ex->getMessage()
                    . " | " . $ex->getTraceAsString());
            throw $ex;
        }
    }
    public static function addLPO($params){
        $base = new base();
        try{
            $statement = "SELECT transaction_id "
                    . "FROM `lpo` "
                    . "WHERE transaction_id=:transaction_id "
                    . "AND "
                    . "lpo_number=:lpo_number "
                    . "AND "
                    . "company=:company";

            $statement_param = [
                ':transaction_id' => $params['transaction_id'],
                ":lpo_number" => $params['lpo_number'],
                ':company' => $params['company']];
            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return false;
            }
             $insert_sql = 'INSERT INTO `lpo`( `transaction_id`'
                    . ', lpo_number,`company`, `amount`, `created`) '
                    . 'VALUES (:transaction_id,:lpo_number,:company'
                    . ',:amount,NOW());';

            $insert_params = [
                ':transaction_id' => $params['transaction_id'],
                ':lpo_number' => $params['lpo_number'],
                ':company' => $params['company'],
                ':amount' => $params['amount'],
            ];

            return $base->rawInsert($insert_sql, $insert_params);
            
        } catch (Exception $ex) {
             $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__ . " | Execption::" . $ex->getCode()
                    . " | " . $ex->getMessage()
                    . " | " . $ex->getTraceAsString());
            throw $ex;
        }
        
    }

    /**
     * Initiate
     * @throws Exception
     */
    public static function Initiate($params) {
        $base = new base();
        try {
            $statement = "SELECT transaction_id "
                    . "FROM `transaction_initiated` "
                    . "WHERE service_id=:service_id "
                    . "AND "
                    . "source=:source "
                    . "AND "
                    . "reference_id=:reference_id";

            $statement_param = [
                ':service_id' => $params['service_id'],
                ":reference_id" => $params['reference_id'],
                ':source' => $params['source']];
            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return false;
            }

            $insert_trx_init = "INSERT INTO `transaction_initiated`( `profile_id`"
                    . ", `service_id`, `reference_id`, `source`, `description`"
                    . ", `extra_data`, `created`) VALUES (:profile_id,:service_id"
                    . ",:reference_id,:source,:description,:extra_data,NOW())";

            $insert_trx_init_params = [
                ':profile_id' => $params['profile_id'],
                ':service_id' => $params['service_id'],
                ':reference_id' => $params['reference_id'],
                ':source' => $params['source'],
                ':description' => $params['description'],
                ':extra_data' => $params['extra_data'],];

            return $base->rawInsert($insert_trx_init, $insert_trx_init_params);
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
    public static function dpoInititate($params){
        $base = new base();
        try{
             $statement = "SELECT transaction_id "
                    . "FROM `dpo_transaction_initiated` "
                    . "WHERE transaction_id=:transaction_id "
                    . "AND "
                    . "TransactionToken=:TransactionToken";

            $statement_param = [
                ':transaction_id' => $params['transaction_id'],
                ':TransactionToken' => $params['TransactionToken']];
            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return false;
            }
            $insert_trx_init = "INSERT INTO `dpo_transaction_initiated`( `transaction_id`"
                    . ", `TransactionToken`, `created`) VALUES (:transaction_id,"
                    . ":TransactionToken,NOW())";

            $insert_trx_init_params = [
                ':transaction_id' => $params['transaction_id'],
                ':TransactionToken' => $params['TransactionToken']];

            return $base->rawInsert($insert_trx_init, $insert_trx_init_params);
        } catch (Exception $ex) {

        }
    }

    public static function UpdateInitiate($params) {
        $base = new base();
        try {
             $statement = "SELECT transaction_id "
                    . "FROM `transaction_initiated` "
                    . "WHERE transaction_id=:transaction_id ";

            $statement_param = [
                ':transaction_id' => $params['transaction_id']];
            $result = $base->rawSelect($statement, $statement_param);
            if ($result) {
                return false;
            }
            $update_sql = "UPDATE `transaction_initiated` SET `reference_id`=:reference_id"
                    . " WHERE `transaction_id`=:transaction_id LIMIT 1";
            if (!$base->rawUpdateWithParams($update_sql, [ ':transaction_id' 
                => $params['transaction_id'],':reference_id'=>$params['reference_id'] ])) {
                return false;
            }
            return true;
            
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * CreateTransaction
     * @throws Exception
     */
    public static function CreateTransaction($params) {
        $base = new base();
        try {
            $insert_trx_init = "INSERT INTO `transaction`( `profile_id`"
                    . ", `service_id`, `reference_id`,`amount`, `source`, extra_data,`description`"
                    . ",`created`) VALUES (:profile_id,:service_id,:reference_id"
                    . ",:amount,:source,:extra_data,:description,NOW())";

            $insert_trx_init_params = [
                ':amount' => $params['amount'],
                ':profile_id' => $params['profile_id'],
                ':service_id' => $params['service_id'],
                ':reference_id' => $params['reference_id'],
                ':source' => $params['source'],
                ':description' => $params['description'],
                ':extra_data' => $params['extra_data'],
            ];

            return $base->rawInsert($insert_trx_init, $insert_trx_init_params);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * CreateTransactionCallback
     * @throws Exception
     */
    public static function CreateTransactionCallback($params) {
        $base = new base();
        try {
            $insert_trx_init = "INSERT INTO `transaction_callback`( `transaction_id`"
                    . ", `response_code`, `response_description`, `narration`, `receipt_number`"
                    . ",purchase_type, extra_data,`created`) VALUES (:transaction_id,:response_code"
                    . ",:response_description,:narration,:receipt_number,:purchase_type,:extra_data,NOW())";

            $insert_trx_init_params = [
                ':purchase_type' => $params['purchase_type'],
                ':transaction_id' => $params['transaction_id'],
                ':response_code' => $params['response_code'],
                ':response_description' => $params['response_description'],
                ':narration' => $params['narration'],
                ':extra_data' => $params['extra_data'],
                ':receipt_number' => $params['receipt_number']
            ];

            return $base->rawInsert($insert_trx_init, $insert_trx_init_params);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * CreateTransactionCallback
     * @throws Exception
     */
    public static function UpdateTransactionCallback($response_code, $response_description, $narration, $receipt_number, $callback_id) {
        $base = new base();
        try {
            $update_sql = "UPDATE `transaction_callback` SET `response_code`='$response_code'"
                    . ",`response_description`='$response_description'"
                    . ",`narration`='$narration'"
                    . ",`receipt_number`='$receipt_number'"
                    . " WHERE `id`=$callback_id LIMIT 1";
            if (!$base->rawUpdate($update_sql)) {
                return false;
            }

            return true;
        } catch (Exception $ex) {
            throw $ex;
        }
    }

}
