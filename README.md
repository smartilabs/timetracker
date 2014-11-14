timetracker - codename SmartiTracker
===========

Simple time tracker created for personal use, testing php SlimFramefork for the first time.
Still in development, will work on it in my spare time.

Application goal:
Simple tracking of time for me and my coworkers, with simple summary export for user or group, by month or all time.

### Features:
 * Excel-like data paste
 * Export to excel with multiple views:
   - current month, 
   - current user all months, 
   - all users current month with summary by modules [admin only]
   - all users all time with summary by modules [admin only]
 * Multiple user (users can be manually added in database with MD5 encoded passwords)
 * Masked input fields, prevent wrong data entry
 * Tracks (or rows) for multiple tasks
 * Fields within track:
   - Location (Office or Home)
   - Date
   - Time start
   - Time end
   - Hour diff - live calculation
   - Description - multiline with expandable input field
   - Task type (ex.: Feature, Maintenance, Planning, Support)
   - Module (ex.: DB, Frontend, Backend, ...)
   - Ticket number (if you have ticketing system)
 * Live track ordering - row moves when you change date or time in ascending order
 * Tracks joined by days using css styles for easier view
 * On login, current month is automatically displayed and new track is prepared with current date and time
 * When adding track, after another track (click action [add]), new track is filled with same date and Time start is time of previous track Time end
 * All data is saved live on every field change
 * Track are not saved until all fields are valid
 * If trying to leave page with unsaved tracks, warning is shown

### Todo:
 * When pasting data everything must be in exact format - for ex.: hour must bo 09:15, it can't be 9:15. Got to enable some parsing/validation prior to changing cell value

### How to install:
 * Create new database (name it "timetracker" or "smartitracker")
 * Run install/create-tables.sql script
 * Enter data in tables User, TaskType and Module
 * In app/database.php replace host, database, username and password fields with your values
 * In public/index.php replace 'baseUrl' => '/' with your url (locally I prepared virtual host, so baseUrl is just "/")


Any ideas or suggestions are welcome.
