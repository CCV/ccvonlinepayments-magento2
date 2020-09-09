<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\PaymentStatus;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderRepository;
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
        OrderPaymentRepositoryInterface $orderPaymentRepository
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
    }
    protected function handlePaymentStatus() {
        $order = $this->orderFactory->create()->loadByIncrementId($this->getRequest()->getParam("order_id"));
        $payment = $order->getPaymentById($this->getRequest()->getParam("payment_id"));

        $reference = $payment->getCcvonlinepaymentsReference();

        $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();
        $paymentStatus = $ccvOnlinePaymentsApi->getPaymentStatus($reference);

        if($paymentStatus->getStatus() === PaymentStatus::STATUS_SUCCESS) {
            if(!$payment->getIsTransactionClosed()) {
                if (abs($order->getTotalDue() - $paymentStatus->getAmount()) < 0.01) {
                    $payment->setIsTransactionClosed(true);
                    $payment->registerCaptureNotification($paymentStatus->getAmount(), true);
                    $this->orderPaymentRepository->save($payment);


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
