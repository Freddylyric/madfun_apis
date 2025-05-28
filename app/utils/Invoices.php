<?php

use Phalcon\Mvc\Controller;
use ControllerBase as base;

/**
 * Description of Invoices
 *
 * @author kevinmwando
 */
class Invoices extends Controller {

    protected $base;
    protected $infologger;
    protected $errorlogger;

    function onConstruct() {
        $this->base = new base();
        $this->infologger = $this->base->getLogFile('info');
        $this->errorlogger = $this->base->getLogFile('error');
    }

    public function createInvoices($params, $balanceAmount) {
        $base = new base();
        try {
            $sqlInsertProfile = "INSERT INTO invoices (invoice_type_id,reference,invoice_reference_id,"
                    . "invoice_from_client_id,invoice_to_client_id,invoice_payment_type_id,"
                    . "invoice_billing_reference,invoice_status,invoice_notes,"
                    . "invoice_issued_date,invoice_due_date,invoice_amount,"
                    . "invoice_amount_paid,invoice_fee,created)"
                    . " VALUES (:invoice_type_id,:reference,:invoice_reference_id,"
                    . ":invoice_from_client_id,:invoice_to_client_id,"
                    . ":invoice_payment_type_id,:invoice_billing_reference,"
                    . ":invoice_status,:invoice_note,:invoice_issued_date,"
                    . ":invoice_due_date,:invoice_amount,:invoice_amount_paid,"
                    . ":invoice_fee,NOW())";
            $paramsProfile = [
                ':invoice_type_id' => $params['invoice_type_id'],
                ':reference' => $base->generateUniqueReference("INV"),
                ':invoice_reference_id' => $params['invoice_reference_id'],
                ':invoice_from_client_id' => $params['invoice_to_client_id'],
                ':invoice_payment_type_id' => $params['invoice_payment_type_id'],
                ':invoice_to_client_id' => $params['invoice_to_client_id'],
                ':invoice_billing_reference' => $params['invoice_billing_reference'],
                ':invoice_status' => 4,
                ':invoice_note' => $params['invoice_note'],
                ':invoice_issued_date' => $params['invoice_issued_date'],
                ':invoice_due_date' => $params['invoice_due_date'],
                ':invoice_amount' => $params['invoice_amount'],
                ':invoice_amount_paid' => $params['invoice_amount_paid'],
                ':invoice_fee' => $params['invoice_fee']];
            $invoicesId = $base->rawInsert($sqlInsertProfile, $paramsProfile);
            if ($invoicesId) {
                return [
                    'status' => 200,
                    'invoice_id' => $invoicesId,
                    'message' => 'Invoice created successful'
                ];
            }
            return [
                'status' => 402,
                'invoice_id' => '',
                'message' => 'Failed to create invoices'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return [
                'status' => 500,
                'invoice_id' => '',
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }

    public function editInvoice($params) {
        $base = new base();
        try {
            $checkInvoice = $base->rawSelectOneRecord("SELECT * FROM invoices "
                    . "WHERE invoice_id=:invoice_id", [":invoice_id" => $params['invoice_id']]);
            if (!$checkInvoice) {
                return [
                    'status' => 404,
                    'message' => 'Failed to create invoice items'
                ];
            }

            $sqlQuery = "UPDATE invoices set invoice_notes=:invoice_note, "
                    . "invoice_reference_id=:invoice_reference_id,"
                    . "invoice_amount_paid=:invoice_amount_paid,"
                    . "invoice_issued_date=:invoice_issued_date,"
                    . "invoice_due_data=:invoice_due_data "
                    . " WHERE invoice_id=:invoice_id LIMIT 1";

            $paramsIvoices = [
                ":invoice_id" => $params['invoice_id'],
                ':invoice_reference_id' => $params['invoice_reference_id'],
                ':invoice_issued_date' => $params['invoice_issued_date'],
                ':invoice_note' => $params['invoice_note'],
                ':invoice_amount_paid' => $params['invoice_amount_paid'],
                ':invoice_due_data' => $params['invoice_due_data'],];

            $base->rawUpdateWithParams($sqlQuery, $paramsIvoices);

            return [
                'status' => 200,
                'invoice_id' => $params['invoice_id'],
                'message' => 'Invoice edited successful'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());
            return [
                'status' => 500,
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }

    public function createInvoiceItems($items, $invoiceId, $balanceAmount) {
        $base = new base();
        try {
            $checkInvoice = $base->rawSelectOneRecord("SELECT * FROM invoices "
                    . "WHERE invoice_id=:invoice_id", [":invoice_id" => $invoiceId]);
            if (!$checkInvoice) {
                return [
                    'status' => 404,
                    'message' => 'Failed to create invoice items'
                ];
            }
            $totalAmount = 0;
            $totalItems = 0;
            foreach ($items as $item) {
                $lineTotal = intval($item->quantity) * floatval($item->unit_price);
                $result = $base->rawInsertBulk('invoice_items', [
                    'invoice_id' => $invoiceId,
                    'product_name' => $item->productName,
                    'product_description' => isset($item->productDesc) ? $item->productDesc : $item->productName,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineTotal,
                    'created' => $base->now(),
                ]);
                if (!$result) {
                    continue;
                }
                $totalItems = $totalItems + intval($item->quantity);
                $totalAmount = $totalAmount + $lineTotal;
            }
            if($totalAmount > $balanceAmount){
                 return [
                    'status' => 422,
                    'message' => "Insufficent Balance. Current Balance is "
                     . "Kes: ".$balanceAmount
                ];
            }
            if ($totalItems == 0) {
                return [
                    'status' => 404,
                    'message' => 'Failed to create invoice items'
                ];
            }
            $resultUpdate = $base->rawUpdateWithParams("UPDATE invoices SET "
                    . "invoice_amount =:invoice_amount, total_items=:total_items, invoice_status = 2 WHERE"
                    . " invoice_id=:invoice_id LIMIT 1",
                    [':invoice_amount' => ceil($totalAmount),
                        ':total_items' => $totalItems,
                        ':invoice_id' => $invoiceId]);

            if (!$resultUpdate) {
                return [
                    'status' => 402,
                    'message' => 'Failed to update invoices'
                ];
            }
            return [
                'status' => 200,
                'message' => 'Invoice created successful. Total Amount: '.$totalAmount
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());
            return [
                'status' => 500,
                'message' => 'Exception::'
            ];
        }
    }

    public function editInvoiceItems($items, $invoiceId) {
        $base = new base();
        try {
            $checkInvoice = $base->rawSelectOneRecord("SELECT * FROM invoices "
                    . "WHERE invoice_id=:invoice_id", [":invoice_id" => $invoiceId]);
            if (!$checkInvoice) {
                return [
                    'status' => 404,
                    'message' => 'Failed to create invoice items'
                ];
            }
            $totalAmount = 0;
            $totalItems = 0;
            foreach ($items as $item) {
                $lineTotal = intval($item->quantity) * floatval($item->unit_price);
                $resultUpdate = $base->rawUpdateWithParams("UPDATE invoice_items SET "
                        . "quantity =:quantity, product_name=:product_name,"
                        . "line_total=:line_total,unit_price=:unit_price,"
                        . " product_description = :product_description,"
                        . "updated=:updated,invoice_id=:invoice_id   WHERE"
                        . " invoice_item_id=:invoice_item_id LIMIT 1",
                        [':invoice_id' => $invoiceId,
                            ':product_name' => $item->product_name,
                            ':product_description' => isset($item->product_description) ? $item->product_description : $item->product_name,
                            ':quantity' => $item->quantity,
                            ':unit_price' => $item->unit_price,
                            ':invoice_item_id' => $item->invoice_item_id,
                            ':line_total' => $lineTotal,
                            ':updated' => $base->now(),
                ]);
                if (!$resultUpdate) {
                    continue;
                }
                $totalItems = $totalItems + intval($item->quantity);
                $totalAmount = $totalAmount + $lineTotal;
            }
            $resultUpdate = $base->rawUpdateWithParams("UPDATE invoices SET "
                    . "total_amount =:totalAmount, total_items=:total_items, status = 2 WHERE"
                    . " invoice_id=:invoice_id LIMIT 1",
                    [':totalAmount' => $totalAmount, ':total_items' =>
                        $totalItems, ':invoice_id' => $invoiceId]);

            return [
                'status' => 200,
                'message' => 'Invoice items update successful'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());
            return [
                'status' => 500,
                'data' => [],
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }

    public function queryInvoice($invoiceId) {
        $base = new base();
        try {
            $checkInvoice = $base->rawSelectOneRecord("select invoices.invoice_id,invoices.reference,"
                    . "invoice_payment_type.invoice_payment_type, "
                    . "invoice_type.invoice_type, events.eventName,invoices.total_items, "
                    . "clients.client_name,clients.address, clients.msisdn,"
                    . "clients.email_address, invoices.invoice_status, "
                    . "invoices.invoice_notes, invoices.invoice_amount, "
                    . "invoices.invoice_fee, invoices.invoice_issued_date,"
                    . "invoices.invoice_due_date, invoices.created,(SELECT IFNULL(SUM(amount),0.00)"
                    . " from invoice_payments WHERE invoice_payments.invoice_id = "
                    . "invoices.invoice_id) as totalPayments from invoices join invoice_type on "
                    . "invoices.invoice_type_id = invoice_type.invoice_type_id "
                    . "join invoice_payment_type on invoices.invoice_payment_type_id"
                    . " = invoice_payment_type.invoice_payment_type_id join "
                    . "events on invoices.invoice_reference_id = events.eventID "
                    . "join clients on invoices.invoice_from_client_id = "
                    . "clients.client_id  WHERE invoices.invoice_id=:invoice_id",
                    [":invoice_id" => $invoiceId]);
            if (!$checkInvoice) {
                return [
                    'status' => 404,
                    'data' => [],
                    'message' => 'No Record Found'
                ];
            }
            return [
                'status' => 200,
                'data' => $checkInvoice,
                'message' => 'Invoice Found Successful'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());
            return [
                'status' => 500,
                'data' => [],
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }

    /**
     * createInitiatial
     * @param type $params
     * @return type
     */
    public function createInitiatialInvoicePayments($params) {
        $base = new base();
        try {
            $checkPayment = "SELECT * FROM invoice_payments WHERE "
                    . "invoice_payments.invoice_id = :invoice_id and"
                    . " invoice_billing_reference=:reference_id and "
                    . "invoice_payment_type_id=:trans_type_id";
            $resultCheck = $base->rawSelectOneRecord($checkPayment,
                    [':invoice_id' => $params['invoice_id'],
                        ':trans_type_id' => $params['trans_type_id'],
                        ':reference_id' => $params['reference_id']]);
            if ($resultCheck) {
                return [
                    'status' => 202,
                    'id' => "",
                    'message' => 'Duplicate invoice payment request'
                ];
            }
            $sqlInvoicePayment = "SELECT IFNULL(SUM(invoice_payments.invoice_amount),0) as"
                    . " totalAmount FROM invoice_payments WHERE "
                    . "invoice_payments.invoice_id = :invoice_id";
            $paramsPayInvoice = [
                ':invoice_id' => $params['invoice_id'],
            ];

            $result = $base->rawSelectOneRecord($sqlInvoicePayment, $paramsPayInvoice);
            if ($result) {
                if ($result['totalAmount'] >= $params['invoiceAmount']) {
                    return [
                        'status' => 202,
                        'id' => "",
                        'message' => 'The invoice has been settled already'
                    ];
                }
                if ($params['amount'] > (floatval($params['invoiceAmount']) - floatval($result['totalAmount']))) {
                    $invoiceBalance = floatval($params['invoiceAmount']) - floatval($result['totalAmount']);
                    return [
                        'status' => 202,
                        'id' => "",
                        'message' => "The invoice has a balance of "
                        . "KES:" . $invoiceBalance . ". "
                        . "The amount entered (KES: " . $params['amount'] . ")"
                    ];
                }
            }

            $sqlInsertInvoicePayment = "INSERT INTO invoice_payments_initial"
                    . " (invoice_id,invoice_payment_type_id,invoice_billing_reference,"
                    . "invoice_amount,invoice_balance,created)"
                    . " VALUES (:invoice_id,:trans_type_id,:reference_id,"
                    . ":amount,:balance,NOW())";

            $paramsInvoicePayments = [
                ':invoice_id' => $params['invoice_id'],
                ':trans_type_id' => $params['trans_type_id'],
                ':reference_id' => $params['reference_id'],
                ':amount' => $params['amount'],
                ':balance' => (floatval($params['invoiceAmount']) - floatval($result['totalAmount'])) - $params['amount']];

            $invoicesPaymentId = $base->rawInsert($sqlInsertInvoicePayment, $paramsInvoicePayments);

            if ($invoicesPaymentId) {
                return [
                    'status' => 200,
                    'id' => $invoicesPaymentId,
                    'message' => 'Invoice Initial Payment created successful'
                ];
            }
            return [
                'status' => 402,
                'id' => '',
                'message' => 'Failed to create Invoices Initial Payment'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return [
                'status' => 500,
                'id' => '',
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }

    /**
     * 
     * @param type $params
     * @return type
     */
    public function createInvoicePayments($params, $purpose="MPESA_INVOICE_PAYMENT") {
        $base = new base();
        try {
            $checkInvoice = $base->rawSelectOneRecord("SELECT * FROM invoices "
                    . "WHERE invoice_id=:invoice_id", [":invoice_id" => $params['invoice_id']]);
            if (!$checkInvoice) {
                return [
                    'status' => 404,
                    'message' => 'Failed to create payment. Invoice not found'
                ];
            }
            $sqlInvoicePayment = "SELECT IFNULL(SUM(invoice_payments.invoice_amount),0) as"
                    . " totalAmount FROM invoice_payments WHERE "
                    . "invoice_payments.invoice_id = :invoice_id";
            $paramsPayInvoice = [
                ':invoice_id' => $params['invoice_id'],
            ];
            $result = $base->rawSelectOneRecord($sqlInvoicePayment, $paramsPayInvoice);

            $sqlInsertInvoicePayment = "INSERT INTO client_invoice_payments"
                    . " (invoice_id,trans_type_id,reference_id,payment_receipt,"
                    . "amount,balance,payment_description,created_at)"
                    . " VALUES (:invoice_id,:trans_type_id,:reference_id,:payment_receipt,"
                    . ":amount,:balance,:payment_description,NOW())";

            $paramsInvoicePayments = [
                ':invoice_id' => $params['invoice_id'],
                ':trans_type_id' => $params['trans_type_id'],
                ':reference_id' => $params['reference_id'],
                ':payment_receipt' => $params['payment_receipt'],
                ':amount' => $params['amount'],
                ':payment_description'=>$purpose,
                ':balance' => (floatval($checkInvoice['total_amount']) - floatval($result['totalAmount'])) - $params['amount']];

            $invoicesPaymentId = $base->rawInsert($sqlInsertInvoicePayment, $paramsInvoicePayments);

            if (!$invoicesPaymentId) {
                return [
                    'status' => 402,
                    'id' => '',
                    'message' => 'Failed to create Invoices Payment'
                ];
            }

            $resultNew = $base->rawSelectOneRecord($sqlInvoicePayment, $paramsPayInvoice);
            $status = 3;
            if ($resultNew['totalAmount'] >= $checkInvoice['total_amount']) {
                $status = 1;
            }

            $base->rawUpdateWithParams("UPDATE client_invoices SET "
                    . " status = :status WHERE"
                    . " invoice_id=:invoice_id LIMIT 1",
                    [':invoice_id' => $params['invoice_id'], ':status' => $status]);

            return [
                'status' => 200,
                'id' => $invoicesPaymentId,
                'message' => 'Invoice Payment created successful'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());

            return [
                'status' => 500,
                'id' => '',
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }

    /**
     * @param type $params
     * @return type
     */
    public function UpdateInvoiceStatus($params) {
        $base = new base();
        try {
            $checkInvoice = $base->rawSelectOneRecord("SELECT * FROM client_invoices "
                    . "WHERE invoice_id=:invoice_id", [":invoice_id" => $params['invoice_id']]);
            if (!$checkInvoice) {
                return [
                    'status' => 404,
                    'message' => 'Failed to create invoice items'
                ];
            }

            $sqlQuery = "UPDATE client_invoices set status=:status "
                    . " WHERE invoice_id=:invoice_id LIMIT 1";

            $paramsIvoices = [
                ":invoice_id" => $params['invoice_id'],
                ':status' => $params['status'],];

            $base->rawUpdateWithParams($sqlQuery, $paramsIvoices);

            return [
                'status' => 200,
                'invoice_id' => $params['invoice_id'],
                'message' => 'Invoice status updated successful'
            ];
        } catch (Exception $ex) {
            $base->getLogFile('error')->emergency(__LINE__ . ":" . __CLASS__
                    . " | Exception::" . $ex->getMessage());
            return [
                'status' => 500,
                'message' => 'Exception::' . $ex->getMessage()
            ];
        }
    }
}
