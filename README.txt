Drupal 8 version
================

Core issues:
============
- #1977206 - Default serialization of ConfigEntities
- #2393387 - WSOD editing image effect when configuration form is Ajax enabled
- #1826362 - ImageEffects of the same image style should be able to pass variables between them
- #2359443 - Allow creating image derivatives from an Image object
- #2479487 - ImageStyles can be deleted while having dependant configuration

@todo:
======
- #2134439 - array_replace_recursive
- use of gifTransparentColor in Textimage.php
- settargeturi should implement forceExtansion
- check background color setting over a passthrogh image
- more tests for url generation
- revise directory structure for textimage_store
- buildImage fails to convert to specified image format

-------------------------------------------------------------------------------

-----------------
Textimage 8.x-3.x
-----------------

Textimage adds text to image functionality using GD2 and Freetype, enabling
users to create crisp images on the fly for use as theme objects, headings or
limitless other possibilities.

Textimage was originally written by Fabiano Sant'Ana (wundo).
- http://chuva-inc.com

Co-maintained by:
- Stuart Clark (Deciphered) http://stuar.tc/lark
- Mondrake http://drupal.org/user/1307444


Quick start instructions
------------------------
- Check requirements (below) and install / configure the modules needed.
- Install and enable the module.
- Check the Configuration page (Administration->Configuration->Media->
  Textimage) and setup.
- Ensure at least one font file is available.
- Create an image style (Administration->Configuration->Media->Image Styles)
  and use Textimage effects in combination with other effects as needed.
- Change a field of a content type (Administration->Structure->Content Types->
  {your type}->Manage Display) to be represented by a Textimage:
    - select 'Textimage' in the format dropdown (applicable to Text and Image
      fields
    - click on the gear icon
    - select the image style you created earlier from the dropdown displayed
- Your field content is now presented as a (Text)image!


-------------------------------------------------------------------------------


Features
--------
* Provides three image effects for use in Drupal's Image system:
  * Textimage text
    * Default text accepts tokens that are resolved at run-time.
    * Support for TrueType fonts and OpenType fonts.
    * Rotate your text at any angle.
    * Configurable opacity in text and background color.
    * Outline and shadow effects.
    * Line-level text alignment (left/center/right).
    * Line spacing (leading).
    * Case format conversion.
    * Easy selection of the text position on the background image.
    * Options to manage text overflow against background.
  * Textimage background
    * Background can be defined as a color, or as a fixed image, or as the
      result of the previous image effects.
    * Exact/relative sizing of the image.
* Field display formatters for Text and Image fields.
* Textimage API to generate Textimage images programmatically.
* Textimage tokens to retrieve URI/URL of generated Textimage images.
* Integrates with Metatag to use Textimage tokens in meta tags.


Requirements
------------
- Drupal 8
- GD2 and FreeType libraries

Recommended modules, for a feature rich set:
- Private file system configured
- @font-your-face       (7.x-2.6 or later)
- Media                 (7.x-1.2 or later)
- Token                 (7.x-1.5 or later)

Integration with modules:
- Metatag               (7.x-1.0-beta8 or later)

Installation instructions (long version)
----------------------------------------
- Ensure that your Drupal installation has a private file system to write
  to. Check (Administration->Configuration->Media->File system), the
  'Private file system path' field should be filled in.
- Consider recommended modules:
  - @font-your-face - if you use this module, all 'local fonts' installed
    and enabled on your system can be used by Textimage.
  - Media - if you use this module, background images can be selected via
    the media browser form.
  - Jquery Colorpicker - this module provides a user-friendly way for
    selecting colors
  - Token - this module allows to list available tokens when entering the
    default text for a 'Textimage text' effect.
- Install and enable Textimage.
- Check Textimage configuration page (Administration->Configuration->
  Media->Textimage):
  - Textimage store location - Textimage needs a structure of directories
    to store cached images, and fonts and background images if either
    @font-your-face or Media are not used. Select the file system where
    this structure can be stored. Private is recommended.
  - Fonts - select the module that will manage fonts in Textimage, and a
    default font to use. Make sure at least one font file is available.
  - Background Images - select the module that will manage background
    images in Textimage.
  - Colors - select the mode for selecting colors in Textimage. Either use
    Jquery colorpicker if the module is installed, or a plain textbox.


-------------------------------------------------------------------------------


Creating Textimage image styles
-------------------------------
- Textimage extends Drupal image system providing additional image effects.
- Just combine Textimage effects with any other image effect to deliver
  the result needed in a image style (Administration->Configuration->Media->
  Image Styles).
- Image styles using Textimage effects are extended to collect Textimage
  only options:
  - Image destination - allows to specify in which file system the derivative
    images (i.e. the final output) shall be stored. By default, this is
    the same destination as specified in 'Textimage store location' in
    configuration, but can be set to alternative file systems (e.g. Amazon
    S3 etc.)


Image effect: Textimage text
----------------------------
- Refresh preview. Allows to refresh the effect preview without leaving
  the effect settings. Optionally, 'Visual aids in preview' will display
  the bounding boxes of the text elements to help finding proper sizing
  and padding setup.
- Default text. Accepts tokens that are resolved at run-time. If Token
  module is installed, a list of available tokens is displayed on the
  effect form. See the 'Using Textimage field formatters and tokens'
  section below for more information regarding the usage of tokens.
- Font
  - Font. Select from a list of available fonts.
  - Size. In points.
  - Rotation. In degrees.
  - Color. Opacity can be defined.
  - Outline. 'Outlines' the font. Size, color, opacity of the outline
    can be specified.
  - Shadow. Provides a shadow effect to the font. Offset, elongation,
    color, opacity of the shadow can be specified.
- Text
  - Maximum width. Maximum width of the text image, inclusive of padding.
    Text lines wider than this will be wrapped. Leave blank to disable
    wrapping. In case of rotation, the width of the final image rendered
    will differ, to accomodate the rotation. If you need a strict
    width/height, add image resize/scale/crop effects afterwards.
  - Fixed width. If checked, the width will always be equal to the maximum
    width.
  - Text alignment.
  - Line spacing. Additional space between text lines.
  - Case format. Specifies conversion of text before rendering.
- Layout
  - Position. Defines where in the source image the text should be placed.
    Beside an anchor selection, additional offset can be specified. In case
    the text element overflows the underlying image, the options are to
    extend the underlying image, crop the overflowing text, or scale the
    text to fit in the underlying image.
  - Padding. Specifies if the text should be padded additionally within the
    text box (the part of the image where the text is laid).
  - Background color. Color / opacity of the text box.


Image effect: Textimage background
----------------------------------
- Background image - can be defined as a color, or as a fixed image, or as the
  result of the previous image effects.
- Background color - either used as the background color, or to fill in gaps
  coming from resizing.
- Exact size - In case the size specified is smaller than the source, the options
  are to scale/crop/resize the image.
- Relative size - Basically adds to the image a border of the size specified.


-------------------------------------------------------------------------------


Using Textimage image styles
----------------------------

1. via Content Type field display formatters

   - Access 'Content type' administration features via Administration->
     Structure->Content Types
   - Select the content type for which you want to manage a Textimage field
     (e.g. Article, Basic page, etc.)
   - If you need to create a new field, in the 'Manage Fields' tab, add a new
     field of type 'Text' (or 'Long text', or 'Long text and summary') or
     'Image'
   - In the 'Manage Display' tab, select a 'Textimage' format for the field
     created above (or any existing one)
   - Click on the gear icon
   - Select from the 'Image style' dropdown the image style you want to use to
     represent the content as a Textimage
   - If the field is a multi-value text field, an option is presented to select
     whether to generate a single image or multiple images. In the first case,
     the formatter will pass sequentially each field value to a separate image
     style's 'Textimage text' effect. Each effect must define where the text
     should be placed on the image. In the second case, each field value will
     be passed to a separate instance of the image style, and only the first
     'Textimage text' effect will be used to produce a separate styled image.
   - Optionally, select from the 'Link image to' dropdown whether the Textimage
     should be clickable, linking to either the node content or the image file.
     By default, the Textimage is not linked.
   - Optionally, enter a value for the image 'alt' attribute in the 'Alternate
     text' textbox. This text will be used by screen readers, search engines,
     or when the image cannot be loaded. Tokens can be used.
   - Optionally, enter a value for the image 'title' attribute in the 'Title'
     textbox. The title is used as a tool tip when the user hovers the mouse
     over the image. Tokens can be used.

2. via URL:

   Create an image with the URL in following format:
   http://[your_domain]{/your_drupal_directory}/[path_to_public_files]/textimage/[style_name]/[Text_0]/[Text_1]/.../[Text_n].[extension]

   In a standard installation, [path_to_public_files] = 'sites/default/files'.

   Text_0...n - each string will be consumed by a textimage_text effect in the
   sequence specified within the image style.

   Note: This method can only be used by users with the 'generate textimage url
   derivatives' permission. This is to prevent Anonymous users from creating
   random images. If you need dynamically created Textimages, it is strongly
   advised you use one of the methods detailed below.

3. Programmers/themers - via theming:

   You can use the 'textimage_formatter' theme to render a Textimage via
   a render array, like e.g.:

    theme(
      'textimage_formatter',
      array(
        'uri' => 'public://textimage/myimagestyle/mytextimage.png,
        'width' => 50,
        'height' => 100,
        'alt'    => 'Alternate text',
        'title'  => 'Image title',
        'attributes' => array(),
        'image_container_attributes' => array(),
        'anchor_url' => NULL,
      ),
    ));

   This theme allows also to specify wrapping the <img> tag in a container
   <div> tag, and/or wrapping the entire output in an anchor tag.

    Variables:
// @todo drop    - textimage - A fully processed Textimage object. If this variable is set,
      the theme function will use this object to render the image, and the
      variables style_name, effects, text, format, caching, node,
      source_image_file, target_uri will be ineffective.
// @todo drop    - style_name - the image style name. If specified, it will override any
      value passed in the 'effects' variable.
// @todo drop    - effects - an array of image style effects. Given a $style image style
      array, corresponds to the $style['effects'] key.
// @todo drop    - text - an array of text strings, with unresolved tokens; each string
      of the array will be consumed by a textimage_text effect in the sequence
      specified within the image style.
// @todo drop    - caching - if set to TRUE, the image will be cached for future accesses;
      otherwise, the image will be stored in textimage_store and deleted on
      cron run.
// @todo drop    - source_image_file - a file entity. It is used to identify the source
      image when the image derivative is created by Textimage and for resolving
      the tokens in the text effects.
// @todo drop    - target_uri - allows to specify the URI where the textimage file should be
      stored. If specified, the automatic URI generation performed by Textimage
      is bypassed and caching disabled.
    - alt - the image alternate text. This text will be used by screen readers,
      search engines, or when the image cannot be loaded.
    - title - the text to be displayed when hovering the image on the browser.
    - attributes - associative array of attributes to be placed in the <img>
      tag.
    - image_container_attributes - if specified, the <img> tag will be wrapped
      in a <div> container, whose attributes will be set to the array passed
      here.
    - anchor_url - if specified, the entire output will be wrapped in a <a>
      anchor, whose 'href' attribute will be set to the value passed here.

4. Programmers - using the API:

    Programmers can get a Textimage object from the Textimage factory, and
    use the relevant methods to process an image. Example:

    $my_textimage = \Drupal::service('textimage.factory')->get();
    $my_textimage_url = $my_textimage
      ->styleByName('textimage_test')
      ->node($node)
      ->process($field_value)
      ->getUrl();

    Methods:
    - style($style) - an image style.
    - styleByName($style_name) - an image style name.
    - effects($effects_outline) - an array of image style effects.
      Given a $style['effects'] array, corresponds to the array of 'name' and 'data'
      keys of each element. You can use the helper function
      TextimageStyles::getStyleEffectsOutline($style_name) to get this array
      based on a style name. If not used, then $style is expected.
    - extension($extension) - the file format of the resulting image
      (png/gif/jpg/jpeg). If not set, defaults to 'png'.
    - setCaching($caching) - if set to TRUE, the image will be cached for
      future access; otherwise, the image will be stored in
      textimage_store/uncached and deleted on cron run. Defaults to TRUE.
// @todo revise
//    - node($node) - a node entity. It is used for resolving the tokens
//      in the text effects.
//    - sourceImageFile($source_image_file) - a file entity. It is used for resolving
//      the tokens in the text effects.
    - setTargetUri($target_uri) - specifies the URI where the textimage file
      should be stored. Allows to bypass the automatic URI generation performed
      by Textimage. NOTE: It disables caching, as, given an URI, there is no
      control on the actual text that gets into the image.
    - process($text) - retrieves or builds a textimage file, using an array of text strings, with unresolved tokens; each string
      of the array will be consumed by a textimage_text effect in the sequence
      specified within the image style.
    - load
    - id
    - getText
    - getUri
    - getUrl


-------------------------------------------------------------------------------


Using Textimage field formatters with tokens
--------------------------------------------

There are specific pre-conditions for text tokens to be resolved into full
text. Some tokens are 'general' (e.g. current date and time, site name, etc.)
but others require the 'context' that has to be accessed to retrieve
information, like a user, or a node, etc. When the context is missing, the
token can not be resolved.

Textimage provides context for 'user', 'node' and 'file' tokens through
its field display formatters. Textimage provides built-in field formatters
for the following field types: 'image', 'text', 'text_with_summary',
'text_long'.

If you select a Textimage formatter for a 'text' field, Textimage will use
the text entered in the field to produce an image with it. In the field text
you can enter the tokens directly, or [textimage:default] in which case
Textimage will just fetch and use the default text entered in the image effect
UI. If your image style is built with multiple 'Textimage text' effects, the
text field needs to be multi-value as well. Each text field value will be used
by one 'Textimage text' effect in the same sequence.
Tokens will be resolved against the 'node' (current content) and 'user' token
types.

If you select a Textimage formatter for an 'image' field, Textimage will use
the text entered in the default text in the image effect UI to produce the
image. In this case, tokens will be resolved against 'node', 'user' and 'file'
token types, where the 'file' is the original image uploaded in the content.

Programmers can develop additional field display formatters, using the lower
level APIs to pass 'node' or 'file' objects to be resolved. 'User' is always
the current user within the scope of the Textimage image building process.


-------------------------------------------------------------------------------


Textimage tokens
----------------

Textimage provides two tokens that can be used to retrieve the location where
a Textimage image has been stored:

A token to retrieve the URL of a Textimage image

[textimage:url:field{:display}{:sequence}]

and the URI equivalent

[textimage:uri:field{:display}{:sequence}]

where:
- 'field' is the machine name of the field for which the Textimage is
  generated (e.g. 'body', or 'field_my_field');
- 'display' is an optional indication of the display view mode (e.g. 'default',
  'full', 'teaser', etc.); 'default' is used if not specified;
- 'sequence' is an optional indication of the URL/URI to return if Textimage
  produces more images for the same field (like e.g. in a multi-value Image
  field); if not specified, a comma-delimited string of all the URLs/URIs
  generated will be returned.

Textimage tokens can be used with the Metatag module to specify e.g. URL
meta tags.


-------------------------------------------------------------------------------


Wishlist
--------
- textimage_text effect - if elements with different opacity overlap (e.g.
  in case of shadow/outline or if background color is opaque itself), then
  we get a combined color effect. One may want to refer each element's
  opacity to the original image instead.
- an ImageMagick toolkit implementation.
