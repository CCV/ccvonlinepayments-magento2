<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use CCVOnlinePayments\Lib\Method;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Sales\Model\Order;
use CCVOnlinePayments\Lib\OrderLine;
use CCVOnlinePayments\Lib\PaymentRequest;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Directory\Api\CountryInformationAcquirerInterface;

class CcvOnlinePaymentsService
{

    private const VERSION = "1.6.2";

    private $config;
    private $cache;
    private $logger;
    private $moduleList;
    private $urlInterface;
    private $localeResolver;
    private $countryInformation;
    private $orderPaymentRepository;
    private $orderRepository;

    private $api = null;

    public function __construct(
        ScopeConfigInterface $config,
        \Magento\Framework\App\CacheInterface $cache,
        \Psr\Log\LoggerInterface $logger,
        ModuleListInterface $moduleList,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepostiory,
        ResolverInterface $localeResolver,
        CountryInformationAcquirerInterface $countryInformation,
        \Magento\Framework\Url $urlInterface
    )
    {
        $this->config                   = $config;
        $this->cache                    = $cache;
        $this->logger                   = $logger;
        $this->moduleList               = $moduleList;
        $this->localeResolver           = $localeResolver;
        $this->countryInformation       = $countryInformation;
        $this->orderPaymentRepository   = $orderPaymentRepository;
        $this->orderRepository          = $orderRepostiory;
        $this->urlInterface             = $urlInterface;
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
        if($id === "ccvonlinepayments_paybylink") {
            $id = "ccvonlinepayments_landingpage";
        }

        foreach($this->getApi()->getMethods() as $method) {
            if("ccvonlinepayments_".$method->getId() === $id) {
                return $method;
            }
        }

        return null;
    }

    public function startTransaction(Order $order, array $metadata) {
        $ccvOnlinePaymentsApi = $this->getApi();

        $ccvOnlinePaymentsApi->addMetadata($metadata);

        $payment = $order->getPayment();
        $methodId = str_replace("ccvonlinepayments_","",$payment->getMethod());
        if($methodId === "paybylink") {
            $methodId = "landingpage";
        }

        $method = $ccvOnlinePaymentsApi->getMethodById($methodId);

        $paymentRequest = new PaymentRequest();
        $paymentRequest->setCurrency($order->getOrderCurrencyCode());
        $paymentRequest->setAmount($payment->getAmountOrdered());

        if($payment->getMethod() === "ccvonlinepayments_paybylink") {
            $paymentRequest->addDetail("allowCustomerCancel", false);

            $expirationDuration = $this->config->getValue("payment/ccvonlinepayments/ccvonlinepayments_paybylink/expiration");

            if($expirationDuration !== null && $expirationDuration > 0 && $expirationDuration < 61) {
                $paymentRequest->addDetail("expirationDuration", "P".$expirationDuration."D");
            }
        }

        if($method->isTransactionTypeAuthoriseSupported()) {
            $paymentRequest->setTransactionType(PaymentRequest::TRANSACTION_TYPE_AUTHORIZE);
        }else{
            $paymentRequest->setTransactionType(PaymentRequest::TRANSACTION_TYPE_SALE);
        }

        $paymentRequest->setReturnUrl($this->urlInterface->getUrl('ccvonlinepayments/checkout/paymentreturn/',["_scope" => $order->getStoreId(),"_scope_to_url" => true,"order_id" => $order->getIncrementId(), "payment_id" => $payment->getId()]));
        $paymentRequest->setWebhookUrl($this->urlInterface->getUrl('ccvonlinepayments/checkout/webhook/',["_scope" => $order->getStoreId(), "_scope_to_url" => true, "order_id" => $order->getIncrementId(), "payment_id" => $payment->getId()]));
        $paymentRequest->setMethod($methodId);
        $paymentRequest->setMerchantOrderReference($order->getId());

        $language = "eng";
        switch(explode("_", $this->localeResolver->getLocale())[0]) {
            case "nl":  $language = "nld"; break;
            case "de":  $language = "deu"; break;
            case "fr":  $language = "fra"; break;
        }
        $paymentRequest->setLanguage($language);

        $additionalInformation = $payment->getAdditionalInformation();

        if(isset($additionalInformation['issuerKey']) && $additionalInformation['issuerKey']=="issuerid") {
            $paymentRequest->setIssuer($additionalInformation['selectedIssuer'] ?? null);
        }elseif(isset($additionalInformation['issuerKey']) && $additionalInformation['issuerKey']=="brand") {
            $paymentRequest->setBrand($additionalInformation['selectedIssuer'] ?? null);
        }

        $paymentRequest->setScaReady(false);

        $billingCountryCode = $this->countryInformation->getCountryInfo($order->getBillingAddress()->getCountryId())->getTwoLetterAbbreviation();
        $paymentRequest->setBillingEmail($order->getBillingAddress()->getEmail());
        $paymentRequest->setBillingFirstName($order->getBillingAddress()->getFirstName());
        $paymentRequest->setBillingLastName($order->getBillingAddress()->getLastName());
        $paymentRequest->setBillingAddress($order->getBillingAddress()->getStreetLine(1));
        $paymentRequest->setBillingCity($order->getBillingAddress()->getCity());
        $paymentRequest->setBillingPostalCode($order->getBillingAddress()->getPostcode());
        $paymentRequest->setBillingCountry($billingCountryCode);
        $paymentRequest->setBillingState($order->getBillingAddress()->getRegionCode());
        $paymentRequest->setBillingPhoneNumber($order->getBillingAddress()->getTelephone());

        $shippingCountryCode = $this->countryInformation->getCountryInfo($order->getShippingAddress()->getCountryId())->getTwoLetterAbbreviation();
        $paymentRequest->setShippingEmail($order->getShippingAddress()->getEmail());
        $paymentRequest->setShippingFirstName($order->getShippingAddress()->getFirstName());
        $paymentRequest->setShippingLastName($order->getShippingAddress()->getLastName());
        $paymentRequest->setShippingAddress($order->getShippingAddress()->getStreetLine(1));
        $paymentRequest->setShippingCity($order->getShippingAddress()->getCity());
        $paymentRequest->setShippingPostalCode($order->getShippingAddress()->getPostcode());
        $paymentRequest->setShippingCountry($shippingCountryCode);
        $paymentRequest->setShippingState($order->getShippingAddress()->getRegionCode());
        $paymentRequest->setMerchantRiskIndicatorDeliveryEmailAddress($order->getShippingAddress()->getEmail());

        if($order->getCustomer() !== null) {
            $paymentRequest->setAccountInfoAccountIdentifier($order->getCustomer()->getId());
            $paymentRequest->setAccountInfoAccountCreationDate((new \DateTime())->setTimeStamp($order->getCustomer()->getCreatedAtTimestamp()));
            $paymentRequest->setAccountInfoEmail($order->getCustomer()->getEmail());
        }

        $paymentRequest->setBrowserFromServer();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $remote = $objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        $paymentRequest->setBrowserIpAddress($remote->getRemoteAddress());

        if($method->isOrderLinesRequired()) {
            $paymentRequest->setOrderLines($this->getOrderlinesByOrder($order));
        }


        $paymentResponse = $ccvOnlinePaymentsApi->createPayment($paymentRequest);

        $payment->setCcvonlinepaymentsReference($paymentResponse->getReference());
        $this->orderPaymentRepository->save($payment);

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $this->orderRepository->save($order);

        return $paymentResponse;
    }

    private function getOrderlinesByOrder(Order $order) {
        $orderLines = [];

        /** @var OrderItemInterface $visibleItem */
        foreach($order->getAllVisibleItems() as $visibleItem) {
            $orderLine = new OrderLine();
            $orderLine->setType(OrderLine::TYPE_PHYSICAL);
            $orderLine->setName($visibleItem->getName());
            $orderLine->setQuantity(round($visibleItem->getQtyOrdered()));
            $orderLine->setTotalPrice($visibleItem->getRowTotal() - $visibleItem->getDiscountAmount() + $visibleItem->getTaxAmount() + $visibleItem->getDiscountTaxCompensationAmount());
            $orderLine->setUnit("pcs");
            $orderLine->setUnitPrice($orderLine->getTotalPrice()/$orderLine->getQuantity());
            $orderLine->setVatRate($visibleItem->getTaxPercent());
            $orderLine->setVat($visibleItem->getTaxAmount());
            $orderLines[] = $orderLine;
        }

        $totalShippingAmount = $order->getShippingAmount() + $order->getShippingTaxAmount() + $order->getShippingDiscountTaxCompensationAmount();
        if($totalShippingAmount > 0) {
            $orderLine = new \CCVOnlinePayments\Lib\OrderLine();
            $orderLine->setType(\CCVOnlinePayments\Lib\OrderLine::TYPE_SHIPPING_FEE);
            $orderLine->setName("Shipping");
            $orderLine->setQuantity(1);
            $orderLine->setTotalPrice($totalShippingAmount);
            $orderLine->setVat($order->getShippingTaxAmount());
            $orderLine->setVatRate(($order->getShippingTaxAmount() / $order->getShippingAmount()) * 100);
            $orderLine->setUnitPrice($totalShippingAmount);
            $orderLines[] = $orderLine;
        }

        return $orderLines;
    }
}
