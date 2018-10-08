<?php

namespace Oro\Bundle\ShippingBundle\Tests\Unit\Entity;

use Oro\Bundle\ShippingBundle\Entity\FreightClass;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;

class FreightClassTest extends \PHPUnit\Framework\TestCase
{
    use EntityTestCaseTrait;

    /** @var FreightClass $entity */
    protected $entity;

    public function setUp()
    {
        $this->entity = new FreightClass();
    }

    public function tearDown()
    {
        unset($this->entity);
    }

    public function testAccessors()
    {
        $properties = [
            ['code', '123'],
        ];

        $this->assertPropertyAccessors($this->entity, $properties);
    }

    public function testToString()
    {
        $this->entity->setCode('test');

        $this->assertEquals('test', (string)$this->entity);
    }
}
