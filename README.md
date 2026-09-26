# Laravel Database Guard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/swisnl/laravel-database-guard.svg?style=flat-square)](https://packagist.org/packages/swisnl/laravel-database-guard)
[![Software License](https://img.shields.io/packagist/l/swisnl/laravel-database-guard.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/github/actions/workflow/status/swisnl/laravel-database-guard/tests.yml?label=tests&branch=main&style=flat-square)](https://github.com/swisnl/laravel-database-guard/actions/workflows/tests.yml)

## Introduction

A single Laravel migration can take down a production database. MySQL takes an
exclusive metadata lock for every schema change, and most changes do more than
flip metadata: adding an index, changing a column type or dropping a primary
key rebuilds or scans the whole table. On a large table that can take minutes
or hours, and while it runs:

- **On a single server,** writes to the table block, and every query on it queues
  behind the DDL if a long-running transaction delays the lock.
- **With async replicas,** the change replays on each replica afterwards, stopping
  replication for the same duration.
- **On HA clusters** such as Percona XtraDB Cluster or Galera, schema changes run
  in lockstep on every node (TOI), so the whole cluster stops committing writes
  until the change completes.

These migrations look harmless in review and pass every test, because
development and CI databases are small. The problem only shows on production
data volumes, during a deployment.

This package stops those migrations before they run.

## Install

```bash
composer require swisnl/laravel-database-guard
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=database-guard
```

## Usage

TODO

## Testing

```bash
composer test
composer analyse
composer lint
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@swis.nl instead of using the issue tracker.

## License

The MIT License (MIT). See [License File](LICENSE) for more information.
