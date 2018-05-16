<?php

namespace Drupal\ipride_calendar\Util;

class CalendarToDo {
  private $raw_obj;
  private $detail;

  public function __construct($calDAVObject) {
    $this->raw_obj = $calDAVObject;
    $this->detail = $this->raw_obj->getToDo();
  }

  public function getSummary() {
    return trim($this->detail['SUMMARY']);
  }

  public function getStatus() {
    return ipride_array_get($this->detail, 'STATUS');
  }
}
