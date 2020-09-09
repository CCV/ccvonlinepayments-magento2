<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;

class CcvOnlinePaymentsConfigProvider implements ConfigProviderInterface
{
    private $config;
    private $ccvOnlinePaymentsService;
    private $assetRepository;

    public function __construct(ScopeConfigInterface $config, CcvOnlinePaymentsService $ccvOnlinePaymentsService, Repository $assetRepository)
    {
        $this->config                   = $config;
        $this->ccvOnlinePaymentsService = $ccvOnlinePaymentsService;
        $this->assetRepository          = $assetRepository;
    }

   public function getConfig() {
        $config = [
            "issuers"    => [],
            "issuerKeys" => [],
            "icons"      => [],
        ];

        $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();
        foreach($ccvOnlinePaymentsApi->getMethods() as $method) {
            $methodId = "ccvonlinepayments_".$method->getId();

            if($method->getIssuers() !== null) {
                $config['issuerKeys'][$methodId] = $method->getIssuerKey();
                $config['issuers'][$methodId] = [];
                foreach ($method->getIssuers() as $issuer) {
                    $config['issuers'][$methodId][] = [
                        "id" => $issuer->getId(),
                        "description" => $issuer->getDescription(),
                    ];
                }
            }

            if(file_exists(__DIR__."/../view/base/web/images/methods/".$method->getId().".png")) {
                $config['icons'][$methodId] = $this->assetRepository->getUrl('CCVOnlinePayments_Magento::images/methods/'.$method->getId().".png");
            }
        }

        return $config;
   }

}
