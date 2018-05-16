<?php

namespace Drupal\ipride_calendar\Util;

use DateTimeZone;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;

use Drupal\ipride_calendar\Util\HolidayDateTime;
use Drupal\ipride_calendar\Util\Util as CalendarUtil;

use Recurr\Rule;
use Recurr\Transformer\TextTransformer;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Translator;

class CalendarEvent {
  private $raw_obj;
  private $detail;

  public function __construct($calDAVObject) {
    $this->raw_obj = $calDAVObject;
    $this->detail = $this->raw_obj->getEvent();
  }

  public function getId() {
    return $this->detail['UID'];
  }

  public function getRawObject() {
    return $this->raw_obj;
  }

  public function getStartDateTime() {
    return $this->getDateTime(
      $this->detail['DTSTART'],
      $this->detail['DTSTART_TIMEZONE']
    );
  }

  public function getStartDateTimeOfDate($date) {
    if ($this->getRRule()) {
      return $this->getStartDateTime();
    }

    $the_day_date = clone $date;
    $the_day_date->setTime(0, 0, 0);

    $start_date_time = $this->getStartDateTime();
    if ($the_day_date <= $start_date_time) {
      return $start_date_time;
    } else {
      return $the_day_date;
    }
  }

  public function getEndDateTime() {
    return $this->getDateTime(
      $this->detail['DTEND'],
      $this->detail['DTEND_TIMEZONE']
    );
  }

  public function getEndDateTimeOfDate($date) {
    if ($this->getRRule()) {
      return $this->getEndDateTime();
    }

    $next_day_date = clone $date;
    $next_day_date->setTime(0, 0, 0);
    $next_day_date->modify('+1 day');

    $end_date_time = $this->getEndDateTime();
    if ($next_day_date >= $end_date_time) {
      return $end_date_time;
    } else {
      return $next_day_date;
    }
  }

  public function getTimeInterval($date) {
    $time_interval = $this->getDateIntervalObj($date);
    if (!$time_interval) {
      return '0分';
    }

    $hour = intval($time_interval->format('%h'));
    $min = intval($time_interval->format('%i'));
    $interval_text = '';
    if ($hour !== 0) {
      $interval_text .= $hour . '時間';
    }

    if ($min !== 0) {
      $interval_text .= $min . '分';
    }

    return $interval_text;
  }

  public function getTimeIntervalInMin($date) {
    $time_interval = $this->getDateIntervalObj($date);
    $hour = intval($time_interval->format('%h'));
    $min = intval($time_interval->format('%i'));

    return $hour * 60 + $min;
  }

  private function getDateIntervalObj($date) {
    $start_date_time = $this->getStartDateTimeOfDate($date);
    $end_date_time = $this->getEndDateTimeOfDate($date);

    if ($start_date_time >= $end_date_time) {
      return FALSE;
    }

    return $start_date_time->diff($end_date_time);
  }

  public function getSummary() {
    return trim($this->detail['SUMMARY']);
  }

  public function getSummaryEscaped() {
    return Html::escape($this->getSummary());
  }

  public function getLocation() {
    return isset($this->detail['LOCATION']) ? trim($this->detail['LOCATION']) : '';
  }

  public function getLocationEscaped() {
    return Html::escape($this->getLocation());
  }

  public function getFileBaseName() {
    return basename($this->raw_obj->getHref(), '.ics');
  }

  public function isAllDay($date = NULL) {
    if ($this->detail['DTSTART_TIMEZONE'] == NULL) {
      return TRUE;
    }

    if ($this->getRRule()) {
      return FALSE;
    }

    if (!$date) {
      return FALSE;
    }

    $the_day_date = clone $date;
    $the_day_date->setTime(0, 0, 0);

    $next_day_date = clone $the_day_date;
    $next_day_date->modify('+1 day');
    if ($this->getStartDateTime() <= $the_day_date && $this->getEndDateTime() >= $next_day_date) {
      return TRUE;
    }

    return FALSE;
  }

  public function getIsAllDay($date) {
    return $this->isAllDay($date);
  }

  public function getCalendarId() {
    return $this->raw_obj->getCalendarId();
  }

  public function getDescription() {
    return isset($this->detail['DESCRIPTION']) ? trim($this->detail['DESCRIPTION']) : '';
  }

  public function getDescriptionEscaped() {
    return Html::escape($this->getDescription());
  }

  public function getOrganizer() {
    if (!isset($this->detail['ORGANIZER'])) {
      return NULL;
    }

    return substr($this->detail['ORGANIZER'], 7); //remove string 'mailto:'
  }

  public function getOrganizerTextIfNotSelf() {
    $current_user_mail = \Drupal::currentUser()->getEmail();
    $organizer = $this->getOrganizer();
    if ($current_user_mail == $organizer) {
      return '';
    }

    $attendees = $this->getAttendees();
    $attendee_mails = [];
    if ($attendees) {
      foreach ($attendees as $attendee) {
        $attendee_mails[] = $attendee['mail'];
      }
    }

    if (in_array($current_user_mail, $attendee_mails)) {
      return;
    }

    $organizer_user = user_load_by_mail($organizer);
    if ($organizer_name = $organizer_user->get('field_name')->value) {
      return '【' . $organizer_name . '】';
    }

    return '【' . $organizer_user->getAccountName() . '】';
  }

  public function getAttendees() {
    if (!isset($this->detail['ATTENDEE'])) {
      return NULL;
    }

    $attendee_array = $this->detail['ATTENDEE_array'];
    $attendees = [];
    for ($index = 0; $index < count($attendee_array); $index += 2) {
      $props = $attendee_array[$index];
      if (isset($props['ROLE']) && strtolower($props['ROLE']) == 'chair') {
        continue;
      }

      $status = $props['PARTSTAT'];
      $mail = substr($attendee_array[$index + 1], 7);
      $attendees[] = [
        'status' => $status,
        'mail' => $mail,
      ];
    }

    return $attendees;
  }

  public function getAttendee($mail) {
    $attendees = $this->getAttendees();
    if (!$attendees) {
      return NULL;
    }

    foreach ($attendees as $attendee) {
      if ($attendee['mail'] == $mail) {
        return $attendee;
      }
    }

    return NULL;
  }

  public function setAttendeeStatus($mail, $status) {
    if (!isset($this->detail['ATTENDEE'])) {
      return;
    }

    $attendee_array = &$this->detail['ATTENDEE_array'];
    $attendees = [];
    for ($index = 0; $index < count($attendee_array); $index += 2) {
      $props = &$attendee_array[$index];
      if (isset($props['ROLE']) && strtolower($props['ROLE']) == 'chair') {
        continue;
      }

      $attendee_mail = substr($attendee_array[$index + 1], 7);
      if ($attendee_mail == $mail) {
        $props['PARTSTAT'] = $status;
        break;
      }
    }
  }


  public function getStatusClassName() {
    if ($this->getStatus()) {
      return 'event-status-' . strtolower($this->getStatus());
    }

    $attendee = $this->getAttendee(\Drupal::currentUser()->getEmail());
    if (!$attendee) {
      return '';
    }

    return 'event-status-' . strtolower($attendee['status']);
  }

  public function getStatus() {
    return isset($this->detail['STATUS']) ? $this->detail['STATUS'] : NULL;
  }

  public function getRRule() {
    return isset($this->detail['RRULE']) ? $this->detail['RRULE'] : NULL;
  }

  public function getRRuleObject() {
    if (isset($this->rrule_object)) {
      return $this->rrule_object;
    }

    $rrule = $this->getRRule();
    if ($rrule) {
      $this->rrule_object = new Rule($rrule, $this->getStartDateTime(), $this->getEndDateTime());
    } else {
      $this->rrule_object = NULL;
    }

    return $this->rrule_object;
  }

  public function getRRuleReadable() {
    $rule = $this->getRRuleObject();
    if (!$rule) {
      return '';
    }

    $textTransformer = new TextTransformer(new Translator('ja'));
    return $textTransformer->transform($rule);
  }

  public function getRepeatCollection() {
    if (isset($this->repeat_collection)) {
      return $this->repeat_collection;
    }

    $transformer = new ArrayTransformer();
    return $this->repeat_collection = $transformer->transform($this->getRRuleObject());
  }

  public function isRepeatBetween($start_date, $end_date) {
    return !$this->getRepeatCollection()
      ->startsBefore($end_date)
      ->endsAfter($start_date)
      ->isEmpty();
  }

  public function getAlarms() {
    if (empty($this->detail['ALARM'])) {
      return array();
    }

    $alarms = array();
    foreach ($this->detail['ALARM'] as $raw_alarm) {
      $trigger = $raw_alarm['TRIGGER'];
      $trigger = strtoupper($trigger);

      $minus = substr($trigger, 0, 1) == '-';
      $matches = array();
      $matched = preg_match('/P([0-9]*)(D?)(T?)([0-9]*)(H?)([0-9]*)(M?)/', $trigger, $matches);

      if ($matched === FALSE) {
        continue;
      }

      $day = 0;
      if (!empty($matches[1])) {
        $day = $matches[1];
      }

      $hour = 0;
      if (!empty($matches[4]) && !empty($matches[5])) {
        $hour = $matches[4];
      }

      $minute = 0;
      if (!empty($matches[7])) {
        if (empty($matches[5]) && !empty($matches[4])) {
          $minute = $matches[4];
        }

        if (!empty($matches[6])) {
          $minute = $matches[6];
        }
      }

      $end = FALSE;
      $trigger_array = $raw_alarm['TRIGGER_array'];
      if (isset($trigger_array['RELATED']) && $trigger_array['RELATED'] == 'END') {
        $end = TRUE;
      }

      $unit = 'minute';
      $time = 0;
      if ($minute) {
        $time = $minute;
      }

      if ($hour) {
        if ($time) {
          $time += $hour * 60;
        } else {
          $time = $hour;
          $unit = 'hour';
        }
      }

      if ($day) {
        if ($time) {
          if ($unit == 'hour') {
            $time += $day * 24;
          } else { //分
            $time += $day * 24 * 60;
          }
        } else {
          $time = $day;
          $unit = 'day';
        }
      }

      $alarms[] = array(
        'minus' => $minus,
        'day' => $day,
        'hour' => $hour,
        'minute' => $minute,
        'end' => $end,
        'time' => $time,
        'unit' => $unit,
      );
    }

    return $alarms;
  }

  public function __toString() {
    return $this->getId();
  }

  public function toText() {
    $uuid = $this->getId();
    $summary = $this->getSummary();
    $location = $this->getLocation();
    $description = str_replace("\n", '\n', $this->getDescription());

    $begin_datetime = DrupalDateTime::createFromDateTime($this->getStartDateTime());
    $end_datetime = DrupalDateTime::createFromDateTime($this->getEndDateTime());

    $begin_date = $begin_datetime->format('Ymd');
    $begin_time = $begin_datetime->format('Hi');
    $end_date = $end_datetime->format('Ymd');
    $end_time = $end_datetime->format('Hi');

    $now_time = time();
    $now = CalendarUtil::formatTimestampToCaldavStr($now_time);

    if ($this->isAllDay()) {//終日
      $dtstart = "VALUE=DATE:${begin_date}";

      $end_datetime->modify('+1 day');
      $end_date = $end_datetime->format('Ymd');

      $dtend = "VALUE=DATE:${end_date}";
    } else {
      $timezone = drupal_get_user_timezone();
      $dtstart = "TZID=$timezone:${begin_date}T${begin_time}00";
      $dtend = "TZID=$timezone:${end_date}T${end_time}00";
    }

    //alarms
    $alarms_text = '';
    if (!empty($this->detail['ALARM'])) {
      foreach ($this->detail['ALARM'] as $raw_alarm) {
        $trigger = $raw_alarm['TRIGGER'];
        $alarms_text .=
"BEGIN:VALARM
ACTION:DISPLAY
TRIGGER;$trigger
DESCRIPTION:Reminder set on drupal8
END:VALARM
";
      }
    }

    //attendees
    $attendees_text = '';
    foreach ($this->getAttendees() as $attendee) {
      $status = $attendee['status'];
      $mail = $attendee['mail'];
      $attendees_text .= "ATTENDEE;PARTSTAT=$status;ROLE=REQ-PARTICIPANT;SCHEDULE-STATUS=2.0:mailto:$mail\n";
    }
    $attendees_text = trim($attendees_text);

    //organizer
    $organizer_mail = $this->getOrganizer();
    $organizer_text = "ORGANIZER;RSVP=TRUE;PARTSTAT=ACCEPTED;ROLE=CHAIR:mailto:$organizer_mail";

    $rrule = $this->getRRule();

    $event = "BEGIN:VCALENDAR
PRODID:-//Sabre//Sabre VObject 3.4.6//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:$uuid
DTSTAMP:$now
SUMMARY:$summary
DESCRIPTION: $description
LOCATION: $location
CLASS:PUBLIC
TRANSP:OPAQUE
$rrule
$organizer_text
$attendees_text
DTSTART;$dtstart
DTEND;$dtend
CREATED:$now
LAST-MODIFIED:$now
$alarms_text
END:VEVENT
END:VCALENDAR
";

    return $event;
  }

  private function getDateTime($date_str, $timezone_str) {
    if ($timezone_str) {
      $date = new HolidayDateTime($date_str, new DateTimeZone($timezone_str));
      //convert to default timezone
      $date->setTimeZone(new DateTimeZone(drupal_get_user_timezone()));
    } else {
      $date = new HolidayDateTime($date_str, new DateTimeZone(drupal_get_user_timezone()));
    }

    return $date;
  }
}
