<?php

namespace Oro\Bundle\CheckoutBundle\Tests\Unit\Condition;

use Oro\Bundle\CheckoutBundle\Condition\ValidateCheckoutAddresses;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ValidateCheckoutAddressesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ValidateCheckoutAddresses
     */
    private $condition;

    /**
     * @var ValidatorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $validator;

    protected function setUp()
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->condition = new ValidateCheckoutAddresses($this->validator);
    }

    /**
     * @expectedException \Oro\Component\ConfigExpression\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing "checkout" option
     */
    public function testInitializeInvalid()
    {
        $this->assertInstanceOf(
            'Oro\Component\ConfigExpression\Condition\AbstractCondition',
            $this->condition->initialize([])
        );
    }

    public function testInitialize()
    {
        $this->assertInstanceOf(
            'Oro\Component\ConfigExpression\Condition\AbstractCondition',
            $this->condition->initialize(['Method', new \stdClass()])
        );
    }

    public function testGetName()
    {
        $this->assertEquals(ValidateCheckoutAddresses::NAME, $this->condition->getName());
    }

    public function testToArray()
    {
        $stdClass = new \stdClass();
        $this->condition->initialize(['checkout' => $stdClass]);
        $result = $this->condition->toArray();

        $key = '@'.ValidateCheckoutAddresses::NAME;

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey($key, $result);

        $resultSection = $result[$key];
        $this->assertInternalType('array', $resultSection);
        $this->assertArrayHasKey('parameters', $resultSection);
        $this->assertContains($stdClass, $resultSection['parameters']);
    }

    public function testCompile()
    {
        $toStringStub = new ToStringStub();
        $options = ['checkout' => $toStringStub];

        $this->condition->initialize($options);
        $result = $this->condition->compile('$factory');
        $this->assertEquals(
            sprintf(
                '$factory->create(\'%s\', [%s])',
                ValidateCheckoutAddresses::NAME,
                $toStringStub
            ),
            $result
        );
    }

    public function testEvaluateNoCheckoutPassed()
    {
        $this->condition->initialize(['checkout' => new \stdClass()]);
        $this->validator->expects($this->never())->method('validate');
        $this->assertEquals(false, $this->condition->evaluate([]));
    }

    public function testEvaluateNoBillingAddress()
    {
        $this->condition->initialize(['checkout' => new Checkout()]);
        $this->validator->expects($this->never())->method('validate');
        $this->assertEquals(false, $this->condition->evaluate([]));
    }

    public function testEvaluateInvalidBillingAddress()
    {
        $billingAddress = new OrderAddress();
        $checkout = (new Checkout())->setBillingAddress($billingAddress);
        $this->condition->initialize(['checkout' =>  $checkout]);
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(['error']);
        $this->assertEquals(false, $this->condition->evaluate([]));
    }

    public function testEvaluateCorrectBillingAddressAndShipToBillingEnabled()
    {
        $billingAddress = new OrderAddress();
        $checkout = (new Checkout())
            ->setBillingAddress($billingAddress)
            ->setShipToBillingAddress(true);
        $this->condition->initialize(['checkout' =>  $checkout]);
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn([]);
        $this->assertEquals(true, $this->condition->evaluate([]));
    }

    public function testEvaluateEmptyShippingAddress()
    {
        $billingAddress = new OrderAddress();
        $checkout = (new Checkout())
            ->setBillingAddress($billingAddress)
            ->setShipToBillingAddress(false);
        $this->condition->initialize(['checkout' =>  $checkout]);
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn([]);
        $this->assertEquals(false, $this->condition->evaluate([]));
    }

    public function testEvaluateInvalidShippingAddress()
    {
        $billingAddress = new OrderAddress();
        $checkout = (new Checkout())
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($billingAddress)
            ->setShipToBillingAddress(false);
        $this->condition->initialize(['checkout' =>  $checkout]);
        $this->validator->expects($this->exactly(2))
            ->method('validate')
            ->willReturnOnConsecutiveCalls([], ['error']);
        $this->assertEquals(false, $this->condition->evaluate([]));
    }

    public function testEvaluateAllAddressesCorrect()
    {
        $billingAddress = new OrderAddress();
        $checkout = (new Checkout())
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($billingAddress)
            ->setShipToBillingAddress(false);
        $this->condition->initialize(['checkout' =>  $checkout]);
        $this->validator->expects($this->exactly(2))
            ->method('validate')
            ->willReturnOnConsecutiveCalls([], []);
        $this->assertEquals(true, $this->condition->evaluate([]));
    }
}