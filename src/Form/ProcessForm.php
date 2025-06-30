<?php

namespace Drupal\site_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Class SiteSettingForm.
 */
class ProcessForm extends FormBase {



  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'site_manager_process';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
 
    $current_url = \Drupal::service('path.current')->getPath();
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath( $current_url);
    $site_new = \Drupal::request()->query->get('site_new');
    $form['nid'] = [
      '#type' => 'hidden',
      '#value' =>  $site_new,
    ];
    $form['alias'] = [
      '#type' => 'hidden',
      '#value' =>   $alias ,
    ];
    switch ($alias) {
      case "/app":  
        if($site_new){
          $form['field_profile'] = array(
            '#type' => 'radios',
            '#title' => t('Business Types'),
            '#options' => array(
              'booking' => t('Booking System'),
              'ecommerce' => t('E-commerce platform (Under construction)')
            ),
            '#default_value' => 'booking',
          );
          
        } else {
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return ;
        }
        break;
      case "/theme":
       
        if($site_new){
            // Select list (dropdown) element
            $form['st_theme'] = array(
              '#type' => 'select',
              '#title' => t('Themes'),
              '#options' => array(
                'template_1' => t('Azure Bliss'),
                'template_2' => t('Verdant Harmony'),
                'template_3' => t('Monochrome Elegance'),
              ),
              '#default_value' =>  'template_1',
              '#ajax' => array(
                'callback' => '_updateImage',
                'wrapper' => 'ajax-wrapper', // ID of the HTML element to replace with updated content.
                'event' => 'change', // You can use other effects like 'slide', 'none', etc.
              ),
  
            );

          $path = 'https://template.staydirect.cloud';
          $image = '/themes/custom/staydirect/theme1.jpg';
          $title = 'Azure Bliss';
          $text = 'Elevate your digital presence with Azure Bliss and let the calming waves of blue inspire confidence and connection.';  
          $html = _html_card_template($title,$text,$image , $path );
          $form['image_wrapper'] = [
              '#type' => 'container',
              '#attributes' => ['id' => 'ajax-wrapper'],
              'image' =>['#markup' =>  $html ],
          ];
        } else {
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return ;
        }

      break;
      case "/conditions":
        $term =  $this->_term();
        $form['html_text'] = [
          '#markup' => '<div class="block-title"> '. $term .' </div>',
        ];
        if($site_new){
          $form['is_agree'] = array(
            '#type' => 'checkbox',
            '#title' => t('I agree to the terms and conditions'),
            '#required' => TRUE, // Set the checkbox as required.
          );
        } else {
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return;
        }
         break;  
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
   
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $alias =  $values["alias"];
    switch ($alias) {
      case "/app":
        $nid =  $values["nid"];
        $node= \Drupal::entityTypeManager()->getStorage('node')->load($nid);   
         if(is_object( $node)){
          $node->field_profile->value = $values["field_profile"];
          $node->save();
         }
         $url  = "/theme?site_new=".$nid;
         $response = new RedirectResponse($url);
         $response->send();
         return ;
      break;   
      case "/theme":
        $nid =  $values["nid"];
        $node= \Drupal::entityTypeManager()->getStorage('node')->load($nid); 
        if(is_object( $node)){ 
         $node->st_theme->value = $values["st_theme"];
         $node->save();
        }
    
        $url  = "/conditions?site_new=".$nid;
        $response = new RedirectResponse($url);
        $response->send();
        return ;
      break;    
      case "/conditions":
        $nid =  $values["nid"];
        $node= \Drupal::entityTypeManager()->getStorage('node')->load($nid); 
        if(is_object( $node)){ 
         $node->field_is_agree->value = 1 ;
         $node->save();
        }

        
        $site = new \Drupal\site_manager\SiteManager($node);
        $site->createDatabase();

        //$site = new \Drupal\site_manager\SiteManager($node);
        //$site->process() ;
        $url  = "/process?site_new=".$nid;
        $response = new RedirectResponse($url);
        $response->send();
        return ;
      break;  
      case "/process":
        $nid =  $values["nid"];
        $node= \Drupal::entityTypeManager()->getStorage('node')->load($nid); 
        if(is_object($node)){ 
         $node->field_status->value = 'In_process' ;
         $node->save();
        }else{
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return ;
        }
        $site = new \Drupal\site_manager\SiteManager($node);
        $status = $site->isExistDatabase();
        if($status){

          $site->process();
          $url  = "/node/".$nid;
          $response = new RedirectResponse($url);
          $response->send();


        }else{
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return ;
        }
      break;   
      
    }
  }
  function _term(){
    $service = \Drupal::service('templating.manager');
    $template= $service->getTemplatingByTitle("condition_term");
    if(is_object($template)){
      return $template->field_templating_html->value;
    }else{
        return '
        <ul class=condition_term>
            <li><strong>Booking Process:</strong>
                <ul>
                    <li>a. To make a reservation, users must provide accurate and complete information.</li>
                    <li>b. Bookings are confirmed upon receipt of payment or as otherwise specified in the system.</li>
                </ul>
            </li>
            <li><strong>Payment:</strong>
                <ul>
                    <li>a. Payment terms, including amounts and methods, are specified during the booking process.</li>
                    <li>b. Payments are non-refundable unless otherwise stated in the booking confirmation.</li>
                </ul>
            </li>
        </ul>
        <div>
            By using our booking system, you agree to abide by these terms and conditions. 
            If you do not agree with any part of these terms, please refrain from using our booking services.
        </div>
      ';
    }
  }
}
