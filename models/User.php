<?php

class User extends Illuminate\Database\Eloquent\Model
{
  protected $table = "User";
  public $timestamps = false;


  public static function authenticate ($username, $password)
  {
    return User::query()->where('Username', '=', $username)->where('Password', '=', md5($password))->get();
  }
}