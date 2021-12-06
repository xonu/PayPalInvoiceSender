<?php

/**
* @copyright Copyright (c) 2013 Pawel Kazakow (http://xonu.de)
*/

?>

<?php

class Xonu_PayPalInvoiceSender_Model_Ipn extends Mage_Paypal_Model_Ipn
{

      /**
     * Process completed payment (either full or partial)
     */
    protected function _registerPaymentCapture($skipFraudDetection = false)
    {
        if ($this->getRequestData('transaction_entity') == 'auth') {
            return;
        }
        $parentTransactionId = $this->getRequestData('parent_txn_id');
        $this->_importPaymentInformation();
        $payment = $this->_order->getPayment();
        $payment->setTransactionId($this->getRequestData('txn_id'))
            ->setCurrencyCode($this->getRequestData('mc_currency'))
            ->setPreparedMessage($this->_createIpnComment(''))
            ->setParentTransactionId($parentTransactionId)
            ->setShouldCloseParentTransaction('Completed' === $this->getRequestData('auth_status'))
            ->setIsTransactionClosed(0)
            ->registerCaptureNotification(
                $this->getRequestData('mc_gross'),
                $skipFraudDetection && $parentTransactionId
            );
        $this->_order->save();

        // notify customer
        if ($invoice = $payment->getCreatedInvoice()) {

            $comment = $this->_order->sendNewOrderEmail()->addStatusHistoryComment(
                    Mage::helper('paypal')->__('Notified customer about invoice #%s.', $invoice->getIncrementId())
                )
                ->setIsCustomerNotified(true)
                ->save();

            $this->_sendInvoiceToCustomer( $this->_order );

        }
    }

    /*
     *  Send Invoice
     */
    protected function _sendInvoiceToCustomer (Mage_Sales_Model_Order $order)
    {

        // get send invoice config
        $sendInvoiceFlag = Mage::getStoreConfig("paypalinvoicesender/general/send_invoice");
        if ( $sendInvoiceFlag != 1 ) return false;

        if ( $order->hasInvoices() )
        {
            foreach ( $order->getInvoiceCollection() as $invoice )
            {
                $invoice->setEmailSent(true);
                try {
                     $invoice->sendEmail(true);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }
    }

}
