<?php

namespace Drupal\ipride_calendar\Util;

use Drupal;
use Drupal\user\Entity\User;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;

require_once __DIR__ . '/../../vendor/simpleCalDAV/SimpleCalDAVClient.php';

class Util {
  public static function getCalendarClient($name = NULL) {
    if ($name) {
      $user = user_load_by_name($name);
    } else {
      $user = Drupal::currentUser();
    }

    $account_name = $user->getAccountName();
    $password = User::load($user->id())->getPassword();
    $config = Drupal::config('ipride_calendar.settings');
    $server_url = $config->get('calendar_server');
    if (empty($server_url)) {
      throw new \Exception(t('カレンダーサーバに接続できませんでした。カレンダーの設定をご確認ください。'));
    }

    $client = new \SimpleCalDAVClient();
    try {
      $client->connect($server_url, $account_name, $password);
    } catch(\Exception $e) {
      \Drupal::logger('ipride_calendar')->error($e);
      throw new \Exception(t('カレンダーサーバに接続できませんでした。カレンダーの設定をご確認ください。'));
    }
    return $client;
  }

  public static function getCalendarById($calendar_id) {
    $calendar = NULL;
    try {
      $client = self::getCalendarClient();
      $calendar = $client->findCalendar($calendar_id);

      if (!$calendar) {
        drupal_set_message(t('カレンダーが存在しません。'), 'error');
      }
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return $calendar;
  }

  public static function selectWhiteOrBlackColor($rgb) {
    $r = hexdec(substr($rgb, 1, 2));
    $g = hexdec(substr($rgb, 3, 2));
    $b = hexdec(substr($rgb, 5, 2));

    $diff_with_white = 255 - $r + 255 - $g + 255 - $b;
    $diff_with_black = $r + $g + $b;

    if ($diff_with_white > $diff_with_black) {
      return 'white';
    }

    return 'black';
  }

  public static function getCalendarAndEvent($calendar_id, $event_id, $client = NULL) {
    $calendar = NULL;
    $event = NULL;

    try {
      if (!$client) {
        $client = self::getCalendarClient();
      }

      $calendar = $client->findCalendar($calendar_id);
      if (!$calendar) {
        drupal_set_message(t('カレンダーが存在しません。'), 'error');
        return [NULL, NULL];
      }

      $event = $client->getEvent($calendar_id, $event_id);
      if (!$event) {
        drupal_set_message(t('イベントが存在しません。'), 'error');
        return [NULL, NULL];
      }

      $event = new CalendarEvent($event);
    } catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
    }

    return [$calendar, $event];
  }

  public static function getSelfText($mail) {
    $user = \Drupal::currentUser();
    if ($user->getEmail() == $mail) {
      return '(自分)';
    }

    return '';
  }

  public static function buildAlarmTable($event, $form, $form_state) {
    self::doAlarmTableActions($form, $form_state);

    if (isset($form_state->getBuildInfo()['alarms_data'])) {
      $rows_data = $form_state->getBuildInfo()['alarms_data'];
    } else {
      $rows_data = [];
      if ($event) {
        foreach ($event->getAlarms() as $alarm) {
          $type = 0;
          if (!$alarm['minus']) {
            $type = 1;
          }
          if ($alarm['end']) {
            $type += 2;
          }

          $rows_data[] = [
            'time' => $alarm['time'],
            'unit' => $alarm['unit'],
            'type' => $type,
          ];
        }
      }
    }

    $form['alarms_title'] = [
      '#type' => 'item',
      '#title' => t('アラーム'),
    ];

    $header = ['time' => '時間', 'unit' => '単位', 'type' => 'タイプ', 'operation' => '操作'];
    $form['alarms'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div id="alarms_wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($rows_data as $index => $row_data) {
      $form['alarms'][$index]['time'] = [
        '#type' => 'number',
        '#size' => 2,
        '#default_value' => $row_data['time'],
      ];

      $form['alarms'][$index]['unit'] = [
        '#type' => 'select',
        '#options' => [
          'minute' => '分',
          'hour' => '時間',
          'day' => '日',
        ],
        '#default_value' => $row_data['unit'],
      ];

      $form['alarms'][$index]['type'] = [
        '#type' => 'select',
        '#options' => [
          '0' => '予定開始まで',
          '1' => '予定開始から',
          '2' => '予定終了まで',
          '3' => '予定終了から',
        ],
        '#default_value' => $row_data['type'],
      ];

      $form['alarms'][$index]['operation'] = [
        '#type' => 'button',
        '#value' => '削除',
        '#name' => 'delete_alarm' . $index,
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [__NAMESPACE__ . '\Util', 'alarmTableCallback'],
          'wrapper' => 'alarms_wrapper',
        ],
      ];
    }

    $form['add_alarm'] = [
      '#type' => 'button',
      '#value' => '+アラーム',
      '#name' => 'add_alarm',
      '#limit_validation_errors' => [],
      '#prefix' => '<div>',
      '#suffix' => '</div><br>',
      '#ajax' => [
        'callback' => [__NAMESPACE__ . '\Util', 'alarmTableCallback'],
        'wrapper' => 'alarms_wrapper',
      ],
    ];

    $form_state->addBuildInfo('alarms_data', $rows_data);

    return $form;
  }

  public static function alarmTableCallback(array &$form, FormStateInterface $form_state) {
    return $form['alarms'];
  }

  public static function doAlarmTableActions(array &$form, FormStateInterface $form_state) {
    $elem = $form_state->getTriggeringElement();
    if (!$elem) {
      return;
    }

    $data =& $form_state->getBuildInfo()['alarms_data'];

    //add
    if ($elem['#name'] == 'add_alarm') {
      $data[] = [
        'time' => 15,
        'unit' => 'minute',
        'type' => '0',
      ];
    }

    //delete
    if (strpos($elem['#name'], 'delete_alarm') === 0) {
      $index = $elem['#parents'][1];
      unset($data[$index]);
    }

    $form_state->addBuildInfo('alarms_data', $data);
  }

  public static function buildAttendeeTable($event, $form, $form_state, $editable = FALSE) {
    if ($event && !$event->getOrganizer() && !$editable) {
      return $form;
    }

    self::doAttendeeTableActions($form, $form_state);

    $user = \Drupal::currentUser();
    $is_owner = !$event //新規
      || !$event->getOrganizer() //主催者なし
      || $user->getEmail() == $event->getOrganizer(); //自分が主催者

    $status_options = [
      'NEEDS-ACTION' => '回答待ち',
      'ACCEPTED' => '出席',
      'DECLINED' => '欠席',
      'TENTATIVE' => '仮承諾',
    ];

    if (isset($form_state->getBuildInfo()['attendees_data'])) {
      $rows_data = $form_state->getBuildInfo()['attendees_data'];
    } else {
      $rows_data = [];
      if ($event && $event->getOrganizer()) {
        $rows_data[] = [
          'mail' => $event->getOrganizer(),
          'desc' => self::getSelfText($event->getOrganizer()) . '(主催者)',
          'status' => 'ACCEPTED',
          'status_text' => $status_options['ACCEPTED'],
        ];

        $attendees = $event->getAttendees();
        if ($attendees) {
          foreach ($attendees as $attendee) {
            $status = $attendee['status'];
            $rows_data[] = [
              'mail' => $attendee['mail'],
              'desc' => self::getSelfText($attendee['mail']),
              'status' => $status,
              'status_text' => $status_options[$status],
            ];
          }
        }
      } else {//新規
        $rows_data[] = [
          'mail' => $user->getEmail(),
          'desc' => '(自分)(主催者)',
          'status' => 'ACCEPTED',
          'status_text' => $status_options['ACCEPTED'],
        ];
      }

      if ($is_owner && $editable) {
        $rows_data[] = [
          'mail' => '',
          'status' => '',
          'status_text' => '',
        ];
      }
    }

    $form['attendees_title'] = [
      '#type' => 'item',
      '#title' => t('参加者'),
    ];

    $header = ['name' => '名前', 'status' => '状態'];
    if ($is_owner && $editable) {
      $header['operation'] = '操作';
    }

    $form['attendees'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div id="attendees_wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($rows_data as $index => $row_data) {
      if (empty($row_data['mail'])) {
        $form['attendees'][$index]['name'] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
        ];
      } else {
        $attendee_user = user_load_by_mail($row_data['mail']);
        $form['attendees'][$index]['name'] = [
          '#theme' => 'username',
          '#account' => $attendee_user,
          '#suffix' => $row_data['desc'],
        ];
      }

      if ($editable && $index != 0 && $user->getEmail() == $row_data['mail']) { //１番目は主催者
        $form['attendees'][$index]['status'] = [
          '#type' => 'select',
          '#options' => $status_options,
          '#default_value' => $row_data['status'],
        ];
      } else {
        $form['attendees'][$index]['status'] = ['#markup' => $row_data['status_text']];
      }

      if ($is_owner && $editable) {
        if ($index == 0) {
          $form['attendees'][$index]['operation'] = ['#markup' => ''];
        } else {
          $form['attendees'][$index]['operation'] = [
            '#type' => 'button',
            '#value' => '削除',
            '#name' => 'delete_attendee' . $index,
            '#limit_validation_errors' => [],
            '#ajax' => [
              'callback' => [__NAMESPACE__ . '\Util', 'attendeeTableCallback'],
              'wrapper' => 'attendees_wrapper',
            ],
          ];
        }
      }
    }

    $form_state->addBuildInfo('attendees_data', $rows_data);

    if ($is_owner && $editable) {
      $form['add_attendee'] = [
        '#type' => 'button',
        '#value' => '+参加者',
        '#name' => 'add_attendee',
        '#limit_validation_errors' => [],
        '#prefix' => '<div>',
        '#suffix' => '</div><br>',
        '#ajax' => [
          'callback' => [__NAMESPACE__ . '\Util', 'attendeeTableCallback'],
          'wrapper' => 'attendees_wrapper',
        ],
      ];
    }

    return $form;
  }

  public static function attendeeTableCallback(array &$form, FormStateInterface $form_state) {
    return $form['attendees'];
  }

  public static function doAttendeeTableActions(array &$form, FormStateInterface $form_state) {
    $elem = $form_state->getTriggeringElement();
    if (!$elem) {
      return;
    }

    $data =& $form_state->getBuildInfo()['attendees_data'];

    //add
    if ($elem['#name'] == 'add_attendee') {
      $data[] = [
        'mail' => '',
        'status' => '',
        'status_text' => '',
      ];
    }

    //delete
    if (strpos($elem['#name'], 'delete_attendee') === 0) {
      $index = $elem['#parents'][1];
      unset($data[$index]);
    }

    $form_state->addBuildInfo('attendees_data', $data);
  }

  public static function buildCalendarAttendeeTable($calendar, $form, $form_state, $editable = FALSE) {
    if ($calendar && !$calendar->getOrganizer() && !$editable) {
      return $form;
    }

    self::doAttendeeTableActions($form, $form_state);

    $user = \Drupal::currentUser();
    $is_owner = !$calendar //新規
      || !$calendar->getOrganizer() //主催者なし
      || $user->getEmail() == $calendar->getOrganizer(); //自分が主催者

    if (isset($form_state->getBuildInfo()['attendees_data'])) {
      $rows_data = $form_state->getBuildInfo()['attendees_data'];
    } else {
      $rows_data = [];
      if ($calendar && $calendar->getOrganizer()) {
        $rows_data[] = [
          'mail' => $calendar->getOrganizer(),
          'desc' => self::getSelfText($calendar->getOrganizer()) . '(主催者)',
        ];

        $attendees = $calendar->getAttendees();
        if ($attendees) {
          foreach ($attendees as $attendee) {
            $rows_data[] = [
              'mail' => $attendee['mail'],
              'permission' => $attendee['permission'],
              'desc' => self::getSelfText($attendee['mail']),
            ];
          }
        }
      } else {//新規
        $rows_data[] = [
          'mail' => $user->getEmail(),
          'permission' => 'read-write',
          'desc' => '(自分)(主催者)',
        ];
      }

      if ($is_owner && $editable) {
        $rows_data[] = [
          'mail' => '',
        ];
      }
    }

    $form['attendees_title'] = [
      '#type' => 'item',
      '#title' => t('参加者'),
    ];

    $header = ['name' => '名前'];
    $header['permission'] = '権限';
    if ($is_owner && $editable) {
      $header['operation'] = '操作';
    }

    $form['attendees'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div id="attendees_wrapper">',
      '#suffix' => '</div>',
    ];

    foreach ($rows_data as $index => $row_data) {
      if (empty($row_data['mail'])) {
        $form['attendees'][$index]['name'] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
        ];
      } else {
        $attendee_user = user_load_by_mail($row_data['mail']);
        $form['attendees'][$index]['name'] = [
          '#theme' => 'username',
          '#account' => $attendee_user,
          '#suffix' => $row_data['desc'],
        ];
      }

      if ($is_owner && $editable) {
        if ($index == 0) {
          $form['attendees'][$index]['permission'] = ['#markup' => ''];
          $form['attendees'][$index]['operation'] = ['#markup' => ''];
        } else {
          $form['attendees'][$index]['permission'] = [
            '#type' => 'select',
            '#options' => ['read-write' => '表示と編集', 'read' => '表示のみ', ],
            '#default_value' => isset($row_data['permission']) ? $row_data['permission'] : 'read-write',
          ];
          $form['attendees'][$index]['operation'] = [
            '#type' => 'button',
            '#value' => '削除',
            '#name' => 'delete_attendee' . $index,
            '#limit_validation_errors' => [],
            '#ajax' => [
              'callback' => [__NAMESPACE__ . '\Util', 'attendeeTableCallback'],
              'wrapper' => 'attendees_wrapper',
            ],
          ];
        }
      } else {
        if ($index == 0) {
          $form['attendees'][$index]['permission'] = ['#markup' => ''];
        } else {
          $permission_text = '表示と編集';
          if (isset($row_data['permission']) && $row_data['permission'] == 'read') {
            $permission_text = '表示';
          }
          $form['attendees'][$index]['permission'] = [
            '#markup' => $permission_text,
          ];
        }
      }
    }

    $form_state->addBuildInfo('attendees_data', $rows_data);

    if ($is_owner && $editable) {
      $form['add_attendee'] = [
        '#type' => 'button',
        '#value' => '+参加者',
        '#name' => 'add_attendee',
        '#limit_validation_errors' => [],
        '#prefix' => '<div>',
        '#suffix' => '</div><br>',
        '#ajax' => [
          'callback' => [__NAMESPACE__ . '\Util', 'attendeeTableCallback'],
          'wrapper' => 'attendees_wrapper',
        ],
      ];
    }

    return $form;
  }

  public static function formatDateTimeToCaldavStr($date_time) {
    return self::formatTimestampToCaldavStr($date_time->getTimestamp());
  }

  public static function formatTimestampToCaldavStr($timestamp) {
    return gmdate('Ymd', $timestamp)
      . 'T'
      . gmdate('His', $timestamp)
      . 'Z';
  }

  public static function getUserName($uid) {
    $user = user_load($uid);
    if (!$user) {
      return NULL;
    }

    return $user->getUserName();
  }

  public static function getUserNameByMail($mail) {
    $user = user_load_by_mail($mail);
    if (!$user) {
      return FALSE;
    }

    return $user->getUserName();
  }

  public static function isNewYearHolidays($month, $day) {
    //年末休暇
    if ($month == 12 && 29 <= $day) {
      return TRUE;
    }

    //年始休暇
    if ($month == 1 && $day <= 3) {
      return TRUE;
    }

    return FALSE;
  }
  
}
