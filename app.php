<?php

require_once 'vendor/autoload.php';

$config = include __DIR__ . '/config/core.php';

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
$app->githubClientId = $app->config['githubClientId'];
$app->githubClientSecret = $app->config['githubClientSecret'];

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
            'authorizeUrl' => $authorizeUrl
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
    $all_starred = array();
    $has_next_page = true;
    while ($has_next_page) {
        $all_starred = array_merge($all_starred, $app->github->api('me')->starred($page));
        $has_next_page = $app->github->getHttpClient()->getLastResponse()
            ->getHeader('link')->getLink('next');
        $page++;
    }

    return $app->render(
        'stars.html.php',
        array(
            'starred' => $all_starred,
        )
    );
});

return $app;
