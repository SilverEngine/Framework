<?php
declare(strict_types=1);

namespace Silver\Core;

use PDO;

class Model extends Controller
{
    private ?PDO $db = null;

    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $filterable = [];
    protected array $includable = [];
    protected array $searchable = [];
    protected array $hidden = [];
    protected array $fillable = [];
    protected array $selectable = [];

    public function __construct()
    {
        $env = Env::get('database');

        if ($env) {
            $dsn = $env->driver . ':host=' . $env->hostname . ';dbname=' . $env->basename;
            $this->db = new PDO($dsn, $env->username, $env->password, [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
            ]);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    protected function select(string $table): array
    {
        $columns = implode(', ', $this->filterable);
        $sql = $columns !== '' ? "SELECT $columns FROM $table" : "SELECT * FROM $table";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function getPrimaryKey(): string { return $this->primaryKey; }
    public function getTable(): string { return $this->table; }
    public function getFilterable(): array { return $this->filterable; }
    public function getIncludable(): array { return $this->includable; }
    public function getSearchable(): array { return $this->searchable; }
    public function getHidden(): array { return $this->hidden; }
    public function getFillable(): array { return $this->fillable; }
    public function getSelectable(): array { return $this->selectable; }

    public function isSelectable(string $column): bool
    {
        return in_array($column, $this->selectable, true)
            && !in_array($column, $this->hidden, true);
    }
}
