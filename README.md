brainbits/monolog-sentry
========================

This package provides a opiniated factory for bgalati/monolog-sentry-handler, based on the provided [symfony guide](https://github.com/B-Galati/monolog-sentry-handler/blob/main/doc/guide-symfony.md).

Required configuration:

```yaml
# brainbits_monolog_sentry.yaml

parameters:
    env(SENTRY_DSN): ''
    env(SENTRY_ENVIRONMENT): ''

services:
    _defaults:
        autowire: true
        autoconfigure: true

    Brainbits\MonologSentry\SentryFactory: ~

    Sentry\State\HubInterface:
        factory: ['@Brainbits\MonologSentry\SentryFactory', 'create']
        arguments:
            $dsn: '%env(SENTRY_DSN)%'
            $environment: '%env(SENTRY_ENVIRONMENT)%'
            $inAppInclude: ['%kernel.project_dir%/src']
            $inAppExclude: ['%kernel.cache_dir%', '%kernel.project_dir%/vendor']
            $prefixes: ['%kernel.project_dir%']
            $release: 'web-%app_version%'
            $tags:
                foo: bar
            $logger: '@logger'

    Controlling\Sentry\Sentry\SentryHandler: ~

    Controlling\Sentry\EventListener\SentryConsoleListener: ~
    Controlling\Sentry\EventListener\SentryRequestListener:
    Controlling\Sentry\EventListener\SentryUserListener:
    Controlling\Sentry\EventListener\MonologResetterEventListener: ~
```

Example monolog configuration:

```yaml
# monolog.yaml

when@prod:
    monolog:
        handlers:
            sentry:
                type: fingers_crossed
                process_psr_3_messages: true
                action_level: warning
                handler: sentry_buffer
                excluded_http_codes: [400, 401, 403, 404, 405]
                buffer_size: 100 # Prevents memory leaks for workers
                channels: ["!event", "!security"]
            sentry_buffer:
                type: buffer
                handler: sentry_handler
            sentry_handler:
                type: service
                id: 'Brainbits\MonologSentry\SentryHandler'
```