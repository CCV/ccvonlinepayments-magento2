<?php namespace CCVOnlinePayments\Magento\Method;

use CCVOnlinePayments\Lib\Exception\ApiException;
use CCVOnlinePayments\Lib\RefundRequest;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use \Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Relation\Refund;
use Psr\Log\LoggerInterface;

class Method extends \Magento\Payment\Model\Method\Adapter {

    public $_canCapture = true;
    public $_canAuthorize = true;
    public $_canRefund = true;
    public $_canRefundInvoicePartial = true;

    public $_isInitializeNeeded = true;

    private $checkoutSession;
    private $ccvOnlinePaymentsService;

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
        LoggerInterface $logger = null)
    {
        parent::__construct($eventManager, $valueHandlerPool, $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $commandPool, $validatorPool, $commandExecutor, $logger);
        $this->checkoutSession          = $checkoutSession;
        $this->ccvOnlinePaymentsService = $ccvOnlinePaymentsService;
    }

    public function isAvailable(CartInterface $quote = null)
    {
        if(parent::isAvailable($quote)) {
            $method = $this->ccvOnlinePaymentsService->getMethodById($this->getCode());
            if($method !== null) {
                return $method->isCurrencySupported($this->checkoutSession->getQuote()->getCurrency()->getQuoteCurrencyCode());
            }
        }

        return false;
    }

    public function capture(InfoInterface $payment, $amount)
    {

    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        foreach($data->getAdditionalData() as $key => $value) {
            $this->getInfoInstance()->setAdditionalInformation($key, $value);
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
        $refundRequest->setReference($payment->getCcvonlinepaymentsReference());
        $refundRequest->setAmount($amount);

        try {
            $refundResponse = $this->ccvOnlinePaymentsService->getApi()->createRefund($refundRequest);
        }catch(ApiException $apiException) {
            throw new LocalizedException("Could not create a refund: %1", $apiException->getMessage());
        }

        return $this;
    }

}
