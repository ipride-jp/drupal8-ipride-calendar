<?php

// Drupal8のcoreディレクトリへのpathを正しく指定してください。
define('DRUPAL_CORE', '/var/www/html/drupal/core');

spl_autoload_register(function ($class_name) {
  $file_path = sprintf('%s/lib/%s.php',
    DRUPAL_CORE,
    str_replace('\\', '/', $class_name)
  );

  if (strpos($class_name, "Drupal") === 0 && file_exists ($file_path)){
    require_once($file_path);
  }
});

use Drupal\Core\Password\PhpassHashedPassword;

class DrupalAuthCallback {
  private $pdo;
  function __construct($pdo) {
    $this->pdo = $pdo;
  }

  function isAuthorizedAccount($username, $password) {
    $stmt = $this->pdo->prepare('SELECT pass FROM users_field_data WHERE name = ? and status = 1');
    $stmt->execute([$username]);
    $hash = $stmt->fetchColumn();

    if (strpos($password, '$S$') === 0) {//hashed password
      return $hash == $password;
    }

    $hashedPassword = new Drupal\Core\Password\PhpassHashedPassword(16);
    return $hashedPassword->check($password, $hash);
  }
}
