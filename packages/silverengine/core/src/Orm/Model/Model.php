<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use Silver\Orm\Cache\CacheStore;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;

/**
 * Active Record base. User models extend this and declare their
 * shape through typed public properties + PHP attributes
 * (#[Table], #[PrimaryKey], #[Cast], #[Hidden], #[Fillable], #[Guarded])
 * and class-level concerns (#[Timestamps], #[SoftDeletes], #[ObservedBy]).
 *
 * The class itself is intentionally minimal — discovery, casting,
 * dirty tracking and event dispatch all live in dedicated services
 * so each piece can be unit-tested in isolation.
 *
 * @phpstan-consistent-constructor User models must keep the no-arg
 * constructor that Model::create() / Hydrator depend on.
 */
abstract class Model implements JsonSerializable
{
    private static ?ConnectionManager $cm           = null;
    private static ?Compiler          $compiler     = null;
    private static ?EventDispatcher   $events       = null;
    /** @var \Closure(): bool */
    private static ?\Closure          $lazyAllowed  = null;

    // ---- Class-level array shortcuts (alternative to per-property
    // attributes). Subclasses override these; the AttributeRegistry
    // unions them with any attribute-tagged properties.
    //
    //   protected static array $fillable   = ['email', 'name'];
    //   protected static array $hidden     = ['password', 'salt'];
    //   protected static array $searchable = ['email', 'name'];
    //   protected static array $guarded    = ['id'];        // always last word
    //
    /** @var list<string> */ protected static array $fillable    = [];
    /** @var list<string> */ protected static array $editable    = [];
    /** @var list<string> */ protected static array $hidden      = [];
    /** @var list<string> */ protected static array $guarded     = [];
    /** @var list<string> */ protected static array $searchable  = [];
    /** @var list<string> */ protected static array $defer       = [];
    /** @var list<string> */ protected static array $replicateTo = [];

    /**
     * Virtual / appended attributes — names that surface in
     * toArray() / jsonSerialize() but are not backed by a DB column.
     * Resolved per-instance via getFooBarAttribute() methods, OR
     * set ad-hoc via {@see appendAttribute()}.
     *
     * @var list<string>
     */
    protected static array $appends = [];

    /**
     * Connection-name overrides for this model. Default → use the
     * ConnectionManager's current default. The split form lets a
     * model write to the primary and read from a replica.
     */
    protected static ?string $connection      = null;
    protected static ?string $readConnection  = null;
    protected static ?string $writeConnection = null;

    /** @var array<class-string, true> */
    private static array $booted = [];

    /**
     * Per-model repository binding. Populated either via
     * #[UseRepository(...)] attribute or {@see useRepository()}.
     *
     * @var array<class-string, class-string<Repository>>
     */
    private static array $repositories = [];

    /** Original DB-shaped row, captured on hydrate. Used for ->isDirty()/dirty diff. */
    private array $original = [];
    /** Extra columns from JOINs that don't map to declared properties. */
    private array $extras   = [];
    /** @var array<string, mixed> Eager-loaded relation results, keyed by relation name. */
    private array $loadedRelations = [];
    /**
     * Per-instance virtual attributes set via {@see appendAttribute()}.
     * Layered ON TOP of the class-level static $appends + accessor methods.
     *
     * @var array<string, mixed>
     */
    private array $virtualAttributes = [];
    private bool  $exists   = false;

    // ---------- bootstrapping ----------

    public static function bind(ConnectionManager $cm, ?EventDispatcher $events = null): void
    {
        self::$cm       = $cm;
        self::$compiler = new Compiler($cm);
        self::$events   = $events ?? new EventDispatcher();
    }

    public static function connections(): ConnectionManager
    {
        return self::$cm ?? throw new \LogicException(
            'Model::bind(ConnectionManager) must be called before any model operation.'
        );
    }

    private static function events(): EventDispatcher
    {
        return self::$events ?? new EventDispatcher();
    }

    private static function compiler(): Compiler
    {
        return self::$compiler ??= new Compiler(self::connections());
    }

    // ---------- metadata accessors ----------

    public static function metadata(): ModelMetadata
    {
        return AttributeRegistry::for(static::class);
    }

    public static function table(): string
    {
        return static::metadata()->table;
    }

    public static function primaryKey(): string
    {
        return static::metadata()->primaryKey
            ?? throw new \LogicException(static::class . ' has no primary key declared.');
    }

    // ---------- public hydrator hooks (called by Hydrator) ----------

    public function setOriginal(array $row): void { $this->original = $row; }
    public function getOriginal(?string $column = null): mixed
    {
        if ($column === null) {
            return $this->original;
        }
        return $this->original[$column] ?? null;
    }
    public function setExtra(string $key, mixed $value): void { $this->extras[$key] = $value; }
    public function extras(): array { return $this->extras; }
    public function markExists(bool $on): void { $this->exists = $on; }
    public function exists(): bool { return $this->exists; }

    // ---------- relation hooks (Hydrator + Eager loader use these) ----------

    public function setLoadedRelation(string $name, mixed $value): void
    {
        $this->loadedRelations[$name] = $value;
    }

    public function getLoadedRelation(string $name): mixed
    {
        return $this->loadedRelations[$name] ?? null;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->loadedRelations);
    }

    // ---------- relation factories (used inside model method definitions) ----------

    /** @param class-string<Model> $related */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): \Silver\Orm\Relations\HasOne
    {
        return new \Silver\Orm\Relations\HasOne(
            $this,
            $related,
            $foreignKey ?? \Silver\Orm\Relations\Relation::defaultForeignKey(static::class),
            $localKey   ?? \Silver\Orm\Relations\Relation::defaultLocalKey(static::class),
        );
    }

    /** @param class-string<Model> $related */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): \Silver\Orm\Relations\HasMany
    {
        return new \Silver\Orm\Relations\HasMany(
            $this,
            $related,
            $foreignKey ?? \Silver\Orm\Relations\Relation::defaultForeignKey(static::class),
            $localKey   ?? \Silver\Orm\Relations\Relation::defaultLocalKey(static::class),
        );
    }

    /** @param class-string<Model> $related */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): \Silver\Orm\Relations\BelongsTo
    {
        $fk = $foreignKey ?? \Silver\Orm\Relations\Relation::defaultForeignKey($related);
        $ok = $ownerKey   ?? \Silver\Orm\Relations\Relation::defaultLocalKey($related);
        return new \Silver\Orm\Relations\BelongsTo($this, $related, $fk, $ok);
    }

    /** @param class-string<Model> $related */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable      = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey       = null,
        ?string $relatedKey      = null,
    ): \Silver\Orm\Relations\BelongsToMany {
        $parentBase  = substr(static::class, (int) strrpos(static::class, '\\') + 1);
        $relatedBase = substr($related,       (int) strrpos($related,       '\\') + 1);

        $sortedPair  = [strtolower($parentBase), strtolower($relatedBase)];
        sort($sortedPair);
        $defaultPivotTable = implode('_', $sortedPair);

        return new \Silver\Orm\Relations\BelongsToMany(
            $this,
            $related,
            $pivotTable      ?? $defaultPivotTable,
            $foreignPivotKey ?? \Silver\Orm\Relations\Relation::defaultForeignKey(static::class),
            $relatedPivotKey ?? \Silver\Orm\Relations\Relation::defaultForeignKey($related),
            $parentKey       ?? \Silver\Orm\Relations\Relation::defaultLocalKey(static::class),
            $relatedKey      ?? \Silver\Orm\Relations\Relation::defaultLocalKey($related),
        );
    }

    /**
     * @param class-string<Model> $related
     * @param class-string<Model> $through
     */
    protected function hasManyThrough(
        string  $related,
        string  $through,
        ?string $firstKey       = null,
        ?string $secondKey      = null,
        ?string $localKey       = null,
        ?string $secondLocalKey = null,
    ): \Silver\Orm\Relations\HasManyThrough {
        return new \Silver\Orm\Relations\HasManyThrough(
            $this,
            $related,
            $through,
            $firstKey       ?? \Silver\Orm\Relations\Relation::defaultForeignKey(static::class),
            $secondKey      ?? \Silver\Orm\Relations\Relation::defaultForeignKey($through),
            $localKey       ?? \Silver\Orm\Relations\Relation::defaultLocalKey(static::class),
            $secondLocalKey ?? \Silver\Orm\Relations\Relation::defaultLocalKey($through),
        );
    }

    /** @param class-string<Model> $related */
    protected function morphOne(string $related, string $morph, ?string $localKey = null): \Silver\Orm\Relations\Morph\MorphOne
    {
        return new \Silver\Orm\Relations\Morph\MorphOne(
            $this,
            $related,
            $morph,
            $localKey ?? \Silver\Orm\Relations\Relation::defaultLocalKey(static::class),
        );
    }

    /** @param class-string<Model> $related */
    protected function morphMany(string $related, string $morph, ?string $localKey = null): \Silver\Orm\Relations\Morph\MorphMany
    {
        return new \Silver\Orm\Relations\Morph\MorphMany(
            $this,
            $related,
            $morph,
            $localKey ?? \Silver\Orm\Relations\Relation::defaultLocalKey(static::class),
        );
    }

    protected function morphTo(string $morph): \Silver\Orm\Relations\Morph\MorphTo
    {
        return new \Silver\Orm\Relations\Morph\MorphTo($this, $morph);
    }

    /**
     * Property access for relations: returns the eager-loaded value
     * if present; otherwise throws LazyLoadingViolation. Real
     * (typed) properties are short-circuited by PHP's normal
     * property resolution and never reach this magic.
     */
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->loadedRelations)) {
            return $this->loadedRelations[$name];
        }
        // Is it a relation method on the subclass? If so, complain.
        if (method_exists($this, $name)) {
            throw \Silver\Orm\Relations\LazyLoadingViolation::for(static::class, $name);
        }
        // Fall through: extras (join projections) are addressable.
        if (array_key_exists($name, $this->extras)) {
            return $this->extras[$name];
        }
        throw new \BadMethodCallException(
            sprintf('%s::$%s is not defined.', static::class, $name),
        );
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->loadedRelations)
            || array_key_exists($name, $this->extras);
    }

    // ---------- queries ----------

    /**
     * Fresh ModelBuilder for this class. Has the model's read connection,
     * default-deferred column projection, and soft-delete scope already
     * applied.
     */
    public static function query(): ModelBuilder
    {
        static::bootIfNeeded();

        $b = new ModelBuilder(self::connections(), self::compiler(), null, static::class);
        $b->from(static::table());

        $read = static::readConnection();
        if ($read !== null) {
            $b->onConnection($read);
        }

        // Apply default-deferred column exclusion via an explicit
        // column list (the grammar picks * when select is empty).
        $deferred = static::deferred();
        if ($deferred !== []) {
            $cols = [];
            foreach (static::metadata()->properties as $name => $_) {
                if (!in_array($name, $deferred, true)) {
                    $cols[] = $name;
                }
            }
            $b->select($cols !== [] ? $cols : ['*']);
        }

        // Soft-delete scope: filter out trashed rows by default.
        $meta = static::metadata();
        if ($meta->hasSoftDeletes()) {
            $b->whereNull($meta->softDeletes->column);
        }

        // Global scopes are deferred — registered on the builder, then
        // applied just before the terminal runs. That way
        // ->withoutGlobalScope() can opt out before SQL is built.
        foreach ($meta->globalScopes as $scopeClass) {
            $b->registerGlobalScope($scopeClass);
        }

        return $b;
    }

    /**
     * Per-class boot hook. Override on subclasses to register
     * listeners, scopes, casts, etc. Runs exactly once per class
     * per process — guarded by self::$booted.
     */
    protected static function boot(): void
    {
        // no-op by default
    }

    public static function bootIfNeeded(): void
    {
        if (isset(self::$booted[static::class])) {
            return;
        }
        self::$booted[static::class] = true;
        static::boot();
    }

    /** Test helper — drop the boot guard so re-running tests is clean. */
    public static function unboot(): void
    {
        unset(self::$booted[static::class]);
    }

    public static function find(mixed $id): ?static
    {
        /** @var ?static $found */
        $found = ModelCache::rememberPk(
            static::class,
            $id,
            fn () => static::query()->where(static::primaryKey(), $id)->first(),
        );
        return $found;
    }

    /**
     * Bind a cross-request cache store for this model. The
     * request-scoped identity-map layer (ArrayStore) is always on
     * and doesn't need to be configured.
     */
    public static function useCache(CacheStore $store, int $ttl = 60): void
    {
        ModelCache::use(static::class, $store, $ttl);
    }

    /** Drop every cache entry for this model (both identity + persistent). */
    public static function flushCache(): void
    {
        ModelCache::forgetAll(static::class);
    }

    public static function findOrFail(mixed $id): static
    {
        $found = static::find($id);
        if ($found === null) {
            throw ModelNotFound::for(static::class, $id);
        }
        return $found;
    }

    /** @return list<static> */
    public static function all(): array
    {
        /** @var list<static> $rows */
        $rows = static::query()->all();
        return $rows;
    }

    public static function where(string $column, mixed $op, mixed $value = null): Builder
    {
        return static::query()->where($column, $op, $value);
    }

    /** @param array<string, mixed> $values */
    public static function create(array $values): static
    {
        $model = new static();
        $model->fill($values);
        $model->save();
        return $model;
    }

    // ---------- mass assignment ----------

    /** @param array<string, mixed> $values */
    public function fill(array $values): static
    {
        $meta     = static::metadata();
        $fillable = static::fillable();
        $guarded  = static::guarded();

        foreach ($values as $key => $value) {
            $prop = $meta->properties[$key] ?? null;
            if ($prop === null) {
                continue;
            }
            if (in_array($key, $guarded, true)) {
                continue;
            }
            if (!in_array($key, $fillable, true)) {
                continue; // deny-by-default
            }
            $this->{$key} = $value;
        }
        return $this;
    }

    // ---------- persistence ----------

    public function save(): static
    {
        $cm   = self::connections();
        $meta = static::metadata();

        if ($this->exists) {
            return $this->performUpdate($cm, $meta);
        }
        return $this->performInsert($cm, $meta);
    }

    private function performInsert(ConnectionManager $cm, ModelMetadata $meta): static
    {
        self::events()->dispatch('creating', $this);

        // Stamp timestamps.
        if ($meta->hasTimestamps()) {
            $now = self::nowUtc();
            $this->setIfMissing($meta->timestamps->createdAt, $now);
            $this->setIfMissing($meta->timestamps->updatedAt, $now);
        }

        $row     = $this->toRow();
        $builder = $this->writer();
        $id      = $builder->insertGetId($row);

        if ($meta->primaryKey !== null && $meta->primaryKeyIncrementing
            && ($this->{$meta->primaryKey} ?? null) === null
            && $id !== '') {
            $idValue = is_numeric($id) ? (int) $id : $id;
            $this->{$meta->primaryKey} = $idValue;
            $row[$meta->primaryKey]    = $idValue;
        }

        $this->exists   = true;
        $this->original = $row;

        $this->replicateRow('insert', $row);

        // New row: tag-bust to invalidate any list-style cached queries,
        // then re-seed the identity map with THIS instance so
        // subsequent find($pk) in the same request returns it.
        ModelCache::forgetAll(static::class);
        if ($meta->primaryKey !== null) {
            $pkVal = $this->primaryKeyValue();
            if ($pkVal !== null) {
                ModelCache::identity()->set(
                    ModelCache::pkKey(static::class, $pkVal),
                    $this,
                    0,
                    [ModelCache::tag(static::class)],
                );
            }
        }

        self::events()->dispatch('created', $this);
        return $this;
    }

    private function performUpdate(ConnectionManager $cm, ModelMetadata $meta): static
    {
        $dirty    = $this->dirty();
        $editable = static::editable();
        if ($editable !== []) {
            $dirty = array_intersect_key($dirty, array_flip($editable));
        }
        if ($dirty === []) {
            return $this;
        }

        self::events()->dispatch('updating', $this);

        if ($meta->hasTimestamps()) {
            $now = self::nowUtc();
            $col = $meta->timestamps->updatedAt;
            $this->{$col} = $now;
            $dirty[$col]  = $this->columnValue($col);
        }

        $builder = $this->writer()->where(self::primaryKey(), $this->primaryKeyValue());
        $builder->update($dirty);

        // Refresh dirty baseline.
        foreach ($dirty as $col => $val) {
            $this->original[$col] = $val;
        }

        $this->replicateRow('update', $dirty);

        // Targeted bust: only this PK.
        ModelCache::forgetPk(static::class, $this->primaryKeyValue());

        self::events()->dispatch('updated', $this);
        return $this;
    }

    public function delete(): bool
    {
        $meta = static::metadata();
        self::events()->dispatch('deleting', $this);

        if ($meta->hasSoftDeletes()) {
            $col = $meta->softDeletes->column;
            $this->{$col} = self::nowUtc();
            $pkValue = $this->primaryKeyValue();
            $this->writer()
                ->where(self::primaryKey(), $pkValue)
                ->update([$col => $this->columnValue($col)]);
            $this->original[$col] = $this->columnValue($col);
            ModelCache::forgetPk(static::class, $pkValue);
        } else {
            $this->forceDelete();
            return true;
        }

        self::events()->dispatch('deleted', $this);
        return true;
    }

    public function forceDelete(): bool
    {
        self::events()->dispatch('deleting', $this);
        $pkValue = $this->primaryKeyValue();
        $deleted = (new ModelBuilder(self::connections(), self::compiler(), null, static::class))
            ->from(static::table())
            ->where(self::primaryKey(), $pkValue)
            ->delete();

        // Replicate the delete to mirror tables.
        $this->replicateDelete($pkValue);

        ModelCache::forgetPk(static::class, $pkValue);

        $this->exists = false;
        self::events()->dispatch('deleted', $this);
        return $deleted > 0;
    }

    /**
     * Mirror an insert/update into each table listed in
     * static::$replicateTo. Uses the same connection — cross-DB
     * mirroring belongs in a CDC/queue layer, not the ORM.
     *
     * @param array<string, mixed> $row
     */
    private function replicateRow(string $op, array $row): void
    {
        $targets = static::replicates();
        if ($targets === []) {
            return;
        }
        $meta = static::metadata();
        foreach ($targets as $table) {
            $b = (new Builder(self::connections(), self::compiler()))->from($table);
            if ($op === 'insert') {
                $b->insert($row);
            } else {
                // Update by primary key value (assumed schema-shared).
                $pk = $this->primaryKeyValue();
                $b->where($meta->primaryKey ?? 'id', $pk)->update($row);
            }
        }
    }

    private function replicateDelete(mixed $pkValue): void
    {
        $targets = static::replicates();
        if ($targets === []) {
            return;
        }
        $meta = static::metadata();
        foreach ($targets as $table) {
            (new Builder(self::connections(), self::compiler()))
                ->from($table)
                ->where($meta->primaryKey ?? 'id', $pkValue)
                ->delete();
        }
    }

    /**
     * Save THIS model into the ordered list right before $anchor — the
     * shared ordering column (default: `position`) of $anchor and every
     * row at >= that position is bumped by one to make room.
     *
     * Wraps the shift + insert in a transaction so a crash leaves no
     * duplicate positions.
     */
    public function insertBefore(self $anchor, string $orderColumn = 'position'): static
    {
        return $this->insertPositioned($anchor, $orderColumn, before: true);
    }

    /**
     * Save THIS model into the ordered list right after $anchor.
     */
    public function insertAfter(self $anchor, string $orderColumn = 'position'): static
    {
        return $this->insertPositioned($anchor, $orderColumn, before: false);
    }

    private function insertPositioned(self $anchor, string $orderColumn, bool $before): static
    {
        if (!isset($anchor->{$orderColumn})) {
            throw new \LogicException(
                sprintf('Anchor %s has no value in $%s — cannot insert relative to it.', $anchor::class, $orderColumn),
            );
        }
        $anchorPos = (int) $anchor->{$orderColumn};
        $newPos    = $before ? $anchorPos : $anchorPos + 1;

        $cm = self::connections();
        $tx = new \Silver\Orm\Connection\TransactionManager($cm);

        $tx->run(function () use ($newPos, $orderColumn): void {
            // Shift every row at >= $newPos by +1 to make a hole.
            $cm      = self::connections();
            $driver  = $cm->driver(static::writeConnection());
            $shiftBy = new \Silver\Orm\Query\Node\Raw(
                $driver->quoteIdentifier($orderColumn) . ' + 1',
            );
            $this->writer()
                ->where($orderColumn, '>=', $newPos)
                ->update([$orderColumn => $shiftBy]);

            $this->{$orderColumn} = $newPos;
            $this->save();
        }, name: static::writeConnection());

        return $this;
    }

    public function fresh(): ?static
    {
        $pk = $this->primaryKeyValue();
        if ($pk === null) {
            return null;
        }
        return static::find($pk);
    }

    public function refresh(): static
    {
        $other = $this->fresh();
        if ($other === null) {
            return $this;
        }
        foreach (static::metadata()->properties as $name => $_) {
            if (isset($other->{$name})) {
                $this->{$name} = $other->{$name};
            }
        }
        $this->original = $other->original;
        return $this;
    }

    // ---------- dirty / row projection ----------

    public function isDirty(?string $column = null): bool
    {
        $dirty = $this->dirty();
        return $column === null ? $dirty !== [] : array_key_exists($column, $dirty);
    }

    /** @return array<string, mixed> Column → casted-back-to-DB value, only fields that changed. */
    public function dirty(): array
    {
        $out  = [];
        $meta = static::metadata();
        foreach ($meta->properties as $name => $prop) {
            if (!isset($this->{$name})) {
                continue;
            }
            $value = $this->columnValue($name);
            if (!array_key_exists($name, $this->original) || $this->original[$name] !== $value) {
                $out[$name] = $value;
            }
        }
        return $out;
    }

    /** @return array<string, mixed> All declared properties projected to DB-shape. */
    private function toRow(): array
    {
        $out  = [];
        $meta = static::metadata();
        foreach ($meta->properties as $name => $prop) {
            if (!isset($this->{$name})) {
                continue;
            }
            $out[$name] = $this->columnValue($name);
        }
        return $out;
    }

    private function columnValue(string $name): mixed
    {
        $prop = static::metadata()->properties[$name] ?? null;
        $val  = $this->{$name};
        return $prop?->cast !== null ? $prop->cast->set($val) : $val;
    }

    private function setIfMissing(string $prop, mixed $value): void
    {
        if (!isset($this->{$prop})) {
            $this->{$prop} = $value;
        }
    }

    private function primaryKeyValue(): mixed
    {
        $pk = self::primaryKey();
        return $this->{$pk} ?? null;
    }

    /**
     * Build a fresh write-targeted builder (no soft-delete scope) — routed
     * through static::writeConnection() so a model that splits read/write
     * sends mutations to the primary instead of a replica.
     */
    private function writer(): Builder
    {
        $b = (new Builder(self::connections(), self::compiler()))->from(static::table());
        $write = static::writeConnection();
        if ($write !== null) {
            $b->onConnection($write);
        }
        return $b;
    }

    private static function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    // ---------- array-form shortcuts ----------

    /**
     * Effective fillable list: union of #[Fillable] attributes and
     * the static $fillable array. Used by fill().
     *
     * @return list<string>
     */
    public static function fillable(): array
    {
        $meta = static::metadata();
        return array_values(array_unique(array_merge($meta->fillable, static::$fillable)));
    }

    /** @return list<string> */
    public static function hidden(): array
    {
        $meta = static::metadata();
        return array_values(array_unique(array_merge($meta->hidden, static::$hidden)));
    }

    /** @return list<string> */
    public static function guarded(): array
    {
        $meta = static::metadata();
        return array_values(array_unique(array_merge($meta->guarded, static::$guarded)));
    }

    /**
     * Columns allowed to change on UPDATE (after the row exists).
     * If empty, every dirty column is sent through — equivalent to
     * "no extra restriction beyond $fillable / $hidden".
     *
     * @return list<string>
     */
    public static function editable(): array
    {
        return static::$editable;
    }

    /**
     * Columns used by the `search()` convenience scope — LIKE-matched
     * across the listed columns with OR semantics. Set via the
     * static $searchable array on the subclass.
     *
     * @return list<string>
     */
    public static function searchable(): array
    {
        return static::$searchable;
    }

    /**
     * Columns excluded from the default SELECT list. Useful for
     * large blobs (avatar bytes, raw HTML, attachments) — the
     * column is still queryable explicitly via select() but won't
     * load on Model::find() / Model::all().
     *
     * @return list<string>
     */
    public static function deferred(): array
    {
        return static::$defer;
    }

    /**
     * Sibling tables to mirror every write into (insert/update/
     * delete on the primary table is replayed against each entry).
     * Empty = no replication. Same connection only; cross-connection
     * replication belongs to a CDC/queue layer, not the ORM.
     *
     * @return list<string>
     */
    public static function replicates(): array
    {
        return static::$replicateTo;
    }

    public static function readConnection(): ?string
    {
        return static::$readConnection ?? static::$connection;
    }

    public static function writeConnection(): ?string
    {
        return static::$writeConnection ?? static::$connection;
    }

    /**
     * Convenience scope: search() applies an OR-LIKE filter across
     * the columns named in static::$searchable. Returns the builder
     * for further chaining.
     */
    public static function search(string $needle, ?string $pattern = null): Builder
    {
        $columns = static::searchable();
        $b       = static::query();
        if ($columns === [] || $needle === '') {
            return $b;
        }
        $like = $pattern ?? '%' . $needle . '%';
        $b->where(function (Builder $q) use ($columns, $like): void {
            foreach ($columns as $i => $col) {
                if ($i === 0) {
                    $q->where($col, 'LIKE', $like);
                } else {
                    $q->orWhere($col, 'LIKE', $like);
                }
            }
        });
        return $b;
    }

    // ---------- repository binding ----------

    /**
     * Programmatic repository binding. Equivalent to tagging the
     * model class with #[UseRepository(SomeRepo::class)] except
     * it's resolved at runtime so tests can swap implementations.
     *
     * @param class-string<Repository> $repositoryClass
     */
    public static function useRepository(string $repositoryClass): void
    {
        self::$repositories[static::class] = $repositoryClass;
    }

    /** @return class-string<Repository>|null */
    public static function repositoryClass(): ?string
    {
        if (isset(self::$repositories[static::class])) {
            return self::$repositories[static::class];
        }
        // Discovered via #[UseRepository] / #[RepositoryAttribute].
        $rc = new \ReflectionClass(static::class);
        foreach ([\Silver\Orm\Attributes\UseRepository::class, \Silver\Orm\Attributes\RepositoryAttribute::class] as $attr) {
            $tags = $rc->getAttributes($attr);
            if ($tags !== []) {
                /** @var object{class: class-string<Repository>} $inst */
                $inst = $tags[0]->newInstance();
                self::$repositories[static::class] = $inst->class;
                return $inst->class;
            }
        }
        return null;
    }

    public static function repository(): ?Repository
    {
        $cls = static::repositoryClass();
        if ($cls === null) {
            return null;
        }
        /** @var Repository $instance Container/new() resolves to Repository because $cls is class-string<Repository>. */
        $instance = function_exists('app') ? app($cls) : new $cls();
        $instance->bindModel(static::class);
        return $instance;
    }

    /**
     * Forward unknown static calls to the repository if one is
     * registered. Lets you write `User::findByEmail($e)` and have it
     * land on `UserRepository::findByEmail($e)`.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        $repo = static::repository();
        if ($repo !== null && method_exists($repo, $name)) {
            return $repo->{$name}(...$arguments);
        }
        throw new \BadMethodCallException(
            sprintf('Static method %s::%s does not exist (and no repository handles it).', static::class, $name),
        );
    }

    // ---------- serialization ----------

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out    = [];
        $meta   = static::metadata();
        $hidden = static::hidden();
        foreach ($meta->properties as $name => $prop) {
            if (in_array($name, $hidden, true)) {
                continue;
            }
            if (!isset($this->{$name})) {
                continue;
            }
            $out[$name] = $this->{$name};
        }

        // Layer virtual attributes on top:
        //   1. accessor-backed appends: $appends = ['full_name']
        //      → getFullNameAttribute() returns the value
        //   2. per-instance ad-hoc via appendAttribute()
        foreach (static::$appends as $name) {
            if (in_array($name, $hidden, true)) {
                continue;
            }
            $value = $this->resolveAccessor($name);
            if ($value !== null) {
                $out[$name] = $value;
            }
        }
        foreach ($this->virtualAttributes as $k => $v) {
            if (in_array($k, $hidden, true)) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * Attach a one-off virtual attribute that surfaces only in
     * toArray()/jsonSerialize() output, never in the DB. Pair with
     * static $appends for class-level declarations.
     *
     *   $user->appendAttribute('avatar_url', cdn_url($user->avatar))
     *        ->appendAttribute('display_name', $user->name ?? $user->email);
     */
    public function appendAttribute(string $name, mixed $value): static
    {
        $this->virtualAttributes[$name] = $value;
        return $this;
    }

    public function withoutAttribute(string $name): static
    {
        unset($this->virtualAttributes[$name]);
        return $this;
    }

    /**
     * Resolve a getNameAttribute() accessor method for $name, if one
     * exists on this class. Returns null when no accessor matches.
     */
    protected function resolveAccessor(string $name): mixed
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
        $method = 'get' . $studly . 'Attribute';
        return method_exists($this, $method) ? $this->{$method}() : null;
    }

    public function jsonSerialize(): array
    {
        $out = [];
        foreach ($this->toArray() as $k => $v) {
            $out[$k] = $v instanceof DateTimeImmutable ? $v->format(DATE_ATOM) : $v;
        }
        return $out;
    }
}
