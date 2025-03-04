<?php

namespace Drupal\citation_select;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/HumanNameParserInterface.php
 * The original code has been modified to fit the needs of this module.
 */

/**
 * Define an interface for HumanNameParser service.
 */
interface HumanNameParserInterface {

  /**
   * Parse the name into its constituent parts.
   *
   * @param string $name
   *   Human name string.
   *
   * @return array
   *   Parsed name parts.
   */
  public function parse($name);

}
