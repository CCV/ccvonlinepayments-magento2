<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\PaymentStatus;
use Magento\Framework\App\Action\Action;

class PaymentReturn extends Action {

    use AbstractStatusHandlerTrait;

    public function execute()
    {
        $paymentStatus = $this->handlePaymentStatus();

        if($paymentStatus === null) {
            $this->session->restoreQuote();
            $this->messageManager->addErrorMessage(__("There was an unexpected error processing your payment"));
            $this->_redirect('checkout/cart');
        }elseif($paymentStatus->getStatus() === PaymentStatus::STATUS_SUCCESS) {
            $this->session->start();
            $this->_redirect('checkout/onepage/success');
        }elseif($paymentStatus->getStatus() === PaymentStatus::STATUS_PENDING) {
            $this->session->start();
            $this->messageManager->addWarningMessage(__('Your payment is still pending.'));
            $this->_redirect('checkout/onepage/success');
        }elseif($paymentStatus->getStatus() === PaymentStatus::STATUS_FAILED) {
            $this->session->restoreQuote();
            $this->messageManager->addErrorMessage(__('There was an error processing the payment.'));
            $this->_redirect('checkout/cart');
        }
    }
}
