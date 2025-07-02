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
    $temp_store_factory = \Drupal::service('session_based_temp_store');
    $uid = \Drupal::currentUser()->id();// User ID
    $temp_store = $temp_store_factory->get($uid.'_build_site', 106400); 
    $data = $temp_store->get('data');

    $form['alias'] = [
      '#type' => 'hidden',
      '#value' =>   $alias ,
    ];


    switch ($alias) {
      case "/app":  
        if($data && $data["title"] && $data["site_label"] && $data["created"]){
          $form['field_profile'] = array(
            '#type' => 'radios',
            '#title' => t('Business Types'),
            '#options' => array(
              'booking' => t('Booking System'),
              'ecommerce' => t('E-commerce platform (Under construction)')
            ),
            '#default_value' => 'booking',
          );
          $form['actions']['back'] = [
            '#type' => 'submit',
            '#value' => t('Back'),
            '#submit' => ['::customRedirectSubmit'],
            '#limit_validation_errors' => [], // Skip validation
            '#attributes' => [
              'class' => ['button'],
            ],
            '#weight' => 100,
          ];
          $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save and continue'),
          ];
          
        } else {
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return ;
        }
        break;
      case "/theme":
          
        if($data && $data["title"] && $data["site_label"] && $data["created"] 
        && $data["field_profile"] ){
            $request = \Drupal::request();
            $theme_select = $request->query->get('theme_select');
            $op = $request->query->get('back');
            if($op  && $op == "Back"){
              $url  = "/app";
              $response = new RedirectResponse($url);
              $response->send();
            }
            if($theme_select){
              $data["st_theme"] = $theme_select ;
              $temp_store->set('data',$data);
              $url  = "/conditions";
              $response = new RedirectResponse($url);
              $response->send();
            }else{
              return [];
            }

        }else{
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
        if($data && $data["title"] && $data["site_label"] && $data["created"] 
        && $data["field_profile"] && $data["st_theme"] ){
          $form['is_agree'] = array(
            '#type' => 'checkbox',
            '#title' => t('I agree to the terms and conditions'),
            '#required' => TRUE, // Set the checkbox as required.
          );
          $form['actions']['back'] = [
            '#type' => 'submit',
            '#value' => t('Back'),
            '#submit' => ['::customRedirectSubmit'],
            '#limit_validation_errors' => [], // Skip validation
            '#attributes' => [
              'class' => ['button'],
            ],
            '#weight' => 100,
          ];
          $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit and Start process'),
          ];
        } else {
          $url  = "/order";
          $response = new RedirectResponse($url);
          $response->send();
          return;
        }
         break; 
         case "/process": 
            $site_new = \Drupal::request()->query->get('site_new');
            $form['nid'] = [
              '#type' => 'hidden',
              '#value' =>  $site_new,
            ];
            $form['submit'] = [
              '#type' => 'submit',
              '#value' => $this->t('Submit and Start process'),
            ];
         break;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
   
  }
  public function customRedirectSubmit(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $current_url = \Drupal::service('path.current')->getPath();
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath( $current_url);
    $url_string = "/order"; 
    switch ($alias) {
      case "/app":  
      $url_string = "/order";
      break;  
      case "/conditions":  
         $url_string = "/theme";   
      break; 
   }
   $url = \Drupal\Core\Url::fromUserInput($url_string); // ← Your target path
   $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $alias =  $values["alias"];
    $temp_store_factory = \Drupal::service('session_based_temp_store');
    $uid = \Drupal::currentUser()->id();// User ID
    $temp_store = $temp_store_factory->get($uid.'_build_site', 106400); 
    $data = $temp_store->get('data');
    switch ($alias) {
      case "/app":
        $data["field_profile"] =  $values["field_profile"];
        $temp_store->set('data',$data);
        $url  = "/theme";
        $response = new RedirectResponse($url);
        $response->send();
        return ;
      break;      
      case "/conditions":
        $url  = "/process";
        $response = new RedirectResponse($url);
        $response->send();
        return;
      break;  
      case "/process":
        $bundle = "site";
        $entity_type = "node";
        $data["field_status"] = 'In_process' ;
        $node = \Drupal::service('crud')->save($entity_type, $bundle, $data);
        $node->save();
        $site = new \Drupal\site_manager\SiteManager($node);
        $site->createDatabase();
        $nid = $node->id();
        $node= \Drupal::entityTypeManager()->getStorage('node')->load($nid); 
        if(is_object($node)){ 
            $site = new \Drupal\site_manager\SiteManager($node);
            $status = $site->isExistDatabase();
            if($status){
              $site->process();
              $url  = "/node/".$nid;
              $response = new RedirectResponse($url);
              $response->send();
              return ;
            }
        }

        $url  = "/order";
        $response = new RedirectResponse($url);
        $response->send();
        return ;
      
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
