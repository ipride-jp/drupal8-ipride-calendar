# drupal8-ipride-calendar
## ipride_calendarモジュールについて
このモジュールはカレンダーサーバ(sabredav)との連携機能と、それに付随するGUIを提供します。

## 前提
* Drupalのバージョンは8.5.2以上推奨。
* カレンダーサーバ(calDAV)として[sabredav](http://sabre.io/)が利用可能であること。
  * sabredav 3.22で動作確認済み。
* カレンダーサーバのDBはMySqlであること。
* カレンダーサーバと同じマシン上にDrupal8がインストールされている、またはDrupal8のcoreディレクトリ以下全てが配置されていること。

## インストール方法
### 1 カレンダーサーバの変更
#### 1.1 DrupalAuthCallback.phpの追加
 ＜sabredavのインストールディレクトリ＞/vendor 下に[DrupalAuthCallback.php](https://github.com/ipride-jp/drupal8-ipride-calendar/tree/master/sabredav)をコピーして配置します。<br>
配置したDrupalAuthCallback.phpを開き、以下のコードを修正します。
```
// Drupal8のcoreディレクトリへのpathを正しく指定してください。
define ('DRUPAL_CORE_PATH', '/var/www/html/drupal_/core');
```
#### 1.2 認証設定の変更
calendarserver.php(sabredavのカレンダーサーバファイル)を開き、以下を追記します。
```
//drupal8で使用しているDB
$drupalPdo = new PDO('mysql:dbname=<DB名>;host=<host名>', '<rootユーザ名>', '<パスワード>');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```
```
require_once '../vendor/DrupalAuthCallback.php';
```
続けて、バックエンド設定を以下のように変更します。
```
// Backends
//$authBackend = new Sabre\DAV\Auth\Backend\PDO($pdo);
$authBackend = new Sabre\DAV\Auth\Backend\BasicCallBack(
       [new DrupalAuthCallback($drupalPdo), 'isAuthorizedAccount']//drupalアカウント情報を利用した認証
);
$calendarBackend = new Sabre\CalDAV\Backend\PDO($pdo);
$principalBackend = new Sabre\DAVACL\PrincipalBackend\PDO($pdo);
```
※ [設定例](https://github.com/ipride-jp/drupal8-ipride-calendar/blob/master/sabredav/examples/drupal-calserver.php)


### 2 Drupalの変更
#### 2.1 ipride_calendarモジュールのインストールと初期設定
ipride_calenadarモジュールをお使いのDrupal8にインストールします。<br>
<br>
次にカレンダーの設定ページ( ＜DrupalのURL＞/admin/config/ipride/calendar )にアクセスして、各項目を全て入力し、「保存」してください
* カレンダーサーバ
  * カレンダーサーバのエンドポイントURLを入力してください。(eg. http://localhost/sabredav/public/calendarserver.php/)
* カレンダーのデータベース(MYSQL)
  * カレンダーサーバが利用しているDBの接続情報を入力してください。

#### 2.2 権限設定の変更 
権限設定ページにて、「Use ipride calendar」権限の付与を行ってください。
(この権限のあるロールのみカレンダー機能が利用できるようになります）

#### 2.3 Drupalアカウントとカレンダーの作成
アカウントを作成してください。<br>
カレンダーサーバ側のアカウントはdrupal8のアカウントに紐付いて自動的に作成されます。<br>
<br>
作成したアカウントでログインし、カレンダー画面( ＜DrupalのURL＞/calendar/month/current )にアクセスします。<br>
<br>
「カレンダー選択」の「＋登録」ボタンをクリックし、カレンダーを作成してください。(各ユーザごとに一つ以上のカレンダーが必要になります。)<br>
<br>
カレンダーに登録された予定の表示・非表示切り替える場合は各カレンダー名の横にある「有効」チェックボックスにて行ってください。ONにしたカレンダーの予定が表示されます。
