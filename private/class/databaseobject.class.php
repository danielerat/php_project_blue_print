<?php
class DatabaseObject
{
    static protected $db;
    static protected $db_columns;
    static protected $table_name = "";
    //create our colums from the Database
    static protected $columns = [];
    // Array to hold all the errors
    public $errors = [];

    // Set the database connection instance from outside
    static public function set_database($database)
    {
        self::$db = $database;
    }

    // Function To Execute Databse queries 
    static public function find_by_sql($sql)
    {
        // Execute Query (return a resultset)
        $result = self::$db->query($sql);
        if (!$result) {
            exit("Database Query Failed.");
        }
        // convert result into object 
        $object_array = [];
        while ($record = $result->fetch_assoc()) {
            $object_array[] = static::instantiate($record);
        }

        $result->free();
        return $object_array;
    }

    static public function find_all()
    {
        $sql = "SELECT * FROM " . static::$table_name;
        return static::find_by_sql($sql);
    }
    static public function count_all()
    {
        $sql = "SELECT count(*) FROM " . static::$table_name;
        /* Since we are going to find a single row with a single valur
         No need to call the fancy find_by_sql function 
        Instead we are goin to use a fetch array on a resultset*/
        $result_set = self::$db->query($sql);
        $row = $result_set->fetch_array();
        return array_shift($row);
    }


    static public function find_by_id($id)
    {
        $sql = "SELECT * FROM " . static::$table_name;
        $sql .= " where id='" . self::$db->escape_string($id) . "'";
        $object_array = static::find_by_sql($sql);
        if (!empty($object_array)) {
            // Since it's only one object then thre is no need to retrun a whole array with data 
            return array_shift($object_array);
        } else {
            return false;
        }
    }





    //Function to create new instance from a select statement
    static protected function instantiate($record)
    {

        $object = new static;
        // We could manually assign values to properties 
        //  but automatic assignment is gonna be faster easier and reuseable 

        foreach ($record as $property => $value) {
            if (property_exists($object, $property)) {
                $object->$property = $value;
            }
        }

        return $object;
    }





    // Functions to Do The crud Operations on our Instance

    protected function validate()
    {
        // Kinda reset the error thing , to an emty arry before adding new erros to  it
        $this->errors = [];
        // add custom validations
        return $this->errors;
    }

    // Create A Record
    protected function create()
    {
        $this->validate();
        if (!empty($this->errors)) {
            return "False";
        }
        $attributes = $this->sanitize_attributes();
        $sql = "INSERT INTO " . static::$table_name . " (";
        $sql .= join(',', array_keys($attributes));
        $sql .= ") values('";
        $sql .= join("','", array_values($attributes));
        $sql .= "');";

        $result = self::$db->query($sql);
        if ($result) {
            $this->id = self::$db->insert_id;
        }
        return $result;
    }


    // Update A Record
    protected function update()
    {
        //Get the sanitized version of our attributes 
        $this->validate();
        if (!empty($this->errors)) {
            return "False";
        }
        $attributes = $this->sanitize_attributes();
        $attribute_pairs = [];

        //Create a String like of attributes pairs
        foreach ($attributes as $key => $values) {
            $attribute_pairs[] = "{$key}='{$values}'";
        }

        $sql = " UPDATE " . static::$table_name . " SET ";
        //Joing them with , : brand='',model='',..... 
        $sql .= join(",", $attribute_pairs);

        $sql .= " Where id='" . self::$db->escape_string($this->id) . "'";
        $result = self::$db->query($sql);
        return $result;
    }
    public function merge_attributes($args = [])
    {
        foreach ($args as $key => $value) {
            if (property_exists($this, $key) && !is_null($value)) {
                $this->$key = $value;
            }
        }
    }



    //Properties which have the database columns excluding id
    public function attributes()
    {
        $attributs = [];
        foreach (static::$db_columns as $column) {
            if ($column == 'id') {
                continue;
            }
            $attributs[$column] = $this->$column;
        }
        return $attributs;
    }

    protected function sanitize_attributes()
    {
        $sanitized = [];
        foreach ($this->attributes() as $key => $value) {
            $sanitized[$key] = self::$db->escape_string($value);
        }
        return $sanitized;
    }

    // Delete a row in a database
    public function delete()
    {
        $sql = "DELETE FROM " . static::$table_name;
        $sql .= " WHERE id='" . self::$db->escape_string($this->id) . "' ";
        $sql .= "Limit 1";
        $result = self::$db->query($sql);
        return $result;

        // After Deleting the instance of the object it will still 
        // Exist , even though the database record does not 
        // this can be useful , as in : 
        // we can still use this $user->first_name. was Deleted.
        // Despite not having the record 
        // But we can not call $user->update() after calling delete 
    }




    // If there is an id set , then we want to update otherwise , we want to create
    public function save()
    {
        if (isset($this->id)) {
            return $this->update();
        } else {
            return $this->create();
        }
    }




    // End Of actie record Code -------------------------

}