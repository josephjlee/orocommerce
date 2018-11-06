<?php

namespace Oro\Bundle\PricingBundle\Tests\Unit\Entity\EntityListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\CatalogBundle\Event\ProductsChangeRelationEvent;
use Oro\Bundle\PricingBundle\Entity\EntityListener\CategoryEntityListener;

class CategoryEntityListenerTest extends AbstractRuleEntityListenerTest
{
    /**
     * @return string
     */
    protected function getEntityClassName()
    {
        return Category::class;
    }

    /**
     * @return CategoryEntityListener
     */
    protected function getListener()
    {
        return new CategoryEntityListener(
            $this->priceRuleLexemeTriggerHandler,
            $this->fieldsProvider,
            $this->registry
        );
    }

    /**
     * @return array
     */
    public function preUpdateData()
    {
        return [
            [
                [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
                ['key2'],
                1,
            ],
            [
                [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
                ['key3'],
                0,
            ],
        ];
    }

    /**
     * @dataProvider preUpdateData
     * @param array $changeSet
     * @param array $expectedFields
     * @param int $numberOfCalls
     */
    public function testPreUpdate(array $changeSet, array $expectedFields, int $numberOfCalls)
    {
        /** @var Category $category */
        $category = $this->getEntity($this->getEntityClassName(), ['id' => 1]);

        /** @var PreUpdateEventArgs|\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createMock(PreUpdateEventArgs::class);
        $event->expects($this->once())
            ->method('hasChangedField')
            ->with(Category::FIELD_PARENT_CATEGORY)
            ->willReturn(false);

        $event->expects($this->once())
            ->method('getEntityChangeSet')
            ->willReturn($changeSet);

        $this->assertRecalculateByEntityFieldsUpdate(1, $numberOfCalls, $expectedFields, $changeSet);

        $this->listener->preUpdate($category, $event);
    }

    public function testPreUpdateFeatureEnabled()
    {
        /** @var Category $category */
        $category = $this->getEntity(Category::class, ['id' => 42]);

        /** @var PreUpdateEventArgs|\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createMock(PreUpdateEventArgs::class);
        $event->expects($this->once())
            ->method('hasChangedField')
            ->with(Category::FIELD_PARENT_CATEGORY)
            ->willReturn(true);

        $this->priceRuleLexemeTriggerHandler->expects($this->once())
            ->method('findEntityLexemes')
            ->willReturn([]);

        $this->assertFeatureChecker('feature1')
            ->preUpdate($category, $event);
    }

    public function testPreUpdateFeatureDisabled()
    {
        /** @var Category $category */
        $category = $this->getEntity(Category::class, ['id' => 42]);

        /** @var PreUpdateEventArgs|\PHPUnit\Framework\MockObject\MockObject $event */
        $event = $this->createMock(PreUpdateEventArgs::class);
        $event->expects($this->never())->method('hasChangedField');

        $this->assertFeatureChecker('feature1', false)
            ->preUpdate($category, $event);
    }

    public function testPreRemoveFeatureEnabled()
    {
        $this->priceRuleLexemeTriggerHandler->expects($this->once())
            ->method('findEntityLexemes')
            ->willReturn([]);

        $this->assertFeatureChecker('feature1')->preRemove();
    }

    public function testPreRemoveFeatureDisabled()
    {
        $this->priceRuleLexemeTriggerHandler->expects($this->never())
            ->method('findEntityLexemes');

        $this->assertFeatureChecker('feature1', false)->preRemove();
    }

    public function testOnProductsChangeRelationFeatureEnabled()
    {
        $event = $this->createMock(ProductsChangeRelationEvent::class);
        $event->expects($this->once())
            ->method('getProducts')
            ->willReturn([]);

        $this->priceRuleLexemeTriggerHandler->expects($this->once())
            ->method('findEntityLexemes')
            ->willReturn([]);

        $this->assertFeatureChecker('feature1')
            ->onProductsChangeRelation($event);
    }

    public function testOnProductsChangeRelationFeatureDisabled()
    {
        $event = $this->createMock(ProductsChangeRelationEvent::class);
        $event->expects($this->never())->method('getProducts');

        $this->assertFeatureChecker('feature1', false)
            ->onProductsChangeRelation($event);
    }
}
