<?php
// Since we need to get events continuously during a long-polling watch
// I am using streams instead of curl
// Heavily inspired by https://github.com/travisghansen/kubernetes-client-php
class Stream {
    private $stream_context = '';
    private $buffer = '';
    const DEFAULT_STREAM_TIMEOUT_SECS = 2;
    const DEFAULT_STREAM_READ_LENGTH = 8192;
    
    function __construct($token) {
        $opts = array(
            'http'=>array(
                'ignore_errors' => true,
                'header' => "Accept: application/json, */*\r\nContent-Encoding: gzip\r\nContent-type: application/json\r\n",
                'method' => "GET"
            ),
            'ssl'=>array(
                'verify_peer' => false,
                'verify_peer_name' => false
            ),
        );
        
        if (!empty($token)) {
            $opts['http']['header'] .= "Authorization: Bearer ${token}\r\n";
        }
        
        $this->stream_context = stream_context_create($opts);
        
        // echo 'Stream Object Ready!';
    }
    
    function getResponse($url, $params=[], $content='', $delete=false) {
        $query = http_build_query($params);
        
        // reset content
        stream_context_set_option($this->stream_context, array(
                'http' => array(
                    'content' => null
                )
            ));
            
        if ($content) {
            stream_context_set_option($this->stream_context, array(
                'http' => array(
                    'method' => "POST",
                    'content' => $content
                )
            ));
        }
        
        if ($delete === true) {
            stream_context_set_option($this->stream_context, array(
                'http' => array(
                    'method' => "DELETE",
                    'content' => null
                )
            ));
            
        }

        if (!empty($query)) {
            $parsed = parse_url($base);
            if (key_exists('query', $parsed) || substr($base, -1) == "?") {
                $url .= '&'.$query;
            } else {
                $url .= '?'.$query;
            }
        }

        $handle = fopen($url, 'r', false, $this->stream_context);
        if ($handle === false) {
            $e = error_get_last();
            throw new \Exception($e['message'], $e['type']);
        }
        $response = stream_get_contents($handle);
        fclose($handle);

        $response = json_decode($response, true);

        return $response;
    }
    
    function getPollResponse($url, $params=[]) {
        $query = http_build_query($params);
        
        if (!empty($query)) {
            $parsed = parse_url($base);
            if (key_exists('query', $parsed) || substr($base, -1) == "?") {
                $url .= '&'.$query;
            } else {
                $url .= '?'.$query;
            }
        }

        // reset content
        stream_context_set_option($this->stream_context, array(
            'http' => array(
                'method' => "GET",
                'content' => null
            )
        ));

        $handle = fopen($url, 'r', false, $this->stream_context);
        stream_set_timeout($handle, self::DEFAULT_STREAM_TIMEOUT_SECS);
        if ($handle === false) {
            $e = error_get_last();
            throw new \Exception($e['message'], $e['type']);
        }
        
        $max_empty_tries = 1;
        $retry_count = 0;
        while (true) {
            if (feof($handle)) {
                return;
            }
    
            $data = fread($handle, self::DEFAULT_STREAM_READ_LENGTH);
            if ($data === false) {
                // PHP 7.4 now returns false when the timeout is hit
                if (version_compare(PHP_VERSION, '7.4', 'ge')) {
                    $data = "";
                } else {
                    throw new \Exception('Failed to read bytes from stream: ' . $url);
                }
            }

            // If the response is empty wait for 20 secs and try again
            // after the max number of retries break
            if (empty($data)) {
                if ($retry_count > $max_empty_tries) {
                    break;
                }
                $retry_count++;
                sleep(20);
            } else {
                $this->buffer .= $data;    
            }
            
            // blindly store the events in a text file so you know what the raw data looks like
            file_put_contents('./junk.txt',  print_r($data,1) . "\n----------\n", FILE_APPEND);
        }
        fclose($handle);
        
        // format the raw data to be used outside
        $return_events = [];
        if ((bool) strstr($this->buffer, "\n")) {
            $parts = explode("\n", $this->buffer);
            $parts_count = count($parts);
            for ($x = 0; $x < ($parts_count - 1); $x++) {
                if (!empty($parts[$x])) {
                    $return_events[] = json_decode($parts[$x], true);
                }
            }
        }
        
        return $return_events;
    }
    
    // just for debugging
    function printStreamContext() {
        print_r(stream_context_get_options($this->stream_context));
    }
}