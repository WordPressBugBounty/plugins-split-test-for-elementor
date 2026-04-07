<?php

namespace SplitTestForElementor\Classes\Database;

/**
 * Fluent query builder wrapping WordPress $wpdb with automatic prepared statements.
 *
 * The table name passed to table() is auto-prefixed with the WordPress table prefix.
 * Use QueryBuilder::prefix() to get the full prefixed name for JOIN conditions.
 *
 * Examples:
 *
 *   // SELECT – all rows
 *   QueryBuilder::table('elementor_splittest')->where('active', true)->get();
 *
 *   // SELECT – first match
 *   QueryBuilder::table('elementor_splittest')->where('id', $id)->first();
 *
 *   // WHERE IN
 *   QueryBuilder::table('elementor_splittest')->whereIn('id', $ids)->get();
 *
 *   // INSERT – returns new row id
 *   QueryBuilder::table('elementor_splittest')->insert(['name' => 'Test', 'active' => true]);
 *
 *   // UPDATE
 *   QueryBuilder::table('elementor_splittest')->where('id', $id)->update(['name' => 'New']);
 *
 *   // DELETE
 *   QueryBuilder::table('elementor_splittest')->where('id', $id)->delete();
 *
 *   // JOIN – use QueryBuilder::prefix() for fully-qualified table names in conditions
 *   $postsTable    = QueryBuilder::prefix('posts');
 *   $postTestTable = QueryBuilder::prefix('elementor_splittest_post');
 *   QueryBuilder::table('elementor_splittest_post')
 *       ->join($postsTable, "{$postTestTable}.post_id", '=', "{$postsTable}.ID")
 *       ->where('splittest_id', $testId)
 *       ->get();
 */
class RSTQueryBuilder {

    /** @var \wpdb */
    private $wpdb;

    /** @var string Fully-prefixed table name */
    private $table;

    /** @var string[] */
    private $selects = ['*'];

    /** @var array  Each entry: [column, operator, value] */
    private $wheres = [];

    /** @var array  Each entry: [column, values[]] */
    private $whereIns = [];

    /** @var array  Each entry: [raw_sql, bindings[]] */
    private $rawWheres = [];

    /** @var string[] Raw INNER JOIN clauses */
    private $joins = [];

    /** @var string[] */
    private $groupBys = [];

    /** @var string[] */
    private $orderBys = [];

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    // =========================================================================
    // Entry points
    // =========================================================================

    /**
     * Start a query for the given table.
     * The table name is automatically prefixed with the WordPress table prefix.
     */
    public static function table(string $name): self {
        $instance        = new self();
        $instance->table = $instance->wpdb->prefix . $name;
        return $instance;
    }

    /**
     * Return the fully-prefixed name for a table.
     * Useful when constructing JOIN conditions or raw WHERE expressions.
     */
    public static function prefix(string $name): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    // =========================================================================
    // Clause builders – all return $this for fluent chaining
    // =========================================================================

    /**
     * Set the columns to SELECT.
     * Accepts a single string, multiple strings, or an array.
     * Raw SQL expressions (e.g. 'COUNT(*) as count') are passed through unchanged.
     *
     *   ->select('*')
     *   ->select('id', 'name')
     *   ->select(['COUNT(*) as count', 'variation_id'])
     */
    public function select($columns): self {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add a WHERE condition. The format placeholder (%d / %s / %f) is inferred
     * automatically from the PHP type of the value.
     *
     *   ->where('id', 5)               ⟹  id = %d
     *   ->where('type', '!=', 'view')  ⟹  type != %s
     *   ->where('active', true)        ⟹  active = %d  (treated as 1)
     */
    public function where(string $column, $operatorOrValue, $value = null): self {
        if ($value === null) {
            $this->wheres[] = [$column, '=', $operatorOrValue];
        } else {
            $this->wheres[] = [$column, $operatorOrValue, $value];
        }
        return $this;
    }

    /**
     * Add a WHERE IN condition.
     *
     *   ->whereIn('id', [1, 2, 3])  ⟹  id IN (%d, %d, %d)
     */
    public function whereIn(string $column, array $values): self {
        if (!empty($values)) {
            $this->whereIns[] = [$column, $values];
        }
        return $this;
    }

    /**
     * Add a raw WHERE fragment. Bindings are passed through $wpdb->prepare().
     *
     *   ->whereRaw('active IS TRUE')
     *   ->whereRaw("test_type = '' OR test_type IS NULL")
     *   ->whereRaw('created_at > %s', ['2024-01-01'])
     */
    public function whereRaw(string $sql, array $bindings = []): self {
        $this->rawWheres[] = [$sql, $bindings];
        return $this;
    }

    /** Add a WHERE column IS NULL condition. */
    public function whereNull(string $column): self {
        return $this->whereRaw("{$column} IS NULL");
    }

    /** Add a WHERE column IS NOT NULL condition. */
    public function whereNotNull(string $column): self {
        return $this->whereRaw("{$column} IS NOT NULL");
    }

    /**
     * Add an INNER JOIN clause.
     * Pass the fully-qualified (prefixed) table name for the joined table.
     *
     *   $posts = QueryBuilder::prefix('posts');
     *   $mine  = QueryBuilder::prefix('elementor_splittest_post');
     *   ->join($posts, "{$mine}.post_id", '=', "{$posts}.ID")
     */
    public function join(string $table, string $localColumn, string $operator, string $foreignColumn): self {
        $this->joins[] = "INNER JOIN {$table} ON {$localColumn} {$operator} {$foreignColumn}";
        return $this;
    }

    /**
     * Add one or more GROUP BY columns.
     *
     *   ->groupBy('type', 'variation_id', 'date')
     */
    public function groupBy(string ...$columns): self {
        $this->groupBys = array_merge($this->groupBys, $columns);
        return $this;
    }

    /**
     * Add an ORDER BY column.
     *
     *   ->orderBy('created_at', 'DESC')
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        // Only allow safe column name characters (letters, digits, underscores, dots for table.column)
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
            return $this;
        }
        $dir              = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBys[] = "{$column} {$dir}";
        return $this;
    }

    // =========================================================================
    // Execution
    // =========================================================================

    /**
     * Execute a SELECT and return all matching rows as stdClass objects.
     *
     * @return \stdClass[]
     */
    public function get(): array {
        [$sql, $bindings] = $this->buildSelectSql();
        $sql = $this->maybePrep($sql, $bindings);
        return $this->wpdb->get_results($sql, OBJECT) ?: [];
    }

    /**
     * Execute a SELECT and return the first row, or null if none found.
     */
    public function first(): ?\stdClass
	{
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Execute an INSERT and return the new row's ID.
     * Format placeholders are inferred automatically from PHP value types.
     *
     * @param  array $data  column => value pairs
     * @return int          The insert ID (0 on failure)
     */
    public function insert(array $data): int {
        $formats = array_map([$this, 'inferFormat'], array_values($data));
        $this->wpdb->insert($this->table, $data, $formats);
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Execute an UPDATE for rows matching the current WHERE conditions.
     * Null values are written as SQL NULL without a placeholder.
     * Format placeholders for non-null values are inferred automatically.
     *
     * @param  array $data  column => value pairs
     * @return bool         True on success (including 0 rows affected), false on DB error
     */
    public function update(array $data): bool {
        [$whereSql, $whereBindings] = $this->buildWherePart();

        $setParts    = [];
        $setBindings = [];
        foreach ($data as $column => $value) {
            if ($value === null) {
                $setParts[] = "{$column} = NULL";
            } else {
                $setParts[]    = "{$column} = " . $this->inferFormat($value);
                $setBindings[] = $value;
            }
        }

        $sql      = "UPDATE {$this->table} SET " . implode(', ', $setParts);
        $bindings = array_merge($setBindings, $whereBindings);

        if (!empty($whereSql)) {
            $sql .= " WHERE {$whereSql}";
        }

        return $this->wpdb->query($this->maybePrep($sql, $bindings)) !== false;
    }

    /**
     * Execute a DELETE for rows matching the current WHERE conditions.
     *
     * @return bool  True on success, false on DB error
     */
    public function delete(): bool {
        [$whereSql, $bindings] = $this->buildWherePart();

        $sql = "DELETE FROM {$this->table}";
        if (!empty($whereSql)) {
            $sql .= " WHERE {$whereSql}";
        }

        return $this->wpdb->query($this->maybePrep($sql, $bindings)) !== false;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the full SELECT SQL and return it together with all bindings.
     *
     * @return array{string, array}
     */
    private function buildSelectSql(): array {
        [$whereSql, $bindings] = $this->buildWherePart();

        $sql  = 'SELECT ' . implode(', ', $this->selects);
        $sql .= " FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " {$join}";
        }
        if (!empty($whereSql)) {
            $sql .= " WHERE {$whereSql}";
        }
        if (!empty($this->groupBys)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }
        if (!empty($this->orderBys)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBys);
        }

        return [$sql, $bindings];
    }

    /**
     * Compile all WHERE conditions into a SQL fragment and a flat bindings array.
     * Conditions from where(), whereIn() and whereRaw() are AND-joined.
     *
     * @return array{string, array}  [where_sql, bindings]
     */
    private function buildWherePart(): array {
        $parts    = [];
        $bindings = [];

        foreach ($this->wheres as $cond) {
            list($column, $op, $value) = $cond;
            $parts[]    = "{$column} {$op} " . $this->inferFormat($value);
            $bindings[] = $value;
        }

        foreach ($this->whereIns as $in) {
            list($column, $values) = $in;
            $placeholders = implode(', ', array_map([$this, 'inferFormat'], $values));
            $parts[]      = "{$column} IN ({$placeholders})";
            $bindings     = array_merge($bindings, $values);
        }

        foreach ($this->rawWheres as $raw) {
            list($rawSql, $rawBindings) = $raw;
            $parts[]  = $rawSql;
            $bindings = array_merge($bindings, $rawBindings);
        }

        return [implode(' AND ', $parts), $bindings];
    }

    /**
     * Call $wpdb->prepare() only when there are bindings.
     * Avoids the "missing argument" notice that prepare() emits on empty input.
     */
    private function maybePrep(string $sql, array $bindings): string {
        if (empty($bindings)) {
            return $sql;
        }
        return $this->wpdb->prepare($sql, $bindings);
    }

    /**
     * Map a PHP value to the appropriate wpdb format placeholder.
     *
     *   int / bool  → %d
     *   float       → %f
     *   everything else → %s  (null is handled separately in update())
     */
    private function inferFormat($value): string {
        if (is_int($value) || is_bool($value)) {
            return '%d';
        }
        if (is_float($value)) {
            return '%f';
        }
        return '%s';
    }
}
