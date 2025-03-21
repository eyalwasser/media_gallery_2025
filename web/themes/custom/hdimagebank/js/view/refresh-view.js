/** on hold feautre. */
(function ($, Drupal, once) {
  Drupal.behaviors.refreshViewAfterDownload = {
    attach: function (context, settings) {
      console.log(once('refresh-view-download', '.trigger-content-refresh-view', context));
      
      once('refresh-view-download', '.trigger-content-refresh-view', context).forEach((element) => {
        $(element).on('click', function (e) {
          // Wait for the download to complete before refreshing the view
          setTimeout(function () {
            $('.view-id-image_file_list_content').trigger('RefreshView');
          }, 3000); // Adjust delay if needed
        });
      });
    }
  };
})(jQuery, Drupal, once);