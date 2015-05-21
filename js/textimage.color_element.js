/**
 * @file
 *
 * Textimage color element.
 *
 * Alters field_suffix form element after change to the color field.
 */

(function ($) {

Drupal.behaviors.textimageColorElement = {

  attach: function(context, settings) {
    $('.textimage-color-element .form-color', context).once('textimage-color-element').each(function(index) {
      $(this).on('change', function(event) {
        var suffix = $(this).parents('.textimage-color-element').find('.field-suffix').get(0);
        $(suffix).text(this.value.toUpperCase());
      });
    });
  }

}

})(jQuery);
