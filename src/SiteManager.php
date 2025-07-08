<?php

namespace Drupal\site_manager;

use Drupal\Core\Url;
use Drupal\entity_parser\EntityParser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;

/**
 * Created by PhpStorm.
 * User: miandry
 * Date: 2019/10/9
 * Time: 8:30 PM
 */
class SiteManager extends EntityParser
{
    public $logger;
    public $site_name;
    public $domain;
    public $category;
    public $bdinfo = [];
    public $is_not_ready = false;
    private $site;
    private $theme;
    public $root_path;
    public function __construct($site)
    {
        $this->site = $site;
        $site_array = $this->node_parser($site);

        $this->logger = \Drupal::logger('site_manager');
        $this->site_name = isset($site_array['site_name']) ? $site_array['site_name'] : null ;
        $this->domain = isset($site_array['field_st_domain_name']) ? $site_array['field_st_domain_name'] : null;
        $this->theme = isset($site_array['st_theme'])? $site_array['st_theme']:'template_1';
        $config = \Drupal::config('site_manager.settings');
        if ($config->get('host') == null 
        && $config->get('user') == null
        && $config->get('root_path') == null
        ) {
            $this->is_not_ready = true;
            $message = "Please setting database settings in admin/config/site_manager";
            $messenger = \Drupal::messenger();
            $messenger->addMessage($message, 'error');
        }
        $this->bdinfo['password'] = '';
        if ($config->get('password')) {
            $this->bdinfo['password'] = $config->get('password');
        }
        $this->root_path =   $config->get('root_path');
        $this->bdinfo['host'] = $config->get('host');
        $this->bdinfo['user'] = $config->get('user');
    }
    
    public static function import($dbname){
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        $config = \Drupal::config('site_manager.settings');
        $servername = $config->get('host'); // Replace with your server name
        $username = $config->get('user'); // Replace with your database username
        $password =  $config->get('password');
  

        $module_handler = \Drupal::service('module_handler');
        $path = $module_handler->getModule('site_manager')->getPath();
        $sqlFile = DRUPAL_ROOT . "/" . $path . "/data/template.sql";
    
        // Command to import the database
        $command = "mysql -h $servername -u $username -p$password $dbname < $sqlFile";

            // Execute the command
        $output = null;
        $return_var = null;
        exec($command, $output, $return_var);
        // Check if the command was successful
        return ['return' => $return_var, 'output'=> $output];
    }
    public static function dump(){
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $config = \Drupal::config('site_manager.settings');
        $host = $config->get('host');
        $user = $config->get('user');
        $pass = $config->get('password');
        $database = $config->get('database_default');
        
        $module_handler = \Drupal::service('module_handler');
        $path = $module_handler->getModule('site_manager')->getPath();
        $dir = DRUPAL_ROOT . "/" . $path . "/data/template.sql";
        
        // Escape variables to prevent injection
        $escapedUser = escapeshellarg($user);
        $escapedPass = escapeshellarg($pass);
        $escapedHost = escapeshellarg($host);
        $escapedDatabase = escapeshellarg($database);
        $escapedFile = escapeshellarg($dir);
        
        // Step 1: Dump database to file
        exec("mysqldump --no-defaults --comments=FALSE --user={$escapedUser} --password={$escapedPass} --host={$escapedHost} {$escapedDatabase} --result-file={$escapedFile} 2>&1", $output1, $status1);
        
        // Step 2: Clean up file with sed (removing /*! and -- lines)
        exec("sed -i '/^--/d' {$escapedFile}", $output2, $status2);
        exec("sed -i '/\\/\\*!/d' {$escapedFile}", $output3, $status3);
        
        // Check statuses
        if ($status1 !== 0 || $status2 !== 0 || $status3 !== 0) {
          \Drupal::logger('site_manager')->error("Error during dump: mysqldump={$status1}, sed1={$status2}, sed2={$status3}");
        }else{
            $lastModified = date("Y-m-d H:i:s",filemtime($dir));
            \Drupal::messenger()->addMessage('New SQL dump created successfully at '.$lastModified);
        }
        

    
       //      unlink($preview); 
      //   }else{
        //     rename($preview, $dir);
     //       \Drupal::messenger()->addError('Failed to update database'); 
     //    }
 
    }

    public static function dump_file(){
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        $module_handler = \Drupal::service('module_handler');
        $path = $module_handler->getModule('site_manager')->getPath();
        $dir = DRUPAL_ROOT . "/" . $path . "/data/site/files";
        $dir_template = DRUPAL_ROOT . "/sites/template/files";
        exec("rm -rf {$dir} && cp -r {$dir_template} {$dir}
        ", $output);
        \Drupal::messenger()->addMessage( "copy files {$dir} ");
        
    }

    public function process()
    {
        if ($this->is_not_ready) {
            return false;
        }
        // move the site folder to sites/
        $status0 = $this->generateSite();
        // // replace string for database name
        $status1 = $this->configSiteDB();

        // //write in sites.php
        $status2 = $this->configSite();
       
 

        $this->site->field_status->value = 'In_process' ;
        $this->site->save();

        $this->execute();
        
        return true  ;
    }
    public function execute()
    {
      // if($this->site->field_status->value == 'In_process') {
          $newDB = $this->site_name ;
          $result = \Drupal\site_manager\SiteManager::import($newDB);
          if ( $result['return']  == 0) {
              \Drupal::logger('site_manager')->notice('Database '.$newDB.' inserted successfully\n ');
          
                $id = $this->site->id();
                $external_url = "/node/".$id;
                return new RedirectResponse($external_url);
              
          } else {
              \Drupal::logger('site_manager')->error("Error Database'.$newDB.' inserting record: " .$result['output']  );
          }
          return false ;
        //  $this->cloneDatabaseContentV2( $new_bd );
     //  }
    }
    public static function folderSize($dir)
    {
        $size = 0;
        if (is_object($dir)) {
            return $size;
        }
        if (!is_dir($dir)) {
            return " Folder not exist";
        }

        foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
            if (!is_object($each)) {
                $func_name = __FUNCTION__;
                $size_new = is_file($each) ? filesize($each) : static::$func_name($each);
                if (is_object($size_new)) {
                    $size_new = intval($size_new->__toString());
                }
                $size = intval($size) + intval($size_new);
            }
        }
        $mod = 1024;
        $units = explode(' ', 'B KB MB GB TB PB');
        for ($i = 0; $size > $mod; $i++) {
            $size /= $mod;
        }
        $output = round($size, 2) . ' ' . $units[$i];
        return $output;
    }
    public static function format_size_unit($size)
    {
        $mod = 1024;
        $units = explode(' ', 'B KB MB GB TB PB');
        for ($i = 0; $size > $mod; $i++) {
            $size /= $mod;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
    public static function dbconfig()
    {
        $config = \Drupal::config('site_manager.settings');
        if ($config->get('host') == null && $config->get('user') == null) {
            return true;
        }
        return false;
    }



    public static function is_exist($name)
    {
        $query_factory = \Drupal::entityQuery("node");
        $query_factory->condition("type", "site");
        $query_factory->condition("title", $name);
        $result = $query_factory->execute();
        if (!empty($result)) {
            $query_factory = \Drupal::entityQuery("node");
            $query_factory->condition("type", "booking");
            $query_factory->condition("field_item", end($result));
            $booking = $query_factory->execute();
            if (!empty($booking)) {
                return true;
            }
        }
        return false;
    }

    public function isNotReady()
    {
        return $this->is_not_ready;
    }
    public static function getRootPath(){

    }
    // copy default site to current site
    public function generateSite()
    {
        $site_name = $this->site_name;
        $module_handler = \Drupal::service('module_handler');
        $path = $module_handler->getModule('site_manager')->getPath();
        $default_url = DRUPAL_ROOT . "/" . $path . "/data/site/";
        $site_url = $this->root_path . "/sites/" . $site_name;
        return $this->recurse_copy($default_url, $site_url);
    }

    public static function generateThemeSite($site_name)
    {
        $module_handler = \Drupal::service('module_handler');
        $path = $module_handler->getModule('site_manager')->getPath();
        $theme_default = DRUPAL_ROOT . "/" . $path . "/data/theme";
        $directory =  $this->root_path . "/themes/custom/" . $site_name;
        $fileSystem = \Drupal::service('file_system');
        if (!is_dir($directory)) {
            if ($fileSystem->mkdir($directory, 0777, true) === false) {
                \Drupal::messenger()->addMessage(t('Failed to create directory ' . $directory), 'error');
                return false;
            }
        } else {
            @chmod($directory, 0777);
        }
        $this->recurse_copy($theme_default, $directory);

        $shop_info = $directory . "/theme.info.yml.txt";
        //replace token
        $content_settings = file_get_contents($shop_info, FILE_USE_INCLUDE_PATH);
        $content_settings = str_replace("{{site_name}}", $site_name, $content_settings);
        if (file_put_contents($shop_info, $content_settings) === false) {
            $this->logger->error('Failed to write file ' . $shop_info);
            $this->is_not_ready = true;
            return false;
        }

        $shop_new_info = $directory . "/" . $site_name . ".info.yml";
        if (!rename($shop_info, $shop_new_info)) {
            \Drupal::messenger()->addMessage(t('Failed to create file ' . $directory), 'error');
        }

        $shop_lib = $directory . "/theme.libraries.yml.txt";
        $shop_new_lib = $directory . "/" . $site_name . ".libraries.yml";
        if (!rename($shop_lib, $shop_new_lib)) {
            \Drupal::messenger()->addMessage(t('Failed to create file ' . $directory), 'error');
        }

        // return $this->recurse_copy($default_url, $site_url);
    }

    public function recurse_copy($src, $dst)
    {
        $fileSystem = \Drupal::service('file_system');
        if (!is_dir($dst)) {
            if ($fileSystem->mkdir($dst, 0777, true) === false) {
                $this->logger->error('Failed to create directory ' . $dst);
                $this->is_not_ready = true;
                return false;
            }
        }
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    if (@chmod($src . '/' . $file, 0777) === false) {
                        $this->logger->error('Failed to change permission of  folder ' . $src . '/' . $file);
                    }
                    if ($fileSystem->mkdir($dst . '/' . $file, 0777, true) === false) {
                        $this->logger->error('Failed to create directory ' . $src . '/' . $file);
                        $this->is_not_ready = true;
                        return false;
                    }
                    $this->recurse_copy($src . '/' . $file, $dst . '/' . $file);
                } else {    
                    copy($src . '/' . $file, $dst . '/' . $file);               
                    if (@chmod($dst . '/' . $file, 0777) === false) {
                        $file_path = $dst . '/' . $file;
                        $this->logger->error('Failed to change permission file ' . $file_path);
                    }
                   
                }
            }
        }
        closedir($dir);
        return true;
    }
    
    // replace token variable
    public function configSiteDB()
    { 
        $site_name = $this->site_name;
        $site_id = $this->site->id();
        $site_label = $this->site->label();
        $site_logo = "";
        $site_description = "";
        $site_theme = "";
        // Get the current user.
        $current_user = \Drupal::currentUser();
        $uid = $current_user->id();
        $email = $current_user->getEmail();
        $username = $current_user->getAccountName();

        if($this->site->st_logo->getValue('st_logo') && $this->site->st_logo->entity){
            $site_logo = $this->site->st_logo->entity ;
            $site_logo = file_create_url($site_logo->getFileUri());
        }
        if($this->site->st_description->getValue('st_description')){
            $site_description = $this->site->st_description->value;
        }
        if($this->site->st_theme->getValue('st_theme')){
            $site_theme = $this->site->st_theme->value;
        }
        $site_name_db = $site_name;
        $site_settings =  $this->root_path . "/sites/" . $site_name . "/settings.php";

        global $base_url;
        $parent_site = $base_url ;

        $content_settings = file_get_contents($site_settings, FILE_USE_INCLUDE_PATH);
        $content_settings = str_replace("{{site_name}}", $site_name_db, $content_settings);    
        $content_settings = str_replace("{{default_config}}", $site_name, $content_settings);
        $content_settings = str_replace("{{site_label}}", $site_label, $content_settings);
        $content_settings = str_replace("{{site_description}}", $site_description, $content_settings);
        $content_settings = str_replace("{{site_logo}}", $site_logo, $content_settings);
        $content_settings = str_replace("{{site_theme}}", $site_theme, $content_settings);  
        $content_settings = str_replace("{{parent_site}}", $parent_site, $content_settings);
       
        
        $content_settings = str_replace("{{site_id}}", $site_id, $content_settings);
        $content_settings = str_replace("{{uid}}", $uid, $content_settings);  
        $content_settings = str_replace("{{email}}", $email, $content_settings);
        $content_settings = str_replace("{{username}}", $username, $content_settings);
       
   
        $pass = $this->bdinfo['password'];
        $user = $this->bdinfo['user'];
        $content_settings = str_replace("{{user_database}}", $user, $content_settings);
        $content_settings = str_replace("{{pass_database}}", $pass, $content_settings);
        $content_settings = str_replace("{{name_database}}", $site_name, $content_settings);

        if (file_put_contents($site_settings, $content_settings) === false) {
            $this->logger->error('Failed to write file ' . $site_settings);
            $this->is_not_ready = true;
            return false;
        }
        return true;
    }
    public function getHostURL($url){
                    // Sample URL
            //$url = 'http://example.com:8080/path/to/resource';
            // Parse the URL
            $urlComponents = parse_url($url);
            $domaine_name = $urlComponents["host"];
            // Check if a port is specified in the URL
            if (isset($urlComponents['port'])) {
                $port = $urlComponents['port'];
                $domaine_name = $port.".".$domaine_name ;
            }
            return   $domaine_name  ; 
    }
    /** Add domain in sites.php */
    public function configSite()
    {
        $site_name = $this->site_name;
        $domaine_name = $this->getHostURL($this->domain);
        $site_settings =  $this->root_path . "/sites/sites.php";
        $content_settings = file_get_contents($site_settings, FILE_USE_INCLUDE_PATH);
        $new = PHP_EOL . "&#36;sites['" . $domaine_name . "'] =  '" . $site_name . "';";
        $new = html_entity_decode($new);
        if (strpos($content_settings, $new) !== false) {
            $message = "Site " . $domaine_name . " exist already in sites.php";
            $this->logger->error($message);
            return false;
        } else {
            $content_settings = $content_settings . $new;
            if (file_put_contents($site_settings, $content_settings) === false) {
                $this->logger->error('Failed to write file ' . $site_settings);
                $this->is_not_ready = true;
                return false;
            }
        }
        return true;
    }


    public function configDeleteSite()
    {
        $site_name = $this->site_name;
        $domaine_name = $this->getHostURL($this->domain);
        $site_settings =  $this->root_path . "/sites/sites.php";
        $original_string = file_get_contents($site_settings, FILE_USE_INCLUDE_PATH);

        $search = PHP_EOL . "&#36;sites['" . $domaine_name . "'] =  '" . $site_name . "';";
        $search = html_entity_decode($search); 
        $new_string = str_replace($search,"", $original_string);
        if (file_put_contents($site_settings, $new_string) === false) {
            \Drupal::logger('site_manager')->error('Failed to remove in sites.php ' . $site_settings);      
            return false;
        }
        \Drupal::logger('site_manager')->notice("Remove in sites.php ". $this->site_name." sucessfully ");
        return true;
    }
    public function deleteProcess()
    {

        $status2 = true;
        $status_last  = $this->isExistDatabaseOnly() ;
        $status1 = $this->deleteFolder();
        if($status_last){
            $this->deleteBD();
            $status_last = $this->isExistDatabaseOnly() ;
            if(!$status_last){
                \Drupal::logger('site_manager')->notice("Delete database ". $this->site_name." sucessfully ");
                $status2 = true ;
            }else{
                \Drupal::logger('site_manager')->error("Failed to delete database ". $this->site_name);
                $status2 = false ;
            }
        }

        $status3 = $this->configDeleteSite();


        return $status2 && $status1 && $status3 ;

    }
    public function deleteFolder()
    {
        $folder =  $this->root_path . "/sites/" . $this->site_name;
        if (is_dir($folder)) {
            $this->rrmdir($folder);
        }
        if (is_dir($folder)) {
            \Drupal::logger('site_manager')->error("Failed to delete folder ". $this->site_name);
            return false;
        } else {
            \Drupal::logger('site_manager')->notice("Delete Folder ". $this->site_name." sucessfully ");
            return true;
        }
    }
    public function deleteTheme()
    {
        $folder =  $this->root_path . "/themes/custom/" . $this->site_name;
        if (is_dir($folder)) {
            $this->rrmdir($folder);
        }
        if (is_dir($folder)) {
            return false;
        } else {
            return true;
        }
    }

    public function rrmdir($src)
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }
    public function deleteConfigSite()
    {
        $site_name = $this->site_name;
        $domaine_name = $this->domain;
        $site_settings =  $this->root_path . "/sites/sites.php";
        $content_settings = file_get_contents($site_settings, FILE_USE_INCLUDE_PATH);
        $new = PHP_EOL . "&#36;sites['" . $domaine_name . "'] =  '" . $site_name . "';";
        $new = html_entity_decode($new);
        if (strpos($content_settings, $new) !== false) {
            $content_settings = str_replace($new, "", $content_settings);
            if (file_put_contents($site_settings, $content_settings) === false) {
                $this->logger->error('Failed to write file ' . $site_settings);
                $this->is_not_ready = true;
                return false;
            }
        }
        return true;
    }
    public function deleteBD()
    {
        $site_name = $this->site_name;
        $host = $this->bdinfo['host'];
        $password = $this->bdinfo['password'];
        $user = $this->bdinfo['user'];
        /* Attempt MySQL server connection. Assuming you are running MySQL
        server with default setting (user 'root' with no password) */
        $link = mysqli_connect($host, $user, $password);
// Check connection
        if ($link === false) {
            die("ERROR: Could not connect. " . mysqli_connect_error());
        }
// Attempt create database query execution
        $sql = "DROP DATABASE " . $site_name;
        if (mysqli_query($link, $sql)) {
            $message = "Database deleted successfully";
            $messenger = \Drupal::messenger();
            $messenger->addMessage($message, 'status');
        } else {
            $message = "ERROR: Could not able to execute $sql. " . mysqli_error($link);
            $messenger = \Drupal::messenger();
            $messenger->addMessage($message, 'error');
        }
     // Close connection
        mysqli_close($link);
    }
    public  static function domain_build($site_name){
       $config = \Drupal::config('site_manager.settings');
       $domain_name = $config->get('domain_name');
       return "https://".$site_name.".".$domain_name ;
    }

    public static function exportFinishedCallback($success, $results, $operations) {
        if ($success) {
       
          $message = t('items successfully processed');
          \Drupal::messenger()->addMessage($message);
        }

        return new RedirectResponse(Url::fromRoute('<front>')->toString());   
      }
      public static function processBatchImportFake($input,&$context){
        $context['results']['site_id'] = $input['site_id']  ;
      }
        /**
     *
     */
    public static function processBatchImportSql($input,&$context){
        $newDB = $input['newDB'] ;
        $result = \Drupal\site_manager\SiteManager::import($newDB);
        if ( $result['return']  == 0) {
            \Drupal::logger('site_manager')->notice('Database'.$newDB.' inserted successfully\n ');
        } else {
            \Drupal::logger('site_manager')->error("Error Database'.$newDB.' inserting record: " .$result['output']  );
        }
        $context['results']['site_id'] = $input['site_id']  ;

    }  
    //check database and empty database = true 
    function isExistDatabase(){
        $newDB = $this->site_name;
        $host = $this->bdinfo['host'];
        $password = $this->bdinfo['password'];
        $user = $this->bdinfo['user']; 
        
        $conn =  new \MySQLi($host, $user, $password) ;

            // Check connection
            if ($conn->connect_error) {
                $this->logger->error("Connection failed: " . $conn->connect_error);
            }
            // Check if the database already exists
            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$newDB'";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                    $conn->select_db($newDB);
                    $tables = $conn->query("SHOW TABLES");    
                    if ($tables->num_rows == 0) {
                        return true ;
                    } 
            } 
            $conn->close(); 
            return false ;
          
    }
    
    function isExistDatabaseOnly(){
        $newDB = $this->site_name;
        $host = $this->bdinfo['host'];
        $password = $this->bdinfo['password'];
        $user = $this->bdinfo['user']; 
        
        $conn =  new \MySQLi($host, $user, $password) ;

            // Check connection
            if ($conn->connect_error) {
                $this->logger->error("Connection failed: " . $conn->connect_error);
            }
            // Check if the database already exists
            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$newDB'";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                 return true;
            } 
            $conn->close(); 
            return false ;
          
    }
    function createDatabase(){
        $newDB = $this->site_name;
        $host = $this->bdinfo['host'];
        $password = $this->bdinfo['password'];
        $user = $this->bdinfo['user']; 
        $conn =  new \MySQLi($host, $user, $password) ;
            // Check connection
            if ($conn->connect_error) {
                $this->logger->error("Connection failed: " . $conn->connect_error);
            }
            // Check if the database already exists
            $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$newDB'";
            $result = $conn->query($query);

            if ($result->num_rows > 0) {
                $this->logger->error("Database '$newDB' already exists.");
                // Handle the situation where the database already exists
            } else {
               // Create database
                $sql = "CREATE DATABASE ".$newDB;
                if ($conn->query($sql) === TRUE) {
                    $this->logger->info("Database ".$newDB." created successfully ");
                } else {
                    $this->logger->error("Error creating database: " . $conn->error);
                }
        }
        $conn->close();    
    }
    function splitSQLImportBatch($host,$user,$password,$newDB,$file, $delimiter = ';')
    {
        set_time_limit(0);
        $batch = [
            'title' => t('Website installing  ...'),
            'operations' => [],
            'init_message' => t('Starting ..'),
            'progress_message' => t('Processd @current out of @total.'),
            'error_message' => t('An error occurred during processing.'),
            'finished' => 'Drupal\site_manager\SiteManager::buildFinishedCallback',
        ];
      
        // if (is_file($file) === true)
        // {
        //     $file = fopen($file, 'r');
    
        //     if (is_resource($file) === true)
        //     {
        //         $query = array();
    
        //         while (feof($file) === false)
        //         {
        //             $query[] = fgets($file);
    
        //             if (preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1)
        //             {
        //                 $query = trim(implode('', $query));
        //                 $input['user'] =  $user ;
        //                 $input['host'] =  $host ;
        //                 $input['password'] =  $password ;
                         $input['newDB'] =  $newDB ;
        //                 $input['query'] =  $query ;
                         $input['site_id'] =  $this->site->id() ;
                        $batch['operations'][] =  [
                            '\Drupal\site_manager\SiteManager::processBatchImportFake',
                            [$input]  
                        ]; 
                        $batch['operations'][] =  [
                            '\Drupal\site_manager\SiteManager::processBatchImportSql',
                            [$input]  
                        ]; 
                    //     while (ob_get_level() > 0)
                    //     {
                    //         ob_end_flush();
                    //     }
    
                    //     flush();
                    // }
    
                //     if (is_string($query) === true)
                //     {
                //         $query = array();
                //     }
                // }
               
             //   $t = array_unique($status);
           //     if(!empty($t) && sizeof($t) == 1 && $t[0] == true){

            //    }
                batch_set($batch);
            //    return fclose($file);
            //}
       // }
        return false;
    }

    public function cloneDatabaseContentV2($newDB)
    {
        $host = $this->bdinfo['host'];
        $password = $this->bdinfo['password'];
        $user = $this->bdinfo['user']; 
        $module_handler = \Drupal::service('module_handler');
        $path = $module_handler->getModule('site_manager')->getPath();
        $file = DRUPAL_ROOT . "/" . $path . "/data/template.sql";
       $this->splitSQLImportBatch($host,$user,$password,$newDB,$file);
    }
    public static function buildFinishedCallback($success, $results, $operations)
    {
        if ($success) {
            $message = t('Build site done');
            $messenger = \Drupal::messenger();
            $messenger->addMessage($message);

        }
        $id = $results['site_id'];
        $external_url = "/node/".$id;
        return new RedirectResponse($external_url);
    }
    public function setThemeDefault( $template )
    {
        $theme = 'staydirect_'.$template;

        // Get the theme installer service.
        $themeInstaller = \Drupal::service('theme_installer');

        // Install the theme.
        $themeInstaller->install([$theme]);

        // Set the theme as the default.
        \Drupal::configFactory()->getEditable('system.theme')->set('default', $theme)->save();

        // Clear the theme cache.
        \Drupal::service('cache.bootstrap')->deleteAll();
        \Drupal::service('cache.render')->deleteAll();
    }

}
