<?php
// Caminho absoluto no servidor
define('BASE_PATH', __DIR__);

// URL base dinâmica (funciona mesmo se mudar o nome da pasta)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// Descobre o caminho da pasta do projeto dinamicamente
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = rtrim($protocol . $host . $scriptName, '/');

define('BASE_URL', $baseUrl);
