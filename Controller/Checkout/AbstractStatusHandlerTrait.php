<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\Enum\PaymentStatus;
use CCVOnlinePayments\Lib\Enum\TransactionType;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

trait AbstractStatusHandlerTrait {
    public function __construct(
        Context $context,
        private readonly Session $checkoutSession,
        private readonly OrderFactory $orderFactory,
        private readonly OrderRepository $orderRepository,
        private readonly OrderSender $orderSender,
        private readonly ScopeConfigInterface $config,
        private readonly StoreInterface $store,
        private readonly ResolverInterface $localeResolver,
        private readonly \Magento\Framework\Controller\Result\Raw $rawFactory,
        private readonly CcvOnlinePaymentsService $ccvOnlinePaymentsService,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly InvoiceService $invoiceService,
        private readonly Order\InvoiceRepository $invoiceRepository,
        private readonly Order\Status\HistoryRepository $historyRepository,
        private readonly LockManagerInterface $lockManager
    )
    {
        parent::__construct($context);

    }

    protected function handlePaymentStatus() : ?\CCVOnlinePayments\Lib\PaymentStatus {
        $orderId = $this->getRequest()->getParam("order_id");
        $lockName = "ccv_payment_lock_".$orderId;
        if(!$this->lockManager->lock($lockName)) {
            throw new \Exception("Could not acquire lock $lockName");
        }

        try {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            $payment = $order->getPaymentById($this->getRequest()->getParam("payment_id"));

            if($payment === false) {
                return null;
            }

            $reference = $payment->getCcvonlinepaymentsReference();
            if($reference === null) {
                return null;
            }

            $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();
            $paymentStatus = $ccvOnlinePaymentsApi->getPaymentStatus($reference);

            if($paymentStatus->getAmount() === null) {
                return null;
            }

            if($paymentStatus->getStatus() === PaymentStatus::SUCCESS) {
                if(!$payment->getIsTransactionClosed()) {
                    if (abs($order->getTotalDue() - $paymentStatus->getAmount()) < 0.01) {
                        if ($paymentStatus->getTransactionType() === TransactionType::SALE) {
                            $payment->setIsTransactionClosed(true);
                            $payment->setTransactionId($reference);
                            $payment->registerCaptureNotification($paymentStatus->getAmount(), true);
                            $this->orderPaymentRepository->save($payment);
                        } elseif ($paymentStatus->getTransactionType() === TransactionType::AUTHORIZE) {
                            $payment->setIsTransactionClosed(false);
                            $payment->setTransactionId($reference);
                            $payment->registerAuthorizationNotification($paymentStatus->getAmount());
                            $this->orderPaymentRepository->save($payment);
                        } else {
                            throw new \Exception("Not implemented");
                        }

                        $status = $this->config->getValue("payment/ccvonlinepayments/ccvonlinepayments_general/order_status_processing");
                        $order->setStatus($status);


                        $order->setState(Order::STATE_PROCESSING);
                        $this->orderRepository->save($order);

                        if(!$order->getEmailSent()) {
                            $this->orderSender->send($order);

                            $history = $order->addStatusHistoryComment(__("New order email sent"));
                            $history->setIsCustomerNotified(1);
                            $this->historyRepository->save($history);
                        }
                    }
                }
            }
        } finally {
            $this->lockManager->unlock($lockName);
        }

        return $paymentStatus;
    }

}
