<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of DPOCardProcessing
 *
 * @author kevinkmwando
 */
use Phalcon\Mvc\Controller;
use ControllerBase as base;

class DPOCardProcessing extends Controller {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }

    //put your code here
    public function createToken($params, $currrency = "KES", $isoCode = "KE") {
        $base = new base();
        try {
            $endpoint = $base->settings['DPO']['endpoint'];
            $companyToken = $base->settings['DPO']['companyToken'];
            $servicesType = $base->settings['DPO']['servicesType'];
            $backUrl = $base->settings['DPO']['backUrl'];
            $redirectURL = $base->settings['DPO']['redirectURL'];
            $paymentHourLimit = $base->settings['DPO']['paymentHourLimit'];
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                             <API3G>
                                <CompanyToken>' . $companyToken . '</CompanyToken>
                                <Request>createToken</Request>
                                <Transaction>
                                <PaymentAmount>' . $params['amount'] . '</PaymentAmount>
                                <PaymentCurrency>' . $currrency . '</PaymentCurrency>
                                <CompanyRef>' . $params['account'] . '</CompanyRef>
                                <RedirectURL>' . $redirectURL . '</RedirectURL>
                                <BackURL>' . $backUrl . '' . $params['eventId'] . '</BackURL>
                                <CompanyRefUnique>0</CompanyRefUnique>
                                <PTL>' . $paymentHourLimit . '</PTL>
                                <customerCountry>' . $isoCode . '</customerCountry>
                                <customerDialCode>' . $isoCode . '</customerDialCode>
                                <customerFirstName>' . $params['first_name'] . '</customerFirstName>
                                <customerLastName>' . $params['last_name'] . '</customerLastName>
                                <customerEmail>' . $params['email'] . '</customerEmail>
                                <customerPhone>' . $params['phone'] . '</customerPhone>
                                <DefaultPayment>CC</DefaultPayment>
                                </Transaction>
                                <Services>
                                  <Service>
                                    <ServiceType>' . $servicesType . '</ServiceType>
                                    <ServiceDescription>' . $params['description'] . '</ServiceDescription>
                                    <ServiceDate>' . $base->now('Y/m/d H:i') . '</ServiceDate>
                                  </Service>
                                </Services>
                                <Additional>
                                <BlockPayment>MO</BlockPayment>
                                </Additional>
                               </API3G>';
            $result = $base->sendXMLRequestData($endpoint, $xmlRequest);
            $response = $result['response'];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Response: " . json_encode($response) . " Date: " . $base->now('Y/m/d H:i') . " Request " . json_encode($xmlRequest));
            return $response;
        } catch (Exception $ex) {
            $this->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | createToken Exception::" . $ex->getMessage());
        }
        return false;
    }

    public function createMobileToken($params, $currrency = "UGX", $isoCode = "UG", $country="Uganda") {
        $base = new base();
        try {
            $endpoint = $base->settings['DPO']['endpoint'];
            $companyToken = $base->settings['DPO']['companyToken'];
            $servicesType = $base->settings['DPO']['servicesType'];
            $backUrl = $base->settings['DPO']['backUrl'];
            $redirectURL = $base->settings['DPO']['redirectURL'];
            $paymentHourLimit = $base->settings['DPO']['paymentHourLimit'];
            
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?><API3G>'
                    . '<CompanyToken>'.$companyToken.'</CompanyToken>'
                    . '<Request>createToken</Request><Transaction>'
                    . '<PaymentAmount>'.$params['amount'] .'</PaymentAmount>'
                    . '<PaymentCurrency>'.$currrency.'</PaymentCurrency>'
                    . '<CompanyRef>'. $params['account'] .'</CompanyRef>'
                    . '<RedirectURL>'.$redirectURL.'</RedirectURL>'
                    . '<BackURL>'.$backUrl.'</BackURL>'
                    . '<CompanyRefUnique>0</CompanyRefUnique>'
                    . '<PTL>'. $paymentHourLimit .'</PTL>'
                    . '<PTLtype>Hours</PTLtype>'
                    . '<customerFirstName>'. $params['first_name'] .'</customerFirstName>'
                    . '<customerLastName>'. $params['last_name'] .'</customerLastName>'
                    . '<customerCountry>'.$isoCode.'</customerCountry>'
                    . '<customerDialCode>'.$isoCode.'</customerDialCode>'
                    . '<customerEmail>' . $params['email'] . '</customerEmail>'
                    . '<customerPhone>' . $params['phone'] . '</customerPhone>'
                    . '<EmailTransaction>1</EmailTransaction>'
                    . '</Transaction><Services><Service>'
                    . '<ServiceType>' . $servicesType . '</ServiceType>'
                    . '<ServiceDescription>' . $params['description'] . '</ServiceDescription>'
                    . '<ServiceDate>' . $base->now('Y/m/d H:i') . '</ServiceDate>'
                    . '</Service></Services></API3G>';
           
            $result = $base->sendXMLRequestData($endpoint, $xmlRequest);
            $response = $result['response'];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Response: " . json_encode($response) . " Date:"
                    . " " . $base->now('Y/m/d H:i') . " Request " . json_encode($xmlRequest));
            return $response;
        } catch (Exception $ex) {
            $this->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | createToken Exception::" . $ex->getMessage());
        }
        return false;
    }

    public function streamToken($params, $currrency = "KES") {
        $base = new base();
        try {
            $endpoint = $base->settings['DPO']['endpoint'];
            $companyToken = $base->settings['DPO']['companyToken'];
            $servicesType = $base->settings['DPO']['servicesType'];
            $redirectURL = $base->settings['DPO']['redirectStreamURL'];
            $paymentHourLimit = $base->settings['DPO']['paymentHourLimit'];
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
                             <API3G>
                                <CompanyToken>' . $companyToken . '</CompanyToken>
                                <Request>createToken</Request>
                                <Transaction>
                                <PaymentAmount>' . $params['amount'] . '</PaymentAmount>
                                <PaymentCurrency>' . $currrency . '</PaymentCurrency>
                                <CompanyRef>' . $params['account'] . '</CompanyRef>
                                <RedirectURL>' . $redirectURL . '</RedirectURL>
                                <BackURL>' . $params['cancelURL'] . '</BackURL>
                                <CompanyRefUnique>0</CompanyRefUnique>
                                <PTL>' . $paymentHourLimit . '</PTL>
                                <customerCountry>KE</customerCountry>
                                <customerDialCode>KE</customerDialCode>
                                <customerFirstName>' . $params['first_name'] . '</customerFirstName>
                                <customerLastName>' . $params['last_name'] . '</customerLastName>
                                <customerEmail>' . $params['email'] . '</customerEmail>
                                <customerPhone>' . $params['phone'] . '</customerPhone>
                                <DefaultPayment>CC</DefaultPayment>
                                </Transaction>
                                <Services>
                                  <Service>
                                    <ServiceType>' . $servicesType . '</ServiceType>
                                    <ServiceDescription>' . $params['description'] . '</ServiceDescription>
                                    <ServiceDate>' . $base->now('Y/m/d H:i') . '</ServiceDate>
                                  </Service>
                                </Services>
                                <Additional>
                                <BlockPayment>MO</BlockPayment>
                                </Additional>
                               </API3G>';
            $result = $base->sendXMLRequestData($endpoint, $xmlRequest);
            $response = $result['response'];

            $this->infologger->info(__LINE__ . ":" . __CLASS__
                    . " | Response: " . json_encode($response) . " Date: " . $base->now('Y/m/d H:i'));
            return $response;
        } catch (Exception $ex) {
            $this->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | createToken Exception::" . $ex->getMessage());
        }
        return false;
    }
}
