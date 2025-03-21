<?php

namespace Drupal\views_custom\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a Views Bulk Operations action to download media files attached to selected nodes as a ZIP.
 *
 * @Action(
 *   id = "bulk_media_download",
 *   label = @Translation("Download media files as ZIP"),
 *   type = "node",
 *   confirm = TRUE
 * )
 */
class BulkMediaDownload extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    // No need to execute on a single node.
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $file_system = \Drupal::service('file_system');
    $temp_dir = DRUPAL_ROOT . '/zip_files';  // Set to web/zip_files directory 

    $zip_file_path = $temp_dir . '/media_files_' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
      dd('ggg');
      \Drupal::logger('custom_module')->debug('Could not create ZIP file.');
      return;
    }


    foreach ($entities as $entity) {
     
      if ($entity instanceof \Drupal\node\Entity\Node) {
        if ($entity->hasField('field_file') && !$entity->get('field_file')->isEmpty()) {
          \Drupal::logger('custom_module')->debug('Processing entity ID: ' . $entity->id());
          foreach ($entity->get('field_file')->referencedEntities() as $media) {
            // Ensure $media is a File entity
            if (!$media instanceof \Drupal\file\Entity\File) {
                \Drupal::logger('custom_module')->warning('Skipping entity ID ' . $media->id() . ' because it is not a File entity. It is a ' . get_class($media));
                continue;
            }
        
            $file_uri = $media->getFileUri();
            $correct_uri = str_replace('public://files/', '/sites/default/files/files/', $file_uri); // Adjust if needed
            $file_path = $correct_uri;
            // dd($correct_uri);
            
            if (file_exists($file_path)) {
              // $zip->addFile($file_path, basename($file_path));
              \Drupal::logger('custom_module')->notice("Added to ZIP: $file_path");
            } else {
                \Drupal::logger('custom_module')->error('Corrected file path NOT found: ' . $file_path);
            }
            
            
        }
        
        
        
        }
      }
    }
    
    

    $zip->close();
//     // Check if the ZIP file was created.
if (!file_exists($zip_file_path)) {
  \Drupal::messenger()->addError(t('ZIP file creation failed. Please check file permissions.'));
  return;
}

    // Serve ZIP file to user.
    $response = new BinaryFileResponse($zip_file_path);
    $response->setContentDisposition('attachment', 'media_files.zip');
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowedIfHasPermission($account, 'administer files');
    return $return_as_object ? $access : $access->isAllowed();
  }
}
