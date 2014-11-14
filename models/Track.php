<?php

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Connection;

class Track extends Illuminate\Database\Eloquent\Model
{
  protected $table = "Track";
  protected $primaryKey = 'TrackID';
  public $timestamps = false;

  public static function getMonthForUser ($year, $month, $userID)
  {
    // just a basic precaution
    $year = (int) $year;
    $month = (int) $month;
    $userID = (int) $userID;

    return Track::query()
      ->select(array('Track.*', 'TaskType.Name AS TaskType', 'Module.Name AS Module'))
      ->where('UserID', '=', $userID)
      ->where('IsDeleted', '=', '0')
      ->whereRaw('YEAR(TimeStart) = ' . $year)
      ->whereRaw('MONTH(TimeStart) = ' . $month)
      ->join('TaskType', 'Track.TaskTypeID', '=', 'TaskType.TaskTypeID', 'left')
      ->join('Module', 'Module.ModuleID', '=', 'Track.ModuleID', 'left')
      ->orderBy('TimeStart')
      ->get();
  }

  public static function getMonthList ($userID)
  {
    return Track::query()
      ->selectRaw('YEAR(TimeStart) AS `Year`, MONTH(TimeStart) AS `Month`')
      ->where('UserID', '=', $userID)
      ->where('IsDeleted', '=', '0')
      ->orderBy('TimeStart')
      ->groupBy(new Expression('YEAR(TimeStart), MONTH(TimeStart)'))
      ->get();
  }

  public static function newTrack ()
  {
    return Track::firstOrNew(['TrackID' => null]);
  }

  public static function getTrack ($trackID)
  {
    // just a basic precaution
    $trackID = (int) $trackID;

    return Track::firstOrNew(['TrackID' => $trackID, 'IsDeleted' => 0]);
  }

  public static function getVerifiedData ($data = array())
  {
    $now = date('Y-m-d H:i:s', self::getCurrentTimePart());
    $nowEnd = date('Y-m-d H:i:s', self::getCurrentTimePart(1));

    // default data
    $newData = [
      'Location' => 'Office',
      'TimeStart' => $now,
      'TimeEnd' => $nowEnd,
      'Description' => 'Ticket description',
      'TaskTypeID' => null,
      'ModuleID' => null,
      'Ticket' => null
    ];

    // locations are just one of two values
    if (isset($data['Location']) && in_array($data['Location'], ['Office', 'Home']))
      $newData['Location'] = $data['Location'];

    // we have to combine date with times, to get two full dates
    if (isset($data['Date']) && isset($data['TimeStart']) && isset($data['TimeEnd'])) {
      $timeStart = $data['Date'] . ' ' . $data['TimeStart'];
      $timeEnd = $data['Date'] . ' ' . $data['TimeEnd'];

      $timeStart = static::parseDate($timeStart);
      $timeEnd = static::parseDate($timeEnd);

      if ($timeStart && $timeEnd) {
        if ($timeStart > $timeEnd)
          $timeEnd->modify('+1 day');

        $newData['TimeStart'] = $timeStart->format('Y-m-d H:i:s');
        $newData['TimeEnd'] = $timeEnd->format('Y-m-d H:i:s');
      }
    }

    // task type is numeric and indexed in table Module
    if (isset($data['TaskTypeID']) && $data['TaskTypeID'] > 0)
      $newData['TaskTypeID'] = (int) $data['TaskTypeID'];

    // module is numeric and indexed in table Module
    if (isset($data['ModuleID']) && $data['ModuleID'] > 0)
      $newData['ModuleID'] = (int) $data['ModuleID'];

    // ticket is numeric and positive
    if (isset($data['Ticket'])) {
      $ticket = (int) str_replace('#', null, $data['Ticket']);

      if ($ticket > 0)
        $newData['Ticket'] = $ticket;
    }

    if (isset($data['Description']) && $data['Description'])
      $newData['Description'] = $data['Description'];

    return $newData;
  }

  public static function saveTrack ($data, $trackID = null)
  {
    $track = $trackID ? Track::getTrack($trackID) : new Track();

    $user = $_SESSION['UserAuthenticated'];
    $userID = $user['UserID'];

    $track->UserID = $userID;
    $track->Location = $data['Location'];
    $track->TimeStart = $data['TimeStart'];
    $track->TimeEnd = $data['TimeEnd'];
    $track->Description = $data['Description'];
    $track->TaskTypeID = $data['TaskTypeID'];
    $track->ModuleID = $data['ModuleID'];
    $track->Ticket = $data['Ticket'];

    $track->save();

    return $track->TrackID;
  }

  public function delete ()
  {
    $this->IsDeleted = 1;

    $this->save();
  }

  private static function getCurrentTimePart ($add = 0)
  {
    $t = time();
    $interval = 15 * 60;

    $interval += ($add * $interval);

    return $last = $t - $t % $interval;
  }

  /**
   * @param $date
   * @return DateTime
   */
  public static function parseDate ($date)
  {
    return DateTime::createFromFormat('d.m.Y H:i', $date);
  }
}