# MyCrudPHP

## Description

MyCrudPHP is a single PHP class that provides basic CRUD functionality to be used with any PDO compatible database.

## Examples

### Include the class in your script
```
require_once("MyCrudPHP.php");
```

### Connect and instantiate the object
```
$PDO = new PDO( "mysql:host=127.0.0.1;port=3306;dbname=database", 'root', null);
$crud = new MyCrudPHP($PDO);
```

### Query record
```
$person = $crud->table('people')->getRecord(array('id' => 1));
print_r($person->getLoadedRecord());
```

### Update record
```
$person = $crud->table('people')->getRecord(array('id' => 1));
$person->setValues(array('name' => 'Lawrence', 'age' => 27));
$person->saveRecord();
```

### Insert record
```
$person = $crud->table('people')->newRecord();
$person->setValues(array('name' => 'Lawrence', 'age' => 27));
$person->saveRecord();
```

### Delete record
```
$person = $crud->table('people')->getRecord(array('id' => 1));
$person->deleteRecord();
```

### Copy record to another table
```
$person = $crud->table('people')->getRecord(array('id' => 1));
$person_copy = $person->copyAsNew()->table('people_2');
$person_copy->saveRecord();
```

### Copy record to another table, excluding and/or adding some fields
```
$person = $crud->table('people')->getRecord(array('id' => 1));
$person_copy = $person->copyAsNew()->table('people_2');
$person_copy->unsetValues(array('id'));
$person_copy->setValues(array('age' => 20));
$person_copy->saveRecord();
```
