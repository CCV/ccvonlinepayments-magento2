<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\Exception\ApiException;
use CCVOnlinePayments\Lib\OrderLine;
use CCVOnlinePayments\Lib\PaymentRequest;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Api\Data\StoreInterface;

class Redirect extends Action {

    private $session;
    private $orderFactory;
    private $config;
    private $store;
    private $localeResolver;
    private $orderPaymentRepository;
    private $orderRepository;
    private $countryInformation;
    private $ccvOnlinePaymentsService;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        ScopeConfigInterface $config,
        StoreInterface $store,
        ResolverInterface $localeResolver,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepostiory,
        CountryInformationAcquirerInterface $countryInformation,
        CcvOnlinePaymentsService $ccvOnlinePaymentsService
    )
    {
        parent::__construct($context);
        $this->session                  = $checkoutSession;
        $this->orderFactory             = $orderFactory;
        $this->config                   = $config;
        $this->store                    = $store;
        $this->localeResolver           = $localeResolver;
        $this->orderPaymentRepository   = $orderPaymentRepository;
        $this->orderRepository          = $orderRepostiory;
        $this->countryInformation       = $countryInformation;
        $this->ccvOnlinePaymentsService = $ccvOnlinePaymentsService;
    }

    protected function getOrder() {
        $orderId = $this->session->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    public function execute()
    {
        $order = $this->getOrder();

        if ($order->getState() === Order::STATE_PENDING_PAYMENT || $order->getState() === Order::STATE_NEW) {
            $this->doPayment($order);
        }elseif($order->getState() === Order::STATE_CANCELED) {
            $this->_redirect('checkout/cart');
        }else{
            $this->_redirect('checkout/cart');
        }
    }

    private function doPayment(Order $order) {
        $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();

        $payment = $order->getPayment();
        $methodId = str_replace("ccvonlinepayments_","",$payment->getMethod());
        $method = $ccvOnlinePaymentsApi->getMethodById($methodId);

        $paymentRequest = new PaymentRequest();
        $paymentRequest->setCurrency($order->getOrderCurrencyCode());
        $paymentRequest->setAmount($payment->getAmountOrdered());

        if($method->isTransactionTypeAuthoriseSupported()) {
            $paymentRequest->setTransactionType(PaymentRequest::TRANSACTION_TYPE_AUTHORIZE);
        }else{
            $paymentRequest->setTransactionType(PaymentRequest::TRANSACTION_TYPE_SALE);
        }

        $paymentRequest->setReturnUrl($this->_url->getUrl('ccvonlinepayments/checkout/paymentreturn/',["order_id" => $order->getIncrementId(), "payment_id" => $payment->getId()]));
        $paymentRequest->setWebhookUrl($this->_url->getUrl('ccvonlinepayments/checkout/webhook/',["order_id" => $order->getIncrementId(), "payment_id" => $payment->getId()]));
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
        $paymentRequest->setBillingAddress($order->getBillingAddress()->getStreetLine(1));
        $paymentRequest->setBillingCity($order->getBillingAddress()->getCity());
        $paymentRequest->setBillingPostalCode($order->getBillingAddress()->getPostcode());
        $paymentRequest->setBillingCountry($billingCountryCode);
        $paymentRequest->setBillingState($order->getBillingAddress()->getRegionCode());
        $paymentRequest->setBillingPhoneNumber($order->getBillingAddress()->getTelephone());

        $shippingCountryCode = $this->countryInformation->getCountryInfo($order->getShippingAddress()->getCountryId())->getTwoLetterAbbreviation();
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

        try {
            $paymentResponse = $ccvOnlinePaymentsApi->createPayment($paymentRequest);
        }catch(ApiException $apiException) {
            $this->_redirect($this->_url->getUrl('ccvonlinepayments/checkout/paymentreturn/',["order_id" => $order->getIncrementId(), "payment_id" => $payment->getId()]));
            return;
        }

        $payment->setCcvonlinepaymentsReference($paymentResponse->getReference());
        $this->orderPaymentRepository->save($payment);

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $this->orderRepository->save($order);

        $this->_redirect($paymentResponse->getPayUrl());
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
