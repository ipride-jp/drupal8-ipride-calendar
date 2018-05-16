<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Form\CalendarEditForm.
 */

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;

/**
 * Class CalendarEditForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class CalendarEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calendar_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view = NULL, $date = NULL, $calendar_id = NULL) {
    $calendar = CalendarUtil::getCalendarById($calendar_id);
    if (!$calendar) {
      return $form;
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#required' => TRUE,
      '#default_value' => $calendar->getDisplayName(),
    ];

    $form['color'] = [
      '#type' => 'textfield',
      '#title' => t('Color'),
      '#required' => TRUE,
      '#size' => 8,
      '#default_value' => $calendar->getRBGcolor(),
      '#attributes' => [
        'class' => ['color'],
        'readonly' => 'readonly',
      ],
    ];

    $form = CalendarUtil::buildCalendarAttendeeTable($calendar, $form, $form_state, TRUE);

    $form['save'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    $form['delete'] = [
      '#type' => 'link',
      '#title' => t('Delete'),
      '#url' => Url::fromRoute('ipride_calendar.calendar.delete', [
        'view' => $view,
        'date' => $date,
        'calendar_id' => $calendar_id,
      ]),
    ];

    $form['#attached']['library'][] = 'ipride_calendar/calendar';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $calendar_id = $request->attributes->get('calendar_id');
    $date = $request->attributes->get('date');
    $view = $request->attributes->get('view');

    try {
      $client = CalendarUtil::getCalendarClient();
      $client->updateCalendar(
        $calendar_id,
        $form_state->getValue('name'),
        $form_state->getValue('color')
      );

      //share
      $calendar = $client->findCalendar($calendar_id);
      if (\Drupal::currentUser()->getEmail() == $calendar->getOrganizer()) {
        $attendees_data = [];
        if (isset($form_state->getBuildInfo()['attendees_data'])) {
          $attendees_data = $form_state->getBuildInfo()['attendees_data'];
        }

        $attendees_to_share = [];
        $attendees = $form_state->getValue('attendees');
        foreach($attendees_data as $index => $attendee_data) {
          $mail = $attendee_data['mail'];

          if ($index == 0) { //organizer
            continue;
          }

          if (!empty($attendees[$index]) && !empty($attendees[$index]['name'])) {
            $mail = user_load($attendees[$index]['name'])->getEmail();
          }

          if (empty($mail)) {
            continue;
          }

          if (key_exists($mail, $attendees_to_share)) {
            continue;
          }

          $attendees_to_share[$mail] = [
            'mail' => $mail,
            'permission' => $attendees[$index]['permission'],
          ];
        }

        $client->shareCalendar(
          $calendar_id,
          $form_state->getValue('name'),
          array_values($attendees_to_share)
        );
      }

      drupal_set_message(t('カレンダーを保存しました。'), 'status');
      $form_state->setRedirect('ipride_calendar.calendar.view', [
        'view' => $view,
        'date' => $date,
        'calendar_id' => $calendar_id
      ]);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  public function title($calendar_id) {
    $calendar = CalendarUtil::getCalendarById($calendar_id);
    if (!$calendar) {
      return 'カレンダー';
    }

    return 'カレンダー ' . $calendar->getDisplayName() . ' の編集';
  }
}
