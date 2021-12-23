<?php

# Backend work sample distributed workers
# https://github.com/spqt/backend-work-sample-worker

require_once('setup.php');

if (!extension_loaded('curl')) {
  echo 'Error: PHP cURL extension is not loaded, please load it'."\n";
  exit(1);
}

function get_database_connection() {
  # database credentials
  $mysqli = new mysqli(
    DATABASE_HOST,
    DATABASE_USERNAME,
    DATABASE_PASSWORD,
    DATABASE_NAME
  );

  if ($mysqli->connect_errno) {
    echo 'Error: ' . $mysqli->connect_error."\n";
    exit(1);
  }
  return $mysqli;
}

$verbose = false;

$opts = getopt(
  'a:d:hl::p::r::v',
  array('add:', 'delete:', 'help', 'list::', 'process::', 'reset::', 'verbose')
);

if (!count($opts)) {
  $opts = array('p' => false);
}

foreach ($opts as $opt => $value) {
  switch ($opt) {
    case 'h':
    case 'help':
?>Usage: <?php echo basename(__FILE__); ?> <options>

Options:
-h
  Print this information.
-a="<protocol://...>", --add="<protocol://...>"
  Add site, id is required.
-d=<id>, --delete=<id>
  Delete site, id is required.
-l(=<id>), --list(=<id>)
  List sites, id is optional.
-p(=<id>), --process(=<id>)
  Process sites, id is optional.
  No id will process sites with the NEW status.
  The process action is the default if none is specified.
-r(=<id>), --reset(=<id>)
  Reset sites, id is optional.
-v, --verbose
  Output more details.
<?php
      exit();
    case 'v':
    case 'verbose':
      $verbose = true;
      break;
  }
}

foreach ($opts as $opt => $value) {
  switch ($opt) {
    case 'a':
    case 'add':
      if (filter_var($value, FILTER_VALIDATE_URL) === false || !strlen($value)) {
          echo 'Error: invalid URL'."\n";
          exit(1);
      }
      $mysqli = get_database_connection();
      $sites = $mysqli->query('SELECT id, status, url FROM sites');
      if (!$sites) {
        echo 'Error: '.mysqli_error($mysqli)."\n";
        $mysqli->close();
        exit(1);
      }
      while ($row = $sites->fetch_object()) {
        if ($row->url === $value) {
          echo 'Error: the URL is already in the database'."\n";
          $mysqli->close();
          exit(1);
        }
      }
      if (!$mysqli->query('
        INSERT INTO sites (
          url
        ) VALUES(
          "'.$mysqli->real_escape_string($value).'"
        )'
      )) {
        if (!$mysqli) {
          echo 'Error: '.mysqli_error($mysqli)."\n";
          $mysqli->close();
          exit(1);
        }
      }
      if ($verbose) {
        echo 'Added "'.$value.'"'."\n";
      }
      break;
    case 'd':
    case 'delete':
      $id = is_numeric($value) ? intval($value) : false;
      if ($id === false) {
        echo 'Error: id required'."\n";
      }
      $mysqli = get_database_connection();
      if (!$mysqli->query(
        'DELETE FROM
          sites
        WHERE
          id="'.$mysqli->real_escape_string($id).'"'
      )) {
        if (!$mysqli) {
          echo 'Error: '.mysqli_error($mysqli)."\n";
          $mysqli->close();
          exit(1);
        }
      }
      if ($verbose) {
        echo 'Deleted site #'.$id."\n";
      }
      break;
    case 'l':
    case 'list':
      $id = is_numeric($value) ? intval($value) : false;
      $mysqli = get_database_connection();
      $where = $value !== false ? ' WHERE id="'.$mysqli->real_escape_string($id).'"' : '';
      $sites = $mysqli->query('
        SELECT
          CONCAT("#", id) AS id,
          RPAD(status, 5, " ") as status,
          LPAD(httpcode, 3, "0") AS httpcode,
          CONCAT("\"", url, "\"") AS url
        FROM
          sites
          '.$where
      );
      if (!$sites) {
        echo 'Error: '.mysqli_error($mysqli)."\n";
        $mysqli->close();
        exit(1);
      }
      echo implode('  ', array('id', 'state', 'code', 'url'))."\n";
      while ($row = $sites->fetch_array(MYSQLI_ASSOC)) {
        echo implode('  ', $row)."\n";
      }
      break;
    default:
    case 'p':
    case 'process':
      $id = is_numeric($value) ? intval($value) : false;
      $mysqli = get_database_connection();
      $where = $value !== false ? 'id="'.$mysqli->real_escape_string($id).'"' : 'status="NEW"';
      $sites = $mysqli->query('SELECT id, status, url FROM sites WHERE '.$where);
      if (!$sites) {
        echo 'Error: '.mysqli_error($mysqli)."\n";
        $mysqli->close();
        exit(1);
      }
      if ($verbose) {
        echo $sites->num_rows.' sites to process'."\n";
      }
      # no sites - get out
      if (!$sites->num_rows) {
        $sites->free_result();
        $mysqli->close();
        break;
      }
      while ($row = $sites->fetch_object()) {
        if ($verbose) {
          echo '#'.$row->id.': processing '.$row->url."\n";
        }
        if (!$mysqli->query('
          UPDATE
            sites
          SET
            status="PROCESSING"
          WHERE
            id='.$mysqli->real_escape_string($row->id)
        )) {
          echo 'Error: '.mysqli_error($mysqli)."\n";
          $mysqli->close();
          exit(1);
        }
        $curl = curl_init($row->url);
        curl_setopt_array($curl, array(
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_HEADER         => true,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_ENCODING       => '',
          CURLOPT_USERAGENT      =>
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) '.
            'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.1.1 Safari/605.1.15', # who am i
          CURLOPT_AUTOREFERER    => true,
          CURLOPT_CONNECTTIMEOUT => 120,
          CURLOPT_TIMEOUT        => 120,
          CURLOPT_MAXREDIRS      => 10,
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_VERBOSE        => false
        ));

        # run curl
        $data = curl_exec($curl);
        if(curl_error($curl)) {
          echo '#'.$row->id.': error: '.trim(curl_error($curl))."\n";
          curl_close($curl);
          if ($mysqli->query('
            UPDATE
              sites
            SET
              status="ERROR"
            WHERE id='.$mysqli->real_escape_string($row->id)
          )) {
            continue;
          }
        }

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($verbose) {
          echo '#'.$row->id.': response code: '.$httpcode."\n";
        }
        if (!$mysqli->query('
          UPDATE
            sites
          SET
            httpcode="'.$mysqli->real_escape_string($httpcode).'",
            status="DONE" WHERE id='.$mysqli->real_escape_string($row->id)
        )) {
          echo 'Error: '.mysqli_error($mysqli)."\n";
          $mysqli->close();
          curl_close($curl);
          exit(1);
        }

        curl_close($curl);
      }
      $sites->free_result();
      $mysqli->close();
      break;
    case 'r':
    case 'reset':
      $id = is_numeric($value) ? intval($value) : false;
      $mysqli = get_database_connection();
      $where = $value !== false ? ' WHERE id="'.$mysqli->real_escape_string($id).'"' : '';
      if (!$mysqli->query('UPDATE sites SET status="NEW", httpcode=0'.$where)) {
        if (!$mysqli) {
          echo 'Error: '.mysqli_error($mysqli)."\n";
          $mysqli->close();
          exit(1);
        }
      }
      $mysqli->close();
      if ($verbose) {
        echo 'Reset performed '.($id !== false ? 'on #'.$id : 'on all sites')."\n";
      }
      break;
    case 'v':
    case 'verbose':
      break;
  }
}

?>
