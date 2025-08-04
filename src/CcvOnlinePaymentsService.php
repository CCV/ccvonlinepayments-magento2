<?php namespace CCVOnlinePayments\Magento;

use CCVOnlinePayments\Lib\CcvOnlinePaymentsApi;
use CCVOnlinePayments\Lib\Enum\OrderLineType;
use CCVOnlinePayments\Lib\Enum\TransactionType;
use CCVOnlinePayments\Lib\Method;
use CCVOnlinePayments\Lib\PaymentResponse;
use Magento\Customer\Model\Address\AbstractAddress;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use CCVOnlinePayments\Lib\OrderLine;
use CCVOnlinePayments\Lib\PaymentRequest;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Directory\Api\CountryInformationAcquirerInterface;

class CcvOnlinePaymentsService
{
    private const VERSION = "1.8.0";

    private ?CcvOnlinePaymentsApi $api = null;

    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly \Magento\Framework\App\CacheInterface $cache,
        private readonly \Psr\Log\LoggerInterface $logger,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ResolverInterface $localeResolver,
        private readonly CountryInformationAcquirerInterface $countryInformation,
        private readonly \Magento\Framework\Url $urlInterface
    )
    {

    }


    public function getApi() : CcvOnlinePaymentsApi {
        if($this->api === null) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

            $apiKey = $this->config->getValue("payment/ccvonlinepayments/ccvonlinepayments_general/api_key");

            $this->api = new CcvOnlinePaymentsApi(new Cache($this->cache), $this->logger, $apiKey);
            $this->api->setMetadata([
                "CCVOnlinePayments" => self::VERSION,
                "Magento"           => strval($productMetadata?->getVersion())
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

    /**
     * @param array<string,string> $metadata
     */
    public function startTransaction(Order $order, array $metadata) : PaymentResponse {
        $ccvOnlinePaymentsApi = $this->getApi();

        $ccvOnlinePaymentsApi->addMetadata($metadata);

        /** @var Order\Payment $payment */
        $payment = $order->getPayment();
        $methodId = str_replace("ccvonlinepayments_","",$payment->getMethod());
        if($methodId === "paybylink") {
            $methodId = "landingpage";
        }

        $method = $ccvOnlinePaymentsApi->getMethodById($methodId);
        if($method === null) {
            throw new \Exception("Method not found");
        }

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
            $paymentRequest->setTransactionType(TransactionType::AUTHORIZE);
        }else{
            $paymentRequest->setTransactionType(TransactionType::SALE);
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

        /** @var ?AbstractAddress $billingAddress */
        $billingAddress = $order->getBillingAddress();
        if($billingAddress === null) {
            throw new \Exception("Billing address not found");
        }

        $billingCountryCode = $this->countryInformation->getCountryInfo($billingAddress->getCountryId())->getTwoLetterAbbreviation();
        $paymentRequest->setBillingEmail($billingAddress->getEmail());
        $paymentRequest->setBillingFirstName($billingAddress->getFirstName());
        $paymentRequest->setBillingLastName($billingAddress->getLastName());
        $paymentRequest->setBillingAddress($billingAddress->getStreetLine(1));
        $paymentRequest->setBillingCity($billingAddress->getCity());
        $paymentRequest->setBillingPostalCode($billingAddress->getPostcode());
        $paymentRequest->setBillingCountry($billingCountryCode);
        $paymentRequest->setBillingState($billingAddress->getRegionCode());
        $paymentRequest->setBillingPhoneNumber($billingAddress->getTelephone());

        /** @var ?AbstractAddress $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        if($shippingAddress === null) {
            throw new \Exception("Shipping address not found");
        }

        $shippingCountryCode = $this->countryInformation->getCountryInfo($shippingAddress->getCountryId())->getTwoLetterAbbreviation();
        $paymentRequest->setShippingEmail($shippingAddress->getEmail());
        $paymentRequest->setShippingFirstName($shippingAddress->getFirstName());
        $paymentRequest->setShippingLastName($shippingAddress->getLastName());
        $paymentRequest->setShippingAddress($shippingAddress->getStreetLine(1));
        $paymentRequest->setShippingCity($shippingAddress->getCity());
        $paymentRequest->setShippingPostalCode($shippingAddress->getPostcode());
        $paymentRequest->setShippingCountry($shippingCountryCode);
        $paymentRequest->setShippingState($shippingAddress->getRegionCode());
        $paymentRequest->setMerchantRiskIndicatorDeliveryEmailAddress($shippingAddress->getEmail());

        if($order->getCustomer() !== null) {
            $paymentRequest->setAccountInfoAccountIdentifier($order->getCustomer()->getId());
            $paymentRequest->setAccountInfoAccountCreationDate((new \DateTimeImmutable())->setTimeStamp($order->getCustomer()->getCreatedAtTimestamp()));
            $paymentRequest->setAccountInfoEmail($order->getCustomer()->getEmail());
        }

        $paymentRequest->setBrowserFromServer();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remote */
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

    /**
     * @param Order $order
     * @return array<OrderLine>
     */
    private function getOrderlinesByOrder(Order $order) : array {
        $orderLines = [];

        /** @var OrderItemInterface $visibleItem */
        foreach($order->getAllVisibleItems() as $visibleItem) {
            $orderLine = new OrderLine();
            $orderLine->setType(OrderLineType::PHYSICAL);
            $orderLine->setName($visibleItem->getName());
            $orderLine->setQuantity(intval(round($visibleItem->getQtyOrdered()??1)));
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
            $orderLine->setType(OrderLineType::SHIPPING_FEE);
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
