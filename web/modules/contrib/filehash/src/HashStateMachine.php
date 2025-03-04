<?php

namespace Drupal\filehash;

/**
 * State machine for the Hash PHP extension.
 */
class HashStateMachine implements StateMachineInterface {

  /**
   * The hash state.
   */
  protected \HashContext $state;

  public function __construct(string $algo) {
    $this->state = hash_init($algo);
  }

  /**
   * {@inheritdoc}
   */
  public function update(string $data): void {
    hash_update($this->state, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function final(): string {
    return hash_final($this->state);
  }

}
