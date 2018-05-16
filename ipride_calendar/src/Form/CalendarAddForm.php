<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\user\Entity\User;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;

class CalendarAddForm extends FormBase {
  public function getFormId() {
    return 'calendar_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#required' => TRUE,
    ];

    $form['color'] = [
      '#type' => 'textfield',
      '#title' => t('Color'),
      '#required' => TRUE,
      '#size' => 8,
      '#default_value' => '#000000',
      '#attributes' => [
        'class' => ['color'],
        'readonly' => 'readonly',
      ],
    ];

    $form = CalendarUtil::buildCalendarAttendeeTable(NULL, $form, $form_state, TRUE);

    $form['create'] = [
      '#type' => 'submit',
      '#value' => t('作成'),
    ];

    $form['#attached']['library'][] = 'ipride_calendar/calendar';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $date = $request->attributes->get('date');
    $view = $request->attributes->get('view');

    try {
      $client = CalendarUtil::getCalendarClient();
      $uuid = Drupal::service('uuid')->generate();
      $client->createCalendar(
        $uuid,
        $form_state->getValue('name'),
        $form_state->getValue('color')
      );

      //share
      $attendees = $form_state->getValue('attendees');
      $attendees_data = [];
      foreach ($attendees as $attendee) {
        if (!$attendee['name']) {
          continue;
        }
        $attendees_data[] = [
          'mail' => user_load($attendee['name'])->getEmail(),
          'permission' => $attendee['permission'],
        ];
      }
      $client->shareCalendar($uuid, $form_state->getValue('name'), $attendees_data);

      drupal_set_message(t('カレンダーを登録しました。'), 'status');

      $form_state->setRedirect('ipride_calendar.calendar.view', [
        'calendar_id' => $uuid,
        'view' => $view,
        'date' => $date,
      ]);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }
}
