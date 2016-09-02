# Пример использования клиента для интеграции API Payoutmoney

```
<?php

use olegf13\PayOut;

$gate = new PayOut();
$gate->setPoint('010999'); // идентификатор проекта

// Запрос баланса
$balance = $gate->getBalance();

// Список провайдеров
$resp = $gate->getProviders();

$payment = [
    'payment_id' => 1, // id платежа в Вашей системе
    'service_id' => 1008, // сервис проведения $gate->getProviders()
    'fields' => [ // поля, что указаны в $gate->getProviders() для сервиса
        'phone' => 'номeр телефона',
    ],
    'amount' => 15, // сумма на счет клиента
];

// Верификация платежа
$payment = $gate->verifyPayment($payment);

// Проведение платежа
$payment = [
    'payment_id' => 1, // id платежа в Вашей системе
    'service_id' => 1008, // сервис проведения $gate->getProviders()
    'fields' => [ // поля, что указуны в $gate->getProviders() для сервиса
        'phone' => 'номeр телефона',
    ],
    'amount' => 15, // сумма на счет клиента
    'data' => date('Y-m-d H:i:s'),
    'comment' => 'mobile 1' // комментарий платежа
];

$payment = $gate->createPayment($payment);

// Проверка статуса платежа
$uid = '';
$paymentStatus = $gate->getPaymentStatus($uid);
```
