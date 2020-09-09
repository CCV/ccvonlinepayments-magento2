<?php namespace CCVOnlinePayments\Magento\State;

use Magento\Sales\Model\Config\Source\Order\Status;

class PaymentPending extends Status
{

    protected $_stateStatuses = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;

}
