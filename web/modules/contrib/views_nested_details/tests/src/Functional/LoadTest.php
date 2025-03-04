<?php

namespace Drupal\Tests\views_nested_details\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group views_nested_details
 */
class LoadTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['views_ui', 'views_nested_details_test'];


  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();
    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer views',
    ]);
    $this->drupalLogin($user);

  }

  /**
   * Tests that the home page loads with a 200 response.
   */
  public function testLoad() {
    $assert_session = $this->assertSession();
    // Test views add form.
    $edit = [
      'id' => 'test',
      'label' => 'test',
      'show[wizard_key]' => 'node',
      'show[sort]' => 'none',
      'page[create]' => TRUE,
      'page[title]' => 'Test',
      'page[path]' => 'test',
      'page[style][style_plugin]' => 'nested_details',
      'page[style][row_plugin]' => 'teasers',
    ];
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm($edit, 'Save and edit');
    $assert_session->pageTextContains('Views Nested Details requires Fields as row style');

    $edit['page[style][row_plugin]'] = 'fields';
    $this->submitForm($edit, 'Save and edit');
    $assert_session->pageTextContains('The view test has been saved.');

    // Assert the options of our exported view display correctly.
    $this->drupalGet('admin/structure/views/view/views_nested_details_test/edit');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Nested Details');

    $this->drupalGet('admin/structure/views/nojs/display/views_nested_details_test/page_1/style_options');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldValueEquals('style_options[grouping][0][field]', 'created');
    $assert_session->checkboxChecked('style_options[grouping][0][rendered]');
    $assert_session->fieldValueEquals('style_options[grouping][1][field]', 'created_1');
    $assert_session->checkboxChecked('style_options[grouping][1][rendered]');

  }

}
