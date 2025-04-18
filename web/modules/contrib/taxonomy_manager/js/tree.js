(function ($, Drupal, drupalSettings, once) {
  /**
   * Attaches the JS.
   */
  Drupal.behaviors.TaxonomyManagerTree = {
    attach(context, settings) {
      const treeSettings = settings.taxonomy_manager.tree || [];
      if (treeSettings instanceof Array) {
        for (let i = 0; i < treeSettings.length; i++) {
          $(once('taxonomy-manager-tree', `#${treeSettings[i].id}`)).each(
            function () {
              const tree = new Drupal.TaxonomyManagerFancyTree(
                treeSettings[i].id,
                treeSettings[i].name,
                treeSettings[i].source,
              );
            },
          );
        }
      }
      // Handle click on search terms
      $('.taxonomy-manager-search-button').click(function (e) {
        e.preventDefault();
        $('.taxonomy-manager-autocomplete-input').show();
        return true;
      });

      once('input', 'input[name="search_terms"]', context).forEach((value) => {
        const uiAutocomplete = $(value);

        // Bind the autocomplete widget to the input field
        // @see https://api.jqueryui.com/autocomplete/#event-select
        uiAutocomplete.bind('autocompleteselect', (event, ui) => {
          event.stopPropagation();
          const tidMatch = ui.item.value.match(/\([0-9]*\)/g);
          if (tidMatch.length) {
            const tid = parseInt(tidMatch[0].replace(/^[^0-9]+/, ''), 10);
            $.ajax({
              url: Drupal.url('taxonomy_manager/subtree/child-parents'),
              dataType: 'json',
              data: {
                tid,
              },
              success: (termData) => {
                const $tree = $('#edit-taxonomy-manager-tree').fancytree(
                  'getTree',
                );
                const { path } = termData;
                $tree.loadKeyPath(path).progress((keyData) => {
                  if (keyData.status === 'ok') {
                    $tree.activateKey(keyData.node.key);
                  }
                });
              },
              error: (jqXHR, textStatus, errorThrown) => {
                console.error(`Request failed: ${textStatus}, ${errorThrown}`);
              },
            });
          }
        });
      });
    },
  };

  /**
   * FancyTree integration.
   *
   * @param {string} id The id of the wrapping div element
   * @param {string} name The form element name (used in $_POST)
   * @param {object} source The JSON object representing the initial tree
   */
  Drupal.TaxonomyManagerFancyTree = function (id, name, source) {
    // Reset selected items.
    if (sessionStorage.hasOwnProperty('fancytree-1-selected')) {
      sessionStorage['fancytree-1-selected'] = '';
    }
    // Settings generated by http://wwwendt.de/tech/fancytree/demo/sample-configurator.html
    $(`#${id}`).fancytree({
      extensions: ['persist'],
      activeVisible: true, // Make sure, active nodes are visible (expanded).
      aria: false, // Enable WAI-ARIA support.
      autoActivate: true, // Automatically activate a node when it is focused (using keys).
      autoCollapse: false, // Automatically collapse all siblings, when a node is expanded.
      autoScroll: false, // Automatically scroll nodes into visible area.
      clickFolderMode: 4, // 1:activate, 2:expand, 3:activate and expand, 4:activate (dblclick expands)
      checkbox: true, // Show checkboxes.
      debugLevel: 2, // 0:quiet, 1:normal, 2:debug
      disabled: false, // Disable control
      focusOnSelect: false, // Set focus when node is checked by a mouse click
      generateIds: false, // Generate id attributes like <span id='fancytree-id-KEY'>
      idPrefix: 'ft_', // Used to generate node id´s like <span id='fancytree-id-<key>'>.
      icon: false, // Display node icons.
      keyboard: false, // Support keyboard navigation.
      keyPathSeparator: '/', // Used by node.getKeyPath() and tree.loadKeyPath().
      minExpandLevel: 1, // 1: root node is not collapsible
      quicksearch: false, // Navigate to next node by typing the first letters.
      selectMode: 2, // 1:single, 2:multi, 3:multi-hier
      tabindex: 0, // Whole tree behaves as one single control
      titlesTabbable: false, // Node titles can receive keyboard focus
      lazyLoad(event, data) {
        // Load child nodes via ajax GET /taxonomy_manager/parent=1234
        data.result = {
          url: Drupal.url('taxonomy_manager/subtree'),
          data: { parent: data.node.key },
          cache: false,
        };
      },
      source,
      select(event, data) {
        // We update the form inputs on every checkbox state change as ajax
        // events might require the latest state.
        data.tree.generateFormElements(`${name}[]`);
        // If no item is selected then disable delete button.
        if (data.tree.getSelectedNodes().length < 1) {
          document.getElementById('edit-delete').disabled = true;
        } else {
          const $deleteButton = document.getElementById('edit-delete');
          $deleteButton.disabled = false;
          if ($deleteButton.classList.contains('is-disabled')) {
            $deleteButton.classList.remove('is-disabled');
          }
        }

        // Create custom event for tree selection so other modules are able to
        // react on selection changes.
        const treeSelectEvent = new CustomEvent(
          'taxonomy_manager-tree-select',
          {
            detail: data,
          },
        );
        document.dispatchEvent(treeSelectEvent);
      },
      focus(event, data) {
        new Drupal.TaxonomyManagerTermData(data.node.key, data.tree);
      },
      click(event, data) {
        if (
          event.type === 'fancytreeclick' &&
          (data.targetType === 'checkbox' || data.targetType === 'title')
        ) {
          new Drupal.TaxonomyManagerTermData(data.node.key, data.tree);
          if (data.targetType === 'title') {
            window.location.href = Drupal.url(`${drupalSettings.path.currentPath}?tid=${data.node.key}`);
          }
        }
      },
      persist: {
        expandLazy: true,
        overrideSource: false,
        store: 'session',
      },
      restore(event, data) {
        // Check if the key exists in sessionStorage
        if (sessionStorage.hasOwnProperty('fancytree-1-active')) {
          // Open the active node.
          new Drupal.TaxonomyManagerTermData(
            sessionStorage['fancytree-1-active'],
            data.tree,
          );
        }
      },
    });
  };
})(jQuery, Drupal, drupalSettings, once);
