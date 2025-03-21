<?php
namespace Drupal\views_custom\Commands;

use Drush\Commands\DrushCommands;
use Drupal\file\Entity\File;

/**
 * Defines Drush commands for views_custom module.
 */
class ViewsCustomDrushCommands extends DrushCommands {    

/**
 * Update file download count for a specific file or all files.
 *
 * @command views-custom:update-file-counts
 * @aliases vcu
 * @param int|null $fid (optional) File ID to process.
 * @usage drush views-custom:update-file-counts
 * @usage drush views-custom:update-file-counts 123
 */
public function updateFileCounts($fid = NULL) {
    if ($fid) {
      // Process a single file.
      $file = \Drupal\file\Entity\File::load($fid);
      if ($file) {
        views_custom_file_presave($file);
        $this->logger()->success("Updated file ID: $fid");
      } else {
        $this->logger()->error("File ID $fid not found.");
      }
    } else {
      // Process all files.
      $file_ids = \Drupal::entityQuery('file')->accessCheck(FALSE)->execute();
      if (empty($file_ids)) {
        $this->logger()->warning("No files found.");
        return;
      }
  
      foreach ($file_ids as $fid) {
        $file = \Drupal\file\Entity\File::load($fid);
        if ($file) {
          views_custom_file_presave($file);
          $this->logger()->success("Updated file ID: $fid");
        }
      }
  
      $this->logger()->success("All files have been processed.");
    }
  }
  
}
