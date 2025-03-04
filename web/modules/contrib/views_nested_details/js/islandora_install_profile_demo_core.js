(function ($, Drupal) {
  Drupal.behaviors.islandora_install_profile_demo_core = {
    attach: function (context, settings) {
      function unserialize(data) {
        data = data.split('&');
        var response = {};
        for (var k in data){
          var newData = data[k].split('=');
          response[newData[0]] = newData[1];
        }
        return response;
      }

      $('select[name="sort_order"]').once('islandora_install_profile_demo_core_actions').change(function () {
        // Grab ALL elements from the URL and re-use them with this one request
        let existing_parameters = unserialize(location.search.slice(1));
        let selected_option = 'select[name="sort_order"] option[value="' + $(this).val() + '"]';
        existing_parameters.sort_order = $(selected_option).data('sort_order');
        existing_parameters.sort_by = $(selected_option).data('sort_by');
        let paramData = $.param(existing_parameters);
        // Redirect to perform a new search, removing old URL query parameters
        location.href = window.location.href.split("?")[0]  + '?' + paramData;
      });

      // Reference the toggle link
      var xa = document.getElementsByClassName('expAll');
      var ca = document.getElementsByClassName('collAll');

      for(var i=0;i < xa.length;i++) {
        // Register link on click event
        xa.item(i).addEventListener('click', function(e) {
          e.target.classList.add("expanded");
          // Get only details from the group that we're trying to expand
          var D = e.target.parentNode.parentNode.querySelectorAll("details");
          for (var i = 0; i < D.length; i++) {
            D[i].open = true;
          }
          e.preventDefault();
        }, false);
        ca.item(i).addEventListener('click', function(e) {
          e.target.classList.remove("expanded");
          // Get only details from the group that we're trying to expand
          var D = e.target.parentNode.parentNode.querySelectorAll("details");
          for (var i = 0; i < D.length; i++) {
            D[i].open = false;
          }
          e.preventDefault();
        }, false);
      }
    }
  };
})(jQuery, Drupal);
