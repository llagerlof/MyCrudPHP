<?php

class MyCrudPHP {
	private $conn;
	private $table_name;
	private $execution_state;
	private $filter;
	private $record;
	private $values;
	private $execution_log;
	private $table_structure;

	function __construct($conn) {
		$this->execution_state = 'INSERT';
		$paramType = gettype($conn);
		if ($paramType == 'object') {
			$this->conn = $conn;
		} else if ($paramType == 'array') {
			$this->conn = new PDO($conn['statement'], $conn['user'], $conn['password']);
		}
		//$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		//$this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	}

	// Return the table structure.
	public function getTableStructure() {
		return $this->table_structure;
	}

	// Set table name and structure
	public function table($table_name) {
		$this->table_name = $table_name;

		// Load table structure
		$sql = "desc " . $table_name;
		$ps = $this->conn->prepare($sql);
		$this->addExecutionLog(array(trim($sql)));

		$run = $ps->execute();
		if (!$run) {
			$errorInfo = $ps->errorInfo();
			$errorMessage = $errorInfo[2];
			throw new Exception(__FUNCTION__ . '(): ' . $errorMessage);
		}

		$rs = $ps->fetchAll(PDO::FETCH_ASSOC);
		$this->table_structure = $rs;
		return $this;
	}

	public function getRecord($filter) {
		$newCrud = new self($this->conn);
		$newCrud->execution_state = 'UPDATE';
		$newCrud->filter = $filter;
		$newCrud->table_name = $this->table_name;
		$newCrud->table_structure = $this->table_structure;

		$where = "";
		foreach ($filter as $field => $value) {
			$where .= " and " . $field . " = :" . $field;
		}
		$sql = "
			select * from " . $this->table_name .
			" where true " . $where;

		$q = $this->conn->prepare($sql);
		$newCrud->addExecutionLog(array(trim($sql), $filter));

		$run = $q->execute($filter);

		if (!$run) {
			$errorInfo = $q->errorInfo();
			$errorMessage = $errorInfo[2];
			throw new Exception(__FUNCTION__ . '(): ' . $errorMessage);
		}

		$r = $q->fetchAll(PDO::FETCH_ASSOC);

		$record_count = count($r);
		if ($record_count == 0) {
			throw new Exception(__FUNCTION__ . '():' . 'Record not found.');
		} elseif ($record_count > 1) {
			throw new Exception(__FUNCTION__ . '(): ' . 'More than 1 record found.');
		}
		$newCrud->record = $r;
		return $newCrud;
	}

	public function setValues($values) {
		if (!is_array($this->values)) {
			$this->values = array();
		}
		$merged = array_merge($this->values, $values);
		$this->values = $merged;
	}

	public function unsetValues($values) {
		if (!is_array($this->values)) {
			$this->values = array();
		}
		if (!empty($this->values)) {
			foreach ($values as $field) {
				unset($this->values[$field]);
			}
		} else {
			throw new Exception(__FUNCTION__ . '(): ' . 'Field values have not been set.');
		}
	}

	public function getValues() {
		return $this->values;
	}

	public function saveRecord() {
		if (($this->execution_state !== 'UPDATE') && ($this->execution_state !== 'INSERT')) {
			throw new Exception(__FUNCTION__ . '(): ' . 'Tried to save without previous call to getRecord() or newRecord().');
		}

		if (($this->execution_state == 'UPDATE') && (empty($this->filter))) {
			throw new Exception(__FUNCTION__ . '(): ' . "Can't update without filter.");
		}

		if (empty($this->table_name)) {
			throw new Exception(__FUNCTION__ . '(): ' . 'Table name have not been set.');
		}

		if (empty($this->values)) {
			throw new Exception(__FUNCTION__ . '(): ' . 'Field values have not been set.');
		}

		if ($this->execution_state == 'UPDATE') {
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

			$run = $q->execute($bound);

			if (!$run) {
				$errorInfo = $q->errorInfo();
				$errorMessage = $errorInfo[2];
				throw new Exception(__FUNCTION__ . '(): ' . $errorMessage);
			} else {
				foreach ($this->values as $field => $value) {
					$this->record[0][$field] = $value;
				}
				$this->values = array();
				return true;
			}

		} elseif ($this->execution_state == 'INSERT') {

			$fields_params = $this->getFieldsParameters();
			$fields_insert = $this->getFieldsInsert();

			$sql = "
				insert into ". $this->table_name . " (" . $fields_insert . ") values (". $fields_params .")
			";
			$q = $this->conn->prepare($sql);
			$bound = $this->values;
			$this->addExecutionLog(array(trim($sql), $bound));

			$run = $q->execute($bound);

			if (!$run) {
				$errorInfo = $q->errorInfo();
				$errorMessage = $errorInfo[2];
				throw new Exception(__FUNCTION__ . '(): ' . $errorMessage);
			} else {
				$this->values = array();
				return true;
			}

		} else {
			return false;
		}
	}

	public function save() {
		return $this->saveRecord();
	}

	private function addExecutionLog($data) {
		$this->execution_log[] = $data;
	}

	public function getExecutionLog() {
		return $this->execution_log;
	}

	public function getLoadedRecord() {
		return $this->record[0];
	}

	private function getFieldsParameters() {
		$params = "";
		$counter = 1;
		$number_of_fields = count($this->values);
		foreach ($this->values as $field => $value) {
			$params .= ":" . $field;
			if ($counter < $number_of_fields) {
				$params .= ", ";
			}
			$counter++;
		}
		return $params;
	}

	private function getFieldsInsert() {
		$params = "";
		$counter = 1;
		$number_of_fields = count($this->values);
		foreach ($this->values as $field => $value) {
			$params .= $field;
			if ($counter < $number_of_fields) {
				$params .= ", ";
			}
			$counter++;
		}
		return $params;
	}

	public function newRecord() {
		$newCrud = new self($this->conn);
		$newCrud->table_name = $this->table_name;
		$newCrud->execution_state = 'INSERT';
		foreach ($this->table_structure as $field) {
			$newCrud->record[0][$field['Field']] = null;
		}
		return $newCrud;
	}

	public function copyAsNew() {
		$newCrud = new self($this->conn);
		$newCrud->table_name = $this->table_name;
		$newCrud->table_structure = $this->table_structure;
		$newCrud->execution_state = 'INSERT';
		$newCrud->setValues($this->getLoadedRecord());
		$newCrud->record = $this->record;
		return $newCrud;
	}

	public function deleteRecord() {
		if (empty($this->filter)) {
			throw new Exception(__FUNCTION__ . '(): ' . "Can't delete without filter.");
		}

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

		$sql = "
			delete from " . $this->table_name .
			" where " . $where;

		$this->addExecutionLog(array(trim($sql), $this->filter));

		$q = $this->conn->prepare($sql);
		$bound = $this->filter;

		$run = $q->execute($bound);

		if (!$run) {
			$errorInfo = $q->errorInfo();
			$errorMessage = $errorInfo[2];
			throw new Exception(__FUNCTION__ . '(): ' . $errorMessage);
		} else {
			return true;
		}
	}
}

/*
// Examples
$PDO = new PDO( "mysql:host=127.0.0.1;port=3306;dbname=database", 'root', null);
$crud = new MyCrudPHP($PDO);

// Query:
$person = $crud->table('people')->getRecord(array('id' => 1));
print_r($person->getLoadedRecord());

// Update:
$person = $crud->table('people')->getRecord(array('id' => 1));
$person->setValues(array('name' => 'Lawrence', 'age' => 27));
$person->saveRecord();

// Insert:
$person = $crud->table('people')->newRecord();
$person->setValues(array('name' => 'Lawrence', 'age' => 27));
$person->saveRecord();

// Delete:
$person = $crud->table('people')->getRecord(array('id' => 1));
$person->deleteRecord();

// Copy record to another table:
$person = $crud->table('people')->getRecord(array('id' => 1));
$person_copy = $person->copyAsNew()->table('people_2');
$person_copy->saveRecord();

// Copy record to another table, excluding and/or adding some fields:
$person = $crud->table('people')->getRecord(array('id' => 1));
$person_copy = $person->copyAsNew()->table('people_2');
$person_copy->unsetValues(array('id'));
$person_copy->setValues(array('age' => 20));
$person_copy->saveRecord();
*/

?>