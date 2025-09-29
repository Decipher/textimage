<?php

declare(strict_types=1);

namespace Drupal\textimage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * Defines a Textimage logger.
 */
class TextimageLogger extends LoggerChannel {

  use StringTranslationTrait;
  use MessengerTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $loggerChannel,
    AccountInterface $currentUser,
  ) {
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function log(/* mixed */ $level, /* string */ $message, array $context = []): void {
    // Convert to integer equivalent for consistency with RFC 5424.
    $level_code = is_string($level) ? $this->levelTranslation[$level] : $level;

    // Process debug entries only if required.
    if ($level_code == RfcLogLevel::DEBUG && !$this->configFactory->get('textimage.settings')->get('debug')) {
      return;
    }

    // Logs through the logger channel.
    $this->loggerChannel->log($level_code, $message, $context);

    // Display the message to qualified users.
    if ($this->currentUser->hasPermission('administer site configuration') ||
        $this->currentUser->hasPermission('administer image styles')) {
      switch ($level_code) {
        case RfcLogLevel::DEBUG:
        case RfcLogLevel::INFO:
        case RfcLogLevel::NOTICE:
          $type = 'status';
          break;

        case RfcLogLevel::WARNING:
          $type = 'warning';
          break;

        default:
          $type = 'error';
      }
      // @todo replace call to $this->t
      // @codingStandardsIgnoreLine
      $this->messenger()->addMessage($this->t($message, $context), $type);
    }
  }

}
