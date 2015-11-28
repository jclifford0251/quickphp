<?php
/*
 * AUTHOR: James Clifford <jclifford0251+quick@hotmail.com>
 * DATE  : 2014-02-01
 */
class quickgrid {
    //////////////////////////////////////////////////////////
    // Member Variables
    //////////////////////////////////////////////////////////

    //MySql Information
    private $select;
    private $from;
    private $where;

    private $result;
    private $sql;
    private $cols;
            
    //Pagation
    private $row_count;
    private $per_page;
    private $cur_page;
    private $pre_page;
    private $nxt_page;

    //Sort Order
    private $sort_col;
    private $sort_dir;

    //Html Options
    private $tbl_class;
    private $row_classes;
    private $col_classes;
    private $td_formats;

    //////////////////////////////////////////////////////////
    // Constructors
    //////////////////////////////////////////////////////////

    function quickgrid() {
        
        if(isset($_GET["p"])) {
            $this->cur_page = $_GET["p"];
        } else {
            $this->cur_page = 1;
        }
        $this->pre_page = $this->cur_page - 1;
        $this->nxt_page = $this->cur_page + 1;

        if(isset($_GET["pp"])) {
            $this->per_page = $_GET["pp"];
        } else {
            $this->per_page = 15;
        }

        if(isset($_GET["sn"])) {
            $this->sort_col = $_GET["sn"];
        } else {
            $this->sort_col = NULL;
        }

        if(isset($_GET["sd"])) {
            $this->sort_dir = $_GET["sd"];
        } else {
            $this->sort_dir = "A";
        }

        $this->row_count = 100;
        $this->cols = array();
        $this->tbl_class = "";
        $this->row_classes = array("");
        $this->col_classes = array("");
        $this->td_format = array();
        $this->td_formats = array();
        
    }

    //////////////////////////////////////////////////////////
    // Gets And Sets
    //////////////////////////////////////////////////////////

    public function setColumns($column_array) {
        $this->cols = $column_array;
    }

    public function addColumn($header, $field) {
        $this->cols[$header] = $field;
    }

    public function setPageSize($num) {
        $this->per_page = $num;
    }

    public function setPage($num) {
        $this->cur_page = $num;
    }

    public function setRowCount($num) {        
        $this->row_count = $num;
    }

    public function setTableClass($class) {
        $this->tbl_class = $class;
    }

    public function setRowAlternatingClasses($class_array) {
        $this->row_classes = $class_array;
    }

    public function setColumnAlternatingClasses($class_array) {
        $this->col_classes = $class_array;
    }

    public function setColumnFormat($callback_array) {
        $this->td_formats = $callback_array;
    }

    //////////////////////////////////////////////////////////
    // Worker Functions
    //////////////////////////////////////////////////////////

    public function getLimitSql() {
        $tmp = "";

        if($this->sort_col !== NULL) {
            $tmp = sprintf("\n ORDER BY `%s` %s",
                $this->cols[$this->sort_col],
                $this->sort_dir === "A" ? "ASC" : "DESC"
            );
        }

        $tmp .= sprintf("\n LIMIT %d, %d",
            ($this->per_page * ($this->cur_page - 1)),
            $this->per_page
        );

        return $tmp;
    }

    public function html($mysqli_stmt) {
        $xml = new XmlWriter();
		$xml->openMemory();
		$xml->setIndent(true);
		$xml->setIndentString("\t");
			
		$xml->startElement('table');
        $xml->writeAttribute('class', $this->tbl_class);

            $xml->startElement('thead');
                $xml->startElement('tr');

                    //////////////////////////////////
                    // Column Headers
                    /////////////////////////////////

                    $cntcol = count($this->col_classes);
                    $altcol = 0;

                    foreach(array_keys($this->cols) as $th) {
                        $xml->startElement('th');
                        $xml->writeAttribute('scope','col');

                        if($this->col_classes[$altcol] != "") {
                            $xml->writeAttribute('class', $this->col_classes[$altcol]);
                        }
                        $altcol = ++$altcol % $cntcol;

                        if(substr($th,0,2) == "__") {
                            $xml->text('');
                        } else {
                            //Sorting
                            $dir = "A";
                            if (isset($_GET["sn"]) 
                            && ($_GET["sn"] == $th) 
                            && ($_GET["sd"] == "A") 
                            ) {
                                $dir = "D";
                            }

                            $xml->startElement('a');
                            $xml->startAttribute('href');
                            $xml->writeRaw(quickgrid::get_href(["sn" => $th, "sd" => $dir, "p" => 1]));
                            $xml->endAttribute();

                            $xml->text($th);
                            $xml->endElement(); //a
                        }
                        $xml->endElement(); //th
                    }
                $xml->endElement(); //tr
            $xml->endElement(); //thead

            $xml->startElement('tfoot');
                $xml->startElement('tr');
                    $xml->startElement('td');
                    $xml->writeAttribute('colspan',count($this->cols));

                    //////////////////////////////////
                    // Pager
                    /////////////////////////////////

                    $last = ceil($this->row_count / $this->per_page);
                    $length = 8;
                    $lbound = $this->cur_page - ($length / 2);
                    $ubound = $this->cur_page + ($length / 2);

                    if($lbound < 1) $lbound = 1;
                    if($ubound > $last ) $ubound = $last;

                    if($this->cur_page != 1) {
                        $xml->startElement('a');
                        $xml->startAttribute('href');
                        $xml->writeRaw(quickgrid::get_href(["p" => $this->cur_page - 1]));
                        $xml->endAttribute();
                        $xml->text("<");
                        $xml->endElement(); //a
                    }

                    for($i = $lbound; $i <= $ubound; $i++) {    
                        if($i != $this->cur_page) {
                            $xml->startElement('a');
                            $xml->startAttribute('href');
                            $xml->writeRaw(quickgrid::get_href(["p" => $i]));
                            $xml->endAttribute();
                            $xml->text("$i");
                            $xml->endElement(); //a
                        } else {
                            $xml->startElement('span');
                            $xml->text("$i");
                            $xml->endElement(); //span
                        }
                    }

                    if($this->cur_page != $last) {
                        $xml->startElement('a');
                        $xml->startAttribute('href');
                        $xml->writeRaw(quickgrid::get_href(["p" => $this->cur_page + 1]));
                        $xml->endAttribute();
                        $xml->text(">");
                        $xml->endElement(); //a                        
                    }

                    $xml->endElement(); //td
                $xml->endElement(); //tr
            $xml->endElement(); //tfoot

            $xml->startElement('tbody');
                
                //////////////////////////////////
                // Table Data
                /////////////////////////////////
                
                $cntrow = count($this->row_classes);
                $altrow = 0;

                $cntcol = count($this->col_classes);
                $altcol = 0;
                
                $result = $mysqli_stmt->get_result();
                while($row = $result->fetch_assoc())
		        {
                    $xml->startElement('tr');
                    
                    if($this->row_classes[$altrow] != "") {
                        $xml->writeAttribute('class', $this->row_classes[$altrow]);
                    }
                    $altrow = ++$altrow % $cntrow;

                    foreach(array_keys($this->cols) as $th) {
                        $xml->startElement('td');

                        if($this->col_classes[$altcol] != "") {
                            $xml->writeAttribute('class', $this->col_classes[$altcol]);
                        }
                        $altcol = ++$altcol % $cntcol;

                        if(isset($this->td_formats[$th]) && is_callable($this->td_formats[$th])) {
                            $tmp = $this->td_formats[$th];
                            $xml->writeRaw(call_user_func($tmp,$row));
                        } else {
                            $td = $this->cols[$th];
                            if(isset($row[$td])) {
                                $xml->text($row[$td]);
                            } else {
                                $xml->text('');
                            }
                        }

                        $xml->endElement(); //td
                    }
                    $xml->endElement(); //tr
                }
        
            $xml->endElement(); //tbody

        $xml->endElement(); //table
		return $xml->outputMemory();
    }

    //////////////////////////////////////////////////////////
    // Private Functions
    //////////////////////////////////////////////////////////

    private static function get_href($new_values) {
        $uri = $_SERVER['REQUEST_URI'];
    
        //Get the path of the URI, without the GET string
        $pos = strpos($uri,"?");
        if($pos > 0) {
            $path = substr($uri,0,$pos);
        } else {
            $path = $uri;
        }

        $new_values += $_GET; //Merge the replacement values with the existing GET string

        //Rebuild the GET string
        $vars = "?";
        $amp = '';
        foreach(array_keys($new_values) as $name) {
            $value = $new_values[$name];
            if($value != "") {
                $vars .= sprintf("%s%s=%s",
                    $amp,
                    urlencode($name),
                    urlencode($value)
                );
            }
            $amp = '&';
        }

        return "$path$vars";
    }
}
