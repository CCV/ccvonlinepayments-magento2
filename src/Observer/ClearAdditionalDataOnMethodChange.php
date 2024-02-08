<?php
namespace CCVOnlinePayments\Magento\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class ClearAdditionalDataOnMethodChange implements ObserverInterface {

    public function execute(Observer $observer) {
        $payment = $observer->getData('payment');
        $oldPaymentMethod = $payment->getMethod();
        $newPaymentMethod = $observer->getData('input')->getData('method');

        if($oldPaymentMethod !== $newPaymentMethod) {
            $payment->unsAdditionalInformation("issuerKey");
            $payment->unsAdditionalInformation("selectedIssuer");
        }
    }
}
