<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use CCVOnlinePayments\Lib\Method;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Module\ModuleListInterface;

class CcvOnlinePaymentsService
{

    private const VERSION = "1.3.8";

    private $config;
    private $cache;
    private $logger;
    private $moduleList;

    private $api = null;

    public function __construct(
        ScopeConfigInterface $config,
        \Magento\Framework\App\CacheInterface $cache,
        \Psr\Log\LoggerInterface $logger,
        ModuleListInterface $moduleList
    )
    {
        $this->config       = $config;
        $this->cache        = $cache;
        $this->logger       = $logger;
        $this->moduleList   = $moduleList;
    }


    public function getApi() : CcvOnlinePaymentsApi {
        if($this->api === null) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

            $apiKey = $this->config->getValue("payment/ccvonlinepayments/ccvonlinepayments_general/api_key");

            $this->api = new CcvOnlinePaymentsApi(new Cache($this->cache), $this->logger, $apiKey);
            $this->api->setMetadata([
                "CCVOnlinePayments" => self::VERSION,
                "Magento"           => $productMetadata->getVersion()
            ]);
        }

        return $this->api;
    }

    public function getMethodById(string $id) : ?Method {
        foreach($this->getApi()->getMethods() as $method) {
            if("ccvonlinepayments_".$method->getId() === $id) {
                return $method;
            }
        }

        return null;
    }
}
