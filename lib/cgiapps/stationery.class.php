<?php
/**
 * stationery.class.php
 * a subclass of cgiapp2
 * an app to access the Chili publishing system
 *
 * this app expects to be logged into via passport,
 * and so refers to some SESSION variables
 * (session name is "stationery")
 * @author Patrick Maslen (pmaslen@unimelb.edu.au)
 *
 * uses Twig templating system, without the cgiapp2 interface to same
 */
/**
 * required files
 */
require_once(dirname(__FILE__) . "/../../lib/find_path.inc.php");
//require_once($_SERVER["DOCUMENT_ROOT"] . LIBPATH . "/lib/addons/cgiapp2_Plugin_twig.class.php"); //also includes cgiapp2
require_once($_SERVER["DOCUMENT_ROOT"] . LIBPATH . "/lib/addons/Cgiapp2-2.0.0/Cgiapp2.class.php");
require_once($_SERVER["DOCUMENT_ROOT"] . LIBPATH . "/lib/addons/Twig/lib/Twig/Autoloader.php");
require_once($_SERVER["DOCUMENT_ROOT"] . LIBPATH . "/includes/dbconnect.inc.php");
include_once($_SERVER["DOCUMENT_ROOT"] . LIBPATH . "/includes/chili.inc.php");
class Stationery extends Cgiapp2 {
  /**
   * @var string $username
   * obtained from passport session info, or defaulting to 'bobthemadjr'
   */
  private $username;
  /**
   * @var array(string mode_name => string description) $run_modes_default_text
   * default text to appear in links to the visible run modes
   */
  private $run_modes_default_text;
  /**
   * @var object $twig
   * Twig environment for templates
   * In this case the template environment may be
   * simpler to install than the template interface
   */
  private $twig;
  /**
   * @var object $loader
   * loader for twig environment
   */
  private $loader;
  /** 
   * @var object $conn
   * PDO database connection
   */
  private $conn;
  /**
   * @var error
   * error messages
   */
  private $error;
  /** 
   * @var action
   * default action for forms
   */
  private $action;
  /** 
   * @var first_time
   * sets to true if a user has not set up a profile
   */
  private $first_time;
  /**
   * @var $select, $insert, $update, $delete
   * arrays to store prepared sql statements used by the program
   */
   private $select;
   private $insert;
   private $update;
   private $delete;
   /**
    * @var chili_apikey
    * @var chiliservice
    * object to connect to chili server
    */
   //private $chiliservice;
   private $apikey;
   private $chili_user;
   private $chili_pass;
   private $client;
   function setup() {
    /** 
     * database
     */
    // $this->dbconnect_string = DBCONNECT;
    /* should put some error catching here */
    try {
      $this->conn = new PDO(DBCONNECT, DBUSER, DBPASS);
      $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch(PDOException $e) {
      $this->error = 'ERROR: ' . $e->getMessage();
      $this->conn = null;
    }
    /** 
     * template
     */
 
   /*prepare Twig environment */
    Twig_Autoloader::register();
    if ($this->param('template_path')) {
	$this->template_path = $this->param('template_path');
    }
    $this->loader = new Twig_Loader_Filesystem($this->template_path); 
    $this->twig = new Twig_Environment($this->loader, array(
					    "auto_reload" => true
					    ));
    $tpl_params = $this->param('template_params');
    $this->template_filename = $tpl_params['filename'];

    /* obtain chili api key */
    /* remove , array('trace' => 1) after testing */
    $this->client = new SoapClient(CHILI_APP . "main.asmx?wsdl");
    $this->getChiliUser();
    $keyrequest = $this->client->GenerateApiKey(array("environmentNameOrURL" => CHILI_ENV,"userName" => $this->chili_user, "password" => $this->chili_pass));
    $dom = new DOMDocument();
    $dom->loadXML($keyrequest->GenerateApiKeyResult);
    $this->apikey = $dom->getElementsByTagName("apiKey")->item(0)->getAttribute("key");
    /**
     * set up the legal run modes => methods table
     * note that login screen is handled outside of the app
     * these run modes assume access is allowed
     * (see passport)
     */
    $this->run_modes(array(
			   'start' => 'showStart',
			   'profile' => 'showProfile',
			   'new_profile' => 'createProfile',
			   'template' => 'selectTemplate',
			   'edit' => 'editTemplate',
			   'proof' => 'showProof',
			   'history' => 'showHistory',
			   'detail' => 'showJobDetail',
			   'confirm' => 'showConfirmation',
			   'thanks' => 'showFinal'
			   ));
    // should be an entry for each of the run modes above
    $this->run_modes_default_text = array(
					  'start' => 'Home',
					  'profile' => 'Profile',
					  'template' => 'Select Template',
					  'edit' => 'Edit Template',
					  'proof' => 'Show Proof',
					  'history' => 'History',
					  'detail' => 'Detail',
					  'confirm' => 'Confirm',
					  'thanks' => 'Thanks'
					  );
    $this->user_visible_modes = array(
			      'template' => 'Select Template',
			      'history' => 'History',
			      'detail' => 'Detail',
			      'confirm' => 'Confirm',
			      'thanks' => 'Thanks'
			      );

    $this->start_mode('start');
    //$this->error_mode('handle_errors');
    $this->mode_param('mode');
    $this->action = $_SERVER['SCRIPT_NAME'];
    if(isset($_SESSION['username']))
      {
	$this->username = $_SESSION['username'];
      }
    else
      {
	$this->username = "bobmadjr"; // test username only
      }
    $this->sqlstatements();
  }
 
   /* select a random user for chili api functions */
   private function getChiliUser() {
     // users need to be moved to databas
     $chili_users_inc = array(
			 array('username'=>'StatUser1', 'password'=>'cmyk011'),
			 array('username'=>'StatUser2', 'password'=>'cmyk011'), 
			 array('username'=>'StatUser3', 'password'=>'cmyk011'), 
			 array('username'=>'StatUser4', 'password'=>'cmyk011'), 
			 array('username'=>'StatUser5', 'password'=>'cmyk011'), 
			 array('username'=>'StatUser6', 'password'=>'cmyk011')
			 );
     $x = mt_rand(0,count($chili_users_inc)-1);
     $user_array = $chili_users_inc[$x];
     $this->chili_user = $user_array['username'];
     $this->chili_pass = $user_array['password'];
   }

  /**
   * setup PDO prepared sql statements for use by the program
   * arrays of SELECT, INSERT, UPDATE and DELETE statements
   * this function is a convenient holder for all the SQL 
   * to prevent duplication
   */
  private function sqlstatements() {
    $this->select = array(
			  'SELECT * FROM user WHERE username = :id',
			  'SELECT name, acronym, department_id FROM department',
			  'SELECT department_id from user_department where username = :id',
			  "SELECT * FROM template WHERE category_id = :category_id AND department_id in ( 'jjjdepartments' ) OR department_id IS NULL ORDER BY department_id",
			  'SELECT * FROM job WHERE username = :username ORDER BY job_id DESC LIMIT 1',
			  'SELECT j.job_id, j.username, c.description FROM job j, category c, template t WHERE t.template_id = j.template_id AND t.category_id = c.category_id and j.job_id = :job_id',
			  'SELECT id FROM template WHERE template_id = :template_id AND chili_id = :chili_id'
			  );
    $this->insert = array(
			  'INSERT INTO user VALUES(:username, :firstname, :lastname, :telephone, :email, DEFAULT);',
			  'INSERT INTO user_department VALUES(:username, :department_id)',
			  'INSERT INTO job (job_id, username, template_id) VALUES(DEFAULT, :username, :template_id)' 
			  );
    $this->update = array(
			  'UPDATE user SET given_name = :firstname, family_name = :lastname, phone = :phone, email = :email WHERE username = :id',
			  'UPDATE job SET chili_id = :chili_id WHERE username = :username AND job_id = :job_id'
			  );
    $this->delete = array(
			  'DELETE FROM user_department WHERE username = :username AND department_id = :department_id'
			  );
  }
  /**
   * function to shut everything down after the app has run
   */
  function teardown() {
    // close database connection
      $this->conn = null;
  }  
  /**
   * error handling
   */
  function handle_errors($errno, $errstr) {
  }
  /**
   * mode functions here
   */
  /**
   * showStart
   * Starting page -- shows instructions on how to use the app.
   * redirect to showProfile if no profile is defined locally
   * for this username ($_SESSION["username"])
   */
  function showStart() {
    /* check database for user name */
    $error = "";
    try {
      //$conn = new PDO(DBCONNECT, DBUSER, DBPASS);
      //$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $stmt = $this->conn->prepare($this->select[0]);
      $stmt->execute(array('id' => $_SESSION["username"]));
      if ($stmt->rowCount() == 0) {
	// go to profile page
	return $this->createProfile();
      }
    } catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    $t = 'start.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes,
			       'error' => $error
			       ));
    return $output;
  }
  private function isDepartment($string) {
    $isDept = false;
    if(strpos($string, "department")!== false) {
      $isDept = true;
    }
    return $isDept;
  }
  function createProfile() {
    $first_time = true;
    $error = "";
    if (isset($_REQUEST["submitted"])) {
      //$error = print_r($_REQUEST);
      try {
	$stmt = $this->conn->prepare($this->insert[0]);
	/* first add user */
	$stmt->execute(array(
			     'username' => $_SESSION["username"],
			     'firstname' => $_REQUEST['firstname'],
			     'lastname' => $_REQUEST['lastname'],
			     'telephone' => $_REQUEST['phone'],
			     'email' => $_REQUEST['email']
			     ));
	
	$stmt2 = $this->conn->prepare($this->insert[1]);
	$stmt2->bindParam(':username', $username);
	$stmt2->bindParam(':department_id', $department_id);
	$department_keys = array();
	foreach(array_keys($_REQUEST) as $key) {
	  if ($this->isDepartment($key)) {
	    $department_keys[$key] = $_REQUEST[$key];
	  }
	}
	foreach($department_keys as $dept => $value) {
	  $username = $_SESSION["username"];
	  $department_id = $value;
	  $stmt2->execute();
	}
	$this->error .= "<p>Adding details for ". $this->username . ".</p>";
	$error = print_r($department_keys);
	return $this->showProfile();
      }
      catch(PDOException $e) {
	$error .= $e->getMessage();
      }
    }
    $first_name = $_SESSION["given_names"];
    $surname = $_SESSION["family_name"];
    $email = $_SESSION["email"];
    $phone = "";
/* get department names */
    $departments = array();
    try {
      $stmt = $this->conn->prepare($this->select[1]);
      $stmt->execute(array());
      while($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	array_push ($departments, $row);
      }
    }
    catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    /* divide department list into 2 for styling */
    $list1_length = ceil(count($departments)/2);
    $departments1 = array_slice($departments, 0, $list1_length );
    $departments2 = array_slice($departments, -1 * ($list1_length-1));
    /* output */
    $t = 'profile.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes,
			       'user' => $this->username,
			       'first_time' => $first_time,
			       'first_name' => $first_name,
			       'surname' => $surname,
			       'email' => $email,
			       'phone' => $phone,
			       'departments1'=> $departments1,
			       'departments2'=> $departments2,
			       'action' => $this->action,
			       'error' => $error
			       ));
    return $output;
  }
  function showProfile() {
    /* edit account profile
     * default if no account setup
     * save profile in database
     */
    $action = $this->action . "?mode=" . "profile";
    $first_time = false;
    $error = $this->error;
    /* check for form submission first */
    if (isset($_REQUEST["submitted"])) {
      /* if the form has been submitted, update the record */
      /* modify update statement depending on what has changed */
      $form_keywords = array("firstname", "lastname", "phone", "email");
      $used_keywords = array();
      $update_string = "";
      foreach ($form_keywords as $keyword) {
	if (array_key_exists($keyword, $_REQUEST)) {
	  $update_string .= $keyword . " = :" . $keyword . " ";
	  $used_keywords[$keyword] = $_REQUEST[$keyword];
	}
      }
      if (count($form_keywords) == count($used_keywords)) {
	$statement = $this->update[0];
      }
      else {
	$statement = substr_replace($this->update[0], $update_string, 16,80); 
      }
      $used_keywords["id"] = $_SESSION["username"];
      try {
	$stmt = $this->conn->prepare($statement);
	$stmt->execute($used_keywords);
      }
      catch(Exception $e) {
	$error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
      }
      /* update departments */
      $old_depts = array(); /* the previously selected departments */
      $new_depts = array(); /* the newly selected departments */
      foreach(array_keys($_REQUEST) as $key) {
	if ($this->isDepartment($key)) {
	  $new_depts[] = $_REQUEST[$key];
	}
      }
      try {
	$stmt_depts = $this->conn->prepare($this->select[2]);
	$stmt_depts->execute(array('id' => $_SESSION["username"]));
	while($row = $stmt_depts->fetch(PDO::FETCH_ASSOC)) {
	  array_push ($old_depts, $row["department_id"]);
	}
      }
      catch(Exception $e) {
	$error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
      }
      $common_depts = array_intersect($old_depts, $new_depts);
      $to_insert = array_diff($new_depts, $common_depts, $old_depts );
      $to_delete = array_diff($old_depts, $common_depts, $new_depts );
      try {
	$stmt = $this->conn->prepare($this->insert[1]);
	$stmt->bindParam(':username', $username);
	$stmt->bindParam(':department_id', $department_id);
	$stmt2 = $this->conn->prepare($this->delete[0]);
	$stmt2->bindParam(':username', $username);
	$stmt2->bindParam(':department_id', $department_id);
	if (count($to_insert) > 0){
	  foreach ($to_insert as $dept_id) {
	    $username = $_SESSION["username"];
	    $department_id = $dept_id;
	    $stmt->execute(array(
				 'username' => $username,
				 'department_id' => $department_id
				 ));
	  }
	}
	if (count($to_delete) > 0) {
	  foreach ($to_delete as $dept_id) {
	    $username = $_SESSION["username"];
	    $department_id = $dept_id;
	    $stmt2->execute(array(
				  'username' => $username,
				  'department_id' => $department_id
				  ));
	  }
	}	
      }
      catch(Exception $e) {
	$error = print_r($to_delete, true) . '<pre>ERROR: ' . $e->getMessage() . '</pre>';
      }
      print_r($to_insert);
      print_r($to_delete);
      $error .= "<p>Updated ". $this->username . ".</p>";
    }
    /* get user details */
    /* get info for form */
    try {
      $stmt = $this->conn->prepare($this->select[0]);
      $stmt->execute(array('id' => $_SESSION["username"]));
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$first_name = $row["given_name"];
	$surname = $row["family_name"];
	$email = $row["email"];
	$phone = $row["phone"];
      }
    }
    catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    /* find active departments */
    $active_depts = array();
    try {
      $stmt = $this->conn->prepare($this->select[2]);
      $stmt->execute(array('id' => $_SESSION["username"]));
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	array_push ($active_depts, $row["department_id"]);
      }
    }
    catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    /* get department names */
    $departments = array();
    try {
      $stmt = $this->conn->prepare($this->select[1]);
      $stmt->execute(array());
      while($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	array_push ($departments, $row);
      }
    }
    catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    /* divide department list into 2 for styling */
    $list1_length = ceil(count($departments)/2);
    $departments1 = array_slice($departments, 0, $list1_length );
    $departments2 = array_slice($departments, -1 * ($list1_length-1));
    /* output */
    $t = 'profile.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes,
			       'user' => $this->username,
			       'first_time' => $first_time,
			       'first_name' => $first_name,
			       'surname' => $surname,
			       'email' => $email,
			       'phone' => $phone,
			       'departments1'=> $departments1,
			       'departments2'=> $departments2,
			       'action' => $action,
			       'error' => $error,
			       'active_depts' => $active_depts
			       ));
    return $output;
  }
  function selectTemplate() {
    /* choose from one of the available CHILI templates */
    /* get categories */
    /* buscards = 1, letheads = 2, withcomps = 3 */
    $error = "";
    $stationery_type_list = array(
				  array(), array(), array()
				  );
    $dept_ids = array();
    /* get department ids into comma separated string */
    try {
      $stmt_depts = $this->conn->prepare($this->select[2]);
      $stmt_depts->execute(array('id' => $_SESSION["username"]));
      while($row = $stmt_depts->fetch(PDO::FETCH_ASSOC)) {
	array_push ($dept_ids, $row["department_id"]);
      }
    }
    catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    } 
    $department_list = implode(",", $dept_ids);
    /* get category ids and names into $categories */
    /* get templates */
    $categories_count = 3;
    try {
      $statement1 = $this->select[3];
      // repace :department with $department_list
      $statement = str_replace("jjjdepartments", $department_list, $statement1);
      $stmt = $this->conn->prepare($statement);
      $category_id = 1;
      //$stmt->bindParam(':category', $category_id);
      /* for each category, just 1 here now */
      for ($category_id = 1; $category_id < $categories_count +1; $category_id ++) {
	$stmt->execute(array(':category_id' => $category_id));
	while($row = $stmt->fetch(PDO::FETCH_OBJ)) {
	  array_push ($stationery_type_list[$category_id-1], $row);
	}

      }
      
    }
    catch(Exception $e) {
      $error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    $basic_url = 'index.php?mode=edit';
    foreach ($stationery_type_list[0] as $buscard) {
      $buscard->url = $basic_url . '&id=' . $buscard->chili_id . '&base=' . $buscard->template_id;
      $buscard->short = $buscard->short_name;
    }
    $t = 'template.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'error' => $error,
			       'modes' => $this->user_visible_modes,
			       'buscards'=> $stationery_type_list[0],
			       'letheads'=> $stationery_type_list[1],
			       'withcomps'=> $stationery_type_list[2]
			       ));
     return $output;
  }
 /* embed the CHILI editor and submit button */
  /* if no template_id in url, arbort and return to select a template
  /* create new job
   * get new chili job id based on template_id
   * open iframe with chili_job for editing
  /* submit button goes to confirm screen */ 
  /* API calls:
   * 1. ResourceItemCopy to create new doc from template
   * public string ResourceItemCopy ( string apiKey, string resourceName, string itemID, string newName, string folderPath );
   * 2. DocumentGetEditorURL to get URL for new document
   * public string DocumentGetEditorURL ( string apiKey, string itemID, string workSpaceID, string viewPrefsID, string constraintsID, bool viewerOnly, bool forAnonymousUser );
   */
  function editTemplate() {
    $blankDocTemplateID = $_REQUEST["id"];
    $base = $_REQUEST["base"];
    $error = $this->error;
    $folderPath = 'USERFILES/';
    /* as a basic check */
    /* kick them back to select if the id is not the right length */
    if (strlen($blankDocTemplateID) != 36) {
      return $this->selectTemplate();
    }
    
    /* check for $_REQUEST["proof"] --> generate proof pdf, load samesame page

     * check for $_REQUEST["submit"] --> go to confirm screen
     * check for $_REQUEST["samesame"] --> use job_id directly instead of copy
     * proof will also have samesame by default
     * non-submitted should also be samesame
     */
    if (isset($_REQUEST["samesame"])) {
	if ($_REQUEST["samesame"]=="same" and isset($_REQUEST["job"])) {
	  /* use job_id directly instead of copy */
	  /* unless base + id  is one of the base templates */
	  /*if (! $this->isBaseTemplate($blankDocTemplateID, $base)) {

	    }*/
	  $job_id = $_REQUEST["job"];
	  $itemID = $this->getChiliId($job_id);
	  /*else {
	    /* create new job locally 
	    $job_id = $this->createJob($base);
	  }*/
	 
	}
      }
    else {
      /* create new job locally */
      $job_id = $this->createJob($base);
      $documentName = $this->getTemplateName($job_id);
      $soap_params = array(
			   "apiKey" => $this->apikey,
			   "resourceName" => "Documents",
			   "itemID" => $blankDocTemplateID,
			   "newName" => $documentName,
			   "folderPath" =>  $folderPath,
			   );
      $resourceItemXML = $this->client->ResourceItemCopy($soap_params);
      $dom = new DOMDocument();
      $dom->loadXML($resourceItemXML->ResourceItemCopyResult);
      $itemID = $dom->getElementsByTagName("item")->item(0)->getAttribute("id");
      /* update job with new template_id */
      $this->updateJob($job_id, $itemID);
    }
    $proofurl = $this->action . "?mode=proof&base=$base&proof=true&samesame=same&job=$job_id";

    $doc = $itemID;
    $ws = CHILI_WS;
    $apikey = $this->apikey;
    $username = $this->chili_user;
    $password = $this->chili_pass;
    $DocumentGetEditorURL_params = array(
					 "apiKey" => $this->apikey,
					 "itemID" => $itemID,
					 "workSpaceID" => $ws,
					 "viewPrefsID" => "",
					 "constraintsID" => "",
					 "viewerOnly" => false,
					 "forAnonymousUser" => false
					 );
    $urlinfo = $this->client->DocumentGetEditorURL($DocumentGetEditorURL_params);
    $dom = new DOMDocument();
    $dom->loadXML($urlinfo->DocumentGetEditorURLResult);
    $src = $dom->getElementsByTagName("urlInfo")->item(0)->getAttribute("url");
    $src_extra = "&username=$username&password=$password";
    $error = "";
 
    $t = 'edit.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'proofurl' => $proofurl,
			       'error' => $error,
			       'modes' => $this->user_visible_modes,
			       'iframesrc' => $src . $src_extra
			       ));
    return $output;
  }
  /* returns a chili id for a particular job */
  private function getChiliId($job_id) {
    $chili_id = "";
    $statement = $this->select[4];
    $replaced = str_replace(":username", ":username and job_id = :job_id", $statement);
    try {
      $stmt = $this->conn->prepare($replaced);
      $stmt->execute(array('username' => $this->username,
			   'job_id' => $job_id));
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$chili_id = $row["chili_id"];
      }
      
    }
    catch (Exception $e){
      $this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    return $chili_id;
  }

  /* create a new job based on the username
   * return job_id or false if it failed
   */
  private function createJob($base_template_id) {
    /* get job id for documentName below */
    /* $this->select[4] */
    $job_id = 2; // obviously a dummy function
    /* create new job locally */
    try {
      $stmt = $this->conn->prepare($this->insert[2]);
      $stmt->execute(array('username' => $this->username, 'template_id' => $base_template_id));
    }
    catch (Exception $e) {
      $this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
      $job_id = false;
    }
    /* get job id for job just created */
    if ($job_id !== false) {
      try {
	$stmt2 = $this->conn->prepare($this->select[4]);
	$stmt2->execute(array('username' => $this->username));
	while($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
	  $job_id = $row["job_id"];
	}
      
      }
      catch (Exception $e){
	$this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
	$job_id = false;
      }
    }
    return $job_id;
  }

  /* returns true if the combination of base template id and chili id 
   * is one of the chili base templates
   */
  private function isBaseTemplate($base_template_id, $chili_id) {
    $isBaseTemplate = false;
    $template_id = -1;
    try {
      $stmt = $this->conn->prepare($this->select[6]);
      $stmt->execute(array('template_id' => $base_template_id,
			   'chili_id' => $chili_id));
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$template_id = $row["template_id"];
      }
      if ($template_id != -1) {
	$isBaseTemplate = true;
      }
    }
    catch (Exception $e){
      $this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
    return $isBaseTemplate;
  }


  /* add chili id to job
   * yeah, could be more general */
  private function updateJob($job_id, $chili_id) {
    try {
      $stmt = $this->conn->prepare($this->update[1]);
      $stmt->execute(array(
			   'chili_id' => $chili_id,
			   'username' => $this->username,
			   'job_id' => $job_id
			   ));
    }
    catch (Exception $e) {
      $this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }
  }
  
  /* returns a string document name in the format:
   * job_id-username-category (no spaces)
   * or the derived name from the job_id
   * or false if no name is available
   * $template_id is a string
   */
  private function getTemplateName($job_id) {
    $template_name_array = array();
    $template_name = "";
    try {
      $stmt = $this->conn->prepare($this->select[5]);
      $stmt->execute(array('job_id' => $job_id));
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$template_name_array = array(
				     str_pad($row["job_id"], 4, "0", STR_PAD_LEFT),
				$row["username"],
				$row["description"]
				);
      }
    }
    catch(Exception $e) {
      $this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
      $template_name = false;
    }
    $template_name = implode('-',$template_name_array);
    return str_replace(' ', '', $template_name);
  }
  function showProof() {
    /* display proof PDF and allow user to return to editing or sumbit to print */
    $base = $_REQUEST["base"];
    $error = $this->error;
    $job_id = $_REQUEST["job"];
    $itemID = $this->getChiliId($job_id);
    $editurl = $this->action . "?mode=edit&base=$base&id=$itemID&samesame=same&job=$job_id";
    $pdfurl = "";
    if (isset($_REQUEST["proof"])) {
      /* get settingsXML for PDF settings resource PROOF */
      /* ResourceItemGetDefinitionXML used in API sample:
       * public string ResourceItemGetDefinitionXML ( string apiKey, string resourceName, string itemID );
      /* public string ResourceItemGetXML ( string apiKey, string resourceName, string itemID ); */
      $pdf_resource_params = array(
				   "apiKey" => $this->apikey,
				   "resourceName" => "PDFExportSettings",
				   "itemID" => CHILI_PROOF
				   );
      $settingsXML = $this->client->ResourceItemGetDefinitionXML($pdf_resource_params);
      print_r($settingsXML);
      /*generate pdf with api */
      /* public string DocumentCreatePDF ( string apiKey, string itemID, string settingsXML, int taskPriority ); */
      $soap_params = array(
			   "apiKey" => $this->apikey,
			   "itemID" => $itemID,
			   "settingsXML" => $settingsXML->ResourceItemGetDefinitionXMLResult,
			   "taskPriority" => 1
			   );
      $taskXML = $this->client->DocumentCreatePDF($soap_params);
      $dom = new DOMDocument();
      $dom->loadXML($taskXML->DocumentCreatePDFResult);
      $task_id = $dom->getElementsByTagName("task")->item(0)->getAttribute("id");
      print_r($task_id);
    }
    // check task status until task is finished, then get URL
    $task_params = array(
			 "apiKey" => $this->apikey,
			 "taskID" => $task_id
			 );
    $status = "";
    try{
      do {
	$task_statusXML =  $this->client->TaskGetStatus($task_params);
	$dom = new DOMDocument();
	$dom->loadXML($task_statusXML->TaskGetStatusResult);
	$status = $dom->getElementsByTagName("task")->item(0)->getAttribute("finished");
      } while ($status != "True");
      print_r($task_statusXML);
      $result = $dom->getElementsByTagName("task")->item(0)->getAttribute("result");
      $dom2 = new DOMDocument();
      $dom2->loadXML($result);
      $relativeURL = $dom2->getElementsByTagName("result")->item(0)->getAttribute("relativeURL");
      $pdfurl = CHILI_APP . $relativeURL; 
      //print_r($status);
    }
    catch (Exception $e) {
      $this->error = '<pre>ERROR: ' . $e->getMessage() . '</pre>';
    }

    $t = 'showproof.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes,
			       'editurl' => $editurl,
			       'pdfurl' => $pdfurl
			       ));
    return $output;
  }
  function showHistory() {
    /* show a list of past jobs for this user */
    $t = 'history.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes
			       ));
    return $output;
  }
function showJobDetail() {
  /* show details of a specific past job
   * include a 're-order' button which defines
   * a new job with the parameters of this one
   */
  /* remember to include base template in 're-order' url */
     $t = 'detail.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       
			       'modes' => $this->user_visible_modes
			       ));
    return $output;
  }
function showConfirmation() {
  /* confirmation screen requires
   * THEMIS code
   * quantity
   * special comments
   * pressing Submit here: 
   * updates the local job database, 
   * contacts CHILI for a job number
   * prints job as proof pdf
   * sends proof and job data to temporary storage area
   * redirect to showFinal 
   */ 
    $t = 'confirm.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes
			       ));
    return $output;
  }
function showFinal() {
    $t = 'final.html';
    $t = $this->twig->loadTemplate($t);
    $output = $t->render(array(
			       'modes' => $this->user_visible_modes
			       ));
    return $output;
  }
}
?>