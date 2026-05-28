<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Silver\Orm\Attributes\Cast;
use Silver\Orm\Attributes\Fillable;
use Silver\Orm\Attributes\GlobalScope;
use Silver\Orm\Attributes\Guarded;
use Silver\Orm\Attributes\Hidden;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Scope;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Casts\CastResolver;
use Silver\Orm\Casts\CastsAttribute;
use Silver\Orm\Concerns\ObservedBy;
use Silver\Orm\Concerns\SoftDeletes;
use Silver\Orm\Concerns\Timestamps;

/**
 * Reflects a model class once, caches the discovered metadata for
 * the rest of the request. Anything that wants to know the model's
 * shape (Hydrator, Builder, mass-assignment guard, event dispatcher)
 * goes through here.
 */
final class AttributeRegistry
{
    /** @var array<class-string, ModelMetadata> */
    private static array $cache = [];

    /** @param class-string $class */
    public static function for(string $class): ModelMetadata
    {
        return self::$cache[$class] ??= self::build($class);
    }

    /** @param class-string $class */
    private static function build(string $class): ModelMetadata
    {
        $rc = new ReflectionClass($class);

        // ---------- class-level attributes ----------
        $tableAttr      = self::firstAttr($rc->getAttributes(Table::class));
        $timestampsAttr = self::firstAttr($rc->getAttributes(Timestamps::class));
        $softDeletes    = self::firstAttr($rc->getAttributes(SoftDeletes::class));

        $observers = [];
        foreach ($rc->getAttributes(ObservedBy::class) as $a) {
            /** @var ObservedBy $inst */
            $inst = $a->newInstance();
            $observers[] = $inst->observer;
        }

        $globalScopes = [];
        foreach ($rc->getAttributes(GlobalScope::class) as $a) {
            /** @var GlobalScope $inst */
            $inst = $a->newInstance();
            $globalScopes[] = $inst->scope;
        }

        // Local scopes: any public method tagged #[Scope].
        $scopes = [];
        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $rm) {
            if ($rm->getAttributes(Scope::class) === []) {
                continue;
            }
            $scopes[$rm->getName()] = $rm->getName();
        }

        $tableName  = $tableAttr !== null ? $tableAttr->name       : self::inferTableName($class);
        $connection = $tableAttr !== null ? $tableAttr->connection : null;

        // ---------- properties ----------
        $properties = [];
        $primaryKey = null;
        $primaryKeyIncrementing = true;
        $hiddenProps = [];
        $fillable    = [];
        $guarded     = [];

        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $rp) {
            if ($rp->isStatic()) {
                continue;
            }

            $name        = $rp->getName();
            $type        = $rp->getType() instanceof ReflectionNamedType ? $rp->getType() : null;
            $castInst    = self::resolveCast($rp, $type);
            $isHidden    = $rp->getAttributes(Hidden::class)   !== [];
            $isFillable  = $rp->getAttributes(Fillable::class) !== [];
            $isGuarded   = $rp->getAttributes(Guarded::class)  !== [];
            $pkAttr      = self::firstAttr($rp->getAttributes(PrimaryKey::class));

            $properties[$name] = new PropertyMetadata(
                name:        $name,
                type:        $type?->getName(),
                allowsNull:  $type?->allowsNull() ?? true,
                cast:        $castInst,
                hidden:      $isHidden,
                fillable:    $isFillable,
                guarded:     $isGuarded,
                isPrimary:   $pkAttr !== null,
            );

            if ($pkAttr !== null) {
                if ($primaryKey === null) {
                    $primaryKey = $name;
                    $primaryKeyIncrementing = $pkAttr->incrementing;
                } else {
                    // Composite PK: store secondary keys separately.
                    // Rare enough that we don't expose it on metadata yet.
                }
            }
            if ($isHidden)   { $hiddenProps[] = $name; }
            if ($isFillable) { $fillable[]    = $name; }
            if ($isGuarded)  { $guarded[]     = $name; }
        }

        // Fall back to a property literally named "id" if no #[PrimaryKey].
        if ($primaryKey === null && isset($properties['id'])) {
            $primaryKey = 'id';
        }

        return new ModelMetadata(
            class:                  $class,
            table:                  $tableName,
            connection:             $connection,
            primaryKey:             $primaryKey,
            primaryKeyIncrementing: $primaryKeyIncrementing,
            properties:             $properties,
            hidden:                 $hiddenProps,
            fillable:               $fillable,
            guarded:                $guarded,
            timestamps:             $timestampsAttr,
            softDeletes:            $softDeletes,
            observers:              $observers,
            scopes:                 $scopes,
            globalScopes:           $globalScopes,
        );
    }

    private static function resolveCast(ReflectionProperty $rp, ?ReflectionNamedType $type): ?CastsAttribute
    {
        $explicit = self::firstAttr($rp->getAttributes(Cast::class));
        if ($explicit !== null) {
            return CastResolver::resolveExplicit($explicit);
        }
        return CastResolver::resolveFromType($type);
    }

    /**
     * @template T of object
     * @param array<int, \ReflectionAttribute<T>> $attrs
     * @return T|null
     */
    private static function firstAttr(array $attrs): ?object
    {
        return $attrs === [] ? null : $attrs[0]->newInstance();
    }

    /** Snake-case + pluralize the basename. "BlogPost" → "blog_posts". */
    private static function inferTableName(string $class): string
    {
        $base = substr($class, (int) strrpos($class, '\\') + 1);
        $snake = self::snake($base);
        return self::pluralize($snake);
    }

    private static function snake(string $s): string
    {
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($i > 0 && ctype_upper($c)) {
                $out .= '_';
            }
            $out .= strtolower($c);
        }
        return $out;
    }

    /** Tiny pluralizer: handles common irregulars + -y/-s/-x/-z/-ch/-sh rules. */
    private static function pluralize(string $word): string
    {
        $irregular = [
            'person' => 'people',
            'man'    => 'men',
            'woman'  => 'women',
            'child'  => 'children',
            'foot'   => 'feet',
            'tooth'  => 'teeth',
            'mouse'  => 'mice',
            'goose'  => 'geese',
            'octopus'=> 'octopuses',
            'datum'  => 'data',
            'criterion' => 'criteria',
        ];
        if (isset($irregular[$word])) {
            return $irregular[$word];
        }
        // -y preceded by consonant → -ies
        if (preg_match('/[^aeiou]y$/', $word) === 1) {
            return substr($word, 0, -1) . 'ies';
        }
        // -s, -ss, -sh, -ch, -x, -z → add -es
        if (preg_match('/(s|ss|sh|ch|x|z)$/', $word) === 1) {
            return $word . 'es';
        }
        // -f / -fe → -ves
        if (preg_match('/(fe?)$/', $word, $m) === 1) {
            return substr($word, 0, -strlen($m[1])) . 'ves';
        }
        return $word . 's';
    }

    /** Test helper. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
