<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\PaymentRequest;
use CCVOnlinePayments\Lib\PaymentStatus;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Api\Data\StoreInterface;

trait AbstractStatusHandlerTrait {

    protected $session;
    protected $orderFactory;
    protected $orderRepository;
    protected $config;
    protected $store;
    protected $localeResolver;
    protected $rawFactory;
    protected $ccvOnlinePaymentsService;
    protected $orderPaymentRepository;
    protected $invoiceService;
    protected $invoiceRepository;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        OrderRepository $orderRepository,
        ScopeConfigInterface $config,
        StoreInterface $store,
        ResolverInterface $localeResolver,
        \Magento\Framework\Controller\Result\Raw $rawFactory,
        CcvOnlinePaymentsService $ccvOnlinePaymentsService,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        InvoiceService $invoiceService,
        Order\InvoiceRepository $invoiceRepository
    )
    {
        parent::__construct($context);
        $this->session                  = $checkoutSession;
        $this->orderFactory             = $orderFactory;
        $this->orderRepository          = $orderRepository;
        $this->config                   = $config;
        $this->store                    = $store;
        $this->localeResolver           = $localeResolver;
        $this->rawFactory               = $rawFactory;
        $this->ccvOnlinePaymentsService = $ccvOnlinePaymentsService;
        $this->orderPaymentRepository   = $orderPaymentRepository;
        $this->invoiceService           = $invoiceService;
        $this->invoiceRepository        = $invoiceRepository;
    }

    protected function handlePaymentStatus() {
        $order = $this->orderFactory->create()->loadByIncrementId($this->getRequest()->getParam("order_id"));
        $payment = $order->getPaymentById($this->getRequest()->getParam("payment_id"));

        $reference = $payment->getCcvonlinepaymentsReference();
        if($reference === null) {
            return null;
        }

        $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();
        $paymentStatus = $ccvOnlinePaymentsApi->getPaymentStatus($reference);

        if($paymentStatus->getStatus() === PaymentStatus::STATUS_SUCCESS) {
            if(!$payment->getIsTransactionClosed()) {
                if (abs($order->getTotalDue() - $paymentStatus->getAmount()) < 0.01) {
                    if ($paymentStatus->getTransactionType() === PaymentRequest::TRANSACTION_TYPE_SALE) {
                        $payment->setIsTransactionClosed(true);
                        $payment->setTransactionId($reference);
                        $payment->registerCaptureNotification($paymentStatus->getAmount(), true);
                        $this->orderPaymentRepository->save($payment);
                    } elseif ($paymentStatus->getTransactionType() === PaymentRequest::TRANSACTION_TYPE_AUTHORIZE) {
                        $payment->setIsTransactionClosed(false);
                        $payment->setTransactionId($reference);
                        $payment->registerAuthorizationNotification($paymentStatus->getAmount(), true);
                        $this->orderPaymentRepository->save($payment);
                    } else {
                        throw new \Exception("Not implemented");
                    }

                    $status = $this->config->getValue("payment/ccvonlinepayments/ccvonlinepayments_general/order_status_processing");
                    $order->setStatus($status);

                    $order->setState(Order::STATE_PROCESSING);
                    $this->orderRepository->save($order);
                }
            }
        }

        return $paymentStatus;
    }
}
