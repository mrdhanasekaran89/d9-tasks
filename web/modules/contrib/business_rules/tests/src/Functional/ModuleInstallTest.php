<?php

namespace Drupal\Tests\business_rules\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Assert that the business_rules module installed correctly.
 *
 * For example, if parts of the config schema are missing, then the module will
 * not install correctly. Note that Unit and Kernel tests don't fully install
 * the module.
 *
 * This test can be deleted as soon as there is at least one other Functional
 * or FunctionalJavascript test that installs the business_rules module during
 * its setUp phase.
 *
 * @group business_rules
 */
class ModuleInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['business_rules'];

  /**
   * Assert that the business_rules module installed correctly.
   */
  public function testModuleInstalls() {
    // If we get here, then the module was successfully installed during the
    // setUp phase without throwing any Exceptions. Assert that TRUE is true,
    // so at least one assertion runs, and then exit.
    $this->assertTrue(TRUE, 'Module installed correctly.');
  }

}
