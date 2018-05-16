<?php

namespace Drupal\ipride_calendar\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarToDo;

/**
 * Class ToDoForm.
 *
 * @package Drupal\ipride_calendar\Form
 */
class ToDoForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'to_do_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->calendars = [];
    try {
      $client = CalendarUtil::getCalendarClient();
      $this->calendars = $client->findCalendars();

      $this->calendar_to_dos = [];
      foreach ($this->calendars as $calendar_id => $calendar) {
        $client->setCalendar($calendar);
        $to_dos = $client->getTODOs();
        $this->calendar_to_dos[$calendar_id] = $to_dos;
      }

    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    $form['#attached']['library'][] = 'ipride_calendar/to_do';
    $form = $this->buildCalendarSelection($form);
    $form = $this->buildToDoTable($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm($form_state->getValue('calendar_table'));
    dpm($form_state->getUserInput('calendar_radio'));
  }

  private function buildCalendarSelection($form) {
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
        'number' => '件数',
      ],
    ];

    $table =& $form['calendar_details']['calendars'];

    foreach ($this->calendars as $calendar_id => $calendar) {
      $color = $calendar->getRBGcolor();

      $table[$calendar_id] = [
        'name' => [
          '#markup' => $calendar->getDisplayName()
        ],
        'enabled' => [
          '#type' => 'checkbox',
          '#default_value' => TRUE,
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
        'number' => [
          '#markup' => count($this->calendar_to_dos[$calendar_id]),
        ],
      ];
    }

    $form['calendar_details']['apply'] = [
      '#type' => 'submit',
      '#value' => '適用',
    ];

    return $form;
  }

  private function buildToDoTable($form) {
    $form['to_do_table'] = [
      '#type' => 'table',
      '#header' => [
        'complete' => '完了',
        'name' => '件名',
      ],
    ];

    $table =& $form['to_do_table'];
    foreach ($this->calendar_to_dos as $calendar_id => $to_dos) {
      foreach ($to_dos as $to_do) {
        $cal_to_do = new CalendarToDo($to_do);
        $table[] = [
          'complete' => [
            '#type' => 'checkbox',
          ],
          'name' => [
            '#markup' => sprintf(
              '<span class="to-do-%s">%s</span>',
              strtolower($cal_to_do->getStatus()),
              $cal_to_do->getSummary()
            ),
          ],
        ];
      }
    }

    return $form;
  }
}
