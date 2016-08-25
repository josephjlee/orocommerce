<?php

namespace OroB2B\Bundle\OrderBundle\Layout\Block\Extension;

use Oro\Component\Layout\AbstractBlockTypeExtension;
use Oro\Component\Layout\Block\Type\BaseType;
use Oro\Component\Layout\Block\Type\Options;
use Oro\Component\Layout\BlockInterface;
use Oro\Component\Layout\BlockView;
use Oro\Component\Layout\Block\OptionsResolver\OptionsResolver;

class BlockPrefixExtension extends AbstractBlockTypeExtension
{
    /** {@inheritdoc} */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['block_prefixes' => []]);
    }

    /** {@inheritdoc} */
    public function finishView(BlockView $view, BlockInterface $block, Options $options)
    {
        $blockPrefixes = [];
        if ($view->vars->offsetExists('block_prefixes')) {
            $blockPrefixes = $view->vars['block_prefixes']->toArray();
        }

        if ($options->offsetExists('block_prefixes')) {
            $blockPrefixes = array_merge($blockPrefixes, $options['block_prefixes']->toArray());
        }

        $view->vars['block_prefixes'] = $blockPrefixes;
    }

    /** {@inheritdoc} */
    public function getExtendedType()
    {
        return BaseType::NAME;
    }
}
