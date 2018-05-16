var $ = jQuery;
$(function() {
  function showTimeInputs(visible) {
    if (visible) {
      $("#edit-begin-datetime-time").show();
      $("#edit-end-datetime-time").show();
    } else {
      $("#edit-begin-datetime-time").hide();
      $("#edit-end-datetime-time").hide();
    }
  }

  function getDateTime(name) {
    return new Date($("#edit-" + name + "-date").val()
      + ' ' + $("#edit-" + name + "-time").val());
  }

  function setDateTime(name, date_time) {
    var date_str = $.datepicker.formatDate('yy-mm-dd', date_time);
    $("#edit-" + name + "-date").val(date_str);

    var hours = date_time.getHours();
    var minutes = date_time.getMinutes();
    var time_str = ("0" + hours).slice(-2) + ":" + ("0" + minutes).slice(-2);
    $("#edit-" + name + "-time").val(time_str);
  }


  $("#edit-all-day").click(function() {
    showTimeInputs(!$(this).prop("checked"));
  });

  showTimeInputs(!$("#edit-all-day").prop("checked"));

  var begin_time_old_val = getDateTime("begin-datetime");
  $("#edit-begin-datetime-date, #edit-begin-datetime-time").change(function(e) {
    var begin_time_new_val = getDateTime("begin-datetime");
    if (isNaN(begin_time_old_val.getTime()) || isNaN(begin_time_new_val.getTime())) {
      return;
    }

    var delta = begin_time_new_val - begin_time_old_val;
    begin_time_old_val = begin_time_new_val;

    var end_time_val = getDateTime("end-datetime");
    end_time_val.setTime(end_time_val.getTime() + delta);
    setDateTime('end-datetime', end_time_val);
  });
});
