<?php

namespace Drupal\ipride_calendar;

use Drupal\ipride_calendar\Util\Util as CalendarUtil;
use Drupal\ipride_calendar\Util\CalendarEvent;


/**
 * Class DefaultService.
 *
 * @package Drupal\ipride_calendar
 */
class DefaultService {
  /**
   * Constructor.
   */
  public function __construct() {

  }

  public function updateSharedEventStatus($event_id, $status) {
    $logger = \Drupal::logger("ipride_calendar");

    try {
      $client = CalendarUtil::getCalendarClient();
      $calendars = $client->findCalendars();
      if (empty($calendars)) {
        $logger->error("カレンダーが見つかりません。");
        return FALSE;
      }

      $calendar = array_values($calendars)[0];
      $event = $client->getEvent($calendar->getCalendarID(), $event_id);
      $event = new CalendarEvent($event);

      $mail = \Drupal::currentUser()->getEmail();
      $event->setAttendeeStatus($mail, $status);

      $raw_obj = $event->getRawObject();

      $event_text = $event->toText();
      $logger->debug($event_text);

      $client->setCalendar($calendar);
      $client->change($raw_obj->getHref(), $event_text, $raw_obj->getEtag());
      return TRUE;
    } catch (\Exception $e) {
      $logger->error($e->getMessage());
      return FALSE;
    }
  }
}
