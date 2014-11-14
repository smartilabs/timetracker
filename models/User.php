<?php

class User extends Illuminate\Database\Eloquent\Model
{
  protected $primaryKey = 'UserID';
  protected $table = "User";
  public $timestamps = false;


  public static function authenticate ($username, $password)
  {
    return User::query()->where('Username', '=', $username)->where('Password', '=', md5($password))->get();
  }

  public static function updatePassword ($password)
  {
    $user = $_SESSION['UserAuthenticated'];
    $userID = $user['UserID'];

    $user = User::query()->where('UserID', '=', $userID)->first();

    if ($user) {
      $user->Password = md5($password);

      $user->save();
    }
  }
}