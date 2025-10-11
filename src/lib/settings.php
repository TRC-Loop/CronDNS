<?php

require_once __DIR__."/orm.php";

class KeyValue extends PersistentEntity {
    public string $key;
    public mixed $value;
}

?>
