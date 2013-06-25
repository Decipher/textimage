
Textimage adds text to image functionality using GD2 and Freetype, enabling
users to create crisp images on the fly for use as theme objects, headings or
limitless other possibilities.

This module requires clean URLs be enabled. Without clean URL support Textimage
is unable to cache images, which can cause serious performance problems.


Textimage was originally written by Fabiano Sant'Ana (wundo).
- http://chuva-inc.com

Co-maintained by:
- Stuart Clark (Deciphered) http://stuar.tc/lark
- Mondrake http://drupal.org/user/1307444

-----------------
Textimage 7.x-3.x
-----------------

Note: this release *breaks backward compatibility*, since it uses different
API and DB structures. If you want to test or review, please mind about
installing the module in an environment where there is no 7.x-2.x installed.


DELTA FEATURES:
---------------

Textimage 3 is a major rewrite of Textimage.

- Leverage Image and Tokens features that are embedded in core Drupal 7.

- Drop the preset concept and db schema and use instead the Image concepts:
styles and effects. This finally allows Textimage to use any image effect
to build the final image - leveraging a wide library of image effects
provided by core and other contrib modules. Also, it allows core Image module
to use Textimage effects.

- Move all primitive image functions to toolkit specific includes, allowing to
potentially use alternative toolkits (other than GD).

- Implement Drupal 7 field formatters for Text and Image fields.

- Implement a derivative delivery mechanism specific to Textimage - enabling
usage of scheme wrappers (public, private, ...) to indicate storage
destination of image files, and providing a framework to leverage tokens.
(90% done)

- Enable Tokens substitution at runtime in the text.

- Implement a direct text to image theme (i.e. enable producing a textimage
with no predefined style!).

- Enhance the text overlay effects

- Integrate with Imagecache Actions module to leverage its effects and
functions (dependency).

- Optional @font-your-face module integration for font management.

- Optional Media module integration for background image management.

- Optional jQuery Colorpicker module integration for color selection in
effects' admin forms.

- Document code(90% done)

- Documentation for users(0% done)

NICE TO HAVE:
-------------
- a way to specify a http link for the textimage in fields and/or themes,
  with tokens
- textimage_text effect - if elements with different opacity overlap (e.g.
  in case of shadow/outline or if background color is opaque itself), then
  we get a combined color effect. One may want to refer each element's
  opacity to the original image instead.
