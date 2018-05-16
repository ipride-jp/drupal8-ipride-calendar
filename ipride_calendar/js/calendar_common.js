var $ = jQuery;

$(function() {
  //setTimeout(trucEventSummary, 500);
  $(window).resize(function() {
    //trucEventSummary();
  });
});

function trucEventSummary() {
  $('.trunc-str').each(function() {
    var display_str_length = Math.round($(this).parent().width() / 10);
    var original_str = $(this).text().trim();
    var original_summary_elem = $(this).find(".original-summary");
    if (original_summary_elem.length) {
      original_str = original_summary_elem.text().trim();
    }

    var icon_html = "";
    $(this).find("span").each(function() {
      icon_html += this.outerHTML;
    });

    //truncate string
    $(this).text(truncate(original_str, display_str_length));

    //append original text
    var html = '<div class="original-summary" style="display: none"></div>';
    $(this).append(html);
    $(this).find(".original-summary").text(original_str);

    //append icons
    $(this).append(icon_html);
  });
}

function redirectToEventView(jquery_elem) {
  var calendar_id = jquery_elem.attr("data-calendar-id");
  var event_id = jquery_elem.attr("data-event-id");
  var event_file_base_name = jquery_elem.attr("data-event-file-base-name");

  if (!calendar_id || !event_id) {
    console.log('Calendar id or event id is undefined.');
    return;
  }

  var param_obj = {};
  if (drupalSettings.ipride_calendar.destination) {
    param_obj.destination = drupalSettings.ipride_calendar.destination;
  }

  var url = drupalSettings.path.baseUrl
    + drupalSettings.path.currentPath
    + "/calendar/" + calendar_id
    + "/event/" + event_id;

  window.location = url + '?' + $.param(param_obj);
}

function redirectToNewEvent(jquery_elem) {
  var date = jquery_elem.attr("data-ymd");
  var param_obj = {};
  param_obj.begin_date = date;
  param_obj.end_date = date;

  if (jquery_elem.attr("data-all-day")) {
    param_obj.all_day = 1;
  }

  if (jquery_elem.attr("data-hour")) {
    param_obj.begin_time = jquery_elem.attr("data-hour") + ":00";
    param_obj.end_time = jquery_elem.attr("data-hour") + ":30";
  }

  if (drupalSettings.ipride_calendar.destination) {
    param_obj.destination = drupalSettings.ipride_calendar.destination;
  }

  window.location = drupalSettings.path.baseUrl
    + drupalSettings.path.currentPath
    + "/event/add?" + $.param(param_obj);
}

/**
 * Refers to https://gist.github.com/Cside/806088
 */
function truncate(str, size, suffix) {
  if (!str) str = '';
  if (!size) size = 32;
  if (!suffix) suffix = '...';
  var b = 0;
  for (var i = 0;  i < str.length; i++) {
    b += str.charCodeAt(i) <= 255 ? 1 : 2;
    if (b > size) {
        return str.substr(0, i) + suffix;
    }
  }

  return str;
}
