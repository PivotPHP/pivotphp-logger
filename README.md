# PivotPHP Logger

[![CI](https://github.com/PivotPHP/pivotphp-logger/actions/workflows/ci.yml/badge.svg)](https://github.com/PivotPHP/pivotphp-logger/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/github/v/release/PivotPHP/pivotphp-logger)](https://github.com/PivotPHP/pivotphp-logger/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Implementação de logging em conformidade com PSR-3 (`Psr\Log\LoggerInterface`) desenvolvida para o ecossistema **PivotPHP**, baseada no motor **Monolog**.

Oferece suporte a múltiplos canais, rotação de arquivos diária com retenção configurável, saída para console/streams para contêineres Docker/Kubernetes, formatação estruturada em JSON, sanitização de dados sensíveis e integração direta com o container de injeção de dependências do PivotPHP.

## Instalação

```bash
composer require pivotphp/logger
```

## Requisitos

- PHP >= 8.2
- Monolog >= 3.0
- PSR-3 (`psr/log`)

## Uso Básico

```php
use PivotPHP\Logger\LoggerFactory;

$logger = LoggerFactory::create([
    'default' => 'daily',
    'channels' => [
        'daily' => [
            'path' => 'storage/logs/app.log',
            'days' => 14,
            'level' => 'debug',
        ],
        'stdout' => [
            'stream' => 'php://stdout',
            'level' => 'info',
            'formatter' => 'json',
        ],
    ],
]);

$logger->info('Usuário autenticado', ['user_id' => 42]);
```

## Integração com PivotPHP

Ao utilizar o `LoggerServiceProvider`, a instância é automaticamente vinculada ao container de serviços sob `Psr\Log\LoggerInterface::class`, `log` e `logger`.

## Licença

Distribuído sob a licença MIT. Consulte o arquivo [LICENSE](LICENSE) para obter mais informações.

