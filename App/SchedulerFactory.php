<?php
namespace Quartz\App;

use Enqueue\SimpleClient\SimpleClient;
use Quartz\App\Async\AsyncJobRunShell;
use Quartz\App\Async\JobRunShellProcessor;
use Quartz\Core\JobRunShellFactory;
use Quartz\Core\Scheduler;
use Quartz\Core\SchedulerFactory as BaseSchedulerFactory;
use Quartz\Core\SimpleJobFactory;
use Quartz\Core\StdJobRunShell;
use Quartz\Core\StdJobRunShellFactory;
use Quartz\Store\YadmStore;
use Quartz\Store\YadmStoreResource;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SchedulerFactory implements BaseSchedulerFactory
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var Scheduler
     */
    private $scheduler;

    /**
     * @var YadmStore
     */
    private $store;

    /**
     * @var SimpleClient
     */
    private $enqueue;

    /**
     * @var SimpleJobFactory
     */
    private $jobFactory;

    /**
     * @var JobRunShellFactory
     */
    private $jobRunShellFactory;

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getScheduler()
    {
        if (null == $this->scheduler) {
            $eventDispatcher = new EventDispatcher();

            $this->scheduler = new Scheduler(
                $this->getStore(),
                $this->getJobRunShellFactory(),
                $this->getJobFactory(),
                $eventDispatcher
            );
        }

        return $this->scheduler;
    }

    public function getJobRunShellFactory()
    {
        if (null == $this->jobRunShellFactory) {
            $runJobShell = new AsyncJobRunShell($this->getEnqueue()->getProducer());
            $this->jobRunShellFactory = new StdJobRunShellFactory($runJobShell);
        }

        return $this->jobRunShellFactory;
    }

    /**
     * @return SimpleJobFactory
     */
    public function getJobFactory()
    {
        if (null == $this->jobFactory) {
            $job = new EnqueueResponseJob($this->getEnqueue()->getProducer());

            $this->jobFactory = new SimpleJobFactory([
                EnqueueResponseJob::class => $job,
            ]);
        }

        return $this->jobFactory;
    }


    /**
     * @return JobRunShellProcessor
     */
    public function getJobRunShellProcessor()
    {
        $jobRunShell = new StdJobRunShell();
        $jobRunShell->initialize($this->getScheduler());

        return new JobRunShellProcessor($this->getStore(), $jobRunShell);
    }

    /**
     * @return YadmStore
     */
    public function getStore()
    {
        if (null == $this->store) {
            $config = isset($this->config['store']) ? $this->config['store'] : [];
            $this->store = new YadmStore(new YadmStoreResource($config));
        }

        return $this->store;
    }

    /**
     * @return SimpleClient
     */
    public function getEnqueue()
    {
        if (null == $this->enqueue) {
            $config = isset($this->config['enqueue']) ? $this->config['enqueue'] : [];
            $this->enqueue = new SimpleClient($config);
        }

        return $this->enqueue;
    }
}