<?php

namespace Drupal\views_custom\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to handle bulk download of media files.
 */
class DownloadMediaController extends ControllerBase {

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a DownloadMediaController object.
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, FileSystemInterface $fileSystem) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('file_system')
    );
  }

  /**
   * Generates and serves the ZIP file for selected media files.
   */
  public function download() {
    $tempStore = $this->tempStoreFactory->get('download_media_action');
    if (!$tempStore->has('file_paths')) {
        return new Response('TempStore key "file_paths" not found.', Response::HTTP_NOT_FOUND);
      }
      
      $file_paths = $tempStore->get('file_paths');
  
    if (empty($file_paths)) {
      return new Response('No files available for download.', Response::HTTP_NOT_FOUND);
    }
  
    // Define ZIP file location.
    $zip_filename = 'media_files_' . time() . '.zip';
    $zip_directory = 'private://bulk_downloads/';
    $zip_path = $zip_directory . $zip_filename;
    $this->fileSystem->prepareDirectory($zip_directory, FileSystemInterface::CREATE_DIRECTORY);
    $real_zip_path = $this->fileSystem->realpath($zip_path);
  
    // Create ZIP archive.
    $zip = new \ZipArchive();
    if ($zip->open($real_zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
      return new Response('Could not create ZIP file.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  
    foreach ($file_paths as $file_path) {
      if (file_exists($file_path) && is_readable($file_path)) {
        $zip->addFile($file_path, basename($file_path));
      }
    }
  
    $zip->close();
  
    // Ensure ZIP file exists.
    if (!file_exists($real_zip_path) || !is_readable($real_zip_path)) {
      return new Response('ZIP file not found or not readable.', Response::HTTP_NOT_FOUND);
    }
  
    \Drupal::logger('views_custom')->notice('ZIP file created at: ' . $real_zip_path);
  
    // Serve ZIP file as response.
    $response = new BinaryFileResponse($real_zip_path);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $zip_filename);
    $response->headers->set('Content-Type', 'application/zip');
    $response->headers->set('Content-Length', filesize($real_zip_path));
    $response->headers->set('Cache-Control', 'private, must-revalidate, max-age=0');
    $response->headers->set('Pragma', 'public');
  
    // Optionally clear temp storage.
    $tempStore->delete('file_paths');
  
    return $response;
  }
  

}