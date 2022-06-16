<?php

namespace Drupal\business_rules\Plugin\BusinessRulesAction;

use Drupal\business_rules\ActionInterface;
use Drupal\business_rules\Events\BusinessRulesEvent;
use Drupal\business_rules\ItemInterface;
use Drupal\business_rules\Plugin\BusinessRulesActionPlugin;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\language\ConfigurableLanguageManagerInterface;

/**
 * Class SendEmail.
 *
 * @package Drupal\business_rules\Plugin\BusinessRulesAction
 *
 * @BusinessRulesAction(
 *   id = "send_email",
 *   label = @Translation("Send email"),
 *   group = @Translation("System"),
 *   description = @Translation("Sent email action."),
 *   isContextDependent = FALSE,
 *   hasTargetEntity = FALSE,
 *   hasTargetBundle = FALSE,
 *   hasTargetField = FALSE,
 * )
 */
class SendEmail extends BusinessRulesActionPlugin {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id = 'send_email', $plugin_definition = []) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailManager = $this->util->container->get('plugin.manager.mail');
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array &$form, FormStateInterface $form_state, ItemInterface $item) {

    $form_state->set('business_rules_item', $item);

    // Only show settings form if the item is already saved.
    if ($item->isNew()) {
      return [];
    }

    $site_mail = \Drupal::config('system.site')->get('mail');

    $settings['use_site_mail_as_sender'] = [
      '#type'          => 'select',
      '#title'         => t('Use site mail as sender'),
      '#options'       => [
        1  => t('Yes'),
        0 => t('No'),
      ],
      '#required'      => TRUE,
      '#default_value' => $item->getSettings('use_site_mail_as_sender') ?: 0,
      '#description'   => t('Use %mail as sender', ['%mail' => $site_mail]),
    ];

    $settings['from'] = [
      '#type'          => 'textfield',
      '#title'         => t('From'),
      '#default_value' => $item->getSettings('from'),
      '#description'   => t('You can use variables on this field.'),
      '#states'        => [
        'visible' => [
          'select[name="use_site_mail_as_sender"]' => ['value' => '0'],
        ],
      ],
    ];

    $settings['to'] = [
      '#type'          => 'textfield',
      '#title'         => t('To'),
      '#required'      => TRUE,
      '#default_value' => $item->getSettings('to'),
      '#description'   => t('For multiple recipients, use semicolon(;). You can use variables on this field. The variable can contain one email or an array of emails'),
    ];

    $settings['subject'] = [
      '#type'          => 'textfield',
      '#title'         => t('Subject'),
      '#required'      => TRUE,
      '#maxlength'     => 256,
      '#default_value' => $item->getSettings('subject'),
      '#description'   => t('You can use variables on this field.'),
    ];

    $settings['format'] = [
      '#type'          => 'select',
      '#title'         => t('Mail format'),
      '#options'       => [
        'html' => t('HTML'),
        'text' => t('Text'),
      ],
      '#required'      => TRUE,
      '#default_value' => $item->getSettings('format') ? $item->getSettings('format') : 'text',
      '#description'   => t('Email body format.'),
    ];

    $settings['body'] = [
      '#type'          => 'text_format',
      '#title'         => t('Message'),
      '#required'      => TRUE,
      '#default_value' => $item->getSettings('body')['value'],
      '#description'   => t('You can use variables on this field.'),
      '#format' => ($item->getSettings('body') && isset($item->getSettings('body')['format'])) ? $item->getSettings('body')['format'] : 'full_html',
    ];

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function processSettings(array $settings, ItemInterface $item) {

    if (isset($settings['use_site_mail_as_sender']) && $settings['use_site_mail_as_sender'] === 1) {
      $settings['from'] = NULL;
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ActionInterface $action, BusinessRulesEvent $event) {
    $event_variables = $event->getArgument('variables');
    $to              = $this->processVariables($action->getSettings('to'), $event_variables);
    $arr_to          = explode(';', $to);
    $result          = [];
    $from = NULL;

    // If we have set to NOT use site email; get the From setting.
    if (!$action->getSettings('use_site_mail_as_sender')) {
      $from = $action->getSettings('from');
      $from = $this->processVariables($from, $event_variables);
    }

    // Should handle the To set as ; separated list: multiple emails sent
    // OR CSV list which is single item in $arr_to and 1 email sent to multiple people.
    foreach ($arr_to as $to) {
      // Handle RFC-822 formatted emails.
      $email_pattern = '/\s*"?([^><,"]+)"?\s*((?:<[^><,]+>)?)\s*/';
      if(preg_match_all($email_pattern, $to, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $m) {
          if(!empty($m[2])) {
            $emails[trim($m[2], '<>')] = trim($m[1]);
          }
          else {
            $emails[$m[1]] = '';
          }
        }
      }
      else {
        // If not valid email or list of emails; just skip this one.
        continue;
      }

      // Check if $to (or 1st To) is a registered email to get Language.
      // @todo: go through all in CSV list to find a registered user.
      $user = user_load_by_mail(current(array_keys($emails)));
      if ($user) {
          $langcode = $user->language()->getId();
        }
      else {
        // If user not found, use the site language.
        $langcode = \Drupal::config('system.site')->get('langcode');
      }

      // Send the email.
      $languageManager = \Drupal::languageManager();
      if ($languageManager instanceof ConfigurableLanguageManagerInterface) {
        $action_translated   = $languageManager->getLanguageConfigOverride($langcode, 'business_rules.action.' . $action->id());
        $settings_translated = $action_translated->get('settings');
      }

      $subject = isset($settings_translated['subject']) ? $settings_translated['subject'] : $action->getSettings('subject');
      $message = isset($settings_translated['body']) ? $settings_translated['body'] : $action->getSettings('body')['value'];
      $subject = $this->processVariables($subject, $event_variables);
      $message = $this->processVariables($message, $event_variables);

      // Check if body is on html format.
      if ($action->getSettings('format') == 'html') {
        $headers['Content-Type'] = 'text/html; charset=UTF-8';
        $message = html_entity_decode($message);
      }
      else {
        $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        $message = MailFormatHelper::htmlToText($message);
      }

      // Add we have our own From, add into headers. If not; Drupal will add Site Email
      if ($from) {
        $headers['From'] = $from;
      }

      $params = [
        'headers' => $headers,
        'subject' => $subject,
        'message' => $message,
      ];

      $send_result = $this->mailManager->mail('business_rules', 'business_rules_mail', $to, $langcode, $params);

      $result = [
        '#type'   => 'markup',
        '#markup' => t('Send mail result: %result. Subject: %subject, from: %from, to: %to, message: %message.', [
          '%result'  => $send_result['result'] ? t('success') : t('fail'),
          '%subject' => $subject,
          '%from'    => $from,
          '%to'      => $to,
          '%message' => $message,
        ]),
      ];
    }

    return $result;
  }

}
