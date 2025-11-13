<?php
require_once __DIR__ . '/config.php';



if (defined('IN_INDEX')) {
    if (!isset($_COOKIE['path'])) {
        $projectRoot = realpath(__DIR__ . '/../');
        setcookie('path', $projectRoot, time() + 24*60*60, '/');
        setcookie('url', BASE_URL, time() + 24*60*60, '/');
    }
}

if (isset($_SESSION['usuario'])) {
    $token = bin2hex(random_bytes(32));
    $_SESSION['login_token'] = $token;
    setcookie('login_token', $token, time() + 24*60*60, '/');
} else {
    if (isset($_COOKIE['url'])){
        //header('Location: ' . $_COOKIE['url'] . '/pages/login.php');
        //exit;
    }else{
        //header('Location: ' . BASE_URL . '/pages/login.php');
        //exit;
    };
}
