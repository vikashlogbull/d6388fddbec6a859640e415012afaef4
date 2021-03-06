<?

use Phalcon\Mvc\Controller;

class GithubController extends Controller {

    var $repo_owner;
    var $repo_name;
    var $repo_token;
	var $response;

	public function onConstruct($options = array())	{
		$this->repo_owner	= $this->request->get("repo_owner", "string");
		$this->repo_name	= $this->request->get("repo_name", "string");
		$this->repo_token	= $this->request->get("token", "string");

	    $this->response = array(
	    	"Status"		=> "Failed",
	    	"MsgCode"		=> "0",
	    	"msgdescription"=> "General Error",
	    );
	}

	public function deploys($company_id,$site_id,$env) {

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> array(),
	    );

		if ($this->request->isGet() &&
		    ($env == 'dev' || $env == 'test' || $env == 'live') &&

			($res = $this->modelsManager->executeQuery("
				SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token,
					gb.branch_name
				FROM sites s
				    LEFT JOIN company c ON s.companyid=c.backendcompanyid
					LEFT JOIN xrefSiteEnvironment se ON se.siteid=s.siteid
					LEFT JOIN xrefGitSites gs ON se.gitid=gs.gitid AND se.siteid=gs.sitesid
					LEFT JOIN environment e ON e.environmentid=se.environmentid
					LEFT JOIN git g ON se.gitid=g.git_id
					JOIN gitBranch gb ON g.git_id=gb.git_id AND gb.id = se.git_branch_id
				WHERE c.frontendcompanyid = :company_id: AND s.siteid = :site_id: AND 
					LOWER(e.environmentname) = :env:
			", array(
		   		'company_id'=> $company_id,
		   		'site_id'	=> $site_id,
		   		'env'	    => $env,
			))) &&

			($repo_owner = trim($res[0]->repo_owner)) &&
			($repo_name  = trim($res[0]->repo_name)) &&
			($repo_token = trim($res[0]->repo_token)) &&
			($branch_name= trim($res[0]->branch_name)) &&

			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

			try {
				if (is_array($deploys = $client->api('deployment')->all(
						$repo_owner, $repo_name, 
						array('environment' => $env,'ref' => $branch_name)
					))
				) {
					$this->response['Status']	= 'Success';
					$this->response['MsgCode']	= '100';
					$this->response['MsgDescription']	= 'Operation done successfully';
					$this->response['Data']	= $deploys;
error_log("branch_name=".var_export($branch_name,true));
error_log("deploys=".var_export($deploys,true));
				}
			} catch (ErrorException $e) {
				$this->response = array(
	            	"Status"		=> "Failure",
	            	"MsgCode"		=> "-1",
	            	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	            );
			}
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}

	public function deployStatusesAction() {

		if ($this->request->isGet() &&
			 $this->repo_owner && $this->repo_name &&
			($deployId = $this->request->get("id", "string")) && 
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);

			if (is_array($deployStatuses = $client->api('deployment')->getStatuses(
				$this->repo_owner, $this->repo_name, $deployId))
			) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $deployStatuses,
	            );
	    	}
		}

		return $this->response;
	}
/*
	public function updatedeployStatusesAction() {

		if ($this->request->isGet() &&
			 $this->repo_owner && $this->repo_name &&
			($deployId = $this->request->get("id", "string")) && 
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);

			if (is_array($deployStatuses = $client->api('deployment')->getStatuses(
				$this->repo_owner, $this->repo_name, $deployId))
			) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $deployStatuses,
	            );
	    	}
		}

		return $this->response;
	}
*/
	public function makeDeployAction() {

		if ($this->request->isPost() &&
			($env = $this->request->getPost("env", "string")) && 
			 $this->repo_owner && $this->repo_name &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			$_params = array('environment' => $env);

			if ($deployCommit = $this->request->getPost("sha", "string")) {
				$_params['ref'] = $deployCommit;

				if ($deployPayload = $this->request->getPost("payload", "string")) {
					$_params['payload'] = $deployPayload;
				}

				if (is_array($deploy = $client->api('deployment')->create(
					$this->repo_owner, $this->repo_name, $_params))
				) {
					$this->response = array(
	                	"Status"		=> "Success",
	                	"MsgCode"		=> "100",
	                	"MsgDescription"=> "Operation done successfully",
	                	"Data"		=> $deploy,
	                );
	    		}
			}
		}

		return $this->response;
	}

	public function makeRepoBranch($company_id,$site_id,$git_id,$branch_name,$sha) {
	                                                                                
		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> array(),
	    );

		if (($branch_name_arr = explode('_',$branch_name)) &&
    	    ($branch_type = end($branch_name_arr)) &&
			($branch_type == 'dev' || $branch_type == 'test' || $branch_type == 'live') &&
			($env = &$branch_type)
//			($env = ($branch_type == 'master') ? 'dev' : $branch_type)
		) {
		    try {
				if (($res = $this->modelsManager->executeQuery("
						SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token,g.git_id
						FROM xrefGitSites gs
							JOIN sites s ON s.siteid = gs.sitesid
							JOIN git g ON gs.gitid=g.git_id
						WHERE s.companyid = :company_id: AND s.siteid = :site_id: AND gs.gitid = :git_id:
					", array(
		   	   	   		'company_id'=> $company_id,
		   	   	   		'site_id'	=> $site_id,
		   	   	   		'git_id'	=> $git_id,
					))) && isset($res[0]) &&

					($repo_owner = &$res[0]->repo_owner) &&
					($repo_name  = &$res[0]->repo_name) &&
					($repo_token = &$res[0]->repo_token) &&

					($res2 = $this->modelsManager->executeQuery("
						SELECT 1 FROM gitbranch WHERE git_id = :git_id: AND branch_name = :branch_name:
					", array(
		   	   	   		'git_id'	=> $git_id,
		   	   	   		'branch_name'=> $branch_name,
					)))
				) {
				    if (isset($res2[0])) {
						$this->response['Status']	= 'Error';
						$this->response['MsgCode']	= '0';
						$this->response['MsgDescription']	= 'The branch is already exists in the database';
					} else {
			        
						$client = new \Github\Client(
							new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache')));
					
						$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);
					
						try {
							if (is_array($ref = $client->api('git')->references()->create(
								trim($repo_owner), trim($repo_name),
								array('ref' => 'refs/heads/'.$branch_name, 'sha' => $sha)))
							) {
                                                          $this->response['Status']       = 'Success';
                                                          $this->response['MsgCode']      = '200';
                                                          $this->response['MsgDescription']  = 'Branch created Successfully!';
	    					}
	    			    
						} catch (\Github\Exception\RuntimeException $e) {
							$this->response = array(
	                        	"Status"		=> "Failure",
	                        	"MsgCode"		=> "-1",
	                        	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	                        );
						}
					}
				}
		    } catch (Phalcon\Mvc\Model\Exception $e) {
				$this->response['Status']	= 'Error';
				$this->response['MsgCode']	= '0';
				$this->response['MsgDescription']	= "Failed, reason: ". $e->getMessage();
		    }
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}

	public function getcommits($company_id,$git_id,$env) {

	    $commits = array();

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> &$commits,
	    );

		if ($this->request->isGet() &&
		    ($env == 'dev' || $env == 'test' || $env == 'live') &&

			($res = $this->modelsManager->executeQuery("
				SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token
				FROM git g
				    LEFT JOIN company c ON g.companyid=c.backendcompanyid
					LEFT JOIN environment e ON LOWER(e.environmentname) = :env:
				WHERE c.frontendcompanyid = :company_id: AND g.git_id = :git_id: 
				AND g.repo_access_type = 'private'
			", array(
		   		'company_id'=> $company_id,
		   		'git_id'	=> $git_id,
		   		'env'	    => $env,
			))) &&

			($repo_owner = &$res[0]->repo_owner) &&
			($repo_name  = &$res[0]->repo_name) &&
			($repo_token = &$res[0]->repo_token) &&

			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

			try {

				if ($_commits = $client->api('repo')->commits()->all(
					trim($repo_owner), trim($repo_name), 
					array('sha' => ($env == 'dev' ? 'master' : $env)))
				) {
					foreach ($_commits as $commit) {
						$commits[] = array(
							'sha'			=> $commit['sha'],
							'hash'			=> substr($commit['sha'],0,8),
							'author_name'	=> $commit['commit']['author']['name'],
							'author_email'	=> $commit['commit']['author']['email'],
							'message'		=> $commit['commit']['message'],
							'url'			=> $commit['commit']['url'],
							'ts'			=> $commit['commit']['author']['date'],
						);
					}
		    
					$this->response['Status']	= 'Success';
					$this->response['MsgCode']	= '100';
					$this->response['MsgDescription']	= 'Operation done successfully';
				}
			} catch (ErrorException $e) {
				$this->response = array(
	            	"Status"		=> "Failure",
	            	"MsgCode"		=> "-1",
	            	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	            );
			}
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}
	
	//by mayank
	public function getallcommits($company_id,$git_id,$env) {

	    $commits = array();

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> &$commits,
	    );

		if ($this->request->isGet() &&
		    ($res = $this->modelsManager->executeQuery("
				SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token
				FROM git g
				    LEFT JOIN company c ON g.companyid=c.backendcompanyid
					LEFT JOIN environment e ON LOWER(e.environmentname) = :env:
				WHERE c.frontendcompanyid = :company_id: AND g.git_id = :git_id: 
				AND g.repo_access_type = 'private'
			", array(
		   		'company_id'=> $company_id,
		   		'git_id'	=> $git_id,
		   		'env'	    => $env,
			))) &&

			($repo_owner = &$res[0]->repo_owner) &&
			($repo_name  = &$res[0]->repo_name) &&
			($repo_token = &$res[0]->repo_token) &&

			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

			try {

				if ($_commits = $client->api('repo')->commits()->all(
					trim($repo_owner), trim($repo_name), 
					array('sha' => ($env == 'dev' ? 'master' : $env)))
				) {
					foreach ($_commits as $commit) {
						$commits[] = array(
							'sha'			=> $commit['sha'],
							'hash'			=> substr($commit['sha'],0,8),
							'author_name'	=> $commit['commit']['author']['name'],
							'author_email'	=> $commit['commit']['author']['email'],
							'message'		=> $commit['commit']['message'],
							'url'			=> $commit['commit']['url'],
							'ts'			=> $commit['commit']['author']['date'],
						);
					}
		    
					$this->response['Status']	= 'Success';
					$this->response['MsgCode']	= '100';
					$this->response['MsgDescription']	= 'Operation done successfully';
				}
			} catch (ErrorException $e) {
				$this->response = array(
	            	"Status"		=> "Failure",
	            	"MsgCode"		=> "-1",
	            	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	            );
			}
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}	

	public function commits($company_id,$site_id,$env) {

	    $commits = array();

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> &$commits,
	    );

		if ($this->request->isGet() &&
		    ($env == 'dev' || $env == 'test' || $env == 'live') &&

			($res = $this->modelsManager->executeQuery("
				SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token
				FROM sites s
				    LEFT JOIN company c ON s.companyid=c.backendcompanyid
					LEFT JOIN xrefSiteEnvironment se ON se.siteid=s.siteid
					LEFT JOIN xrefGitSites gs ON se.gitid=gs.gitid AND se.siteid=gs.sitesid
					LEFT JOIN git g ON se.gitid=g.git_id
					LEFT JOIN environment e ON e.environmentid=se.environmentid
				WHERE c.frontendcompanyid = :company_id: AND s.siteid = :site_id: AND 
					LOWER(e.environmentname) = :env:
			", array(
		   		'company_id'=> $company_id,
		   		'site_id'	=> $site_id,
		   		'env'	    => $env,
			))) &&

			($repo_owner = &$res[0]->repo_owner) &&
			($repo_name  = &$res[0]->repo_name) &&
			($repo_token = &$res[0]->repo_token) &&

			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

			try {

				if ($_commits = $client->api('repo')->commits()->all(
					trim($repo_owner), trim($repo_name), 
					array('sha' => ($env == 'dev' ? 'master' : $env)))
				) {
					foreach ($_commits as $commit) {
						$commits[] = array(
							'sha'			=> $commit['sha'],
							'hash'			=> substr($commit['sha'],0,8),
							'author_name'	=> $commit['commit']['author']['name'],
							'author_email'	=> $commit['commit']['author']['email'],
							'message'		=> $commit['commit']['message'],
							'url'			=> $commit['commit']['url'],
							'ts'			=> $commit['commit']['author']['date'],
						);
					}
		    
					$this->response['Status']	= 'Success';
					$this->response['MsgCode']	= '100';
					$this->response['MsgDescription']	= 'Operation done successfully';
				}
			} catch (ErrorException $e) {
				$this->response = array(
	            	"Status"		=> "Failure",
	            	"MsgCode"		=> "-1",
	            	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	            );
			}
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}

	public function commitsDiff($company_id,$site_id,$base,$head) {

	    $commits = array();

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> &$commits,
	    );

		if ($this->request->isGet() &&
		    ($base == 'dev' || $base == 'test' || $base == 'live') &&
		    ($head == 'dev' || $head == 'test' || $head == 'live') &&

			($res = $this->modelsManager->executeQuery("
				SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token,
					LOWER(e.environmentname) AS env,gb.branch_name
				FROM sites s
				    LEFT JOIN company c ON s.companyid=c.backendcompanyid
					LEFT JOIN xrefSiteEnvironment se ON se.siteid=s.siteid
					LEFT JOIN xrefGitSites gs ON se.gitid=gs.gitid AND se.siteid=gs.sitesid
					LEFT JOIN environment e ON e.environmentid=se.environmentid
					LEFT JOIN git g ON se.gitid=g.git_id
					JOIN gitBranch gb ON g.git_id=gb.git_id AND gb.id = se.git_branch_id
				WHERE c.frontendcompanyid = :company_id: AND s.siteid = :site_id: AND 
					LOWER(e.environmentname) IN (:base:, :head:)
			", array(
		   		'company_id'=> $company_id,
		   		'site_id'	=> $site_id,
		   		'base'	    => $base,
		   		'head'	    => $head,
			))) &&

			($repo_owner = &$res[0]->repo_owner) &&
			($repo_name  = &$res[0]->repo_name) &&
			($repo_token = &$res[0]->repo_token) &&

			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

			foreach ($res as $_rec) {
				if ($_rec->env == $base) {
					$base_branch = $_rec->branch_name;
				}
				if ($_rec->env == $head) {
					$head_branch = $_rec->branch_name;
				}
			}
			if ($_commits = $client->api('repo')->commits()->compare(
				trim($repo_owner), trim($repo_name), trim($base_branch), trim($head_branch))
			) {
				$commits['status']			= &$_commits['status'];
				$commits['ahead_by']		= &$_commits['ahead_by'];
				$commits['behind_by']		= &$_commits['behind_by'];
				$commits['total_commits']	= &$_commits['total_commits'];
				$commits['commits']			= array();

				foreach ($_commits['commits'] as $commit) {
					$commits['commits'][] = array(
						'sha'			=> $commit['sha'],
						'hash'			=> substr($commit['sha'],0,8),
						'author_name'	=> $commit['commit']['author']['name'],
						'author_email'	=> $commit['commit']['author']['email'],
						'message'		=> $commit['commit']['message'],
						'url'			=> $commit['commit']['url'],
						'ts'			=> $commit['commit']['author']['date'],
					);
				}

				$this->response['Status']	= 'Success';
				$this->response['MsgCode']	= '100';
				$this->response['MsgDescription']	= 'Operation done successfully';
			}
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}

/* TODO
	public function commitActivityAction() {

		if ($this->request->isGet() &&
			$this->repo_owner && $this->repo_name &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);

			if ($commits = $client->api('repo')->commits()->all(
				$this->repo_owner, $this->repo_name, array('sha' => 'master'))
			) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $commits,
	            );
	    	}
		}

		return $this->response;
	}
*/

	public function commitDetailsAction() {

		if ($this->request->isGet() &&
			($sha = $this->request->get("sha", "string")) && 
			 $this->repo_owner && $this->repo_name &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);

			if ($commitDetails = $client->api('repo')->commits()->show(
				$this->repo_owner, $this->repo_name, $sha)
			) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $commitDetails,
	            );
	    	}
		}

		return $this->response;
	}

	public function hooksAction() {

		if ($this->request->isGet() &&
			$this->repo_owner && $this->repo_name &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);

			if (is_array($hooks = $client->api('repo')->hooks()->all(
				$this->repo_owner, $this->repo_name))
			) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $hooks,
	            );
	    	}
		}
                echo json_encode($this->response);
		return $this->response;
	}

	public function deleteHookAction() {
		if ($this->request->isDelete() &&
			($hookId = $this->request->get("id", "string")) && 
			$this->repo_owner && $this->repo_name &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			$client->api('repo')->hooks()->remove($this->repo_owner, $this->repo_name, $hookId);
			$this->response = array(
	        	"Status"		=> "Success",
	        	"MsgCode"		=> "100",
	        	"MsgDescription"=> "Operation done successfully",
	        );
		}
                echo json_encode($this->response);
		return $this->response;
	}

	public function addHook() {

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> array(),
	    );

		if ($this->request->isPost() &&
			($body = $this->request->getJsonRawBody()) &&
			property_exists($body,'events') && is_array($body->events) && 
			property_exists($body,'config') && is_array($body->config) && 
			isset($body->config[0]) && is_object($body->config[0]) &&
			property_exists($body->config[0],'url') && $body->config[0]->url &&
// TODO
//			isset($body->config['secret']) && $body->config['secret'] &&
//			($config['secret'] == hash_hmac('sha1', $this->RawPayload, $SecretKey, false )) &&

			property_exists($body,'repo_owner') && ($repo_owner = &$body->repo_owner) && 
			property_exists($body,'repo_name') && ($repo_name = &$body->repo_name) && 
			property_exists($body,'repo_token') && ($repo_token = &$body->repo_token) && 

			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);
			$body->config['content_type'] = 'json';

			$_params = array(
			    'name'		=> 'web',
			    'active'	=> (property_exists($body,'active') ? (bool) $body->active : true),
			    'events'	=> $body->events,
				'config'	=> (array) $body->config[0],
			);

			if (is_array($hook = $client->api('repo')->hooks()->create(
				trim($repo_owner), trim($repo_name), $_params))
			) {
				$this->response['Status']	= 'Success';
				$this->response['MsgCode']	= '100';
				$this->response['MsgDescription']	= 'Operation done successfully';
                                $this->response['response'] = $hook;
			}

		} else {
			$this->response['Status']	= 'Failure';
			$this->response['MsgCode']	= '0';
			$this->response['MsgDescription']	= 'Incorrect parameters provided';
		}
                echo json_encode($this->response);
		return $this->response;
	}

	public function branchDeployAction() {

		if ($this->request->isPost() &&
			($env = $this->request->getPost('env')) &&

			$this->repo_owner && $this->repo_name &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			$_params = array('ref' => $env,'environment' => $env,'auto_merge' => true);

			if (is_array($deploy = $client->api('deployment')->create(
				$this->repo_owner, $this->repo_name, $_params))
			) {
				$this->response = array(
		        	"Status"		=> "Success",
	    	    	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	        		"Data"			=> $deploy,
		        );
	    	}
		}

		return $this->response;
	}

	public function branchDeployHookAction() {

	    $this->repo_owner	= 'rus-ik';
	    $this->repo_name	= 'test1';
	    $this->repo_token	= '052216e0bc6e238bd22a10ab24aca56911624dc0';
		$local_repo_path	= 'C:\Program Files\Apache Software Foundation\Apache2.2\htdocs\test1';

		if ($this->request->isPost() &&
			($payload = $this->request->getPost('payload')) &&
			($payload = json_decode($payload))
		) {
			switch ($this->request->getHeader('X-GitHub-Event')) {
				case 'push':
                    $payload_ref_arr = explode('/',$payload->ref);
                    $env = end($payload_ref_arr);

					$cmd = 'cd '.$local_repo_path.'\\'.$env.
						' && git pull origin '.$env;
					$res = exec($cmd);

					$this->response = array(
						"Status"		=> "Success",
	                	"MsgCode"		=> "100",
	                	"MsgDescription"=> "Operation done successfully",
		            	"Data"			=> $res,
					);
					break;

				case 'deployment':
					if (property_exists($payload,'deployment') &&
						property_exists($payload->deployment,'environment') &&
						($payload->deployment->environment <> 'master') &&
						property_exists($payload->deployment,'repository_url') &&
						property_exists($payload->deployment,'id') &&
						property_exists($payload->deployment,'creator') &&
						property_exists($payload->deployment->creator,'login') &&
	            
						$this->repo_owner && $this->repo_name &&
						($client = new \Github\Client(
							new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
					) {
						$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);

//						Create 'pending' deployment status
						$deployStatus = $client->api('deployment')->updateStatus(
							$this->repo_owner, $this->repo_name, $payload->deployment->id,
							array('state' => 'pending', 'description' => 'Started a new deployment processing..')
						);

						$output = array(); 

						$cmd = 'cd '.$local_repo_path.'\\'.$payload->deployment->environment.
							' && git fetch --all'.
							' && git merge origin/'.$payload->deployment->environment.
							' && git push origin '.$payload->deployment->environment;

						if ($res = exec($cmd)) {
							
//							Create 'success' deployment status
							$deployStatus = $client->api('deployment')->updateStatus(
								$this->repo_owner, $this->repo_name, $payload->deployment->id,
								array('state' => 'success', 'description' => 'Deploy successfully finished..')
							);

							$this->response = array(
						    	"Status"		=> "Success",
	    	            		"MsgCode"		=> "100",
	        	            	"MsgDescription"=> "Operation done successfully",
	            	        	"Data"			=> $output,
						    );
						}
					}
					break;

				case 'deployment_status':
					if (property_exists($payload,'deployment') &&
						property_exists($payload->deployment,'environment') &&
						($payload->deployment->environment <> 'master') &&
						property_exists($payload->deployment,'repository_url') &&
						property_exists($payload->deployment,'id') &&
						property_exists($payload->deployment,'creator') &&
						property_exists($payload->deployment->creator,'login') &&
	            
						property_exists($payload,'deployment_status') &&
						property_exists($payload->deployment_status,'state') &&
						($payload->deployment_status->state == 'success') &&

						$this->repo_owner && $this->repo_name &&
						($client = new \Github\Client(
							new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
					) {
						$cmd = 'cd '.$local_repo_path.'\\'.$payload->deployment->environment.
							' && git pull origin '.$payload->deployment->environment;

						if ($res = exec($cmd)) {

							$this->response = array(
						    	"Status"		=> "Success",
	    	            		"MsgCode"		=> "100",
	        	            	"MsgDescription"=> "Operation done successfully",
	            	        	"Data"			=> $res,
						    );
						}
					}
					break;
			}
		}

		return $this->response;
	}

	public function userEventsAction() {

		if ($this->request->isPost() &&
			($user = $this->request->getPost("user")) && 
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			if (is_array($events = $client->api('user')->publicEvents($user))) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $events,
	            );
	    	}
		}

		return $this->response;
	}

	public function deployWebhook() {

error_log("repoWebhook, X-GitHub-Delivery=".$this->request->getHeader('X-GitHub-Delivery'));
error_log("repoWebhook, X-GitHub-Event=".$this->request->getHeader('X-GitHub-Event'));

		if ($this->request->isPost()) {

            switch ($this->request->getHeader('content_type')) {
            	case 'application/json':
            	    $payload = $this->request->getJsonRawBody();
					break;
            	case 'application/x-www-form-urlencoded':
					$payload = json_decode($this->request->getPost('payload'));
					break;
			}

			if (is_object($payload)) {
				switch ($this->request->getHeader('X-GitHub-Event')) {
					case 'push':
                        $payload_ref_arr = explode('/',$payload->ref);
                        $branch_name = end($payload_ref_arr);
                        $branch_name_arr = explode('_',$branch_name);
                        $env = end($branch_name_arr);

error_log("repoWebhook, payload->repository->url=".$payload->repository->url);
error_log("repoWebhook, payload->ref=".$payload->ref);
                        
						if (($res = $this->modelsManager->executeQuery("
								SELECT g.git_id,gb.id AS branch_id,g.git_username,g.github_token,
									g.repo_name,g.repo_path,g.repo_access_type,gb.connected_to
								FROM git g
									JOIN gitBranch gb ON g.git_id=gb.git_id
								WHERE gb.branch_name = :branch_name: AND g.git_url = :git_url:
							", array(
						   		'branch_name' =>  $branch_name,
						   		'git_url' => $payload->repository->url,
							))) && isset($res[0]) &&
			    
							($git_id = &$res[0]->git_id) &&
							($git_branch_id = &$res[0]->branch_id)
						) {
							if ($res[0]->repo_access_type == 'private') {
							    // our private repo

error_log("repoWebhook, Private repo..");

							    if (preg_match('/^Auto-merged (\w+) into (\w+) on deployment.$/',
							    		$payload->head_commit->message,$match) &&

							    	($repo_token = trim($res[0]->github_token)) &&
									($repo_owner = trim($res[0]->git_username)) &&
									($repo_name = trim($res[0]->repo_name)) &&

									($client = new \Github\Client(
										new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
									))
							    ) {
error_log("repoWebhook, Auto-merged push..doing deployment again..");
							    	// a new deployment returned a merge commit, so doing deployment again

									$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

									$deploy = $client->api('deployment')->create(
										$repo_owner, $repo_name, 
										array('ref' => $branch_name,'environment' => $env,'auto_merge' => true)
									);
error_log("repoWebhook, deploy=".var_export($deploy,true));

							    } else {

									$local_repo_path = (substr($res[0]->repo_path,-1) != '/') ? 
										$res[0]->repo_path.'/' : $res[0]->repo_path;
error_log("repoWebhook, Pulling origin..");
	                                
									$cmd = 'cd '.$local_repo_path.$env.
										' && git pull origin '.$env;
									$ret = exec($cmd);
							        
									$this->response = array(
										"Status"		=> "Success",
	                                	"MsgCode"		=> "100",
	                                	"MsgDescription"=> "Operation done successfully",
		                            	"Data"			=> $ret,
									);
							        
/*									
									foreach ($payload->commits as $commit) {
										$res2 = $this->modelsManager->executeQuery("
											INSERT INTO gitCommits (gitid,branch_id,commit_sha_hash,commit_hash,
												commit_msg,	commit_datetime, url, author_email, author_username)
											VALUES (:gitid:, :branch:,:sha:,:hash:,:msg:,:ts:,:url:,
												:a_email:,:a_uname:)
										", array(
						   	   	   	   		'gitid'	=> $git_id,
						   	   	   	   		'branch'=> $git_branch_id,
						   	   	   	   		'sha'	=> $commit->id,
						   	   	   	   		'hash'	=> substr($commit->id,0,8),
						   	   	   	   		'msg'	=> $commit->message,
						   	   	   	   		'ts'	=> $commit->timestamp,
						   	   	   	   		'url'	=> $commit->url,
						   	   	   	   		'a_email'	=> $commit->author->email,
						   	   	   	   		'a_uname'	=> $commit->author->username,
										));
									}
*/			    
                            	}

							} else {
			    
							    // original repo push, doing a merge into the master branch

error_log("repoWebhook, Original repo..");
                
								if (($github_token = trim($res[0]->github_token)) &&
									($git_username = trim($res[0]->git_username)) &&
									($repo_name = trim($res[0]->repo_name)) &&
			    
									($res = $this->modelsManager->executeQuery("
										SELECT g.git_id,gb.id AS branch_id,g.git_username,g.github_token,
											g.repo_name,g.repo_path,gb.branch_name
										FROM Git g
											JOIN gitBranch gb ON g.git_id=gb.git_id
										WHERE gb.connected_to = :git_branch_id:
									", array(
								   		'git_branch_id' =>  $git_branch_id,
									))) && isset($res[0]) &&

									($branch_name = &$res[0]->branch_name) &&
			    
									($upstream_github_token = trim($res[0]->github_token)) &&
									($upstream_git_username = trim($res[0]->git_username)) &&
									($upstream_repo_name = trim($res[0]->repo_name))
								) {
error_log("repoWebhook, Merging upstream..");
                                    $local_repo_path = (substr($res[0]->repo_path,-1) != '/') ? 
										$res[0]->repo_path.'/' : $res[0]->repo_path;

									$cmd = 'cd '.$local_repo_path.'master'.
										' && git fetch upstream'.
										' && git merge -X theirs upstream/'.$env.
										' && git push origin master';
									$ret = exec($cmd);
								}
							}
						}
						break;
			    
					case 'deployment':
						if (property_exists($payload,'deployment') &&
							property_exists($payload->deployment,'environment') &&
							($payload->deployment->environment <> 'master') &&
							property_exists($payload->deployment,'repository_url') &&
							property_exists($payload->deployment,'id') &&
							property_exists($payload->deployment,'creator') &&
							property_exists($payload->deployment->creator,'login') &&
	                
							$this->repo_owner && $this->repo_name &&
							($client = new \Github\Client(
								new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
						) {
							$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			    
//							Create 'pending' deployment status
							$deployStatus = $client->api('deployment')->updateStatus(
								$this->repo_owner, $this->repo_name, $payload->deployment->id,
								array('state' => 'pending', 'description' => 'Started a new deployment processing..')
							);
			    
							$output = array(); 
			    
							$cmd = 'cd '.$local_repo_path.'\\'.$payload->deployment->environment.
								' && git fetch --all'.
								' && git merge origin/'.$payload->deployment->environment.
								' && git push origin '.$payload->deployment->environment;
			    
							if ($res = exec($cmd)) {
								
//								Create 'success' deployment status
								$deployStatus = $client->api('deployment')->updateStatus(
									$this->repo_owner, $this->repo_name, $payload->deployment->id,
									array('state' => 'success', 'description' => 'Deploy successfully finished..')
								);
			    
								$this->response = array(
							    	"Status"		=> "Success",
	    		            		"MsgCode"		=> "100",
	            	            	"MsgDescription"=> "Operation done successfully",
	                	        	"Data"			=> $output,
							    );
							}
						}
						break;
			    
					case 'deployment_status':
						if (property_exists($payload,'deployment') &&
							property_exists($payload->deployment,'environment') &&
							($payload->deployment->environment <> 'master') &&
							property_exists($payload->deployment,'repository_url') &&
							property_exists($payload->deployment,'id') &&
							property_exists($payload->deployment,'creator') &&
							property_exists($payload->deployment->creator,'login') &&
	                
							property_exists($payload,'deployment_status') &&
							property_exists($payload->deployment_status,'state') &&
							($payload->deployment_status->state == 'success') &&
			    
							$this->repo_owner && $this->repo_name &&
							($client = new \Github\Client(
								new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
						) {
							$cmd = 'cd '.$local_repo_path.'\\'.$payload->deployment->environment.
								' && git pull origin '.$payload->deployment->environment;
			    
							$res = exec($cmd);
			    
							$this->response = array(
								"Status"		=> "Success",
	    		            	"MsgCode"		=> "100",
	            	        	"MsgDescription"=> "Operation done successfully",
	                	    	"Data"			=> $res,
							);
/* todo         
							$res2 = $this->modelsManager->executeQuery("
								INSERT INTO git_commits_xref_git_sites (deploy_id)
								SELECT ':deploy_id:',
								FROM Git AS g
									JOIN gitBranches gb ON g.git_id=gb.git_id
								WHERE gb.branch_name = ':branch:' AND g.git_url = ':git_url:'
							", array(
						   		'deploy_id'	=> $payload->deployment->id,
						   		'branch'=> $payload->deployment->environment,
						   		'git_url' => $payload->repository->url,
							));
*/              
						}
						break;
				}
			}
		}

		return $this->response;
	}

	public function repoWebhook() {
error_log("repoWebhook, X-GitHub-Delivery=".$this->request->getHeader('X-GitHub-Delivery'));
error_log("repoWebhook, X-GitHub-Event=".$this->request->getHeader('X-GitHub-Event'));

		if ($this->request->isPost()) {

            switch ($this->request->getHeader('content_type')) {
            	case 'application/json':
            	    $payload = $this->request->getJsonRawBody();
					break;
            	case 'application/x-www-form-urlencoded':
					$payload = json_decode($this->request->getPost('payload'));
					break;
			}

			if (is_object($payload)) {
				switch ($this->request->getHeader('X-GitHub-Event')) {
					case 'push':
                        $payload_ref_arr = explode('/',$payload->ref);
                        $branch_name = end($payload_ref_arr);
                        $branch_name_arr = explode('_',$branch_name);
                        $env = end($branch_name_arr);
error_log("repoWebhook, payload->repository->url=".$payload->repository->url);
error_log("repoWebhook, payload->ref=".$payload->ref);
                       error_log("BranchName: ".$branch_name);
                       error_log("GitURL: ".$payload->repository->url); 
						if (($res = $this->modelsManager->executeQuery("
                                                                
                                                        SELECT g.git_id AS git_id,gb.id AS branch_id,
                                                        g.git_username,g.github_token, g.repo_name,g.repo_path AS repopath,g.repo_access_type AS repo_access_type,
                                                        gb.connected_to AS connectedbranch, g.git_url AS git_url
                                                        FROM git g 
                                                        JOIN gitBranch gb ON g.git_id=gb.git_id
                                                        WHERE gb.branch_name = :branch_name: AND g.git_url = :git_url:
							", array(
						   		'branch_name' =>  $branch_name,
						   		'git_url' => $payload->repository->url.".git",
							))) && isset($res[0]) ) {
                                                        $repo_access_type = $res[0]->repo_access_type;
                                                        if ($repo_access_type == 'private') {
                                                            $git_id = $res[0]->git_id;
                                                            error_log("PrivateGitID: ".$git_id);
                                                        } elseif ($repo_access_type == 'public') {
                                                            $publicbranchid = $res[0]->connectedbranch; 
                                                            $resp = $this->modelsManager->executeQuery("
                                                                    SELECT g.git_url AS git_url, g.git_id AS git_id, g.git_username AS github_user, g.github_token AS github_token FROM 
                                                                    git g JOIN gitBranch gb ON g.git_id=gb.git_id WHERE id = :branchid:",
                                                                    array( 'branchid' => $publicbranchid));
                                                            $git_id = $resp[0]->git_id;
                                                            $private_git_url = trim($resp[0]->git_url);
                                                            $private_git_user = trim($resp[0]->github_user);
                                                            $private_git_token = trim($resp[0]->github_token);
                                                            error_log("PrivateGitURL: ".trim($private_git_url));
                                                            error_log("PrivateGitUser: ".$private_git_user);
                                                            error_log("PrivateGitToken: ".$private_git_token);

                                                            $privatebranchid = $res[0]->branch_id; 
                                                            $pubresp = $this->modelsManager->executeQuery("
                                                                    SELECT g.git_url AS git_url, g.git_id AS git_id, g.git_username AS github_user, g.github_token AS github_token FROM 
                                                                    git g JOIN gitBranch gb ON g.git_id=gb.git_id WHERE id = :branchid:",
                                                                    array( 'branchid' => $privatebranchid));
                                                            $public_git_url = trim($pubresp[0]->git_url);
                                                            $public_git_user = trim($pubresp[0]->github_user);
                                                            $public_git_token = trim($pubresp[0]->github_token);
                                                            error_log("PublicGitID: ".$git_id);
                                                            error_log("PublicGitURL: ".trim($public_git_url));
                                                            error_log("PublicGitUser: ".$public_git_user);
                                                            error_log("PublicGitToken: ".$public_git_token);
                                                        }
                                                }
						if (($res = $this->modelsManager->executeQuery("
                                                                
                                                        SELECT es.externalip AS serverip, se.serverid AS serverid, gs.sitesid, g.git_id,gb.id AS branch_id,
                                                        g.git_username,g.github_token, g.repo_name,g.repo_path AS repopath,g.repo_access_type,gb.connected_to AS connectedbranch, g.git_url AS git_url
                                                        FROM server es
                                                        JOIN siteenvironment se ON es.serverid = se.serverid
                                                        JOIN xrefGitSites gs ON se.siteid = gs.sitesid
                                                        JOIN git g ON gs.gitid = g.git_id
                                                        JOIN gitBranch gb ON g.git_id=gb.git_id
                                                        WHERE gb.branch_name = :branch_name: AND g.git_id = :git_id:
							", array(
						   		'branch_name' =>  $branch_name,
						   		'git_id' => $git_id,
							))) && isset($res[0]) &&
			    
							($git_id = &$res[0]->git_id) &&
							($git_branch_id = &$res[0]->branch_id)
						) {
                                                        error_log("RepoAccessType : ".$res[0]->repo_access_type);
							if ($res[0]->repo_access_type == 'private') {
							    // our private repo

error_log("repoWebhook, Private repo..");

							    if (preg_match('/^Auto-merged (\w+) into (\w+) on deployment.$/',
							    		$payload->head_commit->message,$match) &&

							    	($repo_token = trim($res[0]->github_token)) &&
									($repo_owner = trim($res[0]->git_username)) &&
									($repo_name = trim($res[0]->repo_name)) &&

									($client = new \Github\Client(
										new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
									))
							    ) {
error_log("repoWebhook, Auto-merged push..doing deployment again..");
							    	// a new deployment returned a merge commit, so doing deployment again

									$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

									$deploy = $client->api('deployment')->create(
										$repo_owner, $repo_name, 
										array('ref' => $branch_name,'environment' => $env,'auto_merge' => true)
									);
error_log("repoWebhook, deploy=".var_export($deploy,true));

							    } else {
                                                                        $serverip = $res[0]->serverip;
							    	        $repo_token = trim($res[0]->github_token);
									$repo_owner = trim($res[0]->git_username);
									$repo_name = trim($res[0]->repo_name);
									$repopath = trim($res[0]->repopath);
						   		        $git_url = trim($res[0]->git_url);
error_log("repoWebhook, Pulling origin..");
	                                
                                                                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                                                                        $getListenerRes = $clientlistener->request('GET','/Listener.php',
                                                                         ['query' => ['git_url' => $git_url, 'token' => $repo_token, 'gituser' => $repo_owner, 'repopath' => $repopath, 'gitaction' => 'pull',  'listeneraction' => 'repoWebhook', 'branchname' => $branch_name]])->getBody()->getContents();
                                                                        error_log(json_encode($getListenerRes));
							        
									$this->response = array(
										"Status"		=> "Success",
	                                	"MsgCode"		=> "100",
	                                	"MsgDescription"=> "Operation done successfully",
		                            	"Data"			=> $getListenerRes,
									);
							        
                            	}

							} else {
			    
							    // original repo push, doing a merge into the master branch

error_log("repoWebhook, Original repo..");
                
								if (($github_token = trim($res[0]->github_token)) &&
									($git_username = trim($res[0]->git_username)) &&
									($repo_name = trim($res[0]->repo_name)) &&
			    
									($resg = $this->modelsManager->executeQuery("
										SELECT g.git_url AS git_url, g.git_id,gb.id AS branch_id,g.git_username,
                                                                                g.github_token,	g.repo_name,g.repo_path,gb.branch_name
										FROM Git g
											JOIN gitBranch gb ON g.git_id=gb.git_id
										WHERE gb.connected_to = :git_branch_id:
									", array(
								   		'git_branch_id' =>  $git_branch_id,
									))) && isset($resg[0]) &&

									($branch_name = &$resg[0]->branch_name) &&
			    
									($upstream_github_token = trim($resg[0]->github_token)) &&
									($upstream_git_username = trim($resg[0]->git_username)) &&
									($upstream_repo_name = trim($resg[0]->repo_name))
								) {
error_log("repoWebhook, Merging upstream.."); 
                                                                        $serverip = $res[0]->serverip;
							    	        $repo_token = trim($resg[0]->github_token);
									$repo_owner = trim($resg[0]->git_username);
									$repo_name = trim($resg[0]->repo_name);
									$repopath = trim($res[0]->repopath);
						   		        $git_url = trim($resg[0]->git_url);
                                                                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                                                                        $getListenerRes = $clientlistener->request('GET','/Listener.php',
                                                                         ['query' => ['git_url' => $git_url, 'token' => $repo_token, 'gituser' => $repo_owner, 'repopath' => $repopath, 'gitaction' => 'push',  'listeneraction' => 'repoWebhook' , 'branchname' => $branch_name]])->getBody()->getContents();
                                                                        error_log(json_encode($getListenerRes));
								}
							}
						}
						break;
			    
					case 'deployment':
                                                error_log("Deployment Phase!");
						if (property_exists($payload,'deployment') &&
							property_exists($payload->deployment,'environment') &&
							($payload->deployment->environment <> 'master') &&
							property_exists($payload->deployment,'repository_url') &&
							property_exists($payload->deployment,'id') &&
							property_exists($payload->deployment,'creator') &&
							property_exists($payload->deployment->creator,'login') &&
	                
							$this->repo_owner && $this->repo_name &&
							($client = new \Github\Client(
								new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
						) {
							$client->authenticate($this->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			    
//							Create 'pending' deployment status
							$deployStatus = $client->api('deployment')->updateStatus(
								$this->repo_owner, $this->repo_name, $payload->deployment->id,
								array('state' => 'pending', 'description' => 'Started a new deployment processing..')
							);
			    
							$output = array(); 
                                                        $serverip = $res[0]->serverip;
						        $repo_token = trim($resg[0]->github_token);
							$repo_owner = trim($resg[0]->git_username);
							$repo_name = trim($resg[0]->repo_name);
							$repopath = trim($res[0]->repopath);
						        $git_url = trim($resg[0]->git_url);
                                                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                                                        $getListenerRes = $clientlistener->request('GET','/Listener.php',
                                                         ['query' => ['git_url' => $git_url, 'token' => $repo_token, 'gituser' => $repo_owner, 'repopath' => $repopath, 'gitaction' => 'merge',  'listeneraction' => 'repoWebhook' , 'branchname' => $branch_name]])->getBody()->getContents();
                                                        error_log(json_encode($getListenerRes));
			                                $response = json_decode(json_encode($getListenerRes)); 
							if ($response->{'status'} == 'success') {
								
//								Create 'success' deployment status
								$deployStatus = $client->api('deployment')->updateStatus(
									$this->repo_owner, $this->repo_name, $payload->deployment->id,
									array('state' => 'success', 'description' => 'Deploy successfully finished..')
								);
			    
								$this->response = array(
							    	"Status"		=> "Success",
	    		            		"MsgCode"		=> "100",
	            	            	"MsgDescription"=> "Operation done successfully",
	                	        	"Data"			=> $output,
							    );
							}
						}
						break;
			    
					case 'deployment_status':
						if (property_exists($payload,'deployment') &&
							property_exists($payload->deployment,'environment') &&
							($payload->deployment->environment <> 'master') &&
							property_exists($payload->deployment,'repository_url') &&
							property_exists($payload->deployment,'id') &&
							property_exists($payload->deployment,'creator') &&
							property_exists($payload->deployment->creator,'login') &&
	                
							property_exists($payload,'deployment_status') &&
							property_exists($payload->deployment_status,'state') &&
							($payload->deployment_status->state == 'success') &&
			    
							$this->repo_owner && $this->repo_name &&
							($client = new \Github\Client(
								new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
						) {
                                                        $serverip = $res[0]->serverip;
						        $repo_token = trim($resg[0]->github_token);
							$repo_owner = $this->repo_owner;
							$repo_name = $this->repo_name;
							$repopath = trim($res[0]->repopath);
						        $git_url = trim($resg[0]->git_url);
                                                        $clientlistener = new GuzzleHttp\Client(['base_uri' => 'http://'.$serverip]);
                                                        $getListenerRes = $clientlistener->request('GET','/Listener.php',
                                                         ['query' => ['git_url' => $git_url, 'token' => $repo_token, 'gituser' => $repo_owner, 'repopath' => $repopath, 'gitaction' => 'pull',  'listeneraction' => 'repoWebhook' , 'branchname' => $payload->deployment->environment]])->getBody()->getContents();
                                                        error_log(json_encode($getListenerRes));
			                                $response = json_decode(json_encode($getListenerRes)); 
							$this->response = array(
								"Status"		=> "Success",
	    		            	"MsgCode"		=> "100",
	            	        	"MsgDescription"=> "Operation done successfully",
	                	    	"Data"			=> $response,
							);
						}
						break;
				}
			}
		}
                error_log(json_encode(error_get_last()));
                error_log(json_encode($this->response));
		return $this->response;
	}

	public function makeDeploy($company_id,$site_id,$env) {

		$this->response = array(
	    	"Status"		=> "Unknown",
	    	"MsgCode"		=> "",
	    	"MsgDescription"=> "",
	    	"Data"			=> array(),
	    );

	    try {

			if (($env == 'test' || $env == 'live') &&
				($base = (($env == 'test') ? 'dev' : 'test')) &&
				($head = &$env) &&
						    
				($res = $this->modelsManager->executeQuery("
					SELECT g.git_username AS repo_owner,g.repo_name,g.github_token AS repo_token,
						LOWER(e.environmentname) AS env,gb.branch_name
					FROM sites s
					    LEFT JOIN company c ON s.companyid=c.backendcompanyid
						LEFT JOIN xrefSiteEnvironment se ON se.siteid=s.siteid
						LEFT JOIN xrefGitSites gs ON se.gitid=gs.gitid AND se.siteid=gs.sitesid
						LEFT JOIN environment e ON e.environmentid=se.environmentid
						LEFT JOIN git g ON se.gitid=g.git_id
						JOIN gitBranch gb ON g.git_id=gb.git_id AND gb.id = se.git_branch_id
					WHERE c.frontendcompanyid = :company_id: AND s.siteid = :site_id: AND 
						LOWER(e.environmentname) IN (:base:, :head:)
				", array(
		   	   		'company_id'=> $company_id,
		   	   		'site_id'	=> $site_id,
		   	   		'base'	    => $base,
		   	   		'head'	    => $head,
				))) &&
		    
				($client = new \Github\Client(
					new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
			) {
	            $base_branch = $head_branch = '';

				foreach ($res as $_rec) {
					if ($_rec->env == $base) {
						$base_branch = trim($_rec->branch_name);
					}
					if ($_rec->env == $head) {
						$head_branch = trim($_rec->branch_name);

						$repo_owner  = trim($_rec->repo_owner);
						$repo_name   = trim($_rec->repo_name);
						$repo_token  = trim($_rec->repo_token);
					}
				}

	            try {
					$client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);

					if ($base_branch && $head_branch && 
						is_array($deploy = $client->api('deployment')->create(
							$repo_owner, $repo_name, 
							array('ref' => $head_branch,'environment' => $head,'auto_merge' => true)
						))
					) {
error_log("deploy=".var_export($deploy,true));
						$this->response = array(
			            	"Status"		=> "Success",
	        		    	"MsgCode"		=> "100",
	                    	"MsgDescription"=> "Operation done successfully",
	                		"Data"			=> $deploy,
			            );
	        		}
				} catch (\Github\Exception\ErrorException $e) {
					$this->response = array(
	                	"Status"		=> "Error",
	                	"MsgCode"		=> "-1",
	                	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	                );
				} catch (\Github\Exception\RuntimeException $e) {
					$this->response = array(
	                	"Status"		=> "Failure",
	                	"MsgCode"		=> "-1",
	                	"MsgDescription"=> "Failed, reason: ". $e->getMessage(),
	                );
				}
			}

		} catch (Phalcon\Mvc\Model\Exception $e) {
			$this->response['Status']	= 'Error';
			$this->response['MsgCode']	= '0';
			$this->response['MsgDescription']	= "Failed, reason: ". $e->getMessage();
		}

		header('Content-Type: application/json');
		echo json_encode($this->response);
	}

/*
	public function deploys($company_id,$site_id,$env_id) {

		if ($this->request->isGet() && $company_id && $site_id && $env_id &&
			($res = $this->modelsManager->executeQuery("
				SELECT DISTINCT g.git_username AS repo_owner,g.repo_name,g.git_url,g.git_ssh,g.github_token AS repo_token
				FROM epm_sites s
					LEFT JOIN epm_xref_site_environment se ON se.siteid=s.siteid
					LEFT JOIN epm_xref_git_sites gs ON se.gitid = gs.gitid AND se.siteid = gs.sitesid
					LEFT JOIN epm_git g ON se.gitid=g.git_id
				WHERE s.companyid = :company_id: AND s.siteid = :site_id: AND 
					se.environmentid = :env_id:
			", array(
		   		'company_id' => $company_id,
		   		'site_id' => $site_id,
		   		'env_id' => $env_id,
			)))
		) {
		}

		if ($res[0]->repo_owner && $res[0]->repo_name && $res[0]->repo_token &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($res[0]->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			$_params = array('environment' => $env_id);

			if ($deployBranch = $this->request->get("branch", "string")) {
				$_params['ref'] = $deployBranch;
			} elseif ($deployTag = $this->request->get("tag", "string")) {
				$_params['ref'] = $deployTag;
			} elseif ($deployCommit = $this->request->get("sha", "string")) {
				$_params['ref'] = $deployCommit;
			}

			if (is_array($deploys = $client->api('deployment')->all($this->repo_owner, $this->repo_name, $_params))) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $deploys,
	            );
	    	}
		}

		return $this->response;
	}
*/
/*
	public function client_AddSourceGitRepoHook() {
	}

	public function client_makeDeployToTest() {

		if ($this->request->isPost() &&
			($gse_id = $this->request->getPost('git_site_env_id')) &&
			($res = $this->modelsManager->executeQuery("
				SELECT g.git_url,g.github_token,
				FROM epm_git_site_envs gse
					JOIN epm_xref_git_sites gs ON gse.gitid = gs.gitid AND gse.sitesid = gs.sitesid
					JOIN epm_xref_site_environment se ON gse.sitesid = se.sitesid AND gse.environmentid = se.environmentid
					LEFT JOIN epm_git g ON gse.gitid=g.git_id
				WHERE gse_id.xref_id = :gse_id: 
				ORDER BY c.name
			", array(
		   		'name' => ""
			))
		) {
			foreach ($res as $row) {
				echo "Taxes: ", $row->taxes, "\n";
			}
		}
	}
*/
}

/*
	public function deploys($company_id,$site_id,$env_id) {

		if ($this->request->isGet() && $company_id && $site_id && $env_id &&
			($res = $this->modelsManager->executeQuery("
				SELECT DISTINCT g.git_username AS repo_owner,g.repo_name,g.git_url,g.git_ssh,g.github_token AS repo_token
				FROM epm_sites s
					LEFT JOIN epm_xref_site_environment se ON se.siteid=s.siteid
					LEFT JOIN epm_xref_git_sites gs ON se.gitid = gs.gitid AND se.siteid = gs.sitesid
					LEFT JOIN epm_git g ON se.gitid=g.git_id
				WHERE s.companyid = :company_id: AND s.siteid = :site_id: AND 
					se.environmentid = :env_id:
			", array(
		   		'company_id' => $company_id,
		   		'site_id' => $site_id,
		   		'env_id' => $env_id,
			)))
		) {
		}

		if ($res[0]->repo_owner && $res[0]->repo_name && $res[0]->repo_token &&
			($client = new \Github\Client(
				new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))))
		) {
			$client->authenticate($res[0]->repo_token,Github\Client::AUTH_HTTP_TOKEN);
			$_params = array('environment' => $env_id);

			if ($deployBranch = $this->request->get("branch", "string")) {
				$_params['ref'] = $deployBranch;
			} elseif ($deployTag = $this->request->get("tag", "string")) {
				$_params['ref'] = $deployTag;
			} elseif ($deployCommit = $this->request->get("sha", "string")) {
				$_params['ref'] = $deployCommit;
			}

			if (is_array($deploys = $client->api('deployment')->all($this->repo_owner, $this->repo_name, $_params))) {
				$this->response = array(
	            	"Status"		=> "Success",
	            	"MsgCode"		=> "100",
	            	"MsgDescription"=> "Operation done successfully",
	            	"Data"		=> $deploys,
	            );
	    	}
		}

		return $this->response;
	}
*/
/*
	public function client_AddSourceGitRepoHook() {
	}

	public function client_makeDeployToTest() {

		if ($this->request->isPost() &&
			($gse_id = $this->request->getPost('git_site_env_id')) &&
			($res = $this->modelsManager->executeQuery("
				SELECT g.git_url,g.github_token,
				FROM epm_git_site_envs gse
					JOIN epm_xref_git_sites gs ON gse.gitid = gs.gitid AND gse.sitesid = gs.sitesid
					JOIN epm_xref_site_environment se ON gse.sitesid = se.sitesid AND gse.environmentid = se.environmentid
					LEFT JOIN epm_git g ON gse.gitid=g.git_id
				WHERE gse_id.xref_id = :gse_id: 
				ORDER BY c.name
			", array(
		   		'name' => ""
			))
		) {
			foreach ($res as $row) {
				echo "Taxes: ", $row->taxes, "\n";
			}
		}
	}
*/
