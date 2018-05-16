<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\EventForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarEvent;

/**
 * Class EventForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class EventViewForm extends FormBase {

  public function title($calendar_id, $event_id) {
    list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id);
    if (!$calendar || !$event) {
      return 'イベント';
    }

    return 'イベント ' . $event->getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $calendar_id = NULL, $event_id = NULL) {
    list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id);
    if (!$calendar || !$event) {
      return $form;
    }

    $form['title'] = [
      '#type' => 'item',
      '#title' => t('タイトル'),
      '#markup' => $event->getSummaryEscaped(),
    ];

    if ($event->getLocation()) {
      $form['location'] = [
        '#type' => 'item',
        '#title' => t('場所'),
        '#markup' => $event->getLocationEscaped(),
      ];
    }

    $form['all_day'] = [
      '#type' => 'item',
      '#title' => t('終日'),
      '#markup' => $event->isAllDay() ? 'はい' : 'いいえ',
    ];

    if ($calendar) {
      $form['calendar'] = [
        '#type' => 'item',
        '#title' => t('カレンダー'),
        '#markup' => $calendar->getDisplayName(),
      ];
    }

    $datetime_format = 'Y-m-d H:i';
    if ($event->isAllDay()) {
      $datetime_format = 'Y-m-d';
    }

    $form['begin_datetime'] = [
      '#type' => 'item',
      '#title' => t('開始日時'),
      '#markup' => $event->getStartDateTime()->format($datetime_format),
    ];

    $end_date = clone $event->getEndDateTime();
    if ($event->isAllDay()) {
      $end_date->modify('-1 day');
    }
    $form['end_datetime'] = [
      '#type' => 'item',
      '#title' => t('終了日時'),
      '#markup' => $end_date->format($datetime_format),
    ];

    if ($event->getRRule()) {
      $form['rrule'] = [
        '#type' => 'item',
        '#title' => t('繰り返し'),
        '#markup' => $event->getRRuleReadable(),
      ];
    }

    if ($event->getDescription()) {
      $form['description'] = [
        '#type' => 'item',
        '#title' => t('メモ'),
        '#markup' => str_replace('\n', '<br>', $event->getDescriptionEscaped()),
      ];
    }

    $alarms = $event->getAlarms();
    if (!empty($alarms)) {
      $form['alarms'] = [
        '#type' => 'item',
        '#title' => t('アラーム'),
        '#markup' => $this->getAlarmsDescription($alarms),
      ];
    }

    if ($event->getStatus()) {
      $form['status'] = [
        '#type' => 'item',
        '#title' => t('ステータス'),
        '#markup' => $event->getStatus(),
      ];
    }

    $form = CalendarUtil::buildAttendeeTable($event, $form, $form_state);
    $form['#attached']['library'][] = 'ipride_calendar/calendar_common';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  private function getAlarmsDescription($alarms) {
    $unit_jps = [
      'minute' => '分',
      'hour' => '時間',
      'day' => '日',
    ];

    $desc = '';
    foreach ($alarms as $alarm) {
      $prefix = '予定';
      if ($alarm['end']) {
        $prefix .= '終了';
      } else {
        $prefix .= '開始';
      }

      if ($alarm['minus']) {
        $prefix .= 'まで';
      } else {
        $prefix .= 'から';
      }

      $minute = $alarm['minute'];
      $hour = $alarm['hour'];
      $day = $alarm['day'];

      $desc .= $prefix . $alarm['time'] . $unit_jps[$alarm['unit']] . '<br>';
    }

    return $desc;
  }
}
