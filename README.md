## Job-queue

### Требования

* Одну task выполняет в одно и то же время только 1 worker. В rabbit это решается через acknowledgment + basic_qos.
* Масштабируемость (добавлять workers)
* ~~Возможность назначать задачам группы, указывая что все задачи данной группы должны идти друг за другом в порядке fifo (возможно это легко достижимо посредством  помещения их в определенные channels) ~~ - **изучить***
  вот примеры
- https://laravel.com/docs/11.x/queues#job-chaining и https://laravel.com/docs/11.x/queues#chains-and-batches
- https://docs.celeryq.dev/en/stable/userguide/canvas.html#chains
- https://github.com/path/android-priority-jobqueue (group)

* Возможность задавать больше workers на tasks определенной группы (здесь тоже группа будет нужна ну или другой какой-то идентификатор)
* Возможность как то влиять на задачи (хотя бы отменять), (возможно менять приоритет - хотя наверно проще будет всегда отменить и создать заново)
* Возможность как-то задавать реакцию на возникающие ошибки. Так как у каждого task будет свой TaskHandler - возможно бы будем просто решать эту проблему в нем.
* Логирование процесса обработки в самой задаче? => **нужна таблица?**
* Надежность => Возможность re-run в случае падения rabbit**нужна таблица?**  Более частый пример: после деплоя, когда Job по какой-то причине изменили

### Пожелания

* возможность указать номер задачи в группе?
* мониторинг и логирование - UI  => **нужна таблица?**
* composer пакет, для легкого импорта в minzdrav и поддержки единой кодовой базы
* для UI - как? npm?
* поискать готовые решения Task Queues

### TODO

* подумать что делать с текущими job, в случае если было deploy где job поменяли? отмена + rerun? тогда нужен такой статус
* описать текущие таски
* возможно можно обойтись и без таблицы.
* подумать об использовании php-enqueue/laravel-queue (task queue) вместо symfnoy/messenger (message bus)
* jobId VO

### Решение

 * Job - doctrine entity
 * JobCommand - сообщение отправляемое в bus и обрабатываемое handlers

### Конфигурирование

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

Команда для запуска worker:

ExecStart=php bin/console messenger:consume async --time-limit=3600 --id=worker_1
