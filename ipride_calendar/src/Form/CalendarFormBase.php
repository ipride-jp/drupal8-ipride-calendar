<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\Component\Serialization\Json;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarEvent;
use Drupal\ipride_calendar\Util\HolidayDateTime;

use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;

use DateTimeZone;

abstract class CalendarFormBase extends FormBase {
  protected function buildFormCommonPart(array $form, FormStateInterface $form_state, $view, $date) {
    $all_calendars = $this->getAllCalendars();
    $this->calendar_colors = $this->getCalendarColors($all_calendars);
    $this->calendar_ids = $this->getCalendarIds($all_calendars);

    $destination = \Drupal::service('redirect.destination')->get();

    $form['#attached']['library'][] = 'ipride_calendar/calendar_common';
    $form['#attached']['library'][] = 'ipride_calendar/calendar_selection';
    $form['#attached']['drupalSettings']['ipride_calendar']['destination'] = $destination;

    $form['calendar_details'] = [
      '#type' => 'details',
      '#title' => 'カレンダー選択',
      '#open' => TRUE,
    ];

    $form['calendar_details']['calendars'] = [
      '#type' => 'table',
      '#header' => [
        'name' => '名前',
        'enabled' => '有効',
        'color' => '色',
        'operation' => '操作',
        'weight' => t('Weight')
      ],
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'mytable-order-weight',
        ),
      ),
    ];

    $route_params = [
      'view' => $view,
      'date' => $date,
      'destination' => $destination,
    ];

    $selected_calendar_ids = $this->getUserCalendarSelections();
    foreach ($all_calendars as $calendar) {
      $calendar_id = $calendar->getCalendarId();
      $color = $calendar->getRBGcolor();
      $row = [
        'name' => [
          '#type' => 'link',
          '#title' => $calendar->getDisplayName(),
          '#url' => Url::fromRoute(
            'ipride_calendar.calendar.view',
            $route_params + ['calendar_id' => $calendar_id]
          ),
        ],
        'enabled' => [
          '#type' => 'checkbox',
          '#default_value' => in_array($calendar_id, $selected_calendar_ids),
        ],
        'color' => [
          '#type' => 'textfield',
          '#size' => 8,
          '#value' => $color,
          '#attributes' => [
            'readonly' => 'readonly',
            'style' => sprintf('background-color: %s; color: %s',
              $color,
              CalendarUtil::selectWhiteOrBlackColor($color)
            ),
          ],
        ],
        'operation' => [
          '#type' => 'link',
          '#title' => t('Edit'),
          '#url' => Url::fromRoute(
            'ipride_calendar.calendar.edit',
            $route_params + ['calendar_id' => $calendar_id]
          ),
        ],
        'weight' => [
          '#type' => 'weight',
          '#default_value' => $calendar->getOrder(),
          '#attributes' => array('class' => array('mytable-order-weight')),
        ],
      ];

      $row['#weight'] = $calendar->getOrder();
      $row['#attributes']['class'][] = 'draggable';

      $form['calendar_details']['calendars'][$calendar_id] = $row;
    }


    $form['calendar_details']['calendars']['#default_value'] =
      array_combine($selected_calendar_ids, $selected_calendar_ids);

    $form['calendar_details']['add'] = [
      '#type' => 'link',
      '#title' => t('登録'),
      '#url' => Url::fromRoute(
        'ipride_calendar.calendar.add',
        $route_params
      ),
      '#attributes' => [
        'class' => ['button', 'button-action'],
      ],
    ];

    $form['calendar_details']['apply'] = [
      '#type' => 'submit',
      '#value' => '適用',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_calendar_ids = [];
    $new_order_calendar_ids = [];
    if ($calendar_values = $form_state->getValue('calendars')) {
      uasort($calendar_values, function($a, $b) {
        return $a['weight'] < $b['weight'] ? -1 : 1;
      });

      foreach ($calendar_values as $calendar_id => $calendar_value) {
        if ($calendar_value['enabled']) {
          $selected_calendar_ids[] = $calendar_id;
        }
      }
      $new_order_calendar_ids = array_keys($calendar_values);
    }

    //save calendar selections
    $current_user = user_load(\Drupal::currentUser()->id());
    if ($current_user->hasField('field_calendar_selections')) {
      $current_user->get('field_calendar_selections')->value
        = json_encode($selected_calendar_ids);

      $current_user->save();
    }

    //update calendar order
    $client = CalendarUtil::getCalendarClient();
    if ($this->calendar_ids != $new_order_calendar_ids) {
      foreach ($new_order_calendar_ids as $index => $calendar_id) {
        $this->updateCalendarOrder($client, $calendar_id, $index + 1); //start from 1
      }
    }
  }

  private function updateCalendarOrder($client, $calendar_id, $order) {
    $calendar = $client->findCalendar($calendar_id);
    $client->updateCalendar(
      $calendar_id,
      $calendar->getDisplayName(),
      $calendar->getRBGcolor(),
      $order
    );
  }

  protected function getDateEventMap($dates, $calendar_ids) {
    $first_date = $dates[0];
    $last_date = $dates[count($dates) - 1];
    $next_of_last_date = clone $last_date;
    $next_of_last_date->modify('+1 day');

    $event_objs = [];
    try {
      $client = CalendarUtil::getCalendarClient();
      $calendars = $client->findCalendars();
      foreach ($calendars as $calendar_id => $calendar) {
        if (!in_array($calendar_id, $calendar_ids)) {
          continue;
        }

        $client->setCalendar($calendar);
        $start_date_time = CalendarUtil::formatDateTimeToCaldavStr($first_date);
        $end_date_time = CalendarUtil::formatDateTimeToCaldavStr($next_of_last_date);
        $event_objs = array_merge($event_objs, $client->getEvents(
          $start_date_time,
          $end_date_time
        ));
      }
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    $events_filtered = [];
    foreach($event_objs as $event_obj) {
      $event = new CalendarEvent($event_obj);

      if ($event->getRRule()) {
        if (!$event->isRepeatBetween($first_date, $next_of_last_date)) {
          continue;
        }
      } else {
        if ($event->getEndDateTime() <= $first_date) {
          continue;
        }

        if ($event->getStartDateTime() >= $next_of_last_date) {
          continue;
        }
      }

      $events_filtered[] = $event;
    }

    $date_event_map = [];
    foreach ($dates as $date) {
      $next_date = clone $date;
      $next_date->modify('+1 day');

      $timestamp_str = strval($date->getTimestamp());
      $date_event_map[$timestamp_str] = [];
      foreach ($events_filtered as $event) {
        if ($event->getRRule()) { //repeat event
          if (!$event->isRepeatBetween($date, $next_date)) {
            continue;
          }
        } else {
          if ($event->getStartDateTime() >= $next_date) {
            continue;
          }
          if ($event->getEndDateTime() <= $date) {
            continue;
          }
        }

        $date_event_map[$timestamp_str][] = $event;
      }

      usort($date_event_map[$timestamp_str], function($a, $b) {
        if ($a->getStartDateTime() == $b->getStartDateTime()) {
          return $a->getSummary() > $b->getSummary();
        } else {
          return $a->getStartDateTime() > $b->getStartDateTime();
        }
      });
    }

    return $date_event_map;
  }

  protected function getUserCalendarSelections() {
    $selected_calendar_ids = NULL;

    $calendar_selections = user_load(\Drupal::currentUser()->id())
      ->get('field_calendar_selections')
      ->value;

    if (empty($calendar_selections)) {
      if (isset($this->calendar_ids)) {
        $selected_calendar_ids = $this->calendar_ids;
      }
    } else {
      $selected_calendar_ids = json_decode($calendar_selections, TRUE);
    }

    if (!$selected_calendar_ids) {
      $selected_calendar_ids = [];
    }

    return $selected_calendar_ids;
  }

  protected function getDateCssClassesAsStr($dates, $now) {
    $date_class_strs = [];
    $holiday_texts = [];
    foreach ($dates as $date) {
      $class_names = [];
      if ($date->dayOfWeek == 0 || $date->dayOfWeek == 6) {
        $class_names[] = 'weekend';
      }
      if ($date->holiday()) {
        $class_names[] = 'holiday';
        $holiday_texts[$date->timestamp] = $date->holiday();
      }
/* 年末年始休暇(12/29 〜 1/3)
      elseif(CalendarUtil::isNewYearHolidays($date->month, $date->day)) { 
        $class_names[] = 'holiday';
      }
*/
      if ($date == $now) {
        $class_names[] = 'today';
      }
      $date_class_strs[$date->timestamp] = implode(' ', $class_names);
    }

    return $date_class_strs;
  }

  protected function getDateHolidayTexts($dates) {
    $holiday_texts = [];
    foreach ($dates as $date) {
      if ($date->holiday()) {
        $holiday_texts[$date->timestamp] = $date->holiday();
      }
    }

    return $holiday_texts;
  }

  protected function getYmd($date_str) {
    $year_month_day = $date_str;
    $now = new HolidayDateTime('now', new DateTimeZone(drupal_get_user_timezone()));
    $year = $now->year;
    $month = $now->month;
    $day = $now->day;
    $now->setTime(0, 0, 0);
    if ($year_month_day != NULL && $year_month_day != 'current') {
      $date = $this->parseYearMonthDay($year_month_day);
      if ($date) {
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('j');
      } else {
        drupal_set_message(sprintf('年月日「%s」のフォーマットが不正です。正しいフォーマットはYYYYMMDDです。', $year_month_day), 'error');
      }
    }

    return [$year, $month, $day];
  }

  private function parseYearMonthDay($year_month_day) {
    return HolidayDateTime::createFromFormat(
      'Ymd',
      $year_month_day,
      new DateTimeZone(drupal_get_user_timezone())
    );
  }

  private function getAllCalendars() {
    try {
      $client = CalendarUtil::getCalendarClient();
      return $client->findCalendars();
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return [];
  }

  private function getCalendarOptions($calendars) {
    $calendar_options = [];
    foreach ($calendars as $calendar) {
      $calendar_options[$calendar->getCalendarID()] = $calendar->getDisplayName();
    }

    return $calendar_options;
  }

  private function getCalendarColors($calendars) {
    $calendar_colors = [];
    foreach ($calendars as $calendar) {
      $calendar_colors[$calendar->getCalendarID()] = [
        'background-color' => $calendar->getRBGcolor(),
        'color' => CalendarUtil::selectWhiteOrBlackColor($calendar->getRBGcolor()),
      ];
    }

    return $calendar_colors;
  }

  private function getCalendarIds($calendars) {
    $calendar_ids = [];

    foreach ($calendars as $calendar) {
      $calendar_ids[] = $calendar->getCalendarID();
    }

    return $calendar_ids;
  }
}
