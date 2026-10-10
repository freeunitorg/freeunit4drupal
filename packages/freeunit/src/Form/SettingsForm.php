<?php

declare(strict_types=1);

namespace Drupal\freeunit\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures post-response work and the FreeUnit control socket.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['freeunit.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'freeunit_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('freeunit.settings');

    $form['post_response'] = [
      '#type' => 'details',
      '#title' => $this->t('Post-response work'),
      '#open' => TRUE,
      '#description' => $this->t(
        'Runs after the response has been sent and the request detached, so the client is not waiting. The budget must stay well below the application\'s limits.timeout in the FreeUnit configuration.',
      ),
    ];
    $form['post_response']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Drain queues and run cron after the response'),
      '#default_value' => (bool) $config->get('post_response.enabled'),
    ];
    $form['post_response']['budget_ms'] = [
      '#type' => 'number',
      '#title' => $this->t('Budget (milliseconds)'),
      '#min' => 0,
      '#max' => 60000,
      '#default_value' => (int) ($config->get('post_response.budget_ms') ?? 2000),
      '#description' => $this->t('Zero disables the work without changing the switch above.'),
    ];
    $form['post_response']['queues'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Queue worker plugin ids'),
      '#default_value' => implode("\n", (array) ($config->get('post_response.queues') ?? [])),
      '#description' => $this->t('One per line. Leave empty to drain every queue worker that Drupal marks for cron.'),
    ];
    $form['post_response']['cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also run hook_cron() if the budget allows'),
      '#default_value' => (bool) $config->get('post_response.cron'),
    ];

    $form['control_socket'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FreeUnit control socket'),
      '#default_value' => $config->get('control_socket') ?: '/run/control.unit.sock',
      '#description' => $this->t('The unix socket of the running unitd, as given to --control.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $queues = preg_split('/\s+/', trim((string) $form_state->getValue('queues'))) ?: [];
    $this->config('freeunit.settings')
      ->set('post_response.enabled', (bool) $form_state->getValue('enabled'))
      ->set('post_response.budget_ms', (int) $form_state->getValue('budget_ms'))
      ->set('post_response.queues', array_values(array_filter($queues)))
      ->set('post_response.cron', (bool) $form_state->getValue('cron'))
      ->set('control_socket', (string) $form_state->getValue('control_socket'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
