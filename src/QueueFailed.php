<?php

namespace terranc\Yii2QueueFailedJobs;

use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApp;
use yii\db\Connection;
use yii\db\Query;
use yii\helpers\Inflector;
use yii\queue\JobInterface;

class QueueFailed extends Component implements BootstrapInterface
{
    /**
     * @var string failed jobs table name
     */
    public $failedJobsTable = "{{%failed_jobs}}";

    /** @var string|array Queue component id */
    public $queue = "queue";
    /**
     * @var string database connection component id
     */
    public $connection = "db";
    /**
     * @var string command class name
     */
    public $commandClass = Command::class;
    /**
     * @var array of additional options of command
     */
    public $commandOptions = [];

    public function bootstrap($app)
    {
        // register console commands
        if ($app instanceof ConsoleApp) {
            $app->controllerMap[$this->getCommandId()] = array_merge(
                [
                    "class" => $this->commandClass,
                    "failedJobsTable" => $this->failedJobsTable,
                    "connection" => $this->connection,
                ],
                $this->commandOptions,
            );
        }

        // attach behavior to each queue components
        $queues = (array) $this->queue;
        foreach ($queues as $queue) {
            \Yii::$app->get($queue)->attachBehavior("queueFailed", [
                "class" => SaveFailedJobsBehavior::class,
                "failedJobsTable" => $this->failedJobsTable,
                "connection" => $this->connection,
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
        throw new InvalidConfigException(
            "QueueFailed must be an application component.",
        );
    }
}
