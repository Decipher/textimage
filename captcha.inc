<?php
// $Id$
/**
 * @file
 * Provides textimage captcha functions
 */
 
/**
* Captcha settings form.
*/
function textimage_captcha_settings_form() {

  $fonts_path = variable_get("textimage_captcha_fonts_path", "");
  $images_path = variable_get("textimage_captcha_images_path", "");

  //check for valid font path
  if ($fonts_path != '' && !file_check_directory($fonts_path)) {
    drupal_set_message(t('The current font path is invalid. The default font will be used.'), 'error');
  }
  //check for valid image path
  if ($images_path != '' && !file_check_directory($images_path)) {
    drupal_set_message(t('The current images path is invalid. No images will be used.'), 'error');
  }

  //Fonts settings
  $form['fonts'] = array(
    '#type' => 'fieldset', '#title' => t('Fonts settings'),
    '#collapsible' => TRUE, '#collapsed' => FALSE
  );
              
  $form['fonts']['textimage_captcha_fonts_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Captcha TrueType Fonts Path'),
    '#default_value' =>  $fonts_path,
    '#size' => 30,
    '#maxlength' => 255,
    '#description' => t('Location of the directory where the Truetype (.ttf) fonts are stored. If you do not provide any fonts, the module will use the default font for text. Relative paths will be resolved relative to the Drupal installation directory.'),
  );
  
  $form['fonts']['textimage_captcha_font_size'] = array(
    '#type' => 'textfield',
    '#title' => t('Font Size'),
    '#default_value' =>  variable_get('textimage_captcha_font_size',24),
    '#size' => 5,
    '#maxlength' => 2,
    '#description' => t('Font size of Captcha text (in pixels).'),
    '#validate' => array("_textimage_number_validate" => array("textimage_font_size")),
  );
  
  $form['fonts']['textimage_captcha_char_spacing_max'] = array(
    '#type' => 'textfield',
    '#title' => t('Character Spacing'),
    '#default_value' =>  variable_get('textimage_captcha_char_spacing_max',10),
    '#size' => 5,
    '#maxlength' => 4,
    '#description' => t('Sets the kerning between letters in Captcha. Higher numbers indicate more spacing.'),
    '#validate' => array("_textimage_number_validate" => array("textimage_char_spacing_max")),
  );
  
  $form['fonts']['textimage_captcha_char_jiggle_amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Character Jiggle'),
    '#default_value' =>  variable_get('textimage_captcha_char_jiggle_amount',5),
    '#size' => 5,
    '#maxlength' => 2,
    '#description' => t('Sets the amount of up and down movement in the Captcha letters. Higher numbers indicate more jiggling.'),
    '#validate' => array("_textimage_number_validate" => array("textimage_char_jiggle_amount")),
  );
  
  $form['fonts']['textimage_captcha_char_rotate_amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Character Rotation'),
    '#default_value' =>  variable_get('textimage_captcha_char_rotate_amount',5),
    '#size' => 5,
    '#maxlength' => 2,
    '#description' => t('Sets the amount of rotation in the Captcha letters (in degrees, only works with non-default fonts).'),
    '#validate' => array("_textimage_number_validate" => array("textimage_char_rotate_amount")),
  );
  
  $form['fonts']['textimage_captcha_char_size_amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Character Size Adjustment'),
    '#default_value' =>  variable_get('textimage_captcha_char_size_amount',2),
    '#size' => 5,
    '#maxlength' => 2,
    '#description' => t('Sets the amount of variation in size between the different letters in the Captcha (in pixels).'),
    '#validate' => array("_textimage_number_validate" => array("textimage_char_size_amount")),
  );
              
  $form['fonts']['textimage_captcha_use_only_upper'] = array(
    '#type' => 'checkbox',
    '#title' => t('Use only Uppercase'),
    '#default_value' => variable_get('textimage_captcha_use_only_upper',0)
  );

  //Image settings
  $form['images'] = array(
    '#type' => 'fieldset', '#title' => t('Image settings'),
    '#collapsible' => TRUE, '#collapsed' => FALSE
  );
  $form['images']['textimage_captcha_images_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Captcha Background Images Path'),
    '#default_value' =>  $images_path,
    '#size' => 30,
    '#maxlength' => 255,
    '#description' => t('Location of the directory where the background images are stored. If you do not provide a directory, solid colors will be used. Relative paths will be resolved relative to the Drupal installation directory.'),
  );
                                
  $form['images']['textimage_captcha_image_noise'] = array(
    '#type' => 'textfield',
    '#title' => t('Image Noise (pixels)'),
    '#default_value' =>  variable_get('textimage_captcha_image_noise',4),
    '#size' => 5,
    '#maxlength' => 4,
    '#description' => t('Sets the amount of noise (random pixels) in the Captcha image. Higher numbers indicate more noise.'),
    '#validate' => array("_textimage_number_validate" => array("textimage_image_noise")),
  );
                                
  $form['images']['textimage_captcha_image_lines'] = array(
    '#type' => 'textfield',
    '#title' => t('Image Noise (lines)'),
    '#default_value' =>  variable_get('textimage_captcha_image_lines',4),
    '#size' => 5,
    '#maxlength' => 4,
    '#description' => t('Sets the amount of noise (random lines) in the Captcha image. Higher numbers indicate more noise.'),
    '#validate' => array("_textimage_number_validate" => array("textimage_image_lines")),
  );
                                
  $form['images']['textimage_captcha_image_margin'] = array(
    '#type' => 'textfield',
    '#title' => t('Image Margin'),
    '#default_value' =>  variable_get('textimage_captcha_image_margin',10),
    '#size' => 5,
    '#maxlength' => 4,
    '#description' => t('Set a distance between the Captcha letters and the edges of the image.'),
    '#validate' => array("_textimage_number_validate" => array("textimage_image_margin")),
  );


  $form['info'] = array(
    '#type' => 'fieldset', '#title' => t('Image and font information'),
    '#collapsible' => TRUE, '#collapsed' => FALSE
  );
  if (isset($fonts_path)) {
    $imagefontinfo .= t('Number of captcha fonts found: ').count(_textimage_font_list($fonts_path));
  }
  if (isset($images_path)) {
    $imagefontinfo .= '<br />'.t('Number of captcha background images found: ').count(_textimage_image_list($images_path));
  }

  $form['info']['captcha_info'] = array (
   '#type' => 'item',
   '#value' => $imagefontinfo,
  );

  return system_settings_form($form);
}

function textimage_captcha_settings_form_validate($form_id, $form_values) {
  textimage_settings_form_validate($form_id, $form_values);
}

function textimage_captchachallenge(&$form) {
  $form['captcha_response'] = array (
    '#type' => 'textfield',
    '#title' => t('Captcha Validation'),
    '#default_value' => '',
    '#required' => TRUE,
    '#validate' => array('_captcha_validate' => array()),
    '#description' => t('Please type in the letters/numbers that are shown in the image above.'),
    '#prefix' => '<div class="textimage-challenge"><img src="' . url('_textimage/image/'.time()) . '" '.$imageSize[3].'  alt="'.t('Captcha Image: you will need to recognize the text in it.').'"/></div>',
  );
 
  return $form;
}

function textimage_captchavalidate(&$captcha_word, &$correct) {

  $captcha_word = drupal_strtolower($captcha_word);
  if ($captcha_word == $_SESSION['captcha']) {
    $correct = true;
  }
  else {
    $correct = false;
    form_set_error('captcha_response', t('The image verification code you entered is incorrect.'));
  }

}

/**
* Prints an image containing a textimage code.
*/
function _textimage_captcha_image() {
  //if we don't have GD2 functions, we can't generate the image
  if (!function_exists('imagecreatetruecolor')) return;

  // Set headers
  header('Expires: Mon, 01 Jan 1997 05:00:00 GMT');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');

  header('Content-type: image/png');

  $string = _textimage_captcha_code();

  // Get truetype font list
  $fonts = _textimage_font_list(variable_get("textimage_captcha_fonts_path", ""));
  // Get the background images list
  $images = _textimage_image_list(variable_get("textimage_captcha_images_path", ""));

  // Randomization amounts:
  $charSpacingMax   = variable_get('textimage_captcha_char_spacing_max',10);   // Letter spacing max (pixels)
  $charSpacingMin   = max($charSpacingMax*.5,0);                     // Letter spacing minimum (pixels)
  $charJiggleAmount = variable_get('textimage_captcha_char_jiggle_amount',5);  // Up and down randomization (pixels)
  $charRotateAmount = variable_get('textimage_captcha_char_rotate_amount',5);  // Character rotation amount (degrees)
  $charSizeAmount   = variable_get('textimage_captcha_char_size_amount',2);    // Character size amount (pixels)
  
  // Static amounts:
  $charInitialSize   = variable_get('textimage_captcha_font_size',24);      // Initial Font 
  $imageNoise        = variable_get('textimage_captcha_image_noise',4);    // Amount of noise added to image
  $imageLines        = variable_get('textimage_captcha_image_lines',4);    // Amount of noise added to image
  $imageMargin       = variable_get('textimage_captcha_image_margin',10);    // Margin around image (pixels)

  $imageWidth = 0;

  // write text using a truetype font
  if (function_exists(imagettftext) && count($fonts) > 0) {
    
    // Initialize variables for the loop
    $characterDetails = array(); // contains the final info about each character
          
    // Build a list of character settings for the captcha string
    for ($i=0;$i<drupal_strlen($string);$i++) {
      // Select our character
      $char = drupal_substr($string,$i,1);

      // Define angle, size, and font of the character
      $charAngle = rand(-$charRotateAmount,$charRotateAmount);
      $charSize  = $charInitialSize + rand(-$charSizeAmount,$charSizeAmount);
      $font = variable_get("textimage_captcha_fonts_path", "") . '/' . $fonts[rand(0,count($fonts)-1)];
      
      // Find height and width of the character
      $bbox = imagettfbbox($charSize, $charAngle, $font, $char);
      $charWidth = max($bbox[0],$bbox[2],$bbox[4],$bbox[6]) - min($bbox[0],$bbox[2],$bbox[4],$bbox[6]);
      $charHeight = abs(max($bbox[1],$bbox[3])) - min($bbox[5],$bbox[7]);
      
      // Define the x and y position
      $charSpacing = rand($charSpacingMin, $charSpacingMax);
      $x = $imageWidth + $charSpacing;
      $y = ($charHeight + rand(0,$charJiggleAmount));

      $characterDetails[] = array (
        "size"  => $charSize,
        "angle" => $charAngle,
        "x"     => $x,
        "y"     => $y,
        "font"  => $font,
        "char"  => $char
      );
      
      // Increment the image size
      $imageWidth  = $x + $charWidth;
      $imageHeight = max($imageHeight,$y+$charJiggleAmount);
    }
    
    // Create the image based off the string length and margin
    if (count($images) > 0) {
      // We're going to be using an image, and need a tranparent background to start with
      $im = _textimage_create_transparent_image($imageWidth+2*$imageMargin, $imageHeight+2*$imageMargin);
      $noisecolor = imagecolorallocatealpha($im, 0, 0, 0, 127);
    } else {
      // Just make a plain-jane color brackground
      $im = imagecreatetruecolor($imageWidth+2*$imageMargin, $imageHeight+2*$imageMargin);
      $background = imagecolorallocate($im, rand(180, 250), rand(180, 250), rand(180, 250));
      $noisecolor = $background;
      imagefill($im, 0, 0, $background);
    }
    
    // Specify colors to be used in the image
    $foreground = imagecolorallocate($im, rand(0, 80), rand(0, 80), rand(0, 80));
    
    foreach($characterDetails as $char) {
      // draw character
      imagettftext($im,$char['size'],$char['angle'],$char['x']+$imageMargin,$char['y']+$imageMargin,$foreground,$char['font'],$char['char']);
    }

  }

  else {
    // write text using a built-in font
    $x = 0;
    $y = 0;
    $imageWidth = 60 + drupal_strlen($string)*$charSpacingMax*.35;
    $imageHeight = 30 + $charJiggleAmount;

    // Create the image
    if (count($images) > 0 && function_exists(imagecolorallocatealpha)) {
      // We're going to be using an image, and need a tranparent background to start with
      $im = _textimage_create_transparent_image($imageWidth, $imageHeight);
      $noisecolor = imagecolorallocatealpha($im, 0, 0, 0, 127);
    } else {
      // Just make a plain-jane color brackground
      $im = imagecreatetruecolor($imageWidth, $imageHeight);
      $background = imagecolorallocate($im, rand(180, 250), rand(180, 250), rand(180, 250));
      $noisecolor = $background;
      imagefill($im, 0, 0, $background);
    }
    
    // Add the text
    for ($i=0;$i<drupal_strlen($string);$i++) {
      imagestring($im,max(2,5-rand(0,$charSizeAmount)),$x,$y,drupal_substr($string,$i,1),$foreground);
      $x += rand(10+$charSpacingMin,10+$charSpacingMax);
      $y = $imageMargin + rand(0,$charJiggleAmount);
    }
    
    // Scale the image according to the user defined font size and add the margin
    $imageWidth  = imagesx($im) * ($charInitialSize * .08) + $imageMargin;
    $imageHeight = imagesy($im) * ($charInitialSize * .08) + $imageMargin;
   
    if (count($images) > 0) {
      // Prepare a larger image with a background image
      $im2 = _textimage_create_transparent_image($imageWidth, $imageHeight);
    } else {
      // Prepare a larger image with a solid color
      $im2 = imagecreatetruecolor($imageWidth, $imageHeight);
      imagefill($im2, 0, 0, $background);
    }
    
    $result = imagecopyresampled ($im2, $im, $imageMargin, $imageMargin, 0, 0, $imageWidth, $imageHeight, imagesx($im), imagesy($im));
    $im = $im2;
  }

  // strikethrough
  imageline($im, rand(0, 120), rand(0, 120), rand(0, 120), rand(0, 120), $foreground);

  // Add Noise
  for ($x=0; $x<$imageWidth; $x++) {
    for ($row=0; $row<$imageNoise;$row++) {
      $y = rand(0,$imageHeight);
      imagesetpixel($im, $x, $y, $noisecolor);
    }
  }
  // Add Lines and Ellipses
  for ($x=0; $x<$imageLines;$x++) {
    imageline($im, rand(0, $imageWidth), rand(0, $imageHeight), rand(0, $imageWidth), rand(0, $imageHeight), $noisecolor);
    imageellipse($im, rand(0, $imageWidth), rand(0, $imageHeight), rand(0, $imageWidth), rand(0, $imageHeight), $noisecolor);
  }
  
  // Fill image with a random background image if available
  if (count($images) > 0) {
    $image = $images[rand(0,count($images)-1)];
    _textimage_captcha_apply_background_image($im,$image);
  }
  
  //output to browser
  imagepng($im);
  imagedestroy($im);

}

/**
* Overlays an image to the supplied image resource
*/
function _textimage_captcha_apply_background_image (&$imageResource, $imageFile) {
  $backgroundResource = image_gd_open($imageFile,substr($imageFile,-3));
  // Copy the text onto the background
  $backX = imagesx($backgroundResource);
  $backY = imagesy($backgroundResource);
  $textX = imagesx($imageResource);
  $textY = imagesy($imageResource);
  
  $randomBackX = rand(0,$backX-$textX);
  $randomBackY = rand(0,$backY-$textY);
  
  // Place the text onto a random location of the background image
  imagecopyresampled($backgroundResource,$imageResource,$randomBackX,$randomBackY,0,0,$textX,$textY,$textX,$textY);
  // Crop the background image to the original image size
  imagecopyresampled($imageResource,$backgroundResource,0,0,$randomBackX,$randomBackY,$textX,$textY,$textX,$textY);
}

/**
* Returns a random string for use in a captcha
*/
function _textimage_captcha_code() {
  $consts='bcdgjxvmnprst';
  $vowels='aeiou';

  for ($x=0; $x < 6; $x++) {
    mt_srand ((double) microtime() * 1000000);
    $const[$x] = drupal_substr($consts,mt_rand(0,drupal_strlen($consts)-1),1);
    $vow[$x] = drupal_substr($vowels,mt_rand(0,drupal_strlen($vowels)-1),1);
  }

  $string = $const[0] . $vow[0] .$const[2] . $const[1] . $vow[1] . $const[3] . $vow[3] . $const[4];
  $string = drupal_substr($string,0,rand(4,6));

  //everytime we create a new code, we write it to session
  $_SESSION['captcha'] = drupal_strtolower($string);

  if(variable_get('textimage_captcha_use_only_upper',0)) {
    $string = drupal_strtoupper($string);
  }

  return $string;
}
