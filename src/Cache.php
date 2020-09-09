<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Cache extends \CCVOnlinePayments\Lib\Cache
{

    private $cache;

    public function __construct(\Magento\Framework\App\CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function set(string $key, string $value, int $timeout): void
    {
        $this->cache->save($value, $key, [], $timeout);
    }

    public function get(string $key): ?string
    {
        $value = $this->cache->load($key);
        if($value === false) {
            return null;
        }else {
            return $value;
        }
    }


}
