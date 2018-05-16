<?php

namespace Drupal\ipride_calendar\Form;

use Drupal;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Session\AccountProxy;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarEvent;
use Drupal\ipride_calendar\Util\HolidayDateTime;

class EventAddForm extends EventFormBase {
  public function getFormId() {
    return 'event_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $request = $this->getRequest();
    $all_day = $request->get('all_day');
    if ($all_day) {
      $form['all_day']['#default_value'] = $all_day;
    }

    $now = new HolidayDateTime('now', new \DateTimeZone(drupal_get_user_timezone()));
    $next_hour = clone $now;
    $next_hour->setTime($now->hour, 0, 0);
    $next_hour->modify('+1 hour');

    $begin_date = $request->get('begin_date');
    $end_date = $request->get('end_date');
    $begin_time = $request->get('begin_time');
    $end_time = $request->get('end_time');
    if ($begin_date) {
      if ($all_day) {
        $begin_time = '00:00';
      }

      if (!$begin_time) {
        $begin_time = $next_hour->format('H:i');
      }

      $form['begin_datetime']['#default_value'] = new DrupalDateTime($begin_date . ' ' . $begin_time);
    }

    $next_hour->modify('+30 minute');
    if ($end_date) {
      if ($all_day) {
        $end_time = '00:00';
      }

      if (!$end_time) {
        $end_time = $next_hour->format('H:i');
      }

      $form['end_datetime']['#default_value'] = new DrupalDateTime($end_date . ' ' . $end_time);
    }

    $form = CalendarUtil::buildAlarmTable(NULL, $form, $form_state);
    $form = CalendarUtil::buildAttendeeTable(NULL, $form, $form_state, TRUE);

    $form['create'] = [
      '#type' => 'submit',
      '#value' => t('作成'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $date = $request->attributes->get('date');
    $view = $request->attributes->get('view');

    try {
      $client = CalendarUtil::getCalendarClient();
      $calendar_id = $form_state->getValue("calendar");
      $calendar = $client->findCalendar($calendar_id);
      if (!$calendar) {
        return;
      }

      $client->setCalendar($calendar);
      $event_obj = $client->create($this->getNewEventData($form_state));
      $event = new CalendarEvent($event_obj);
      drupal_set_message(t('イベントを登録しました。'), 'status');

      $this->notifyAttendees($event, $calendar_id, $view, $date);
      $form_state->setRedirect('ipride_calendar.event.view', [
        'calendar_id' => $calendar_id,
        'event_id' => $event->getId(),
        'view' => $view,
        'date' => $date,
      ]);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  private function getAttendeeMails($event) {
    $attendees = $event->getAttendees();
    if (empty($attendees)) {
      return;
    }

    $current_mail = Drupal::currentUser()->getEmail();
    $mails = [];
    foreach ($attendees as $attendee) {
      if ($current_mail == $attendee['mail']) {
        continue;
      }

      $mails[] = $attendee['mail'];
    }

    return $mails;
  }

  private function notifyAttendees($event, $calendar_id, $view, $date) {
    if (!$event) {
      return;
    }

    $mails = $this->getAttendeeMails($event);
    if (empty($mails)) {
      return;
    }

    $organizer = $event->getOrganizer();
    if (!$organizer) {
      return;
    }

    $organizer_name = CalendarUtil::getUserNameByMail($organizer);
    if (!$organizer_name) {
      return;
    }

    $summary = $event->getSummary();
    $all_day = $event->isAllDay() ? 'はい' : 'いいえ';

    $datetime_format = 'Y-m-d H:i';
    if ($event->isAllDay()) {
      $datetime_format = 'Y-m-d';
    }
    $begin_date = $event->getStartDateTime()->format($datetime_format);

    $end_date = clone $event->getEndDateTime();
    if ($event->isAllDay()) {
      $end_date->modify('-1 day');
    }
    $end_date = $end_date->format($datetime_format);

    global $base_root;
    $month_date = $event->getStartDateTime()->format('Ym01');;
    $month_view_url = $base_root . Url::fromRoute(
      'ipride_calendar.month', [
        'date' => $month_date,
      ])->toString();

    $renderer = Drupal::service('renderer');
    $mail_manager = Drupal::service('plugin.manager.mail');
    $langcode = Drupal::currentUser()->getPreferredLangcode();

    $params['subject'] = "イベント【" . $summary. "】に誘われました";

    //send mail
    $body = [
      '#theme' => 'mail_notify_event',
      '#organizer_name' => $organizer_name,
      '#summary' => $summary,
      '#all_day' => $all_day,
      '#begin_date' => $begin_date,
      '#end_date' => $end_date,
      '#month_view_url' => $month_view_url,
    ];
    $params['body'] = $renderer->render($body);
    $result = $mail_manager->mail('ipride_calendar', 'add_event',
      implode(',', $mails), $langcode, $params, NULL, TRUE);

    //dispatch message notify event
/*
    $message = "${organizer_name}様より、イベントに誘われました。";
    $attachment_fields = [[
      'title' => '開始日時',
      'value' => $begin_date,
      'short' => TRUE,
    ], [
      'title' => '終了日時',
      'value' => $end_date,
      'short' => TRUE,
    ]];

    if ($event->getRRule()) {
      $attachment_fields[] = [
        'title' => '繰り返し',
        'value' => $event->getRRuleReadable(),
        'short' => FALSE,
      ];
    }

    if ($event->getLocation()) {
      $attachment_fields[] = [
        'title' => '場所',
        'value' => $event->getLocation(),
        'short' => FALSE,
      ];
    }

    if ($event->getDescription()) {
      $attachment_fields[] = [
        'title' => 'メモ',
        'value' => $event->getDescription(),
        'short' => FALSE,
      ];
    }

    $attachment_actions = [[
      'name' => 'event',
      'text' => '出席',
      'type' => 'button',
      'value' => 'ACCEPTED',
    ], [
      'name' => 'event',
      'text' => '欠席',
      'type' => 'button',
      'style' => 'danger',
      'value' => 'DECLINED',
    ]];

    $attachments = [[
      'fallback' => $summary,
      'color' => 'good',
      'title' => $summary,
      'title_link' => $month_view_url,
      'footer' => 'カレンダー',
      'footer_icon' => $base_root. '/themes/'
        . \Drupal::theme()->getActiveTheme()->getName()
        . "/images/primary-menu-icon/calendar.png",
      'fields' => $attachment_fields,
      'actions' => $attachment_actions,
    ]];

    $logger = \Drupal::logger('ipride_calendar');
    foreach ($mails as $mail) {
      $user_event_id = $this->getUserEventId($mail, $event);
      if (!$user_event_id) {
        $logger->error("Cannot get user event id. Mail: $mail, Event ID: "
          . $event->getId());

        continue;
      }

      $attachments[0]['callback_id'] = $user_event_id;
      ipride_dispatch_message_notify_event($mail, $message, $attachments);
    }
*/

  }

  private function getUserEventId($mail, $orig_event) {
    $orig_event_id = $orig_event->getId();

    $old_user = \Drupal::currentUser();
    $user = user_load_by_mail($mail);
    if (!$user) {
      return null;
    }

    $account = new AccountProxy();
    $account->setAccount($user);
    \Drupal::getContainer()->set('current_user', $account);

    $client = CalendarUtil::getCalendarClient();
    $calendars = $client->findCalendars();
    $calendar = array_values($calendars)[0];
    if (!$calendar) {
      \Drupal::getContainer()->set('current_user', $old_user);
      return null;
    }

    $client->setCalendar($calendar);

    $time_from = CalendarUtil::formatDateTimeToCaldavStr($orig_event->getStartDateTime());
    $time_to = CalendarUtil::formatDateTimeToCaldavStr($orig_event->getEndDateTime());

    $raw_events = $client->getEvents($time_from, $time_to);
    $user_event_id = null;
    foreach ($raw_events as $raw_event) {
      $event = new CalendarEvent($raw_event);

      if ($event->getId() == $orig_event_id) {
        $user_event_id = $event->getFileBaseName();
        break;
      }
    }

    \Drupal::getContainer()->set('current_user', $old_user);
    return $user_event_id;
  }
}
