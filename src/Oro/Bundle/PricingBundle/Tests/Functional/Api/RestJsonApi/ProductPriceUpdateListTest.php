<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\Api\RestJsonApi;

use Oro\Bundle\ApiBundle\Tests\Functional\JsonApiDocContainsConstraint;
use Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiUpdateListTestCase;
use Oro\Bundle\PricingBundle\Async\Topics;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Model\PriceListTriggerFactory;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadProductPricesWithRules;

/**
 * @dbIsolationPerTest
 */
class ProductPriceUpdateListTest extends RestJsonApiUpdateListTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->loadFixtures([LoadProductPricesWithRules::class]);
    }

    public function testCreateEntities()
    {
        $priceList5Id = $this->getReference('price_list_5')->getId();
        $data = [
            'data' => [
                [
                    'type'          => 'productprices',
                    'attributes'    => [
                        'quantity' => 250,
                        'value'    => '150.0000',
                        'currency' => 'EUR'
                    ],
                    'relationships' => [
                        'priceList' => [
                            'data' => ['type' => 'pricelists', 'id' => (string)$priceList5Id]
                        ],
                        'product'   => [
                            'data' => ['type' => 'products', 'id' => '<toString(@product-5->id)>']
                        ],
                        'unit'      => [
                            'data' => ['type' => 'productunits', 'id' => '<toString(@product_unit.milliliter->code)>']
                        ]
                    ]
                ],
                [
                    'type'          => 'productprices',
                    'attributes'    => [
                        'quantity' => 10,
                        'value'    => '20.0000',
                        'currency' => 'GBP'
                    ],
                    'relationships' => [
                        'priceList' => [
                            'data' => ['type' => 'pricelists', 'id' => (string)$priceList5Id]
                        ],
                        'product'   => [
                            'data' => ['type' => 'products', 'id' => '<toString(@product-1->id)>']
                        ],
                        'unit'      => [
                            'data' => ['type' => 'productunits', 'id' => '<toString(@product_unit.bottle->code)>']
                        ]
                    ]
                ]
            ]
        ];
        $this->processUpdateList(ProductPrice::class, $data);

        self::assertMessageSent(
            Topics::RESOLVE_COMBINED_PRICES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $priceList5Id => [
                        $this->getReference('product-5')->getId(),
                        $this->getReference('product-1')->getId()
                    ]
                ]
            ]
        );
        self::assertMessageSent(
            Topics::RESOLVE_PRICE_RULES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $priceList5Id => [
                        $this->getReference('product-5')->getId(),
                        $this->getReference('product-1')->getId()
                    ]
                ]
            ]
        );

        $response = $this->cget(['entity' => 'productprices'], ['filter[priceList]' => '@price_list_5->id']);
        // we cannot rely to order of returned data due to product price ID is UUID
        $responseContent = self::jsonToArray($response->getContent());
        if (isset($responseContent['data'][0]['attributes']['quantity'])
            && count($responseContent['data']) === 2
            && $responseContent['data'][0]['attributes']['quantity'] !== 250
        ) {
            $tmp = $responseContent['data'][0];
            $responseContent['data'][0] = $responseContent['data'][1];
            $responseContent['data'][1] = $tmp;
        }
        $expectedData = $data;
        $expectedData['data'][0]['id'] = $responseContent['data'][0]['id'];
        $expectedData['data'][1]['id'] = $responseContent['data'][1]['id'];
        self::assertThat(
            $responseContent,
            new JsonApiDocContainsConstraint(self::processTemplateData($this->getResponseData($expectedData)))
        );
    }

    public function testUpdateEntities()
    {
        $priceList1Id = $this->getReference('price_list_1')->getId();
        $productPrice1Id = $this->getReference(LoadProductPricesWithRules::PRODUCT_PRICE_1)->getId();
        $productPrice1ApiId = $productPrice1Id . '-' . $priceList1Id;
        $productPrice2Id = $this->getReference(LoadProductPricesWithRules::PRODUCT_PRICE_2)->getId();
        $productPrice2ApiId = $productPrice2Id . '-' . $priceList1Id;
        $data = [
            'data' => [
                [
                    'meta'          => ['update' => true],
                    'type'          => 'productprices',
                    'id'            => $productPrice1ApiId,
                    'attributes'    => [
                        'quantity' => 250,
                        'value'    => '150.0000',
                        'currency' => 'CAD'
                    ],
                    'relationships' => [
                        'product' => [
                            'data' => ['type' => 'products', 'id' => '<toString(@product-5->id)>']
                        ],
                        'unit'    => [
                            'data' => ['type' => 'productunits', 'id' => '<toString(@product_unit.milliliter->code)>']
                        ]
                    ]
                ],
                [
                    'meta'          => ['update' => true],
                    'type'          => 'productprices',
                    'id'            => $productPrice2ApiId,
                    'attributes'    => [
                        'quantity' => 10,
                        'value'    => '20.0000',
                        'currency' => 'USD'
                    ],
                    'relationships' => [
                        'product' => [
                            'data' => ['type' => 'products', 'id' => '<toString(@product-3->id)>']
                        ],
                        'unit'    => [
                            'data' => ['type' => 'productunits', 'id' => '<toString(@product_unit.liter->code)>']
                        ]
                    ]
                ]
            ]
        ];
        $this->processUpdateList(ProductPrice::class, $data);

        self::assertMessageSent(
            Topics::RESOLVE_COMBINED_PRICES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $priceList1Id => [
                        $this->getReference('product-5')->getId(),
                        $this->getReference('product-3')->getId()
                    ]
                ]
            ]
        );
        self::assertMessageSent(
            Topics::RESOLVE_PRICE_RULES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $priceList1Id => [
                        $this->getReference('product-5')->getId(),
                        $this->getReference('product-3')->getId()
                    ]
                ]
            ]
        );

        $response = $this->cget(['entity' => 'productprices'], ['filter[priceList]' => (string)$priceList1Id]);
        // we cannot rely to order of returned data due to product price ID is UUID
        $responseContent = self::jsonToArray($response->getContent());
        if (isset($responseContent['data'][0]['attributes']['quantity'])
            && count($responseContent['data']) === 2
            && $responseContent['data'][0]['attributes']['quantity'] !== 250
        ) {
            $tmp = $responseContent['data'][0];
            $responseContent['data'][0] = $responseContent['data'][1];
            $responseContent['data'][1] = $tmp;
        }
        $expectedData = $data;
        unset($expectedData['data'][0]['meta'], $expectedData['data'][1]['meta']);
        self::assertThat(
            $responseContent,
            new JsonApiDocContainsConstraint(self::processTemplateData($this->getResponseData($expectedData)))
        );
    }
}