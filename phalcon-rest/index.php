<?php

use Phalcon\Di,
    Phalcon\Loader as Loader,
    Phalcon\Config\Adapter\Ini as ConfigIni,
    Phalcon\Db\Adapter\Pdo\Postgresql as PostgresAdapter,

    Phalcon\Http\Request as Request,
    Phalcon\Http\Response as Response,

    Phalcon\Mvc\Router as Router,
    Phalcon\Mvc\Micro as Micro,
    Phalcon\Mvc\Micro\Collection as MicroCollection,
    Phalcon\Mvc\Model as Model,
    Phalcon\Mvc\Model\Manager as ModelsManager,
    Phalcon\Mvc\Model\Metadata\Memory as ModelsMetadataMemory,
    Phalcon\Mvc\Model\Transaction\Failed as TransactionFailed,
    Phalcon\Mvc\Model\Transaction\Manager as TransactionManager,
    Phalcon\Filter as Filter;

$di = new Di();

$di->set('modelsManager', function() {
	return new ModelsManager();
});
$di->set('modelsMetadata', function() {
	return new ModelsMetadataMemory();
});
$di->set('request', function() {
	return new Request();
});
$di->set('response', function() {
	return new Response();
});
$di->set('router', function() {
	return new Router();
});
$di->setShared('transactions', function () {
    return new TransactionManager();
});

$di->setShared('filter', function() {
    return new Filter();
});

// Setup loader
$loader = new Loader();
$loader->registerDirs(array(
    __DIR__ . '/app/models/',    
    __DIR__ . '/app/controllers/'
))->register();

// Read database configuration from the config.ini
$config = new ConfigIni(__DIR__ . '/config/config.ini');

// Setup the database service
$di->set('db', function () use ($config) {
    return new PostgresAdapter(array(
        "host" => $config->database->host,
        "username" => $config->database->username,
        "password" => $config->database->password,
        "dbname" => $config->database->dbname
    ));
});
// Start Micro
$app = new Micro();
$app->setDI($di);

// Include controllers
$app['controllers'] = function() {
    return [
        'core' => true,
        'users' => true,
        'messages' => true,
        'sites' => true,
        'subsites' => true,
        'sitelisting' => true,
        'integrategit' => true,
        'github' => true,
    ];
};

// Authentication
$app['auth'] = function() use ($app, $config) {
    $auth = array();
    $authorization = $app->request->getHeader("AUTHORIZATION");
    if ($authorization) {
        $cut = str_replace('Basic ', '', $authorization);
        $creds = explode(':', base64_decode($cut));
        $auth['login'] = $creds[0];
        $auth['password'] = $creds[1];
    } else {
        $auth['login'] = null;
        $auth['password'] = null;
    }
    
    $usr = new Users();    
    $auth['id'] = $usr->getUserId($auth['login'], $auth['password']);
    
    return $auth;
};

// CoreController
if ($app['controllers']['core']) {
    $core = new MicroCollection();

    // Set the handler & prefix
    $core->setHandler(new CoreController($app));
    $core->setPrefix('/');

    // Set routers
    $core->get('/', 'index');

    $app->mount($core);
}

//  MessagesController
if ($app['controllers']['messages']) {
    $messages = new MicroCollection();

    // Set the handler & prefix
    $messages->setHandler(new MessagesController($app));
    $messages->setPrefix('/messages');

    // Set routers
    $messages->post('/', 'create');
    $messages->get('/{id_sender}/{id_receiver}', 'stream');
    $messages->get('/', 'inbox');

    $app->mount($messages);
}

// epm_sitesController
if ($app['controllers']['sites']) {
    $users = new MicroCollection();

    // Set the handler & prefix
    $users->setHandler(new EpmsitesController($app));
    $users->setPrefix('/Epmsites');

    // Set routers
    $users->post('/', 'create');
    #$users->post('/{apikey}', 'create');
    $users->put('/{id}', 'update');
    $users->delete('/{id}', 'delete');
    $users->get('/', 'preview');
    $users->get('/{id}', 'info');

    $app->mount($users);
}

// epm_subsitesController
if ($app['controllers']['subsites']) {
    $users = new MicroCollection();

    // Set the handler & prefix
    $users->setHandler(new EpmsubsitesController($app));
    $users->setPrefix('/Epmsubsites');

    // Set routers
    $users->post('/', 'create');
    #$users->post('/{apikey}', 'create');
    $users->put('/{id}', 'update');
    $users->delete('/{id}', 'delete');
    $users->get('/', 'preview');
    $users->get('/{id}', 'info');

    $app->mount($users);
}

//  SitelistingController
if ($app['controllers']['sitelisting']) {
    $sitelisting = new MicroCollection();

    // Set the handler & prefix
    $sitelisting->setHandler(new SitelistingController($app));
    $sitelisting->setPrefix('/Sitelisting');

    // Set routers
    $sitelisting->get('/', 'getsites');

    $app->mount($sitelisting);
}

// UsersController
if ($app['controllers']['users']) {
    $users = new MicroCollection();

    // Set the handler & prefix
    $users->setHandler(new UsersController($app));
    $users->setPrefix('/users');

    // Set routers
    $users->post('/', 'create');
    $users->put('/{id}', 'update');
    $users->delete('/{id}', 'delete');
    $users->get('/', 'preview');
    $users->get('/{id}', 'info');

    $app->mount($users);
}

// TestGithubController
if ($app['controllers']['integrategit']) {
    $integrateobj = new MicroCollection();

    // Set the handler & prefix
    $integrateobj->setHandler(new IntegrategitController($app));
    $integrateobj->setPrefix('/integrategit');

    // $github->post ('/git/{repopath}/{companyid}/{clientgitrepo}/{siteid}/{clientbranch}/{clienttoken}/{clientuser}/{sitenameforgit}','gitaction');
    //$gitinit->get ('/gitaction','gitaction');
    $integrateobj->post('/','create');
    $app->mount($integrateobj);

}

// GithubController
if ($app['controllers']['github']) {
    $github = new MicroCollection();

    // Set the handler & prefix
    $github->setHandler(new GithubController($app));
    $github->setPrefix('/github');

    // a hook which has to be installed on every client's repo
    $github->post('/evolverWebhook',	'repoWebhook');

    // get list of commits
    $github->get ('/commits/{company_id}/{site_id}/{env}',	'commits');

    // get commits difference bewteen two environments
    $github->get ('/commitsDiff/{company_id}/{site_id}/{env1}/{env2}',	'commitsDiff');

    // create new Repo Branch
    $github->get ('/createRepoBranch/{company_id}/{site_id}/{git_id}/{branch_name}/{sha}',	'makeRepoBranch');

    // Create a repo Hook
    $github->post('/addHook', 'addHook');

//    $github->get('/_deploys', 'deploysAction');
//    $github->get('/deploys/{company_id}/{site_id}/{env_id}', 'deploys');
    $github->post('/makeDeploy', 'makeDeploy');

//    $github->get('/commitDetails/{company_id}/{site_id}/{env_id}', 'commitDetails');
//    $github->get('/userEvents/{company_id}/{user_id}', 'userEvents');

    $app->mount($github);
}

// Use composer autoloader to load vendor classes
require_once __DIR__ . '/vendor/autoload.php';

// Not Found
$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
});

$app->handle();
