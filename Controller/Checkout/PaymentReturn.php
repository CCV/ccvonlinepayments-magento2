<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\Enum\PaymentStatus;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class PaymentReturn extends Action {

    use AbstractStatusHandlerTrait, RedirectTrait;

    public function execute() : ResultInterface
    {
        $paymentStatus = $this->handlePaymentStatus();

        if($paymentStatus === null) {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(__("There was an unexpected error processing your payment"));
            return $this->redirect('checkout/cart');
        }elseif($paymentStatus->getStatus() === PaymentStatus::SUCCESS) {
            $this->checkoutSession->start();
            $this->messageManager->addSuccessMessage(__('Your payment was successful.'));
            return $this->redirect('checkout/onepage/success');
        }elseif($paymentStatus->getStatus() === PaymentStatus::PENDING || $paymentStatus->getStatus() === PaymentStatus::MANUAL_INTERVENTION) {
            $this->checkoutSession->start();
            $this->messageManager->addWarningMessage(__('Your payment is still pending.'));
            return $this->redirect('checkout/onepage/success');
        }elseif($paymentStatus->getStatus() === PaymentStatus::FAILED) {
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(__('There was an error processing the payment.'));
            return $this->redirect('checkout/cart');
        }

        throw new \Exception("Unexpected payment status");
    }
}
