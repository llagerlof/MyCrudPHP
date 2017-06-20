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

### Query a record
```
$person = $crud->table('persons')->getRecord(array('id' => 1));
print_r($person->getLoadedRecord());
```

### Update a record
```
$person = $crud->table('persons')->getRecord(array('id' => 1));
$person->setValues(array('name' => 'Lawrence', 'age' => 27));
$person->saveRecord();
```

### Insert a record
```
$person = $crud->table('persons')->newRecord();
$person->setValues(array('name' => 'Lawrence', 'age' => 27));
$person->saveRecord();
```

### Delete a record
```
$person = $crud->table('persons')->getRecord(array('id' => 1));
$person->deleteRecord();
```
