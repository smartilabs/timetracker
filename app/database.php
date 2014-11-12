<?php
if (! isset($app))
  die('Direct access not allowed!');

use Illuminate\Database\Capsule\Manager as Capsule;


$capsule = new Capsule;

$capsule->addConnection(array(
  'driver' => 'mysql',
  'host' => 'localhost',
  'database' => 'timetracker',
  'username' => 'root',
  'password' => 'root',
  'charset' => 'utf8',
  'collation' => 'utf8_unicode_ci',
  'prefix' => ''
));

$capsule->setAsGlobal();
$capsule->bootEloquent();

$app->db = $capsule->getConnection();