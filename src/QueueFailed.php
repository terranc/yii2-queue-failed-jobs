<?php

namespace terranc\Yii2QueueFailedJobs;

use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApp;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\Inflector;
use yii\queue\JobInterface;

class QueueFailed extends Component implements BootstrapInterface
{
    /**
     * @var Connection|array|string
     */
    public $db = 'db';

    /**
     * @var string failed jobs table name
     */
    public $failedJobsTable = '{{%queue_failed}}';

    /** @var string|array Queue component id */
    public $queue = 'queue';

    /**
     * @var string command class name
     */
    public $commandClass = Command::class;
    /**
     * @var array of additional options of command
     */
    public $commandOptions = [];

    public function init()
    {
        $this->db = Instance::ensure($this->db, Connection::class);
    }

    public function bootstrap($app)
    {
        // register console commands
        if ($app instanceof ConsoleApp) {
            $app->controllerMap[$this->getCommandId()] = [
                    'class' => $this->commandClass,
                    'failedJobTable' => $this->failedJobsTable,
                ] + $this->commandOptions;
        }

        // attach behavior to each queue components
        $queues = (array)$this->queue;
        foreach ($queues as $queue) {
            \Yii::$app->get($queue)->attachBehavior('queueFailed', [
                'class' => SaveFailedJobsBehavior::class,
                'failedJobTable' => $this->failedJobsTable,
                'queue' => $queue,
            ]);
        }
    }


    /**
     * @return string command id
     * @throws
     */
    protected function getCommandId()
    {
        foreach (\Yii::$app->getComponents(false) as $id => $component) {
            if ($component === $this) {
                return Inflector::camel2id($id);
            }
        }
        throw new InvalidConfigException('QueueFailed must be an application component.');
    }
}
