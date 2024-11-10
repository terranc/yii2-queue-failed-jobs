<?php
namespace terranc\Yii2QueueFailedJob\controllers;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\Console;

/**
 * 管理失败的队列任务
 */
class FailedQueueController extends Controller
{
    public $defaultAction = 'list';
    protected $jobStartedAt;
    public $failedJobsTable = 'failed_jobs';

    /**
     * 显示所有失败的任务
     */
    public function actionList()
    {
        $query = \Yii::$app->db->createCommand('SELECT * FROM ' . $this->failedJobsTable . ' order by id desc limit 500');
        $messages = $query->queryAll();

        if (!$messages) {
            $this->stdout("没有找到失败的任务\n");
            return ExitCode::OK;
        }

        $rows = [];
        foreach ($messages as $message) {
            $payload = json_decode($message['payload'], true);
            $rows[] = [
                $message['id'],
                $payload['data']['commandName'],
                $message['queue_name'],
                $this->formatDate($message['failed_at'])
            ];
        }

        $table = (new Table())
            ->setHeaders(['Id', 'Class', 'Queue', 'Failed at'])
            ->setRows($rows)
            ->run();
        $this->stdout($table);

        return ExitCode::OK;
    }

    /**
     * 显示任务详细信息
     * @param string $id 任务ID
     */
    public function actionInfo($id)
    {
        $message = $this->getMessageById($id);
        if (!$message) {
            $this->stdout("任务未找到\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $payload = json_decode($message['payload'], true);

        $this->stdout("Id", Console::FG_BLUE, Console::BOLD);
        $this->stdout(PHP_EOL);
        $this->stdout($message['id']);
        $this->stdout(PHP_EOL . PHP_EOL);

        $this->stdout("Queue", Console::FG_BLUE, Console::BOLD);
        $this->stdout(PHP_EOL);
        $this->stdout($message['queue_name']);
        $this->stdout(PHP_EOL . PHP_EOL);

        $this->stdout("Class", Console::FG_BLUE, Console::BOLD);
        $this->stdout(PHP_EOL);
        $this->stdout($payload['data']['commandName']);
        $this->stdout(PHP_EOL . PHP_EOL);

        $this->stdout("Params", Console::FG_BLUE, Console::BOLD);
        $this->stdout(PHP_EOL);
        $this->stdout(json_encode(get_object_vars(unserialize($payload['data']['command']))));
        $this->stdout(PHP_EOL . PHP_EOL);

        $this->stdout("Failed at", Console::FG_BLUE, Console::BOLD);
        $this->stdout(PHP_EOL);
        $this->stdout($this->formatDate($message['failed_at']));
        $this->stdout(PHP_EOL . PHP_EOL);

        $this->stdout("Error", Console::FG_BLUE, Console::BOLD);
        $this->stdout(PHP_EOL);
        $this->stdout($message['exception']);
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * 执行指定ID的任务，传`all`表示所有任务
     * @param $id 任务ID
     */
    public function actionRetry($id)
    {
        if (is_numeric($id)) {
            $message = $this->getMessageById($id);
            if (!$message) {
                $this->stdout("任务未找到\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->execute($message);
            return ExitCode::OK;
        } elseif ($id === 'all') {
            return $this->actionRetryAll();
        }
    }

    /**
     * 重试所有失败的任务
     */
    protected function actionRetryAll()
    {
        $query = \Yii::$app->db->createCommand('SELECT * FROM ' . $this->failedJobsTable);
        $messages = $query->queryAll();

        if (!$messages) {
            $this->stdout("没有找到失败的任务\n");
            return ExitCode::OK;
        }

        $total = count($messages);
        $success = 0;
        $failed = 0;

        foreach ($messages as $message) {
            if ($this->execute($message)) {
                $success++;
            } else {
                $failed++;
            }
        }

        $this->stdout("\n总计: ", Console::BOLD);
        $this->stdout($total);
        $this->stdout("\n成功: ", Console::BOLD);
        $this->stdout($success);
        $this->stdout("\n失败: ", Console::BOLD);
        $this->stdout($failed . "\n");

        return ExitCode::OK;
    }

    /**
     * 清除失败任务
     */
    public function actionClear()
    {
        $query = \Yii::$app->db->createCommand();
        $count = $query->delete($this->failedJobsTable)->execute();

        if ($count) {
            $this->stdout("已删除 $count 个任务\n", Console::FG_GREEN);
        } else {
            $this->stdout("没有找到任务\n");
        }

        return ExitCode::OK;
    }

    protected function getMessageById($id)
    {
        return \Yii::$app->db->createCommand('SELECT * FROM ' . $this->failedJobsTable . ' WHERE id = :id')
            ->bindValue(':id', $id)
            ->queryOne();
    }

    protected function execute($message)
    {
        try {
            $this->jobStartedAt = microtime(true);
            $payload = json_decode($message['payload'], true);

            $this->logStart($message);

            $job = unserialize($payload['data']['command']);
            $jobClass = $payload['data']['commandName'];
            $params = get_object_vars($job);
            if (!method_exists($jobClass, 'dispatch')) {
                throw new \Exception('The job class must implement the dispatchSync() method');
            }
            $jobClass::dispatch($params);
            \Yii::$app->db->createCommand()
                ->delete($this->failedJobsTable, ['id' => $message['id']])
                ->execute();

            $this->logDone($message);
            return true;
        } catch (\Throwable $e) {
            $this->logError($message, $e);
            return false;
        }
    }

    protected function logStart($message)
    {
        $payload = json_decode($message['payload'], true);
        $this->stdout(date('Y-m-d H:i:s'), Console::FG_YELLOW);
        $this->stdout(" [{$message['id']}] {$payload['data']['commandName']}", Console::FG_GREY);
        $this->stdout(' - ', Console::FG_YELLOW);
        $this->stdout("Started\n", Console::FG_GREEN);
    }

    protected function logDone($message)
    {
        $payload = json_decode($message['payload'], true);
        $duration = number_format(round(microtime(true) - $this->jobStartedAt, 3), 3);
        $memory = round(memory_get_peak_usage(false)/1024/1024, 2);

        $this->stdout(date('Y-m-d H:i:s'), Console::FG_YELLOW);
        $this->stdout(" [{$message['id']}] {$payload['data']['commandName']}", Console::FG_GREY);
        $this->stdout(' - ', Console::FG_YELLOW);
        $this->stdout('Done', Console::FG_GREEN);
        $this->stdout(" ($duration s, $memory MiB)\n", Console::FG_YELLOW);
    }

    protected function logError($message, \Throwable $error)
    {
        $payload = json_decode($message['payload'], true);
        $duration = number_format(round(microtime(true) - $this->jobStartedAt, 3), 3);

        $this->stdout(date('Y-m-d H:i:s'), Console::FG_YELLOW);
        $this->stdout(" [{$message['id']}] {$payload['data']['commandName']}", Console::FG_GREY);
        $this->stdout(' - ', Console::FG_YELLOW);
        $this->stdout('Error', Console::BG_RED);
        $this->stdout(" ($duration s)\n", Console::FG_YELLOW);
        $this->stdout('> ' . get_class($error) . ': ', Console::FG_RED);
        $this->stdout($error->getMessage() . "\n", Console::FG_GREY);
    }

    protected function formatDate($date)
    {
        return \Yii::$app->formatter->asDatetime($date, 'php:Y-m-d H:i:s');
    }
}
