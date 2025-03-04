<?php

namespace Drupal\citation_select;

/**
 * This file is based on the file from the bibcite module.
 *
 * Source: https://git.drupalcode.org/project/bibcite/-/blob/8e8c61a568096a1cdc4a1f65b61d25cd8ae7eb08/src/HumanNameParser.php
 * The original code has been modified to fit the needs of this module.
 */

use ADCI\FullNameParser\Parser;

/**
 * Human name parser service.
 */
class HumanNameParser implements HumanNameParserInterface {

  /**
   * Parser object.
   *
   * @var \ADCI\FullNameParser\Parser
   */
  protected $parser;

  /**
   * HumanNameParser constructor.
   */
  public function __construct() {
    $this->parser = new Parser([
      'mandatory_last_name' => FALSE,
      'mandatory_middle_name' => FALSE,
    ]);
  }

  /**
   * Parse the name into its constituent parts.
   *
   * @param string $name
   *   Human name string.
   *
   * @return array
   *   Parsed name parts.
   *
   * @throws \ADCI\FullNameParser\Exception\NameParsingException
   */
  public function parse($name) {
    $parsed_name = $this->parser->parse($name);

    return [
      'leading_title' => $parsed_name->getLeadingInitial(),
      'prefix' => $parsed_name->getAcademicTitle(),
      'first_name' => $parsed_name->getFirstName(),
      'middle_name' => $parsed_name->getMiddleName(),
      'last_name' => $parsed_name->getLastName(),
      'nick' => $parsed_name->getNicknames(),
      'suffix' => $parsed_name->getSuffix(),
    ];
  }

}
