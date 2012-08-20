
Textimage adds text to image functionality using GD2 and Freetype, enabling
users to create crisp images on the fly for use as theme objects, headings or
limitless other possibilities.

This module requires clean URLs be enabled. Without clean URL support Textimage
is unable to cache images, which can cause serious performance problems.


Textimage was originally written by Fabiano Sant'Ana (wundo).
- http://chuva-inc.com

Co-maintained by Stuart Clark (Deciphered).
- http://stuar.tc/lark


Features
------------

* Support for TrueType fonts and OpenType fonts.
* Rotate your text at any angle.
* Configurable opacity in text color.
* Backgrounds:
  * Define a color or simply have a transparent background.
  * Use a pre-made image to integrate directly with your theme.
  * Use another Textimage preset to achieve a multi-layered image (see image above).
* CCK and Views formatter integration.
* Support for non-alphanumeric characters.


Requirements
------------

* GD2
* FreeType


Install instructions
------------

1. Make sure your server supports the needed PHP extensions, if you're in a
   shared environment you may need help from your host.
   
2. Upload fonts files you want to use for Textimages to the 'fonts' directory
   inside textimage directory. If you want you can change this directory to 
   anywhere accessible by Drupal


Usage
------------

1. via theme_textimage_image():

   Use the theme_textimage_image() function at the theme/module level with the
   following format:

   theme('textimage_image', array(
    'preset' => 'Preset', 
    'text'   => 'Text', 
    'additional_text' => array('Additional', 'Text'), 
    'format'    => 'png', 
    'file_path' => 'public://myimages/sub_folder/image-filename' // Don't include the file extension.
   ));

2. via CCK/Views formatter:

   Select a Textimage preset in a text field display options.


3. via URL:

   Create an image with the URL in following format:
   /[files directory]/textimage/[Preset](/Additional/Text)/[Text].[extension]

   Note: This method can only be used by users with the 'create textimages'
   permission. This is to prevent Anonymous users from creating random images.

   If you need dynamically created Textimages, it is strongly advised you use
   one of the methods detailed above.


Recommended
------------

* Vertical Tabs - http://drupal.org/project/vertical_tabs

  When the Vertical Tabs module is enabled you will receive a modified user
  interface when creating and editing Textimage presets.


Updating
------------

* Always run update.php on your Drupal site after updating Textimage.
* Note: Due to certain changes in the Textimage module, some of your presets may
  require alterations after updating from 6.x-1.x to 6.x-2.x.

