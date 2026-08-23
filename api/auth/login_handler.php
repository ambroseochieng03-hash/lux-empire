<?php

declare(strict_types=1);

require_once '../../config/db.php';
require_once '../../config/session.php';
require_once '../../classes/Auth.php';

Session::start();


/*
|--------------------------------------------------------------------------
| Only POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Collect inputs
|--------------------------------------------------------------------------
*/

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/

if ($email === '' || $password === '') {
    header(
        'Location: ' . BASE_URL . '/login?error='
        . urlencode('Email and password are required.')
    );

    exit;
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header(
        'Location: ' . BASE_URL . '/login?error='
        . urlencode('Invalid email format.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$database = new Database();
$pdo = $database->connect();


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$auth = new Auth($pdo);

$user = $auth->login(
    $email,
    $password
);


/*
|--------------------------------------------------------------------------
| Authentication failed
|--------------------------------------------------------------------------
*/

if ($user === null) {
    header(
        'Location: ' . BASE_URL . '/login?error='
        . urlencode('Incorrect empire credentials.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Account status
|--------------------------------------------------------------------------
*/

if (isset($user['status'])) {

    if ($user['status'] === 'suspended') {

        header(
            'Location: ' . BASE_URL . '/login?error='
            . urlencode(
                'Kindly contact Empire support for assistance.'
            )
        );

        exit;
    }

    if ($user['status'] === 'blocked') {

        header(
            'Location: ' . BASE_URL . '/login?error='
            . urlencode(
                'Account blocked from platform access.'
            )
        );

        exit;
    }

    if ($user['status'] !== 'active') {

        header(
            'Location: ' . BASE_URL . '/login?error='
            . urlencode(
                'Account inactive.'
            )
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Regenerate session after authentication
|--------------------------------------------------------------------------
*/

Session::regenerateAfterLogin();


/*
|--------------------------------------------------------------------------
| Store authenticated user
|--------------------------------------------------------------------------
*/

$_SESSION['user'] = [
    'id'        => $user['id'],
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'role'      => $user['role'],
];


/*
|--------------------------------------------------------------------------
| Role-based redirect
|--------------------------------------------------------------------------
*/

switch ($user['role']) {

    case 'tenant':
        header(
            'Location: ' . BASE_URL . '/tenant'
        );
        break;

    case 'landlord':
        header(
            'Location: ' . BASE_URL . '/landlord'
        );
        break;

    case 'driver':
        header(
            'Location: ' . BASE_URL . '/driver'
        );
        break;

    case 'admin':
        header(
            'Location: ' . BASE_URL . '/admin'
        );
        break;

    default:

        Session::destroy();

        header(
            'Location: ' . BASE_URL . '/login?error='
            . urlencode('Unknown empire role.')
        );

        break;
}

exit;