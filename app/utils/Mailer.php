<?php

/**
 * Description of Mailer
 *
 * @author User
 */
use Phalcon\Mvc\Controller;
use ControllerBase as base;

class Mailer extends Controller {

    public $sender;
    public $sender_pass;
    public $smtp_host_ip = 'smtp.gmail.com';
    public $smtp_port_no = 465;
    public $from_details;
    public $email_to;
    public $email_cc;
    public $email_bcc;
    public $filepath;
    public $email_subject;
    public $email_message;
    public $decription;
    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
        require_once (__DIR__) . '/../../vendor/swiftmailer/swiftmailer/lib/swift_required.php';
    }

    /**
     * SendEmailWithAttachment
     * @return boolean
     */
    public function SendEmailWithAttachments() {
        $base = new ControllerBase();
        try {
            require_once (__DIR__) . '/../../vendor/swiftmailer/swiftmailer/lib/swift_required.php';

            $host_ip = gethostbyname($this->smtp_host_ip);
            $transport = \Swift_SmtpTransport::newInstance($this->smtp_host_ip, 465, 'ssl')
                    ->setUsername($this->sender)
                    ->setPassword($this->sender_pass);

            if (!$this->from_details) {
                $this->from_details = [
                    'from' => 'noreply@vaspro.co',
                    'decription' => isset($this->decription) ? $this->decription : 'Core Platform Mail Bot'
                ];
            }

            foreach ($this->email_to as $value) {
                $this->email_to[$value] = $value;
            }

            if (empty($this->email_to)) {
                return ['code' => 2, 'message' => 'Email recipient is required!!', 'response' => null];
            }

            foreach ($this->email_cc as $value) {
                $this->email_cc[$value] = $value;
            }

            foreach ($this->email_bcc as $value) {
                $this->email_bcc[$value] = $value;
            }

            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance($this->email_subject)
                    ->setFrom(array($this->from_details['from'] => $this->from_details['decription']));

            $message->setTo($this->email_to);
            $message->setCc($this->email_cc);
            $message->setBcc($this->email_bcc);
            $message->setBody($this->email_message, 'text/html');

            if (!is_array($this->filepath)) {
                $this->filepath = explode(',', $this->filepath);
            }

            $i = 1;
            foreach ($this->filepath as $file) {
                if (!is_file($this->filepath)) {
                    $base->getLogFile("error")->info(__LINE__ . ":" . __CLASS__ . " | File Not Ok");
                }

                if (filesize($file) > 1024 * 25) {
                    $file = $this->gzCompressFile($file);

                    $message->attach(
                            Swift_Attachment::fromPath($file)
                                    ->setFilename("attachment_$i.gz"));
                    continue;
                }

                $extension = "";
                $mime = mime_content_type($file);
                if ($mime == 'application/pdf') {
                    $extension = '.pdf';
                }

                if ($mime == 'application/csv' || $mime == 'text/csv') {
                    $extension = '.csv';
                }

                if ($mime == 'text/plain') {
                    $extension = '.csv';
                }

                $message->attach(
                        Swift_Attachment::fromPath($file)
                                ->setFilename("attachment_$i" . $extension));

                $base->getLogFile("error")->info(__LINE__ . ":" . __CLASS__ . " | File Ok");
            }

            $result = $mailer->send($message);

            if ($result > 0) {
                return ['code' => 0
                    , 'message' => 'Email email with attachment sent!!'
                    , 'response' => $result];
            }

            return ['code' => 1
                , 'message' => 'Email email with attachment Not sent!!'
                , 'response' => $result];
        } catch (Exception $ex) {
            return ['code' => 2
                , 'message' => $ex->getMessage()
                , 'response' => null];
        }
    }

    /**
     * SendEmailWithoutAttachments
     * @return type
     */
    public function SendEmailWithoutAttachments() {
        $base = new ControllerBase();

        try {
            require_once (__DIR__) . '/../../vendor/swiftmailer/swiftmailer/lib/swift_required.php';

            $host_ip = gethostbyname($this->smtp_host_ip);
            $transport = \Swift_SmtpTransport::newInstance($this->smtp_host_ip, 465, 'ssl')
                    ->setUsername($this->sender)
                    ->setPassword($this->sender_pass);

            if (!$this->from_details) {
                $this->from_details = [
                    'from' => 'noreply@vaspro.co',
                    'decription' => isset($this->decription) ? $this->decription : 'Core Platform Mail Bot'
                ];
            }

            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance($this->email_subject)
                    ->setFrom(array($this->from_details['from'] => $this->from_details['decription']));

            if (!is_array($this->email_to)) {
                $this->email_to = explode(',', $this->email_to);
            }

            $this->email_to = array_unique($this->email_to);

            foreach ($this->email_to as $value) {
                $this->email_to[$value] = $value;
            }

            if (empty($this->email_to)) {
                return ['code' => 2, 'message' => 'Email recipient is required!!', 'response' => null];
            }

            $message->setTo($this->email_to);

            if ($this->email_cc) {
                if (!is_array($this->email_cc)) {
                    $this->email_cc = explode(',', $this->email_cc);
                }

                $this->email_cc = array_unique($this->email_cc);

                foreach ($this->email_cc as $value) {
                    $this->email_cc[$value] = $value;
                }

                $message->setCc($this->email_cc);
            }


            if ($this->email_bcc) {
                if (!is_array($this->email_bcc)) {
                    $this->email_bcc = explode(',', $this->email_bcc);
                }

                $this->email_bcc = array_unique($this->email_bcc);

                foreach ($this->email_bcc as $value) {
                    $this->email_bcc[$value] = $value;
                }

                $message->setBcc($this->email_bcc);
            }

            $message->setBody($this->email_message, 'text/html');

            $result = $mailer->send($message);

            if ($result > 0) {
                return ['code' => 0, 'message' => 'Email email without attachment sent!!', 'response' => $result];
            }

            return ['code' => 1, 'message' => 'Email email without attachment Not sent!!', 'response' => $result];
        } catch (Exception $ex) {
            return ['code' => 2, 'message' => $ex->getMessage(), 'response' =>
                ['cc' => $this->email_cc, 'bcc' => $this->email_bcc, 'to' => $this->email_to]];
        }
    }

    /**
     * GetSpecificTempate
     * @param type $section
     * @return string
     */
    public function GetTicketTempate($params) {
        $base = new ControllerBase();
        $template = "";
        try {
//            $statement = "select template from event_email_template "
//                    . "WHERE event_email_template.eventId = :eventID AND"
//                    . " event_email_template.type = :type";
//
//            $selectParams = [
//                ':eventID' => $params['eventID'],
//                ':type' => $params['type']
//            ];
//            $result = $base->rawSelect($statement, $selectParams);
//            if ($result) {
//                $template = $result[0]['template'];
//                $template = str_replace('£name£', $params['name'], $template);
//                $template = str_replace('£eventDate£', $params['eventDate'], $template);
//                $template = str_replace('£eventAmount£', $params['eventAmount'], $template);
//                $template = str_replace('£eventName£', $params['eventName'], $template);
//                $template = str_replace('£eventVenue£', $params['eventVenue'], $template);
//                $template = str_replace('£ticketQuantity£', $params['ticketQuantity'], $template);
//                $template = str_replace('£QRcodeURL£', $params['QRcodeURL'], $template);
//                $template = str_replace('£QRcode£', $params['QRcode'], $template);
//                

            $template = "<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' lang='' xml:lang=''>
<head>
<title></title>

<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'/>
 <br/>
<style type='text/css'>
<!--
	p {margin: 0; padding: 0;}	.ft10{font-size:16px;font-family:Times;color:#00a89d;}
	.ft11{font-size:9px;font-family:Times;color:#00a89d;}
	.ft12{font-size:5px;font-family:Times;color:#00a89d;}
	.ft13{font-size:13px;font-family:Times;color:#ffffff;}
	.ft14{font-size:7px;font-family:Times;color:#730231;}
	.ft15{font-size:9px;font-family:Times;color:#c00000;}
	.ft16{font-size:9px;font-family:Times;color:#730231;}
	.ft17{font-size:5px;font-family:Times;color:#ffffff;}
	.ft18{font-size:9px;line-height:16px;font-family:Times;color:#ffffff;}
	.ft19{font-size:9px;line-height:20px;font-family:Times;color:#ffffff;}
	.ft110{font-size:9px;line-height:14px;font-family:Times;color:#ffffff;}
	.ft111{font-size:9px;line-height:15px;font-family:Times;color:#ffffff;}
	.ft112{font-size:9px;line-height:21px;font-family:Times;color:#ffffff;}
-->
</style>
</head>
<body bgcolor='#A0A0A0' vlink='blue' link='blue'>
<div id='page1-div' style='position:relative;width:892px;height:511px;margin-left:18%'>
<img width='892' height='511' src='https://tokeabucket.s3.us-east-2.amazonaws.com/Events/tokea-001-nyarari.jpeg' alt='background image'/>
<p style='position:absolute;top:35px;left:809px;white-space:nowrap' class='ft10'><b>30</b></p>
<p style='position:absolute;top:36px;left:834px;white-space:nowrap' class='ft11'><b>TH</b></p>
<p style='position:absolute;top:58px;left:806px;white-space:nowrap' class='ft10'><b>OCT</b></p>
<p style='position:absolute;top:84px;left:822px;white-space:nowrap' class='ft12'><b>2021</b></p>
<p style='position:absolute;top:379px;left:16px;white-space:nowrap' class='ft13'><b>Name:" . $params['name'] . "</b></p>
<p style='position:absolute;top:436px;left:600px;white-space:nowrap' class='ft14'>@pizzaandwinefestival</p>
<p style='position:absolute;top:355px;left:720px;white-space:nowrap' class='ft15'><b><img  height='150px' src='" . $params['QRcodeURL'] . "' alt='".$params['QRcode']."' /></b></p>
<p style='position:absolute;top:355px;left:519px;white-space:nowrap' class='ft15'><b>ROYAL&#160;GARDENIA&#160;GARDENS&#160;(EVERGREEN)</b></p>
<p style='position:absolute;top:370px;left:582px;white-space:nowrap' class='ft15'><b>KIAMBU&#160;ROAD,&#160;KENYA</b></p>
<p style='position:absolute;top:399px;left:597px;white-space:nowrap' class='ft16'><b>11AM&#160;-&#160;8PM</b></p>
<p style='position:absolute;top:16px;left:22px;white-space:nowrap' class='ft13'><b>EVENT&#160;NAME:" . $params['eventName'] . "</b></p>
<p style='position:absolute;top:124px;left:22px;white-space:nowrap' class='ft13'><b>PAYMENT:KES " . $params['eventAmount'] . "</b></p>
<p style='position:absolute;top:238px;left:16px;white-space:nowrap' class='ft13'><b>EVENT&#160;DATE&#160;" . $params['eventDate'] . "</b></p>
<p style='position:absolute;top:259px;left:17px;white-space:nowrap' class='ft13'><b>SATURDAY<br/>30</b></p>
<p style='position:absolute;top:277px;left:31px;white-space:nowrap' class='ft17'><b>TH</b></p>
<p style='position:absolute;top:275px;left:48px;white-space:nowrap' class='ft13'><b>&amp;&#160;31</b></p>
<p style='position:absolute;top:277px;left:75px;white-space:nowrap' class='ft17'><b>ST</b></p>
<p style='position:absolute;top:275px;left:88px;white-space:nowrap' class='ft13'><b>OCT&#160;2021</b></p>
<p style='position:absolute;top:290px;left:17px;white-space:nowrap' class='ft13'><b>11:00&#160;AM</b></p>
<p style='position:absolute;top:240px;left:190px;white-space:nowrap' class='ft19'><b>LOCATION:<br/>ROYAL&#160;</b></p>
<p style='position:absolute;top:275px;left:190px;white-space:nowrap' class='ft110'><b>GARDENIA&#160;<br/>GARDENS&#160;</b></p>
<p style='position:absolute;top:303px;left:190px;white-space:nowrap' class='ft111'><b>(EVERGREEN),<br/>KIAMBU&#160;ROAD,<br/>KENYA</b></p>
<p style='position:absolute;top:379px;left:190px;white-space:nowrap' class='ft112'><b>TYPE:<br/>INFINITY&#160;DIE&#160;&#160;</b></p>
<p style='position:absolute;top:414px;left:190px;white-space:nowrap' class='ft13'><b>HARD&#160;TICKET</b></p>

</div>
</body>
</html>
";
            return $template;

        } catch (Exception $ex) {
            throw $ex;
        }
    }

}
