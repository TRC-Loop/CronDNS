<?php

require_once __DIR__.'/../conf/config.php';

abstract class PersistentEntity {
    protected static $db;
    public ?int $id = null;
    public ?string $guid;
    public string $created;
    public string $updated;
    private static array $initializedTables = [];

    public function __construct() {
        self::initDB();
    
        if (empty($this->guid)) {
            $this->guid = self::generateGuid();
        }
    
        $this->initTable();
    }
    
    protected static function generateGuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, // version 4
            mt_rand(0, 0x3fff) | 0x8000, // variant
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }    
    
    public static function count(): int {
        self::initDB();
        $class = static::class;
        $stmt = self::$db->query("SELECT COUNT(*) FROM $class");
        return (int) $stmt->fetchColumn();
    }


    private static function initDB() {
        if (!self::$db) {
            $dbPath = DB;
            $dir = dirname($dbPath);
    
            // Make sure the directory exists
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                    throw new RuntimeException("Failed to create directory for database: $dir");
                }
            }
    
            // Make sure it's writable
            if (!is_writable($dir)) {
                throw new RuntimeException("Database directory is not writable: $dir");
            }
    
            self::$db = new PDO('sqlite:' . $dbPath);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }
    
    public static function getDbType() : string {
        $db = new PDO('sqlite:' . DB);
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        return $driver;
    }

    private function initTable() {
        $class = static::class;
    
        if (isset(self::$initializedTables[$class])) {
            return; // Already initialized for this class
        }
    
        $props = $this->getProperties();
        $cols = ["id INTEGER PRIMARY KEY AUTOINCREMENT"];
        foreach ($props as $prop => $type) {
            if ($prop === 'id') continue;
            $sqliteType = $this->mapType($type);
            $cols[] = "$prop $sqliteType";
        }
    
        $sql = "CREATE TABLE IF NOT EXISTS $class (" . implode(', ', $cols) . ")";
        self::$db->exec($sql);
    
        self::$initializedTables[$class] = true;
    }    

    private function mapType(string $type): string {
        return match ($type) {
            'int', 'integer' => 'INTEGER',
            'float', 'double' => 'REAL',
            'bool', 'boolean' => 'INTEGER',
            default => 'TEXT',
        };
    }

    private function getProperties(): array {
        $refl = new ReflectionClass($this);
        $props = [];
        foreach ($refl->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $props[$prop->getName()] = $type ? $type->getName() : 'text';
        }
        return $props;
    }

    public function save(): bool {
        $props = $this->getProperties();
        $data = [];
    
        $now = date('c'); // ISO 8601 format
    
        foreach ($props as $prop => $_) {
            if ($prop === 'id') continue;
    
            // Handle timestamps
            if ($prop === 'created' && empty($this->created)) {
                $this->created = $now;
            }
            if ($prop === 'updated') {
                $this->updated = $now;
            }
    
            $data[$prop] = $this->$prop ?? null;
        }
    
        $class = static::class;
    
        try {
            if ($this->id) {
                $fields = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $data['id'] = $this->id;
                $stmt = self::$db->prepare("UPDATE $class SET $fields WHERE id = :id");
            } else {
                $cols = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
                $stmt = self::$db->prepare("INSERT INTO $class ($cols) VALUES ($placeholders)");
            }
    
            $stmt->execute($data);
    
            if (!$this->id) {
                $this->id = (int) self::$db->lastInsertId();
            }
    
            return true;
        } catch (PDOException $e) {
            // Optional: log $e->getMessage()
            return false;
        }
    }
        
    public static function load(int $id): ?static {
        self::initDB();
        $class = static::class;
        $stmt = self::$db->prepare("SELECT * FROM $class WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $obj = new static();
        foreach ($row as $k => $v) {
            if (property_exists($obj, $k)) {
                $obj->$k = $v;
            }
        }
        return $obj;
    }

    public function delete(): void {
        if (!$this->id) return;
        $class = static::class;
        $stmt = self::$db->prepare("DELETE FROM $class WHERE id = :id");
        $stmt->execute(['id' => $this->id]);
        $this->id = null;
    }

    public static function reconcile(): void {
        global $logger;
        $logger->info("Reconciling...");
        self::initDB();
        $class = static::class;
        $instance = new static(); // to access getProperties()
        $props = $instance->getProperties();
    
        // 1. Get current DB schema
        $stmt = self::$db->query("PRAGMA table_info($class)");
        $dbColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbFields = [];
        foreach ($dbColumns as $col) {
            $dbFields[$col['name']] = $col['type'];
        }
    
        // 2. Add missing columns
        foreach ($props as $name => $type) {
            if (!array_key_exists($name, $dbFields)) {
                $sqliteType = $instance->mapType($type);
                $sql = "ALTER TABLE $class ADD COLUMN $name $sqliteType";
                self::$db->exec($sql);
            }
        }
    
        // 3. Identify removed fields
        $toRemove = [];
        foreach ($dbFields as $name => $_) {
            if ($name === 'id') continue;
            if (!array_key_exists($name, $props)) {
                $toRemove[] = $name;
            }
        }
    
        // 4. Drop removed columns via table rebuild
        if ($toRemove) {
            $keep = array_keys($props);
            $keep[] = 'id'; // always keep 'id'
            $columnsSQL = implode(', ', array_map(fn($c) => "`$c`", $keep));
            $tmpTable = $class . '_tmp_' . uniqid();
    
            // Create new table
            $cols = ["id INTEGER PRIMARY KEY AUTOINCREMENT"];
            foreach ($props as $prop => $type) {
                if ($prop === 'id') continue;
                $sqliteType = $instance->mapType($type);
                $cols[] = "$prop $sqliteType";
            }
            $createSQL = "CREATE TABLE $tmpTable (" . implode(', ', $cols) . ")";
            self::$db->exec($createSQL);
    
            // Copy data
            $copySQL = "INSERT INTO $tmpTable ($columnsSQL) SELECT $columnsSQL FROM $class";
            self::$db->exec($copySQL);
    
            // Replace original table
            self::$db->exec("DROP TABLE $class");
            self::$db->exec("ALTER TABLE $tmpTable RENAME TO $class");
        }
    
        // 5. Reset initialized table flag so initTable() won't skip
        self::$initializedTables[$class] = true;
    }

    public function find(string $field, mixed $value): bool {
        self::initDB();
        $class = static::class;
    
        // Verify the field exists
        $props = $this->getProperties();
        if (!array_key_exists($field, $props)) {
            throw new InvalidArgumentException("Property '$field' does not exist on class '$class'");
        }
    
        $isString = $props[$field] === 'string';
        $useLike = $isString && strpbrk($value, '%_') !== false;
    
        $operator = $useLike ? 'LIKE' : '=';
    
        // Prepare and execute the SELECT query
        $stmt = self::$db->prepare("SELECT * FROM $class WHERE $field $operator :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$row) {
            return false;
        }
    
        // Clone to avoid altering the current object until successful
        $clone = clone $this;
        foreach ($row as $k => $v) {
            if (property_exists($clone, $k)) {
                $clone->$k = $v;
            }
        }
    
        // Copy from clone to original
        foreach ($props as $k => $_) {
            $this->$k = $clone->$k;
        }
    
        return true;
    }

    public static function findAll(string $field, mixed $value): array {
        self::initDB();
        $class = static::class;
        $instance = new static();
        $props = $instance->getProperties();
    
        if (!array_key_exists($field, $props)) {
            throw new InvalidArgumentException("Property '$field' does not exist on class '$class'");
        }
    
        $isString = $props[$field] === 'string';
        $useLike = $isString && strpbrk($value, '%_') !== false;
        $operator = $useLike ? 'LIKE' : '=';
    
        $stmt = self::$db->prepare("SELECT * FROM $class WHERE $field $operator :value");
        $stmt->execute(['value' => $value]);
    
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $obj = new static();
            foreach ($row as $k => $v) {
                if (property_exists($obj, $k)) {
                    $obj->$k = $v;
                }
            }
            $results[] = $obj;
        }
    
        return $results;
    }    
    
}

?>
