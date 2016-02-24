<?php

namespace OroB2B\Bundle\ProductBundle\Tests\Unit\Form\Handler;

use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

use OroB2B\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorRegistry;
use OroB2B\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorInterface;
use OroB2B\Bundle\ProductBundle\Entity\Product;
use OroB2B\Bundle\ProductBundle\Form\Type\QuickAddType;
use OroB2B\Bundle\ProductBundle\Form\Handler\QuickAddHandler;
use OroB2B\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorRegistry;
use OroB2B\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorInterface;
use OroB2B\Bundle\ProductBundle\Provider\QuickAddFormProvider;
use OroB2B\Bundle\ProductBundle\Model\Builder\QuickAddRowCollectionBuilder;
use OroB2B\Bundle\ProductBundle\Model\QuickAddRowCollection;
use OroB2B\Bundle\ProductBundle\Storage\ProductDataStorage;

class QuickAddHandlerTest extends \PHPUnit_Framework_TestCase
{
        const COMPONENT_NAME = 'component';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|TranslatorInterface
         */
        protected $translator;

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|UrlGeneratorInterface
         */
        protected $router;

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|QuickAddFormProvider
         */
        protected $formProvider;

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|ComponentProcessorRegistry
         */
        protected $componentRegistry;

        /**
         * @var QuickAddHandler
         */
        protected $handler;

        /**
         * @var QuickAddRowCollectionBuilder|\PHPUnit_Framework_MockObject_MockObject
         */
        protected $collectionBuilder;

        /**
         * @var ObjectManager|\PHPUnit_Framework_MockObject_MockObject
         */
        protected $manager;

        /**
         * @var QuickAddRowCollection|\PHPUnit_Framework_MockObject_MockObject
         */
        protected $quickAddRowCollection;

        protected function setUp()
        {
                $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
                $this->translator->expects($this->any())
                    ->method('trans')
                    ->willReturnCallback(
                        function ($message) {
                                return $message . '.trans';
                        }
                    );

                $this->formProvider = $this->getMockBuilder('OroB2B\Bundle\ProductBundle\Provider\QuickAddFormProvider')
                    ->disableOriginalConstructor()
                    ->getMock();
                $this->router = $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface');

                $this->componentRegistry = $this
                    ->getMockBuilder('OroB2B\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorRegistry')
                    ->disableOriginalConstructor()
                    ->getMock();

                $this->collectionBuilder = $this
                    ->getMockBuilder('OroB2B\Bundle\ProductBundle\Model\Builder\QuickAddRowCollectionBuilder')
                    ->disableOriginalConstructor()
                    ->getMock();

                $this->handler = new QuickAddHandler(
                    $this->formProvider,
                    $this->componentRegistry,
                    $this->getManagerRegistry(),
                    $this->router,
                    $this->translator,
                    $this->collectionBuilder
                );
        }

        public function testProcessGetRequest()
        {
                $request = Request::create('/get');

                $form = $this->getMock('Symfony\Component\Form\FormInterface');

                $this->formProvider->expects($this->never())
                    ->method('getForm')
                    ->with([])
                    ->willReturn($form);

                $this->assertEquals(null, $this->handler->process($request, 'reload'));
        }

        public function testProcessNoProcessor()
        {
                $request = Request::create('/post/no-processor', 'POST');
                $request->setSession($this->getSessionWithErrorMessage());

                $this->prepareCollectionBuilder($request, []);

                $form = $this->getMock('Symfony\Component\Form\FormInterface');
                $form->expects($this->once())
                    ->method('submit')
                    ->with($request);

                $this->formProvider->expects($this->once())
                    ->method('getForm')
                    ->with(['products' => []])
                    ->willReturn($form);

                $this->assertEquals(null, $this->handler->process($request, 'reload'));
        }

        public function testProcessNotAllowedProcessor()
        {
                $request = Request::create('/post/not-allowed-processor', 'POST');
                $request->request->set(QuickAddType::NAME, [QuickAddType::COMPONENT_FIELD_NAME => self::COMPONENT_NAME]);
                $request->setSession($this->getSessionWithErrorMessage());

                $this->prepareCollectionBuilder($request, []);

                $form = $this->getMock('Symfony\Component\Form\FormInterface');
                $form->expects($this->once())
                    ->method('submit')
                    ->with($request);

                $this->formProvider->expects($this->once())
                    ->method('getForm')
                    ->with(['validation_required' => false, 'products' => []])
                    ->willReturn($form);

                $processor = $this->getProcessor(false, false);
                $processor->expects($this->never())
                    ->method('process');

                $this->assertEquals(null, $this->handler->process($request, 'reload'));
        }

        public function testProcessInvalidForm()
        {
                $request = Request::create('/post/invalid-form', 'POST');
                $request->request->set(
                    QuickAddType::NAME,
                    [
                        QuickAddType::PRODUCTS_FIELD_NAME => [
                            ['productSku' => 'sku1'],
                            ['productSku' => 'sku2'],
                        ],
                        QuickAddType::COMPONENT_FIELD_NAME => self::COMPONENT_NAME,
                    ]
                );

                $product = new Product();
                $product->setSku('SKU1');

                $this->prepareCollectionBuilder($request, ['SKU1' => $product]);

                $form = $this->getMock('Symfony\Component\Form\FormInterface');
                $form->expects($this->once())
                    ->method('submit')
                    ->with($request);
                $form->expects($this->once())
                    ->method('isValid')
                    ->willReturn(false);

                $this->formProvider->expects($this->once())
                    ->method('getForm')
                    ->with(['validation_required' => true, 'products' => ['SKU1' => $product]])
                    ->willReturn($form);

                $processor = $this->getProcessor();
                $processor->expects($this->never())
                    ->method('process');

                $this->assertEquals(null, $this->handler->process($request, 'reload', 'reload'));
        }

        public function testProcessValidDataWithoutResponse()
        {
                $request = Request::create('/post/valid-without-response', 'POST');
                $request->request->set(QuickAddType::NAME, [QuickAddType::COMPONENT_FIELD_NAME => self::COMPONENT_NAME]);

                $products = [['sku' => '111', 'qty' => 123], ['sku' => '222', 'qty' => 234]];

                $productsForm = $this->getMock('Symfony\Component\Form\FormInterface');
                $productsForm->expects($this->once())
                    ->method('getData')
                    ->willReturn($products);

                $this->prepareCollectionBuilder($request, []);

                $mainForm = $this->getMock('Symfony\Component\Form\FormInterface');
                $mainForm->expects($this->once())
                    ->method('submit')
                    ->with($request);
                $mainForm->expects($this->once())
                    ->method('isValid')
                    ->willReturn(true);
                $mainForm->expects($this->once())
                    ->method('get')
                    ->with(QuickAddType::PRODUCTS_FIELD_NAME)
                    ->willReturn($productsForm);

                $this->formProvider->expects($this->at(0))
                    ->method('getForm')
                    ->with(['validation_required' => true, 'products' => []])
                    ->willReturn($mainForm);

                $processor = $this->getProcessor();
                $processor->expects($this->once())
                    ->method('process')
                    ->with(
                        [
                            ProductDataStorage::ENTITY_ITEMS_DATA_KEY => $products,
                            ProductDataStorage::ADDITIONAL_DATA_KEY => null
                        ],
                        $request
                    );

                $this->router->expects($this->once())->method('generate')->with('reload')->willReturn('/reload');
                $this->assertEquals(new RedirectResponse('/reload'), $this->handler->process($request, 'reload'));
        }

        public function testProcessValidDataWithResponse()
        {
                $request = Request::create('/post/valid-with-response', 'POST');
                $request->request->set(QuickAddType::NAME, [QuickAddType::COMPONENT_FIELD_NAME => self::COMPONENT_NAME]);

                $response = new RedirectResponse('/processor-redirect');

                $products = [['sku' => '111', 'qty' => 123], ['sku' => '222', 'qty' => 234]];

                $productsForm = $this->getMock('Symfony\Component\Form\FormInterface');
                $productsForm->expects($this->once())
                    ->method('getData')
                    ->willReturn($products);

                $this->prepareCollectionBuilder($request, []);

                $form = $this->getMock('Symfony\Component\Form\FormInterface');
                $form->expects($this->once())
                    ->method('submit')
                    ->with($request);
                $form->expects($this->once())
                    ->method('isValid')
                    ->willReturn(true);
                $form->expects($this->once())
                    ->method('get')
                    ->with(QuickAddType::PRODUCTS_FIELD_NAME)
                    ->willReturn($productsForm);

                $this->formProvider->expects($this->once())
                    ->method('getForm')
                    ->with(['validation_required' => true, 'products' => []])
                    ->willReturn($form);

                $processor = $this->getProcessor();
                $processor->expects($this->once())
                    ->method('process')
                    ->with(
                        [
                            ProductDataStorage::ENTITY_ITEMS_DATA_KEY => $products,
                            ProductDataStorage::ADDITIONAL_DATA_KEY => null
                        ],
                        $request
                    )
                    ->willReturn($response);

                $this->assertEquals($response, $this->handler->process($request, 'reload'));
        }

        /**
         * @return \PHPUnit_Framework_MockObject_MockObject|Session
         */
        protected function getSessionWithErrorMessage()
        {
                $flashBag = $this->getMock('Symfony\Component\HttpFoundation\Session\Flash\FlashBag');
                $flashBag->expects($this->once())
                    ->method('add')
                    ->with('error', 'orob2b.product.frontend.quick_add.messages.component_not_accessible.trans');

                $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')
                    ->disableOriginalConstructor()
                    ->getMock();
                $session->expects($this->any())
                    ->method('getFlashBag')
                    ->willReturn($flashBag);

                return $session;
        }

        /**
         * @param bool $isValidationRequired
         * @param bool $isAllowed
         * @return \PHPUnit_Framework_MockObject_MockObject|ComponentProcessorInterface
         */
        protected function getProcessor($isValidationRequired = true, $isAllowed = true)
        {
                $processor = $this->getMock('OroB2B\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorInterface');
                $processor->expects($this->any())
                    ->method('isValidationRequired')
                    ->willReturn($isValidationRequired);
                $processor->expects($this->any())
                    ->method('isAllowed')
                    ->willReturn($isAllowed);

                $this->componentRegistry->expects($this->once())
                    ->method('getProcessorByName')
                    ->with(self::COMPONENT_NAME)
                    ->willReturn($processor);

                return $processor;
        }

        /**
         * @param Request $request
         * @param array $products
         */
        protected function prepareCollectionBuilder(Request $request, array $products)
        {
                $this->collectionBuilder->expects($this->once())
                    ->method('buildFromRequest')
                    ->with($request)
                    ->willReturn($this->prepareCollection($products));
        }

        /**
         * @param array $products
         * @return \PHPUnit_Framework_MockObject_MockObject
         */
        protected function prepareCollection(array $products)
        {
                if (!$this->quickAddRowCollection) {
                        $this->quickAddRowCollection = $this->getMock('OroB2B\Bundle\ProductBundle\Model\QuickAddRowCollection');
                        $this->quickAddRowCollection->expects($this->once())
                            ->method('getProducts')
                            ->withAnyParameters()
                            ->willReturn($products);
                }

                return $this->quickAddRowCollection;
        }
}
