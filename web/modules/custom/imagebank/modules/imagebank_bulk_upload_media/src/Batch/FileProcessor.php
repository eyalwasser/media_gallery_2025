<?php

namespace Drupal\imagebank_bulk_upload_media\Batch;

use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;
use Drupal\node\Entity\Node;

/**
 * Class FileProcessor to handle batch file processing.
 */
class FileProcessor {

  /**
   * Processes each file in the batch.
   */
  public static function processFile($file_info, $node_id, &$context) {
    $temp_path = $file_info['path'];
    $filename = $file_info['filename'];
    $token = $file_info['token'];
    $thumb_id = $file_info['thumb'];

    // Move file to the final destination.
    $file_system = \Drupal::service('file_system');
    $destination_directory = 'public://uploads/';

    // Ensure the directory exists.
    if (!$file_system->prepareDirectory($destination_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        \Drupal::logger('imagebank_bulk_upload_media')->error('Failed to create or set permissions for directory: @dir', ['@dir' => $destination_directory]);
        return;
    }
    // Define the final file destination.
    $destination = $destination_directory . '/' . $filename;
    $destination = $file_system->copy($temp_path, $destination, FileExists::Replace);
    if ($destination) {
      // Create file entity.
      $file = File::create([
        'uri' => $destination,
        'status' => 1,
        'uid' => \Drupal::currentUser()->id(),
      ]);
      // Set as permanent.
      $file->setPermanent();
      $file->save();

    // Create node entity.
    $node = Node::create([
    'type' => 'image_file', // Change this to your content type
    'title' => self::convertToken($file, $token),
    'status' => 1,
    'field_file' => [ // Change this to your field name
        'target_id' => $file->id(),
        'display' => 1,
    ],
    'field_image_ref' => $thumb_id,
    ]);
    $node->save();

      $context['results']['processed_files'][] = $filename;
    } else {
      $context['results']['errors'][] = "Failed to process file: $filename";
    }
  }

  /**
   * Finish callback for batch processing.
   */
  public static function finishBatch($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addStatus(t('Files processing completed successfully.'));
    } else {
      \Drupal::messenger()->addError(t('Some files failed to process.'));
    }
  }

/**
 * Converts a token for a given file.
 *
 * @param \Drupal\file\FileInterface $file
 *   The file entity to process.
 * @param string $token
 *   The token to convert.
 *
 * @return mixed
 *   The result of the token conversion.
 */
  public static function convertToken($file, $token) {
    // Get the Token service.
    $token_service = \Drupal::token();
    
    // Define the token type and replacement data.
    $token_data = ['file' => $file];
    $options = ['clear' => TRUE];
  
    // Replace the token.
    $token_value = $token_service->replace($token, $token_data, $options);
  
    return $token_value;
  }
}