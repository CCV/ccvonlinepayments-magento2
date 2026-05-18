<?php namespace CCVOnlinePayments\Magento\Model\Config\Backend;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use CCVOnlinePayments\Magento\Cache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class ApiKey extends Value
{
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $apiKey = $this->getValue();

        if (empty($apiKey)) {
            return parent::beforeSave();
        }

        $api = new CcvOnlinePaymentsApi(new Cache($this->cache), $this->logger, $apiKey);
        try {
            if (!$api->isKeyValid()) {
                throw new LocalizedException(__('The API Key is invalid.'));
            }
        } catch (\CCVOnlinePayments\Lib\Exception\ApiException $apiException) {
            if ($apiException->getHttpStatusCode() === 403) {
                throw new LocalizedException(__('Your ip has been blocked. Please contact CCV.'));
            } else {
                throw new LocalizedException(__(
                    'An error (Http %1 error) has occurred. Please try again later or contact CCV.',
                    $apiException->getHttpStatusCode()
                ));
            }
        }

        return parent::beforeSave();
    }
}
