<?php

namespace Drupal\site_manager\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class DefaultController.
 */
class DefaultController extends ControllerBase {

  /**
   * List.
   *
   * @return string
   *   Return Hello string.
   */
  public function list() {
        
    if (is_file(DRUPAL_ROOT . '/sites/sites.php')) {
      include DRUPAL_ROOT . '/sites/sites.php';
    }
           //  ***** TABLE **** //
           $output = [];
           $header = [
               'id' => t('Node id'),
               'site_name' => t('Site name'),
               'domain' => t('Domain name'),
               'size' => t('Folder size'),
               'db_size' => t('Database size'),
               'operation' => t('Actions')
           ];
           $form['table'] = array(
            '#type' => 'table',
            '#weight' => 999,
            '#header' => $header,
            '#empty' => $this->t('No variables found')
        );
        $i = 0 ;
        $service_base = \Drupal::service('site_manager.base') ;
        foreach($sites as $key => $item){
          $nid = $service_base->load_site_by_name($item);
          $form['table'][$i]['id'] = [
            '#id' =>   $nid ,
          ];
            $form['table'][$i]['domain'] = [
              '#plain_text' => $item,
            ];
            $form['table'][$i]['site_name'] = [
                '#plain_text' => $key,
            ];
            $dir = DRUPAL_ROOT."/sites/".$item."/files" ;
           // var_dump( $dir);
            $size  = \Drupal\site_manager\SiteManager::folderSize($dir);
            $form['table'][$i]['size'] = [
              '#plain_text' => $size,
            ];
            $t = $service_base->db_size_info($item);
            $db_size =  ($t['size'].' '.$t['type']);
            $form['table'][$i]['db_size'] = [
                '#plain_text' => $db_size,
            ];
            db_set_active('default');
            $form['table'][$i]['operation'] = $service_base->operation($key,$item);
            $i++ ;
        }    
    return $form ;
  }

  

}
