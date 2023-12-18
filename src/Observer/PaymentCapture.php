<?php

namespace CCVOnlinePayments\Magento\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 * Generate item list for payment capture
 */
class PaymentCapture implements ObserverInterface
{

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $payment = $observer->getPayment();

        if (strpos($payment->getMethod(), 'ccvonlinepayments_') === false) {
            return;
        }

        $payment->setInvoice($observer->getInvoice());
    }
}
