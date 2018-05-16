<?php

namespace Drupal\ipride_calendar\Dao;

class PrincipalDao {
  private $connection = NULL;
  private $table_name = 'principals';

  function __construct() {
    $config = \Drupal::config('ipride_calendar.settings');
 
    $options = [ 
      'host'     => $config->get('db_host'),
      'port'     => $config->get('db_port'),
      'database' => $config->get('db_schema'),
      'username' => $config->get('db_user'),
      'password' => $config->get('db_passwd'),
    ];

    $driver = $config->get('db_driver') ? $config->get('db_driver') : 'mysql';
    $connection_class = '\\Drupal\\Core\\Database\\Driver\\mysql\\Connection';

    try {
      $pdo = $connection_class::open($options);
      $this->connection = new $connection_class($pdo, $options);
    }
    catch (\Exception $e) {
      \Drupal::logger('ipride_calendar')->error($e);
      throw new \Exception(t('カレンダーサーバのデータベースに接続できませんでした。カレンダーの設定をご確認ください。'));
    }
  }

  public function add($fields) {
    try {
      $this->connection
      ->insert($this->table_name)
      ->fields($fields)
      ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('ipride_calendar')->error($e);
      throw new \Exception(t('カレンダーサーバのユーザ登録に失敗しました。'));
    }
  }

  public function removeByUri($uri){
    try {
      $this->connection
      ->delete($this->table_name)
      ->condition('uri', $uri)
      ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('ipride_calendar')->error($e);
      throw new \Exception(t('カレンダーサーバのユーザ削除に失敗しました。'));
    }
  }
}


