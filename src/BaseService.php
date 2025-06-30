<?php

namespace Drupal\site_manager;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use Drupal\Core\Database\DatabaseException;

/**
* Class BaseService.
*/

class BaseService {

    /**
    * Constructs a new BaseService object.
    */

    public function __construct() {
    }

    function load_site_by_name( $name ) {

        $rs = \Drupal::service( 'entity_type.manager' )->getStorage( 'node' )->loadByProperties(
            [
                'site_name' => $name,
                'type' => 'site'
            ]
        );
        return end( array_keys( $rs ) );
    }

    function db_size_format( $dbsize ) {

        $bytes = array( 'KB', 'KB', 'MB', 'GB', 'TB' );

        if ( $dbsize < 1024 ) $dbsize = 1;

        for ( $i = 0; $dbsize > 1024; $i++ ) $dbsize /= 1024;

        $db_size_info[ 'size' ] = ceil( $dbsize );

        $db_size_info[ 'type' ] = $bytes[ $i ];

        return $db_size_info;

    }

    function db_size_info( $site_name ) {

        $dbssize = 0;
        // if ( is_file( DRUPAL_ROOT . '/sites/'.$site_name.'/settings.php' ) ) {
        //     $site_path = null ;
        //     $app_root = null ;
        //     include DRUPAL_ROOT . '/sites/'.$site_name.'/settings.php';
        //     $con = \Drupal\Core\Database\Database::getConnection();
        //     $other_database = $databases[ 'default' ][ 'default' ];
        //     \Drupal\Core\Database\Database::addConnectionInfo( $site_name, 'default', $other_database );
        //     db_set_active( $site_name );

        //     // Database size = table size + index size:
        //     $rows = db_query( 'SHOW TABLE STATUS' )->fetchAll();

        //     $dbssize = 0;

        //     foreach ( $rows as $row ) {

        //       $dbssize += $row->Data_length + $row->Index_length ;

        //     }

        //     db_set_active( 'default' );
        // }

        return $this->db_size_format( $dbssize );

    }

    function operation( $key, $item ) {
        $operations = [];
        $operations[ 'edit_settings' ] = array(
            'title' => 'Edit settings',
            'url' => Url::fromRoute( '<front>', array( 'import' => 'ok' ) )
        );
        return array( 'data' => array( '#type' => 'operations', '#links' => $operations ) ) ;
    }


    public function selectUserByName( $username, $externalbd = 'default' ) {
        $external_db = Database::getConnection( 'default', $externalbd );
        $query = $external_db->select( 'users_field_data', 'u' )
        ->fields( 'u', [ 'name' ] )
        ->condition( 'u.name', $username )
        ->execute();
        $results = $query->fetchField();
        return $results;
    }

    public function selectUsers( $externalbd = 'default' ) {
        $external_db = Database::getConnection( 'default', $externalbd );
        $query = $external_db->select( 'users_field_data', 'u' )
        ->fields( 'u', [ 'uid', 'name', 'mail' ] )
        ->condition( 'u.name', 'admin' )
        ->execute();
        $results = $query->fetchAll();
        return $results;
    }

    public function loadJSONConfig() {
        $file_path = DRUPAL_ROOT.'/sites/staydirect/files/sites.json';
        if ( file_exists( $file_path ) ) {
            $json_content = file_get_contents( $real_path );
            return json_decode( $json_content, TRUE );
        } else {
            return $this->rebuildJSONConfig();
        }
    }
 
    function createFileWithPath($path, $filename) {
        // Combine directory path and filename to form the full file path
        $fullPath = rtrim($path, '/') . '/' . $filename;
    
        // Check if the directory path exists
        if (!file_exists($path)) {
            // Create the directory recursively
            mkdir($path, 0777, true);
            echo "Directory created: $path\n";
        }
    
        // Check if the file already exists
        if (!file_exists($fullPath)) {
            // Attempt to create the file
            $file = fopen($fullPath, 'w');
    
            // Check if the file was created successfully
            if ($file === false) {
                echo "Failed to create the file: $fullPath\n";
            } else {
                echo "File created: $fullPath\n";
                fclose($file); // Close the file handler
            }
        } else {
            echo "File already exists: $fullPath\n";
        }
    }

    public function buildJsonSite($nid){
        $parser = \Drupal::service( 'entity_parser.manager' );
        $site = $parser->node_parser($nid);
        $value = [
            'username' =>  $site[ 'uid' ][ 'name' ] ,
            'uid' => $site[ 'uid' ][ 'uid' ] ,
            'site_name' => $site[ 'site_name' ],
            'status' => $site['status']
        ];
        $path = DRUPAL_ROOT.'/sites/default/files/sites/' ;
        $file = $site[ 'site_name' ].'.json';
        $this->createFileWithPath($path,$file);
        $data[ 'value' ] = $value;
        $file_path = $path.$file ;

        $json_content = json_encode( $data, JSON_PRETTY_PRINT );
        if ( $json_content === false ) {
            $message = 'Error encoding JSON: ' . json_last_error_msg();
            \Drupal::logger( 'mz_staydirect')->error( $message );

            return false ;
        }
        $result = file_put_contents( $file_path, $json_content );
        if ( $result === false ) {
            $message = 'Error writing to the JSON file value.';
            \Drupal::logger( 'mz_staydirect' )->error( $message );

            return false ;
        }
        if ( file_exists( $file_path ) ) {
            // Get the contents of the JSON file.
            $json_content = file_get_contents( $file_path );
            // Decode the JSON content into a PHP array.
            $data = json_decode( $json_content, TRUE );
            return  $data ;
        } else {
            \Drupal::logger( 'mz_staydirect' )->error( 'Failed generate JsonConfig Value in '.$file_path );

            return false ;

        }


    }
    public function rebuildJSONConfig() {

        $file_path = DRUPAL_ROOT.'/sites/default/files/sites.json';
        $query = \Drupal::entityQuery( 'node' )
        ->condition( 'type', 'site' )
        ->sort( 'created', 'DESC' );
        $nids = $query->execute();
        $sites = [];
        if ( !empty( $nids ) ) {
            $parser = \Drupal::service( 'entity_parser.manager' );
            foreach ( $nids as $nid ) {
                $site = $parser->node_parser( $nid );
                $sites[] = [
                    'username' =>  $site[ 'uid' ][ 'name' ] ,
                    'uid' => $site[ 'uid' ][ 'uid' ] ,
                    'site_name' => $site[ 'site_name' ],
                    'status' => $site['status']
                ];
            }
        }
        $data[ 'sites' ] =  $sites;
        $json_content = json_encode( $data, JSON_PRETTY_PRINT );
        if ( $json_content === false ) {
            $message = 'Error encoding JSON: ' . json_last_error_msg();
            \Drupal::logger( 'mz_staydirect')->error( $message );

            return false ;
        }
        $result = file_put_contents( $file_path, $json_content );
        if ( $result === false ) {
            $message = 'Error writing to the JSON file.';
            \Drupal::logger( 'mz_staydirect' )->error( $message );

            return false ;
        }
        if ( file_exists( $file_path ) ) {
            // Get the contents of the JSON file.
            $json_content = file_get_contents( $file_path );
            // Decode the JSON content into a PHP array.
            $data = json_decode( $json_content, TRUE );
            return  $data ;
        } else {
            \Drupal::logger( 'mz_staydirect' )->error( 'Failed generate JsonConfig in '.$file_path );

            return false ;

        }

    }

    function getMaintenanceMode( $externalbd ) {

        $connection = Database::getConnection( 'default', $externalbd );
        // Check if the maintenance mode key exists in the external Drupal database.
        $result = $connection->select( 'key_value', 'kv' )
        ->fields( 'kv', [ 'value' ] )
        ->condition( 'kv.name', 'system.maintenance_mode' )
        ->execute()
        ->fetchField();
        return !empty( $result ) && unserialize( $result ) == 1 ? TRUE : FALSE;

    }
    // $externalbd = 'external_SITE_NAME'
    //  $service_base = \Drupal::service( 'site_manager.base' ) ;
    //  $service_base->setMaintenanceMode( 'external_stjr', 0 );

    function setMaintenanceMode( $externalbd, $status = 1 ) {
        // $connection = Database::getConnection( 'default', $externalbd );
        // Check if the maintenance mode key exists in the external Drupal database.
        // $maintenance_value = $status ? 1 : 0;

        // $result = $connection->select( 'key_value', 'kv' )
        // ->fields( 'kv', [ 'value' ] )
        // ->condition( 'kv.name', 'system.maintenance_mode' )
        // ->execute()
        // ->fetchField();
        // if ( $result ) {
        //     $connection->update( 'key_value' )
        //     ->fields( [ 'value' => serialize( $maintenance_value ) ] )  // Serialize the value ( 1 or 0 ).
        //     ->condition( 'collection', 'state' )
        //     ->condition( 'name', 'system.maintenance_mode' )
        //     ->execute();
        // } else {
        //     $connection->insert( 'key_value' )
        //     ->fields( [
        //         'collection' => 'state',
        //         'name' => 'system.maintenance_mode',
        //         'value' => serialize( $maintenance_value ),
        // ] )
        //     ->execute();
        // }

        // $cache_tables = [
        //     'cache_bootstrap',
        //     'cache_config',
        //     'cache_container',
        //     'cache_data',
        //     'cache_default',
        //     'cache_discovery',
        //     'cache_dynamic_page_cache',
        //     'cache_entity',
        //     'cache_menu',
        //     'cache_page',
        //     'cache_render',
        //     'cache_toolbar',
        // ];

        // // Iterate over each cache table and truncate it.
        // foreach ( $cache_tables as $table ) {
        //     try {
        //         // Truncate the cache table.
        //         $connection->truncate( $table )->execute();
        //         \Drupal::messenger()->addMessage( t( 'Cache table @table cleared.', [ '@table' => $table ] ) );
        //     } catch ( \Exception $e ) {
        //         \Drupal::messenger()->addError( t( 'Failed to clear cache table @table: @error', [
        //             '@table' => $table,
        //             '@error' => $e->getMessage(),
        // ] ) );
        //     }
        // }

    }
    function isExistSiteNameInDatabaseSettings($site_name){
        $file_path = DRUPAL_ROOT.'/sites/default/files/sites.json';
        $json_data = get_json_file_content($file_path);
        $external_dbs = $json_data["sites"];
        $found = false;
        if ($external_dbs) {
            foreach ( $external_dbs as $site) {
                if ($site['site_name'] === $site_name) {
                    $found = true;
                    break;
                }
            }
        }
        return $found ;
    }
    function connectToDatabase($site_name) {
        $is_exist = $this->isExistSiteNameInDatabaseSettings($site_name);
        if( $is_exist == false) { 
            \Drupal::messenger()->addMessage("Site name is not in sites.json ", 'error');
            return false ;
        } 
        $externalbd = "external_".$site_name ;
        try {
            return  Database::getConnection( 'default', $externalbd );
            // Proceed with using the $connection as needed
        } catch (DatabaseException $e) {
            \Drupal::messenger()->addMessage("Database connection error: " . $e->getMessage(), 'error');
            // Handle the exception as required for your application context
        }
        return false ;
    }
    
    function updateUserEmailByUsername($site_name , $username,$new_email) {
        $connection = $this->connectToDatabase($site_name);
        if(is_object($connection)){
                // Update query
                $num_updated = $connection->update('users_field_data')
                ->fields(['mail' => $new_email])
                ->condition('name', $username)
                ->execute();
            if ($num_updated > 0) {
                \Drupal::messenger()->addMessage("Email updated successfully for ".$username . " in ". $site_name );
            } else {
                \Drupal::messenger()->addMessage("No such user found", 'error');
            }
        }
   
      }
      
    function getConfigInDatabase( $externalbd, $config_name ) {
        $connection = Database::getConnection( 'default', $externalbd );

        $query = $connection->select( 'config', 'c' )
        ->fields( 'c', [ 'data' ] )
        ->condition( 'c.name', $config_name )
        ->execute()
        ->fetchField();
        if ( $query ) {
            return unserialize( $query );
        }
        return false;
    }

    function checkDrupalRequiredTables( $externalbd ) {
        $required_tables = [
            'config', 'key_value', 'users', 'users_field_data', 'role', 'user__roles',
            'node', 'node_field_data', 'taxonomy_term_data', 'file_managed', 'system',
            'field_config', 'field_config_instance', 'cache_default', 'cache_config',
        ];
        $missing_tables = [];
        try {
            // Get the connection to the target database.
            $connection = Database::getConnection( 'default', $externalbd );
            $schema = $connection->schema();

            // Check if each required table exists.
            foreach ( $required_tables as $table ) {
                if ( !$schema->tableExists( $table ) ) {
                    $missing_tables[] = $table;
                }
            }
            // Return the results.
            if ( empty( $missing_tables ) ) {
                return true;
            }

        } catch ( \Exception $e ) {
            $message = 'Error connecting to the database: ' . $e->getMessage() ;
            \Drupal::logger( 'site_manager' )->error( $message );

        }
        return false ;
    }
    


}
