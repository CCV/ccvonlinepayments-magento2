<?php namespace CCVOnlinePayments\Magento\Method;

use CCVOnlinePayments\Lib\CaptureRequest;
use CCVOnlinePayments\Lib\Enum\OrderLineType;
use CCVOnlinePayments\Lib\Exception\ApiException;
use CCVOnlinePayments\Lib\OrderLine;
use CCVOnlinePayments\Lib\RefundRequest;
use CCVOnlinePayments\Lib\ReversalRequest;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use \Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class Method extends \Magento\Payment\Model\Method\Adapter {

    public bool $_canCapture = true;
    public bool $_canAuthorize = true;
    public bool $_canRefund = true;
    public bool $_canRefundInvoicePartial = true;

    public bool $_isInitializeNeeded = true;

    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        private readonly CcvOnlinePaymentsService $ccvOnlinePaymentsService,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        protected readonly \Magento\Framework\App\State $state,
        protected readonly ScopeConfigInterface $config,
        ?CommandPoolInterface $commandPool = null,
        ?ValidatorPoolInterface $validatorPool = null,
        ?CommandManagerInterface $commandExecutor = null,
        ?LoggerInterface $logger = null)
    {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $commandPool, $validatorPool, $commandExecutor, $logger);

    }

    public function isAvailable(?CartInterface $quote = null) : bool
    {
        if($quote !== null && parent::isAvailable($quote)) {
            $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
            if($method !== null) {
                $currencyCode = $quote->getCurrency()?->getQuoteCurrencyCode();
                if($currencyCode !== null) {
                    return $method->isCurrencySupported($currencyCode);
                }else{
                    return false;
                }
            }
        }

        return false;
    }

    public function canCapture() : bool
    {
        return true;
    }

    public function canCapturePartial() : bool
    {
        return true;
    }

    /**
     * @param Order\Payment $payment
     * @param float $amount
     */
    public function capture(InfoInterface $payment, $amount) : self
    {
        $captureRequest = new CaptureRequest();
        $captureRequest->setReference($payment->getCcvonlinepaymentsReference());
        $captureRequest->setAmount($amount);

        /** @var Order\Invoice $invoice */
        $invoice = $payment->getInvoice();
        $captureRequest->setOrderLines($this->getOrderlinesByInvoice($invoice));

        $captureResponse = $this->ccvOnlinePaymentsService->getApi()->createCapture($captureRequest);
        if($captureResponse->getReference() === null) {
            throw new \Exception("Reference not found");
        }

        $payment->setTransactionId($captureResponse->getReference());
        $this->orderPaymentRepository->save($payment);

        $invoice->setTransactionId($captureResponse->getReference());
        $this->invoiceRepository->save($invoice);

        return $this;
    }

    public function assignData(\Magento\Framework\DataObject $data) : self
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

    public function isInitializeNeeded() : bool
    {
        return true;
    }

    /**
     * @param Order $stateObject
     */
    public function initialize($paymentAction, $stateObject) : self {
        /** @var Payment $payment */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $status = $this->getConfigData("order_status_payment_pending", $order->getStoreId());
        $stateObject->setState(Order::STATE_NEW);
        $stateObject->setStatus($status);
        $stateObject->setIsNotified(false);

        return $this;
    }

    public function canRefund() : bool {
        $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
        return $method?->isRefundSupported() ?? false;
    }

    public function canRefundPartialPerInvoice() : bool {
        $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
        return $method?->isRefundSupported() ?? false;
    }

    /**
     * @param Payment $payment
     * @param float $amount
     */
    public function refund(InfoInterface $payment, $amount) : self
    {
        $refundRequest = new RefundRequest();
        $refundRequest->setAmount($amount);

        $creditMemoInterface = $payment->getCreditmemo();

        /** @var Order\Invoice $invoice */
        $invoice = $creditMemoInterface->getInvoice();
        $refundRequest->setReference($invoice->getTransactionId());

        $refundRequest->setOrderLines($this->getOrderlinesByInvoice($invoice));

        try {
            $refundResponse = $this->ccvOnlinePaymentsService->getApi()->createRefund($refundRequest);
        }catch(ApiException $apiException) {
            throw new LocalizedException(new Phrase("Could not create a refund: %1", [$apiException->getMessage()]), $apiException);
        }

        return $this;
    }

    public function canVoid() : bool
    {
        $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
        return $method?->isTransactionTypeAuthoriseSupported() ?? false;
    }

    /**
     * @param Payment $payment
     */
    public function void(InfoInterface $payment) : self
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

    /**
     * @param Order\Invoice $invoice
     * @return array<OrderLine>
     */
    private function getOrderlinesByInvoice(Order\Invoice $invoice) : array {
        $orderLines = [];

        foreach($invoice->getItems() as $item) {
            $totalPrice = $item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount() + $item->getDiscountTaxCompensationAmount();
            if(abs($totalPrice) > 0.0001) {
                $orderLine = new OrderLine();
                $orderLine->setType(OrderLineType::PHYSICAL);
                $orderLine->setName($item->getName());
                $orderLine->setQuantity(intval(round($item->getQty())));
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
            $orderLine->setType(OrderLineType::SHIPPING_FEE);
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
