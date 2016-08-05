<?php
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
class SitelistingController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }

	public function getsites()
	{
		$this->app->response
				->setStatusCode(201, "Created")
				->setJsonContent(array('status' => 'OK','message' => 'Mayank Testing'));
        return $this->app->response;
	}
}
