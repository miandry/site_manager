<?php

namespace Drupal\site_manager\Form;


use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Edit config variable form.
 */
class FileEditor extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'filereader_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config_name = '') {

      $query = $this->getRequest()->query ;
      $path_file = $query->get('path');

      if(!$path_file){
          return $form;
      }
      $path_file = DRUPAL_ROOT.'/'.$path_file;
      $output = file_get_contents($path_file, FILE_USE_INCLUDE_PATH);
      $form['path_file'] = array(
          '#type' => 'hidden',
          '#default_value' => $path_file
      );

      $form['editor_content'] = array(
          '#type' => 'container',

      );
      $form['editor_content']['content_file'] = array(
          '#type' => 'textarea',
          '#title' => $this->t('Content File'),
          '#attributes' => [ 'class' => ['json-editor-data']],
          '#default_value' => $output,
          '#rows' => 24,
          '#required' => TRUE,
      );
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Update File'),
        ];
        $form['actions']['cancel'] = array(
          '#type' => 'link',
          '#title' => $this->t('Back to list'),
          '#url' => $this->buildCancelLinkUrl(),
        );

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
      if($values['path_file'] && $values['content_file']){
          $path_file = $values['path_file'] ;
          $content_file = $values['content_file'] ;
          $status = \Drupal::service('filereader')->writeFileContent($content_file, trim($path_file));
          if($status){
              $this->messenger()->addMessage($this->t('File update was successfully'));
          }else{
              $this->messenger()->addError($this->t('Failed to update the file'));
          }

      }

  }

  /**
   * Builds the cancel link url for the form.
   *
   * @return Url
   *   Cancel url
   */
  private function buildCancelLinkUrl() {
    $query = $this->getRequest()->query;

    if ($query->has('destination')) {
      $path = $query->get('destination');
      $url = Url::fromUri('base:'.$path, array('absolute' => TRUE));
    }
    else {
      $url = Url::fromRoute('<current>');
    }

    return $url;
  }

}
