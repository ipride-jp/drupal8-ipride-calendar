var $ = jQuery;

$(function() {
  // $('.color').ColorPickerSliders();

  $('.color').colorPicker({
    selector: '.color',
    customBG: '#222',
    margin: '4px -2px 0',
    doRender: 'div div',
    opacity: false,

    buildCallback: function($elm) {
      var colorInstance = this.color,
        colorPicker = this,
        random = function(n) {
          return Math.round(Math.random() * (n || 255));
        };

      $elm.append('<div class="cp-memory">' +
        '<div style="background: black"></div>' +
        '<div style="background: blue"></div>' +
        '<div style="background: brown"></div>' +
        '<div style="background: cyan"></div>' +
        '<div style="background: green"></div>' +
        '<br>' +
        '<div style="background: magenta"></div>' +
        '<div style="background: purple"></div>' +
        '<div style="background: red"></div>' +
        '<div style="background: yellow"></div>' +
        '<div style="background: white"></div>' +
        '</div>'
      ).
      on('click', '.cp-memory div', function(e) {
        var $this = $(this);

        if (this.className) {
          $this.parent().prepend($this.prev()).children().eq(0).
            css('background-color', '#' + colorInstance.colors.HEX);
        } else {
          colorInstance.setColor($this.css('background-color'));
          colorPicker.render();
        }
      });
    },

    cssAddon: // could also be in a css file instead
      '.cp-memory {margin-bottom:6px; clear:both;}' +
      '.cp-xy-slider {width: 200px; height: 200px;}' +
      '.cp-z-slider {width: 40px; height: 200px;}' +
      '.cp-xy-slider:active {cursor:none;}' +
      '.cp-memory div {float:left; width:40px; height:40px;' +
      'margin-bottom:6px; margin-right:1px; border-width:4px; border-style:solid;' +
      'background:rgba(0,0,0,1); text-align:center; line-height:17px;}'
  });
});
