<?php
require_once 'php-github-api-master/vendor/autoload.php';
use Phalcon\Mvc\Controller;
use Phalcon\DI\Injectable;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class DockerController extends Phalcon\DI\Injectable {

    private $app;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->app->response->setContentType('application/json', 'utf-8');
    }
	
    public function create()
    {
        try {
            // Get input to the script 
            $apikey 				= $this->app->request->get("apikey");
            $sitename 				= $this->app->request->get("sitename");
            $frontendcompanyid 		= $this->app->request->get("companyid");
            $siteurl 				= $this->app->request->get("siteurl");
            $git_url 				= $this->app->request->get("giturl");
            $environmentname 		= $this->app->request->get("environment");
            $InstallType 			= $this->app->request->get("InstallType");
            $drupal_distribution 	= $this->app->request->get("Distribution"); 
            $clienttoken 			= $this->app->request->get("clienttoken");
            $gituser 				= $this->app->request->get("gituser");
            $repo_name 				= $this->app->request->get("repo_name");
            $branch_name 			= $this->app->request->get("branch_name");
			$doc_root 				= '/var/www/html';

            if (!isset($apikey) || empty($apikey)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'API Key not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($InstallType) || empty($InstallType)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Install type not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($siteurl) || empty($siteurl)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site url not Posted or is Empty!'));
                return $this->app->response;
            }

            # Check Site Url
            $objsiteenv = new siteenvironment();
            $checksiteurl = $objsiteenv->checkSiteUrl($siteurl);
            if (!is_null($checksiteurl)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site Url Already Exist!'));
                return $this->app->response;
            }

            // Process and Validate Git URL
            if ($InstallType == 'GIT' || $InstallType == 'GITHUB') {				
                $git_url = $this->validateGitUrl($git_url, $gituser, $clienttoken);
                if (is_null($git_url)) {
                    $this->app->response
                            ->setStatusCode(404, "Not Found")
                            ->setJsonContent(array('status' => 'ERROR', 'data' => 'Git Repository does not exist.'));
                    return $this->app->response;
                }

                $gitSHA = $this->checkGitRepoEmpty($gituser, $repo_name, $clienttoken, mb_strtolower($environmentname));
                if ($gitSHA == False || empty($gitSHA)) {
                    $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Git Repository is Empty.'));
                    return $this->app->response;
                }

				//Get Input to the script for Git Repository create.
				$gitUserName = $gituser;
				$git_token = $clienttoken;
				$RepoGitUrl = $git_url;
				$Description = "Initial checkin while creating site!";
            }
			else if ($InstallType == 'FRESH' && $drupal_distribution != 'null'){
				$objdist = new distributions();
				$distributionurl = $objdist->getDistributionurl($drupal_distribution);
				if (is_null($distributionurl)) {
					$this->app->response
					   ->setStatusCode(404, "Not Found")
					   ->setJsonContent(array('status' => 'ERROR', 'data' => 'Drupl distribution not found!'));
                    return $this->app->response;
				}
			}
			else {
				$this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Installation type is not defined properly.'));
				return $this->app->response;
			}

            if (!isset($sitename) || empty($sitename)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Sitename not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($frontendcompanyid) || empty($frontendcompanyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company Id not Posted or is Empty!'));
                return $this->app->response;
            }

            if (!isset($environmentname) || empty($environmentname)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environement Name not Posted or is Empty!'));
                return $this->app->response;
            }

            # Get Backend Company ID
            $objcompany = new company();
            $backendcompanyid = $objcompany->getBackendCompanyIdbyFrontend($frontendcompanyid);
            if (is_null($backendcompanyid)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
                return $this->app->response;
            }

            # Match the company id within the DB backendcompanyid from company id
            $companyname = $objcompany->getBackendCompanyName($backendcompanyid);
            if (is_null($companyname)) {
                 $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Company does not exist!'));
                return $this->app->response;
            }

            # Get environmentid
            $objenv = new environment();
            $environmentid = $objenv->getEnvironmentId($environmentname, 'active');
            if (is_null($environmentid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Environment does not exist!'));
                return $this->app->response;
            }

            # Get serverips 
            $objserv = new server();
            $serverips = $objserv->getAllServerIP($backendcompanyid, $environmentid);
            if (is_null($serverips)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Server does not exist!'));
                return $this->app->response;
            }

            # Get serverid
            $serverid = $objserv->getServerID($backendcompanyid, $environmentid);
            if (is_null($serverid)){
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Primary server does not exist!'));
                return $this->app->response;
            }
            $primaryserverid = $serverid;

            $phql = "SELECT A1.db_external_hostname AS dbhost FROM databaseserver A1 JOIN server A2 on A1.serverid = A2.serverid JOIN xrefServerCompany A3 ON A2.serverid = A3.serverid WHERE A2.serverfunction = 'dbserver' AND A1.status = 'active' AND A2.serverstatus = 'active' and A3.backendcompanyid = $backendcompanyid AND A2.environmentid = $environmentid";
            
			$resdb = $this->modelsManager->executeQuery($phql);
            $dbserverips = [];
            $arrayIndexDBIP = 0;
            foreach($resdb as $res) {
                $dbserverips[$arrayIndexDBIP] = trim($res->dbhost);
                $arrayIndexDBIP += 1;
            }

            if (is_null($dbserverips)) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Database Server does not exist!'));
                return $this->app->response;
            }

            # Insert into epm_sites table
            $objsite = new sites();
            $siteid = $objsite->setSites($backendcompanyid, $sitename, 'Creating');
            if ($siteid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'Success','message' => 'Site being created', 'siteid' => $siteid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site already exists!'));
                return $this->app->response;
            }

            # Subsite database entry in epm_database.
            $database_name = substr(md5($sitename."_".$environmentname."_".$siteid), 0, 12); 
            $dbtype = 'Mysql';
            $dbversion = '5.5.47';
            $dbhostname = '';
            $status = 'active';
            $external_hostname = '';
            $primary_db_id = '1';
            $isprimary = 'TRUE';
            $serverfunction = 'dbserver';

            # Get DB connection information.
            if (($res = $this->modelsManager->executeQuery("

                SELECT A1.id AS database_server_id, A2.serverid AS dbid, A1.db_external_hostname AS dbhost, A1.db_port AS dbport, 
                A1.db_server_username, A1.db_server_password  
                FROM databaseserver A1
                JOIN server A2 on A1.serverid = A2.serverid
                JOIN xrefServerCompany A3 ON A2.serverid = A3.serverid 
                WHERE A1.is_primary_db_server = 1 AND A1.status = 'active' AND A2.serverstatus = 'active' and 
                A3.backendcompanyid = :backendcompanyid: AND A2.environmentid = :environmentid:
                LIMIT 1 ", array(
                'backendcompanyid'=> $backendcompanyid,
                'environmentid'=> $environmentid,))) && isset($res[0]) 
                && ($dbhost = &trim($res[0]->dbhost)) &&
                ($db_server_username = &trim($res[0]->db_server_username)) &&
                ($db_server_password = &trim($res[0]->db_server_password)) &&
                ($database_server_id = &trim($res[0]->database_server_id)) &&
                ($dbport = &trim($res[0]->dbport))) {
                   error_log($backendcompanyid.','.$environmentid.','.$dbhost.','.$db_server_username.','.$db_server_password.','.$dbport.','.$primaryserverid);
            }

			$con = mysql_connect($dbhost, $db_server_username, $db_server_password) or die("Could not connect: " . mysql_error());
			$result = mysql_query("CREATE DATABASE ".$database_name);
			$dbAccess = $this->CreateDBUser($sitename, $database_name, $dbhost, $db_server_username, $db_server_password, $dbport);
			if($dbAccess['Status'] != "Success"){
				$this->app->response
					->setStatusCode(409, "Conflict")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Unable to create DB users!'));
                return $this->app->response;
			}
			$dbusername = $dbAccess["Response"]["dbuser"];
			$dbpassword = $dbAccess["Response"]["dbpassword"];
            $status = 'active'; 
            $database = new database();
            $databaseid = $database->setDatabase($database_name, $dbusername, $dbpassword, $dbhost, $dbport, $dbtype, $dbversion, $dbhostname, $environmentid, $isprimary, $status, $external_hostname, $primary_db_id, $database_server_id);
			
			$DBDetails				= array();
			$DBDetails["host"] 		= $dbhost;
			$DBDetails["username"] 	= $db_server_username;
			$DBDetails["password"] 	= $db_server_password;
			$DBDetails["database"] 	= $database_name;
			$DBDetails["port"] 		= $dbport;
			
			$NewSite = trim($sitename)."_".trim($backendcompanyid)."_".trim($environmentid)."_".trim($siteid);
            #INSERT INTO epm_xref_site_environment
            $drupalfolderpath = '/var/www/html/'.$NewSite;
            $drup_dbname = $database_name;
            $git_branch_id = '1';
            $database_uses_global_password = 'TRUE';
            $database_id = $databaseid;
            $gitid = '1';
            $sitedomainname = $siteurl;
            $username = ''; 
            $password = '';
            $site_status = '';
            $objsiteenv = new siteenvironment();
            $xrefid = $objsiteenv->setSite(trim($siteid), trim($environmentid), trim($drupalfolderpath), trim($serverid), trim($sitedomainname), trim($drup_dbname), trim($dbhost), trim($username), trim($password), trim($git_branch_id), trim($database_uses_global_password), trim($database_id), trim($gitid), trim($site_status));
            if ($xrefid) {
                $this->app->response
                        ->setStatusCode(201, "Created")
                        ->setJsonContent(array('status' => 'Success', 'message' => 'Site environment reference created', 'xrefid' => $xrefid));
            } else {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Site environment reference already exists!'));
                return $this->app->response;
            }

            $repopath =  $_SERVER['DOCUMENT_ROOT'];
			
            $client = new GuzzleHttp\Client(['base_uri' => 'http://api.stratosys.tech']); 
            $gitresponse = $client->request('POST','/phalcon-rest-v1/integrategit/', ['query' => ['repopath' => $drupalfolderpath, 'companyid' => $backendcompanyid, 'clientgit' => $git_url, 'siteid' => $siteid, 'clienttoken' => $clienttoken, 'clientuser' => $gituser, 'sitenameforgit' => $sitename, 'repo_name' => $repo_name, 'environmentname' => strtolower($environmentname), 'branchsuffix' => $branch_name]])->getBody()->getContents();

			$gitdecode = json_decode($gitresponse);
            $gitencode = json_encode($gitdecode);
            $gitdecodedata = json_decode($gitencode);
            if ($gitdecodedata->Status != 'Success') {
                $this->app->response
                        ->setStatusCode(409, "Conflict")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Failed, reason: Git operations has not successfully done.'));
                return $this->app->response;
            } else {
                $jsongitdata = $gitdecodedata->Response;
                $ClientPublicRepo = $jsongitdata->ClientPublicRepo;
                $PrivateRepoDirectory = $jsongitdata->PrivateRepoDirectory;
                $PrivateRepoGitUrl = $jsongitdata->PrivateRepoGitUrl;
                $PrivateClientBranch = $jsongitdata->PrivateClientBranch;
            }

			//echo $PrivateRepoGitUrl;
			//die;
			
			$currentDirectory = exec('pwd');	// results in /var/www/html/phalcon-rest-v1
			chdir($doc_root);
			
			if ($InstallType == 'FRESH' && $drupal_distribution != 'null'){
				exec('git clone -b '.$PrivateClientBranch.' '.$PrivateRepoGitUrl);
				chdir($doc_root.'/'.$PrivateRepoDirectory);
				exec('wget ' .$distributionurl);
				$innerDir = exec('ls');
				exec('unzip ' .$innerDir);
				exec('rm *.zip');
				$innerDir = exec('ls');
				exec('cp -r '.$innerDir.'/. .');
				exec('rm -rf '.$innerDir);
				if (!file_exists(".htaccess")) exec('cp '.$doc_root.'/source_files/.htaccess .');
				
				exec('git config user.name "stratosys"');
				exec('git config user.email "ashish@logbullit.com"');
				exec('git add *');
				exec('git commit -m "Uploading drupal source code" ');
				exec('git remote add origin '.$PrivateRepoGitUrl);
				exec('git remote -v');
				exec('git push -u origin '.$PrivateClientBranch);
				//chdir($doc_root);
			} 
			else {
				exec('git clone '.$ClientPublicRepo);
				$innerDir = exec('ls');
				exec('cp -r '.$innerDir.'/. .');
				exec('rm -rf '.$innerDir);
				chdir ($doc_root.'/'.$NewSite);
				if (!file_exists(".htaccess")) exec('cp '.$doc_root.'/source_files/.htaccess .');

				exec('git init');
				exec('git config user.name "stratosys"');
				exec('git config user.email "ashish@logbullit.com"');
				exec('git add *');
				exec('git commit -m "First commit while creating private repo" ');
				exec('git remote rm origin');
				exec('git remote add origin '.$PrivateRepoGitUrl);
				exec('git remote -v');
				exec('git pull origin '.$PrivateClientBranch);
				exec('git push -u origin '.$PrivateClientBranch);
			}

			if ($InstallType == 'FRESH' && $drupal_distribution != 'null'){
				$DBFile = $doc_root."/drupal.sql";
			}
			else {
				$newsitepath = $doc_root."/".$PrivateRepoDirectory;
				$fileList = $this->searchSQLFiles($newsitepath);
				if($fileList == "False") {
					$DBFile = $doc_root."/drupal.sql";
				}
				else {
					$searchdir = searchDirectory($newsitepath);
					$data = json_decode($searchdir);
					$sitesdirpath = $data->sitesdirpath;
					if (is_null($sitesdirpath) || empty($sitesdirpath)) {
						$this->app->response
								->setStatusCode(409, "Conflict")
								->setJsonContent(array('status' => 'ERROR', 'data' => 'Failed, reason: sites directory not present in client git repo.'));
						return $this->app->response;
					}
					
					$sitesphppath = $data->sitesphppath;
					$settingsphppath = $data->settingsphppath;
					$sitesphpflag = $data->sitesphpflag;
					$settingphpflag = $data->settingphpflag;
					
					if ($settingphpflag == 'True') {
						$DBFile = $this->GetDBFileName($settingsphppath, $fileList);
					}
					else {
						$DBFile = $fileList[0];
					}
				}
			}
			
			if($DBFile == ""){
				$DBFile = $doc_root."/drupal.sql";
			}
			$restoredb =<<<EOF
				mysql -u '$db_server_username' -p'$db_server_password' -h '$dbhost'  '$database_name'< '$DBFile'
EOF;
			exec($restoredb);
			mysql_close($con);

			getcwd();
			exec('cp '.$doc_root.'/source_files/example.sites.php sites/sites.php');
			exec('cp '.$doc_root.'/source_files/default.settings.php sites/default/settings.php');

			$SitesFilePath = $doc_root.'/'.$PrivateRepoDirectory.'/sites/sites.php';
			$SettingsFilePath = $doc_root.'/'.$PrivateRepoDirectory.'/sites/default/settings.php';

			$updateSitesphp = $this->updateSitesFile($siteurl, $SitesFilePath);
			if (!$updateSitesphp) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Failure! Unable to update sites.php file.'));
                return $this->app->response;
			}
			
			// Update settings.php file
			$prefix = '';
			$updateSettingsphp = $this->updateSettingsFile($drup_dbname, $dbusername, $dbpassword, $dbhost, $SettingsFilePath, $prefix);
			if (!$updateSettingsphp) {
                $this->app->response
                        ->setStatusCode(404, "Not Found")
                        ->setJsonContent(array('status' => 'ERROR', 'data' => 'Failure! Unable to update settings.php file.'));
				return $this->response;
			}


			$privateGitFolder = $siteid."-".strtolower($environmentname);
			$gitdir = $doc_root."/strato-site-priv-priv/".$privateGitFolder;
			exec("sudo mkdir ".$gitdir);
			exec('sudo cp sites/sites.php '.$gitdir.'/sites.php');
			exec('sudo cp sites/default/settings.php '.$gitdir.'/settings.php');
			chdir($doc_root."/strato-site-priv-priv");

			$PrivatePrivateRepoGitUrl = "https://stratosys:014afd3d6ac6caee5fc8454556898591f476ea16@github.com/stratosys/strato-site-priv-priv.git";
			//exec('git config user.name "superdevelopergit"');
			//exec('git config user.email "ashishupworkuser@gmail.com"');
			//exec('sudo git remote add origin '.$PrivatePrivateRepoGitUrl);
			exec('sudo git pull origin master');
			exec('sudo git add *');
			exec('sudo git commit -m "Uploading sites/settings files" ');
			//exec('sudo git remote add origin '.$PrivatePrivateRepoGitUrl);
			exec('sudo git remote -v');
			exec('sudo git push -u origin master');

			// Fetching AWS access details from DB
			$objAWS = new AWSAccessDetails();
			$S3BucketDetails = $objAWS->getAWSAccessDetailsByAppUse("gocdcreatesiteapi");
			if (is_null($S3BucketDetails)) {
				error_log("Unable to fetch AWS info from DB!");
			}
			// Instantiate the S3 client with your AWS credentials
			$s3Client = S3Client::factory(array(
				'credentials' => array(
					'key'    => $S3BucketDetails['awsAccessKey'],
					'secret' => $S3BucketDetails['awsSecretKey']
				),
				'region'  => 'us-east-1',
				'version' => 'latest'
			));
			$PublicFileBucket = "";
			$PrivateFileBucket = "";
			
			chdir($doc_root."/".$PrivateRepoDirectory);	
			
			$file_public_path = $this->ReadVariableTable($DBDetails,'file_public_path');
			if($file_public_path == ""){
				$this->AddNewRecordInVariableTable($DBDetails,'file_public_path',serialize('sites/default/files'));
				$file_public_path = $this->ReadVariableTable($DBDetails,'file_public_path');
			}
			if(!is_dir($file_public_path)) {
				mkdir($file_public_path);
			}
			// to remove
			if($this->is_dir_empty($file_public_path)) {
				fopen($file_public_path."/empty_public_files.txt",'w');
			}
			
			$file_private_path 	= $this->ReadVariableTable($DBDetails,'file_private_path');
			if($file_private_path == ""){
				$this->AddNewRecordInVariableTable($DBDetails,'file_private_path',serialize('sites/default/private'));
				$file_private_path = $this->ReadVariableTable($DBDetails,'file_private_path');
			}
			if(!is_dir($file_private_path)) {
				mkdir($file_private_path);
			}
			// to remove
			if($this->is_dir_empty($file_private_path)) {
				fopen($file_private_path."/empty_private_files.txt",'w');
			}
			
			// Uploading public files on AWS
			if($file_public_path != "" && @is_dir($file_public_path) && !$this->is_dir_empty($file_public_path)){
				$PublicFileBucket = $this->CreateAWSBucket($s3Client,$siteid,$environmentname,"public");
				$s3Client->uploadDirectory($file_public_path,$PublicFileBucket);
			}
			// Uploading private files on AWS
			if($file_private_path !="" && is_dir($file_private_path) && !$this->is_dir_empty($file_private_path)){
				$PrivateFileBucket = $this->CreateAWSBucket($s3Client,$siteid,$environmentname,"private");
				$s3Client->uploadDirectory($file_private_path,$PrivateFileBucket);
			}
			
			if($PublicFileBucket != "" || $PrivateFileBucket != ""){
				$objsiteenv->updateSiteBucket($xrefid, $PublicFileBucket, $PrivateFileBucket);
			}
			if($privateGitFolder != ""){
				$objsiteenv->updateSitePrivateGitFolder($xrefid, $privateGitFolder);
			}
			
			chdir($doc_root);
			//exec('rm -rf '.$PrivateRepoDirectory);
			chdir($currentDirectory);

			if ($InstallType == 'FRESH' && $drupal_distribution != 'null'){

                // Loop for multiple server deployment.
                $ListenerRes = [];
                $arrayIndexLis = 0;
                foreach($serverips as $serverip)
                {
                    $server_id = $objserv->getServerIDByIP($serverip);
                    if($server_id == $primaryserverid) {
                        $primary_server = TRUE;
                    } else {
                        $primary_server = FALSE;
                    }
                    try {
						$pipelineObject = new pipeline();
						$pipelinename = $siteid."-".strtolower($environmentname);
						$pipelineid = $pipelineObject->setPipeline($pipelinename,$xrefid,"Creating");
						if(is_null($pipelineid)) {
							$this->app->response
								->setStatusCode(404, "Not Found")
								->setJsonContent(array('status' => 'ERROR', 'data' => 'Unable to create pipeline!'));
							return $this->app->response;
						}

						$dockername = $siteid."-".strtolower($environmentname)."1";
						$serverport = $this->getServerPort($server_id);
						$dockerObject = new docker();
						
						$dockerid = $dockerObject->setDocker($dockername, $pipelineid, $server_id, $serverport, "Creating");
						if(is_null($dockerid)) {
							$this->app->response
								->setStatusCode(404, "Not Found")
								->setJsonContent(array('status' => 'ERROR', 'data' => 'Unable to create docker!'));
							return $this->app->response;
						}

						$createPipeline = $this->createPipeline($siteid, $pipelinename, $PrivateRepoGitUrl, $PrivateClientBranch, $serverip, $serverport,$privateGitFolder, $S3BucketDetails, $file_public_path, $file_private_path, $PublicFileBucket, $PrivateFileBucket);
						if($createPipeline['Status'] != "Success"){
							$this->app->response
								->setStatusCode(404, "Not Found")
								->setJsonContent(array('status' => 'ERROR', 'data' => 'Failure! Unable to create pipline. Error: ' . $createPipeline['Msg']));
							return $this->app->response;
						}
						
						$unpausePipeline = $this->unpausePipeline($pipelinename);
						if($unpausePipeline['Status'] != "Success"){
							$this->app->response
								->setStatusCode(404, "Not Found")
								->setJsonContent(array('status' => 'ERROR', 'data' => 'Failure! Unable to unpause pipline. Error: ' . $unpausePipeline['Msg']));
							return $this->app->response;
						}

						$pipelineUpdated = $pipelineObject->updatePipelineStatus($pipelineid, "Completed");
						$dockerUpdated = $dockerObject->updateDockerStatus($dockerid, "Completed");
						if(is_null($pipelineUpdated) || is_null($dockerUpdated)) {
							$this->app->response
									->setStatusCode(201, "Success")
									->setJsonContent(array('status' => 'Alert', 'data' => 'Alert! Site created successfully but somthing is wrong when updating pipeline and docker tables'));
							return $this->app->response;
						}
						
						echo "Server IP : " . $serverip;
						echo "==============";
						echo "Server Port : " . $serverport;
						echo "==============";
						echo "Pipeline ID : " . $pipelineid;
						echo "==============";
						echo "Docker ID : " . $dockerid;
						echo "==============";
                    }
                    catch(Exception $e){
						return $this->app->response
							->setStatusCode(404, "Not Found")
							->setJsonContent(array('status' => 'ERROR', 'data' => 'Failure! Unable to create pipeline. Error: ' . $e->getMessage()));
                    }
                }
				
				echo "gitfoldername : " . $PrivateRepoGitUrl;
				echo "==============";
            }
			
			try {
				// Add secondry user to db
				$dbsecondryuser = $client->request('POST','/phalcon-rest-v1/site/addusertodb/',['query' => ['apikey'=> $apikey, 'environmentid' => $environmentid, 'siteid' => $siteid]])->getBody()->getContents();
				$getdbdecode = json_decode($dbsecondryuser);
				$getdbencode = json_encode($getdbdecode);
				$getdbdecodedata = json_decode($getdbencode);
				if (($getdbdecodedata->status == 'Failed')) {
					error_log("Secondary user for drupal db is not created!");
				}
			}
			catch(Exception $e){
					error_log("Secondary user for drupal db is not created. Error: " . $e->getMessage());
			}
			$update1 = $objsite->updateSites($backendcompanyid, $sitename, 'Completed', $siteid);
			$update2 = $objsiteenv->updateSiteStatus($xrefid, 'Completed');
			if(!$update1 || !$update2){
				error_log("Site created successfully but DB not updated!");
			}
        }
        catch(Exception $e) {
			return $this->app->response
				->setStatusCode(404, "Not Found")
				->setJsonContent(array('status' => 'ERROR', 'data' => 'Failure! Unable to create site. Error: ' . $e->getMessage()));
        } 
        return $this->app->response;
    }

    public function searchSQLFiles($newsitepath) {
        $fileObjects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($newsitepath), RecursiveIteratorIterator::SELF_FIRST);
        $arrayIndex = 0;
        $fileList = array();
        if ($fileObjects) {
            foreach($fileObjects as $key => $object){
                $filename = $object->getFilename();
                if ($filename != 'access_test.sql' && $filename != 'sql-2.sql') {
                    $file_ext = pathinfo($filename);
                    if ($file_ext['extension'] == 'sql') {
                        $path = $object->getPathname();
                        $fileList[$arrayIndex] = $path;
                        $arrayIndex += 1;
                    }
                }
            }
        }
        if ($arrayIndex > 0) {
            return $fileList;
        } else {
            return 'False';
        }
    }

    function searchDirectory($newsitepath) {
        $fileObjects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($newsitepath), RecursiveIteratorIterator::SELF_FIRST);
        // Search sites directory
        $sitesdirpath = '';
        if ($fileObjects) {
            foreach($fileObjects as $key => $object){
                $dirpath = $object->getPathname();
                if (basename($dirpath) == 'sites') {
                    $sitesdirpath = $dirpath;
                    break;
                }
            }
        }

        $dirObjects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sitesdirpath), RecursiveIteratorIterator::SELF_FIRST);
        // Search sites.php file
        $sitesphpflag = 'False';
        $sitesphppath = '';
        if ($dirObjects) {
            foreach($dirObjects as $key => $object){
                if ($object->getFilename() === 'sites.php') {
                    $sitesphppath = $object->getPathname();
                    $sitesphpflag = 'True';
                    break;
                }
            }
        }

        $dirObjects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sitesdirpath), RecursiveIteratorIterator::SELF_FIRST);
        // Search settings.php file
        $settingphpflag = 'False';
        $settingsphppath = '';
        if ($dirObjects) {
            foreach($dirObjects as $key => $object){
                if ($object->getFilename() === 'settings.php') {
                    $settingsphppath = $object->getPathname();
                    $settingphpflag = 'True';
                    break;
                }
            }
        }

        $data = array("sitesdirpath" => $sitesdirpath,
                  "sitesphppath"  => $sitesphppath,
                  "settingsphppath"  => $settingsphppath,
                  "sitesphpflag"  => $sitesphpflag,
                  "settingphpflag" => $settingphpflag
                );
        return json_encode($data);
    }
	
	public function GetDBFileName($settingsphppath, $fileList){
        $DBDetails = $this->ReadDBDetailsFromSettingsDotPHPFile($settingsphppath);
        $setting_dbname = $DBDetails["database"];
        $backupfile = '';
        $arrayIndexZero = 0;
        foreach($fileList as $file) {
            $filename = basename($file);
            $sqlname = explode('.', $filename);
            if ($sqlname[$arrayIndexZero] == $setting_dbname) {
                return $file;
            }
        }
	}

	public function ReadDBDetailsFromSettingsDotPHPFile($siteSettingsFilePath){
		$DBDetails = array();
		if($file = file($siteSettingsFilePath)){
			foreach( $file as $line ) {
				if($line[0] === "$"){					
					$DBTitle = strstr($line, '=', true);		
					$match = "$"."databases['default']['default']";											
					if(trim($DBTitle) == $match){
						$DBString = substr(strstr($line, '='), 1, -1);
						$DBArray = explode("(",$DBString);
						$DBArray = explode(")",$DBArray[1]);
						$DBArray = explode(",",$DBArray[0]);
						
						foreach($DBArray as $val){
							$DBElement = explode("=>",$val);
							$DBDetails[trim(trim($DBElement[0]),"'")] = trim(trim($DBElement[1]),"'");
						}
					}
				}
			}
		}
		return $DBDetails;
	}
	
	public function CreateAWSBucket($s3Client,$siteid,$environmentname,$suffix, $count=1){
		
		$bucketname = $siteid."-".strtolower($environmentname)."-".$suffix;
		if($count>1){
			$bucketname .= $count;	
		}
		$bucketList = $s3Client->listBuckets();
		foreach ($bucketList['Buckets'] as $bucket) {
			if ("{$bucket['Name']}" == $bucketname) {
				$count++;
				$this->CreateAWSBucket($s3Client,$siteid,$environmentname,$suffix,$count);
			}
		}
		$s3Client->createBucket(array('Bucket' => $bucketname));
		return $bucketname;
	}
	
    public function validateGitUrl($giturl, $gituser, $clienttoken) {
        $parsedURL = parse_url($giturl);
        $urldecode = json_decode(json_encode($parsedURL));
        $path = $urldecode->path;
        $branchflag = 'False';
        $addedgit = 'False';
        $chunks = explode('/', $path);
        foreach ($chunks as $i => $chunk) {
            if ($chunks[$i] == 'tree') {
                $branchflag = 'True';
            }
        }
        $arrayIndexOne = 1;
        $arrayIndexTwo = 2;
        $repoowner = $chunks[$arrayIndexOne];
        $reponame = $chunks[$arrayIndexTwo];
        $repocheck = explode('.', $chunks[$arrayIndexTwo]);
        if ($repocheck[$arrayIndexOne] == 'git') {
            $reponame = $repocheck[0];
            $addedgit = 'True';
        }
        if ($branchflag == 'False' && $i == 2) {
            if ($addedgit == 'False') {
                $NewGitUrl = $giturl . ".git";
            } else {
                $NewGitUrl = $giturl;
            }
        } 
        if ($branchflag == 'True') {
            $Newpath = "";
            if ($addedgit == 'False') {
                $withgit = $chunks[$arrayIndexTwo];
                $chunks[$arrayIndexTwo] = $withgit . ".git";
            }
            foreach ($chunks as $i => $chunk) {
                if ($i != 0) {
                    if ($i <= 2) {
                        $Newpath = $Newpath . "/" . $chunks[$i];
                    }
                }
            }
            $NewGitUrl = $urldecode->scheme . "://" . $urldecode->host . "" . $Newpath;
        }
        // Validate Git URL
        $client = new \Github\Client();
        $client->authenticate($gituser, $clienttoken);
        $repo = new Github\Api\Repo($client);
        $repoinfo = $repo->show($repoowner, $reponame);
        $myrepo_json = json_decode(json_encode($repoinfo), true);
        $clone_url = $myrepo_json["clone_url"];
        if ($NewGitUrl == $clone_url) {
            $GitUrl = $NewGitUrl;
            return $GitUrl;
        } else {
            return null;
        }
    }

    public function checkGitRepoEmpty($repo_owner, $repo_name, $repo_token, $env) {
        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache')));
        $client->authenticate($repo_token,Github\Client::AUTH_HTTP_TOKEN);
        try {
            $commits = $client->api('repo')->commits()->all(
                     trim($repo_owner), trim($repo_name),
                     array('sha' => ($env == 'dev' ? 'master' : $env)));
            $sha = [];
            $arrayIndexSHA = 0;
            foreach ($commits as $keys=>$objects) {
                foreach ($objects as $key => $object) {
                    if ($key == 'sha') {
                        $sha[$arrayIndexSHA] = $object;
                        $arrayIndexSHA += 1;
                    }
                }
            }
            return $sha;
        } catch (ErrorException $e) {
            return False;
        }
    }
	
	public function test(){

		chdir("/var/www/html/phalcon-rest-v1/app/controllers");
	
		$objAWS = new AWSAccessDetails();
		$S3BucketDetails = $objAWS->getAWSAccessDetailsByAppUse("gocdcreatesiteapi");
		$BucketName = "drup-db-exports2";
		$files = "files2";
		$AWSURI  = "files";
		
		
		// Instantiate the S3 client with your AWS credentials
		$s3Client = S3Client::factory(array(
			'credentials' => array(
				'key'    => $S3BucketDetails['awsAccessKey'],
				'secret' => $S3BucketDetails['awsSecretKey']
			),
			'region'  => 'us-east-1',
			'version' => 'latest'
		));

		$result = $s3Client->uploadDirectory($files,$BucketName);
		
		die();
		
		
		$siteid = "2497";
		$doc_root = "/var/www/html";
		$PrivateRepoDirectory = "70e574a12eeed0312f21f1bb175d15b5";
		$sitename = "mayank29julyc1s1";
		$environmentname = "Dev";
		$file_private_path = "sites/default/private";
		$BucketName = "drup-db-exports";
		$environmentid=1;

		chdir($doc_root.'/'.$PrivateRepoDirectory);
		$PrivateFilesZipName = trim($sitename).'_'.trim($environmentname).'_'.date("Y-m-d").'_'.date("h:i:s").'_'.date_default_timezone_get().'_private_files.zip';

		if(!$this->CreateZip($file_private_path,$PrivateFilesZipName)){
			return "Failed! ZIP can not be created.";
		}
		
		$objAWS = new AWSAccessDetails();
		$S3BucketDetails = $objAWS->getAWSAccessDetails();
		if (is_null($S3BucketDetails)) {
			$this->app->response
					->setStatusCode(404, "Not Found")
					->setJsonContent(array('status' => 'ERROR', 'data' => 'Unable to fetch AWS info from DB!'));
			return $this->app->response;
		}

		$AWSURI  = $siteid."/";
		$AWSURI .= $environmentname."/";
		$AWSURI .= "drup_file_archive/".$PrivateFilesZipName;
		
		$AWSLinkArray = $this->UploadFilesOnS3Bucket($S3BucketDetails,$BucketName,$PrivateFilesZipName, $AWSURI);
		if(!is_array($AWSLinkArray)){
			return $error = $AWSLinkArray;
		}
		
		$file_path = $AWSLinkArray["AWSLink"];
		
		$FileDetails = $this->GetAWSFileDetails($S3BucketDetails,$BucketName, $AWSURI);
		
		if(!is_array($FileDetails)){
			return $error = $FileDetails;
		}

		$uploaded_timestamp = trim($FileDetails["time"]);
		$filesize 			= trim($FileDetails["size"]);
		$subsiteid			= NULL;
		$objExport = new siteExportDetails();
		$exportID = $objExport->addRecord($siteid,$subsiteid,$environmentid,'private_files',$file_path,$uploaded_timestamp,$filesize,$BucketName,$AWSURI);
		
		if($exportID){
			echo $exportID;
		}
		else {
			echo "Failed! Error when inserting AWS File details into the local database";
			echo "========649=========";
		}
	}

	/* creates a compressed zip file */
	public function CreateZip($folder,$destination) {
		
		// Get real path for our folder
		$rootPath = realpath($folder);

		// Initialize archive object
		$zip = new ZipArchive();
		$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);

		// Create recursive directory iterator
		/** @var SplFileInfo[] $files */
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($rootPath),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $name => $file)
		{
			// Skip directories (they would be added automatically)
			if (!$file->isDir())
			{
				// Get real and relative path for current file
				$filePath = $file->getRealPath();
				$relativePath = substr($filePath, strlen($rootPath) + 1);

				// Add current file to archive
				$zip->addFile($filePath, $relativePath);
			}
		}

		// Zip archive will be created only after closing object
		$zip->close();	
		return true;	
	}
	
	public function ReadVariableTable($DBDetails,$name){
	
		/*Connecting with Mysql*/
		if($DBDetails["port"]==""){
			$conn = mysqli_connect(trim($DBDetails["host"]), trim($DBDetails["username"]), trim($DBDetails["password"]),trim($DBDetails["database"]));
		}
		else{
			$conn = mysqli_connect(trim($DBDetails["host"]), trim($DBDetails["username"]), trim($DBDetails["password"]),trim($DBDetails["database"]),trim($DBDetails["port"]));	
		}

		if (mysqli_connect_errno()) {
			return false;
		}

		
		if(isset($DBDetails["prefix"]) && $DBDetails["prefix"] != "")
			$table = $DBDetails["prefix"]."variable";
		else
			$table = "variable";

		// Perform queries
		$FilePathQuery = mysqli_query($conn,"SELECT * FROM $table WHERE name='$name'");
		
		if(mysqli_num_rows($FilePathQuery)==0){
			return false;
		}
		$data = mysqli_fetch_array($FilePathQuery);
		if(isset($data["value"]) && $data["value"] != ""){
			return unserialize($data["value"]);
		}
		else {
			return false;
		}
	}
	
	public function AddNewRecordInVariableTable($DBDetails,$name,$value){
	
		/*Connecting with Mysql*/
		if($DBDetails["port"]==""){
			$conn = mysqli_connect(trim($DBDetails["host"]), trim($DBDetails["username"]), trim($DBDetails["password"]),trim($DBDetails["database"]));
		}
		else{
			$conn = mysqli_connect(trim($DBDetails["host"]), trim($DBDetails["username"]), trim($DBDetails["password"]),trim($DBDetails["database"]),trim($DBDetails["port"]));	
		}

		if (mysqli_connect_errno()) {
			return false;
		}
		
		if(isset($DBDetails["prefix"]) && $DBDetails["prefix"] != "")
			$table = $DBDetails["prefix"]."variable";
		else
			$table = "variable";

		// Perform queries
		$FilePathQuery = mysqli_query($conn,"SELECT * FROM $table WHERE name='$name'");
		if(mysqli_num_rows($FilePathQuery)>0){
			mysqli_query($conn,"UPDATE $table SET name='$name',value='$value'");
		}
		else {
			mysqli_query($conn,"INSERT INTO $table (name,value) VALUES ('$name','$value')");
		}
	}
	
	public function is_dir_empty($dir) {
	  if (!is_readable($dir)) return NULL; 
	  $handle = opendir($dir);
	  while (false !== ($entry = readdir($handle))) {
		if ($entry != "." && $entry != "..") {
		  return FALSE;
		}
	  }
	  return TRUE;
	}	
	
	public function getServerPort($server_id){
		
		$NumberCharacters 	= '0123456789';
		$port = '';
		for ($i = 0; $i < 4; $i++) {
			$port .= $NumberCharacters[rand(0, strlen($NumberCharacters) - 1)];
			if($i == 0 && $port == 0){
				$this->getServerPort($server_id);
			}
		}
		
		// check it in db here
		$dockerObject = new docker();		
		if($dockerObject->portAlreadyInUse($server_id,$port)){
			$this->getServerPort($server_id);
		}
		
		return $port;
	}
	
    public function CreateDBUser($sitename, $dbname, $dbhost, $dbuser, $dbpassword, $dbport) {
        $dataforuser = $sitename.'_user';
        $dataforpassword = $sitename.'_password';
        $length =12; 
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $user = substr(md5($dataforuser.'_'.str_shuffle( $chars )), 0, $length );
        $password = substr(md5($dataforpassword.'_'.str_shuffle( $chars )), 0, $length );
		if($dbport=="") {
			$con = new mysqli($dbhost, $dbuser, $dbpassword);
		} else {
			$con = new mysqli($dbhost, $dbuser, $dbpassword, $dbname, $dbport);	
		}
		$sql = "CREATE USER '$user'@'%' IDENTIFIED BY '$password'";			
		if($con->query($sql) === TRUE) {
			$con->query("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES ON $dbname.* TO '$user'@'%' IDENTIFIED BY '$password'");				
			$this->response = array(
				"Status" => "Success",
				"MsgCode" => "201",
				"MsgDescription" => "User created successfully",
				"Response" => array(
					"database" => $dbname,
					"dbuser" => $user,
					"dbpassword" => $password,
			   )
			);
			return $this->response;
		} else {
			$this->response = array(
				"Status" => "ERROR",
				"MsgCode" => "400",
				"MsgDescription" => "User is not created",
				"Response" => array(
					"ERROR" => "ERROR_DBSQL"
				)
			);
			return $this->response;
		}
    }

    // Function to update sites.php file
    function updateSitesFile($siteurl, $SitesFilePath) {
        $StepSite = '$sites[\''.$siteurl.'\'] = \'all\';';
        $fileappendsite = fopen($SitesFilePath, 'a');
        if ($fileappendsite) {
            fwrite($fileappendsite, "\n". $StepSite);
            fclose($fileappendsite);
            return True;
        } else {
            return False;
        }
    }

    // Function to update settings.php file
    function updateSettingsFile($drup_dbname, $dbuser, $password, $db_ip, $SettingsFilePath, $prefix) {
        $StepSettings = '$databases[\'default\'][\'default\']';
        $StepSettings .= ' = array(\'database\' => \''.$drup_dbname;
        $StepSettings .= '\',\'username\' => \''.$dbuser;
        $StepSettings .= '\',\'password\' => \''.$password;
        $StepSettings .= '\',\'host\' => \''.$db_ip;
        $StepSettings .= '\',\'port\' => \'\',\'driver\' => \'mysql\',\'prefix\' => \''.$prefix.'\');';
        $fileappendsetting = fopen($SettingsFilePath, 'a');
        if ($fileappendsetting) {
            fwrite($fileappendsetting, "\n". $StepSettings);
            fclose($fileappendsetting);
            return True;
        } else {
            return False;
        }
    }
	
	public function createPipeline($SiteID, $PipelineName, $GitURL, $GitBranch, $ServerIP, $ServerPort, $PrivateGitFolder, $S3BucketDetails, $FilePublicPath,$FilePrivatePath, $PublicFileBucket, $PrivateFileBucket){
		
		try {
			$body = [
				'group'=>$SiteID,
				'pipeline' => [
					'enable_pipeline_locking' => true,
					'name' => $PipelineName,
					'template' => 'Drupal1',
					'parameters' => [
						[
							'name' => 'SOURCE_DIR',
							'value' => ''
						]
					],
					'environment_variables' => [
						[
							'secure' => false,
							'name' => 'SITE_ID',
							'value' => $PipelineName
						],
						[
							'secure' => false,
							'name' => 'COMPOSE_FILE',
							'value' => 'app-compose.yml'
						],
						[
							'secure' => false,
							'name' => 'APP_NODE1_IP',
							'value' => $ServerIP
						],
						[
							'secure' => false,
							'name' => 'APP1_PORT',
							'value' => $ServerPort
						],
						[
							"secure" => false,
							"name" => "SETTINGS_FOLDER_NAME",
							"value" => $PrivateGitFolder
						],
						[
							"secure" => false,
							"name" => "PUBLIC_FILE_PATH",
							"value" => $FilePublicPath
						],
						[
							"secure" => false,
							"name" => "PRIVATE_FILE_PATH",
							"value" => $FilePrivatePath
						],
						[
							"secure" => false,
							"name" => "PUBLIC_FILE_BUCKET_NAME",
							"value" => $PublicFileBucket
						],
						[
							"secure" => false,
							"name" => "PRIVATE_FILE_BUCKET_NAME",
							"value" => $PrivateFileBucket
						],
						[
							"secure" => false,
							"name" => "S3_ACCESS_KEY_ID",
							"value" => $S3BucketDetails['awsAccessKey']
						],
						[
							"secure" => false,
							"name" => "S3_SECRET_ACCESS_KEY",
							"value" => $S3BucketDetails['awsSecretKey']
						]

					],										
					'materials' => [
						[
							'type' => 'git',
							'attributes' => [
								'url' => $GitURL,
								'destination' => 'drupal',
								'filter' => null,
								'name' => 'drupal',
								'auto_update' => true,
								'branch' => $GitBranch,
								'submodule_folder' => null,
								'shallow_clone'=> false
							]
						]
					],										
					'stages' => null,
					'tracking_tool' => null,
					'timer' => null
				]
			];
			
			//echo "<pre>";
			//print_r($body);
			//echo "</pre>";

			$dockerPipeline = new GuzzleHttp\Client(['base_uri' => 'http://cd.stratosys.tech:8153']);
			$dockerPipelineRes = $dockerPipeline->request('POST', '/go/api/admin/pipelines',
				[
					'headers' => [
						'Authorization' => 'Basic ZXZvbHZlcjpnMDVlcnZlcg==',
						'Accept'=>'application/vnd.go.cd.v1+json',
						'Content-Type'=>'application/json'
					],
					'body' => json_encode($body)
				]
			)->getBody()->getContents();
			return array("Status"=>"Success","Msg"=>"Success! Pipeline created successfully.");			
		}
        catch(Exception $e) {
			return array("Status"=>"Failed","Msg"=>$e->getMessage());
        }
	}
	
	public function unpausePipeline($pipelinename){
		try {
			$dockerUnpausePipeline = new GuzzleHttp\Client(['base_uri' => 'http://cd.stratosys.tech:8153']);
			$dockerUnpausePipelineRes = $dockerUnpausePipeline->request('POST', '/go/api/pipelines/'.$pipelinename.'/unpause',
				[
					'headers' => [
						'Authorization' => 'Basic ZXZvbHZlcjpnMDVlcnZlcg==',
						'Confirm' => 'true'
					]
				]
			)->getBody()->getContents();
			return array("Status"=>"Success","Msg"=>"Success! Pipeline unpaused successfully.");
		}
		catch(Exception $e){
			return array("Status"=>"Failed","Msg"=>$e->getMessage());
		}	
	}	
}
