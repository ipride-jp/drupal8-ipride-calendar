<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\EventDeleteForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarEvent;

/**
 * Class EventDeleteForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class EventDeleteForm extends FormBase {
  public function title($calendar_id, $event_id) {
    list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id);
    if (!$calendar || !$event) {
      return 'イベント';
    }

    return 'イベント ' . $event->getSummary() . ' を本当に削除しますか？';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $calendar_id = NULL, $event_id = NULL) {
    list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id);
    if (!$calendar || !$event) {
      return $form;
    }

    $form['message'] = [
      '#type' => 'item',
      '#markup' => t('This action cannot be undone.'),
    ];

    $form['delete'] = [
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#name' => 'delete',
    ];

    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'cancel',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $activity = $form_state->getTriggeringElement()['#name'];

    $request = $this->getRequest();
    $calendar_id = $request->attributes->get('calendar_id');
    $event_id = $request->attributes->get('event_id');
    $view = $request->attributes->get('view');
    $date = $request->attributes->get('date');

    //cancel
    if ($activity == 'cancel') {
      $form_state->setRedirect('ipride_calendar.event.view', [
        'calendar_id' => $calendar_id,
        'event_id' => $event_id,
        'view' => $view,
        'date' => $date,
      ]);

      return;
    }

    //delete
    try {
      $client = CalendarUtil::getCalendarClient();
      list($calendar, $event) = CalendarUtil::getCalendarAndEvent($calendar_id, $event_id, $client);
      if (!$calendar || !$event) {
        return;
      }

      $client->setCalendar($calendar);
      $client->delete($event->getRawObject()->getHref(), $event->getRawObject()->getEtag());

      drupal_set_message(t('イベントを削除しました。'), 'status');

      $route_name = 'ipride_calendar.' . $view;
      $form_state->setRedirect($route_name, [
        'date' => $date,
      ]);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return;
    }
  }
}
