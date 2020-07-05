<?php namespace Zephyrus\Database\Core\Adapters;

use Zephyrus\Database\Core\Database;

class PostgresqlAdapter extends DatabaseAdapter
{
    const DBMS = ["pgsql"];

    public function getLimitClause(int $offset, int $maxEntities): string
    {
        return " LIMIT $maxEntities OFFSET $offset";
    }

    public function getSearchFieldClause(string $field, string $search): string
    {
        $search = $this->purify($search);
        return "($field ILIKE '%$search%')";
    }

    // myapp.user
    public function getAddEnvironmentVariableClause(string $name, string $value): string
    {
        return "set session \"$name\" = '$value';";
    }

    public function getAllTableNames(Database $database): array
    {
        $names = [];
        $sql = "SELECT tables.table_name FROM information_schema.tables WHERE tables.table_schema = 'public' AND tables.table_name != 'schema_version'";
        $statement = $database->query($sql);
        $results = [];
        while ($row = $statement->next()) {
            $results[] = $row->table_name;
        }
        return $results;
    }

    public function getAllColumnNames(Database $database, string $tableName): array
    {
        $columns = [];
        $statement = $database->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?", [$tableName]);
        while ($row = $statement->next()) {
            $columns[] = $row->column_name;
        }
        return $columns;
    }

    public function getAllConstraints(Database $database, string $tableName): array
    {
        $contraints = [];
        $sql = "SELECT tco.constraint_type, kcu.column_name
                  FROM information_schema.table_constraints tco
                  JOIN information_schema.key_column_usage kcu
                    ON kcu.constraint_name = tco.constraint_name
                   AND kcu.constraint_schema = tco.constraint_schema
                   AND kcu.constraint_name = tco.constraint_name
                 WHERE kcu.table_name = ?
                   AND kcu.table_schema = 'public'";
        $statement = $database->query($sql, [$tableName]);
        while ($row = $statement->next()) {
            $contraints[] = (object) [
                'column' => $row->column_name,
                'type' => $row->constraint_type
            ];
        }
        return $contraints;
    }

    public function getAllColumns(Database $database, string $tableName): array
    {
        $columns = [];
        $sql = "SELECT column_name, is_nullable, udt_name, character_maximum_length, column_default 
                  FROM information_schema.columns 
                 WHERE table_schema = 'public' 
                   AND table_name = ?";
        $statement = $database->query($sql, [$tableName]);
        while ($row = $statement->next()) {
            $columns[] = (object) [
                'name' => $row->column_name,
                'type' => strtoupper($row->udt_name) . (($row->udt_name == 'varchar') ? '(' . $row->character_maximum_length . ')' : ''),
                'default' => $row->column_default,
                'notnull' => $row->is_nullable == "YES"
            ];
        }
        return $columns;
    }
}
