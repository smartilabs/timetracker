<?php
session_start();

require '../vendor/autoload.php';

// Setup custom Twig view
$twigView = new \Slim\Extras\Views\Twig();


$app = new \Slim\Slim([
  'debug' => true,
  'view' => $twigView,
  'templates.path' => '../templates/',
  'session.handler' => null
]);

// prepare session middleware
//$app->add(new \Slim\Middleware\SessionCookie(array(
//  'expires' => '60 minutes',
//  'path' => '/',
//  'domain' => null,
//  'secure' => true,
//  'httponly' => true,
//  'name' => 'slim_session',
//  'secret' => 'Thi$1Ss0meeXtremlyS3cureS3ct3t',
//  'cipher' => MCRYPT_RIJNDAEL_256,
//  'cipher_mode' => MCRYPT_MODE_CBC
//)));

// prepare base url
$app->hook('slim.before', function () use ($app) {
  $app->view()->appendData([
    'baseUrl' => '/',
    'user' => isset($_SESSION['UserAuthenticated']) ? $_SESSION['UserAuthenticated'] : null
  ]);
});

// route middleware for simple API authentication
function authenticate (\Slim\Route $route)
{
  $app = \Slim\Slim::getInstance();
  $user = isset($_SESSION['UserAuthenticated']) ? $_SESSION['UserAuthenticated'] : null;

  if (! validateUser($user)) {
    $app->response()->redirect('/login');
  }
}

function authenticated (\Slim\Route $route)
{
  $app = \Slim\Slim::getInstance();
  $user = isset($_SESSION['UserAuthenticated']) ? $_SESSION['UserAuthenticated'] : null;

  if (validateUser($user)) {
    $app->response()->redirect('/');
  }
}

function validateUser ($user)
{
  return ($user && $user['UserID']);
}

// diff twig function
$twig = $twigView->getEnvironment();

$function = new Twig_SimpleFilter('diff', function ($start, $end) {
  $time = strtotime($end) - strtotime($start);
  $hours = $time / 60 / 60;

  return number_format($hours, 2);
});
$twig->addFilter($function);

$function = new Twig_SimpleFilter('padZero', function ($number) {
  return str_pad($number, 2, '0', STR_PAD_LEFT);
});
$twig->addFilter($function);

require '../app/database.php';

$app->get('/', 'authenticate', function () use ($app) {
  $currentMonth = date('/Y/m', time());
  $app->response()->redirect($currentMonth);
});

$app->get('/:year/:month', 'authenticate', function ($year, $month) use ($app) {
  // display track by month

  $currentMonth = (int) date('m');
  $currentYear = (int) date('Y');

  $user = $_SESSION['UserAuthenticated'];
  $userID = $user['UserID'];

  $tracks = Track::getMonthForUser($year, $month, $userID);
  $months = Track::getMonthList($userID);

  // set selected
  foreach ($months as &$m) {
    if ($m['Month'] == $month && $m['Year'] == $year)
      $m['Selected'] = true;
  }

  $modules = Module::all()->toArray();
  $taskTypes = TaskType::all()->toArray();

  $app->render('tracks.html', [
    'tracks' => $tracks->toArray(),
    'year' => $year,
    'month' => $month,
    'months' => $months,
    'taskTypes' => $taskTypes,
    'modules' => $modules,
    'emptyRow' => ((int) $year == $currentYear && $currentMonth == (int) $month)
  ]);
})->conditions(array('year' => '(20)\d\d', 'month' => '\d\d'));

$app->get('/module/list', 'authenticate', function () use ($app) {
  // echo Module::all()->toJson();

  $modules = Module::all();
  $app->render('modules.html', array('modules' => $modules->toArray()));
});

$app->post('/track/empty', 'authenticate', function () use ($app) {
  header("Content-Type: application/json");

  $taskTypes = TaskType::all()->toArray();
  $modules = Module::all()->toArray();

  $req = $app->request();
  $date = $req->params('date');
  $timeStart = $req->params('time-start');
  $timeEnd = $req->params('time-end');

  $viewData = [
    'taskTypes' => $taskTypes,
    'modules' => $modules
  ];

  if ($date && $timeStart && $timeEnd) {
    $timeStart = Track::parseDate($date . ' ' . $timeStart);
    $timeEnd = Track::parseDate($date . ' ' . $timeEnd);

    if ($timeStart && $timeEnd) {
      if ($timeStart > $timeEnd)
        $timeStart->modify('+1 day');

      $viewData['date'] = $timeStart->getTimestamp();
      $viewData['timeStart'] = $timeEnd->getTimestamp();
    }
  }

  $app->view()->setData($viewData);

  $track = $app->view()->fetch('track.html');

  echo json_encode([
    'Status' => 'Success',
    'TrackHtml' => $track
  ]);

  exit;
});

$app->post('/track', 'authenticate', function () use ($app) {
  header("Content-Type: application/json");

  $req = $app->request();

  $trackData = Track::getVerifiedData([
    'Location' => $req->params('location'),
    'Date' => $req->params('date'),
    'TimeStart' => $req->params('time-start'),
    'TimeEnd' => $req->params('time-end'),
    'Description' => $req->params('description'),
    'TaskType' => $req->params('task-type'),
    'Module' => $req->params('module'),
    'Ticket' => $req->params('ticket')
  ]);

  $trackID = Track::saveTrack($trackData);

  echo json_encode([
    'Status' => 'Success',
    'TrackID' => $trackID
  ]);

  exit;
});

$app->post('/track/delete/:id', 'authenticate', function ($trackID) use ($app) {
  header("Content-Type: application/json");

  $track = Track::getTrack($trackID);

  if ($track)
    $track->delete($trackID);

  echo json_encode([
    'Status' => 'Success'
  ]);

  exit;
});

// update track by id
$app->post('/track/:id', 'authenticate', function ($id) use ($app) {
  header("Content-Type: application/json");

  $req = $app->request();

  $trackData = Track::getVerifiedData([
    'Location' => $req->params('location'),
    'Date' => $req->params('date'),
    'TimeStart' => $req->params('time-start'),
    'TimeEnd' => $req->params('time-end'),
    'Description' => $req->params('description'),
    'TaskTypeID' => $req->params('task-type'),
    'ModuleID' => $req->params('module'),
    'Ticket' => $req->params('ticket')
  ]);

  $trackID = Track::saveTrack($trackData, $id);

  $result = [
    'Status' => 'Success',
    'TrackID' => $trackID
  ];

  // get data for month select if new month
  $currentMonth = (int) $req->params('current-month');
  $currentYear = (int) $req->params('current-year');

  $track = Track::getTrack($trackID);
  $dateStart = strtotime($track->TimeStart);
  if (date('m', $dateStart) != $currentMonth || date('Y', $dateStart) != $currentYear) {
    $user = $_SESSION['UserAuthenticated'];
    $userID = $user['UserID'];

    $months = Track::getMonthList($userID);

    foreach ($months as &$month) {
      $month['Month'] = str_pad($month['Month'], 2, '0', STR_PAD_LEFT);
      $month['Url'] = '/' . $month['Year'] . '/' . $month['Month'];
    }

    $result['Months'] = $months;
  }


  echo json_encode($result);

  exit;
});

// display login screen
$app->get('/logout', 'authenticate', function () use ($app) {
  unset($_SESSION['UserAuthenticated']);
  $app->response()->redirect('/');
});

// display login screen
$app->get('/login', 'authenticated', function () use ($app) {
  $app->render('login.html', [
    'invalid' => $app->request()->params('invalid') == 1
  ]);
});

$app->post('/login', 'authenticated', function () use ($app) {
  try {
    $username = $app->request()->params('username');
    $password = $app->request()->params('password');

    $user = User::authenticate($username, $password);

    if ($user->count()) {
      $user = $user->first()->toArray();
      $_SESSION['UserAuthenticated'] = $user;
      $app->response()->redirect('/');
    }
    else {
      $app->response()->redirect('/login?invalid=1');
    }
  } catch (Exception $e) {
    $app->response()->status(400);
    $app->response()->header('X-Status-Reason', $e->getMessage());
  }
});

$app->run();