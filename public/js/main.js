(function($) {
  var login = $('div.login');

  var details = $('div.details');
  var months = $('select.months', details)

  var trackList = $('div.track-list');
  var trackSum = $('div.track-sum', trackList);
  var day = 60 * 60 * 24;
  var focused = null;
  var transitionEnd = 'webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend';
  var animationEnd = 'webkitAnimationEnd oanimationend msAnimationEnd animationend';

  if (details) {
    months.change(function() {
      var url = months.val();
      location.href = url;
    })
  }

  if (trackList.length) {
    // add empty track
    trackList.on('click', '.track span.action .add', function() {
      var track = $(this).closest('.track');

      prepareTrack(track);
    });

    // remove track and focus to next/previous or prepare new track if removing last track
    trackList.on('click', '.track span.action .remove', function() {

      //this must be here
      if (confirm('Do your really want to delete entry?')) {
        var track = $(this).closest('.track');

        var nextTrack = track.next('.track');
        if (!nextTrack.length)
          nextTrack = track.prev('.track');

        if (!nextTrack.length)
          prepareTrack();

        deleteTrack(track);

        if (nextTrack.length)
          nextTrack.find('span.time-start input').focus();
      }
    });

    // autoresize textarea if needed
    trackList.on('keyup', '.description textarea', function() {
      var textarea = $(this);
      textarea.height(textarea.prop('scrollHeight'));
    });

    // prepare new track and append it after current line, or to the end of table
    function prepareTrack(after, silent, lines) {
      var href = '/track/empty';

      var data = {};
      if (after) {
        var date = after.find('input.date').val();
        var timeStart = after.find('input[name=time-start]').val();
        var timeEnd = after.find('input[name=time-end]').val();

        data.date = date;
        data['time-start'] = timeStart;
        data['time-end'] = timeEnd;
      }

      if (lines)
        data['lines'] = lines;


      var promise = new $.Deferred();

      $.ajax({
        url : href,
        type : 'POST',
        dataType : 'json',
        data : data
      }).done(function(data) {
        if (data.Status == 'Success') {
          if (!lines)
            lines = 1;

          for (var i = 0; i < lines; i++) {
            var newTrack = $(data.TrackHtml);

            if (after)
              after.after(newTrack);
            else
              trackSum.before(newTrack);

            newTrack.addClass('unedited');

            if (!silent) {
              newTrack.addClass('invalid');
              newTrack.find('input[name=time-start]').focus().select();

              sortTracks(newTrack);
            }
          }

          promise.resolve();
        }
      });

      return promise.promise();
    }

    // trigger all input masks
    trackList.on('focusin', 'input, textarea', function() {
      var input = $(this);

      if (!input.data('has-inputmask')) {
        input.inputmask();
        input.data('has-inputmask', true)
      }

      if (!input.data('trackpaste')) {
        input.gridpaste({
          onLineMissing : function(lines) {
            return prepareTrack(null, true, lines);
          },
          callback : function() {
            var tracks = trackList.find('div.track');
            tracks.each(function() {
              var track = $(this);
              saveTrack(track);
              sortTracks(track);
              calculateHours(track);
              resizeAllTextArea();
            })
          }
        });
        input.data('trackpaste', true);
      }
    });

    // some events
    trackList.on('keydown', '.track input, .track textarea', function(e) {
      if (e.ctrlKey || e.shiftKey)
        return;

      // handle directions [37 => left, 38 => up, 39 => right, 40 => down]
      // others [9 => tab]
      var disableFor = [38, 40];

      if ($.inArray(e.which, disableFor) > -1) {
        e.preventDefault();

        var input = $(this);
        var inputName = input.attr('name');
        var track = input.closest('.track');

        var isTextarea = input.is('textarea');

        switch (e.which) {
          case 40:
            var next = null;
            if (isTextarea) {
              if (input.prop("selectionStart") != input.val().length)
                input.prop("selectionStart", input.val().length).prop("selectionEnd", input.val().length);
              else
                next = track.next('.track').find('textarea[name="' + inputName + '"]');
            } else {
              next = track.next('.track').find('input[name="' + inputName + '"]');
            }

            if (next)
              next.focus();

            break;

          case 38:
            var prev = null;
            if (isTextarea) {
              if (input.prop("selectionStart") != 0)
                input.prop("selectionStart", 0).prop("selectionEnd", 0);
              else
                prev = track.prev('.track').find('textarea[name="' + inputName + '"]');
            } else {
              prev = track.prev('.track').find('input[name="' + inputName + '"]');
            }

            if (prev)
              prev.focus();

            break;
        }

        return false;
      } else if (e.which == 9) {
        var input = $(this);

        if (input.attr('name') == 'ticket') {
          var track = input.closest('.track');
          if (track.next('.track').length == 0) {
            e.preventDefault();
            prepareTrack(track);

            return false;
          }
        }
      }
    });

    trackList.on('keyup', '.track input, .track textarea', function() {
      var input = $(this);
      var track = input.closest('.track');

      if (input.hasClass('time'))
        calculateHours(track);
    });

    trackList.on('focus', '.track input', function() {
      focused = $(this);
    });

    trackList.on('blur', '.track input, .track textarea', function() {
      var input = $(this);
      var track = input.closest('.track');

      if (input.hasClass('date') || input.hasClass('time'))
        sortTracks(track);
    });

    trackList.on('change', '.track select, .track input, .track textarea', function() {
      var input = $(this);
      var track = input.closest('.track');

      saveTrack(track);
    });


    function validTrack(track) {
      var date = track.find('input.date');
      var timeStart = track.find('input[name=time-start]');
      var timeEnd = track.find('input[name=time-end]');
      var task = track.find('textarea');

      var validDate = verifyDate(date.val());
      var validTimeStart = verifyTime(timeStart.val());
      var validTimeEnd = verifyTime(timeEnd.val());
      var validTask = task.val().trim() != '';

      date.toggleClass('invalid', !validDate);
      timeStart.toggleClass('invalid', !validTimeStart);
      timeEnd.toggleClass('invalid', !validTimeEnd);
      task.toggleClass('invalid', !validTask);

      return validDate && validTimeStart && validTimeEnd && validTask;
    }

    function saveTrack(track) {
      track.removeClass('unedited');

      var isValid = validTrack(track);

      track.toggleClass('invalid', !isValid);

      if (isValid) {
        function onError() {
          track.addClass('warning').one(animationEnd, function() {
            console.log('transition error end');
            track.removeClass('warning');
          });
        }

        var trackID = track.data('track');
        var href = '/track';

        if (trackID)
          href += '/' + trackID;

        var month = months.data('month');
        var year = months.data('year');

        // prepare data
        var data = {
          location : track.find('select[name=location]').val(),
          date : track.find('input[name=date]').val(),
          'time-start' : track.find('input[name=time-start]').val(),
          'time-end' : track.find('input[name=time-end]').val(),
          'description' : track.find('textarea').val(),
          'task-type' : track.find('select[name=task-type]').val(),
          module : track.find('select[name=module]').val(),
          ticket : track.find('input[name=ticket]').val(),
          'current-month' : month,
          'current-year' : year
        };

        track.addClass('loading');
        track.removeClass('warning');
        $.ajax({
          url : href,
          type : 'POST',
          dataType : 'json',
          data : data
        }).done(function(data) {
          if (data.Status == 'Success') {
            track.data('track', data.TrackID);

            track.addClass('success').one(animationEnd, function() {
              console.log('transition end');
              track.removeClass('success');
            });

            if (data.Months) {
              months.empty();
              $.each(data.Months, function(i, m) {
                var option = $('<option />');
                option.text(m.Year + ' / ' + m.Month);
                option.val(m.Url);

                if (parseInt(m.Year) == parseInt(year) && parseInt(m.Month) == parseInt(month))
                  option.prop('selected', true);

                months.append(option);
              });
            }
          } else {
            onError();
          }
        }).fail(function() {
          onError();
        }).always(function() {
          track.removeClass('loading');
        });
      }
    }

    function deleteTrack(track) {
      {
        var trackID = track.data('track');

        if (!trackID)
          track.remove();

        var href = '/track/delete/' + trackID;

        track.addClass('loading');
        $.ajax({
          url : href,
          type : 'POST',
          dataType : 'json'
        }).done(function(data) {
          if (data.Status == 'Success')
            track.remove();
        }).always(function() {
          track.removeClass('loading');
        });
        ;
      }
    }

    function calculateHours(track) {
      var start = track.find('input[name=time-start]').val();
      var end = track.find('input[name=time-end]').val();

      var difference = 0;
      if (verifyTime(start) && verifyTime(end)) {
        var secondsStart = toSeconds(start);
        var secondsEnd = toSeconds(end);

        if (secondsEnd < secondsStart) {
          secondsEnd += day;
        }

        difference = (secondsEnd - secondsStart) / 3600.0;

        var hours = track.find('span.hours');
        hours.text(difference.toFixed(2));
      }

      sumHours();
    }

    function sumHours() {
      var allHours = trackList.find('.track span.hours');
      var sum = 0;
      allHours.each(function() {
        var field = $(this);
        var hours = parseFloat(field.text());
        sum += hours;
      });

      trackSum.find('span.hours').text(sum.toFixed(2));
    }

    function sortTracks(track) {
      var date = track.find('input.date').val();
      var time = track.find('input[name=time-start]').val();

      if (!date || !verifyDate(date))
        return;

      var trackDate = getDate(date, verifyTime(time) ? time : null);

      var nextTracks = track.nextAll('.track');
      var moveAfter = null;
      nextTracks.each(function() {
        var nextTrack = $(this);
        date = nextTrack.find('input.date').val();
        time = nextTrack.find('input[name=time-start]').val();

        // skip empty dates
        if (!verifyDate(date))
          return;

        var nextDate = getDate(date, verifyTime(time) ? time : null);

        if (nextDate < trackDate)
          moveAfter = nextTrack;
        else
          return false;
      });

      if (moveAfter) {
        moveTrack(track, moveAfter);
      } else {
        var prevTracks = track.prevAll('.track');
        var moveBefore = null;
        prevTracks.each(function() {
          var prevTrack = $(this);
          date = prevTrack.find('input.date').val();
          time = prevTrack.find('input[name=time-start]').val();

          // skip empty dates
          if (!verifyDate(date))
            return;

          var prevDate = getDate(date, verifyTime(time) ? time : null);

          if (prevDate > trackDate)
            moveBefore = prevTrack;
          else
            return false;
        });

        if (moveBefore)
          moveTrack(track, moveBefore, true);
        else
          fixBorders();
      }
    }

    function getDate(date, time) {
      var dateParts = date.split('.');
      var year = dateParts[2];
      var month = dateParts[1] - 1; // months are zero indexed
      var day = dateParts[0];

      var hour = 0;
      var minute = 0;
      if (time) {
        var timeParts = time.split(':');
        hour = timeParts[0];
        minute = timeParts[1];
      }

      return new Date(year, month, day, hour, minute);
    }

    // verify format HH:MM
    function verifyTime(time) {
      var regex = /^\s*([01]?\d|2[0-3]):?([0-5]\d)\s*$/;
      return regex.test(time);
    }

    function verifyDate(date) {
      var regex = /^(0?[1-9]|[12][0-9]|3[01])[\.](0?[1-9]|1[012])[\.]\d{4}$/;
      return regex.test(date);
    }

    // time to seconds for calculation
    function toSeconds(time) {
      var parts = time.split(':');
      return parts[0] * 3600 + parts[1] * 60;
    }

    function moveTrack(track, track2, before) {
      var tracks = before ? track.prevUntil(track2, '.track') : track.nextUntil(track2, '.track');
      tracks = tracks.size() + 1

      var procent = tracks * 100;

      if (before)
        procent = -procent;

      track.addClass('shadow').css({
        transform : 'translate3d(0px, ' + procent + '%, 0px) scale(1.01)'
      }).one(transitionEnd,
        function(e) {
          if (before)
            track2.before(track);
          else
            track2.after(track);

          track.css({
            transform : 'none'
          }).removeClass('shadow');

          fixBorders();

          if (focused) {
            focused.focus();
          }
        });
    }

    function fixBorders() {
      var tracks = trackList.find('.track');
      var lastDate = null;
      tracks.each(function() {
        var track = $(this);
        var date = track.find('input.date').val();
        var equals = lastDate == date;

        track.toggleClass('date-start', !equals);
        lastDate = date;
      })
    }

    // resize all textareas
    function resizeAllTextArea() {
      $('textarea', trackList).each(function() {
        var textarea = $(this);
        textarea.height(textarea.prop('scrollHeight'));
      });
    }

    window.onbeforeunload = function() {
      if (trackList.find('.track.invalid:not(.unedited)').length)
        return "Not all tracks are saved, do you want to leave the page?";
    }


    // if empty month || add-empty-row flag, prepare new track
    if (!trackList.find('.track').length || trackList.hasClass('add-empty-row'))
      setTimeout(function() {
        prepareTrack();
      }, 300);


    resizeAllTextArea();
  }

  if (login.length) {
    login.find('input[name=username]').focus();
  }
})(jQuery);