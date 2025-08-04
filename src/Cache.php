<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Cache extends \CCVOnlinePayments\Lib\Cache
{
        public function __construct(
        private readonly \Magento\Framework\App\CacheInterface $cache)
    {

    }

    public function set(string $key, string $value, int $timeout): void
    {
        $this->cache->save($value, $key, [], $timeout);
    }

    public function get(string $key): ?string
    {
        /** @var false|string $value */
        $value = $this->cache->load($key);
        if($value === false) {
            return null;
        }else {
            return $value;
        }
    }


}
