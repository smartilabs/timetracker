<?php
date_default_timezone_set('Europe/Ljubljana');

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

// prepare base url
$app->hook('slim.before', function () use ($app) {
  $app->view()->appendData([
    'baseUrl' => '/',
    'user' => isset($_SESSION['UserAuthenticated']) ? $_SESSION['UserAuthenticated'] : null
  ]);
});

// route middleware for simple API authentication
function authenticated (\Slim\Route $route)
{
  $app = \Slim\Slim::getInstance();
  $user = isset($_SESSION['UserAuthenticated']) ? $_SESSION['UserAuthenticated'] : null;

  if (! validateUser($user)) {
    $app->response()->redirect('/login');
  }

  return $user;
}

function admin (\Slim\Route $route)
{
  $user = authenticated($route);

  if ($user['RoleID'] != 'admin') {
    $app = \Slim\Slim::getInstance();
    $app->response()->redirect('/login');
  }
}

function guest (\Slim\Route $route)
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

$app->get('/', 'authenticated', function () use ($app) {
  $currentMonth = date('/Y/m', time());
  $app->response()->redirect($currentMonth);
});

$app->get('/:year/:month', 'authenticated', function ($year, $month) use ($app) {
  // display track by month
  $currentMonth = (int) date('m');
  $currentYear = (int) date('Y');

  $user = $_SESSION['UserAuthenticated'];
  $userID = $user['UserID'];

  $tracks = Track::getMonthForUser($year, $month, $userID);
  $months = Track::getMonthList($userID);

  $currentMonthData = ['Month' => $currentMonth, 'Year' => $currentYear];

  // for fresh users
  if (! $months->count()) {
    $months[] = $currentMonthData;
  }

  // set selected
  $hasCurrentMonth = false;
  $hasSelected = false;
  foreach ($months as &$m) {
    if ($m['Month'] == $month && $m['Year'] == $year) {
      $m['Selected'] = true;
      $hasSelected = true;
    }

    if ((int) $m['Month'] == $currentMonth && (int) $m['Year'] == $currentYear)
      $hasCurrentMonth = true;
  }

  if (! $hasCurrentMonth) {
    if (! $hasSelected)
      $currentMonthData['Selected'] = true;

    $months[] = $currentMonthData;
  }

  $modules = Module::all()->sortBy('Name')->toArray();
  $taskTypes = TaskType::all()->sortBy('Name')->toArray();

  $app->render('tracks.html', [
    'tracks' => $tracks->toArray(),
    'year' => $year,
    'month' => $month,
    'months' => $months,
    'taskTypes' => $taskTypes,
    'modules' => $modules,
    'emptyRow' => ((int) $year == $currentYear && $currentMonth == (int) $month)
  ]);
})->conditions(array('year' => '(20)\d\d', 'month' => '\d(\d)?'));


$app->get('/export/:year/:month', 'authenticated', function ($year, $month) use ($app) {
  $user = $_SESSION['UserAuthenticated'];
  $userID = $user['UserID'];

  $tracks = Track::getMonthForUser($year, $month, $userID);
  $months = Track::getMonthList($userID);

  $export = new Export();
  $export->generateSingle($tracks->toArray());

  exit;
})->conditions(array('year' => '(20)\d\d', 'month' => '\d(\d)?'));

$app->get('/export-all', 'authenticated', function () use ($app) {
  $user = $_SESSION['UserAuthenticated'];
  $userID = $user['UserID'];

  $months = Track::getMonthList($userID);

  if (empty($months) || ! $months->count())
    $app->response()->redirect('/');

  $data = [];
  foreach ($months as $m) {
    $month = $m['Month'];
    $year = $m['Year'];

    $monthlyTracks = Track::getMonthForUser($year, $month, $userID)->toArray();

    if (empty($monthlyTracks))
      continue;

    $monthData = [
      'Month' => $month,
      'Year' => $year,
      'Data' => $monthlyTracks
    ];

    $data[] = $monthData;
  }

  if (empty($data))
    $app->response()->redirect('/');

  $export = new Export();
  $export->generateAllMonths($data);

  exit;
});

$app->get('/export-all-users(/:year/:month)', 'admin', function ($year = null, $month = null) use ($app) {
  $users = User::all()->toArray();

  $currentMonth = $year && $month ? [[
    'Month' => $month,
    'Year' => $year
  ]] : null;

  $data = [];
  foreach ($users as $user) {
    $userID = $user['UserID'];

    $months = empty($currentMonth) ? Track::getMonthList($userID)->toArray() : $currentMonth;

    if (empty($months))
      continue;

    $userData = [
      'User' => $user,
      'MonthData' => []
    ];

    foreach ($months as $m) {
      $month = $m['Month'];
      $year = $m['Year'];

      $monthlyTracks = Track::getMonthForUser($year, $month, $userID)->toArray();

      if (empty($monthlyTracks))
        continue;

      $monthData = [
        'Month' => $month,
        'Year' => $year,
        'Data' => $monthlyTracks
      ];

      $userData['MonthData'][] = $monthData;
    }

    if (empty($userData['MonthData']))
      continue;

    $data[] = $userData;
  }

  if (empty($data))
    $app->response()->redirect('/');

  $export = new Export();
  $export->generateAllUsers($data);

  exit;
})->conditions(array('year' => '(20)\d\d', 'month' => '\d(\d)?'));


$app->post('/track/empty', 'authenticated', function () use ($app) {
  header("Content-Type: application/json");

  $modules = Module::all()->sortBy('Name')->toArray();
  $taskTypes = TaskType::all()->sortBy('Name')->toArray();

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

$app->post('/track', 'authenticated', function () use ($app) {
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

$app->post('/track/delete/:id', 'authenticated', function ($trackID) use ($app) {
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
$app->post('/track/:id', 'authenticated', function ($id) use ($app) {
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
$app->get('/password', 'authenticated', function () use ($app) {
  $app->render('password.html', [
    'invalid' => $app->request()->params('invalid') == 1
  ]);
});

$app->post('/password', 'authenticated', function () use ($app) {
  $password = $app->request()->params('password');
  $passwordCheck = $app->request()->params('password-check');

  if (strlen($password) > 5 && $password == $passwordCheck) {
    User::updatePassword($password);
    $app->flash('info', 'Password successfully changed');
    $app->response()->redirect('/');
  }
  else {
    $app->response()->redirect('/password?invalid=1');
  }
});

// display login screen
$app->get('/logout', 'authenticated', function () use ($app) {
  unset($_SESSION['UserAuthenticated']);
  $app->response()->redirect('/');
});

// display login screen
$app->get('/login', 'guest', function () use ($app) {
  $app->render('login.html', [
    'invalid' => $app->request()->params('invalid') == 1
  ]);
});

$app->post('/login', 'guest', function () use ($app) {
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