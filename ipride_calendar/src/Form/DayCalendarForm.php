<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\HolidayDateTime;

use DateTimeZone;

class DayCalendarForm extends CalendarFormBase {
  public function title($date) {
    list($year, $month, $day) = $this->getYmd($date);
    return sprintf('カレンダー日(%d年%d月%d日)', $year, $month, $day);
  }

  public function getFormId() {
    return 'day_calendar_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $date = NULL) {
    $form = $this->buildFormCommonPart($form, $form_state, 'day', $date);
    $form['#attached']['library'][] = 'ipride_calendar/day_calendar';

    $now = new HolidayDateTime('now', new DateTimeZone(drupal_get_user_timezone()));
    $form['#attached']['drupalSettings']['ipride_calendar']['now_hour'] = $now->hour;

    $now->setTime(0, 0, 0);

    list($year, $month, $day) = $this->getYmd($date);

    $the_day = clone $now;
    $the_day->setDate($year, $month, $day);

    $tomorrow = clone $the_day;
    $tomorrow->modify('+1 day');

    $yesterday = clone $the_day;
    $yesterday->modify('-1 day');

    $form['yesterday_top'] = [
      '#type' => 'link',
      '#title' => '<<前日',
      '#url' => Url::fromRoute('ipride_calendar.day', [
        'date' => $yesterday->format('Ymd')
      ]),
      '#attributes' => [
        'class' => ['pager_link'],
      ]
    ];

    if ($now == $the_day) {
      $form['today_top'] = [
        '#markup' => '今日',
      ];
    } else {
      $form['today_top'] = [
        '#type' => 'link',
        '#title' => '今日',
        '#url' => Url::fromRoute('ipride_calendar.day', [
          'date' => 'current',
        ]),
      ];
    }
    $form['today_top']['#attributes'] = [
      'class' => ['pager_link'],
    ];

    $form['tomorrow_top'] = [
      '#type' => 'link',
      '#title' => '翌日>>',
      '#url' => Url::fromRoute('ipride_calendar.day', [
        'date' => $tomorrow->format('Ymd')
      ]),
      '#attributes' => [
        'class' => ['pager_link'],
      ]
    ];

    $form['today_desc_top'] = [
      '#markup' => sprintf(
        '%d年%d月%d日',
        $year,
        $month,
        $day
      )
    ];

    $date_event_map = $this->getDateEventMap(
      [$the_day],
      $this->getUserCalendarSelections()
    );
    $events = $date_event_map[$the_day->getTimestamp()];

    $hours = $this->getHours();
    $all_day_events = $this->getAllDayEvents($events, $the_day);

    $form['calendar_table'] = [
      '#theme' => 'day_calendar_table',
      '#hours' => $hours,
      '#all_day_events' => $all_day_events,
      '#normal_events' => array_diff($events, $all_day_events),
      '#calendar_colors' => $this->calendar_colors,
      '#date' => $the_day,
    ];

    $form['yesterday_bottom'] = $form['yesterday_top'];
    $form['today_bottom'] = $form['today_top'];
    $form['tomorrow_bottom'] = $form['tomorrow_top'];
    $form['today_desc_bottom'] = $form['today_desc_top'];

    return $form;
  }

  private function getHours() {
    $hours = [];
    for ($index = 0; $index < 24; $index++) {
      $hours[] = $index;
    }

    return $hours;
  }

  private function getAllDayEvents($events, $date) {
    $events_filtered = [];
    foreach ($events as $event) {
      if (!$event->isAllDay($date)) {
        continue;
      }

      $events_filtered[] = $event;
    }

    return $events_filtered;
  }
}
