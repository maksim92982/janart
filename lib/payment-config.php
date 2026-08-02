<?php
declare(strict_types=1);

/**
 * Настройки интеграции с Сам.Эквайринг (Смарт оплата).
 *
 * Обновите идентификатор магазина и секретный ключ перед использованием.
 * Чтобы не хранить секрет явно, можно:
 * - выставить переменные окружения SELFWORK_MERCHANT_ID и SELFWORK_SECRET_KEY,
 * - или заменить значения по умолчанию ниже своими.
 */

define('SELFWORK_STORE_DOMAIN', 'https://janart-studio.ru');
define('SELFWORK_MERCHANT_ID', getenv('SELFWORK_MERCHANT_ID') ?: '1028984');
define('SELFWORK_SECRET_KEY', getenv('SELFWORK_SECRET_KEY') ?: 'xaCaUogeKUIJAKZH9uQ85U8vmp17fgcz');
define('SELFWORK_SMART_PAYMENT_ENDPOINT', 'https://pro.selfwork.ru/merchant/v1/init');
define('SELFWORK_STATUS_ENDPOINT', 'https://pro.selfwork.ru/merchant/v1/status');
define('SELFWORK_PAYMENT_LOG_PATH', __DIR__ . '/../storage/smart-payment.log');

function smart_payment_log(string $message, array $context = []): void
{
    $payload = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    @file_put_contents(
        SELFWORK_PAYMENT_LOG_PATH,
        sprintf("[%s] %s%s\n", date('Y-m-d H:i:s'), $message, $payload),
        FILE_APPEND | LOCK_EX
    );
}

function smart_payment_require_configuration(): void
{
    if (
        SELFWORK_MERCHANT_ID === 'replace-with-shop-id'
        || SELFWORK_SECRET_KEY === 'replace-with-secret'
    ) {
        throw new RuntimeException(
            'Перед использованием смарт-оплаты установите реальные значения SELFWORK_MERCHANT_ID и SELFWORK_SECRET_KEY в lib/payment-config.php или в переменных окружения.'
        );
    }
}

