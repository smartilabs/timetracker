(function($) {

  function handleTrack(pasted, options) {
    // $(this) is the element that got pasted-into

    var that = $(this);
    var cell = that.closest("span");
    var row = cell.closest("div.track");
    var table = row.closest(".track-list");
    var x = cell.prevAll('.has-input').length;
    var y = row.prevUntil().length;

    // note that this is in matrix[y][x] order, due to data format:
    pasted = pasted.replace(/\r?\n$/, ""); // chomp()
    var data_matrix = pasted.split(/\r?\n/).map(function(elem, idx, array) {
      return elem.split(/\t/);
    });

    var getTableMatrix = function() {
      return table.find("div.track").map(function() {
        return $(this).find("span.has-input");
      });
    }

    // note that this is in matrix[y][x] order, also:
    var table_matrix = getTableMatrix();

    var missingLines = data_matrix.length - table_matrix.length + y;
    var promise = null;
    if (options.onLineMissing && missingLines > 0) {
      promise = options.onLineMissing(missingLines);
    }

    var callback = options.callback;

    if (data_matrix.length > 1 || data_matrix[0].length > 1) {
      if (promise) {
        promise.done(function() {
          // refresh table matrix and continue
          table_matrix = getTableMatrix();

          fillData(table_matrix, data_matrix, x, y, callback)
        });
      } else
        fillData(table_matrix, data_matrix, x, y, callback)
    } else {
      if (that.is('input')) {
        that.val(data_matrix[0][0]);
        that.change();
      }
      else
        return data_matrix[0][0];
    }

    return null; // don't put anything else in the current cell
  }

  function fillData(table_matrix, data_matrix, x, y, callback) {
    for (var j = 0; j < data_matrix.length; j++) {
      // copying from data_matrix[j] to table_matrix[j+y]
      if (table_matrix.length < (j + y))
        continue;
      for (var i = 0; i < data_matrix[j].length; i++) {
        // copying from data_matrix[j][i] to table_matrix[j+y][i+x]
        if (table_matrix[j + y].length < (i + x))
          continue;

        var value = data_matrix[j][i];

        var c = $(table_matrix[j + y][i + x]);
        var input = $('input, textarea, select', c);

        if (input.is('input') || input.is('textarea')) {
          input.val(value);
        } else if (input.is('select')) {
          if (value) {
            var values = getSelectIDs(input);

            var value = value.trim().toLowerCase();
            if (values[value])
              input.val(values[value]);
          }
        }
      }
    }

    if (callback)
      callback();
  }

  function getSelectIDs(select) {
    var options = select.find('option');
    var values = {};

    $.each(options, function() {
      var option = $(this);
      var text = option.text().trim().toLowerCase();
      var id = option.attr('value').trim();

      values[text] = id;
    });

    return values;
  }

  $.fn.gridpaste = function(options) {
    if (!options)
      options = {};

    $(this).catchpaste(handleTrack, options);
  };
})(jQuery);