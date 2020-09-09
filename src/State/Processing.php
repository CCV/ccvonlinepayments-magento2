<?php namespace CCVOnlinePayments\Magento\State;

use Magento\Sales\Model\Config\Source\Order\Status;

class Processing extends Status
{

    protected $_stateStatuses = \Magento\Sales\Model\Order::STATE_PROCESSING;

}
