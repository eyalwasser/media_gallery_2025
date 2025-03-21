<?php

declare(strict_types=1);

namespace Drupal\imagebank_bulk_upload_media\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Imagebank bulk upload media form.
 */
final class MediaBulkUploadForm extends FormBase {
  
  protected $routeMatch;

  /** @var \Drupal\core\Entity\Node $node */
  protected $node;

  /**
   * Dependency injection for RouteMatchInterface.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
    $this->node = $this->routeMatch->getParameter('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'imagebank_bulk_upload_media_media_bulk_upload';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $node = $this->node;
    $form['import_information'] = [
      '#type' => 'fieldset',
    ];
    
    $form['import_information']['bundle_details'] = [
      '#markup' => $this->t('Bundle used for generating the entities: <strong>@bundle_label (@bundle_machine_name)</strong><br>
      Mediafield used for uploading your media: <strong>test (field_file)</strong>', [
        '@bundle_label' => 'Image file',
        '@bundle_machine_name' => 'image_file',
      ]),
    ];
    
    $form['title_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Title Settings'),
    ];
    
    $form['title_fieldset']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#default_value' => '[file:name]',
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['file'],
    ];

    // Token Help Link
    $filename_token = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['file'],
      '#dialog' => TRUE,
      '#click_insert' => TRUE,
      '#show_restricted' => TRUE,
      '#group' => 'file',
    ];
    $form['title_fieldset']['token_help'] = $filename_token;

    // Attach required libraries
  
    $form['dropzonejs'] = [
      '#title' => $this->t('Bulk Media Upload '),
      '#type' => 'dropzonejs',
      '#required' => TRUE,
      '#dropzone_description' => $this->t('Drag files here.'),
      '#description' => $this->t('Allowed file types: <strong>psd gif jpg fla zip png pdf doc docx ai indd swf mp4 avi wmv pt mov mp3 txt idml wav srt eps pptx xls.</strong>'),
      '#max_filesize' => '1M',
      '#extensions' => 'psd gif jpg fla zip png pdf doc docx ai indd swf mp4 avi wmv pt mov mp3 txt idml wav srt eps pptx xls',
    ];

    $form['thumb'] = [
      '#type' => 'entity_autocomplete',
      '#title' => t('Thumb'),
      '#target_type' => $node->getEntityTypeId(), // Reference to Node entity
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => [$node->bundle()], // Restrict to a specific content type
      ],
      '#size' => 40,
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => isset($node) ? $node : NULL,
      '#required' => FALSE,
      '#attributes' => ['disabled' => 'disabled'], // Make the field disabled
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Create media'),
      ],
    ];

    $form['actions']['go_back'] = [
      '#type' => 'link',
      '#title' => $this->t('Go back'),
      '#url' => Url::fromRoute('entity.node.edit_form', ['node' => $this->node->id()]),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if (mb_strlen($form_state->getValue('message')) < 10) {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('Message should be at least 10 characters.'),
    //     );
    //   }
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  // public function submitForm(array &$form, FormStateInterface $form_state): void {
  //   $node = $this->node;
  //   if ($node) {
  //     $url = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
  //     $form_state->setRedirectUrl($url);
  //   }
  // }

  /**
 * {@inheritdoc}
 */
public function submitForm(array &$form, FormStateInterface $form_state): void {
  $uploaded_files = $form_state->getValue('dropzonejs');

  if (!empty($uploaded_files['uploaded_files'])) {
    $operations = [];

    foreach ($uploaded_files['uploaded_files'] as $file_info) {
      $file_info['token'] = $form_state->getValue('title');
      $file_info['thumb'] = $form_state->getValue('thumb');
      $operations[] = [
        ['\Drupal\imagebank_bulk_upload_media\Batch\FileProcessor', 'processFile'], 
        [$file_info, $this->node->id()]
      ];
    }

    $batch = [
      'title' => $this->t('Processing uploaded files...'),
      'operations' => $operations,
      'init_message' => $this->t('Starting file processing...'),
      'progress_message' => $this->t('Processing file @current of @total.'),
      'error_message' => $this->t('An error occurred during processing.'),
      'finished' => ['\Drupal\imagebank_bulk_upload_media\Batch\FileProcessor', 'finishBatch'],
    ];

    batch_set($batch);
    $node = $this->node;
    if ($node) {
      $url = Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]);
      $form_state->setRedirectUrl($url);
    }
  }
}

}
