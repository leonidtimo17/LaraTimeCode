<p align="center">
  <img src="art/logo.svg" width="112" alt="Логотип LaraTimeCode">
</p>

<h1 align="center">LaraTimeCode</h1>

<p align="center">
  <a href="README.md">English</a> · Русский
</p>

<p align="center">
  Превращает неудачные Laravel-запросы в зашифрованные снимки для повторного запуска и регрессионные тесты Pest.
</p>

<p align="center">
  <a href="https://github.com/leonidtimo17/LaraTimeCode/actions/workflows/tests.yml"><img src="https://github.com/leonidtimo17/LaraTimeCode/actions/workflows/tests.yml/badge.svg" alt="Тесты"></a>
  <a href="https://packagist.org/packages/laratimecode/laratimecode"><img src="https://img.shields.io/packagist/php-v/laratimecode/laratimecode" alt="Версия PHP"></a>
  <a href="https://packagist.org/packages/laratimecode/laratimecode"><img src="https://img.shields.io/packagist/v/laratimecode/laratimecode" alt="Версия на Packagist"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/github/license/leonidtimo17/LaraTimeCode" alt="Лицензия MIT"></a>
</p>

```text
Исключение в production → зашифрованный файл .repro → локальный повтор → регрессионный тест
```

> [!WARNING]
> LaraTimeCode находится на альфа-стадии. Он повторяет запросы с использованием
> вашей локальной базы данных и текущего состояния приложения, но не копирует
> и не восстанавливает строки из production-базы.

## Зачем нужен LaraTimeCode?

Логи показывают, где произошла ошибка. LaraTimeCode сохраняет очищенный контекст
запроса, необходимый для её воспроизведения, и превращает его в удобный локальный
процесс отладки:

- перехватывает неудачные HTTP-запросы, не подменяя исходное исключение;
- рекурсивно скрывает секретные данные и шифрует снимки ключом приложения;
- позволяет изучить или повторить запрос через Artisan;
- создаёт основу регрессионного теста Pest;
- автоматически удаляет устаревшие снимки.

## Требования

- PHP 8.2 или новее
- Laravel 12 или 13

## Установка

Установите последний отмеченный тегом релиз с Packagist:

```bash
composer require "laratimecode/laratimecode:^0.1@alpha"
php artisan vendor:publish --tag=laratimecode-config
```

Для локальной разработки пакета добавьте репозиторий как
[Composer path repository](https://getcomposer.org/doc/05-repositories.md#path):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../LaraTimeCode"
        }
    ]
}
```

Затем выполните `composer require laratimecode/laratimecode:@dev`.

Сбор снимков намеренно отключён по умолчанию:

```dotenv
LARATIMECODE_ENABLED=true
LARATIMECODE_ENCRYPT=true
LARATIMECODE_RETENTION_DAYS=14
LARATIMECODE_MAX_FILES=100
```

Для шифрования снимков используется `APP_KEY`. Сохраняйте соответствующий ключ,
пока созданные с ним снимки не будут расшифрованы или удалены.

## Рабочий процесс

Если во время запроса возникает исключение, LaraTimeCode записывает снимок
`.repro` в каталог `storage/laratimecode`.

```bash
# Найти ID снимка
php artisan timecode:list

# Посмотреть очищенные метаданные
php artisan timecode:show 20260817-120000-123456-abcd1234

# Повторить запрос в текущем приложении
php artisan timecode:replay 20260817-120000-123456-abcd1234

# При необходимости найти и авторизовать сохранённого Eloquent-пользователя
php artisan timecode:replay 20260817-120000-123456-abcd1234 --auth

# Создать регрессионный тест Pest
php artisan timecode:make-test 20260817-120000-123456-abcd1234

# Удалить снимок после завершения исследования
php artisan timecode:delete 20260817-120000-123456-abcd1234
```

Созданные тесты сохраняются в `tests/Feature/LaraTimeCode` и содержат временную
проверку `assertSuccessful()`. Добавьте нужные фабрики или фикстуры, затем
замените её проверкой ожидаемого поведения.

## Сохраняемый контекст

- HTTP-метод, URI, query-параметры, входные данные и разрешённые заголовки
- имя маршрута, обработчик и параметры
- класс и идентификатор авторизованной модели
- класс исключения, сообщение, место возникновения и ограниченный stack trace
- SQL-запрос, длительность и имя соединения (bindings по умолчанию отключены)
- URL, метод и статус исходящих запросов Laravel HTTP Client
- версии PHP и Laravel, окружение, релиз и отпечаток `composer.lock`

Снимки записываются атомарно. Внутренние ошибки сбора записываются в лог как
предупреждения и никогда не подменяют исходное исключение приложения.

## Конфиденциальность и безопасность

Пароли, токены, cookie, заголовки авторизации, API-ключи и распространённые
платёжные поля рекурсивно скрываются. Добавьте поля своего приложения в
`config/laratimecode.php`:

```php
'redaction' => [
    'replacement' => '[REDACTED]',
    'fields' => [
        'password',
        'token',
        'authorization',
        'customer.ssn',
        '*.private_key',
    ],
],
```

Сохраняются только явно разрешённые заголовки запросов. IP-адреса, bindings базы
данных и тела исходящих ответов по умолчанию отключены. Перед использованием в
production проверьте конфигурацию на соответствие вашей политике
конфиденциальности. Шифрование защищает данные при хранении, но не делает
безопасным сбор ненужной информации.

Сообщайте об уязвимостях приватно согласно [политике безопасности](SECURITY.ru.md).

## Текущие ограничения

- Строки базы данных не сохраняются и не восстанавливаются.
- Загруженные файлы представлены только метаданными и не копируются.
- Исходящие HTTP-запросы сохраняются только как контекст; автоматические fake-ответы пока не реализованы.
- Задания очереди, события, почта, feature flags и состояние кеша пока не сохраняются.
- Повтор с авторизацией поддерживает Eloquent-модели, которые можно найти по идентификатору.
- Параллельная работа и нагрузка в production требуют более широкого тестирования.

Планы развития описаны в [дорожной карте](ROADMAP.ru.md).

## Разработка

```bash
composer install
composer check
```

Мы рады вашим предложениям и изменениям. Перед созданием pull request прочитайте
[руководство участника](CONTRIBUTING.ru.md).

## Лицензия

LaraTimeCode — программное обеспечение с открытым исходным кодом, распространяемое
по [лицензии MIT](LICENSE.md).
