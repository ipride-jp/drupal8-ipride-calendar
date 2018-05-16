<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\CalendarViewForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;

/**
 * Class CalendarViewForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class CalendarViewForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_view_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $calendar_id = NULL) {
    $calendar = CalendarUtil::getCalendarById($calendar_id);
    if (!$calendar) {
      return $form;
    }

    $form['name'] = [
      '#type' => 'item',
      '#title' => t('Name'),
      '#markup' => $calendar->getDisplayName(),
    ];

    $color = $calendar->getRBGcolor();
    $form['color'] = [
      '#type' => 'textfield',
      '#title' => t('Color'),
      '#size' => 8,
      '#default_value' => $color,
      '#attributes' => [
        'readonly' => 'readonly',
        'style' => sprintf('background-color: %s; color: %s',
          $color,
          CalendarUtil::selectWhiteOrBlackColor($color)
        ),
      ],
    ];

    $calendar_server = \Drupal::config('ipride_calendar.settings')->get('calendar_server');
    $calendar_server = rtrim($calendar_server, '/');
    $form['url'] = [
      '#type' => 'item',
      '#title' => t('カレンダーURL'),
      '#markup' => $calendar_server . $calendar->getURL(),
    ];

    $form = CalendarUtil::buildCalendarAttendeeTable($calendar, $form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  public function title($calendar_id) {
    $calendar = CalendarUtil::getCalendarById($calendar_id);
    if (!$calendar) {
      return 'カレンダー';
    }

    return 'カレンダー ' . $calendar->getDisplayName();
  }

  private function getSelfText($user_name) {
    $user = \Drupal::currentUser();
    if ($user->getAccountName() == $user_name) {
      return '(自分)';
    }

    return '';
  }

}
