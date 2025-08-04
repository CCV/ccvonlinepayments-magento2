<?php namespace CCVOnlinePayments\Magento\Method;

use Magento\Customer\Model\Customer;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class PayByLink extends Method {

    protected string $_code = 'ccvonlinepayments_paybylink';

    /**
     * @param Quote|null $quote
     */
    public function isAvailable(?CartInterface $quote = null) : bool
    {
        if($quote !== null && parent::isAvailable($quote)) {
            if($this->state->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML) {
                return true;
            }else{
                /** @var Customer $customer */
                $customer = $quote->getCustomer();
                $activeGroups = $this->config->getValue("payment/ccvonlinepayments/ccvonlinepayments_paybylink/customerGroupList");
                if($activeGroups !== null) {
                    $activeGroupIds = explode(",",$activeGroups);
                    if($customer->getId() === null) { // Not logged in
                        return in_array("0", $activeGroupIds);
                    }elseif(in_array(\Magento\Customer\Api\Data\GroupInterface::CUST_GROUP_ALL,$activeGroupIds)) {
                        return true;
                    }else {
                        return in_array($customer->getGroupId(), $activeGroupIds);
                    }
                }
            }
        }

        return false;
    }

    public function canUseInternal() {
        return true;
    }

    public function canUseCheckout() {
        return true;
    }
}
