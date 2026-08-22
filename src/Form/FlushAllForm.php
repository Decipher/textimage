<?php

declare(strict_types=1);

namespace Drupal\textimage\Form;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\textimage\TextimageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a form to confirm flushing of all Textimage images.
 *
 * @phpstan-consistent-constructor
 */
class FlushAllForm extends ConfirmFormBase {

  // The parent class uses DependencySerializationTrait. Using it here too
  // lets __wakeup() reinitialise the readonly promoted properties declared
  // in this class, which PHP only allows from the declaring class.
  use DependencySerializationTrait;

  public function __construct(
    protected readonly TextimageFactoryInterface $textimageFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(TextimageFactoryInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'textimage_flush_all_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Cleanup Textimage?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('This will remove all image files generated via Textimage, flush all the image styles, and clear the Textimage cache.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Proceed');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('textimage.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->textimageFactory->flushAll();
    $form_state->setRedirect('textimage.settings');
  }

}
