(function ($, Drupal, drupalSettings) {
  Drupal.TaxonomyManagerTermData = function (tid, tree) {
    // We change the hidden form element which then triggers the AJAX system.
    // eslint-disable-next-line no-jquery/no-val
    $('input[name=load-term-data]').val(tid).trigger('change');
  };
})(jQuery, Drupal, drupalSettings);
