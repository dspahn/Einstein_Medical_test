<?php
  // this constant decides whether or not a line like:
  //    param =
  // is an error.  when TRUE, it is an error.  when false, it is not.
  // I wasn't sure whether or not it should be an error, so decided to make it easy to do either.
  define('NULL_VALUE_IS_ERROR', false);

  // this array is used to translate all of the various different yes/no, true/false, on/off,
  // yep/nope, etc into boolean values.  Depending on the application, I might be inclined to
  // put this into a database, or into a separate config file, thereby minimizing code changes
  // needed when values need to be added or removed.
  $booleans = array("yes"   => true,
                    "on"    => true,
                    "true"  => true,
                    "yep"   => true,
                    "no"    => false,
                    "off"   => false,
                    "false" => false,
                    "nope"  => false);
  
  // if the command line call doesn't look like 
  //   parse.php <config_file>
  // print out a brief usage message
  if ($argc < 2) {
    usage();
    exit;
  }
 
  // get the location of the config file.
  $file_str = $argv[1];

  // populate the $configs array
  $configs = load_configs($file_str);

  // print out the contents of the $configs array (just so you can see what load_configs() does.
  print_r($configs);

  // now loop through all the remaining argv elements (which should be the params being sought),
  // and display their values
  for ($i = 2; $i < $argc; $i++) {
    // first, does the requested param exist in the populated $configs array?
    if (array_key_exists($argv[$i], $configs)) {
      // it does.  is it a boolean?
      if (array_key_exists(strtolower($configs[$argv[$i]]), $booleans)) {
        // it is a boolean. so show it, but say that it's a true/false boolean for clarity's sake.
        
        // NOTE: This bit is really the only part of this whole thing that's slightly tricky, 
        //       so, explanation:
        //          If the command line param provided exists in the config file AND the _value_
        //          of the param exists as an array key of the $booleans array, return the true/false
        //          value found in the $booleans array instead of whatever pseudo boolean value is found
        //          in the config file.
        //        
        //          Example:
        //            some_param = yep
        //            1) Creates $configs['some_param'] => yep
        //            2) Looks in $booleans to see if array key of "yep" is there.
        //            3) It is, so it returns the value for $booleans['yep'] which is the 
        //               boolean true.
        //
        //       I thought about just translating all the different true/false combinations
        //       when creating the $configs array, but opted not to because it results in
        //       lost information.  Also, you (the person evaluating this) wouldn't have seen
        //       expected values in the print_r() statement above.  The strtolower() allows
        //         some_param = nope
        //       to be equivalent to 
        //         some_param = NOPE
        //       which is equivalent to 
        //         some_param = NoPe

        echo $argv[$i] . " : " . $booleans[strtolower($configs[$argv[$i]])] . " <--- this is a " . (($booleans[strtolower($configs[$argv[$i]])]) ? "TRUE" : "FALSE") . " boolean value\n";
      } else {
        // it is not boolean, so okay to display as is.
        echo $argv[$i] . " : " . $configs[$argv[$i]] . "\n";
      }
    } else {
      // the requested parameter isn't in the $configs array. 
      handle_error('unable to find parameter "' . $argv[$i] . '" in config file');
    }
  }

  function load_configs($file_str) {
    // check to make sure that the file location provided exists
    if (!file_exists($file_str)) {
      // it doesn't, so print an error, then the usage text, then exit out completely
      handle_error("file $file_str not found");
      usage();
      exit;
    }
    // attempt to open the file, read only, with a message if the permissions (or something else)
    // prevents the opening
    $handle = fopen($file_str, "r") or die ('unable to open ' . $file_str . "\n");

    $configs = array();

    // loop through each line in the file. depending on the size of the config file, it might be
    // more efficient to get the whole file at once with file(), and then loop through the resulting
    // array, but for any reasonable sized config file, it probably wouldn't matter. The main advantage
    // this method has is that in the event of a large config file, we're not using up a lot of memory
    // to store the entire file at once in an array.  meh.  probably 6 and half a dozen.
    while ($orig_line = fgets($handle)) {
      // strip out any extraneous whitespace before and after the line.
      $line = trim($orig_line);
      
      // first, look for #'s
      $pos = strpos($line, '#');
      
      if ($pos !== false) {
        // we found a #, so strip out the # and everything to the right of it, and any resulting
        // extra whitespace.  This allows a line like
        //   some_param = some_value # some comment
        // to be valid.  I did this because it needed to be done.
        $line = trim(substr($line, 0, $pos));
      }
      
      // after any text removal which might have been done above, does the line have info?
      if ($line != '') {
        // info exists so split the line on =
        $pieces = explode("=", $line);
        
        // make sure that we only have one = sign
        if (count($pieces) != 2) {
          // incorrect # of = found in line. do error.
          handle_error ("problem found: too few/many =", $orig_line);
        
        } elseif ( (trim($pieces[1]) == '') && NULL_VALUE_IS_ERROR) {
          // found a line that looks like:
          //   some_param =
          // if NULL_VALUE_IS_ERROR == true, this will generate an error, otherwise, you'll end up 
          // with a param with no value which might be valid....
          handle_error ("problem found: param value missing", $orig_line);
        
        } elseif ( array_key_exists(strtolower(trim($pieces[0])), $configs) ) {
          // found a duplicate param. we only keep the first value found.
          handle_error ("problem found: duplicate param name found. value discarded", $orig_line);
        
        } else {
          // no errors encountered, so store the name/value pair in the $configs array.
          
          // NOTE: we store all names in lowercase, because, really, who wants to deal with a config
          //       file where 
          //         some_param = some_value
          //       and
          //         SOME_PARAM = some_other_value
          //       results in two valid parameters?
          $configs[trim(strtolower($pieces[0]))] = trim($pieces[1]);
        }
      }
    }
    
    // close the file pointer
    fclose($handle);
    
    // return the array
    return $configs;
    
    // when this is done, $configs will look like:
    /*
      Array
      (
          [host] => test.com
          [server_id] => 55331
          [server_load_alarm] => 2.5
          [user] => user
          [verbose] => true
          [test_mode] => on
          [debug_mode] => off
          [log_file_path] => /tmp/logfile.log
          [send_notifications] => yes
      )
    */
  }

  function handle_error($msg, $line='') {
    // rudimentary error handling
    echo $msg . "\n";
    if ($line != '') {
      echo $line . "\n";
    }
    return;
  }

  function usage() {
    // quick n dirty usage info
    echo 'usage: parse.php <full config file location> [param1] [param2] ... [paramN]' . "\n";
    return;
  }
?>
