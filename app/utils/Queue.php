<?php

/**
 * Description of Queue
 *
 * @author User
 */
use Phalcon\Mvc\Controller;
use ControllerBase as base;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Queue extends Controller {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }

    /**
     * ConnectAndPublishToQueue
     * @param type $payload
     * @param type $queueName
     * @param type $exchangeKey
     * @param type $routeKey
     * @param type $server
     * @param type $port
     * @param type $user
     * @param type $pass
     * @param type $vhost
     * @return \stdClass
     */
    public function ConnectAndPublishToQueue($payload, $queueName, $exchangeKey, $routeKey
            , $server = null, $port = null, $user = null, $pass = null, $vhost = "/") {
        $response = new \stdClass();

        $start = $this->base->getMicrotime();

        if (!$queueName || !$exchangeKey || !$routeKey) {
            $response->code = 422;
            $response->statusDescription = "Mandatory Fields are missing!!";
            $response->data = [];

            return $response;
        }

        $queueName = strtoupper($queueName) . '_QUEUE'; //queue name
        $exchangeKey = strtoupper($exchangeKey) . '_EXCHANGE'; //queue exchange,
        $routeKey = strtoupper($routeKey) . '_ROUTE'; //queue routing

        if (!$vhost) {
            $vhost = "/";
        }


        $rabbitMQ = $this->queue;

        if ($server == null) {
            $server = $rabbitMQ['rabbitServer'];
        }

        if ($port == null) {
            $port = $rabbitMQ['rabbitPortNo'];
        }

        if ($user == null) {
            $user = $rabbitMQ['rabbitUser'];
        }

        if ($pass == null) {
            $pass = $rabbitMQ['rabbitPass'];
        }

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $conn = null;
        try {
         
            $conn = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
        } catch (Exception $ex) {
            $response->code = 500;
            $response->statusDescription = $ex->getMessage();
            $response->data = [];
             $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Took " . $this->base->CalculateTAT($start) . " Sec"
                    . " | [$queueName]"
                    . " | QUEUE SERVICE:: Message:{$payload}"
                    . " | Connection Failed!".$ex->getMessage());

            return $response;
        }

        if (!$conn) {
            $response->code = 401;
            $response->statusDescription = "Rabbit Connection Failed";

            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Took " . $this->base->CalculateTAT($start) . " Sec"
                    . " | [$queueName]"
                    . " | QUEUE SERVICE:: Message:{$payload}"
                    . " | Connection Failed!");

            return $response;
        }


        try {
            $channel = $conn->channel();
            $channel->queue_declare($queueName, false, true, false, false);
            $channel->exchange_declare($exchangeKey, 'direct', false, true, false);
            $channel->queue_bind($queueName, $exchangeKey, $routeKey);
            $channel->basic_publish(new AMQPMessage($payload, array('delivery_mode' => 2)), $exchangeKey, $routeKey);
            $channel->close();
            $conn->close();

            $response->code = 200;
            $response->statusDescription = "Successfully published on Queue:$queueName";
            $response->data = [
                "queue" => $queueName,
                "status" => "success",
                "time" => date("Y-m-d H:i:s")];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Took " . $this->base->CalculateTAT($start) . " Sec"
                    . " | [$queueName]"
                    . " | QUEUE SERVICE:: Message:{$payload}"
                    . " | Successfully Published!");

            return $response;
        } catch (Exception $ex) {
            $this->errorlogger->emergency(__LINE__ . ":" . __CLASS__
                    . " | Took " . $this->base->CalculateTAT($start) . " Sec"
                    . " | [$queueName]"
                    . " | QUEUE SERVICE:: Exception on Publishing Message::" . $ex->getMessage());
            $response->code = 500;
            $response->statusDescription = $ex->getMessage();
        }

        return $response;
    }

}
