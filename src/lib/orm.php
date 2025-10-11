<?php
require_once __DIR__."/../conf/config.php";

abstract class PersistentEntity {
    public ?int $Id = null;
    public ?string $Guid = null;
    public string $Created;
    public ?string $Updated = null;

    public function __construct() {
        if (!isset($this->Guid)) {
            $this->Guid = self::generateGuid();
        }
    }

    public static function generateGuid(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function getPersistentProperties(): array {
        $props = [];
        foreach ((new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $type = $prop->getType();
            $props[$prop->getName()] = $type ? $type->getName() : 'TEXT';
        }
        return $props;
    }
}

class PersistentEntityManager {
    private PDO $db;
    private Logger $logger;
    private string $class;
    private string $table;

    public function __construct(string $class, Logger $logger, PDO|string $dbOrPath, ?string $table = null) {
        if (!is_subclass_of($class, PersistentEntity::class)) {
            throw new InvalidArgumentException("Class '$class' must extend PersistentEntity.");
        }

        $this->class = $class;
        $this->logger = $logger;
        $this->table = $table ?? $class;

        if ($dbOrPath instanceof PDO) {
            $this->db = $dbOrPath;
        } else {
            $dir = dirname($dbOrPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (!is_writable($dir)) {
                throw new RuntimeException("DB directory not writable: $dir");
            }
            $this->db = new PDO('sqlite:' . $dbOrPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        $this->initTable();
    }

    private function initTable(): void {
        $instance = new $this->class();
        $props = $instance->getPersistentProperties();

        $cols = ["Id INTEGER PRIMARY KEY AUTOINCREMENT"];
        foreach ($props as $name => $type) {
            if ($name === 'Id') continue;
            $cols[] = "$name " . $this->mapType($type);
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (" . implode(", ", $cols) . ")";
        $this->db->exec($sql);
    }

    private function mapType(string $type): string {
        return match ($type) {
            'int', 'integer' => 'INTEGER',
            'float', 'double' => 'REAL',
            'bool', 'boolean' => 'INTEGER',
            'array' => 'TEXT',
            default => 'TEXT',
        };
    }

    private function serializeProperty(ReflectionProperty $prop, mixed $value): mixed {
        if ($prop->getType()?->getName() === 'array') {
            return json_encode($value);
        }
        return $value;
    }

    private function deserializeProperty(ReflectionProperty $prop, mixed $value): mixed {
        $type = $prop->getType()?->getName();

        if ($type === 'array') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        if ($type === 'bool') {
            return (bool)$value;
        }

        return $value;
    }

    public function save(PersistentEntity $entity): bool {
        $props = $entity->getPersistentProperties();
        $data = [];
        $now = date('c');

        if (empty($entity->Guid)) $entity->Guid = PersistentEntity::generateGuid();
        if (empty($entity->Created)) $entity->Created = $now;
        $entity->Updated = $now;

        foreach ($props as $name => $_) {
            if ($name === 'Id') continue;
            $value = $entity->$name ?? null;
            $prop = new ReflectionProperty($entity, $name);

            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $data[$name] = $this->serializeProperty($prop, $value);
        }

        try {
            if ($entity->Id) {
                $assignments = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
                $data['Id'] = $entity->Id;
                $stmt = $this->db->prepare("UPDATE {$this->table} SET $assignments WHERE Id = :Id");
            } else {
                $cols = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
                $stmt = $this->db->prepare("INSERT INTO {$this->table} ($cols) VALUES ($placeholders)");
            }
            $stmt->execute($data);

            if (!$entity->Id) {
                $entity->Id = (int) $this->db->lastInsertId();
            }

            return true;

        } catch (PDOException $e) {
            $this->logger->error("Save failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(PersistentEntity $entity): bool {
        if (!$entity->Id) return false;

        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE Id = :Id");
            $stmt->execute(['Id' => $entity->Id]);
            return true;
        } catch (PDOException $e) {
            $this->logger->error("Delete failed: " . $e->getMessage());
            return false;
        }
    }

    public function find(array $filters): ?PersistentEntity {
        $results = $this->list($filters, ['Id' => 'ASC'], 1, 1);
        return $results[0] ?? null;
    }

    private function debugSql(string $sql, array $params): string
    {
        $interpolated = $sql;
        foreach ($params as $key => $value) {
            $quoted = is_numeric($value) ? $value : $this->db->quote($value);
            $interpolated = str_replace($key, $quoted, $interpolated);
        }
        return $interpolated;
    }

    public function list(array $filters = [], ?array $order_by = ['Id' => 'ASC'], ?int $page = null, ?int $page_size = null): array {
        
        if (
            !empty($filters) &&
            !isset($filters['AND']) &&
            !isset($filters['OR']) &&
            !isset($filters['NOT']) &&
            !(isset($filters[0]['field']) && isset($filters[0]['op']) && isset($filters[0]['value']))
        ) {
            $converted = [];
            foreach ($filters as $field => $value) {
                $converted[] = ['field' => $field, 'op' => '=', 'value' => $value];
            }
            $filters = ['AND' => $converted];
        }
        $params = [];
        $counter = 0;
        $where = $this->buildWhereClause($filters, $params, $counter);

        $sql = "SELECT * FROM {$this->table}";
        if ($where !== '') {
            $sql .= " WHERE $where";
        }

        if ($order_by) {
            $order = [];
            foreach ($order_by as $col => $dir) {
                $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $order[] = "$col $dir";
            }
            $sql .= " ORDER BY " . implode(", ", $order);
        }

        if ($page !== null && $page_size !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $page_size;
            $params[':offset'] = ($page - 1) * $page_size;
        }
        $sqlDebug = 'Step 1: ' . $sql;
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $sqlDebug = 'Step 2: ' . $this->debugSql($sql, $params);
            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $obj = new $this->class();
                foreach ($row as $k => $v) {
                    if (property_exists($obj, $k)) {
                        $prop = new ReflectionProperty($obj, $k);
                        $obj->$k = $this->deserializeProperty($prop, $v);
                    }
                }
                $results[] = $obj;
            }

            return $results;

        } catch (PDOException $e) {
            $this->logger->error("List failed for class " . $this->class . "(" . $sqlDebug . "): " . $e->getMessage());
            return [];
        }
    }

    private function buildWhereClause(array $filters, array &$params, int &$counter): string {
        $clauses = [];

        foreach ($filters as $logic => $conditions) {
            $logic_upper = strtoupper($logic);

            if (in_array($logic_upper, ['AND', 'OR', 'NOT'], true)) {
                $sub = [];
                foreach ($conditions as $condition) {
                    if (is_array($condition) && count($condition) === 1 && isset(array_keys($condition)[0])) {
                        $key = array_keys($condition)[0];
                        $value = $condition[$key];
                        $param = ":p$counter";
                        $sub[] = "$key = $param";
                        $params[$param] = $value;
                        $counter++;
                        continue;
                    }

                    if (isset($condition['field'], $condition['op'])) {
                        $param = ":p$counter";
                        $field = $condition['field'];
                        $op = strtoupper($condition['op']);
                        $value = $condition['value'] ?? null;

                        if (in_array($op, ['IS NULL', 'IS NOT NULL'], true)) {
                            $sub[] = "$field $op";
                        } else {
                            $sub[] = "$field $op $param";
                            $params[$param] = $value;
                            $counter++;
                        }
                    } else {
                        $nested = $this->buildWhereClause($condition, $params, $counter);
                        if ($nested !== '') $sub[] = "($nested)";
                    }
                }

                if (!empty($sub)) {
                    $joined = implode(" $logic_upper ", $sub);
                    $clauses[] = $logic_upper === 'NOT' ? "NOT ($joined)" : "($joined)";
                }

            } elseif (is_numeric($logic)) {
                $condition = $conditions;
                if (isset($condition['field'], $condition['op'])) {
                    $param = ":p$counter";
                    $field = $condition['field'];
                    $op = strtoupper($condition['op']);
                    $value = $condition['value'] ?? null;

                    if (in_array($op, ['IS NULL', 'IS NOT NULL'], true)) {
                        $clauses[] = "$field $op";
                    } else {
                        $clauses[] = "$field $op $param";
                        $params[$param] = $value;
                        $counter++;
                    }
                } elseif (is_array($condition) && count($condition) === 1) {
                    $key = array_keys($condition)[0];
                    $value = $condition[$key];
                    $param = ":p$counter";
                    $clauses[] = "$key = $param";
                    $params[$param] = $value;
                    $counter++;
                }
            }
        }

        return implode(' AND ', $clauses);
    }

    public function getPDO(): PDO {
        return $this->db;
    }

    public function select(string $whereClause): array {
        $sql = "SELECT * FROM {$this->table} WHERE $whereClause";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $obj = new $this->class();
                foreach ($row as $k => $v) {
                    if (property_exists($obj, $k)) {
                        $obj->$k = $v;
                    }
                }
                $results[] = $obj;
            }

            return $results;
        } catch (PDOException $e) {
            $this->logger->error("Select failed: " . $e->getMessage());
            return [];
        }
    }
}
?>
