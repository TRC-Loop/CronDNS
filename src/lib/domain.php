<?php

require_once __DIR__."/orm.php";

class Domain extends PersistentEntity {
    public string $domain;
    public string $provider;
    public array $credentials;
}

?>
