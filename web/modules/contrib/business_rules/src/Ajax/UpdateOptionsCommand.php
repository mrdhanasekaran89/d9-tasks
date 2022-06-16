<?php

namespace Drupal\business_rules\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to update form options.
 *
 * @package Drupal\business_rules\Ajax
 */
class UpdateOptionsCommand implements CommandInterface {

  /**
   * The element html id.
   *
   * @var string
   */
  protected $elementId;

  /**
   * The element options [key, value].
   *
   * @var array
   */
  protected $options;

  /**
   * The field formatter.
   *
   * @var string
   */
  protected $formatter;

  /**
   * The 'multiple' attribute of select.
   *
   * @var bool
   */
  protected $multiple;

  /**
   * UpdateOptionsCommand constructor.
   *
   * @param string $elementId
   *   The element html id.
   * @param array $options
   *   The element options [key, value].
   * @param string $formatter
   *   The field formatter.
   * @param bool $multiple
   *   The 'multiple' attribute of select.
   */
  public function __construct($elementId, array $options, $formatter, bool $multiple) {
    $this->elementId = $elementId;
    $this->options = $options;
    $this->formatter = $formatter;
    $this->multiple = $multiple;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'updateOptionsCommand',
      'method' => 'html',
      'elementId' => $this->elementId,
      'options' => $this->options,
      'formatter' => $this->formatter,
      'multiple' => $this->multiple,
    ];
  }

}
