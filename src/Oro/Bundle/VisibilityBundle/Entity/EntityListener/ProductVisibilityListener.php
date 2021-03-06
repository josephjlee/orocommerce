<?php

namespace Oro\Bundle\VisibilityBundle\Entity\EntityListener;

use Oro\Bundle\VisibilityBundle\Async\Topics;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;

/**
 * Sends MQ message to resolve product visibility when a product visibility related entity
 * is created, updated or removed.
 */
class ProductVisibilityListener extends AbstractVisibilityListener
{
    /**
     * @param MessageProducerInterface $messageProducer
     */
    public function __construct(MessageProducerInterface $messageProducer)
    {
        parent::__construct($messageProducer, Topics::RESOLVE_PRODUCT_VISIBILITY);
    }
}
