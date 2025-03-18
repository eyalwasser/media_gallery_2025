<?php

namespace Drupal\views_custom\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Provides a 'Bulk Download Media' action.
 *
 * @Action(
 *   id = "bulk_download_media_action",
 *   label = @Translation("Download bulk media"),
 *   type = "node"
 * )
 */
class DownloadMediaAction extends ActionBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Inject the messenger service using the container.
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // Log the entity type to check if it's a Node.
    \Drupal::logger('bulk_media_download')->notice('Entity type: ' . get_class($entity));
  
    // Check if the entity is a Node and the specific content type (bundle).
    if ($entity instanceof Node) {
      \Drupal::logger('bulk_media_download')->notice('Bundle: ' . $entity->bundle());
  
      // Check for the correct bundle, using "image_file" now
      if ($entity->bundle() === 'image_file') {
        \Drupal::logger('bulk_media_download')->notice('Processing Image File Node');
  
        // Load the referenced field image.
        if ($entity->hasField('field_image_ref')) {
          $field_image_ref = $entity->get('field_image_ref')->referencedEntities();
          \Drupal::logger('bulk_media_download')->notice('Field image ref count: ' . count($field_image_ref));
          
  
          // Now you can loop through and handle the media entities.
          foreach ($field_image_ref as $referenced_node) {
            // Log the class of the referenced node.
            \Drupal::logger('bulk_media_download')->notice('Referenced node class: ' . get_class($referenced_node));
            \Drupal::logger('bulk_media_download')->notice('Referenced node bundle: ' . $referenced_node->bundle());

            
            // Ensure that the referenced entity is a node of type 'item'
            if ($referenced_node instanceof Node && $referenced_node->bundle() === 'thumb') {
              \Drupal::logger('bulk_media_download')->notice('Referenced Item node found: ' . $referenced_node->id());

              // Check and log all fields in the referenced node.
$fields = $referenced_node->getFields();
foreach ($fields as $field_name => $field) {
  \Drupal::logger('bulk_media_download')->notice('Field name: ' . $field_name);
  
  // If the field is a media reference, check for media entities.
  if ($field instanceof \Drupal\Core\Field\FieldItemListInterface && $field->getFieldDefinition()->getType() === 'entity_reference') {
    $referenced_entities = $field->referencedEntities();
    foreach ($referenced_entities as $referenced_entity) {
      if ($referenced_entity instanceof \Drupal\media\Entity\Media) {
        \Drupal::logger('bulk_media_download')->notice('Media entity found: ' . $referenced_entity->id());
      }
    }
  }
}
          
              // Check if the referenced Item node has the media field (field_media_image).
              if ($referenced_node->hasField('field_media_image')) {
                $media_entity = $referenced_node->get('field_media_image')->entity;
          
                if ($media_entity instanceof Media) {
                  \Drupal::logger('bulk_media_download')->notice('Media entity found: ' . get_class($media_entity));
          
                  // Get the file entity from the media field.
                  if ($media_entity->hasField('field_media_image')) {
                    $image_file = $media_entity->get('field_media_image')->entity;
          
                    if ($image_file) {
                      \Drupal::logger('bulk_media_download')->notice('Image file found: ' . $image_file->getFileUri());
                      // Process or download the file here.
                    }
                    else {
                      \Drupal::logger('bulk_media_download')->notice('No file found in field_media_image');
                    }
                  }
                }
                else {
                  \Drupal::logger('bulk_media_download')->notice('The referenced media entity is not found or invalid.');
                }
              }
            }
            else {
              \Drupal::logger('bulk_media_download')->notice('Referenced node is not an Item node.');
            }
          }
          
          
        }
        else {
          \Drupal::logger('bulk_media_download')->notice('Field image ref does not exist');
        }
      }
      else {
        \Drupal::logger('bulk_media_download')->notice('Not an "image_file" bundle');
      }
    }
    else {
      \Drupal::logger('bulk_media_download')->notice('Entity is not a Node');
    }
  }
  
  

  /**
   * Downloads media files as a ZIP.
   *
   * @param array $media_files
   *   The media entities to download.
   */
  protected function downloadMediaFiles(array $media_files) {
    // Prepare the file paths for download. Here we assume the media is of type 'image'.
    $file_paths = [];
    foreach ($media_files as $media) {
      // Get the file URI of the referenced media item (assuming it's an image).
      $file = $media->get('field_media_image')->entity;
      if ($file) {
        $file_paths[] = $file->getFileUri();
      }
    }

    // If there are files to download, create a ZIP and provide a download link.
    if (!empty($file_paths)) {
      // Create a temporary directory for the ZIP file.
      $temp_dir = \Drupal::service('file_system')->tempDirectory();
      $zip_file_path = $temp_dir . '/bulk_media_download.zip';

      // Create a ZIP archive.
      $zip = new \ZipArchive();
      if ($zip->open($zip_file_path, \ZipArchive::CREATE) === TRUE) {
        foreach ($file_paths as $file_path) {
          // Add each file to the ZIP archive.
          $zip->addFile(\Drupal::service('file_system')->realpath($file_path), basename($file_path));
        }
        $zip->close();

        // Provide a download response for the ZIP file.
        $response = new BinaryFileResponse($zip_file_path);
        $response->setContentDisposition('attachment', 'bulk_media_download.zip');
        $response->setContentType('application/zip');

        // Send the response and exit the current request to trigger the download.
        return $response;
      } else {
        $this->messenger->addError($this->t('Failed to create ZIP file.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object = NULL, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Log username for debugging purposes
    \Drupal::logger('bulk_media_download')->notice('Checking access for user: ' . $account->getAccountName());

    // If the user is an admin, allow access
    if ($account->hasPermission('administer site configuration')) {
      \Drupal::logger('bulk_media_download')->notice('Access granted to admin user');
      return AccessResult::allowed();
    }

    // Check if the object is a Node of type 'item'
    if ($object instanceof Node && $object->bundle() === 'item') {
      \Drupal::logger('bulk_media_download')->notice('Access granted to node of type "item"');
      // Check if the user has permission to view the content.
      if ($account->hasPermission('view published content')) {
        return AccessResult::allowed();
      }
    }

    \Drupal::logger('bulk_media_download')->notice('Access denied');
    // If access is not granted, return forbidden
    return AccessResult::forbidden();
  }

}
