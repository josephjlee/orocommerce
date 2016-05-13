<?php

namespace OroB2B\Bundle\PaymentBundle\Action;

use Symfony\Component\OptionsResolver\OptionsResolver;

use OroB2B\Bundle\PaymentBundle\Method\PaymentMethodInterface;

class PurchaseAction extends AbstractPaymentMethodAction
{
    /** {@inheritdoc} */
    protected function configureOptionsResolver(OptionsResolver $resolver)
    {
        parent::configureOptionsResolver($resolver);

        $resolver
            ->setDefined(['reference', 'allowReuseSourceTransaction'])
            ->addAllowedTypes('reference', ['string', 'Symfony\Component\PropertyAccess\PropertyPathInterface'])
            ->addAllowedTypes(
                'allowReuseSourceTransaction',
                ['string', 'Symfony\Component\PropertyAccess\PropertyPathInterface']
            );
    }

    /** {@inheritdoc} */
    protected function configureValuesResolver(OptionsResolver $resolver)
    {
        parent::configureValuesResolver($resolver);

        $resolver
            ->setRequired('paymentMethod')
            ->setDefault('allowReuseSourceTransaction', false)
            ->setDefined('reference')
            ->addAllowedTypes('paymentMethod', 'string')
            ->addAllowedTypes('reference', 'object')
            ->addAllowedTypes('allowReuseSourceTransaction', 'bool');
    }

    /** {@inheritdoc} */
    protected function executeAction($context)
    {
        $options = $this->getOptions($context);

        $paymentTransaction = $this->paymentTransactionProvider->createPaymentTransaction(
            $options['paymentMethod'],
            PaymentMethodInterface::PURCHASE,
            $options['object']
        );

        if (!empty($options['reference'])) {
            $sourcePaymentTransaction = $this->paymentTransactionProvider
                ->getActiveValidatePaymentTransaction($options['reference'], $options['paymentMethod']);

            if (!$sourcePaymentTransaction) {
                throw new \RuntimeException('Payment transaction by reference not found');
            }

            $paymentTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);
        }

        $paymentTransaction
            ->setAmount($options['amount'])
            ->setCurrency($options['currency']);

        if (!empty($options['transactionOptions'])) {
            $paymentTransaction->setTransactionOptions($options['transactionOptions']);
        }

        $response = $this->executePaymentTransaction($paymentTransaction);

        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        if ($sourcePaymentTransaction = $paymentTransaction->getSourcePaymentTransaction()) {
            $sourcePaymentTransaction->setActive($options['allowReuseSourceTransaction']);
        }

        $this->setAttributeValue(
            $context,
            array_merge(
                ['paymentMethod' => $options['paymentMethod']],
                $this->getCallbackUrls($paymentTransaction),
                (array)$paymentTransaction->getTransactionOptions(),
                $response
            )
        );
    }
}
