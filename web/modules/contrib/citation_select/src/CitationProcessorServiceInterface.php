<?php

namespace Drupal\citation_select;

/**
 * Provides a Citation Select form.
 */
interface CitationProcessorServiceInterface {

  /**
   * Builds the CSL array to display the citation.
   *
   * @param string $nid
   *   Node id to get citation information from.
   *
   * @return array
   *   Citation array in CSL-JSON format
   */
  public function getCitationArray($nid);

}
