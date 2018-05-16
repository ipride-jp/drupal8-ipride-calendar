var $ = jQuery;
$(function() {
  $.each(['weekend', 'holiday', 'not-this-month', 'today'], function() {
    var class_name = this.toString();
    $("div." + class_name).each(function() {
      $(this).parent().addClass(class_name);
    });
  });

  $('div.holiday-text').each(function() {
    var prev = $(this).prev();
    prev.html(prev.html() + '&nbsp;' + $(this).html());
  });

  //calendar month
  $('#edit-calendar-table div.event').each(function() {
    $(this).css({
     'color': $(this).attr('data-color'),
     'background-color': $(this).attr('data-background-color')
    });
  }).click(function() {
    redirectToEventView($(this));
    return false;
  });

  $("#edit-calendar-table td").click(function() {
    redirectToNewEvent($(this).find("div.data"));
    return false;
  });
});
