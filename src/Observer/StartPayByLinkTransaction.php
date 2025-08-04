<?php

namespace CCVOnlinePayments\Magento\Observer;

use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;;

/**
 * Generate item list for payment capture
 */
class StartPayByLinkTransaction implements ObserverInterface
{

    public function __construct(
        private readonly CcvOnlinePaymentsService $ccvOnlinePaymentsService,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderSender                     $orderSender
    ) {

    }

    public function execute(\Magento\Framework\Event\Observer $observer) : void
    {
        if (!$observer->hasData('order')) {
            return;
        }

        $order = $observer->getData('order');
        $payment = $order->getPayment();

        if ($payment->getMethod() === 'ccvonlinepayments_paybylink') {
            $paymentResponse = $this->ccvOnlinePaymentsService->startTransaction($payment->getOrder(), []);
            $payment->setAdditionalInformation('ccvPayUrl', $paymentResponse->getPayUrl());
            $this->orderPaymentRepository->save($payment);

            if(!$order->getEmailSent()) {
                $this->orderSender->send($order);
                $order  ->addStatusHistoryComment(__("New order email sent"))
                    ->setIsCustomerNotified(true)
                    ->save();
            }
        }

    }
}
