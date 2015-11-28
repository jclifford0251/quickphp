<?php

class quicktar {
	var $tarName;
	var $isCompressed;
	var $a_name;
	var $a_data;
	var $a_size;
	var $count;
		
	function quicktar($p_file = "download.tar", $p_zip = false) {
		$this->tarName = $p_file;
		$this->isCompressed = $p_zip;
		$this->a_name = array();
		$this->a_data = array();
		$this->a_size = array();
		$this->count = 0;
	}
	
	function getFilename() {
		return $this->tarName;
	}
	
	function setFilename($p_name) {
		$this->tarName = $p_name;
	}
	
	function getCompression($p_yesno) {
		return $this->isCompressed;
	}
	
	function setCompression($p_yesno) {
		$this->isCompressed = $p_yesno;
	}
	
	function addFile($p_name, $p_data, $p_size = -1) {
		$this->a_name[$this->count] = $p_name;
		$this->a_data[$this->count] = $p_data;
		if($p_size < 0) {
			$this->a_size[$this->count] = strlen($p_data);
		} else {
			$this->a_size[$this->count] = $p_size;
		}
		$this->count++;
	}
	
	function send() {
		
		//Create the default header record. we will only be changing a few of these values as we send the files
		// var $h_name		=	str_pad("filename.txt",	100,	"\0",	STR_PAD_RIGHT); //null-terminated character string
		// var $h_mode		= 	str_pad("   777 ",		  8,	"\0",	STR_PAD_RIGHT);
		// var $h_uid		=	str_pad("     0 ",		  8,	"\0",	STR_PAD_RIGHT);
		// var $h_gid		=	str_pad("     0 ",		  8,	"\0",	STR_PAD_RIGHT);
		// var $h_size		=	str_pad("          4 ",	 12,	"\0",	STR_PAD_RIGHT);
		// var $h_mtime	    = 	str_pad("11612635301 ",	 12,	"\0",	STR_PAD_RIGHT);
		// var $h_chksum	=	str_pad("",				  8,	"\0",	STR_PAD_RIGHT);
		// var $h_linkflag	=	str_pad("0",			  1,	"\0",	STR_PAD_RIGHT);
		// var $h_linkname	=	str_pad("",				100,	"\0",	STR_PAD_RIGHT); //null-terminated character string
		// var $h_magic	    =	str_pad("",				  8,	"\0",	STR_PAD_RIGHT); //null-terminated character string
		// var $h_uname	    =	str_pad("",				 32,	"\0",	STR_PAD_RIGHT); //null-terminated character string
		// var $h_gname	    =	str_pad("",				 32,	"\0",	STR_PAD_RIGHT); //null-terminated character string
		// var $h_devmajor	=	str_pad("",				  8,	"\0",	STR_PAD_RIGHT);
		// var $h_devminor	=	str_pad("",				  8,	"\0",	STR_PAD_RIGHT);
		
		//I have to keep track of directory structure
		// var $dir = array();
		// var $dir_count = 0;
		$buf = "";
		for($i = 0; $i < $this->count; $i++) {
			//Create the header with spaces in the checksum, so you can calc the checksum			
			$head = sprintf("%s   777 \0     0 \0     0 \0%11o %11o         %s",
				str_pad($this->a_name[$i],100,"\0",STR_PAD_RIGHT),
				$this->a_size[$i],
				time(),
				//$this->checksum($this->a_data[$i],$this->a_size[$i]),
				str_pad("0",356,"\0",STR_PAD_RIGHT)
			);
			//Recreate the head, this time include the checksum
			$head = sprintf("%s   777 \0     0 \0     0 \0%11o %11o %6o \0%s",
				str_pad($this->a_name[$i],100,"\0",STR_PAD_RIGHT),
				$this->a_size[$i],
				time(),
				$this->checksum($head),
				str_pad("0",356,"\0",STR_PAD_RIGHT)
			);
			
			//make sure the data fits in 512 byte blocks
			if($this->a_size[$i] % 512 != 0) {
				$block = str_pad($this->a_data[$i],$this->a_size[$i] + (512 - ($this->a_size[$i] % 512)), "\0",  STR_PAD_RIGHT);			
			} else {
				$block = $this->a_data[$i];
			}
			
			//append this file to the buffer
			$buf .= $head . $block;
		}
	
		//Add the EOF empty record
		$buf .= str_pad("\0",1024,"\0",STR_PAD_RIGHT);
		
		$zip = gzencode($buf,9);
		
		//Set the HTTP headers
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT\n");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Content-type: application/x-gzip;\n"); //or yours?
		header("Content-Transfer-Encoding: binary");
		$len = strlen($zip);
		header("Content-Length: $len;\n");
		$outname=$this->tarName . ".gz";
		header("Content-Disposition: attachment; filename=\"$outname\";\n\n");
		
		
		//Write the tarball to the browser
		print($zip);
        exit(0);
	}
	
	function checksum($string, $length = -1) {
		if($length < 0)$length = strlen($string);
		$ret = 0;
		for($i=0;$i<$length;$i++) {
			$ret += ord(substr($string,$i,1));
		}
		return $ret;
	}

}
