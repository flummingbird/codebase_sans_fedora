<?php

namespace Drupal\filehash;

/**
 * State machine for the Sodium PHP extension.
 */
class SodiumStateMachine implements StateMachineInterface {

  /**
   * The hash state.
   *
   * @var non-empty-string
   */
  protected string $state;

  public function __construct(protected int $length) {
    $this->state = sodium_crypto_generichash_init('', $length);
  }

  /**
   * {@inheritdoc}
   */
  public function update(string $data): void {
    sodium_crypto_generichash_update($this->state, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function final(): string {
    return bin2hex(sodium_crypto_generichash_final($this->state, $this->length));
  }

}
