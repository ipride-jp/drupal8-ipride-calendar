<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use DateTimeZone;

class ListCalendarForm extends MonthCalendarForm {
  public function title($date) {
    list($year, $month, $day) = $this->getYmd($date);
    return sprintf('カレンダーリスト(%d年%d月)', $year, $month);
  }

  public function getFormId() {
    return 'list_calendar_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $date = NULL) {
    $form = parent::buildForm($form, $form_state, $date);

    $form['#attached']['library'] = array_filter($form['#attached']['library'], function($library) {
      return $library != 'ipride_calendar/month_calendar';
    });

    $form['#attached']['library'][] = 'ipride_calendar/list_calendar';
    return $form;
  }

  protected function getViewId() {
    return 'list';
  }

  protected function getPagerRoute() {
    return 'ipride_calendar.list';
  }

  protected function buildCalendarTable($year, $month, $now, array $selected_calendar_ids) {
    $dates = $this->getMonthDates($year, $month, FALSE);
    $date_event_map = $this->getDateEventMap($dates, $selected_calendar_ids);
    $calendar_table = [
      '#theme' => 'list_calendar_table',
      '#dates' => $dates,
      '#date_event_map' => $date_event_map,
      '#calendar_colors' => $this->calendar_colors,
      '#date_class_strs' => $this->getDateCssClassesAsStr($dates, $now),
      '#holiday_texts' => $this->getDateHolidayTexts($dates),
      '#now' => $now,
    ];

    return $calendar_table;
  }
}
