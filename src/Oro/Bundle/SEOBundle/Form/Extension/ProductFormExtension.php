<?php

namespace Oro\Bundle\SEOBundle\Form\Extension;

use Oro\Bundle\EntityBundle\ORM\OroEntityManager;
use Oro\Bundle\ProductBundle\Form\Type\ProductType;

use Doctrine\Common\Persistence\ManagerRegistry;

class ProductFormExtension extends BaseMetaFormExtension
{
    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return ProductType::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetaFieldLabelPrefix()
    {
        return 'oro.product';
    }
}
