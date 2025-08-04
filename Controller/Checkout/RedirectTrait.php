<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use Magento\Framework\Controller\ResultFactory;

trait RedirectTrait {

    protected function redirect(string $path) : \Magento\Framework\Controller\Result\Redirect {
        /** @var \Magento\Framework\Controller\Result\Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $result->setPath($path);
        return $result;
    }

}
