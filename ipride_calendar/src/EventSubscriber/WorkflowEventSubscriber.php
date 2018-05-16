<?php

namespace Drupal\ipride_calendar\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;

/**
 * Class WorkflowEventSubscriber.
 *
 * @package Drupal\ipride_calendar
 */
class WorkflowEventSubscriber implements EventSubscriberInterface {


  /**
   * Constructor.
   */
  public function __construct() {

  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events['ipride_workflow.approved'][] = ['onWorkflowApproved'];

    return $events;
  }

  public function onWorkflowApproved($event) {
    $node = $event->getNode();
    if ($node->getType() != 'vacation_application_form') {
      return;
    }

    try {
      $client = CalendarUtil::getCalendarClient();
      $calendars = $client->findCalendars();
      if (empty($calendars)) {
        return;
      }

      $client->setCalendar(array_values($calendars)[0]);
      $client->create($this->getEventData($node));

      drupal_set_message(t('カレンダーに休暇届申請関連のイベントを登録しました。'), 'status');
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }
  }

  private function getEventData($node) {
    $uuid = \Drupal::service('uuid')->generate();

    $summary = $node->get('field_vacation_classification')->value;

    global $base_root;
    $description = str_replace("\n", '\n', $node->get('field_reason')->value);
    $description .= '\n\n' . $base_root . $node->toUrl()->toString();

    $begin_datetime = new \DateTime($node->get('field_start_time')->value);
    $end_datetime = new \DateTime($node->get('field_end_time')->value);

    $now_time = time();
    $now = CalendarUtil::formatTimestampToCaldavStr($now_time);

    $route = json_decode($node->get('field_workflow_route')->value, TRUE);
    $attendee_uids = $route['approvers'];
    $owner_uid = $node->getOwnerId();

    $mails_added = [];

    $mail = \Drupal::currentUser()->getEmail();
    $organizer_text = 'ORGANIZER;RSVP=TRUE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:'
      . $mail;
    $mails_added[] = $mail;

    $attendee_uids[] = $owner_uid;
    $attendees_text = "";
    foreach ($attendee_uids as $attendee_uid) {
      $mail = user_load($attendee_uid)->getEmail();

      if (in_array($mail, $mails_added)) {
        continue;
      }

      $attendees_text .= sprintf(
        "ATTENDEE;PARTSTAT=ACCEPTED;ROLE=REQ-PARTICIPANT;SCHEDULE-STATUS=2.0:mailto:%s\n",
        $mail
      );

      $mails_added[] = $mail;
    }

    $attendees_text = trim($attendees_text);

    //終日
    $begin_date = $begin_datetime->format('Ymd');
    $dtstart = "VALUE=DATE:${begin_date}";

    $end_datetime->modify('+1 day');
    $end_date = $end_datetime->format('Ymd');
    $dtend = "VALUE=DATE:${end_date}";

    $summary = '【' .CalendarUtil::getUserName($owner_uid) . '】' . $summary;
    $event_data = "BEGIN:VCALENDAR
PRODID:-//Sabre//Sabre VObject 3.4.6//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:$uuid
DTSTAMP:$now
SUMMARY:$summary
DESCRIPTION: $description
CLASS:PUBLIC
TRANSP:OPAQUE
$organizer_text
$attendees_text
DTSTART;$dtstart
DTEND;$dtend
CREATED:$now
LAST-MODIFIED:$now
END:VEVENT
END:VCALENDAR
";

    return $event_data;
  }

}
