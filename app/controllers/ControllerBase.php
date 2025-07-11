<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use \Firebase\JWT\JWT as jwt;
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Phalcon\Mvc\Dispatcher as MvcDispatcher;

class ControllerBase extends Controller {
    
    /**
     * CalculateTAT
     * @param type $start
     * @return type
     */
    public function CalculateTAT($start) {
        $tat = $this->getMicrotime() - $start;

        return round($tat, 5);
    }
    
     function generateUniqueReference($prefix = 'QOR') {
         $refernceNumber =  $this->ReferenceNumber();
         $random = mt_rand(1000, 9999);
         $reference = $prefix ."-". $refernceNumber ."-". $random."-".$this->num2alpha($random);
    
         return $reference;

         
    }
    
    /**
     * ReferenceNumber
     * @param type $uniqueId
     * @return type
     */
    function ReferenceNumber($uniqueId = false) {
        $referenceId = "";

        $year = [
            '2022' => 'Z', '2023' => 'Y', '2024' => 'X', '2025' => 'W',
            '2026' => 'V', '2027' => 'U', '2028' => 'T', '2029' => 'S',
            '2030' => 'R', '2031' => 'Q', '2032' => 'O', '2033' => 'N'];
        $referenceId .= $year[$this->now('Y')];

        $month = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        $referenceId .= $month[date('m') - 1];

        $d = (int) $this->now('d');
        if (($d >= 1) || ($d <= 10)) {
            $referenceId .= $d;
        } else {
            $referenceId .= strtoupper($this->num2alpha($d));
        }

        $referenceId .= strtoupper($this->num2alpha(((int) $this->now('H'))));
        $referenceId .= strtoupper($this->num2alpha(((int) $this->now('s'))));

        $rand = (strlen($referenceId) < 10) ? (10 - strlen($referenceId)) : 3;
        $referenceId .= substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstvwxyz', 36)), 0, $rand);

        return strtoupper($referenceId);
    }
    
    /**
     * Converts an integer into the alphabet base (A-Z).
     *
     * @param int $n This is the number to convert.
     * @return string The converted number.
     * @author Theriault
     * 
     */
    function num2alpha($n) {
        $r = '';
        for ($i = 1; $n >= 0 && $i < 10; $i++) {
            $r = chr(0x41 + ($n % pow(26, $i) / pow(26, $i - 1))) . $r;
            $n -= pow(26, $i);
        }
        return $r;
    }
    
    function checkForMySQLKeywords($requestData) {
        // Define an array of MySQL-related keywords or patterns to search for.
        $mysqlKeywords = array(
            "SELECT", "INSERT", "UPDATE", "DELETE", "FROM",
            "UNION", "JOIN", "DROP", "ALTER", "CREATE", "DATABASE",
            "mysql_query", "mysqli_query", "PDO::query"
                // Add more keywords or patterns as needed.
        );
        if (is_array($requestData)) {
            $requestData = array_map('strtolower', $requestData);
        } else {
            if (is_object($requestData)) {
                $requestData = json_encode($requestData);
            }
            $requestData = strtolower($requestData);
        }
        
        foreach ($mysqlKeywords as $keyword) {
            if (strpos($requestData, strtolower($keyword)) !== false) {
                return true; // Found a MySQL-related keyword.
            }
        }

        return false; // No MySQL-related keywords found.
    }

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
     * AlphaNumericIdGenerator
     * @param type $input
     * @return type
     */
    public function AlphaNumericIdGenerator($input) {
        $alpha_array = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L"
            , "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z"];
        $number_array = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"];
        $output = "";

        for ($i = 0; $i <= 8; $i++) {
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
     * clean variable string
     * 
     * @param type $text
     * @return type
     */
    public function cleanString($text) {
        $utf8 = array(
            '/[áàâãªä]/u' => 'a',
            '/[ÁÀÂÃÄ]/u' => 'A',
            '/[ÍÌÎÏ]/u' => 'I',
            '/[íìîï]/u' => 'i',
            '/[éèêë]/u' => 'e',
            '/[ÉÈÊË]/u' => 'E',
            '/[óòôõºö]/u' => 'o',
            '/[ÓÒÔÕÖ]/u' => 'O',
            '/[úùûü]/u' => 'u',
            '/[ÚÙÛÜ]/u' => 'U',
            '/ç/' => 'c',
            '/Ç/' => 'C',
            '/ñ/' => 'n',
            '/Ñ/' => 'N',
            '/–/' => '-', // UTF-8 hyphen to "normal" hyphen
            '/[’‘‹›‚]/u' => ' ', // Literally a single quote
            '/[“”«»„]/u' => ' ', // Double quote
            '/ /' => ' ', // nonbreaking space (equiv. to 0x160)
        );
        $string = preg_replace(array_keys($utf8), array_values($utf8), $text);
        $string = stripslashes($string);
        // $string = htmlspecialchars($string);

        return preg_replace('/[[:^print:]]/', '', trim($string));
    }

    /**
     * CompareDateGetMinuteDiffAndNow
     * @param type $from
     * @param type $now
     * @return type
     */
    public function CompareDateGetMinuteDiffAndNow($from, $now = null) {
        if (!$now) {
            $now = $this->now();
        }

        return (int) number_format(((new \DateTime($from))->getTimestamp() - (new \DateTime($now))->getTimestamp()) / 60);
    }

    /**
     * ValidatePassword
     * @param type $password
     * @return boolean|string
     */
    public function ValidatePassword($password) {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
            return 'Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character.';
        } else {
            return true;
        }
    }

    /**
     * ValidateNumber
     * @param type $number
     * @return boolean
     */
    public function ValidateNumber($number) {
        if (!is_numeric($number)) {
            return false;
        }

        if ($number < $this->settings['MinAmount']) {
            return false;
        }

        if ($number > $this->settings['MaxAmount']) {
            return false;
        }

        return true;
    }

    /**
     * Generates a random integer between 48 and 122.
     * <p>
     * @return int Non-cryptographically generated random number.
     */
    function findRandom() {
        $mRandom = rand(48, 122);
        return $mRandom;
    }

    /**
     * Checks if $random equals ranges 48:57, 56:90, or 97:122.
     * <p>
     * This function is being used to filter $random so that when used in:
     * '&#' . $random . ';' it will generate the ASCII characters for ranges
     * 0:8, a-z (lowercase), or A-Z (uppercase).
     * <p>
     * @param int $mRandom Non-cryptographically generated random number.
     * @return int 0 if not within range, else $random is returned. 
     */
    function isRandomInRange() {
        $mRandom = $this->findRandom();
        if (($mRandom >= 58 && $mRandom <= 64) ||
                (($mRandom >= 91 && $mRandom <= 96))) {
            return 0;
        } else {
            return $mRandom;
        }
    }

    /**
     * GenerateApiKey
     * @return type
     */
    public function GenerateApiKey($i, $j = 31) {
        $output = "";
        for ($loop = $i; $loop <= $j; $loop++) {
            for ($isRandomInRange = 0; $isRandomInRange === 0;) {
                $isRandomInRange = $this->isRandomInRange($this->findRandom());
            }
            $output .= html_entity_decode('&#' . $isRandomInRange . ';');
        }

        return $output;
    }

    /**
     * Creates NewAuthToken
     * @param type $payload
     * @return type
     */
    public function createNewAuthToken($payload, $token = null) {
        if ($token == null) {
            $token = ['token' => "55abe029fdebae5e1d417e2ffb2a003a0cd8b54763051cef08bc55abe029"];
        }

        $secretKey = base64_encode($token);
        $jwtToken = jwt::encode($payload, $secretKey, 'HS512');

        return $jwtToken;
    }

    /**
     * messageOnly
     * @param type $function
     * @param type $message
     * @return Response
     */
    public function messageOnly($function, $message) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(200, "SUCCESS - MESSAGE ONLY");

        $res = json_encode($message);
        $response->setContent($res);

        $this->getLogFile('debug')->addWarning("$function - SUCCESS:$res");

        return $response;
    }

    /**
     * formats validation error response messages 
     * @param type $function
     * @return Response
     */
    public function forbiddenAcess($function, $message, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(403, "FORBIDDEN ACCESS");

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = $message;
        $res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addWarning("$function - FORBIDDEN ACCESS:$res");

        return $response;
    }

    /**
     * formats validation Success response messages
     * @param type $function
     * @param type $message
     * @param type $data
     * @return Response
     */
    public function successLarge($function, $message, $data, $iserror = null) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(200, "SUCCESS");

        $res = new \stdClass();
        $res->code = "Success";
        if ($iserror) {
            $res->code = "Error";
        }

        $res->statusDescription = $message;
        $res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addInfo("$function - SUCCESS: Data sent back");

        return $response;
    }

    /**
     * successVueTable
     * @param type $data
     */
    public function successVueTable($data) {

        $this->response->setHeader("Content-Type", "application/json");
        $this->response->setHeader("Access-Control-Allow-Origin", "*");
        $this->response->setStatusCode(200, 'success');
        $this->response->setJsonContent($data);
        $this->response->send();
    }

    /**
     * formats validation Success response messages
     * @param type $function
     * @param type $message
     * @param type $data
     * @return Response
     */
    public function success($function, $message, $data, $iserror = null) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(200, "SUCCESS");

        $res = new \stdClass();
        $res->code = "Success";
        if ($iserror) {
            $res->code = "Error";
        }

        $res->statusDescription = $message;
        $res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addInfo("$function - SUCCESS:$res");

        return $response;
    }

    /**
     * formats validation error response messages 
     * @param type $function
     * @return Response
     */
    public function unProcessable($function, $message = null, $data = []) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(422, "UNPROCESSABLE ENTITY");

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = is_null($message) ? "Mandatory fields required!!" : $message;
        $res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addError("$function - UNPROCESSABLE:$res");

        return $response;
    }

    /**
     * Formats data error response messages 
     * @param type $function
     * @param type $message
     * @param type $data
     * @return Response
     */
    public function dataError($function, $message, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(421, "DATA ERROR");

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = $message;
        $res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->debug("$function - DATA ERROR:$res");

        return $response;
    }

    /**
     * Formats server error response messages 
     * @param type $function
     * @param type $message
     * @param type $data
     * @return Response
     */
    public function serverError($function, $message) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(500, "INTERNAL SERVER ERROR");

        $error = new \stdClass();
        $error->code = "Error";
        $error->statusDescription = $message;
        //$error->data = $data;

        $res = json_encode($error);
        $response->setContent($res);
        $this->getLogFile('debug')->addEmergency("$function - INTERNAL SERVER ERROR:$res");

        return $response;
    }

    /**
     * formats validation error response messages 
     * @param type $function
     * @return Response
     */
    public function unAuthorised($function, $message, $data = []) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(401, "UN-AUTHORISED ACCESS");

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = $message;
        $res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addWarning("$function - UN-AUTHORISED::$res");

        return $response;
    }
    
    /**
     * Formats server error response messages 
     * @param type $function
     * @param type $message
     * @param type $data
     * @return Response
     */
    public function turnstileResponse($function, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "text/html");
        $response->setHeader("Cache-Control", "private ");
        $response->setStatusCode(200, "OK");
        $res = json_encode($data);
        $response->setContent($res);
        $this->getLogFile('debug')->addEmergency("$function - SUCCESS:$res");

        return $response;
    }

    /**
     * formats validation error response messages 
     * @param type $function
     * @return Response
     */
    public function MethodNotAllowed($function, $message) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(405, $message);

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = $message;
        //$res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addWarning("$function - METHOD NOT ALLOWED:$res");

        return $response;
    }

    /**
     * formats validation error response messages 
     * @param type $function
     * @return Response
     */
    public function PaymentRequired($function, $message) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(402, "PAYMENT REQUIRED");

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = $message;
        //$res->data = $data;

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addNotice("$function - PAYMENT REQUIRED:$res");

        return $response;
    }

    /**
     * formats validation error response messages 
     * @param type $function
     * @return Response
     */
    public function BadRequest($function, $message, $data = null) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(400, "BAD REQUEST");

        $res = new \stdClass();
        $res->code = "Error";
        $res->statusDescription = $message;
        if ($data != null) {
            $res->data = $data;
        }

        $res = json_encode($res);
        $response->setContent($res);
        $this->getLogFile('debug')->addNotice("$function - BAD REQUEST:$res");

        return $response;
    }

    /**
     * getMicrotime
     * @return type
     */
    public function getMicrotime() {
        list ($msec, $sec) = explode(" ", microtime());
        return ((float) $msec + (float) $sec);
    }

    /**
     * Return the current Date and time in the standard format
     * @param string $format the format in which to return the date
     * @return string
     */
    public function now($format = 'Y-m-d H:i:s', $timestamp = null) {
        if ($timestamp == null) {
            $timestamp = time();
        }
        return date($format, $timestamp);
    }

    /**
     * getClientIPAddress
     * @return string
     */
    public function getClientIPAddress() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    /**
     * Checks validity of the date
     * @param DateTime $futureDate
     * @param DateTime $startDate
     * @return type
     */
    public function isDateBetweenDates($futureDate, $startDate) {
        $futureDate = new DateTime($futureDate);
        $startDate = new DateTime($startDate);

        return $futureDate > $startDate;
    }

    /**
     * getDatetimeNow
     * @return type
     */
    public function getDatetimeNow() {
        $tz_object = new DateTimeZone('Africa/Nairobi');
        $datetime = new DateTime();
        $datetime->setTimezone($tz_object);

        return $datetime->format('Y\-m\-d\ H:i:s');
    }

    /**
     * validateDate
     * @param type $birthDate
     * @return type
     */
    public function validateDate($birthDate) {
        $validateFlag = true;
        $convertBirthDate = DateTime::createFromFormat('Y-m-d H:i:s', $birthDate);
        $birthDateErrors = DateTime::getLastErrors();

        $status = "";
        if ($birthDateErrors['warning_count'] + $birthDateErrors['error_count'] > 0) {
            $status = "The date format is wrong.";
        } else {
            $testBirthDate = explode('-', $birthDate);
            if ($testBirthDate[0] < 1900) {
                $validateFlag = false;
                $status = "We suspect that you did not born before XX century.";
            }
        }

        return ['status' => $validateFlag, 'desc' => $status];
    }

    /**
     * DayDiff
     * @param type $last_won
     * @return type
     */
    protected function DayDiff($from_date, $to_date) {
        //Convert it into a timestamp.
        $from_date = strtotime($from_date);
        $to_date = strtotime($to_date);

        //Calculate the difference.
        $difference = $to_date - $from_date;

        //Convert seconds into days.
        $days = floor($difference / (60 * 60 * 24));

        return $days;
    }

    /**
     * MinuteDiff
     * @param type $from_date
     * @param type $to_date
     * @return type
     */
    public function MinuteDiff($from_date, $to_date) {
        //Convert it into a timestamp.
        $from_date = strtotime($from_date);
        $to_date = strtotime($to_date);

        //Calculate the difference.
        $difference = $to_date - $from_date;

        //Convert seconds into days.
        $days = floor($difference / (60 * 24));

        return $days;
    }

    /**
     * dayDifference
     * @param type $last_won
     * @return type
     */
    protected function dayDifference($last_won) {
        //Convert it into a timestamp.
        $last_won = strtotime($last_won);

        //Get the current timestamp.
        $now = time();

        //Calculate the difference.
        $difference = $now - $last_won;

        //Convert seconds into days.
        $days = floor($difference / (60 * 60 * 24));

        return $days;
    }

    /**
     * truncate
     * @param type $string
     * @param type $length
     * @param type $dots
     * @return type
     */
    public function truncate($string, $length, $dots = "...") {
        return (strlen($string) > $length) ? substr($string, 0, $length - strlen($dots)) . $dots : $string;
    }

    /**
     * CheckValidateDate
     * @param type $date
     * @param type $format
     * @return type
     */
    public function CheckValidateDate($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public function startsWith($haystack, $needle) {
        $haystack = strtolower($haystack);
        $needle = strtolower($needle);

        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public function endsWith($haystack, $needle) {
        $length = strlen($needle);
        return $length === 0 || (substr($haystack, -$length) === $needle);
    }

    /**
     * validateURL
     * @param type $url
     * @return boolean
     */
    public function validateURL($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return TRUE;
    }

    /**
     * validateEmail
     * @param type $email
     * @return boolean
     */
    public function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return TRUE;
    }

    /**
     * Mysql Function
     */
    /*
     * raw insert
     */

    public function rawInsert($phql, $params = null) {
        try {
            $this->db->execute($phql, $params);
            $last_insert_id = $this->db->lastInsertId();
            return $last_insert_id;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param type $statement
     * @return type $quer
     *  resultset     
     */
    public function rawUpdate($statement) {
        try {
            $connection = $this->di->getShared("db");
            $success = $connection->execute($statement);
            $rowCount = $connection->affectedRows();
            return $rowCount;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * rawUpdateWithParams
     * @param type $statement
     * @return type $quer
     *  resultset     
     */
    public function rawUpdateWithParams($statement, $params) {
        try {
            $connection = $this->di->getShared("db");
            $success = $connection->execute($statement, $params);
            $rowCount = $connection->affectedRows();
            return $rowCount;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param type $statement 
     * @return type $quer resultset
     */
    public function rawSelect($statement, $params = null, $db = null) {
        try {
            if ($db == null) {
                $connection = $this->di->getShared("db");
            } else {
                $connection = $this->di->getShared("db2");
            }

            $success = $connection->query($statement, $params);
            $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
            $result = $success->fetchAll($success);

            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * selectQuery
     * @param type $sql
     * @return type
     */
    public function selectQuery($sql, $params = null) {
        try {
            $connection = $this->di->getShared("db2");
            $success = $connection->query($sql, $params);
            $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
            $result = $success->fetchAll($success);

            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * rawSelectOneRecord
     * @param type $sql
     * @return type
     */
    public function rawSelectOneRecord($sql, $params = null, $db = null) {
        try {
            $connection = null;
            if ($db == null) {
                $connection = $this->di->getShared("db");
            } else {
                $connection = $this->di->getShared("db2");
            }
            return $connection->fetchOne($sql, (Phalcon\Db::FETCH_ASSOC), $params);
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * rawInsertBulk
     * @param type $table
     * @param type $table_data
     * @param type $db
     * @return type
     * @throws \Exception
     */
    public function rawInsertBulk($table, $table_data, $db = null) {
        $startTime = microtime(true);
        $values = '';
        $fields = '';

        $arr = [];
        foreach ($table_data as $k => $v) {
            $fields .= ',' . $k;
            $values .= ",:$k";
            $arr[":$k"] = $v;
        }

        $values = substr($values, 1);
        $fields = substr($fields, 1);
        $sql = "insert into $table ($fields) VALUES ($values)";

        $insert = false;
        try {
            $insert = $this->rawInsert($sql, $arr, $db);
        } catch (\Exception $e) {
            $this->getLogFile('fatal')->emergency(__LINE__ . ":" . __FUNCTION__
                    . " | INSERT_QUERY"
                    . " | SQL::$sql"
                    . " | Exception::" . $e->getTraceAsString()
                    . " | Message:" . $e->getMessage());

            throw $e;
        }

        $executionTime = (microtime(true) - $startTime);
        if ($executionTime > 1) {
            $this->getLogFile('fatal')->debug(__LINE__ . ":" . __FUNCTION__
                    . " | Took " . $executionTime . " Sec"
                    . " | inserting into $table"
                    . " | SQL:$sql");
        }

        return $insert;
    }

    /**
     * tableQueryBuilder
     * @param type $sort
     * @param type $order
     * @param int $page
     * @param int $limit
     * @param type $groupBy
     * @return type
     */
    public function tableQueryBuilder($sort = "", $order = "", $page = 0, $limit = 10, $groupBy = "") {

        $orderBy = $sort ? "ORDER BY $sort $order" : "";

        $sortClause = "$groupBy $orderBy";

        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit) {
            $limit = 10;
        }

        $ofset = (int) ($page - 1) * $limit;
        $limitQuery = "LIMIT $ofset, $limit";

        return "$sortClause $limitQuery";
    }

    /**
     * whereQuery
     * @param type $whereArray
     * @param type $groupBy
     * @param type $searchColumns
     * @return type
     */
    public function whereQuery($whereArray, $groupBy, $searchColumns = []) {

        $whereQuery = "";
        $havingQuery = "";

        foreach ($whereArray as $key => $value) {

            if ($key == 'filter') {
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
            } else if ($key == 'having') {
                if (!empty($value[1]) && !empty($value[2])) {
                    $valueString = " $value[0] between $value[1] AND $value[2] AND ";
                    $havingQuery .= $valueString;
                }
            } else if (is_array($value)) {
                $type = isset($value[3]) ? $value[3] : 1;

                if (!empty($value[1]) && !empty($value[2])) {
                    $valueString = $type == 1 ? " $value[0] between '$value[1]' AND '$value[2]' AND " : " $value[0] between $value[1] AND $value[2] AND ";
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

        if ($havingQuery) {
            $havingQuery = chop($havingQuery, " AND ");
        }

        $whereQuery = $whereQuery ? "WHERE $whereQuery " : "";
        $havingQuery = $havingQuery ? " HAVING $havingQuery " : "";

        return $whereQuery . $groupBy . $havingQuery;
    }

    /**
     *
     * This function is searches through a string and try to find any placeholder variables,
     *  which would be place between two curly brackets {}. It grabs the value between the
     *  curly brackets and uses it to look through an array where it should match the key.
     *  Then it replaces the curly bracket variable in the string with the value in the
     *  array of the matching key.
     *
     * @param $template - string with placeholders
     * @param $data - replaceble values in an array
     */
    public function SMSTemplate($template, $data) {
        return strtr($template, $data);
    }

    /**
     * formatMobileNumber
     * @param type $mobile
     * @return type
     */
    public function formatMobileNumber($mobile, $dial_code = false) {

        if (!$dial_code) {
            $dial_code = $this->settings['mnoApps']['DefaultDialCode'];
        }

        $mobile = str_replace("+", "", $mobile);
        $mobile = preg_replace('/[\t\n\r\s]+/', '', $mobile);
        $mobile = preg_replace('/\s+/', '', $mobile);
        $mobile = preg_replace('~\D~', '', $mobile);
        $input = substr($mobile, 0, -strlen($mobile) + 1);

        $number = '';
        if ($input == '0') {
            $number = substr_replace($mobile, $dial_code, 0, 1);
        } elseif ($input == '+') {
            $number = substr_replace($mobile, $dial_code, 0, 1);
        } elseif ($input == '7') {
            $number = substr_replace($mobile, $dial_code . '7', 0, 1);
        } elseif ($input == '1') {
            $number = substr_replace($mobile, $dial_code . '1', 0, 1);
        } elseif ($input == '2' && (strlen($input) == 9)) {
            $number = substr_replace($mobile, $dial_code . '2', 0, 1);
        } else {
            $number = $mobile;
        }

//        if (strlen($number) != 12) {
//            return false;
//        }

        if (in_array(substr($number, 0, 4), [$this->settings['mnoApps']['DefaultDialCode'] . "1"
                    , $this->settings['mnoApps']['DefaultDialCode'] . '2542'])) {
            if (strlen($number) == 12) {
                return $number;
            }
        }

        return $number;
    }

    /**
     * validateMobile
     * @param type $number
     * @return boolean
     */
    public function validateMobile($number, $dialCode = null) {
        if (in_array(substr($number, 0, 4), ["2541", '2542'])) {
            if (strlen($number) == 12) {
                return $number;
            }
        }

        if (in_array(substr($number, 0, 3), ["233", '232', '234', '971', '973', '255'])) {
            if (strlen($number) == 12) {
                return $number;
            }
        }

        if (in_array(substr($number, 0, 2), ['44', '91', '17', '27'])) {
            if (strlen($number) == 12 || strlen($number) == 11) {
                return $number;
            }
        }

        $regex = '/^(?:\+?(?:[1-9]{3})|0)?7([0-9]{8})$/';
        if (preg_match_all($regex, $number, $capture)) {
            $msisdn = $dialCode . '7' . $capture[1][0];
        } else {
            $msisdn = false;
        }

        return $msisdn;
    }

    /**
     * Gets the Mobile operator Network
     * @param type $MSISDN
     * @return type
     */
    public function getMobileNetwork($MSISDN) {
        $network = "";
        $countryCode = substr($MSISDN, 0, 3);
        $mnoCode = substr($MSISDN, 3, 2);
        switch ($countryCode) {
            case 233://GHANA_TRX
                $network = 'GHANA_TRX';
                break;
            case 252://SOMALIA
                $network = 'SOMALIA';
                break;
            case 254://Kenya
                switch ($mnoCode) {
                    case 70:
                    case 71:
                    case 72:
                    case 74:
                    case 79:
                    case 11:
                        $network = 'SAFARICOM';
                        break;
                    case 73:
                    case 78:
                    case 75:
                    case 10:
                        $network = 'AIRTEL_KE';
                        $countryCode = substr($MSISDN, 0, 6);
                        if ($countryCode == '254757' || $countryCode == '254758' || $countryCode == '254759') {
                            $network = 'SAFARICOM';
                        }
                        break;
                    case 77:
                    case 20:
                        $network = 'TELKOM_KE';
                        break;
                    case 76:
                        $network = 'EQUITEL';
                        $countryCode = substr($MSISDN, 0, 6);
                        if ($countryCode == '254768' || $countryCode == '254769') {//|| 
                            $network = 'SAFARICOM';
                        }

                        if ($countryCode == '254762') {
                            $network = 'AIRTEL_KE';
                        }
                        break;
                    default:
                        $network = 'UNKNOWN';
                        break;
                }
                break;
            case 255://Tanzania
                $network = 'VODACOM_TZ';
                break;
            case 256://Uganda
                $network = 'MTN_UGX';
                break;
            case 250://Rwanda
                $network = 'MTN_RWX';
                break;
            case 233://GHANA
                $network = 'GHANA';
                break;
            case 234://NIGERIA
                $network = 'NIGERIA_TRX';
                break;
            case 249://SUDAN
                $network = 'SUDAN_TRX';
                break;
            case 211://SOUTH SUDAN
                $network = 'SSUDAN_TRX';
                break;
            case 251://ETHIOPIA
                $network = 'ETHIOPIA_TRX';
                break;
            case 252://SOMALIA
                $network = 'SOMALIA';
                break;
            case 250://Rwanda
                $network = 'MTN_RWX';
                break;
            case 267://BOTSWANA
                $network = 'BOTSWANA_TRX';
                break;
            default:
                $network = 'UNKNOWN';
                if (substr($MSISDN, 0, 2) == '91') {
                    $network = 'INDIA_TRX';
                }

                if (substr($MSISDN, 0, 2) == '27') {
                    $network = 'SAFRICA_TRX';
                }

                if (substr($MSISDN, 0, 2) == '86') {
                    $network = 'CHINA_TRX';
                }

                if (substr($MSISDN, 0, 2) == '44') {
                    $network = 'UK_TRX';
                }
                break;
        }
        return $network;
    }

    /**
     * 
     * @param type $len
     * @return string
     */
    public function randStrGen($len) {
        $result = "";
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789$11";
        $charArray = str_split($chars);
        for ($i = 0; $i < $len; $i++) {
            $randItem = array_rand($charArray);
            $result .= "" . $charArray[$randItem];
        }
        return $result;
    }

    /**
     * getClientIPServer
     * @return string
     */
    public function getClientIPServer() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    /**
     * Gets the log file to use
     * @param type $action
     * @return type
     */
    public function getLogFile($action = "") {
        $logger = '';
        /**
         * Read the configuration
         */
        $logPathLocation = "/var/www/logs/madfun/";

        if (!is_dir($logPathLocation)) {
            mkdir($logPathLocation, 0755, true); // Creates directory if it doesn't exist
        }
        $dateFormat = $this->logPath['dateFormat'];
        $output = $this->logPath['output'];
        $filename = $this->logPath['systemName'];

        switch ($action) {
            case 'info':
                $streamFile = $logPathLocation . "" . $filename . "Info.log";
                $stream = new StreamHandler($streamFile, Logger::INFO);
                $stream->setFormatter(new LineFormatter($output, $dateFormat));
                $logger = new Logger('INFO');
                $logger->pushHandler($stream);
                break;
            case 'error':
                $streamFile = $logPathLocation . "" . $filename . "Error.log";
                $stream = new StreamHandler($streamFile, Logger::ERROR);
                $stream->setFormatter(new LineFormatter($output, $dateFormat));
                $logger = new Logger('ERROR');
                $logger->pushHandler($stream);
                break;
            case 'fatal':
                $streamFile = $logPathLocation . "" . $filename . "Fatal.log";
                $stream = new StreamHandler($streamFile, Logger::EMERGENCY);
                $stream->setFormatter(new LineFormatter($output, $dateFormat));
                $logger = new Logger('FATAL');
                $logger->pushHandler($stream);
                break;
            case 'debug':
                $streamFile = $logPathLocation . "" . $filename . "Debug.log";
                $stream = new StreamHandler($streamFile, Logger::DEBUG);
                $stream->setFormatter(new LineFormatter($output, $dateFormat));
                $logger = new Logger('DEBUG');
                $logger->pushHandler($stream);
                break;
            default:
                $streamFile = $logPathLocation . "" . $filename . "Api.log";
                $stream = new StreamHandler($streamFile, Logger::INFO);
                $stream->setFormatter(new LineFormatter($output, $dateFormat));
                $logger = new Logger('INFO');
                $logger->pushHandler($stream);
                break;
        }

        return $logger;
    }

    /**
     * SendPostAuthData
     * @param type $postUrl
     * @param type $postData
     */
    public function SendJsonPostAuthData($postUrl, $postData, $authorisation, $type = null) {

        $auth = "Bearer";
        if ($type == 1) {
            $auth = "Basic";
        }

        if ($type == 2) {
            $auth = "Token";
        }
        if ($type == 3) {
            $auth = "Bearer";
        }


        $ch = curl_init($postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-Authorisation: ' . $auth . ' ' . trim($authorisation),
            'X-Requested-With: XMLHttpRequest',
            'Content-Length: ' . strlen(json_encode($postData))));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->settings['appName'] . "/3.0");

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return ["statusCode" => $status, "response" => $result, 'error' => $curlError];
    }

    /**
     * Send Post Data via cURL
     * @param type $postUrl
     * @param type $postData
     */
    public function sendJsonPostData($postUrl, $postData) {
        $httpRequest = curl_init($postUrl);
        curl_setopt($httpRequest, CURLOPT_NOBODY, true);
        curl_setopt($httpRequest, CURLOPT_POST, true);
        curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array('Content-Type: '
            . 'application/json', 'Content-Length: ' . strlen(json_encode($postData))));
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($httpRequest, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']);
        //accept SSL settings
        curl_setopt($httpRequest, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($httpRequest, CURLOPT_USERAGENT, $this->settings['appName'] . "/3.0");

        $response = curl_exec($httpRequest);
        $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE);
        $curlError = curl_error($httpRequest);
        curl_close($httpRequest);

        return ["statusCode" => $status, "response" => $response, 'error' => $curlError];
    }
    
    public function sendJsonPesapalPostData($postUrl, $postData) {
        $httpRequest = curl_init($postUrl);
        curl_setopt($httpRequest, CURLOPT_NOBODY, true);
        curl_setopt($httpRequest, CURLOPT_POST, true);
        curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json', 
            'Content-Length: ' . strlen(json_encode($postData))));
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($httpRequest, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']);
        //accept SSL settings
        curl_setopt($httpRequest, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($httpRequest, CURLOPT_USERAGENT, $this->settings['appName'] . "/3.0");

        $response = curl_exec($httpRequest);
        $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE);
        $curlError = curl_error($httpRequest);
        curl_close($httpRequest);

        return ["statusCode" => $status, "response" => $response, 'error' => $curlError];
    }
    
   
    
     public function sendJsonTokenPesapalPostData($postUrl, $postData, $authorisation) {

        $auth = "Bearer";


        $ch = curl_init($postUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . $auth . ' ' . trim($authorisation),
            'Content-Length: ' . strlen(json_encode($postData))));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->settings['appName'] . "/3.0");

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return ["statusCode" => $status, "response" => $result, 'error' => $curlError];
    }
    
    /**
     * sendGetRequestWithHeaders
     * @param type $Url
     * @param type $headers
     * @return type
     */
    public function sendGetRequestWithHeaders($Url, $authorisation) {
        $auth = "Bearer";
        $httpRequest = curl_init($Url);
        curl_setopt($httpRequest, CURLOPT_CUSTOMREQUEST, "GET");
         curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: ' . $auth . ' ' . trim($authorisation)));
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($httpRequest, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($httpRequest, CURLOPT_ENCODING, "");
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($httpRequest, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($httpRequest, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        //accept SSL settings
        curl_setopt($httpRequest, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($httpRequest);
        $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE);
        $curlError = curl_error($httpRequest);
        curl_close($httpRequest);

        return ["statusCode" => $status, "response" => $response, 'error' => $curlError];
    }

    /**
     * sendGetRequest
     * @param type $url
     * @return type
     */
    public function sendGetRequest($url) {
        $httpRequest = curl_init($url);
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_USERAGENT, $this->settings['appName'] . "/3.0");
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']); //timeout after 30 seconds
        curl_setopt($httpRequest, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']); //timeout after 30 seconds
        $response = curl_exec($httpRequest);
        $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE);
        curl_close($httpRequest);

        return [
            'statusCode' => isset($status) ? $status : 0,
            'response' => json_decode($response)];
    }

    /**
     * sendSafPostData
     * @param type $postUrl
     * @param type $postData
     * @param type $token
     * @return type
     */
    public function sendSafPostData($postUrl, $postData, $token) {

        $headers = [
            'x-Requested-With: XMLHttpRequest',
            "X-Authorization: Bearer $token",
            'Content-Type: application/json'];

        //x-Requested-With:XMLHttpRequest
        $httpRequest = curl_init($postUrl);
        curl_setopt($httpRequest, CURLOPT_NOBODY, true);
        curl_setopt($httpRequest, CURLOPT_POST, true);
        curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($httpRequest, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($httpRequest, CURLOPT_CONNECTTIMEOUT, $this->settings['timeoutDuration']);
        curl_setopt($httpRequest, CURLOPT_USERAGENT, $this->settings['appName'] . "/3.0");
        //accept SSL settings
        curl_setopt($httpRequest, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($httpRequest, CURLOPT_USERPWD, 'sms@southwell.io:' . md5('sms@southwell.io'));
        $response = curl_exec($httpRequest);
        $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE);
        $curlError = curl_error($httpRequest);
        curl_close($httpRequest);

        return ["statusCode" => $status, "response" => $response, 'error' => $curlError];
    }

    public function sendXMLRequestData($postUrl, $xmlData) {
        $headers = [
            'Content-Type: application/xml'];
        $httpRequest = curl_init($postUrl);
        curl_setopt($httpRequest, CURLOPT_NOBODY, true);
        curl_setopt($httpRequest, CURLOPT_POST, true);
        curl_setopt($httpRequest, CURLOPT_POSTFIELDS, $xmlData);
        curl_setopt($httpRequest, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
        curl_setopt($httpRequest, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($httpRequest, CURLOPT_USERAGENT, "Madfun/1.0");
        //accept SSL settings
        curl_setopt($httpRequest, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($httpRequest, CURLOPT_USERPWD, 'sms@southwell.io:' . md5('sms@southwell.io'));
        $response = curl_exec($httpRequest);
        $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE);
        $curlError = curl_error($httpRequest);
        curl_close($httpRequest);

        return ["statusCode" => $status, "response" => $response, 'error' => $curlError];
    }

}
