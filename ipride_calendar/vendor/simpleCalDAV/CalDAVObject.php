<?php
/**
 * CalDAVObject
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * This class represents a calendar resource on the CalDAV-Server (event, todo, etc.)
 *
 * href: The link to the resource in the calendar
 * data: The iCalendar-Data. The "heart" of the resource.
 * etag: The entity tag is a unique identifier, not only of a resource
 *           like the unique ID, but of serveral versions of the same resource. This means that a resource with one unique
 *           ID can have many different entity tags, depending on the content of the resource. One version of a resource,
 *           i. e. one special description in combination with one special starting time, created at one specific time,
 *           etc., has exactly on unique entity tag.
 *           The assignment of an entity tag ensures, that you know what you are changing/deleting. It ensures, that no one
 *           changed the resource between your viewing of the resource and your change/delete-request. Assigning an entity tag
 *           provides you of accidently destroying the work of others.
 *
 * @package simpleCalDAV
 *
 */

require_once __DIR__ . '/../class.iCalReader.php';

class CalDAVObject {
	private $href;
	private $data;
	private $etag;

	public function __construct ($href, $data, $etag) {
		$this->href = $href;
		$this->data = $data;
		$this->etag = $etag;
	}


	// Getter

	public function getHref () {
		return $this->href;
	}

	public function getData () {
		return $this->data;
	}

	public function getEtag () {
		return $this->etag;
	}

  public function getEvent() {
    $event_data = $this->getDetail()->events()[0];

    $event_data['DTSTART_TIMEZONE'] = $this->getTimeZone($event_data['DTSTART_array']);
    $event_data['DTEND_TIMEZONE'] = $this->getTimeZone($event_data['DTEND_array']);
    return $event_data;
  }

  public function getToDo() {
    dpm($this->getDetail()->todos()[0]);
    return $this->getDetail()->todos()[0];
  }

  public function getCalendarId() {
    preg_match('@.*/(.*)/.*$@', $this->href, $matches);
    return $matches[1];
  }

  private function getDetail() {
    $lines = explode("\n", $this->data);
    $new_lines = [];
    foreach ($lines as $line) {
      if (!empty($line) && $line[0] == ' ') {
        $new_lines[count($new_lines) - 1] .= trim($line);
      } else {
        $new_lines[] = trim($line);
      }
    }

    return new ICal($new_lines);
  }

  private function getTimeZone($dt_array) {
    $params = $dt_array[0];
    if (key_exists('TZID', $params)) {
      return $params['TZID'];
    }

    return NULL;
  }
}
