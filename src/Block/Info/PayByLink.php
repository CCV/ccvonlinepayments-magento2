<?php namespace CCVOnlinePayments\Magento\Block\Info;

use Magento\Payment\Block\Info;

class PayByLink extends Info {

    protected $_template = 'CCVOnlinePayments_Magento::info/paybylink.phtml';

    public function getPaymentLink() {
        return $this->getInfo()->getAdditionalInformation("ccvPayUrl");
    }
}
