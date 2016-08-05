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
        'site' => true,
        'integrategit' => true,
        'github' => true,
        'deploy' => true,
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
    $users->post('/createsubsite', 'createSubsite');
    $users->delete('/{id}', 'delete');
    $users->delete('/deletesubsite/{id}', 'deleteSubsite');
    $app->mount($users);
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

// IntegrategitController for handling the git stuffs.
if ($app['controllers']['integrategit']) {
    $integrateobj = new MicroCollection();

    // Set the handler & prefix
    $integrateobj->setHandler(new IntegrategitController($app));
    $integrateobj->setPrefix('/integrategit');
    // Create git repo branch and hooks and handle the public and private git.
    $integrateobj->post('/','create');
    $integrateobj->delete('/findanddeletehook/{gituser}/{gitrepo}/{gittoken}', 'findanddeleteHook');
    $integrateobj->get('/deploy','deploy');  
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
    $github->get ('/getcommits/{company_id}/{git_id}/{env}',	'getcommits');
    $github->get ('/getallcommits/{company_id}/{git_id}/{env}',	'getallcommits'); // by mayank

    // get commits difference bewteen two environments
    $github->get ('/commitsDiff/{company_id}/{site_id}/{env1}/{env2}',	'commitsDiff');

    // create new Repo Branch
    $github->get ('/createRepoBranch/{company_id}/{site_id}/{git_id}/{branch_name}/{sha}',	'makeRepoBranch');

    // Create a repo Hook
    $github->post('/addHook', 'addHook');

    //Create a Deployment
    $github->get('/makeDeploy/{company_id}/{site_id}/{env}', 'makeDeploy');

    // get list of deployments
    $github->get('/deploys/{company_id}/{site_id}/{env}', 'deploys');

    // Get repo hook
    $github->get('/gethook', 'hooksAction');

    // Delete repo hook
    $github->delete('/deletehook', 'deleteHookAction');
//    $github->get('/_deploys', 'deploysAction');

//    $github->get('/commitDetails/{company_id}/{site_id}/{env_id}', 'commitDetails');
//    $github->get('/userEvents/{company_id}/{user_id}', 'userEvents');

    $app->mount($github);
}


// GithubController
if ($app['controllers']['site']) {
    $site = new MicroCollection();

    // Set the handler & prefix
    $site->setHandler(new SiteController($app));
    $site->setPrefix('/site');

    // a hook which has to be installed on every client's repo
    $site->get('/getsites',	'ListSitesAndSubSites');
    $site->post('/getip',	'GetIPAddressOfSites');
    $site->get('/getconnectioninfo',	'GetConnectionInfo');
    $site->get('/importdb',	'ImportDBBySQLFileURL');
    $site->get('/importfiles',	'ImportFiles');
    $site->get('/addusertodb',	'AddSecondaryUserToDB');
    $site->get('/clonedb',	'CloneDB');
    $site->get('/clonefiles',	'CloneFiles');
    $site->get('/getsinglesite',	'GetSingleSite');
    $site->get('/runupdatedotphp',	'RunUpdateDotPHPFile');
    $site->get('/clearcache',	'ClearCache');
    $site->get('/exportdbtos3',	'ExportDBToS3');
    $site->get('/exportfilestos3',	'ExportFilesToS3');
    $site->get('/gethistoricaldbexports',	'GetListOfHistoricalDBExports');
    $site->get('/gethistoricalfileexports',	'GetListOfHistoricalFileExports');
    $site->get('/getpresignedurl',	'GetPresignedURL');

    $app->mount($site);
}

/*if ($app['controllers']['deploy']) {
    $deploy = new MicroCollection();

    // Set the handler & prefix
    $deploy->setHandler(new DeploySiteController($app));
    $deploy->setPrefix('/deploy');

    $deploy->get('/site',	'Deploy');
	
	

    $app->mount($deploy);
}*/



// IntegrategitController for handling the git stuffs.
if ($app['controllers']['deploy']) {
    $deploy = new MicroCollection();

    // Set the handler & prefix
    $deploy->setHandler(new DeploySiteController($app));
    $deploy->setPrefix('/deploy');
    // Create git repo branch and hooks and handle the public and private git.
    $deploy->post('/site','Deploy');
    $app->mount($deploy);

}



// Use composer autoloader to load vendor classes
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/aws/aws-autoloader.php';

// Not Found
$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
});

$app->handle();
