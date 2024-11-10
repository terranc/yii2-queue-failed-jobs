# Yii2 Queue Failed Jobs

这个扩展为 Yii2 Queue 提供了失败任务处理功能。

## 安装
```
composer require terranc/yii2-queue-failed-jobs
```

```php
// main.php
return [
    'components' => [
        'queue' => [
            'class' => \yii\queue\db\Queue::class,
            'as log' => \yii\queue\LogBehavior::class,
            'as errorLog' => \terranc\Yii2QueueFailedJob\SaveFailedJobsBehavior::class,     // Add this line of configuration
        ],
    ],
];
```