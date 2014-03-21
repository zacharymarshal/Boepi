<?php

require_once 'vendor/autoload.php';

use RedBean_Facade as R;
use Lstr\Assetrinc\AssetService;
use Mailgun\Mailgun;

$config = include __DIR__ . '/config/core.php';
$db_config = include __DIR__ . '/config/database.php';

R::setup($db_config['dsl'], $db_config['username'], $db_config['password']);

$app = new \Slim\Slim(array(
    'mode'           => $config['mode'],
    'debug'          => $config['debug'],
    'templates.path' => __DIR__ .'/templates',
));
$app->container->config = function () use ($config) {
    return $config;
};
$app->container->singleton('github', function () {
    return new \Github\Client();
});
$app->container->singleton('asset', function () {
    return new AssetService(
        array('app' => __DIR__ . '/assets'),
        'http://localhost/~zacharyrankin/boepi/web/assets',
        array('debug' => false)
    );
});
$app->githubClientId = $app->config['githubClientId'];
$app->githubClientSecret = $app->config['githubClientSecret'];
$app->githubToken = $app->config['githubToken'];
$app->container->singleton('mailgun', function ($app) {
    return new Mailgun($app->config['mailgunApiKey']);
});

$app->get('/assets/:assetName', function ($name) use ($app) {
    $app->response->setStatus(200);
    $app->response->headers->set('Content-Type', $app->asset->getContentType($name));
    $app->response->setBody($app->asset->getContent($name));

    return $app->response;
})->conditions(array('assetName' => '.*'));

$app->get('/', function () use ($app) {
    $state = $_SESSION['state'] = rand();

    $authorizeUrl = 'https://github.com/login/oauth/authorize?'
        . http_build_query(array(
            'redirect_uri' => $app->config['url'] . '/access',
            'client_id'    => $app->githubClientId,
            'state'        => $state,
            'scope'        => 'user:email',
        ));

    return $app->render(
        'index.html.php',
        array(
            'authorizeUrl' => $authorizeUrl,
            'jsTag'        => function ($file) use ($app) {
                return $app->asset->jsTag($file);
            },
            'cssTag'        => function ($file) use ($app) {
                return $app->asset->cssTag($file);
            },
        )
    );
});

$app->get('/access', function () use ($app) {
    if ($app->request->get('state') != $_SESSION['state']) {
        $app->halt(401);
    }
    $_SESSION['code'] = $app->request->get('code');
    $app->redirect('access_token');
});

$app->get('/access_token', function () use ($app) {
    $response = $app->github->getHttpClient()->post(
        'https://github.com/login/oauth/access_token',
        array(
            'client_id'     => $app->githubClientId,
            'client_secret' => $app->githubClientSecret,
            'code'          => $_SESSION['code'],
        ),
        array('Accept' => 'application/json')
    );
    $accessTokenInfo = json_decode($response->getBody(true), true);
    $_SESSION['accessToken'] = $accessTokenInfo['access_token'];
    $app->redirect('stars');
});

$app->get('/stars', function () use ($app) {
    $authenticate = $app->github->authenticate(
        $_SESSION['accessToken'],
        null,
        Github\Client::AUTH_URL_TOKEN
    );

    $emails = $app->github->api('me')->emails()->all();

    // $releases = $client->api('repos')->releases()->all($starred['owner'], $starred['name']);

    $page = 1;
    $allStarred = array();
    $hasNextPage = true;
    while ($hasNextPage) {
        $allStarred = array_merge($allStarred, $app->github->api('me')->starred($page));
        $hasNextPage = $app->github->getHttpClient()->getLastResponse()
            ->getHeader('link')->getLink('next');
        $page++;
    }

    foreach ($allStarred as $starred) {
        $repository = R::findOne('repository', 'full_name = ?', array($starred['full_name']));
        if (!$repository) {
            $repository = R::dispense('repository');
            $repository->full_name = $starred['full_name'];
            $repository->owner = $starred['owner']['login'];
            $repository->name = $starred['name'];
        }
        foreach ($emails as $emailAddress) {
            $email = R::findOne('email', 'address = ?', array($emailAddress));
            if (!$email) {
                $email = R::dispense('email');
                $email->address = $emailAddress;
            }
            $repository->sharedEmailList[] = $email;
        }
        R::store($repository);
    }

    return $app->render(
        'stars.html.php',
        array(
            'starred' => $allStarred,
        )
    );
});

return $app;
