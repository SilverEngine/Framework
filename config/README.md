# Application config (overrides)

Framework **defaults** live in the core package:
`packages/silverengine/core/src/Config/`.

Anything you put here **deep-merges over** the core default of the same
filename — you only specify the keys you want to change; everything else
is inherited.

```php
// core default: packages/silverengine/core/src/Config/Recorder.php
return ['enabled' => true, 'limit' => 50, 'ignore' => ['/debug', ...]];

// override: config/Recorder.php
return ['limit' => 200];

// effective: ['enabled' => true, 'limit' => 200, 'ignore' => ['/debug', ...]]
```

Merge rules:

- Associative arrays merge **recursively** (your keys win, the rest is
  inherited from core).
- A **list** (sequential array) or scalar in your override **replaces**
  the core value wholesale — e.g. providing `ignore` here replaces the
  whole core `ignore` list rather than appending to it.
- A file present only in core is used as-is; a file present only here
  (no core default) is used as-is.

A new config file with no core counterpart can also be added here and is
read normally (`Env::get('<filename-lowercased>.<key>')`).
