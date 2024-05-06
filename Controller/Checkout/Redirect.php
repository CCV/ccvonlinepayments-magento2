<?php namespace CCVOnlinePayments\Magento\Controller\Checkout;

use CCVOnlinePayments\Lib\Exception\ApiException;
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
    private $request;
    private $ccvOnlinePaymentsService;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        \Magento\Framework\App\Request\Http $request,
        CcvOnlinePaymentsService $ccvOnlinePaymentsService
    )
    {
        parent::__construct($context);
        $this->session                  = $checkoutSession;
        $this->orderFactory             = $orderFactory;
        $this->request                  = $request;
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

        if($order->getPayment()->getMethod() === "ccvonlinepayments_paybylink") {
            $this->_redirect('checkout/onepage/success');
            return;
        }

        $metadata = [];
        $checkout =  $this->request->getParam('checkout', null);
        if($checkout !== null) {
            $metadata["Checkout"] = $checkout;
        }

        try {
            $paymentResponse = $this->ccvOnlinePaymentsService->startTransaction($order, $metadata);
        }catch(ApiException $apiException) {
            $this->_redirect($this->_url->getUrl('ccvonlinepayments/checkout/paymentreturn/',["order_id" => $order->getIncrementId(), "payment_id" => $payment->getId()]));
            return;
        }

        $this->_redirect($paymentResponse->getPayUrl());
    }


}
