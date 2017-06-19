<?php

class MyCrudPHP {
	private $conn;
	private $table_name;
	private $execution_state;
	private $filter;
	private $record;
	private $values;
	private $execution_log;

	function __construct($conn) {
		$this->log = new MyLogPHP();
		$paramType = gettype($conn);
		if ($paramType == 'object') {
			$this->conn = $conn;
		} else if ($paramType == 'array') {
			$this->conn = new PDO($conn['statement'], $conn['user'], $conn['password']);
		} 
	}

	// Return a table structure.
	private function getTableStructure($table) {
		$sql = "desc " . $table;
		$ps = $this->conn->prepare($sql);
		$this->addExecutionLog(array($trim(sql)));
		$ps->execute();
		$rs = $ps->fetchAll();
		return $rs;
	}

	public function table($table_name) {
		$this->table_name = $table_name;
		return $this;
	}
	
	public function getRecord($filter) {
		$this->execution_state = 'UPDATE';
		$this->filter = $filter;
		
		$where = "";
		foreach ($filter as $field => $value) {
			$where .= " and " . $field . " = :" . $field;
		}
		$sql = "
			select * from " . $this->table_name . 
			" where true " . $where;
		
		$q = $this->conn->prepare($sql);
		$this->addExecutionLog(array(trim($sql), $filter));
		$q->execute($filter);
		$r = $q->fetchAll();
		$this->record = $r;
		//return $r;
		return $this;
	}
	
	public function setValues($values) {
		if (!is_array($this->values)) {
			$this->values = array();
		}
		$temp = array_merge($this->values, $values);
		$this->values = $temp;
	}

	public function save() {
		if ( ($this->execution_state == 'UPDATE') && (count($this->values) > 0) && (count($this->filter) > 0) ) {
			// Filter
			$where = "";
			$counter = 1;
			$number_of_fields_filter = count($this->filter);
			foreach ($this->filter as $field => $value) {
				$where .= $field . " = :" . $field;
				if ($counter < $number_of_fields_filter) {
					$where .= " and ";
				}
				$counter++;
			}
			
			// Changed fields
			$set = "";
			$counter = 1;
			$number_of_fields = count($this->values);
			foreach ($this->values as $field => $value) {
				$set .= $field . " = :" . $field;
				if ($counter < $number_of_fields) {
					$set .= ", ";
				}
				$counter++;
			}
			
			$sql = "
				update " . $this->table_name . " set " . $set .
				" where " . $where;
			
			$this->addExecutionLog(array(trim($sql), $this->values, $this->filter));

			$q = $this->conn->prepare($sql);
			$bound = array_merge($this->values, $this->filter);
			if ($q->execute($bound)) {

				foreach ($this->values as $field => $value) {
					$this->record[0][$field] = $value;
				}
				$this->values = array();
				return true;
			} else {
				return false;
			}
		} else {
			if (($this->execution_state != 'UPDATE') || ($this->execution_state != 'INSERT')) {
				throw new Exception('Not in UPDATE or INSERT state.');
			} else {
				return true;
			}
		}
	}
	
	private function addExecutionLog($data) {
		$this->execution_log[] = $data;
	}
	
	public function getExecutionLog() {
		return $this->execution_log;
	}
	
	public function out() {
		print_r($this->record);
	}
	
	public function getLoadedRecord() {
		return $this->record;
	}
}

/*
// Example:
$PDO = new PDO( "mysql:host=127.0.0.1;port=3306;dbname=database", 'root', null);
$crud = new MyCrudPHP($PDO);
$person = $crud->table('persons')->getRecord(array('id_person' => 1));
$pessoa->setValues(array('name' => 'Lawrence'));
$pessoa->save();
*/

?>