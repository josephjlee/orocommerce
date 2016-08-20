<?php

namespace Oro\Bundle\AccountBundle\Tests\Functional\Entity\Repository;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\AccountBundle\Entity\AccountUser;
use Oro\Bundle\AccountBundle\Entity\AccountUserAddress;
use Oro\Bundle\AccountBundle\Entity\Repository\AccountUserAddressRepository;

/**
 * @dbIsolation
 */
class AccountUserAddressRepositoryTest extends WebTestCase
{
    /**
     * @var AccountUserAddressRepository
     */
    protected $repository;

    protected function setUp()
    {
        $this->initClient();

        $this->repository = $this->getContainer()
            ->get('doctrine')
            ->getRepository('OroAccountBundle:AccountUserAddress');

        $this->loadFixtures(
            [
                'Oro\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadAccountUserAddresses'
            ]
        );
    }

    /**
     * @dataProvider addressesDataProvider
     * @param string $userReference
     * @param string $type
     * @param array $expectedAddressReferences
     */
    public function testGetAddressesByType($userReference, $type, array $expectedAddressReferences)
    {
        /** @var AccountUser $user */
        $user = $this->getReference($userReference);

        /** @var AccountUserAddress[] $actual */
        $actual = $this->repository->getAddressesByType(
            $user,
            $type,
            $this->getContainer()->get('oro_security.acl_helper')
        );
        $this->assertCount(count($expectedAddressReferences), $actual);
        $addressIds = [];
        foreach ($actual as $address) {
            $addressIds[] = $address->getId();
        }
        foreach ($expectedAddressReferences as $addressReference) {
            $this->assertContains($this->getReference($addressReference)->getId(), $addressIds);
        }
    }

    /**
     * @return array
     */
    public function addressesDataProvider()
    {
        return [
            [
                'grzegorz.brzeczyszczykiewicz@example.com',
                'billing',
                [
                    'grzegorz.brzeczyszczykiewicz@example.com.address_1',
                    'grzegorz.brzeczyszczykiewicz@example.com.address_2',
                    'grzegorz.brzeczyszczykiewicz@example.com.address_3'
                ]
            ],
            [
                'grzegorz.brzeczyszczykiewicz@example.com',
                'shipping',
                [
                    'grzegorz.brzeczyszczykiewicz@example.com.address_1',
                    'grzegorz.brzeczyszczykiewicz@example.com.address_3'
                ]
            ]
        ];
    }

    /**
     * @dataProvider defaultAddressDataProvider
     * @param string $accountUserReference
     * @param string $type
     * @param string $expectedAddressReference
     */
    public function testGetDefaultAddressesByType($accountUserReference, $type, $expectedAddressReference)
    {
        /** @var AccountUser $user */
        $user = $this->getReference($accountUserReference);

        /** @var AccountUserAddress[] $actual */
        $actual = $this->repository->getDefaultAddressesByType(
            $user,
            $type,
            $this->getContainer()->get('oro_security.acl_helper')
        );
        $this->assertCount(1, $actual);
        $this->assertEquals($this->getReference($expectedAddressReference)->getId(), $actual[0]->getId());
    }

    /**
     * @return array
     */
    public function defaultAddressDataProvider()
    {
        return [
            [
                'grzegorz.brzeczyszczykiewicz@example.com',
                'billing',
                'grzegorz.brzeczyszczykiewicz@example.com.address_2'
            ],
            [
                'grzegorz.brzeczyszczykiewicz@example.com',
                'shipping',
                'grzegorz.brzeczyszczykiewicz@example.com.address_1'
            ]
        ];
    }
}
