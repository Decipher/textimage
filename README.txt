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
- mondrake https://www.drupal.org/u/mondrake

Ported to Drupal 8 by:
- mondrake https://www.drupal.org/u/mondrake


-------------------------------------------------------------------------------


Drupal 8 version
================

Notes:
1) the port to Drupal 8 is in development. PLEASE EXPECT CHANGES TO API AND
CONFIGURATION THAT MAY LEAD TO THE NEED TO UNINISTALL/REINSTALL THE MODULE.
There is no support yet to updates between different builds of the development
branch.
2) As of alpha2, Textimage will require the Image Effects module to be
installed and configured before installation.
3) In alpha3, the 'Textimage Text' image effect will most likely be dropped
and replaced by an equivalent effect in the Image Effects module - THIS WILL
REQUIRE TO UNINISTALL/REINSTALL THE MODULE, AND IMAGE STYLES USING THE
TEXTIMAGE TEXT EFFECT WILL BREAK.

There is a number of core issues that need to be addressed before a stable
release:
==========================================================================
Blockers:
- #2546212 - Entity view/form mode formatter/widget settings have no translation UI
Nice to have:
- #1826362 - ImageEffects of the same image style should be able to pass variables between them
- #2359443 - Allow creating image derivatives from an Image object

The following @todo items need to be addressed in the module:
==========================================================================
- add original image option for image field formatter

@todo related to Drupal 8.1:
==========================================================================
- align to #2571521 Make the logger available on the controllerBase,
  remove logger from constructor of download controller ?
- align to #1494670 References to CSS, JS, and similar files should be
  root-relative URLs: avoids mixed content warnings & fewer bytes to send


-------------------------------------------------------------------------------


Quick start instructions
------------------------
- Check requirements (below) and install / configure the modules needed.
- Install and enable the module.
- Check the Configuration page (Manage > Configuration > Media > Textimage)
  and setup.
- Ensure at least one font file is available.
- Create an image style (Manage > Configuration > Media > Image Styles)
  and use Textimage effects in combination with other effects as needed.
- Change a field of a content type (Manage > Structure > Content Types >
  {your type} > Manage Display) to be represented by a Textimage:
    - select 'Textimage' in the format dropdown (applicable to Text and Image
      fields)
    - click on the gear icon
    - select the image style you created earlier from the dropdown displayed
- Your field content is now presented as a (Text)image!


-------------------------------------------------------------------------------


Features
--------
* Provides two image effects for use in Drupal's Image system:
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


Requirements
------------
- Drupal 8
- GD2 and FreeType libraries
- Image Effects module

Recommended modules, for a feature rich set:
- Token


Installation instructions (long version)
----------------------------------------
- Install required modules.
- Consider recommended modules:
  - Token - this module allows to list available tokens when entering the
    default text for a 'Textimage text' effect.
- Install and enable Textimage.
- Check Textimage configuration page (Manage > Configuration > Media >
  Textimage):
  - Default image file extension - Select the default extension of the image
    files produced by Textimage. This can be overridden by image style effects
    that specify a format conversion like e.g. Convert or Textimage
    Background. This setting does not affect image derivatives created by the
    Drupal core Image module.
  - URL generation - select whether to enable direct URL generation (see below)
    and the string to be used to separate text elements that need to be pushed
    to separate Textimage Text effects during generation.
  - Maintenance - select whether Textimage needs to log debug messages of its
    operations. The 'Cleanup Textimage' button allows to completely clean all
    the images and image metadata generated by Textimage. NOTE: this
    functionality also flushes all image derivatives generated by Drupal core
    Image module.


-------------------------------------------------------------------------------


Creating Textimage image styles
-------------------------------
- Textimage extends Drupal image system providing additional image effects.
- Just combine Textimage effects with any other image effect to deliver
  the result needed in a image style (Manage > Configuration > Media >
  Image Styles).
- Image styles are extended to collect Textimage options:
  - Image destination - allows to specify in which file system the derivative
    images (i.e. the final output) shall be stored. By default, this is
    the same destination as specified in 'Default download method' (Manage >
    Configuration > Media > File System) in configuration, but can be set to
    alternative file systems (e.g. private etc.). This option affects only
    derivative images generated by Textimage, not those generated by core's
    Image module.


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

   - Access 'Content type' administration features via Manage > Structure >
     Content Types.
   - Select the content type for which you want to manage a Textimage field
     (e.g. Article, Basic page, etc.).
   - If you need to create a new field, in the 'Manage Fields' tab, add a new
     field of type 'Text' (or 'Long text', or 'Long text and summary') or
     'Image'.
   - In the 'Manage Display' tab, select a 'Textimage' format for the field
     created above (or any existing one).
   - Click on the gear icon.
   - Select from the 'Image style' dropdown the image style you want to use to
     represent the content as a Textimage.
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
     or when the image cannot be loaded. Tokens can be used. When left blank,
     the 'alt' information provided in the field will be used.
   - Optionally, enter a value for the image 'title' attribute in the 'Title'
     textbox. The title is used as a tool tip when the user hovers the mouse
     over the image. Tokens can be used.  When left blank, the 'title'
     information provided in the field will be used.

2. via direct URL generation:

   Create an image with the URL in following format:
   http://[your_domain]{/your_drupal_directory}/[path_to_public_files]/textimage/[style_name]/[Text_0][text_separator][Text_1][text_separator]...[text_separator][Text_n].[extension]

   In a standard installation, [path_to_public_files] = 'sites/default/files'.

   [style_name] - the image style must have at least a Textimage Text effect,
   and must be set to output Textimage images in the 'Public files' through
   the 'Image destination' setting on the image style 'Textimage options'.

   [Text_0]...[Text_n] - each string will be consumed by a Textimage text
   effect in the sequence specified within the image style.

   [text_separator] - the string that is configured to be used as text
   separator between different text strings (see configuration above). By
   default, this is counfigured to be '---' (three dashes).

   Note: This method can only be used by users with the 'generate textimage url
   derivatives' permission. This is to prevent Anonymous users from creating
   random images. If you need dynamically created Textimages, it is strongly
   advised you use one of the methods detailed below.

3. via the Textimage API and the theme system:

    Programmers can get a Textimage object from the Textimage factory, and
    use the relevant API methods in a fluent interface to build an image. Then,
    the 'textimage_formatter' theme can be used to build a render array to
    display the image.

    Notes:
    1) Textimage provides by default a caching mechanism that will keep track
       of the Textimage files generated based on the input image style/effects
       and text to be overlaid. It's possible to opt-out from this caching via
       the setTemporary() or the setTargetUri() methods.
    2) The Textimage API throws exceptions in case of errors, so mind to
       include calls to the API in a try/catch block.

    Example:

    try {
      $textimage = \Drupal::service('textimage.factory')->get($bubbleable_metadata)
        ->setStyle(ImageStyle::load($style_name)
        ->process([$text_strings])
        ->buildImage();
      $variables['textimage_image'] = array(
        '#theme' => 'textimage_formatter',
        '#uri' => $textimage->getUri(),
        '#width' => $textimage->getWidth(),
        '#height' => $textimage->getHeight(),
        '#title' => t('textimage'),
        '#alt' => t('Textimage image.'),
      );
      $textimage->getBubbleableMetadata()->applyTo($variables['textimage_image']);
    }
    catch (TextimageException $e) {
      \Drupal::service('textimage.logger')->error("Failed to build a Textimage image.");
    }

    ---------------------------------------------------------------------------
    API methods that set input information to the API - can be called only
    BEFORE processing
    ---------------------------------------------------------------------------
    - setStyle(\Drupal\image\ImageStyleInterface $image_style) - an ImageStyle
      object, whose effects will be used to build the Textimage. This is the
      way to produce a Textimage from an image style stored in configuration.
    - setEffects(array $effects) - an array of image style effects. This allows
      to produce Textimage images programmatically from a dynamic set of
      effects, and should be used in alternative to ::setStyle.
    - setTargetExtension($extension) - the file format of the output image
      (png/gif/jpg/jpeg). If not called, Textimage will default to the value
      set in the Textimage settings, or any override specified by an image
      effect that implements a ::getDerivativeExtension method.
    - setGifTransparentColor($color) - an RGB hex string indicating the color
      to be used for setting transparency in a GIF image. If not called, and
      a GIF file is passed via ::setSourceImageFile, then the source image
      color set for transparent will be used.
    - setSourceImageFile(\Drupal\file\FileInterface\FileInterface $source_image_file, $width = NULL, $height = NULL)
      a File object representing an image file, with optional width and height.
      It can be used to set the background image on top of which text should
      be overlaid. It can be overridden by a Textimage Background effect
      setting a different image.
    - setTokenData(array $token_data) - It is used for resolving the tokens
      in the text effects. $token_data has the same structure as the $data
      parameter of core's \Drupal\Core\Utility\Token::replace().
    - setTemporary($is_temp) - if set to TRUE, the image will be stored in
      a temporary textimage_store/temp directory and deleted on cron run. This
      is useful to generate one-off images like e.g. previews. Generated images
      will not be cached.
    - setTargetUri($uri) - specifies the URI where the output image file
      should be stored. Generated images will not be cached.

    ---------------------------------------------------------------------------
    API methods to produce a Textimage
    ---------------------------------------------------------------------------
    - process($text) - processes the Textimage metadata, using an array of text
      strings, with unresolved tokens; each string of the array will be
      consumed by a Textimage Text effect in the sequence specified within the
      image style. If the Textimage caching is active, after execution of
      ::process the ::id method will return the Textimage ID, and ::getUri and
      ::getUrl respectively the URI and URL of the image file that will be
      generated once ::buildImage is called.
    - load($id) - loads from cache the Textimage metadata. This can be used to
      defer generation of the image to a separate request from the one where
      the Textimage metadata was processed, i.e. request A will call ::process,
      and request B will ::load the Textimage metadata and generate the image
      via ::buildImage.
    - buildImage() - builds a Textimage, using the processed metadata. Should
      be called after ::process() to generate an image within the same request,
      or after ::load() to generate an image in a deferred request.

    ---------------------------------------------------------------------------
    API methods to get information about a Textimage - can be called only
    AFTER processing
    ---------------------------------------------------------------------------
    - id() - Returns the ID of a cached Textimage.
    - getText() - Returns the text elements after processing, with tokens
      replaced.
    - getUri() - Returns the URI of the Textimage image file.
    - getUrl() - Returns the URL of the Textimage image file.
    - getHeight() - Returns the height of the Textimage image.
    - getWidth() - Returns the width of the Textimage image.
    - getBubbleableMetadata() - Returns the bubbleable metadata that was
      collected during execution of ::process.

    ---------------------------------------------------------------------------
    'textimage_formatter' theme variables
    ---------------------------------------------------------------------------
    - 'item' - (optional) the entity for which the Textimage is being produced.
    - 'uri' - the URI of the Textimage.
    - 'width' - (optional) the width of the Textimage, in pixels.
    - 'height' - (optional) the height of the Textimage, in pixels.
    - 'alt' - (optional) the image alternate text. This text will be used by
      screen readers, search engines, or when the image cannot be loaded.
    - 'title' - (optional) the text to be displayed when hovering the image on
      the browser.
    - 'attributes' - (optional) associative array of attributes to be placed in
      the <img> tag.
    - 'image_container_attributes' - (optional) if specified, the <img> tag
      will be wrapped in a <div> container, whose attributes will be set to the
      array passed here.
    - anchor_url - (optional) if specified, the entire output will be wrapped
      in a <a> anchor, whose 'href' attribute will be set to the value passed
      here.


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
UI. Tokens will be resolved against the 'node' (current content) and 'user'
token types.

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


-------------------------------------------------------------------------------


Wishlist
--------
- textimage_text effect - if elements with different opacity overlap (e.g.
  in case of shadow/outline or if background color is opaque itself), then
  we get a combined color effect. One may want to refer each element's
  opacity to the original image instead.
