#!/usr/bin/env php
<?php

$app = include __DIR__ . '/../app.php';

use Boepi\Command\NotifyCommand;
use Symfony\Component\Console\Application;

$notifyCommand = new NotifyCommand;
$notifyCommand->setGithubClient($app->github);
$notifyCommand->setGithubToken($app->githubToken);
$notifyCommand->setMailgun($app->mailgun);

$cli = new Application();
$cli->add($notifyCommand);
$cli->run();
