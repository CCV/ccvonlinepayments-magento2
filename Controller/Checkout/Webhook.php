<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Webhook extends Action implements HttpPostActionInterface, CsrfAwareActionInterface {

    use AbstractStatusHandlerTrait;

    public function execute()
    {
        $this->handlePaymentStatus();

        return $this->rawFactory->setContents("OK");
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
