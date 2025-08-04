<?php namespace CCVOnlinePayments\Magento;

use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentDataBuilder implements BuilderInterface
{

    /**
     * @param array<mixed> $buildSubject
     * @return array<mixed>
     */
    public function build(array $buildSubject) : array
    {
        $result = [];

        return $result;
    }

}
