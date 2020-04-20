<?php
/*
 *  Parse a HLDS log file to JSON format
 *
 *  Usage: php parser.php <logfile> [--json-pretty ] > output.json
 *  options:
 *    --json-pretty
 *
 *
 */

error_reporting(E_ERROR | E_WARNING | E_PARSE);
$json_pretty = $argv[2] == '--json-pretty';
$json = parse_file($argv[1], array('json_pretty' => $json_pretty));
echo ($json);


/*
 * Local functions
 */

/* Parse a HLDS log file and return json in _bulk format accepted for Elasticsearch bulk import
 *
 *  options:
 *   json_pretty (bool)
 */
function parse_file($file, $options = array()){
  $parsed = '';
  $handle = fopen($file, "r");
  if ($handle) {
    $bulk_import = array(
        'index' => array(
            '_index' => 'counter',
        ),
    );
    $bulk_import_json = json_encode($bulk_import);

    $actual_map_name = '';
    while (($line = fgets($handle)) !== false) {

      //First we look at the map
      $parsed_map_name = parse_map($line);
      $actual_map_name = $parsed_map_name ?: $actual_map_name;
      if ($actual_map_name){
        // if we get a map name, start parsing kills
        $parsed_line = parse_line($line);
        if(!empty($parsed_line)){
          $parsed_line['map'] = $actual_map_name;

          $parsed .= $bulk_import_json . "\r\n";
          $parsed .= json_encode($parsed_line) . "\r\n";
        }
      }
    }

    fclose($handle);
  } else {
    // error opening the file.
    // TODO show proper message
  }
  return $parsed;
}

function parse_line($data){
  $ret = array();

  // L 04/11/2020 - 22:59:17: "Oscar_Rusheri<11><STEAM_0:1:538409029><CT>" killed "FACUMURU<2><STEAM_0:1:538179695><TERRORIST>" with "aug"
  if (preg_match('/(\>" killed ")/', $data) ){
    preg_match('/(\d{2}\/\d{2}\/\d{4} - \d{2}:\d{2}:\d{2}): "(.+)<\d+><.+><([A-Z]+)>" killed "(.+)<\d+><.+><([A-Z]+)>" with "(.+)"/i',$data,$matches);
    $ret = array (
      'time' => DateTime::createFromFormat('m/d/Y - H:i:s', $matches[1])->format('Y-m-d H:i:s') ,
      'killer' => $matches[2],
      'killer_team' => $matches[3],
      'victim' => $matches[4],
      'victim_team' => $matches[5],
      'weapon' => $matches[6],
    );
  }

  return $ret;
}

function parse_map($data){
  $ret = false;
  // -------- Mapchange to de_aztec --------
  if (preg_match('/: -------- Mapchange to (.+) --------/i', $data) ){
    preg_match('/: -------- Mapchange to (.+) --------/i',$data,$matches);
    $ret = $matches[1];
  }
  return $ret;
}