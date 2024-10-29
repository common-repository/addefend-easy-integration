<?php
function addefend_extract_from_markers($filename, $marker)
{
    $result = array();
    if (!file_exists($filename)) {
        return $result;
    }
    $markerdata = explode("\n", implode('', file($filename)));
    $state = false;
    foreach ($markerdata as $markerline) {
        if (false !== strpos($markerline, '# END ' . $marker)) {
            $state = false;
        }
        if ($state) {
            $result[] = $markerline;
        }
        if (false !== strpos($markerline, '# BEGIN ' . $marker)) {
            $state = true;
        }
    }
    return $result;
}

function addefend_insert_with_markers($filename, $marker, $insertion)
{
    if (!file_exists($filename)) {
        if (!is_writable(dirname($filename))) {
            return false;
        }
        if (!touch($filename)) {
            return false;
        }
    } elseif (!is_writeable($filename)) {
        return false;
    }

    if (!is_array($insertion)) {
        $insertion = explode("\n", $insertion);
    }

    $start_marker = "# BEGIN {$marker}";
    $end_marker = "# END {$marker}";

    $fp = fopen($filename, 'r+');
    if (!$fp) {
        return false;
    }

// Attempt to get a lock. If the filesystem supports locking, this will block until the lock is acquired.
    flock($fp, LOCK_EX);

    $lines = array();
    while (!feof($fp)) {
        $lines[] = rtrim(fgets($fp), "\r\n");
    }

// Split out the existing file into the preceding lines, and those that appear after the marker
    $pre_lines = $post_lines = $existing_lines = array();
    $found_marker = $found_end_marker = false;
    foreach ($lines as $line) {
        if (!$found_marker && false !== strpos($line, $start_marker)) {
            $found_marker = true;
            continue;
        } elseif (!$found_end_marker && false !== strpos($line, $end_marker)) {
            $found_end_marker = true;
            continue;
        }
        if (!$found_marker) {
            $pre_lines[] = $line;
        } elseif ($found_marker && $found_end_marker) {
            $post_lines[] = $line;
        } else {
            $existing_lines[] = $line;
        }
    }

// Check to see if there was a change
    if ($existing_lines === $insertion) {
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

// Generate the new file data
    $new_file_data = implode("\n", array_merge(
        array($start_marker),
        $insertion,
        array($end_marker),
        $pre_lines,
        $post_lines
    ));

// Write to the start of the file, and truncate it to that length
    fseek($fp, 0);
    $bytes = fwrite($fp, $new_file_data);
    if ($bytes) {
        ftruncate($fp, ftell($fp));
    }
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return (bool)$bytes;
}

function get_line_from_file($file, $find){
    $file_content = file_get_contents($file);
    $lines = explode("\n", $file_content);

    foreach($lines as $num => $line){
        $pos = strpos($line, $find);
        if($pos !== false)
            return $num + 1;
    }
    return false;
}

function getHeaders() {
    $out = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == "HTTP_") {
            $key = substr($key, 5);
            $key = str_replace("_", " ", $key);
            $key = strtolower($key);
            $key = ucwords($key);
            $key = str_replace(" ", "-", $key);
            $out[$key] = $value;
        }
    }
    return $out;
}