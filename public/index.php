<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));// Define el tiempo de inicio de la aplicación para medir el rendimiento

require __DIR__.'/../vendor/autoload.php';// Carga el autoload de Composer para las dependencias

$app = require_once __DIR__.'/../bootstrap/app.php';// Carga la aplicación de Laravel

$kernel = $app->make(Kernel::class);// Crea una instancia del kernel HTTP de Laravel

$response = $kernel->handle(// Maneja la solicitud HTTP entrante y obtiene la respuesta
    $request = Request::capture()
);

$response->send();// Envía la respuesta HTTP al cliente

$kernel->terminate($request, $response);// Termina el ciclo de vida de la solicitud, ejecutando tareas finales como middleware de terminación
