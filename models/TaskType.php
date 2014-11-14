<?php

class TaskType extends Illuminate\Database\Eloquent\Model
{
  protected $table = "TaskType";
  protected $primaryKey = 'TaskTypeID';
  public $timestamps = false;
  public static $snakeAttributes = false;
}