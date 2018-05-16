<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\CalendarDeleteForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;

/**
 * Class CalendarDeleteForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class CalendarDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $calendar_id = NULL) {
    $calendar = CalendarUtil::getCalendarById($calendar_id);
    if (!$calendar) {
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
    $date = $request->attributes->get('date');
    $view = $request->attributes->get('view');

    //cancel
    if ($activity == 'cancel') {
      $form_state->setRedirect('ipride_calendar.calendar.view', [
        'calendar_id' => $calendar_id,
        'view' => $view,
        'date' => $date,
      ]);

      return;
    }

    //delete
    try {
      $client = CalendarUtil::getCalendarClient();
      $calendar = $client->deleteCalendar($calendar_id);

      drupal_set_message(t('カレンダーを削除しました。'), 'status');
      $route_name = 'ipride_calendar.' . $view;
      $form_state->setRedirect(
        $route_name, [
          'date' => $date,
        ]);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return;
    }
  }


  public function title($calendar_id) {
    $calendar = CalendarUtil::getCalendarById($calendar_id);
    if (!$calendar) {
      return 'カレンダー';
    }

    return 'カレンダー ' . $calendar->getDisplayName() . ' を本当に削除しますか？';
  }
}
