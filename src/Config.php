<?php namespace CCVOnlinePayments\Magento;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ?string $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);

    }

    public function getValue($field, $storeId = null)
    {
        if($field === "sort_order") {
            $defaultCountry = $this->scopeConfig->getValue("general/country/default");
            $isBelgium = $defaultCountry === "BE";

            if($isBelgium) {
                if($this->methodCode === "ccvonlinepayments_ideal") {
                    return 1;
                }elseif($this->methodCode === "ccvonlinepayments_card_bcmc") {
                    return 0;
                }
            }
        }


        $value = parent::getValue($field, $storeId);

        if($value === null) {
            $value = $this->scopeConfig->getValue(
                sprintf("payment/%s/%s", $this->methodCode, $field),
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return $value;
    }

}
