# CLAUDE.md — pivotphp-logger

## Visão Geral
`pivotphp/logger` é o pacote de logging PSR-3 do ecossistema PivotPHP alimentado por Monolog.

## Padrões e Convenções
- **PHP**: PHP >= 8.2 com `strict_types=1`.
- **Padrões PSR**: PSR-3 (`Psr\Log\LoggerInterface`), PSR-12 para estilo de código.
- **PHPStan**: Nível 8 sem novos erros.
- **Testes**: PHPUnit.

## Comandos Úteis
- Executar testes: `composer test` ou `vendor/bin/phpunit`
- Executar linter: `composer cs:check` ou `vendor/bin/phpcs --standard=PSR12 src tests`
- Corrigir estilo: `composer cs:fix`
- Análise estática: `composer phpstan`
- Suíte completa: `composer quality:check`
