<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;

class CcvOnlinePaymentsConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly CcvOnlinePaymentsService $ccvOnlinePaymentsService,
        private readonly Repository $assetRepository)
    {

    }

    /**
     * @return array{'payment': mixed}
     */
   public function getConfig() : array {
        $config = [
            "payment" => []
        ];

       $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();
       foreach($ccvOnlinePaymentsApi->getMethods() as $method) {
           $methodId = "ccvonlinepayments_".$method->getId();

           if(file_exists(__DIR__."/../view/base/web/images/methods/".$method->getId().".png")) {
               $config['payment'][$methodId]['icon'] = $this->assetRepository->getUrl('CCVOnlinePayments_Magento::images/methods/'.$method->getId().".png");
           }

           if($method->getId() !== "ideal" && $method->getIssuers() !== null) {
               $config['payment'][$methodId]['issuerKey'] = $method->getIssuerKey();
               $config['payment'][$methodId]['issuers'] = [];
               foreach ($method->getIssuers() as $issuer) {
                   $config['payment'][$methodId]['issuers'][] = [
                       "id" => $issuer->getId(),
                       "description" => $issuer->getDescription(),
                   ];
               }
           }
       }

        return $config;
   }

}
