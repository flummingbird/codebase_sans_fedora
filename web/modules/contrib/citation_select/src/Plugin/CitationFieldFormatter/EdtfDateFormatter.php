<?php

namespace Drupal\citation_select\Plugin\CitationFieldFormatter;

use Drupal\citation_select\CitationFieldFormatterBase;
use EDTF\EdtfFactory;

/**
 * Plugin to format edtf field type.
 *
 * @CitationFieldFormatter(
 *    id = "edtf",
 *    field_type = "edtf",
 * )
 */
class EdtfDateFormatter extends CitationFieldFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function parseDate($string) {
    $parser = EdtfFactory::newParser();
    $edtf_value = $parser->parse($string)->getEdtfValue();
    try {
      // The parser may return either an EDTF Set or an ExtDate object.
      if (method_exists($edtf_value, 'getStartDate') && method_exists($edtf_value, 'getEndDate')) {
        // Parser returned an interval.
        $startDate = $edtf_value->getStartDate();
        $endDate = $edtf_value->getEndDate();
        $start_parts = [
          $startDate->getYear(),
          $startDate->getMonth(),
          $startDate->getDay(),
        ];
        $end_parts = [
          $endDate->getYear(),
          $endDate->getMonth(),
          $endDate->getDay(),
        ];
        // Filter out empty components before adding it into parts.
        $date_parts = [
          array_filter($start_parts),
          array_filter($end_parts),
        ];
      }
      elseif (method_exists($edtf_value, 'getDates')) {
        // Parser returned a Set.
        $date_set = $edtf_value->getElements()[0];
        if (method_exists($date_set, 'getStart') && method_exists($date_set, 'getEnd')) {
          $startDate = $date_set->getStart();
          $endDate = $date_set->getEnd();
          $start_parts = [
            $startDate->getYear(),
            $startDate->getMonth(),
            $startDate->getDay(),
          ];
          $end_parts = [
            $endDate->getYear(),
            $endDate->getMonth(),
            $endDate->getDay(),
          ];
          // Filter out empty components before adding it into parts.
          $date_parts = [
            array_filter($start_parts),
            array_filter($end_parts),
          ];
        }
        else {
          // Parser returned a Set with no start or no end.
          $date_parts = [];
        }
      }
      else {
        // Parser returned an ExtDate object.
        $date_parts = [
          array_filter([
            $edtf_value->getYear(),
            $edtf_value->getMonth(),
            $edtf_value->getDay(),
          ]),
        ];
      }
      return [
        'date-parts' => [...$date_parts],
      ];
    }
    catch (\RuntimeException $e) {
      return [
        'date-parts' => [[]],
      ];
    }
  }

}
