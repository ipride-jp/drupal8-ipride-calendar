<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\EventForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\HolidayDateTime;

/**
 * Class EventFormBase.
 *
 * @package Drupal\ipride_calendar\Form
 */
abstract class EventFormBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $event_id = NULL) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => t('タイトル'),
      '#required' => TRUE,
    ];

    $form['location'] = [
      '#type' => 'textfield',
      '#title' => t('場所'),
    ];

    $form['all_day'] = [
      '#type' => 'checkbox',
      '#title' => t('終日'),
    ];

    $calendar_options = $this->getCalendarOptions();
    $form['calendar'] = [
      '#type' => 'select',
      '#title' => t('カレンダー'),
      '#required' => TRUE,
      '#options' => $calendar_options,
      '#default_value' => '',
    ];

    $now = new HolidayDateTime('now', new \DateTimeZone(drupal_get_user_timezone()));
    $next_hour = clone $now;
    $next_hour->setTime($now->hour, 0, 0);
    $next_hour->modify('+1 hour');

    $form['begin_datetime'] = [
      '#type' => 'datetime',
      '#title' => t('開始日時'),
      '#required' => TRUE,
      '#default_value' => DrupalDateTime::createFromDateTime($next_hour),
      '#date_increment' => NULL,
    ];

    $next_hour->modify('+30 minute');
    $form['end_datetime'] = [
      '#type' => 'datetime',
      '#title' => t('終了日時'),
      '#required' => TRUE,
      '#default_value' => DrupalDateTime::createFromDateTime($next_hour),
      '#date_increment' => NULL,
    ];

    $form = $this->buildRepeatForm($form, $form_state);

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => t('メモ'),
    ];

    $form['#attached']['library'][] = 'ipride_calendar/event';
    $form['#attached']['library'][] = 'ipride_calendar/calendar_common';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  private function getCalendarOptions() {
    $calendar_options = [];

    try {
      $client = CalendarUtil::getCalendarClient();
      $calendars = $client->findCalendars();
      foreach ($calendars as $calendar) {
        if (!$this->isCalendarWriteable($calendar)) {
          continue;
        }
        $calendar_options[$calendar->getCalendarID()] = $calendar->getDisplayName();
      }
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return empty($calendar_options) ? NULL : $calendar_options;
  }

  private function isCalendarWriteable($calendar) {
    $current_user_mail = \Drupal::currentUser()->getEmail();
    if ($current_user_mail == $calendar->getOrganizer()) {
      return TRUE;
    }

    foreach ($calendar->getAttendees() as $attendee) {
      if ($attendee['mail'] == $current_user_mail
        && $attendee['permission'] == 'read-write') {

        return TRUE;
      }
    }
    
    return FALSE;
  }

  protected function getNewEventData($form_state, $event_id = NULL) {
    if ($event_id) {
      $uuid = $event_id;
    } else {
      $uuid = Drupal::service('uuid')->generate();
    }
    $summary = $form_state->getValue('title');
    $location = $form_state->getValue('location');
    $description = str_replace("\n", '\n', $form_state->getValue('description'));

    $begin_datetime = $form_state->getValue('begin_datetime');
    $end_datetime = $form_state->getValue('end_datetime');

    $begin_date = $begin_datetime->format('Ymd');
    $begin_time = $begin_datetime->format('Hi');
    $end_date = $end_datetime->format('Ymd');
    $end_time = $end_datetime->format('Hi');

    $now_time = time();
    $now = CalendarUtil::formatTimestampToCaldavStr($now_time);

    if ($form_state->getValue('all_day') == '1') {//終日
      $dtstart = "VALUE=DATE:${begin_date}";

      $end_datetime->modify('+1 day');
      $end_date = $end_datetime->format('Ymd');

      $dtend = "VALUE=DATE:${end_date}";
    } else {
      $timezone = drupal_get_user_timezone();
      $dtstart = "TZID=$timezone:${begin_date}T${begin_time}00";
      $dtend = "TZID=$timezone:${end_date}T${end_time}00";
    }

    //alarms
    $alarms_data = [];
    if (isset($form_state->getBuildInfo()['alarms_data'])) {
      $alarms_data = $form_state->getBuildInfo()['alarms_data'];
    }

    $alarms_text = '';
    foreach ($alarms_data as $index => $alarm_data) {
      $type = $alarm_data['type'];
      $unit = $alarm_data['unit'];
      $time = $alarm_data['time'];

      $alarms = $form_state->getValue('alarms');
      if (isset($alarms[$index])) {
        $alarm = $alarms[$index];
        if (isset($alarm['type'])) {
          $type = $alarm['type'];
        }
        if (isset($alarm['unit'])) {
          $unit = $alarm['unit'];
        }
        if (isset($alarm['time'])) {
          $time = $alarm['time'];
        }
      }

      $related = '';
      if ($type == 2 || $type == 3) {
        $related = 'RELATED=END;';
      }

      $duration = 'P';
      if ($type == 0 || $type == 2) {
        $duration = '-P';
      }

      switch ($unit) {
        case 'minute':
          $duration .= 'T' . $time . 'M';
          break;

        case 'hour':
          $duration .= 'T' . $time . 'H';
          break;

        case 'day':
          $duration .= $time . 'D';
          break;
      }

      $alarms_text .=
"BEGIN:VALARM
ACTION:DISPLAY
TRIGGER;${related}VALUE=DURATION:$duration
DESCRIPTION:Reminder set on drupal8
END:VALARM
";
    }

    //attendees
    $attendees_data = [];
    if (isset($form_state->getBuildInfo()['attendees_data'])) {
      $attendees_data = $form_state->getBuildInfo()['attendees_data'];
    }

    $organizer_text = '';
    $attendees_text = '';
    $attendees = $form_state->getValue('attendees');
    $mails_added = [];
    foreach($attendees_data as $index => $attendee_data) {
      $mail = $attendee_data['mail'];
      $status = $attendee_data['status'];
      if ($index == 0) {
        $organizer_text = 'ORGANIZER;RSVP=TRUE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:'
          . $mail;

        continue;
      }

      if (isset($attendees[$index]) && isset($attendees[$index]['name']) && !empty($attendees[$index]['name'])) {
        $mail = user_load($attendees[$index]['name'])->getEmail();
      }

      if (isset($attendees[$index]) && isset($attendees[$index]['status'])) {
        $status = $attendees[$index]['status'];
      }

      if (empty($mail)) {
        continue;
      }

      if (in_array($mail, $mails_added)) {
        continue;
      }

      $mails_added[] = $mail;

      if (empty($status)) {
        $status = 'NEEDS-ACTION';
      }

      $attendees_text .= sprintf(
        "ATTENDEE;PARTSTAT=%s;ROLE=REQ-PARTICIPANT;SCHEDULE-STATUS=2.0:mailto:%s\n",
        $status,
        $mail
      );
    }

    $attendees_text = trim($attendees_text);
    $rrule = $this->createRRule($form_state);

    $event = "BEGIN:VCALENDAR
PRODID:-//Sabre//Sabre VObject 3.4.6//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:$uuid
DTSTAMP:$now
SUMMARY:$summary
DESCRIPTION: $description
LOCATION: $location
CLASS:PUBLIC
TRANSP:OPAQUE
$rrule
$organizer_text
$attendees_text
DTSTART;$dtstart
DTEND;$dtend
CREATED:$now
LAST-MODIFIED:$now
$alarms_text
END:VEVENT
END:VCALENDAR
";

    return $event;
  }

  public function repeatCallback(array &$form, FormStateInterface $form_state) {
    return $form['repeat_details'];
  }

  public function repeatEndCallback(array &$form, FormStateInterface $form_state) {
    return $form['repeat_details']['repeat_end_details'];
  }

  private function createRRule($form_state) {
    $repeat = $form_state->getValue('repeat');
    if ($repeat == 'none') {
      return NULL;
    }

    $repeat_interval = $form_state->getValue('repeat_interval');
    $rrule = sprintf('RRULE:FREQ=%s;INTERVAL=%s', strtoupper($repeat), $repeat_interval);

    $repeat_end = $form_state->getValue('repeat_end');
    if ($repeat_end == 'count') {
      $rrule .= ';COUNT=' . $form_state->getValue('repeat_end_count');
    } elseif ($repeat_end == 'date') {
      $repeat_end_date = str_replace('-', '', $form_state->getValue('repeat_end_date'));

      $util_time = new HolidayDateTime($repeat_end_date, new \DateTimeZone(drupal_get_user_timezone()));
      if (!empty($form_state->getValue('end_time'))) {
        $end_time = explode(':', $form_state->getValue('end_time'));
        $util_time->setTime($end_time[0], $end_time[1]);
      }
      $rrule .= ';UNTIL=' . $util_time->getGmdate();
    }

    if ($repeat == 'weekly') {
      $weekday = $form_state->getValue('repeat_weekday');
      $weekday = array_filter(array_values($weekday));
      if (!empty($weekday)) {
        $rrule .= ';BYDAY=' . implode(',', $weekday);
      }
    } elseif ($repeat == 'monthly') {
      $rrule .= ';BYMONTHDAY=' . $form_state->getValue('repeat_month_day');
    }

    return $rrule;
  }

  private function buildRepeatForm(array $form, FormStateInterface $form_state) {
    $rrule_obj = NULL;
    if (isset($this->event) && $this->event->getRRuleObject()) {
      $rrule_obj = $this->event->getRRuleObject();
    }

    $repeat_default_value = 'none';
    if ($rrule_obj) {
      $repeat_default_value = strtolower($rrule_obj->getFreqAsText());
    }
    $form['repeat'] = [
      '#type' => 'select',
      '#title' => t('繰り返し'),
      '#options' => [
        'none' => t('しない'),
        'daily' => t('日'),
        'weekly' => t('週'),
        'monthly' => t('月'),
        'yearly' => t('年'),
      ],
      '#required' => TRUE,
      '#default_value' => $repeat_default_value,
      '#ajax' => [
        'callback' => [$this, 'repeatCallback'],
        'wrapper' => 'repeat_details_wrapper',
      ],
    ];

    $form['repeat_details'] = [
      '#prefix' => '<div id="repeat_details_wrapper">',
      '#suffix' => '</div>',
    ];

    $repeat_value = $form_state->getValue('repeat', $repeat_default_value);

    if ($repeat_value && $repeat_value != 'none') {
      $form['repeat_details']['#type'] = 'details';
      $form['repeat_details']['#title'] = t('繰り返し詳細');
      $form['repeat_details']['#open'] = TRUE;

      $repeat_interval_default_value = 1;
      if ($rrule_obj) {
        $repeat_interval_default_value = $rrule_obj->getInterval();
      }
      $form['repeat_details']['repeat_custom']['repeat_interval'] = [
        '#type' => 'number',
        '#title' => '間隔',
        '#required' => TRUE,
        '#default_value' => $repeat_interval_default_value,
      ];

      if ($repeat_value == 'weekly') {
        $repeat_weekday_default_value = [];
        if ($rrule_obj && $rrule_obj->getByDay()) {
          $repeat_weekday_default_value = $rrule_obj->getByDay();
        }
        $form['repeat_details']['repeat_custom']['repeat_weekday'] = [
          '#type' => 'checkboxes',
          '#title' => '曜日',
          '#options' => [
            'MO' => '月',
            'TU' => '火',
            'WE' => '水',
            'TH' => '木',
            'FR' => '金',
            'SA' => '土',
            'SU' => '日',
          ],
          '#default_value' => $repeat_weekday_default_value,
        ];
      } elseif ($repeat_value == 'monthly') {
        $repeat_month_day_default_value = 1;
        if ($rrule_obj && $rrule_obj->getByMonthDay()) {
          $repeat_month_day_default_value = $rrule_obj->getByMonthDay();
        }
        $form['repeat_details']['repeat_custom']['repeat_month_day'] = [
          '#type' => 'select',
          '#title' => '日',
          '#options' => array_combine(range(1, 31), range(1, 31)),
          '#default_value' => $repeat_month_day_default_value,
        ];
      }

      $repeat_end_default_value = 'none';
      if ($rrule_obj) {
        if ($rrule_obj->getCount()) {
          $repeat_end_default_value = 'count';
        } elseif ($rrule_obj->getUntil()) {
          $repeat_end_default_value = 'date';
        }
      }

      $form['repeat_details']['repeat_end'] = [
        '#type' => 'select',
        '#title' => t('終了'),
        '#required' => TRUE,
        '#options' => [
          'none' => 'しない',
          'count' => '回数',
          'date' => '指定日',
        ],
        '#default_value' => $repeat_end_default_value,
        '#ajax' => [
          'callback' => [$this, 'repeatEndCallback'],
          'wrapper' => 'repeat_end_details_wrapper',
        ],
      ];

      $form['repeat_details']['repeat_end_details'] = [
        '#type' => 'container',
        '#prefix' => '<div id="repeat_end_details_wrapper">',
        '#suffix' => '</div>',
      ];

      $repeat_end_value = $form_state->getValue('repeat_end', $repeat_end_default_value);
      if ($repeat_end_value == 'count') {
        $repeat_end_count_default_value = 1;
        if ($rrule_obj && $rrule_obj->getCount()) {
          $repeat_end_count_default_value = $rrule_obj->getCount();
        }

        $form['repeat_details']['repeat_end_details']['repeat_end_count'] = [
          '#type' => 'number',
          '#title' => t('終了まで回数'),
          '#required' => TRUE,
          '#default_value' => $repeat_end_count_default_value,
        ];
      } elseif ($repeat_end_value == 'date') {
        $formatter = Drupal::service('date.formatter');
        $today = $formatter->format(time(), 'custom', 'Y-m-d');
        $repeat_end_date_default_value = $today;
        if ($rrule_obj && $rrule_obj->getUntil()) {
          $repeat_end_date_default_value = $rrule_obj->getUntil()->format('Y-m-d');
        }

        $form['repeat_details']['repeat_end_details']['repeat_end_date'] = [
          '#type' => 'date',
          '#title' => t('終了日'),
          '#required' => TRUE,
          '#default_value' => $repeat_end_date_default_value,
        ];
      }
    }

    return $form;
  }
}
