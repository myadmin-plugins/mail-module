# Mail Services Module for MyAdmin

[![Tests](https://github.com/detain/myadmin-mail-module/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-mail-module/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-mail-module/version)](https://packagist.org/packages/detain/myadmin-mail-module)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-mail-module/downloads)](https://packagist.org/packages/detain/myadmin-mail-module)
[![License](https://poser.pugx.org/detain/myadmin-mail-module/license)](https://packagist.org/packages/detain/myadmin-mail-module)

A MyAdmin plugin module that provides mail service provisioning, activation, deactivation, and lifecycle management. It integrates with the Symfony EventDispatcher to handle service events such as enabling, reactivating, suspending, and terminating mail accounts (including ZoneMTA-based services).

## Features

- Event-driven mail service lifecycle management (activate, reactivate, deactivate, terminate)
- ZoneMTA service type support with dedicated provisioning workflows
- Configurable suspension, deletion, and billing settings
- Admin and client email notifications on service state changes
- Integrates with the MyAdmin ORM and settings framework

## Installation

```sh
composer require detain/myadmin-mail-module
```

## Configuration

The module exposes its settings through the `$settings` static property on `Detain\MyAdminMail\Plugin`. Key defaults include:

| Setting              | Default                   |
|----------------------|---------------------------|
| SERVICE_ID_OFFSET    | 1100                      |
| SUSPEND_DAYS         | 14                        |
| SUSPEND_WARNING_DAYS | 7                         |
| DELETE_PENDING_DAYS  | 45                        |
| EMAIL_FROM           | support@interserver.net   |

## Running Tests

```sh
composer install
vendor/bin/phpunit
```

## License

This package is licensed under the [LGPL-2.1-only](https://opensource.org/licenses/LGPL-2.1) license.
