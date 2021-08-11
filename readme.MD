# Расширения для кастомных вариантов использования Symfony

То, что входит в стандартный фрэймворк Symfony (в бандл `Symfony\Bundle\FrameworkBundle\FrameworkBundle`), но не завелось в кастомном варианте (потребовало форка) и(или) несет
необязательный функционал.

"Core" бандл. Без него функционал сервис-контейнера будет куцым (минималистичным). Многое не заработает.

По сути, это `FrameworkBundle` с наполовину выпиленным функционалом (не нужным в кастомных сборках) - формы, workflow, translators & и т.п.

## Установка

composer.json:

```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/proklung/core.framework.extension.bundle"
        }
    ]
```

```bash
composer require proklung/core-framework-extension-bundle
```

## Конфигурационный файл

По адресу `local/packages/framework.yaml` (или `/config/packages/framework.yaml` для Wordpress).

Пример:

```yaml
framework:
  validation:
    enabled: true
    enable_annotations: true

  cache:
    enabled: true
    app: cache.adapter.filesystem
    system: cache.adapter.system
    directory: '%kernel.project_dir%/bitrix/cache/symfony'
    default_memcached_provider: 'memcached://localhost'

  pools:
    my_cache_pool:
      public: true
      adapter: cache.adapter.filesystem
      default_lifetime: 600
  twig:
    # Пути к шаблонам
    default_path: '%kernel.project_dir%/local/twig'
    paths:
      '%kernel.project_dir%/local/twig': ~
      # С namespace
      '%kernel.project_dir%/../../vendor/symfony/web-profiler-bundle/Resources/views': WebProfiler

    cache: false
    cache_dir: '/bitrix/cache/twig'
    autoescape: 'name'
    autoescape_service: ~
    autoescape_service_method: ~
    base_template_class: 'Twig\Template'
    debug: '%kernel.debug%'
    strict_variables: '%kernel.debug%'

  dbal:
    enabled: true
    url: '%env(DATABASE_URL)%'
    charset: UTF8
    default_connection: default
    types: ~

    connections:
        default:
          url: "%env(DATABASE_URL)%"
          dbname:           '%env(DB_NAME)%'
          user:             '%env(DB_USER)%'
          password:         '%env(DB_PASSWORD)%'
          host:             localhost
          mapping_types:
            enum: string

  serializer:
    enabled: true
    enable_annotations: true
    max_depth_handler: false
    name_converter: 'serializer.name_converter.camel_case_to_snake_case'

  secrets:
    enabled: true
    vault_directory: '%kernel.project_dir%/config/secrets/%kernel.environment%'
    local_dotenv_file: '%kernel.project_dir%/.env.%kernel.environment%.local'
    decryption_env_var: 'base64:default::SYMFONY_DECRYPTION_SECRET'

  annotations:
    enabled: true
    file_cache_dir: '%kernel.cache_dir%/annotations'
    cache: 'file'
    annotations_ttl_cache: 7200

  session:
    enabled: true

  csrf_protection:
    enabled: true

  property_access:
    enabled: true

  mailer:
    enabled: true

  messenger:
    enabled: true

  notifier:
    enabled: true

  lock:
    enabled: true
    resources: ~
```

## Консольные команды

Стандартные:

- `debug:router`
- `router:match`
- `debug:event-dispatcher`
- `debug:container`
- `debug:autowiring`
- `config:dump-reference`

## Расширения

Любое расширение может быть отключено проставлением `false` параметру `enabled` соответствующего раздела файла
`framework.yaml`.

### DBAL

### Validator

### Cache

### Serializer

### Secrets

### Lock

[док](https://symfony.com/doc/current/lock.html#configuring-lock-with-frameworkbundle)

### Notifier

### Messenger

Всё согласно [документации](https://symfony.com/doc/current/messenger.html#installation)

Из нюансов: 

- Auto-wiring `MessageHandlerInterface` не срабатывает. Нужно прописывать сервисы саморучно.
- Чтобы заработала конструкция с `RabbitMQ` на сервере должно быть установлено `php_amqp` расширение для PHP.

### Mailer

`framework.yaml`:

```yaml
  mailer:
    enabled: true
    dsn: gmail+smtp://robotivanov2020:xxjqfjyegwmznrtb@default

    envelope:
      recipients: ['example@mail.ru']
  
    # Кастомные параметры
    default_email_from: 'email@gmail.com'
    default_email_title: 'Super title'
    # Шаблон - для strftime, где элемент паттерна обрамлен скобками.
    dsn_file: file:///upload/emails/{Y}/{m}/{d}/@hash.eml
```

#### File mailer transport

```php
       use Symfony\Component\Mime\Email;
       $email = (new Email())
            ->from('from@mail.ru')
            ->to('example@gmail.com')
            ->subject('Time for Symfony Mailer!')
            ->text('Sending emails is fun again!')
            ->html('<p>See Twig integration for better HTML integration!</p>');

        /** @var \Symfony\Component\Mailer\Transport\TransportInterface $factory */
        $factory = container()->get('custom_mailer_transports.filesystem');
        $factory->send($email);
```

Письмо запишется в файл, находящийся по адресу, указанному параметром `dsn_file` в `framework.yaml`.

#### Кастомный отправитель писем

```php
        $factoryMail = container()->get('mailer_bundle.send_email_via_factory');
        $message = $factoryMail->createMessage(
            'example@gmail.com',
            'Testing',
            'Testing body',
            [
                'Лог' => '/my_app-2021-26-07.log' // Файл приаттачится к письму.
            ]
        );

        $sender = container()->get('custom_mail_sender');
        $sender->sendImmediately($message);
```

### Чтение PHP 8 атрибутов на PHP >=7.2

С использованием [компонента Spiral Framework](https://spiral.dev/docs/component-attributes).

Сервисы:

- `spiral.annotations_selective_reader` - совмещенный читатель аннотаций Doctrine и PHP 8 атрибутов.
- `spiral.psr6_selective_reader` - PSR-6 совместимый кэшированный вариант совмещенного читателя.
- `spiral.attribute_reader` - отдельный читатель PHP 8 атрибутов.
- `spiral.attribute_cached_reader` - кэшированный вариант сервиса `spiral.attribute_reader`.
- `spiral.annotation_reader` - читатель аннотаций Doctrine из Spiral Framework.
- `spiral.annotation_cached_reader` - кэшированный вариант сервиса `spiral.annotation_reader`.

Если в свойствах фрэймворка (`framework.yaml`) параметр `cache` не равен `file`, то все кэшированные варианты сервисов удаляются из контейнера.

Параметры кэширования - `file_cache_dir` и `ttl_cache` - берутся из конфигурации `framework.yaml`.