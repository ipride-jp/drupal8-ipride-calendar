var $ = jQuery;
$(function() {
  $("div.event").click(function() {
    redirectToEventView($(this));
    return false;
  });

  $(".week-calendar-table tr").click(function() {
    redirectToNewEvent($(this));
  });
});
