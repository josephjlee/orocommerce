<?php

namespace OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures;

use Symfony\Component\Yaml\Yaml;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

use OroB2B\Bundle\AccountBundle\Entity\Account;
use OroB2B\Bundle\AccountBundle\Entity\AccountGroup;
use OroB2B\Bundle\CatalogBundle\Entity\Category;
use OroB2B\Bundle\CatalogBundle\Entity\Repository\CategoryRepository;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\CategoryVisibility;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\AccountGroupCategoryVisibility;
use OroB2B\Bundle\AccountBundle\Entity\Visibility\AccountCategoryVisibility;
use OroB2B\Bundle\FallbackBundle\Entity\LocalizedFallbackValue;
use OroB2B\Bundle\AccountBundle\Entity\AccountUser;

class LoadCategoryVisibilityData extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $categories = $this->getCategoryVisibilityData();

        $this->addCategories($manager, $categories);

        $manager->flush();
    }


    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadAccountUserData',
            'OroB2B\Bundle\AccountBundle\Tests\Functional\DataFixtures\LoadGroups',
        ];
    }

    /**
     * @param ObjectManager $manager
     * @param array $categories
     */
    protected function addCategories(ObjectManager $manager, array $categories)
    {
        /** @var AccountUser $user */
        $user = $this->getReference(LoadAccountUserData::EMAIL);
        $account = $user->getAccount();

        foreach ($categories as $category_name => $categoryData) {
            $category = new Category();
            $this->setTitle($category, $category_name);

            if ($categoryData['parent_category'] == 'root') {
                /** @var CategoryRepository $categoryRepository */
                $categoryRepository = $manager->getRepository('OroB2BCatalogBundle:Category');
                $parentCategory = $categoryRepository->getMasterCatalogRoot();
            } else {
                $parentCategory = $this->getReference($categoryData['parent_category']);
            }

            $category->setParentCategory($parentCategory);

            if (isset($categoryData['to_all'])) {
                $this->setCategoryVisibility($manager, $category, $categoryData['to_all']);
            }

            if ($categoryData['to_group']) {
                $this->setAccountGroupCategoryVisibility(
                    $manager,
                    $category,
                    $account->getGroup(),
                    $categoryData['to_group']
                );
            }

            if ($categoryData['to_account']) {
                $this->setAccountCategoryVisibility(
                    $manager,
                    $category,
                    $account,
                    $categoryData['to_account']
                );
            }

            $this->addReference($category_name, $category);

            $manager->persist($category);
        }
    }

    /**
     * @param Category $category
     * @param $title
     */
    protected function setTitle(Category $category, $title)
    {
        $categoryTitle = new LocalizedFallbackValue();
        $categoryTitle->setString($title);

        $category->addTitle($categoryTitle);
    }

    /**
     * @param ObjectManager $manager
     * @param Category $category
     * @param string $visibilityToAll
     */
    protected function setCategoryVisibility(ObjectManager $manager, Category $category, $visibilityToAll)
    {
        $categoryVisibility = new CategoryVisibility();
        $categoryVisibility->setCategory($category);
        $categoryVisibility->setVisibility($visibilityToAll);

        $manager->persist($categoryVisibility);
    }

    /**
     * @param ObjectManager $manager
     * @param Category $category
     * @param AccountGroup $accountGroup
     * @param string $visibilityToAccountGroup
     */
    protected function setAccountGroupCategoryVisibility(
        ObjectManager $manager,
        Category $category,
        AccountGroup $accountGroup,
        $visibilityToAccountGroup
    ) {
        $accountGroupCategoryVisibility = new AccountGroupCategoryVisibility();
        $accountGroupCategoryVisibility->setCategory($category);

        $accountGroupCategoryVisibility->setAccountGroup($accountGroup);
        $accountGroupCategoryVisibility->setVisibility($visibilityToAccountGroup);

        $manager->persist($accountGroupCategoryVisibility);
    }

    /**
     * @param ObjectManager $manager
     * @param Category $category
     * @param Account $account
     * @param string $visibilityToAccount
     */
    protected function setAccountCategoryVisibility(
        ObjectManager $manager,
        Category $category,
        Account $account,
        $visibilityToAccount
    ) {
        $accountGroupCategoryVisibility = new AccountCategoryVisibility();
        $accountGroupCategoryVisibility->setCategory($category);
        $accountGroupCategoryVisibility->setAccount($account);
        $accountGroupCategoryVisibility->setVisibility($visibilityToAccount);

        $manager->persist($accountGroupCategoryVisibility);
    }

    /**
     * @return array
     */
    protected function getCategoryVisibilityData()
    {
        $filePath = __DIR__.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'visibilities.yml';

        return Yaml::parse($filePath);
    }
}
