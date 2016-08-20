<?php

namespace Oro\Bundle\PricingBundle\Entity\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;

use Oro\Bundle\BatchBundle\ORM\Query\BufferedQueryResultIterator;
use Oro\Bundle\PricingBundle\Entity\ProductPriceChangeTrigger;

class ProductPriceChangeTriggerRepository extends EntityRepository
{
    /**
     * @param ProductPriceChangeTrigger $trigger
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function isExisting(ProductPriceChangeTrigger $trigger)
    {
        return (bool)$this->createQueryBuilder('cpp')
            ->select('1')
            ->where('cpp.priceList = :priceList')
            ->setParameter('priceList', $trigger->getPriceList())
            ->andWhere('cpp.product = :product')
            ->setParameter('product', $trigger->getProduct())
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR);
    }

    /**
     * @return BufferedQueryResultIterator|ProductPriceChangeTrigger[]
     */
    public function getProductPriceChangeTriggersIterator()
    {
        $qb = $this->createQueryBuilder('productPriceChanges');

        return new BufferedQueryResultIterator($qb->getQuery());
    }

    public function deleteAll()
    {
        $this->createQueryBuilder('productPriceChangeTrigger')
            ->delete('OroPricingBundle:ProductPriceChangeTrigger', 'productPriceChangeTrigger')
            ->getQuery()
            ->execute();
    }
}
