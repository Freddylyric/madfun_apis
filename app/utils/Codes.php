<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of Codes
 *
 * @author kevinkmwando
 */
use ControllerBase as base;

class Codes {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }
    /**
     * 
     * @param type $code
     * @return string|int
     * @throws Exception
     */
    public static function queryCode($code) {

        $base = new base();
        try {
            $statement = "SELECT * FROM code_live WHERE code = :code";
            $statement_param = [
                ":code" => strtoupper($code)];
            $result = $base->rawSelect($statement, $statement_param);

            if (!$result) {
                $response = [
                    'response_code' => 404,
                    'message' => 'Invalid Code',
                    'data' => null
                ];
                return $response;
            }
            if ($result[0]['status'] != 1) {
                $response = [
                    'response_code' => 402,
                    'message' => 'Inactive Code',
                    'data' => null
                ];
                return $response;
            }
            $statementCode = "SELECT * FROM redeemed_code WHERE "
                    . " code_id= :code_id";
            $statement_param_code = [
                ":code_id" => $result[0]['id']];
            $resultCode = $base->rawSelect($statementCode, $statement_param_code);
            if ($resultCode) {
                $response = [
                    'response_code' => 402,
                    'message' => 'Invalid Code',
                    'data' => $resultCode[0]
                ];
                return $response;
            }

            $response = [
                'response_code' => 200,
                'message' => 'Valid Code',
                'data' => $result[0]
            ];
            return $response;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    /**
     * 
     * @param type $params
     * @return type
     * @throws Exception
     */
    public static function redemeedCode($params) {
        $base = new base();
        try {
            $sqlInsert = "INSERT INTO redeemed_code "
                    . "(code_id,transaction_id,event_ticket_id,created) VALUES"
                    . " (:code_id,:transaction_id,:event_ticket_id,now())";
            $selectParams = [
                ':code_id' => $params['code_id'],
                ':transaction_id' => $params['transaction_id'],
                ':event_ticket_id' => $params['event_ticket_id']
            ];
            return $base->rawInsert($sqlInsert, $selectParams);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

}
