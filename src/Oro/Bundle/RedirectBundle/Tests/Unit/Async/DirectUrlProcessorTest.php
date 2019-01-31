<?php

namespace Oro\Bundle\RedirectBundle\Tests\Unit\Async;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractDriverException;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\DatabaseExceptionHelper;
use Oro\Bundle\RedirectBundle\Async\DirectUrlProcessor;
use Oro\Bundle\RedirectBundle\Async\Topics;
use Oro\Bundle\RedirectBundle\Cache\UrlCacheInterface;
use Oro\Bundle\RedirectBundle\Entity\SluggableInterface;
use Oro\Bundle\RedirectBundle\Generator\SlugEntityGenerator;
use Oro\Bundle\RedirectBundle\Model\Exception\InvalidArgumentException;
use Oro\Bundle\RedirectBundle\Model\MessageFactoryInterface;
use Oro\Bundle\RedirectBundle\Tests\Unit\Stub\UrlCacheAllCapabilities;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class DirectUrlProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ManagerRegistry|\PHPUnit_Framework_MockObject_MockObject
     */
    private $registry;

    /**
     * @var SlugEntityGenerator|\PHPUnit_Framework_MockObject_MockObject
     */
    private $generator;

    /**
     * @var MessageFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $messageFactory;

    /**
     * @var DatabaseExceptionHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $databaseExceptionHelper;

    /**
     * @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * @var DirectUrlProcessor
     */
    private $processor;

    /**
     * @var UrlCacheInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $urlCache;

    protected function setUp()
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->generator = $this->createMock(SlugEntityGenerator::class);
        $this->messageFactory = $this->createMock(MessageFactoryInterface::class);
        $this->databaseExceptionHelper = $this->createMock(DatabaseExceptionHelper::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->urlCache = $this->createMock(UrlCacheInterface::class);

        $this->processor = new DirectUrlProcessor(
            $this->registry,
            $this->generator,
            $this->messageFactory,
            $this->databaseExceptionHelper,
            $this->logger,
            $this->urlCache
        );
    }

    public function testProcessInvalidMessage()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        $exception = new InvalidArgumentException('Test');
        $message = $this->prepareMessageTrowingException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Queue Message is invalid',
                [
                    'exception' => $exception
                ]
            );

        $this->assertEquals(DirectUrlProcessor::REJECT, $this->processor->process($message, $session));
    }

    public function testProcessInvalidMessageOnGetEntity()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        $exception = new InvalidArgumentException('Test');
        $message = $this->prepareMessageTrowingException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Queue Message is invalid',
                [
                    'exception' => $exception,
                ]
            );

        $this->assertEquals(DirectUrlProcessor::REJECT, $this->processor->process($message, $session));
    }

    public function testProcessExceptionOutsideTransaction()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        $message = $this->prepareMessage();
        $exception = new \Exception('Test');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during Direct URL generation',
                ['exception' => $exception]
            );

        $this->assertEquals(DirectUrlProcessor::REJECT, $this->processor->process($message, $session));
    }

    public function testProcessExceptionInTransaction()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        $exception = new \Exception('Test');
        $message = $this->prepareMessage();
        $this->generator->expects($this->once())
            ->method('generate')
            ->willThrowException($exception);

        $this->assertTransactionRollback();

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during Direct URL generation',
                ['exception' => $exception]
            );

        $this->assertEquals(DirectUrlProcessor::REJECT, $this->processor->process($message, $session));
    }

    public function testProcessExceptionInClosedTransaction()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        $exception = new \Exception('Test');
        $message = $this->prepareMessage();
        $this->generator->expects($this->once())
            ->method('generate')
            ->willThrowException($exception);

        $em = $this->assertTransactionStarted();

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->once())
            ->method('getTransactionNestingLevel')
            ->willReturn(0);
        $em->expects($this->once())
            ->method('getConnection')
            ->willReturn($conn);

        $em->expects($this->never())
            ->method('rollback');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during Direct URL generation',
                ['exception' => $exception]
            );

        $this->assertEquals(DirectUrlProcessor::REJECT, $this->processor->process($message, $session));
    }

    public function testProcessExceptionDeadlockInTransaction()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        /** @var AbstractDriverException|\PHPUnit_Framework_MockObject_MockObject $exception */
        $exception = $this->getMockBuilder(AbstractDriverException::class)
            ->disableOriginalConstructor()
            ->getMock();

        $message = $this->prepareMessage();
        $this->generator->expects($this->once())
            ->method('generate')
            ->willThrowException($exception);

        $this->assertTransactionRollback();

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during Direct URL generation',
                ['exception' => $exception]
            );

        $this->databaseExceptionHelper->expects($this->once())
            ->method('getDriverException')
            ->with($exception)
            ->willReturn($exception);

        $this->databaseExceptionHelper->expects($this->once())
            ->method('isDeadlock')
            ->with($exception)
            ->willReturn(true);

        $this->assertEquals(DirectUrlProcessor::REQUEUE, $this->processor->process($message, $session));
    }

    public function testProcessExceptionDriverExceptionInTransaction()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);

        /** @var AbstractDriverException|\PHPUnit_Framework_MockObject_MockObject $exception */
        $exception = $this->getMockBuilder(AbstractDriverException::class)
            ->disableOriginalConstructor()
            ->getMock();

        $message = $this->prepareMessage();
        $this->generator->expects($this->once())
            ->method('generate')
            ->willThrowException($exception);

        $this->assertTransactionRollback();

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during Direct URL generation',
                ['exception' => $exception]
            );

        $this->databaseExceptionHelper->expects($this->once())
            ->method('getDriverException')
            ->with($exception)
            ->willReturn($exception);

        $this->databaseExceptionHelper->expects($this->once())
            ->method('isDeadlock')
            ->with($exception)
            ->willReturn(false);

        $this->assertEquals(DirectUrlProcessor::REJECT, $this->processor->process($message, $session));
    }

    /**
     * @dataProvider processProvider
     * @param bool $createRedirect
     */
    public function testProcess($createRedirect)
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);
        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);

        $this->urlCache->expects($this->once())
            ->method('removeUrl')
            ->with(UrlCacheInterface::SLUG_ROUTES_KEY, []);
        $this->assertProcessorSuccessfulCalled($message, $createRedirect);

        $this->assertEquals(DirectUrlProcessor::ACK, $this->processor->process($message, $session));
    }

    public function testProcessWithFlushableCache()
    {
        /** @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject $session */
        $session = $this->createMock(SessionInterface::class);
        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);

        $this->assertProcessorSuccessfulCalled($message, false);

        $urlCache = $this->createMock(UrlCacheAllCapabilities::class);
        $urlCache->expects($this->once())
            ->method('removeUrl')
            ->with(UrlCacheInterface::SLUG_ROUTES_KEY, []);
        $urlCache->expects($this->once())
            ->method('flushAll');
        $processor = new DirectUrlProcessor(
            $this->registry,
            $this->generator,
            $this->messageFactory,
            $this->databaseExceptionHelper,
            $this->logger,
            $urlCache
        );

        $this->assertEquals(DirectUrlProcessor::ACK, $processor->process($message, $session));
    }

    /**
     * @return array
     */
    public function processProvider()
    {
        return [
            'create redirect true' => [
                'createRedirect' => true,
            ],
            'create redirect false' => [
                'createRedirect' => false,
            ],
        ];
    }

    public function testGetSubscribedTopics()
    {
        $this->assertEquals([Topics::GENERATE_DIRECT_URL_FOR_ENTITIES], $this->processor->getSubscribedTopics());
    }

    /**
     * @param \Exception $exception
     * @return MessageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function prepareMessageTrowingException(\Exception $exception)
    {
        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode(['class' => \stdClass::class, 'id' => null]));

        $this->messageFactory->expects($this->once())
            ->method('getEntityClassFromMessage')
            ->willThrowException($exception);

        return $message;
    }

    /**
     * @return MessageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function prepareMessage()
    {
        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);

        $class = \stdClass::class;
        $messageData = ['class' => $class, 'id' => null];
        $message->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageData));

        $this->messageFactory->expects($this->once())
            ->method('getEntityClassFromMessage')
            ->with($messageData)
            ->willReturn($class);

        $this->messageFactory->expects($this->once())
            ->method('getCreateRedirectFromMessage')
            ->willReturn(true);

        $entity = $this->createMock(SluggableInterface::class);
        $this->messageFactory->expects($this->once())
            ->method('getEntitiesFromMessage')
            ->with($messageData)
            ->willReturn([$entity]);

        return $message;
    }

    private function assertTransactionCommitted()
    {
        $em = $this->assertTransactionStarted();
        $em->expects($this->once())
            ->method('commit');
    }

    private function assertTransactionRollback()
    {
        $em = $this->assertTransactionStarted();

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->once())
            ->method('getTransactionNestingLevel')
            ->willReturn(1);
        $em->expects($this->once())
            ->method('getConnection')
            ->willReturn($conn);

        $em->expects($this->once())
            ->method('rollback');
    }

    /**
     * @param bool $createRedirect
     * @param string $messageData
     * @param string $class
     * @param object $entity
     */
    private function assertMessageFactoryCallsDuringProcess(
        $createRedirect,
        $messageData,
        $class,
        $entity
    ) {
        $this->messageFactory->expects($this->once())
            ->method('getEntityClassFromMessage')
            ->with($messageData)
            ->willReturn($class);

        $this->messageFactory->expects($this->once())
            ->method('getEntitiesFromMessage')
            ->with($messageData)
            ->willReturn([$entity]);

        $this->messageFactory->expects($this->once())
            ->method('getCreateRedirectFromMessage')
            ->with($messageData)
            ->willReturn($createRedirect);
    }

    /**
     * @param MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message
     * @param bool $createRedirect
     */
    private function assertProcessorSuccessfulCalled($message, $createRedirect)
    {
        $class = \stdClass::class;
        /** @var SluggableInterface $entity */
        $entity = $this->createMock(SluggableInterface::class);
        $messageData = ['class' => $class, 'id' => null];
        $messageBody = json_encode($messageData);
        $message->expects($this->any())
            ->method('getBody')
            ->willReturn($messageBody);

        $this->assertTransactionCommitted();
        $this->assertMessageFactoryCallsDuringProcess($createRedirect, $messageData, $class, $entity);

        $this->generator->expects($this->once())
            ->method('generate')
            ->with($entity, $createRedirect);
    }

    /**
     * @return EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private function assertTransactionStarted()
    {
        /** @var EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('beginTransaction');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($em);

        return $em;
    }
}
