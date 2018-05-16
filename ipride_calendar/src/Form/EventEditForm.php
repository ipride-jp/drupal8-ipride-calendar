<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\EventEditForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarEvent;

/**
 * Class EventEditForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class EventEditForm extends EventFormBase {
  public function title($calendar_id, $event_id) {
    list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id);
    if (!$calendar || !$event) {
      return 'イベント';
    }

    return 'イベント ' . $event->getSummary() . ' の編集';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view = NULL, $date = NULL, $calendar_id = NULL, $event_id = NULL) {
    list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id);
    if (!$calendar || !$event) {
      return $form;
    }

    $this->event = $event;
    $form = parent::buildForm($form, $form_state);

    $form['title']['#default_value'] = $event->getSummary();

    if ($event->getLocation()) {
      $form['location']['#default_value'] = $event->getLocation();
    }

    $form['all_day']['#default_value'] = $event->isAllDay();
    $form['calendar']['#default_value'] = $calendar->getCalendarID();
    $form['begin_datetime']['#default_value']
      = DrupalDateTime::createFromDateTime($event->getStartDateTime());

    $end_datetime = clone $event->getEndDateTime();
    if ($event->isAllDay()) {
      $end_datetime->modify('-1 day');
    }
    $form['end_datetime']['#default_value']
      = DrupalDateTime::createFromDateTime($end_datetime);

    $form['description']['#default_value']
      = str_replace('\n', "\n", $event->getDescription());

    $form = CalendarUtil::buildAlarmTable($event, $form, $form_state);

    if ($event->getStatus()) {
      $form['status'] = [
        '#type' => 'item',
        '#title' => t('ステータス'),
        '#markup' => $event->getStatus(),
      ];
    }

    $form = CalendarUtil::buildAttendeeTable($event, $form, $form_state, $event->getStatus() != 'CANCELLED');

    $form['save'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    $form['delete'] = [
      '#type' => 'link',
      '#title' => t('Delete'),
      '#url' => Url::fromRoute('ipride_calendar.event.delete', [
        'view' => $view,
        'date' => $date,
        'calendar_id' => $calendar_id,
        'event_id' => $event_id,
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $calendar_id = $request->attributes->get('calendar_id');
    $event_id = $request->attributes->get('event_id');
    $date = $request->attributes->get('date');
    $view = $request->attributes->get('view');

    try {
      $client = CalendarUtil::getCalendarClient();
      list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id, $client);

      if (!$calendar || !$event) {
        return;
      }

      $new_event_data = $this->getNewEventData($form_state, $event->getId());
      $new_calendar_id = $form_state->getValue('calendar');
      $raw_obj = $event->getRawObject();

      //if calendar is not changed, update
      if ($calendar_id == $new_calendar_id) {
        $client->setCalendar($calendar);
        $client->change($raw_obj->getHref(), $new_event_data, $raw_obj->getEtag());
      } else { //delete old event, then add new one
        $client->setCalendar($calendar);
        $client->delete($event->getRawObject()->getHref(), $event->getRawObject()->getEtag());

        $client = CalendarUtil::getCalendarClient();
        $new_calendar = $client->findCalendar($new_calendar_id);
        $client->setCalendar($new_calendar);
        $client->create($new_event_data);
        $event_id = $event->getId();
      }

      drupal_set_message(t('イベントを保存しました。'), 'status');
      $form_state->setRedirect('ipride_calendar.event.view', [
        'calendar_id' => $new_calendar_id,
        'event_id' => $event_id,
        'view' => $view,
        'date' => $date,
      ]);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return $form;
    }
  }
}
