# Yandex Lockbox PHP SDK

![Yandex Lockbox PHP SDK](https://github.com/user-attachments/assets/96588cc3-f6b7-4aa8-be93-c7c14e14bf38)

> 🇬🇧 [English version](README.md)

[![Latest Version](https://img.shields.io/packagist/v/tigusigalpa/yandex-lockbox-php.svg?style=flat-square)](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
[![PHP Version](https://img.shields.io/packagist/php-v/tigusigalpa/yandex-lockbox-php.svg?style=flat-square)](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
[![License](https://img.shields.io/packagist/l/tigusigalpa/yandex-lockbox-php.svg?style=flat-square)](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
[![Tests](https://img.shields.io/github/actions/workflow/status/tigusigalpa/yandex-lockbox-php/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/tigusigalpa/yandex-lockbox-php/actions)

PHP/Laravel клиентская библиотека для **Yandex Lockbox** — сервиса безопасного хранения секретов в Yandex Cloud.

## 📚 Документация

- [Документация Yandex Lockbox](https://yandex.cloud/ru/docs/lockbox/)
- [Руководство по быстрому старту](https://yandex.cloud/ru/docs/lockbox/quickstart)
- [Справочник API](https://yandex.cloud/ru/docs/lockbox/api-ref/Secret/)
- [Руководство по OAuth токенам](https://yandex.cloud/ru/docs/iam/concepts/authorization/oauth-token)
- API Endpoint: `https://lockbox.api.cloud.yandex.net/lockbox/v1`

## ✨ Возможности

- ✅ Полная поддержка Yandex Lockbox API
- ✅ **Автоматическая генерация IAM токена из OAuth токена**
- ✅ OAuth Token Manager для управления облаками/папками
- ✅ **Обработка асинхронных операций** (ожидание завершения операций)
- ✅ **Управление правами доступа к папкам** (список/назначение прав)
- ✅ PHP 8.0+ со строгой типизацией
- ✅ Интеграция с Laravel 8-12 (service provider, facade, config)
- ✅ Расширяемый интерфейс провайдера токенов
- ✅ Типизированные исключения для лучшей обработки ошибок
- ✅ Поддержка PSR-3 логгера
- ✅ Комплексное покрытие тестами

## 📦 Установка

```bash
composer require tigusigalpa/yandex-lockbox-php
```

### Разработка (path repository)

Для разработки в монорепозитории добавьте в корневой `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "public_html/packages/yandex-lockbox-php"
        }
    ],
    "require": {
        "tigusigalpa/yandex-lockbox-php": "*"
    }
}
```

Затем выполните:

```bash
composer update tigusigalpa/yandex-lockbox-php
```

## ⚙️ Конфигурация (Laravel)

Опубликуйте файл конфигурации:

```bash
php artisan vendor:publish --tag=yandex-lockbox-config
```

Добавьте переменные окружения в ваш `.env`:

```env
# РЕКОМЕНДУЕТСЯ: Используйте OAuth токен (начинается с y0_, y1_, y2_, y3_)
# OAuth токены не истекают и автоматически конвертируются в IAM токены
YANDEX_LOCKBOX_TOKEN=y0_your-oauth-token

# АЛЬТЕРНАТИВА: Используйте IAM токен (начинается с t1.)
# IAM токены истекают через 12 часов
# YANDEX_LOCKBOX_TOKEN=t1.your-iam-token

YANDEX_LOCKBOX_BASE_URI=https://lockbox.api.cloud.yandex.net/lockbox/v1
YANDEX_LOCKBOX_FOLDER_ID=your-default-folder-id
```

## 🔐 Руководство по авторизации и подключению к API

### Шаг 1: Получение OAuth токена

**Документация:** [Руководство по OAuth токенам](https://yandex.cloud/ru/docs/iam/concepts/authorization/oauth-token)

**Получите токен через OAuth запрос:**

```
https://oauth.yandex.ru/authorize?response_type=token&client_id=1a6990aa636648e9b2ef855fa7bec2fb
```

1. Откройте URL выше в вашем браузере
2. Авторизуйте приложение
3. Скопируйте OAuth токен из URL ответа (формат: `y0_...`, `y1_...`, `y2_...`, `y3_...`)
4. Добавьте токен в `.env` (Laravel):
   ```env
   YANDEX_LOCKBOX_TOKEN=y0_your-oauth-token
   ```

**Или передайте напрямую в OAuthTokenManager:**

```php
use Tigusigalpa\YandexLockbox\Auth\OAuthTokenManager;

$manager = new OAuthTokenManager('y0_your-oauth-token');
```

### Шаг 2: Получение IAM токена (Опционально)

**Документация:** [Как получить IAM токен](https://yandex.cloud/ru/docs/iam/operations/iam-token/create#exchange-token)

IAM токен генерируется автоматически из OAuth токена. Но вы можете получить его вручную:

```php
$manager = new OAuthTokenManager('y0_your-oauth-token');

// Получить IAM токен (кэшируется на 12 часов)
$iamToken = $manager->getIamToken();
```

**Альтернатива - используя Yandex CLI:**

```bash
yc iam create-token
```

⚠️ Примечание: IAM токены истекают через 12 часов

### Шаг 3: Получение Cloud ID

**Документация:** [Получение списка ресурсов Cloud](https://yandex.cloud/ru/docs/resource-manager/api-ref/Cloud/list)

**Список всех облаков:**

```php
$manager = new OAuthTokenManager('y0_your-oauth-token');

// Получить все облака
$clouds = $manager->listClouds();

foreach ($clouds as $cloud) {
    echo "Cloud: {$cloud['name']} (ID: {$cloud['id']})\n";
}

// Использовать первое облако
$cloudId = $clouds[0]['id'];
```

**Или получить первое облако напрямую:**

```php
// Получить ID первого облака (удобный метод)
$cloudId = $manager->getFirstCloudId();
```

### Шаг 4: Получение Folder ID

**Документация:
** [Получение списка ресурсов Folder в указанном облаке](https://yandex.cloud/ru/docs/resource-manager/api-ref/Folder/list)

**Список всех папок в облаке:**

```php
// Получить все папки в облаке
$folders = $manager->listFolders($cloudId);

foreach ($folders as $folder) {
    echo "Folder: {$folder['name']} (ID: {$folder['id']})\n";
}

// Использовать первую папку
$folderId = $folders[0]['id'];
```

**Или получить первую папку напрямую:**

```php
// Получить ID первой папки (удобный метод)
$folderId = $manager->getFirstFolderId($cloudId);

// Или получить первую папку из первого облака одним вызовом
$folderId = $manager->getFirstFolderIdFromFirstCloud();
```

### Шаг 5: Добавление прав доступа к папке

**Документация:** [Управление доступом в Yandex Lockbox](https://yandex.cloud/ru/docs/lockbox/security/)

> **Сначала необходимо получить Subject ID (ID учетной записи пользователя, которому вы хотите назначить права)**

**Документация:
** [Субъекты, которым назначаются роли](https://yandex.cloud/ru/docs/iam/concepts/access-control/#subject)

**Документация:
** [Получение списка учетных записей Yandex Passport](https://yandex.cloud/ru/docs/iam/api-ref/YandexPassportUserAccount/getByLogin)

```php
$subjectId = $manager->getUserIdByLogin('your-yandex-login'); // your-yandex-login@yandex.ru
```

**Документация:** [lockbox.editor](https://yandex.cloud/ru/docs/lockbox/security/#lockbox-editor)

**Документация:
** [Настройка прав доступа к папке](https://yandex.cloud/ru/docs/resource-manager/operations/folder/set-access-bindings)

```php
$manager->assignRoleToFolder(
    $iamToken, 
    $folderId, 
    $subjectId, 
    'lockbox.editor',
    'userAccount',
    true  // waitForCompletion - ожидает завершения операции
);
```

### Шаг 6: Работа с Yandex Lockbox API

**Документация:** [Lockbox API, REST: Secret](https://yandex.cloud/ru/docs/lockbox/api-ref/Secret/)

Теперь вы можете использовать folder ID для работы с секретами:

```php
use Tigusigalpa\YandexLockbox\Client;
use Tigusigalpa\YandexLockbox\Token\OAuthTokenProvider;

// Создать клиент с OAuth токеном
$tokenProvider = new OAuthTokenProvider('y0_your-oauth-token');
$client = new Client($tokenProvider);

// Список всех секретов в папке
$secrets = $client->listSecrets($folderId);

// Получить метаданные секрета
$secret = $client->getSecret('your-secret-id');

// Получить содержимое секрета (фактические значения)
$payload = $client->getPayload('your-secret-id');
foreach ($payload['entries'] as $entry) {
    echo "{$entry['key']}: {$entry['textValue']}\n";
}

// Создать новый секрет
$created = $client->createSecret([
    'folderId' => $folderId,
    'name' => 'my-api-keys',
    'description' => 'Production API keys',
    'labels' => ['env' => 'production'],
]);

// Добавить новую версию со значениями секрета
$version = $client->addVersion($created['id'], [
    'description' => 'Версия с API ключами',
    'payloadEntries' => [
        ['key' => 'API_KEY', 'textValue' => 'super-secret-key'],
        ['key' => 'API_SECRET', 'textValue' => 'super-secret-value'],
    ],
]);

// Обновить метаданные секрета
$client->updateSecret($created['id'], [
    'name' => 'updated-name',
    'description' => 'Обновленное описание',
]);

// Активировать/Деактивировать секрет
$client->activateSecret($created['id']);
$client->deactivateSecret($created['id']);

// Удалить секрет
$client->deleteSecret($created['id']);
```

### Обработка асинхронных операций

Некоторые операции Yandex Cloud являются асинхронными и возвращают объект операции с `done=false`:

**Вариант 1: Автоматическое ожидание завершения**

```php
$manager = new OAuthTokenManager('y0_your-oauth-token');
$iamToken = $manager->getIamToken();

// Установите waitForCompletion в true
$result = $manager->assignRoleToFolder(
    $iamToken,
    'folder-id',
    'user-id',
    'lockbox.editor',
    'userAccount',
    true,  // waitForCompletion
    60     // maxWaitSeconds (опционально)
);
```

**Вариант 2: Ручная проверка статуса операции**

```php
// Запустить операцию
$operation = $manager->assignRoleToFolder($iamToken, 'folder-id', 'user-id', 'lockbox.editor');

// Дождаться завершения операции
if (!$operation['done']) {
    $completed = $manager->waitForOperation($iamToken, $operation['id'], 60);
}
```

### Управление правами доступа к папкам

```php
use Tigusigalpa\YandexLockbox\Auth\OAuthTokenManager;

$manager = new OAuthTokenManager('y0_your-oauth-token');
$iamToken = $manager->getIamToken();

// Список привязок доступа с пагинацией
$result = $manager->listFolderAccessBindings($iamToken, 'folder-id', 100);

// Получить все привязки сразу (автоматическая пагинация)
$allBindings = $manager->getAllFolderAccessBindings($iamToken, 'folder-id');
```

### Laravel Facade

```php
use Tigusigalpa\YandexLockbox\Laravel\Facades\Lockbox;

// Список секретов
$secrets = Lockbox::listSecrets(config('lockbox.default_folder_id'));

// Получить метаданные секрета
$secret = Lockbox::getSecret('secret-id');

// Получить фактические значения секрета
$payload = Lockbox::getPayload('secret-id');

// Создать секрет
$created = Lockbox::createSecret([
    'folderId' => config('lockbox.default_folder_id'),
    'name' => 'laravel-secrets',
    'description' => 'Секреты Laravel приложения',
]);

// Добавить версию
$version = Lockbox::addVersion('secret-id', [
    'payloadEntries' => [
        ['key' => 'DB_PASSWORD', 'textValue' => env('DB_PASSWORD')],
        ['key' => 'APP_KEY', 'textValue' => env('APP_KEY')],
    ],
]);
```

### Laravel Artisan команды

```bash
# Тест подключения
php artisan lockbox:test

# Список всех секретов
php artisan lockbox:list

# Показать детали секрета
php artisan lockbox:show <secret-id> --payload

# Создать новый секрет
php artisan lockbox:create my-secret --description="Мой секрет"

# Добавить версию со значениями
php artisan lockbox:add-version <secret-id> \
  --entry=KEY1=value1 \
  --entry=KEY2=value2

# Удалить секрет
php artisan lockbox:delete <secret-id>
```

## 🔒 Обработка исключений

Библиотека предоставляет специфические исключения для различных типов ошибок:

```php
use Tigusigalpa\YandexLockbox\Exceptions\AuthenticationException;
use Tigusigalpa\YandexLockbox\Exceptions\NotFoundException;
use Tigusigalpa\YandexLockbox\Exceptions\RateLimitException;
use Tigusigalpa\YandexLockbox\Exceptions\ValidationException;
use Tigusigalpa\YandexLockbox\Exceptions\LockboxException;

try {
    $payload = $client->getPayload('secret-id');
} catch (AuthenticationException $e) {
    echo "Ошибка аутентификации: " . $e->getMessage();
} catch (NotFoundException $e) {
    echo "Секрет не найден: " . $e->getMessage();
} catch (RateLimitException $e) {
    echo "Превышен лимит запросов: " . $e->getMessage();
} catch (ValidationException $e) {
    echo "Ошибка валидации: " . $e->getMessage();
} catch (LockboxException $e) {
    echo "Ошибка API: " . $e->getMessage();
}
```

## 🧪 Тестирование

### Artisan команды

#### lockbox:test - Комплексное тестирование

```bash
# Базовый запуск
php artisan lockbox:test

# С указанием конкретной папки
php artisan lockbox:test --folder=b1g8dn6s4f5h6j7k8l9m

# С автоматической очисткой
php artisan lockbox:test --cleanup
```

#### lockbox:list - Список секретов

```bash
php artisan lockbox:list
php artisan lockbox:list --folder=b1g8dn6s4f5h6j7k8l9m
```

#### lockbox:show - Показать детали секрета

```bash
php artisan lockbox:show e6q7r8s9t0u1v2w3x4y5
php artisan lockbox:show e6q7r8s9t0u1v2w3x4y5 --payload
```

#### lockbox:create - Создать секрет

```bash
php artisan lockbox:create my-secret
php artisan lockbox:create my-secret --description="Production API ключи"
```

#### lockbox:add-version - Добавить версию

```bash
php artisan lockbox:add-version e6q7r8s9t0u1v2w3x4y5
php artisan lockbox:add-version e6q7r8s9t0u1v2w3x4y5 \
  --entry=DB_HOST=localhost \
  --entry=DB_USER=admin
```

#### lockbox:delete - Удалить секрет

```bash
php artisan lockbox:delete e6q7r8s9t0u1v2w3x4y5
php artisan lockbox:delete e6q7r8s9t0u1v2w3x4y5 --force
```

### PHPUnit тесты

```bash
composer test
composer test-coverage
```

## 📚 Справочник API

### Методы OAuthTokenManager

#### Аутентификация и управление токенами

- `getIamToken(): string` - Получить IAM токен (автоматически кэшируется)
- `listClouds(): array` - Список всех облаков
- `getFirstCloud(): array` - Получить первое облако
- `getFirstCloudId(): string` - Получить ID первого облака

#### Управление папками

- `listFolders(string $cloudId): array` - Список папок в облаке
- `getFolder(string $folderId): array` - Получить детали папки
- `getFirstFolderId(string $cloudId): string` - Получить ID первой папки
- `getFirstFolderIdFromFirstCloud(): string` - Получить ID первой папки из первого облака
- `createFolder(string $iamToken, string $cloudId, string $name, ?string $description = null): array` - Создать папку

#### Управление доступом

- `assignRoleToFolder(...)` - Назначить роль папке
- `listFolderAccessBindings(...)` - Список привязок доступа к папке
- `getAllFolderAccessBindings(...)` - Получить все привязки доступа

#### Управление пользователями

- `getUserByLogin(string $login): array` - Получить информацию о пользователе
- `getUserIdByLogin(string $login): string` - Получить ID пользователя

#### Асинхронные операции

- `waitForOperation(...)` - Дождаться завершения операции
- `getOperation(...)` - Получить статус операции

### Методы Client

#### Управление секретами

- `listSecrets(string $folderId): array` - Список секретов в папке
- `getSecret(string $secretId): array` - Получить метаданные секрета
- `createSecret(array $data): array` - Создать новый секрет
- `updateSecret(string $secretId, array $data): array` - Обновить секрет
- `deleteSecret(string $secretId): void` - Удалить секрет

#### Управление версиями

- `addVersion(string $secretId, array $data): array` - Добавить новую версию
- `getPayload(string $secretId, ?string $versionId = null): array` - Получить содержимое секрета

## 📝 Требования

- PHP 8.0 или выше
- Laravel 8.x - 12.x (опционально, для интеграции с Laravel)
- Guzzle HTTP client 7.x или 8.x

## 🤝 Участие в разработке

Приветствуются любые вклады! Пожалуйста, не стесняйтесь отправлять Pull Request.

1. Сделайте форк репозитория
2. Создайте ветку для новой функции (`git checkout -b feature/amazing-feature`)
3. Зафиксируйте изменения (`git commit -m 'Add some amazing feature'`)
4. Отправьте в ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

## 📄 Лицензия

Этот пакет является программным обеспечением с открытым исходным кодом, лицензированным по [лицензии MIT](LICENSE).

## 👤 Автор

**Igor Sazonov**

- GitHub: [@tigusigalpa](https://github.com/tigusigalpa)
- Email: sovletig@gmail.com

## 🔗 Ссылки

- [Packagist](https://packagist.org/packages/tigusigalpa/yandex-lockbox-php)
- [GitHub Repository](https://github.com/tigusigalpa/yandex-lockbox-php)
- [Issue Tracker](https://github.com/tigusigalpa/yandex-lockbox-php/issues)
- [Документация Yandex Cloud](https://yandex.cloud/ru/docs/lockbox/)
