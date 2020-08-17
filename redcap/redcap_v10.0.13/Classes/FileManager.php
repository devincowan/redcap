<?php

class FileManager
{
    /**
	 * @return array of files received via the $_FILES array
	 */
	public static function getUploadedFiles()
	{
		$names = array_keys($_FILES); // names of the input file of the form
		$files = [];
		foreach($names as $name)
		{
			$current_file = $_FILES[$name]; // set the current file
			$keys = array_keys($current_file); // get all available keys for the current file ()
			
			$first_key = array_shift(array_values($keys));
			if(is_array($current_file[$first_key]))
			{
				$current_file = self::spread_array($current_file);
			}
			$files[$name] = $current_file;
		}
		return $files;
    }

    /**
	 * helper function to spread
     * the files received via $_FILES as arrays
	 * 
	 * @param array $vector
	 * @return array of files
	 */
	static private function spread_array($vector) { 
		$result = array(); 
		foreach($vector as $key1 => $value1) 
			foreach($value1 as $key2 => $value2) 
				$result[$key2][$key1] = $value2; 
		return $result; 
	}
	
	/**
	 * force the download of a file
	 *
	 * @param string $file_name
	 * @param string $data
	 * @return void
	 */
	private static function forceDownload($file_name, $data)
	{
		$quoted = sprintf('"%s"', addcslashes(basename($file_name), '"\\'));
		$size = strlen($data);

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $quoted); 
		header('Content-Transfer-Encoding: binary');
		header('Connection: Keep-Alive');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . $size);
		echo $data;
		exit(0);
	}


	/**
     * create a csv 
     *
     * @param array $rows array of lines[] 
     * @param boolean $extract_headers if true, get the headers from the keys of the first row
     * @param string $delimiter csv delimiter
     * @param string $enclosure 
     * @param string $escape_char 
     * @return string|null
     */
	public static function getCSV($rows=[], $extract_headers=true, $delimiter=',', $enclosure='"', $escape_char="\\")
	{
        if(count($rows) <= 0) return;

		if($extract_headers)
		{
			$header = array_keys($rows[0]); //get the header
			array_unshift($rows, $header); // prepend the header
		}
		ob_start();
		$handle = fopen("php://output",'w') or die("Can't open php://output");
		
		foreach($rows as $row) {
			fputcsv($handle, $row,$delimiter, $enclosure, $escape_char);
		}
		fclose($handle) or die("Can't close php://output");
		$output = ob_get_contents(); // stores buffer contents to the output variable
		ob_end_clean();  // clears buffer and closes buffering
		return $output;
	}
    
    /**
     * export a csv
     *
     * @param array $rows array of lines[] 
     * @param string $filename name of the file without extension
     * @param boolean $extract_headers if true, get the headers from the keys of the first row
     * @param string $delimiter csv delimiter
     * @param string $enclosure 
     * @param string $escape_char 
     * @return void
     */
    public static function exportCSV($rows=[], $filename='data-export', $extract_headers=true, $delimiter=',', $enclosure='"', $escape_char="\\")
	{
		$output = self::getCSV($rows, $extract_headers, $delimiter, $enclosure, $escape_char);
        if(empty($output)) return;
		self::forceDownload("{$filename}.csv", addBOMtoUTF8($output));
	}

	/**
	 * export a json file
	 *
	 * @param mixed $data
	 * @param string $filename
	 * @return void
	 */
	public static function exportJSON($data, $filename='data-export')
	{
		$encoded_data = json_encode($data);
		self::forceDownload("{$filename}.json", $encoded_data);
	}

	/**
	 * export a txt file
	 *
	 * @param mixed $lines
	 * @param string $filename
	 * @return void
	 */
	public static function exportText($text, $filename='data-export')
	{
		if(is_array($text)) $text = implode(PHP_EOL, $text);
		self::forceDownload("{$filename}.txt", $text);
	}
	
	/**
	 * guess the delimiter of a CSV file.
	 * parse the first line of a file and check for the first match
	 * of the available delimiters 
	 *
	 * @param string $file_path
	 * @return void
	 */
	private static function guessDelimiter($file_path)
    {
		// read first line of the file
		$handle = fopen($file_path, 'r');
		$first_line = fgets($handle);
		// rewind($handle);
		fclose($handle);
        $record_separator = chr(30); //  ASCII code 30: invisible character used to separate values
        $unit_separator = chr(31); //  ASCII code 31: delimiting character
		$delimiters = array(",", "\t", "|", ";", "^", $record_separator, $unit_separator);
		$pattern = sprintf('/[%s]/', implode('', $delimiters));
		preg_match($pattern, $first_line, $matches);
		if(count($matches) > 0) return $matches[0];
		// use the default delimiter if no matches
		$default_delimiter = ','; // use comma as the default delimiter
		return $default_delimiter;
    }


    /**
     * parse a file and get the csv data as array of lines
     *
     * @param string $file_path
     * @param integer $length
     * @param string $delimiter the delimiter character or 'auto' to guess the delimiter from the first line of the file
     * @param string $enclosure
     * @param string $escape_char
     * @return array
     */
    public static function readCSV($file_path, $length=0, $delimiter=',', $enclosure='"', $escape_char="\\")
    {
        $rows = array();
		$handle = fopen($file_path, "r");
		// Remove BOM, if applicable
		$file_contents = file_get_contents($file_path);
		$file_contents = removeBOMfromUTF8($file_contents);
		file_put_contents($file_path, $file_contents);
		if($delimiter === 'auto')
		{
			$delimiter = self::guessDelimiter($file_path);
		}
        while($line = fgetcsv ( $handle, $length, $delimiter, $enclosure, $escape_char ))
        {
            $rows[] = $line;
        }
        return $rows;
    }

    /**
	 * convert a csv to an associative array
     * uses header as keys otherwise the first element in the csv array
	 *
	 * @param array $csv Array of strings
	 * @param array $header Array of strings
	 * @return array Return the csv as an associative array
	 */
	static public function csvToAssociativeArray($csv=array(), $header=array())
	{
        $array = array();
        
		$keys = empty($header) ? array_shift($csv) : $header;
		foreach($csv as $row)
		{
			$array[] = array_combine($keys, $row);
		}
		return $array;
	}

	/**
	 * get the full path to a cache file
	 *
	 * @param string $file_name
	 * @return string
	 */
	static public function getCachedFilePath($file_name)
	{
		return APP_PATH_TEMP . DIRECTORY_SEPARATOR . $file_name;
	}

	/**
	 * retrieve a cached file
	 *
	 * @param string $file_name
	 * @param integer $lifespan only retrieve file if not older then specified lifespan
	 * @return string|null
	 */
	static public function getCachedFile($file_name)
	{
		$cache_file = self::getCachedFilePath($file_name);
		// Serve from the cache if it is younger than $cachetime
		if (file_exists($cache_file))
		{
			return file_get_contents($cache_file);
		}
	}

	/**
	 * save a file and use it as cache
	 *
	 * @param string $file_name
	 * @param string $data
	 * @return int|false The function returns the number of bytes that were written to the file, or false on failure.
	 */
	static public function cacheFile($file_name, $data)
	{
		$cache_file = APP_PATH_TEMP . DIRECTORY_SEPARATOR . $file_name;
		return file_put_contents($cache_file , $data ); //overwrite
	}

    /**
     * here for reference; could be useful for reading csv file with certain encodings
     *
     * @param string $fileName
     * @return object file handle
     */
    private static function utf8_fopen_read($fileName) { 
        $fc = iconv('windows-1250', 'utf-8', file_get_contents($fileName)); 
        $handle=fopen("php://memory", "rw"); 
        fwrite($handle, $fc); 
        fseek($handle, 0); 
        return $handle; 
    }

}