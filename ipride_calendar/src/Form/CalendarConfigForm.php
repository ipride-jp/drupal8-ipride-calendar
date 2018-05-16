<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\CalendarConfigForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the Calendar admin settings form.
 */
class CalendarConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormID() {
    return 'calendar_admin_settings';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('ipride_calendar.settings');

    $form['calendar_server'] = [
      '#type' => 'textfield',
      '#title' => t('カレンダーサーバ'),
      '#default_value' => $config->get('calendar_server'),
    ];

    $form['calender_db'] = [
      '#type' => 'details',
      '#title' => t('カレンダーのデータベース(MySql)'),
      '#open' => TRUE,
    ];

     $form['calender_db']['db_host'] = [
      '#type' => 'textfield',
      '#title' => t('ホスト'),
      '#default_value' => $config->get('db_host'),
    ];

    $form['calender_db']['db_port'] = [
      '#type' => 'textfield',
      '#title' => t('ポート番号'),
      '#default_value' => $config->get('db_port'),
    ];

    $form['calender_db']['db_schema'] = [
      '#type' => 'textfield',
      '#title' => t('データベース名'),
      '#default_value' => $config->get('db_schema'),
    ];

    $form['calender_db']['db_user'] = [
      '#type' => 'textfield',
      '#title' => t('ユーザ名'),
      '#default_value' => $config->get('db_user'),
    ];

    $form['calender_db']['db_passwd'] = [
      '#type' => 'textfield',
      '#title' => t('パスワード'),
      '#default_value' => $config->get('db_passwd'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory->getEditable('ipride_calendar.settings');
    
    $value_keys = [
      'calendar_server',
      'db_driver',
      'db_host',
      'db_port',
      'db_schema',
      'db_user',
      'db_passwd',
    ];
    foreach($value_keys as $key){
      $config->set($key, $values[$key])->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'ipride_calendar.settings',
    ];
  }
}
