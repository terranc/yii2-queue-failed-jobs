# Yii2 Queue Failed Jobs

这个扩展为 Yii2 Queue 提供了失败任务处理功能。

## Install
```
composer require terranc/yii2-queue-failed-jobs
```

Apply database migration:
```
yii migrate --migrationPath=@vendor/terranc/yii2-queue-failed-jobs/src/migrations/
```


## Configuration
Add queueFailed component to the console application config file:
```
return [
    'components' => [
        'queueFailed' => [
            'class' => terranc\Yii2QueueFailedJobs\QueueFailed::class,
        ],
    ],
];
```

Add queueFailed component to the bootstrap:
```
return [
    'bootstrap' => [
        'queue', 'queueFailed'
    ],
    // ...
]
```

## Usage in console

#### Show all failed jobs:

```shell
yii queue-failed/list

╔════╤═══════════════════════════╤═════════════════╤═════════════════════╗
║ Id │ Class                     │ Original Job ID │ Failed at           ║
╟────┼───────────────────────────┼─────────────────┼─────────────────────╢
║ 1  │ app\models\jobs\FailedJob │ 123456789       │ 2022-06-06 06:14:32 ║
╚════╧═══════════════════════════╧═════════════════╧═════════════════════╝
```

Command displays job ID, job class, original job ID and failure time. The ID may be used to execute failed job again.

#### Show detailed information about a job by ID:

```shell
yii queue-failed/info ID
```

Command displays additional information about the job (job payload and error).


#### Retry execute a job by ID:

```shell
yii queue-failed/retry ID
```

#### Retry execute all jobs:

```shell
yii queue-failed/retry all
```

#### Remove a job by ID:

```shell
yii queue-failed/remove ID
```

#### Clear all failed jobs:

```shell
yii queue-failed/clear
```
Pass --class option to filter jobs by class.

## Notes

Jobs are saved in `queue_failed` table by default.
You can change table name in the config (also you need to change name in migration):

```php
'queueFailed' => [
    'class' => silverslice\queueFailed\QueueFailed::class,
    'failedJobsTable' => 'failed_jobs'
],
```

Extension attaches behavior to save failed jobs to the `queue` component by default.
Change queue component name or add more queue components in the config if you need:

```php
'queueFailed' => [
    'class' => silverslice\queueFailed\QueueFailed::class,
    'queue' => ['queue', 'queueDb'],
],
```

Extension registers its own console commands based on its component id.
You can change it however you like:

```php
'failed' => [
    'class' => silverslice\queueFailed\QueueFailed::class,
    'queue' => ['queue', 'queueDb'],
],
```

Then use in console:

```shell
yii failed/list
```
