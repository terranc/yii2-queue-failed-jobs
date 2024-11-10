<?php

namespace terranc\Yii2QueueFailedJobs;

use yii\base\Behavior;
use yii\queue\ExecEvent;
use yii\queue\Queue;

class SaveFailedJobsBehavior extends Behavior
{
    public $failedJobTable = 'failed_jobs';

    public function events()
    {
        return [
            Queue::EVENT_AFTER_ERROR => 'afterError',
        ];
    }

    /**
     * @param ExecEvent $event
     */
    public function afterError(ExecEvent $event)
    {
        if (!$event->retry) {
            $jobClass = get_class($event->job);
//            $jobProperties = get_object_vars($event->job);

            // 构建更丰富的 payload
            $payload = [
                'data' => [
                    'command' => serialize($event->job),
                    'commandName' => $jobClass,
//                    'properties' => $jobProperties,
                ],
                'maxTries' => $event->attempt,
                'queue' => $event->sender->queueName,
                'id' => $event->id,
                'pushedAt' => number_format(microtime(true), 4, '.', ''),
            ];

            \Yii::$app->db->createCommand()->insert($this->failedJobTable, [
                'driver' => get_class($event->sender),
                'queue_name' => $event->sender->queueName,
                'payload' => json_encode($payload),
                'exception' => $event->error->getMessage() . ' in ' . $event->error->getFile() . ':' . $event->error->getLine() . "\n" . "\n" . $event->error->getTraceAsString(),
                'failed_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }
    }
}
