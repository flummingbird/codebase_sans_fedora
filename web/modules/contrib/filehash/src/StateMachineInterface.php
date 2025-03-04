<?php

namespace Drupal\filehash;

/**
 * Defines the state machine interface.
 */
interface StateMachineInterface {

  /**
   * Updates the state with some data.
   */
  public function update(string $data): void;

  /**
   * Returns the computed output.
   */
  public function final(): string;

}
