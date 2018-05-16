<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\HolidayDateTime;

use DateTimeZone;

class MonthCalendarForm extends CalendarFormBase {
  public function title($date) {
    list($year, $month, $day) = $this->getYmd($date);
    return sprintf('カレンダー月(%d年%d月)', $year, $month);
  }

  public function getFormId() {
    return 'month_calendar_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $date = NULL) {
    $form = $this->buildFormCommonPart($form, $form_state, $this->getViewId(), $date);
    $form['#attached']['library'][] = 'ipride_calendar/month_calendar';

    $now = new HolidayDateTime('now', new DateTimeZone(drupal_get_user_timezone()));
    $now->setTime(0, 0, 0);

    list($year, $month, $day) = $this->getYmd($date);

    $next_month = clone $now;
    $next_month->setDate($year, $month, 1);
    $next_month->modify('+1 month');

    $prev_month = clone $now;
    $prev_month->setDate($year, $month, 1);
    $prev_month->modify('-1 month');

    $form['prev_month_top'] = [
      '#type' => 'link',
      '#title' => '<<前月',
      '#url' => Url::fromRoute($this->getPagerRoute(), ['date' => $prev_month->format('Ymd')]),
      '#attributes' => [
        'class' => ['pager_link'],
      ]
    ];

    if ($now->year == $year && $now->month == $month) {
      $form['current_month_top'] = [
        '#markup' => '今月',
      ];
    } else {
      $form['current_month_top'] = [
        '#type' => 'link',
        '#title' => '今月',
        '#url' => Url::fromRoute($this->getPagerRoute(), ['date' => 'current']),
      ];
    }
    $form['current_month_top']['#attributes'] = [
      'class' => ['pager_link'],
    ];

    $form['next_month_top'] = [
      '#type' => 'link',
      '#title' => '翌月>>',
      '#url' => Url::fromRoute($this->getPagerRoute(), ['date' => $next_month->format('Ymd')]),
      '#attributes' => [
        'class' => ['pager_link'],
      ]
    ];

    $form['current_month_desc_top'] = [
      '#markup' => sprintf('%d年%d月', $year, $month),
    ];

    $form['calendar_table'] = $this->buildCalendarTable(
      $year,
      $month,
      $now,
      $this->getUserCalendarSelections()
    );

    $form['prev_month_bottom'] = $form['prev_month_top'];
    $form['current_month_bottom'] = $form['current_month_top'];
    $form['next_month_bottom'] = $form['next_month_top'];
    $form['current_month_desc_bottom'] = $form['current_month_desc_top'];

    return $form;
  }

  protected function getViewId() {
    return 'month';
  }

  protected function getPagerRoute() {
    return 'ipride_calendar.month';
  }

  protected function buildCalendarTable($year, $month, $now, array $selected_calendar_ids) {
    $calendar_table = [
      '#type' => 'table',
      '#header' => ['日', '月', '火', '水', '木', '金', '土'],
      '#sticky' => false,
    ];

    $dates = $this->getMonthDates($year, $month);
    $date_event_map = $this->getDateEventMap($dates, $selected_calendar_ids);

    foreach ($dates as $index => $date) {
      $week_index = intval($index / 7);

      //title
      $cell = [];
      $cell[] = ['#markup' => sprintf('<div>%s</div>', $date->day)];
      $class_names = [];
      if ($date->dayOfWeek == 0 || $date->dayOfWeek == 6) {
        $class_names[] = 'weekend';
      }
      if ($date->holiday() /*|| CalendarUtil::isNewYearHolidays($date->month, $date->day)*/) {
        $class_names[] = 'holiday';
        $cell[] = [
          '#markup' => sprintf('<div class="holiday-text">%s</div>', $date->holiday()),
        ];
      }

      if ($date->month != $month) {
        $class_names[] = 'not-this-month';
      }

      if ($date == $now) {
        $class_names[] = 'today';
      }

      $base_cell = [
        '#markup' => sprintf('<div class="data %s" data-ymd="%s"></div>',
          implode(' ', $class_names),
          $date->format('Y-m-d')
        ),
      ];
      $cell[] = $base_cell;

      $calendar_table[$week_index * 2][$index % 7] = $cell;

      //content
      $events = $date_event_map[strval($date->getTimestamp())];

      $cell = [];
      $cell[] = $base_cell;

      foreach ($events as $event) {
        $text = '';
        $start_date_time = $event->getStartDateTimeOfDate($date);
        if (!$event->isAllDay($date)) {
          $text .= '<span class="event-time">' . $start_date_time->format('H:i ') . '</span>';
        }
        $text .= '<span class="trunc-str">' . $event->getSummaryEscaped() . '</span>';
        if (!empty($event->getAlarms())) {
          $text .= '<span><i class="fa fa-bell-o event-icon"></i></span>';
        }

        if ($event->getRRule()) {
          $text .= '<span><i class="fa fa-repeat event-icon"></i></span>';
        }

        $event_id = $event->getFileBaseName();

        $class_str = 'event';
        $attendee = $event->getAttendee(\Drupal::currentUser()->getEmail());
        $class_str .= ' ' . $event->getStatusClassName();

        $cell[] = [
          '#markup' => sprintf(
            '<div class="%s" data-color="%s" data-background-color="%s" data-calendar-id="%s" data-event-id="%s">%s</div>',
            $class_str,
            $this->calendar_colors[$event->getCalendarId()]['color'],
            $this->calendar_colors[$event->getCalendarId()]['background-color'],
            $event->getCalendarId(),
            $event_id,
            $text
          ),
        ];

      }

      $calendar_table[$week_index * 2 + 1][$index % 7] = $cell;
    }

    return $calendar_table;
  }

  protected function getMonthDates($year, $month, $include_before_after = TRUE) {
    $current_timezone = drupal_get_user_timezone();
    $first_day_date = new HolidayDateTime('now', new DateTimeZone($current_timezone));
    $first_day_date->setDate($year, $month, 1);
    $first_day_date->setTime(0, 0, 0);

    if ($first_day_date->dayOfWeek == 0) {
      $first_sunday_date = $first_day_date;
    } else {
      $first_sunday_date = clone $first_day_date;
      $first_sunday_date->modify('last Sunday');
    }

    $last_day_date = clone $first_day_date;
    $last_day_date->modify('last day of');

    if ($last_day_date->dayOfWeek == 6) {
      $last_saturday_date = $last_day_date;
    } else {
      $last_saturday_date = clone $last_day_date;
      $last_saturday_date->modify('next Saturday');
    }

    $dates = [];

    //before
    if ($include_before_after) {
      if ($first_sunday_date != $first_day_date) {
        $last_day_of_last_month_date = clone $first_sunday_date;
        $last_day_of_last_month_date->modify('last day of');
        for ($date = clone $first_sunday_date; $date <= $last_day_of_last_month_date; ) {
          $dates[] = $date;
          $date = clone $date;
          $date->modify('+1 day');
        }
      }
    }

    for ($date = $first_day_date; $date <= $last_day_date; ) {
      $dates[] = $date;
      $date = clone $date;
      $date->modify('+1 day');
    }

    //after
    if ($include_before_after) {
      if ($last_saturday_date != $last_day_date) {
        $first_day_of_next_month_date = clone $last_saturday_date;
        $first_day_of_next_month_date->modify('first day of');
        for ($date = clone $first_day_of_next_month_date; $date <= $last_saturday_date; ) {
          $dates[] = $date;
          $date = clone $date;
          $date->modify('+1 day');
        }
      }
    }

    return $dates;
  }
}
