var $ = jQuery;
$(function() {
  var current_hour_selector = ".day-calendar-table tr:nth-child("
    + (drupalSettings.ipride_calendar.now_hour + 1) + ")";

  $(current_hour_selector).addClass('current_hour');
  var scroll_size = $(current_hour_selector).offset().top - $('.day-calendar-table-wrapper').offset().top;
  $('.day-calendar-table-wrapper').animate({
    scrollTop: scroll_size
  }, 1000);

  showEvents();

  $("div.event").click(function() {
    redirectToEventView($(this));
    return false;
  });

  $(".day-calendar-table-all-day tr, .day-calendar-table tr").click(function() {
    redirectToNewEvent($(this));
  });
});

function showEvents() {
  //calendar day
  var position = $('.day-calendar-table tr:nth-child(1) td:nth-child(2)').position();
  position.left += 5;
  var cell_height = $('.day-calendar-table tr:nth-child(2) td:nth-child(2)').position().top
    - $('.day-calendar-table tr:nth-child(1) td:nth-child(2)').position().top;
  var bottom_right_position = {top: 0, left:0};

  $('.day-calendar-events div.event').each(function() {
    var start_time = parseInt($(this).attr('data-start-time'));
    var time_length = parseInt($(this).attr('data-time-length'));

    var top = position.top + start_time * cell_height / 60;
    var left = position.left;
    if (top < bottom_right_position.top && left < bottom_right_position.left) {
      left = bottom_right_position.left + 10;
    }

    $(this).css('top', top);
    $(this).css('left', left);
    $(this).height(time_length * cell_height / 60);
    $(this).show();

    bottom_right_position.top = $(this).position().top + $(this).height();
    bottom_right_position.left = $(this).position().left + $(this).find('table').width();
  });
}
