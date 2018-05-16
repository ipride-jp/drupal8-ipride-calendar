var $ = jQuery;
$(function() {
  //scroll to today
  var today_offset = $(".today").offset();
  if (today_offset) {
    $('html, body').animate({
      scrollTop: today_offset.top
    }, 1000);
  }

  $(".list-calendar-table tr.event-detail").hover(function() {
    $(this).addClass('selected');
  }, function() {
    $(this).removeClass('selected');
  }).click(function() {
    redirectToEventView($(this));
  });
});
