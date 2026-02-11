<?php namespace CCVOnlinePayments\Magento\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateIdealPaymentMethodTitle implements DataPatchInterface
{
    private $configWriter;
    private $scopeConfig;

    public function __construct(
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
    }

    public function apply()
    {
        $currentTitle = $this->scopeConfig->getValue(
            'payment/ccvonlinepayments_ideal/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($currentTitle === 'iDeal') {
            $this->configWriter->save(
                'payment/ccvonlinepayments_ideal/title',
                'iDEAL | Wero',
                $scope = 'default',
                $scopeId = 0
            );
        }
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
