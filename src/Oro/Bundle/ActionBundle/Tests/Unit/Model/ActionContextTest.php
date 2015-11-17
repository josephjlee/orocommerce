<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Model;

use Oro\Bundle\ActionBundle\Model\ActionContext;

class ActionContextTest extends \PHPUnit_Framework_TestCase
{
    public function testOffsetExistsSetGetUnset()
    {
        $data = new ActionContext();
        $name = 'foo';
        $value = 'bar';

        $this->assertFalse(isset($data[$name]));
        $this->assertNull($data[$name]);

        $data[$name] = $value;
        $this->assertTrue(isset($data[$name]));
        $this->assertEquals($value, $data[$name]);

        unset($data[$name]);
        $this->assertFalse(isset($data[$name]));
        $this->assertNull($data[$name]);
    }

    public function testGetEntity()
    {
        $data = new ActionContext();
        $this->assertNull($data->getEntity());

        $data['entity'] = new \stdClass();
        $this->assertInternalType('object', $data->getEntity());
    }

    public function testGetIterator()
    {
        $array = ['foo' => 'bar', 'bar' => 'foo'];
        $data = new ActionContext($array);
        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertEquals($array, $result);
    }

    public function testCount()
    {
        $data = new ActionContext();
        $this->assertEquals(0, count($data));

        $data['foo'] = 'bar';
        $this->assertEquals(1, count($data));

        $data['bar'] = 'foo';
        $this->assertEquals(2, count($data));

        unset($data['foo']);
        $this->assertEquals(1, count($data));

        unset($data['bar']);
        $this->assertEquals(0, count($data));
    }

    public function testIsModified()
    {
        $data1 = new ActionContext(['foo' => 'bar']);
        $this->assertFalse($data1->isModified());

        $data1['foo'] = 'bar';
        $this->assertFalse($data1->isModified());

        $data2 = clone $data1;

        $data1['foo'] = null;
        $this->assertTrue($data1->isModified());

        unset($data2['foo']);
        $this->assertTrue($data1->isModified());
    }

    public function testIsEmpty()
    {
        $data = new ActionContext();
        $this->assertTrue($data->isEmpty());

        $data->offsetSet('foo', 'bar');
        $this->assertFalse($data->isEmpty());
    }

    public function testToArray()
    {
        $array = ['foo' => 'bar', 'bar' => 'foo'];

        $data = new ActionContext($array);
        $this->assertEquals($array, $data->toArray());
    }
}
