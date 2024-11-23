## Job-queue

### Usage

```php
$job = Job::initNew(
    Uuid::uuid4()->toString(),
    ExampleReverseGeocodeCommand::fromLonLat(0.5, 0.5),
    new \DateTimeImmutable()
);

$jobManager = new JobManager();
$jobManager->enqueueJob($job);
```

### Requirements

* Possible to add additional workers to pool (for some group of tasks too)
* One task can be executed only by one worker at the same time (see acknowledgment + basic_qos)
* Ability to group jobs to chains that will be run sequentially
  examples:
    - https://laravel.com/docs/11.x/queues#job-chaining и https://laravel.com/docs/11.x/queues#chains-and-batches
    - https://docs.celeryq.dev/en/stable/userguide/canvas.html#chains
    - https://github.com/path/android-priority-jobqueue (group)
* Ability to revoke handling job (in long-running iterable handlers too)
* Ability to view errors of handling and react to them
* Ability to re-run jobs

### Wishes

* composer package
* VueJs UI

### TODO

* re-run jobs due deployment stuffs (for example after compatibility-breaking modifications of some JobCommand)
* several implementations of JobCommandFactoryInterface
* more plain command (JobCommandInterface is overweight)

### Solution

* Bus - symfony/messenger bus
* Job - stored in DB (ORM) entity
* JobCommand - DTO that represents Job and will be used for transferring over Bus
* JobManager - service that will be used for enqueue Jobs or chain of Jobs

### Configuration

https://davegebler.com/post/php/how-to-create-a-symfony-5-bundle

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    buses:
      messenger.bus.default:
        middleware:
          - @core-extensions.job_queue.job_middleware

## ????
job_queue:
  jobs_table: "orm_jobs"
```

Shell command to run consumer:

```bash
ExecStart=php bin/console messenger:consume async --time-limit=3600 --id=worker_1
```

