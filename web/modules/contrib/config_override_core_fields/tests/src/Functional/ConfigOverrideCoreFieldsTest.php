<?php

declare(strict_types=1);

namespace Drupal\Tests\config_override_core_fields\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Config Override Core Fields.
 *
 * @group config_override_core_fields
 */
final class ConfigOverrideCoreFieldsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'coi', 'config_override_core_fields'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A very simple test to get CI going.
   */
  public function testConfigOverrideCoreFields(): void {
    $this->drupalLogin($this->createUser(['administer site configuration']));
    $this->drupalGet(Url::fromRoute('system.performance_settings'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<h1>Performance</h1>');
    $this->assertSession()->pageTextContains('Aggregate CSS files');
    $this->assertSession()->pageTextNotContains('This field is overridden by environment specific configuration.');

    $this->assertDirectoryIsWritable($this->siteDirectory);
    $settingsFileName = $this->siteDirectory . '/settings.php';
    chmod($settingsFileName, 0777);
    $this->assertFileIsWritable($settingsFileName);

    $settingsPhp = file_get_contents($settingsFileName);
    $settingsPhp .= "\n\$config['system.performance']['css']['preprocess'] = FALSE;";
    file_put_contents($settingsFileName, $settingsPhp);

    $this->drupalGet(Url::fromRoute('system.performance_settings'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('<h1>Performance</h1>');
    $this->assertSession()->pageTextContains('Aggregate CSS files');
    $this->assertSession()->pageTextContains('This field is overridden by environment specific configuration.');
  }

}
