<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\Exception\ApiException;
use CCVOnlinePayments\Magento\CcvOnlinePaymentsService;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Model\Session;

class Redirect extends Action {

    use RedirectTrait;

    public function __construct(
        Context $context,
        private readonly Session $checkoutSession,
        private readonly OrderFactory $orderFactory,
        private readonly \Magento\Framework\App\Request\Http $request,
        private readonly CcvOnlinePaymentsService $ccvOnlinePaymentsService
    )
    {
        parent::__construct($context);
    }

    protected function getOrder() : ?Order{
        $orderId = $this->checkoutSession->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    public function execute() : ResultInterface
    {
        $order = $this->getOrder();
        if($order === null) {
            return $this->redirect('checkout/cart');
        }

        if ($order->getState() === Order::STATE_PENDING_PAYMENT || $order->getState() === Order::STATE_NEW) {
            return $this->doPayment($order);
        }elseif($order->getState() === Order::STATE_CANCELED) {
            return $this->redirect('checkout/cart');
        }else{
            return $this->redirect('checkout/cart');
        }
    }

    private function doPayment(Order $order) : ResultInterface{
        $ccvOnlinePaymentsApi = $this->ccvOnlinePaymentsService->getApi();

        /** @var ?Order\Payment $orderPayment */
        $orderPayment = $order->getPayment();
        if($orderPayment === null || $orderPayment->getMethod() === "ccvonlinepayments_paybylink") {
            return $this->redirect('checkout/onepage/success');
        }

        $metadata = [];
        $checkout =  $this->request->getParam('checkout', null);
        if($checkout !== null) {
            $metadata["Checkout"] = $checkout;
        }

        try {
            $paymentResponse = $this->ccvOnlinePaymentsService->startTransaction($order, $metadata);
        }catch(ApiException $apiException) {
            return $this->redirect($this->_url->getUrl('ccvonlinepayments/checkout/paymentreturn/',[
                "order_id"   => $order->getIncrementId(),
                "payment_id" => $orderPayment->getId()
            ]));
        }

        if($paymentResponse->getPayUrl() === null) {
            throw new \Exception("Missing pay url");
        }else {
            return $this->redirect($paymentResponse->getPayUrl());
        }
    }


}
