<?php namespace CCVOnlinePayments\Magento;

use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        var_dump($buildSubject);
        die();

        $result = [];

        return $result;
    }

}
