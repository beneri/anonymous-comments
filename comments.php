<?php
// mysql.php contains the constants DB_HOST, DB_USER, etc
require_once("mysql.php");
require_once("comment_moderators.php");

// Adds the comment to the database 
function insertComment( $entry_id, $name, $trip, $content, $ip, $deleted ) {
    $long_ip = ip2long($ip);
    $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    $stmt = $link->prepare("INSERT INTO blog_comments 
      (bid,author_name,author_tripcode,content,author_ip,deleted) VALUES 
      (?, ?, ?, ?, ?, ?)"); 
    $stmt->bind_param("isssii", $entry_id, $name, $trip, $content, $long_ip, $deleted);
    $stmt->execute();
    $error = array( $stmt->errno, $stmt->error );
    $stmt->close();

    return $error;
    
}

// Check for flooding attempts
function flooding( $entry_id, $ip ) {
    // Comments
    $long_ip = ip2long($ip);
    $times = array();

    $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    $stmt = $link->prepare(
      "SELECT UNIX_TIMESTAMP(`posted_on`) AS `posted_on`
       FROM `blog_comments` 
       WHERE (bid = ? and author_ip = ? and `posted_on` > DATE_SUB(NOW(), INTERVAL 10 MINUTE) )
       ORDER BY cid DESC
       LIMIT 10");
    $stmt->bind_param("ii", $entry_id, $long_ip);
    $stmt->execute();
    $stmt->bind_result($posted_on);
    while($stmt->fetch()){
      array_push($times, $posted_on);
    }
    $stmt->close();

    $n = sizeof($times);
    
    if( $n )  {
      // Might be double post
      if( time() -  $times[0]  < 2 ) {
        return array("status" => 0, "error" => "Flooding detected, may be double post");
      } else if( $n > 1 ) {
        $left = (pow(2, $n) +  $times[$n-1]) - time();
        if( $left > 0 ) {
          return array("status" => 0, "error" => "Flooding detected, $left seconds left");
        }
      }
    }

    return array("status" => 1);
}

// Returns the list of moderators for a specific entry
function getModerator( $entry_id ) {

  $link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
  $stmt = $link->prepare(
    "SELECT `moderator`
     FROM `blog_comments_moderator` 
     WHERE ( bid = ? ) 
     ");
  $stmt->bind_param("i", $entry_id);
  $stmt->execute();
  $stmt->bind_result($moderator);
  $mods = array();
  while($stmt->fetch()){
    array_push($mods, $moderator);
  }
  $stmt->close();

  return $mods;
}


// Formats and validates comments before adding to database
function addComment( $entry_id, $name, $content, $ip ) {
  // Format the necessary variables
  if( empty($content) ) {
    return array("status" => 0, "error" => "Content cannot be empty");
  }

  if( !empty($name) ) {
    $firstHashtag = strpos($name, '#');
    if( $firstHashtag > -1 ) {
      $salt = "tripsalt";
      $trip = hash('ripemd160', $salt . substr($name, $firstHashtag+1));
      $name = substr($name, 0, $firstHashtag);
    } else {
      $trip = NULL;
    }
  } else {
    $name = "Anonymous";
    $trip = NULL;
  }

  // Flooding
  $res = flooding($entry_id, $ip);  
  if( ! $res['status'] ) {
    return $res;
  }
    
  
  // Moderators
  // If deleted is set the comment requires manual approval 
  $mods = getModerator($entry_id);
  $deleted = 0;
  $meanMods = array();

  foreach( $mods as $moderator ) {
    $m = new $moderator();
    $res = $m->analyse( $name, $content );
    if( ! $res['status'] ) {
      $deleted = 1;
      array_push( $meanMods, $moderator );
    }
  }

  $insert_error = insertComment( $entry_id, $name, $trip, $content, $ip, $deleted);
  // Check for errors
  if( $insert_error[0] ) {
    switch ($insert_error[0]) {
      case 1452:
        return array("status" => 0, "error" => "Cannot comment on non-existing post.");
        break;
      default:
        // We dont want to give out too much information in this case
        print_r($insert_error);
        return array("status" => 0, "error" => "Unknown error.");
    }
  }

  if( $deleted ) {
    return array("status" => 0, "warning" => "The moderators (".implode(', ', $meanMods).") think there might be 
      a problem with this comment. The comment will need approval from an administrator."); 
  }

  return array("status" => 1);
}



// Handle new comments
function checkNewComment() { 
  $comment_status = NULL;

  if( isset($_POST['submit']) ) {
    $result = addComment(
      $_POST['entry_id'], 
      $_POST['author_name'], 
      $_POST['content'],
      $_SERVER['REMOTE_ADDR']
    );

    if( $result['status'] ) {
      $comment_status = '<div class="alert alert-success">Post successful</div>';
    } else {
      if( isset( $result['error'] ) ) {
        $comment_status = '<div class="alert alert-danger"><b>Failed</b> ' . $result['error'] . '</div>';
      } else {
        $comment_status = '<div class="alert alert-warning"><b>Warning</b> ' . $result['warning'] . '</div>';
      }
    }
  }

  return $comment_status;
}

// Prints a comment using the html templates.
function printComments( $entry_id, $comments, $comment_status ) {

  $formTemplate = file_get_contents('comment_form_template.html');
  $replaces = array(
    "{{entry_id}}"=>$entry_id,
    "{{comment_status}}"=>$comment_status
  );
  echo strtr( $formTemplate, $replaces);

  echo '<h2 id="comments">Comments</h2>';
  if( $comments ) {
    $commentTemplate = file_get_contents('comment_template.html');
    foreach( $comments as $comment) {
      //print_r($comment);
      $author = $comment['author_name'];
      $tripcode = substr($comment['author_trip'], 0, 12);
      $date = date($comment['posted_on']);

      $replaces = array(
        "{{cid}}"=>$comment['cid'],
        "{{author}}"=>htmlspecialchars($author, ENT_QUOTES, 'utf-8'),
        "{{tripcode}}"=>htmlspecialchars($tripcode, ENT_QUOTES, 'utf-8'),
        "{{fulltripcode}}"=>htmlspecialchars($comment['author_trip'], ENT_QUOTES, 'utf-8'),
        "{{date}}"=>htmlspecialchars($date, ENT_QUOTES, 'utf-8'),
        "{{content}}"=>htmlspecialchars($comment['content'], ENT_QUOTES, 'utf-8')
      );
      echo strtr( $commentTemplate, $replaces);
    }
  } else {
    echo "No comments yet";
  }

}
?>
