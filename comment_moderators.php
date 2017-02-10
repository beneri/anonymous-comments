<?php

# Example of how a moderator could look
class Linguist {
  function stats($text) {
    $text_length = strlen($text);
    $word_count =  str_word_count($text);
    $word_length = $text_length / $word_count;

    # https://www.quora.com/What-is-the-average-number-of-letters-for-an-English-word
    $english_average = 4.79; 

    $length_miss = abs($word_length - $english_average);

    // Remove non-ascii
    $ascii = preg_replace('/[^\00-\255]+/u', '', $text);
    $relative_miss = 1-strlen($ascii)/$text_length;

    return array("length_miss" => $length_miss, "relative_miss" => $relative_miss);
  }

  function analyse($name, $content) {
    # Names doesn't have to follow word length
    //$stats = $this->stats($name);
    $stats = $this->stats($content);

    $length_threshold = 3;
    $relative_threshold = 0.1;
    if( $stats['length_miss'] > $length_threshold ) {
      return array("status" => 0, "error" => "Wrong word length");
    } else if ( $stats['relative_miss'] > $relative_threshold ) {
      return array("status" => 0, "error" => "Too many non-ascii character");
    } else {
      return array("status" => 1);
    } 

  }
}


class Spam {
  function isSpam($text) {
    $list = explode( "\n",  file_get_contents("phrases.txt") );
    foreach( $list as $phrase ) {
      if (stripos(strtolower($text), $phrase) !== false) {
        return 1;
      }
    } 

    return 0;
  }

  function analyse($name, $content) {
    if( $this->isSpam($name) || $this->isSpam($content) ) {
      return array("status" => 0, "error" => "Seems like spam");
    } 

    return array("status" => 1);

  }
}

class Security {
  function isAttack($text) {
    $hasTags = $text != strip_tags($text);
    $badWords = array("script", "alert", "drop", "table", "&#x");

    $hasBadWord = 0;
    foreach( $badWords as $word ) {
      if (stripos(strtolower($text), $word) !== false) {
        $hasBadWord = 1;
      }
    } 

    if( $hasTags && $hasBadWord ) {
      return 1;
    }

    return 0;
  }

  function analyse($name, $content) {
    if( $this->isAttack($name) || $this->isAttack($content) ) {
      return array("status" => 0, "error" => "Seems like an attack");
    } 

    return array("status" => 1);

  }
}
?>
