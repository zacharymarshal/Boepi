<?php

session_cache_limiter(false);
session_start();

$app = include_once '../app.php';
$app->run();
