<?php
namespace Quartz\Bridge\Tests\Scheduler;

use Enqueue\Client\CommandSubscriberInterface;
use Enqueue\Consumption\QueueSubscriberInterface;
use Enqueue\Consumption\Result;
use Enqueue\Null\NullMessage;
use Enqueue\Util\JSON;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrProcessor;
use PHPUnit\Framework\TestCase;
use Quartz\Bridge\Scheduler\JobRunShellProcessor;
use Quartz\Scheduler\StdJobRunShell;
use Quartz\Scheduler\Store\YadmStore;
use Quartz\Triggers\SimpleTrigger;

class JobRunShellProcessorTest extends TestCase
{
    public function testShouldImplementPsrProcessorInterface()
    {
        $processor = new JobRunShellProcessor($this->createJobStore(), $this->createJobRunShell());

        $this->assertInstanceOf(PsrProcessor::class, $processor);
    }

    public function testShouldImplementCommandSubscriberInterfaceAndReturnExpectectedSubscribedCommand()
    {
        $processor = new JobRunShellProcessor($this->createJobStore(), $this->createJobRunShell());

        $this->assertInstanceOf(CommandSubscriberInterface::class, $processor);

        $expectedConfig = [
            'processorName' => 'quartz_job_run_shell',
            'queueName' => 'quartz_job_run_shell',
            'queueNameHardcoded' => true,
            'exclusive' => true,
        ];

        $this->assertSame($expectedConfig, JobRunShellProcessor::getSubscribedCommand());
    }

    public function testShouldImplementQueueSubscriberInterfaceAndReturnExpectectedSubscribedCommand()
    {
        $processor = new JobRunShellProcessor($this->createJobStore(), $this->createJobRunShell());

        $this->assertInstanceOf(QueueSubscriberInterface::class, $processor);

        $this->assertSame(['quartz_job_run_shell'], JobRunShellProcessor::getSubscribedQueues());
    }

    public function testShouldRejectMessageIfJobInstanceIdIsNotSet()
    {
        $store = $this->createJobStore();
        $store
            ->expects($this->never())
            ->method('retrieveFireTrigger')
        ;

        $shell = $this->createJobRunShell();
        $shell
            ->expects($this->never())
            ->method('execute')
        ;

        $processor = new JobRunShellProcessor($store, $shell);

        $result = $processor->process(new NullMessage(), $this->createMock(PsrContext::class));

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame('fire instance id is empty', $result->getReason());
    }

    public function testShouldRejectMessageIfJobInstanceWasNotFound()
    {
        $store = $this->createJobStore();
        $store
            ->expects($this->once())
            ->method('retrieveFireTrigger')
        ;

        $shell = $this->createJobRunShell();
        $shell
            ->expects($this->never())
            ->method('execute')
        ;

        $processor = new JobRunShellProcessor($store, $shell);

        $message = new NullMessage();
        $message->setBody(JSON::encode([
            'fireInstanceId' => '1234',
        ]));

        $result = $processor->process($message, $this->createMock(PsrContext::class));

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame('There is not trigger with fire instance id: "1234"', $result->getReason());
    }

    public function testShouldPassTriggerToJobRunShell()
    {
        $trigger = new SimpleTrigger();

        $store = $this->createJobStore();
        $store
            ->expects($this->once())
            ->method('retrieveFireTrigger')
            ->willReturn($trigger)
        ;

        $shell = $this->createJobRunShell();
        $shell
            ->expects($this->once())
            ->method('execute')
            ->with($trigger)
        ;

        $processor = new JobRunShellProcessor($store, $shell);

        $message = new NullMessage();
        $message->setBody(JSON::encode([
            'fireInstanceId' => '1234',
        ]));

        $result = $processor->process($message, $this->createMock(PsrContext::class));

        $this->assertSame(Result::ACK, $result);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|YadmStore
     */
    private function createJobStore()
    {
        return $this->createMock(YadmStore::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|StdJobRunShell
     */
    private function createJobRunShell()
    {
        return $this->createMock(StdJobRunShell::class);
    }
}
