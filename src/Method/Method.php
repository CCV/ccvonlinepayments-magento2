<?php namespace CCVOnlinePayments\Magento\Method;

use CCVOnlinePayments\Lib\CaptureRequest;
use CCVOnlinePayments\Lib\Exception\ApiException;
use CCVOnlinePayments\Lib\OrderLine;
use CCVOnlinePayments\Lib\RefundRequest;
use CCVOnlinePayments\Lib\ReversalRequest;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use \Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Relation\Refund;
use Psr\Log\LoggerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Method extends \Magento\Payment\Model\Method\Adapter {

    public $_canCapture = true;
    public $_canAuthorize = true;
    public $_canRefund = true;
    public $_canRefundInvoicePartial = true;

    public $_isInitializeNeeded = true;

    private $checkoutSession;
    private $ccvOnlinePaymentsService;

    private $orderPaymentRepository;
    private $invoiceRepository;

    protected $state;
    protected $config;

    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        \Magento\Checkout\Model\Session $checkoutSession,
        CcvOnlinePaymentsService $ccvOnlinePaymentsService,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Framework\App\State $state,
        ScopeConfigInterface $config,
        LoggerInterface $logger = null)
    {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $commandPool, $validatorPool, $commandExecutor, $logger);
        $this->checkoutSession          = $checkoutSession;
        $this->ccvOnlinePaymentsService = $ccvOnlinePaymentsService;
        $this->orderPaymentRepository   = $orderPaymentRepository;
        $this->invoiceRepository        = $invoiceRepository;
        $this->config                   = $config;
        $this->state                    = $state;
    }

    public function isAvailable(CartInterface $quote = null)
    {
        if(parent::isAvailable($quote)) {
            $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
            if($method !== null) {
                return $method->isCurrencySupported($quote->getCurrency()->getQuoteCurrencyCode());
            }
        }

        return false;
    }

    public function canCapture()
    {
        return true;
    }

    public function canCapturePartial()
    {
        return true;
    }

    public function capture(InfoInterface $payment, $amount)
    {
        $captureRequest = new CaptureRequest();
        $captureRequest->setReference($payment->getCcvonlinepaymentsReference());
        $captureRequest->setAmount($amount);

        /** @var InvoiceInterface $invoice */
        $invoice = $payment->getInvoice();
        $captureRequest->setOrderLines($this->getOrderlinesByInvoice($invoice));

        $captureResponse = $this->ccvOnlinePaymentsService->getApi()->createCapture($captureRequest);
        $payment->setTransactionId($captureResponse->getReference());
        $this->orderPaymentRepository->save($payment);

        $invoice->setTransactionId($captureResponse->getReference());
        $this->invoiceRepository->save($invoice);
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $additionalData = $data->getAdditionalData();

        if (isset($additionalData['selectedIssuer'])) {
            $this->getInfoInstance()->setAdditionalInformation('selectedIssuer', $additionalData['selectedIssuer']);
        }

        if (isset($additionalData['issuerKey'])) {
            $this->getInfoInstance()->setAdditionalInformation('issuerKey', $additionalData['issuerKey']);
        }

        return $this;
    }

    public function isInitializeNeeded()
    {
        return true;
    }

    public function initialize($paymentAction, $stateObject) {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $status = $this->getConfigData("order_status_payment_pending", $order->getStoreId());
        $stateObject->setState(Order::STATE_NEW);
        $stateObject->setStatus($status);
        $stateObject->setIsNotified(false);
    }

    public function canRefund() {
        $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
        return $method->isRefundSupported();
    }

    public function canRefundPartialPerInvoice() {
        $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
        return $method->isRefundSupported();
    }

    public function refund(InfoInterface $payment, $amount)
    {
        $refundRequest = new RefundRequest();
        $refundRequest->setAmount($amount);

        /** @var CreditmemoInterface $creditMemoInterface */
        $creditMemoInterface = $payment->getCreditmemo();

        /** @var InvoiceInterface $invoice */
        $invoice = $creditMemoInterface->getInvoice();
        $refundRequest->setReference($invoice->getTransactionId());

        $refundRequest->setOrderLines($this->getOrderlinesByInvoice($creditMemoInterface));

        try {
            $refundResponse = $this->ccvOnlinePaymentsService->getApi()->createRefund($refundRequest);
        }catch(ApiException $apiException) {
            throw new LocalizedException(new Phrase("Could not create a refund: %1", [$apiException->getMessage()]), $apiException);
        }

        return $this;
    }

    public function canVoid()
    {
        $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
        return $method->isTransactionTypeAuthoriseSupported();
    }

    public function void(InfoInterface $payment)
    {
        $reversalRequest = new ReversalRequest();
        $reversalRequest->setReference($payment->getCcvonlinepaymentsReference());

        try {
            $reversalResponse = $this->ccvOnlinePaymentsService->getApi()->createReversal($reversalRequest);
        }catch(ApiException $apiException) {
            throw new LocalizedException(new Phrase("Could not create reversal: %1", [$apiException->getMessage()]), $apiException);
        }

        return $this;
    }

    private function getOrderlinesByInvoice($invoice) {
        $orderLines = [];

        foreach($invoice->getItems() as $item) {
            $totalPrice = $item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount() + $item->getDiscountTaxCompensationAmount();
            if(abs($totalPrice) > 0.0001) {
                $orderLine = new OrderLine();
                $orderLine->setType(OrderLine::TYPE_PHYSICAL);
                $orderLine->setName($item->getName());
                $orderLine->setQuantity(round($item->getQty()));
                if($orderLine->getQuantity() < 1) {
                    $orderLine->setQuantity(1);
                }

                $orderLine->setTotalPrice($totalPrice);
                $orderLine->setUnit("pcs");
                $orderLine->setUnitPrice($orderLine->getTotalPrice()/$orderLine->getQuantity());
                $orderLine->setVatRate(($item->getTaxAmount() / $orderLine->getTotalPrice()) * 100 );
                $orderLine->setVat($item->getTaxAmount());
                $orderLines[] = $orderLine;
            }
        }

        $totalShippingAmount = $invoice->getShippingAmount() + $invoice->getShippingTaxAmount() + $invoice->getShippingDiscountTaxCompensationAmount();
        if($totalShippingAmount > 0) {
            $orderLine = new \CCVOnlinePayments\Lib\OrderLine();
            $orderLine->setType(\CCVOnlinePayments\Lib\OrderLine::TYPE_SHIPPING_FEE);
            $orderLine->setName("Shipping");
            $orderLine->setQuantity(1);
            $orderLine->setTotalPrice($totalShippingAmount);
            $orderLine->setVat($invoice->getShippingTaxAmount());
            $orderLine->setVatRate(($invoice->getShippingTaxAmount() / $invoice->getShippingAmount()) * 100);
            $orderLine->setUnitPrice($totalShippingAmount);
            $orderLines[] = $orderLine;
        }

        return $orderLines;
    }

}
