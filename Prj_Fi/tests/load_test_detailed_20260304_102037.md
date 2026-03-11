
**Дата и время теста:** Ср 04 мар 2026 10:20:37 +07

**Тестируемый URL:** http://localhost:3000/index.php?page=home

**Параметры теста:** -n 1000 (запросов), -c 100 (одновременных пользователей)

---

## КЛЮЧЕВЫЕ ПОКАЗАТЕЛИ ПРОИЗВОДИТЕЛЬНОСТИ

| Показатель | Значение |
|------------|----------|
| Запросов в секунду (RPS) | 12196.61 req/сек |
| Среднее время ответа | 8.199 мс |
| Неудачных запросов | 0 |
| Скорость передачи данных | 4359.33 KB/сек |

---

## ДОПОЛНИТЕЛЬНАЯ ИНФОРМАЦИЯ

| Параметр | Значение |
|----------|----------|
| Всего запросов | 1000 |
| Уровень конкурентности | 100 |
| Время выполнения теста | 0.082 seconds |
| Всего передано данных | 366000 bytes |
| Передано HTML данных | 0 bytes |

---

## АНАЛИЗ РЕЗУЛЬТАТОВ

### Анализ времени ответа:

✅ **ОТЛИЧНО:** Время ответа менее 100 мс. Сервер отлично справляется с нагрузкой.

### Анализ успешности запросов:

✅ **ВСЕ ЗАПРОСЫ УСПЕШНЫ:** Процент ошибок: 0%

### Анализ пропускной способности:

💪 **ВЫСОКАЯ:** Пропускная способность: 12196.61 запросов/сек

---

## РЕКОМЕНДАЦИИ ПО ОПТИМИЗАЦИИ

✅ **Все показатели в норме.**
✅ Дополнительная оптимизация не требуется.
✅ Система успешно справляется с нагрузкой в 100 одновременных пользователей.

---

## СИСТЕМНАЯ ИНФОРМАЦИЯ

| Параметр | Значение |
|----------|----------|
| Имя хоста | r301client2 |
| Ядер CPU | 12 |
| ОЗУ всего | 15Gi |
| ОЗУ доступно | 10Gi |
| Версия ядра | 6.12.21-1.red80.x86_64 |

---

## ПОЛНЫЙ ВЫВОД УТИЛИТЫ AB

```
This is ApacheBench, Version 2.3 <$Revision: 1923142 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking localhost (be patient)
Completed 100 requests
Completed 200 requests
Completed 300 requests
Completed 400 requests
Completed 500 requests
Completed 600 requests
Completed 700 requests
Completed 800 requests
Completed 900 requests
Completed 1000 requests
Finished 1000 requests


Server Software:        
Server Hostname:        localhost
Server Port:            3000

Document Path:          /index.php?page=home
Document Length:        0 bytes

Concurrency Level:      100
Time taken for tests:   0.082 seconds
Complete requests:      1000
Failed requests:        0
Non-2xx responses:      1000
Total transferred:      366000 bytes
HTML transferred:       0 bytes
Requests per second:    12196.61 [#/sec] (mean)
Time per request:       8.199 [ms] (mean)
Time per request:       0.082 [ms] (mean, across all concurrent requests)
Transfer rate:          4359.33 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.6      0       2
Processing:     1    8   2.5      6      14
Waiting:        0    8   2.5      6      14
Total:          3    8   2.4      6      14

Percentage of the requests served within a certain time (ms)
  50%      6
  66%      9
  75%      9
  80%     10
  90%     11
  95%     13
  98%     14
  99%     14
 100%     14 (longest request)
```

---

*Отчёт сгенерирован автоматически Ср 04 мар 2026 10:20:37 +07*
