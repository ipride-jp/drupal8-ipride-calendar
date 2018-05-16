var $ = jQuery;
$(function() {
  $.cookie.json = true;

  //save calendar selection to cookie
  $("#edit-calendars input").change(function() {
    saveCalendarSelections();
  });

  saveCalendarSelections();
});

function saveCalendarSelections() {
  var calendarIds = [];
  $("#edit-calendars input").each(function() {
    if (this.checked && this.value != 'on') {
      calendarIds.push(this.value);
    }
  });

  $.cookie('calendar_ids', calendarIds, {
    path: drupalSettings.path.baseUrl,
    expires: 365
  });
}
