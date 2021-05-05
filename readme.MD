# Расширения для кастомных вариантов использования Symfony

То, что входит в стандартный фрэймворк Symfony (в бандл `Symfony\Bundle\FrameworkBundle\FrameworkBundle`), но не завелось в кастомном варианте (потребовало форка) и(или) несет
необязательный функционал.

"Core" бандл. Без него функционал сервис-контейнера будет куцым (минималистичным). Многое не заработает.

По сути, это `FrameworkBundle` с наполовину выпиленным функционалом (не нужным в кастомных сборках) - формы, workflow, translators & и т.п.

## Конфигурационный файл

По адресу `local/packages/framework.yaml`

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

Пример файла `framework.yaml`:

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
        provider: cache.adapter.filesystem
        default_lifetime: 600

  dbal:
    enabled: true
    url: '%env(DATABASE_URL)%'
    charset: UTF8
    default_connection: default
    types: ~

    connections:
        default:
          url: "%env(DATABASE_URL)%"
          dbname:           bitrix.loc
          user:             root
          password:         null
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

  session:
    enabled: true

  csrf_protection:
    enabled: true

  property_access:
    enabled: true
```