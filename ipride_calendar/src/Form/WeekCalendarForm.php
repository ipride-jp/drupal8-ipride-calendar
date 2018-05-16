<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\ipride_calendar\Util\HolidayDateTime;

use DateTimeZone;

class WeekCalendarForm extends CalendarFormBase {
  public function title($date) {
    list($year, $month, $day) = $this->getYmd($date);

    $this_week = new HolidayDateTime('now', new DateTimeZone(drupal_get_user_timezone()));
    $this_week->setDate($year, $month, $day);
    $this_week->setTime(0, 0, 0);
    if ($this_week->dayOfWeek != 0) {
      $this_week->modify('last Sunday');
    }

    return sprintf('カレンダー週(%s年%d月第%s週)', $year, intval($month), $this_week->weekOfYear);
  }

  public function getFormId() {
    return 'week_calendar_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $date = NULL) {
    $form = $this->buildFormCommonPart($form, $form_state, 'week', $date);
    $form['#attached']['library'][] = 'ipride_calendar/week_calendar';

    list($year, $month, $day) = $this->getYmd($date);

    $now = new HolidayDateTime('now', new DateTimeZone(drupal_get_user_timezone()));
    $now->setTime(0, 0, 0);
    $this_week = clone $now;
    $this_week->setDate($year, $month, $day);
    if ($this_week->dayOfWeek != 0) {
      $this_week->modify('last Sunday');
    }
    $next_week = clone $this_week;
    $next_week->modify('+1 weeks');

    $prev_week = clone $this_week;
    $prev_week->modify('-1 weeks');

    $form['prev_week_top'] = [
      '#type' => 'link',
      '#title' => '<<前週',
      '#url' => Url::fromRoute('ipride_calendar.week', [
        'date' => $prev_week->format('Ymd')
      ]),
      '#attributes' => [
        'class' => ['pager_link'],
      ]
    ];

    if ($now->weekOfYear == $this_week->weekOfYear) {
      $form['current_week_top'] = [
        '#markup' => '今週',
      ];
    } else {
      $form['current_week_top'] = [
        '#type' => 'link',
        '#title' => '今週',
        '#url' => Url::fromRoute('ipride_calendar.week', [
          'date' => 'current',
        ]),
      ];
    }
    $form['current_week_top']['#attributes'] = [
      'class' => ['pager_link'],
    ];

    $form['next_week_top'] = [
      '#type' => 'link',
      '#title' => '翌週>>',
      '#url' => Url::fromRoute('ipride_calendar.week', [
        'date' => $next_week->format('Ymd')
      ]),
      '#attributes' => [
        'class' => ['pager_link'],
      ]
    ];

    $form['current_week_desc_top'] = [
      '#markup' => sprintf(
        '%s年%d月第%s週',
        $year,
        intval($month),
        $this_week->weekOfYear
      )
    ];

    $dates = $this->getWeekDates($this_week);
    $date_event_map = $this->getDateEventMap(
      $dates,
      $this->getUserCalendarSelections()
    );

    $date_class_strs = $this->getDateCssClassesAsStr($dates, $now);
    $holiday_texts = $this->getDateHolidayTexts($dates);

    $form['calendar_table'] = [
      '#theme' => 'week_calendar_table',
      '#dates' => $dates,
      '#date_event_map' => $date_event_map,
      '#calendar_colors' => $this->calendar_colors,
      '#date_class_strs' => $date_class_strs,
      '#holiday_texts' => $holiday_texts,
    ];

    $form['prev_week_bottom'] = $form['prev_week_top'];
    $form['current_week_bottom'] = $form['current_week_top'];
    $form['next_week_bottom'] = $form['next_week_top'];
    $form['current_week_desc_bottom'] = $form['current_week_desc_top'];

    return $form;
  }

  private function getWeekDates($this_week) {
    $dates = [];
    $date = clone $this_week;
    for ($index = 0; $index < 7; $index++) {
      $dates[] = clone $date;
      $date->modify('+1 day');
    }

    return $dates;
  }
}
