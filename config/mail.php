<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

return [

    'host' => 'smtp.gmail.com',


   'username' => 'your-email@example.com',
    'password' => 'YOUR_APP_PASSWORD',

    'port' => 587,

    'encryption' => 'tls',

    'from_email' => 'smartcampusassistants@gmail.com',

    'from_name' => 'LUX EMPIRE'
];
