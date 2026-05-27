<?php
/**
 * Heartbeat HTML visualization. Server-rendered, inline CSS only — no
 * external assets — so it renders even when /build/ is missing or the
 * app is otherwise wedged. Mirrors the same data shape the JSON returns.
 *
 * @var array<string,mixed> $report  output of Heartbeat::run()
 */
declare(strict_types=1);

$status     = $report['status'];
$checks     = $report['checks'] ?? [];
$perf       = $report['performance'] ?? [];
$lifecycle  = $perf['lifecycle'] ?? ['enabled' => false];
$lastReq    = $perf['last_completed'] ?? ['enabled' => false];
$subsystems = $perf['subsystems'] ?? [];
$memory     = $perf['memory'] ?? [];
$opcache    = $perf['opcache'] ?? ['enabled' => false];
$request    = $perf['request'] ?? [];

$total = max(0.001, (float) ($lifecycle['total_ms'] ?? 0));

// Category palette — matched against Engine\Ghost dt() categories.
$categoryColors = [
    'boot'        => '#3b82f6',
    'kernel'      => '#8b5cf6',
    'request'     => '#06b6d4',
    'middleware'  => '#f59e0b',
    'controller'  => '#10b981',
    'view'        => '#ec4899',
    'database'    => '#ef4444',
];
$colorFor = static fn (string $cat): string => $categoryColors[$cat] ?? '#71717a';

// Rolled-up verdict colors.
$statusTone = match ($status) {
    'ok'       => ['bg' => '#10b981', 'fg' => '#ffffff', 'label' => 'Healthy'],
    'degraded' => ['bg' => '#f59e0b', 'fg' => '#1c1917', 'label' => 'Degraded'],
    'down'     => ['bg' => '#ef4444', 'fg' => '#ffffff', 'label' => 'Down'],
    default    => ['bg' => '#71717a', 'fg' => '#ffffff', 'label' => 'Unknown'],
};

$h = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5);

// Counts for the hero summary.
$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $c) { $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1; }

// Tooltip text for spans.
$spanTip = static function (array $s): string {
    return sprintf(
        '%s · %sms (%s → %s)',
        $s['label'],
        number_format($s['ms'], 3),
        number_format($s['start_ms'], 3),
        number_format($s['end_ms'], 3),
    );
};
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Heartbeat · SilverEngine</title>
<style>
  *,*::before,*::after { box-sizing: border-box; }
  :root {
    color-scheme: light dark;
    --bg: #ffffff;
    --fg: #18181b;
    --muted: #71717a;
    --muted-2: #a1a1aa;
    --border: #e4e4e7;
    --border-soft: #f4f4f5;
    --card: #ffffff;
    --card-soft: #fafafa;
    --code: #fafafa;
    --hairline: rgba(0,0,0,.04);
    --shadow: 0 1px 2px rgba(0,0,0,.04), 0 1px 3px rgba(0,0,0,.05);
    --ok:   #10b981;
    --warn: #f59e0b;
    --fail: #ef4444;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #09090b;
      --fg: #fafafa;
      --muted: #a1a1aa;
      --muted-2: #71717a;
      --border: #27272a;
      --border-soft: #1e1e22;
      --card: #131316;
      --card-soft: #0f0f12;
      --code: #0c0c0e;
      --hairline: rgba(255,255,255,.04);
      --shadow: 0 0 0 1px rgba(255,255,255,.02);
    }
  }
  html, body { margin: 0; background: var(--bg); color: var(--fg); -webkit-font-smoothing: antialiased; }
  body { font: 14px/1.55 ui-sans-serif, system-ui, -apple-system, "SF Pro Text", "Segoe UI", sans-serif; }
  a { color: inherit; text-decoration: none; }
  .mono { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }

  .wrap { max-width: 96rem; margin: 0 auto; padding: 1.75rem 1.75rem 4rem; }

  /* Header */
  header { display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border-soft); }
  .logo { display: inline-flex; align-items: center; gap: .55rem; font-weight: 500; letter-spacing: -.01em; }
  .logo .dot { display: inline-block; width: .5rem; height: .5rem; background: currentColor; }
  .logo .sub { color: var(--muted); font-weight: 400; }
  nav { display: flex; gap: 1.5rem; font-size: 13px; color: var(--muted); }
  nav a:hover { color: var(--fg); }

  /* Hero */
  .hero { display: grid; grid-template-columns: 1fr auto; gap: 2rem; align-items: flex-end; margin-bottom: 2rem; }
  .hero-l h1 { font-size: 2.75rem; font-weight: 500; letter-spacing: -.03em; margin: .25rem 0 .5rem; }
  .hero-l .sub { color: var(--muted); font-size: 13px; }
  .hero-l .sub b { color: var(--fg); font-weight: 500; }

  .verdict { display: inline-flex; align-items: center; gap: .55rem; padding: .5rem .9rem; border-radius: 999px; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: <?= $h($statusTone['fg']) ?>; background: <?= $h($statusTone['bg']) ?>; box-shadow: 0 8px 24px -8px <?= $h($statusTone['bg']) ?>66; }
  .verdict .pulse { width: .55rem; height: .55rem; border-radius: 50%; background: currentColor; animation: pulse 1.8s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .35 } }

  .hero-r { display: flex; flex-direction: column; align-items: flex-end; gap: .65rem; }
  .summary { display: flex; gap: .5rem; font-size: 11px; color: var(--muted); letter-spacing: .04em; }
  .summary b { color: var(--fg); font-variant-numeric: tabular-nums; }
  .summary .ok b { color: var(--ok); }
  .summary .warn b { color: var(--warn); }
  .summary .fail b { color: var(--fail); }

  /* Sections */
  section { margin: 2.25rem 0; }
  h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .2em; color: var(--muted); margin: 0 0 .85rem; font-weight: 500; display: flex; align-items: baseline; justify-content: space-between; }
  h2 .meta { color: var(--muted-2); letter-spacing: 0; text-transform: none; font-size: 11px; font-weight: 400; }

  /* Stat cards */
  .kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .65rem; }
  .kpi .card { padding: .9rem 1rem; border: 1px solid var(--border); border-radius: .85rem; background: var(--card); box-shadow: var(--shadow); position: relative; overflow: hidden; }
  .kpi .card .k { font-size: 10px; text-transform: uppercase; letter-spacing: .14em; color: var(--muted); }
  .kpi .card .v { font-size: 24px; font-weight: 500; margin-top: .35rem; letter-spacing: -.015em; font-variant-numeric: tabular-nums; }
  .kpi .card .v small { font-size: 12px; color: var(--muted); font-weight: 400; margin-left: .2rem; }
  .kpi .card.accent::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--accent, var(--muted)); }

  /* Subsystem checks */
  .checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr)); gap: .5rem; }
  .check { display: flex; align-items: center; gap: .75rem; padding: .8rem 1rem; border: 1px solid var(--border); border-radius: .75rem; background: var(--card); transition: border-color .15s, transform .15s; position: relative; }
  .check:hover { border-color: var(--muted-2); }
  .check .pin { position: relative; width: .6rem; height: .6rem; border-radius: 50%; flex-shrink: 0; }
  .check .pin::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; opacity: .25; }
  .check .pin.ok   { background: var(--ok);   } .check .pin.ok::after   { background: var(--ok); }
  .check .pin.warn { background: var(--warn); } .check .pin.warn::after { background: var(--warn); }
  .check .pin.fail { background: var(--fail); } .check .pin.fail::after { background: var(--fail); }
  .check .name { font-weight: 500; min-width: 5.5rem; }
  .check .detail { color: var(--muted); font-size: 12px; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /* Non-ok checks: tinted background + colored border + status tag so they
     pull the eye. The whole card is the warning, not just the pin. */
  .check.warn,
  .check.fail {
    background: var(--tint);
    border-color: var(--accent);
    box-shadow: 0 0 0 1px var(--accent), 0 4px 14px -8px var(--accent);
  }
  .check.warn .name,
  .check.fail .name { color: var(--accent-fg); }
  .check.warn .detail,
  .check.fail .detail { color: var(--accent-fg); opacity: .85; white-space: normal; }
  .check.warn {
    --accent:    var(--warn);
    --accent-fg: #92400e;   /* amber-800 — readable on amber tint */
    --tint:      color-mix(in srgb, var(--warn) 10%, var(--card));
  }
  .check.fail {
    --accent:    var(--fail);
    --accent-fg: #991b1b;   /* red-800 */
    --tint:      color-mix(in srgb, var(--fail) 12%, var(--card));
  }
  @media (prefers-color-scheme: dark) {
    .check.warn { --accent-fg: #fde68a; --tint: color-mix(in srgb, var(--warn) 18%, var(--card)); }
    .check.fail { --accent-fg: #fecaca; --tint: color-mix(in srgb, var(--fail) 22%, var(--card)); }
  }
  .check.warn::before,
  .check.fail::before {
    content: attr(data-tag);
    position: absolute; top: -.5rem; right: .75rem;
    background: var(--accent); color: white;
    font-size: 9px; letter-spacing: .12em; text-transform: uppercase; font-weight: 700;
    padding: .1rem .4rem; border-radius: .25rem;
  }

  /* Timeline */
  .timeline { border: 1px solid var(--border); border-radius: 1rem; background: var(--card); padding: 1.5rem; box-shadow: var(--shadow); }
  .legend { display: flex; flex-wrap: wrap; gap: .65rem 1rem; font-size: 11px; color: var(--muted); margin-bottom: 1.25rem; }
  .legend span { display: inline-flex; align-items: center; gap: .4rem; }
  .swatch { display: inline-block; width: .6rem; height: .6rem; border-radius: 2px; }

  .chart { position: relative; }
  .track { display: grid; grid-template-columns: 13rem 1fr 5.5rem 4rem; align-items: center; gap: .75rem; padding: .3rem 0; font-size: 12px; }
  .track + .track { border-top: 1px dashed var(--hairline); }
  .track .lab { color: var(--fg); text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
  .track .lab small { display: block; color: var(--muted); font-weight: 400; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; margin-top: -.1rem; }
  .track .rail { position: relative; height: 1.4rem; background: linear-gradient(to right, var(--hairline) 1px, transparent 1px) repeat-x; background-size: 12.5% 100%; border-radius: 3px; }
  .track .pct { color: var(--muted-2); text-align: right; font-family: ui-monospace, monospace; font-size: 10px; }
  .track .bar { position: absolute; top: 0; bottom: 0; border-radius: 3px; min-width: 2px; cursor: default; transition: filter .15s; box-shadow: inset 0 -2px 0 rgba(0,0,0,.08); }
  .track .bar:hover { filter: brightness(1.15); }

  /* In-progress (open) spans — middleware frames + controller action that
     haven't end()'d yet because heartbeat fires mid-request. Show diagonal
     stripes + a pulsing right edge so it's clear they're still running. */
  .track .bar.live {
    background-image: repeating-linear-gradient(
      45deg,
      rgba(255,255,255,.18) 0, rgba(255,255,255,.18) 4px,
      transparent 4px, transparent 8px
    );
    background-blend-mode: overlay;
    border-right: 2px solid rgba(255,255,255,.7);
    animation: live-edge 1.4s ease-in-out infinite;
  }
  @keyframes live-edge {
    0%, 100% { box-shadow: inset 0 -2px 0 rgba(0,0,0,.08), 2px 0 0 rgba(255,255,255,.5); }
    50%      { box-shadow: inset 0 -2px 0 rgba(0,0,0,.08), 4px 0 8px rgba(255,255,255,.4); }
  }
  .track .lab.live::before {
    content: '●'; color: currentColor; margin-right: .25rem;
    animation: pulse 1.8s ease-in-out infinite;
  }
  .track .ms.live::after {
    content: ' …'; color: var(--muted-2);
  }
  .track .bar::after {
    content: attr(data-tip);
    position: absolute; bottom: calc(100% + 6px); left: 0;
    font: 11px/1.3 ui-monospace, monospace; color: var(--fg); background: var(--card);
    border: 1px solid var(--border); border-radius: .375rem; padding: .25rem .5rem; white-space: nowrap;
    opacity: 0; pointer-events: none; transition: opacity .12s; box-shadow: var(--shadow); z-index: 5;
  }
  .track .bar:hover::after { opacity: 1; }
  .track .ms { color: var(--muted); text-align: right; font-family: ui-monospace, monospace; font-size: 11px; }

  .axis { display: grid; grid-template-columns: 13rem 1fr 5.5rem 4rem; gap: .75rem; font-size: 10px; color: var(--muted-2); margin-top: .75rem; }
  .axis .ticks { display: flex; justify-content: space-between; font-family: ui-monospace, monospace; }

  /* Category breakdown */
  .cats { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .5rem; margin-top: 1rem; }
  .cat-pill { display: flex; align-items: center; gap: .5rem; padding: .55rem .8rem; border: 1px solid var(--border); border-radius: .65rem; background: var(--card-soft); }
  .cat-pill .swatch { width: .5rem; height: .5rem; border-radius: 1px; }
  .cat-pill .name { font-size: 11px; color: var(--muted); flex: 1; text-transform: uppercase; letter-spacing: .08em; }
  .cat-pill .ms { font-family: ui-monospace, monospace; font-size: 12px; font-variant-numeric: tabular-nums; }

  /* Bench rows */
  .bench { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .5rem; }
  .bench-row { padding: .6rem .85rem; border: 1px solid var(--border); border-radius: .65rem; background: var(--card); }
  .bench-row .k { font-size: 11px; color: var(--muted); text-transform: capitalize; }
  .bench-row .bar-line { position: relative; height: .35rem; background: var(--border-soft); border-radius: 999px; margin-top: .45rem; overflow: hidden; }
  .bench-row .bar-line .fill { position: absolute; left: 0; top: 0; bottom: 0; background: linear-gradient(90deg, #8b5cf6, #06b6d4); border-radius: 999px; }
  .bench-row .v { font-size: 13px; font-family: ui-monospace, monospace; font-variant-numeric: tabular-nums; margin-top: .35rem; display: flex; justify-content: space-between; }

  /* Memory gauges */
  .gauges { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .65rem; }
  .gauge { padding: .9rem 1rem; border: 1px solid var(--border); border-radius: .85rem; background: var(--card); }
  .gauge .k { font-size: 10px; text-transform: uppercase; letter-spacing: .14em; color: var(--muted); }
  .gauge .v { font-size: 22px; font-weight: 500; margin-top: .3rem; font-variant-numeric: tabular-nums; }
  .gauge .v small { font-size: 11px; color: var(--muted); font-weight: 400; margin-left: .2rem; }
  .gauge .bar-line { position: relative; height: .35rem; background: var(--border-soft); border-radius: 999px; margin-top: .55rem; overflow: hidden; }
  .gauge .bar-line .fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 999px; }

  /* JSON details */
  details { border: 1px solid var(--border); border-radius: .85rem; background: var(--card); overflow: hidden; margin-top: 1rem; }
  summary { padding: .75rem 1rem; cursor: pointer; color: var(--muted); font-size: 12px; user-select: none; display: flex; justify-content: space-between; align-items: center; }
  summary::after { content: '⌄'; transition: transform .15s; }
  details[open] summary::after { transform: rotate(180deg); }
  summary:hover { color: var(--fg); }
  details[open] > pre { border-top: 1px solid var(--border); }
  pre { margin: 0; padding: 1rem 1.25rem; overflow-x: auto; font: 11.5px/1.6 ui-monospace, "SF Mono", Menlo, monospace; color: var(--fg); background: var(--code); max-height: 24rem; }

  footer { color: var(--muted); font-size: 11px; margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--border-soft); display: flex; justify-content: space-between; }
  footer a:hover { color: var(--fg); }

  @media (max-width: 960px) {
    .track { grid-template-columns: 9rem 1fr 4rem 3rem; }
    .axis  { grid-template-columns: 9rem 1fr 4rem 3rem; }
  }
  @media (max-width: 720px) {
    .hero { grid-template-columns: 1fr; }
    .hero-r { align-items: flex-start; }
    .track { grid-template-columns: 6rem 1fr 3.5rem 2.5rem; gap: .5rem; font-size: 11px; }
    .axis  { grid-template-columns: 6rem 1fr 3.5rem 2.5rem; gap: .5rem; }
    .track .lab small { display: none; }
  }
</style>
</head>
<body>
<div class="wrap">

<header>
  <span class="logo">
    <span class="dot"></span>
    SilverEngine
    <span class="sub">· Heartbeat</span>
  </span>
  <nav>
    <a href="/">Home</a>
    <a href="/docs/">Docs</a>
    <a href="/new">Scaffolder</a>
    <a href="/heartbeat" title="JSON">JSON</a>
  </nav>
</header>

<div class="hero">
  <div class="hero-l">
    <span class="verdict">
      <span class="pulse"></span>
      <?= $h($statusTone['label']) ?>
    </span>
    <h1>System heartbeat</h1>
    <div class="sub">
      env=<b><?= $h($report['env']) ?></b> ·
      php=<b><?= $h($report['php']) ?></b> ·
      debug=<b><?= $report['debug'] ? 'on' : 'off' ?></b>
    </div>
  </div>
  <div class="hero-r">
    <div class="summary">
      <span class="ok"><b><?= (int) $counts['ok'] ?></b>&nbsp;ok</span>
      <span>·</span>
      <span class="warn"><b><?= (int) $counts['warn'] ?></b>&nbsp;warn</span>
      <span>·</span>
      <span class="fail"><b><?= (int) $counts['fail'] ?></b>&nbsp;fail</span>
    </div>
    <div class="summary mono">
      <?= $h(number_format((float) ($request['elapsed_ms'] ?? 0), 2)) ?> ms · boot <?= $h(number_format((float) ($request['boot_ms'] ?? 0), 2)) ?> ms
    </div>
  </div>
</div>

<!-- KPI hero cards -->
<section>
  <div class="kpi">
    <div class="card accent" style="--accent: <?= $h($statusTone['bg']) ?>">
      <div class="k">Verdict</div>
      <div class="v"><?= $h($statusTone['label']) ?></div>
    </div>
    <div class="card accent" style="--accent: #8b5cf6">
      <div class="k">Total time</div>
      <div class="v"><?= $h(number_format($total, 2)) ?><small>ms</small></div>
    </div>
    <div class="card accent" style="--accent: #3b82f6">
      <div class="k">Memory peak</div>
      <div class="v"><?= $h((string) ($memory['peak_mb'] ?? '–')) ?><small>MB</small></div>
    </div>
    <div class="card accent" style="--accent: #10b981">
      <div class="k">Opcache hit</div>
      <div class="v"><?= !empty($opcache['enabled']) ? $h((string) ($opcache['hit_rate_pct'] ?? '–')) . '<small>%</small>' : 'off' ?></div>
    </div>
  </div>
</section>

<section>
  <h2>Subsystem checks <span class="meta"><?= count($checks) ?> probes</span></h2>
  <div class="checks">
    <?php
      // Sort: fails first, then warns, then ok — so anything needing
      // attention is at the top of the grid.
      $rank = ['fail' => 0, 'warn' => 1, 'ok' => 2];
      $sorted = $checks;
      usort($sorted, static fn ($a, $b) => ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9));
    ?>
    <?php foreach ($sorted as $c): ?>
      <div class="check <?= $h($c['status']) ?>"<?= $c['status'] !== 'ok' ? ' data-tag="' . $h($c['status']) . '"' : '' ?>>
        <span class="pin <?= $h($c['status']) ?>"></span>
        <span class="name"><?= $h($c['name']) ?></span>
        <span class="detail" title="<?= $h($c['detail']) ?>"><?= $h($c['detail']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!empty($lifecycle['enabled']) && !empty($lifecycle['spans'])):
  $closed = array_filter($lifecycle['spans'], static fn ($s) => empty($s['in_progress']));
  $open   = array_filter($lifecycle['spans'], static fn ($s) => !empty($s['in_progress']));
  $closedSum = array_sum(array_column($closed, 'ms'));
  $coveragePct = $total > 0 ? min(100, ($closedSum / $total) * 100) : 0;
?>
<section>
  <h2>Request lifecycle
    <span class="meta">
      <?= count($lifecycle['spans']) ?> spans · <?= count($closed) ?> closed · <?= count($open) ?> in progress ·
      <?= $h(number_format($total, 3)) ?> ms total · index.php → controller (mid-flight)
    </span>
  </h2>

  <div class="timeline">
    <div class="legend">
      <?php
        $shownCats = [];
        $hasLive = false;
        foreach ($lifecycle['spans'] as $s) {
          $shownCats[$s['category']] = true;
          if (!empty($s['in_progress'])) $hasLive = true;
        }
        foreach (array_keys($shownCats) as $cat):
      ?>
        <span><i class="swatch" style="background:<?= $h($colorFor((string) $cat)) ?>"></i><?= $h((string) $cat) ?></span>
      <?php endforeach; ?>
      <?php if ($hasLive): ?>
        <span style="margin-left:.5rem; opacity:.85">
          <i class="swatch" style="background-image: repeating-linear-gradient(45deg, var(--muted) 0, var(--muted) 3px, transparent 3px, transparent 6px); background-color: var(--muted-2)"></i>
          in progress (mid-request)
        </span>
      <?php endif; ?>
    </div>

    <div class="chart">
      <?php foreach ($lifecycle['spans'] as $span):
        $startPct = max(0.0, min(100.0, ($span['start_ms'] / $total) * 100));
        $widthPct = max(0.4, min(100.0 - $startPct, ($span['ms'] / $total) * 100));
        $pct      = ($span['ms'] / $total) * 100;
        $color    = $colorFor($span['category']);
        $live     = !empty($span['in_progress']);
        $liveCls  = $live ? ' live' : '';
        $tip      = $live ? $spanTip($span) . ' · still running' : $spanTip($span);
      ?>
        <div class="track">
          <div class="lab<?= $liveCls ?>" title="<?= $h($span['label']) ?>">
            <?= $h($span['label']) ?>
            <small style="color:<?= $h($color) ?>"><?= $h($span['category']) ?></small>
          </div>
          <div class="rail">
            <div class="bar<?= $liveCls ?>"
                 style="left: <?= number_format($startPct, 3, '.', '') ?>%; width: <?= number_format($widthPct, 3, '.', '') ?>%; background: <?= $h($color) ?>"
                 data-tip="<?= $h($tip) ?>"></div>
          </div>
          <div class="ms<?= $liveCls ?>"><?= $h(number_format($span['ms'], 3)) ?>ms</div>
          <div class="pct"><?= $h(number_format($pct, 1)) ?>%</div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="axis">
      <span></span>
      <span class="ticks">
        <span>0</span>
        <span><?= $h(number_format($total * 0.25, 2)) ?></span>
        <span><?= $h(number_format($total * 0.5, 2)) ?></span>
        <span><?= $h(number_format($total * 0.75, 2)) ?></span>
        <span><?= $h(number_format($total, 2)) ?> ms</span>
      </span>
      <span></span>
    </div>
  </div>

  <?php if (!empty($lifecycle['by_category'])): ?>
    <div class="cats">
      <?php foreach ($lifecycle['by_category'] as $cat => $ms):
        $color = $colorFor((string) $cat);
      ?>
        <div class="cat-pill">
          <i class="swatch" style="background:<?= $h($color) ?>"></i>
          <span class="name"><?= $h((string) $cat) ?></span>
          <span class="ms"><?= $h(number_format((float) $ms, 3)) ?>ms</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($lastReq['enabled']) && !empty($lastReq['spans'])):
  $lastTotal = max(0.001, (float) $lastReq['total_ms']);
  // Non-wrapping spans only — middleware frames wrap the controller and
  // would double-count if summed naively.
  $nonWrapping = ['autoload + bootstrap','Env::construct','error handlers','database connect',
    'kernel construct','load routes','load middlewares','request init','providers',
    'route resolve','controller resolve','controller action','view render',
    'deferred hooks','finalize providers','recorder snapshot'];
  $lastCoverMs = 0.0;
  foreach ($lastReq['spans'] as $s) {
    if (in_array($s['label'], $nonWrapping, true)) $lastCoverMs += $s['ms'];
  }
  $lastCoverPct = $lastTotal > 0 ? min(100, ($lastCoverMs / $lastTotal) * 100) : 0;
?>
<section>
  <h2>
    Previous complete request
    <span class="meta">
      <?= $h($lastReq['method']) ?> <?= $h($lastReq['path']) ?> →
      <?= $h((string) $lastReq['status']) ?> ·
      <?= count($lastReq['spans']) ?> spans · all closed ·
      <?= $h(number_format($lastTotal, 3)) ?> ms total ·
      <?= $h(number_format($lastCoverPct, 1)) ?>% covered
    </span>
  </h2>

  <div class="timeline">
    <div class="legend">
      <?php
        $cats2 = [];
        foreach ($lastReq['spans'] as $s) { $cats2[$s['category']] = true; }
        foreach (array_keys($cats2) as $cat):
      ?>
        <span><i class="swatch" style="background:<?= $h($colorFor((string) $cat)) ?>"></i><?= $h((string) $cat) ?></span>
      <?php endforeach; ?>
    </div>

    <div class="chart">
      <?php foreach ($lastReq['spans'] as $span):
        $startPct = max(0.0, min(100.0, ($span['start_ms'] / $lastTotal) * 100));
        $widthPct = max(0.4, min(100.0 - $startPct, ($span['ms'] / $lastTotal) * 100));
        $pct      = ($span['ms'] / $lastTotal) * 100;
        $color    = $colorFor($span['category']);
        $tip      = $spanTip($span);
      ?>
        <div class="track">
          <div class="lab" title="<?= $h($span['label']) ?>">
            <?= $h($span['label']) ?>
            <small style="color:<?= $h($color) ?>"><?= $h($span['category']) ?></small>
          </div>
          <div class="rail">
            <div class="bar"
                 style="left: <?= number_format($startPct, 3, '.', '') ?>%; width: <?= number_format($widthPct, 3, '.', '') ?>%; background: <?= $h($color) ?>"
                 data-tip="<?= $h($tip) ?>"></div>
          </div>
          <div class="ms"><?= $h(number_format($span['ms'], 3)) ?>ms</div>
          <div class="pct"><?= $h(number_format($pct, 1)) ?>%</div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="axis">
      <span></span>
      <span class="ticks">
        <span>0</span>
        <span><?= $h(number_format($lastTotal * 0.25, 2)) ?></span>
        <span><?= $h(number_format($lastTotal * 0.5, 2)) ?></span>
        <span><?= $h(number_format($lastTotal * 0.75, 2)) ?></span>
        <span><?= $h(number_format($lastTotal, 2)) ?> ms</span>
      </span>
      <span></span>
    </div>
  </div>

  <?php if (!empty($lastReq['by_category'])): ?>
    <div class="cats">
      <?php foreach ($lastReq['by_category'] as $cat => $ms): ?>
        <div class="cat-pill">
          <i class="swatch" style="background:<?= $h($colorFor((string) $cat)) ?>"></i>
          <span class="name"><?= $h((string) $cat) ?></span>
          <span class="ms"><?= $h(number_format((float) $ms, 3)) ?>ms</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($subsystems)):
  $maxBench = max(array_map('intval', $subsystems)) ?: 1;
?>
<section>
  <h2>Cold-path micro-benchmarks <span class="meta">median of 5 runs</span></h2>
  <div class="bench">
    <?php foreach ($subsystems as $k => $v):
      $label = ucwords(str_replace('_', ' ', preg_replace('/_us$/', '', (string) $k)));
      $pct = min(100, ((int) $v / $maxBench) * 100);
    ?>
      <div class="bench-row">
        <div class="k"><?= $h($label) ?></div>
        <div class="bar-line"><div class="fill" style="width: <?= number_format($pct, 1, '.', '') ?>%"></div></div>
        <div class="v"><span><?= $h((string) $v) ?> µs</span><span class="mono" style="color:var(--muted)"><?= $h(number_format($pct, 0)) ?>%</span></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section>
  <h2>Memory &amp; Opcache</h2>
  <div class="gauges">
    <?php
      $peak = (float) ($memory['peak_mb'] ?? 0);
      $limit = (string) ($memory['limit'] ?? '');
      // Parse "128M", "1G" into MB for the gauge fill.
      $limitMb = 0;
      if (preg_match('/^(\d+)\s*([MG])?/i', $limit, $m)) {
          $limitMb = (int) $m[1] * (strtoupper($m[2] ?? '') === 'G' ? 1024 : 1);
      }
      $memPct = $limitMb > 0 ? min(100, ($peak / $limitMb) * 100) : 0;
    ?>
    <div class="gauge">
      <div class="k">Memory · current</div>
      <div class="v"><?= $h((string) ($memory['current_mb'] ?? '–')) ?> <small>MB</small></div>
      <div class="bar-line"><div class="fill" style="width: <?= number_format($limitMb > 0 ? min(100, ((float)($memory['current_mb'] ?? 0) / $limitMb) * 100) : 0, 1, '.', '') ?>%; background: #3b82f6"></div></div>
    </div>
    <div class="gauge">
      <div class="k">Memory · peak</div>
      <div class="v"><?= $h((string) $peak) ?> <small>MB / <?= $h($limit) ?></small></div>
      <div class="bar-line"><div class="fill" style="width: <?= number_format($memPct, 1, '.', '') ?>%; background: #8b5cf6"></div></div>
    </div>

    <?php if (!empty($opcache['enabled'])):
      $hit = (float) ($opcache['hit_rate_pct'] ?? 0);
      $usedMb = (float) ($opcache['used_mb'] ?? 0);
      $freeMb = (float) ($opcache['free_mb'] ?? 0);
      $oTotal = max(0.01, $usedMb + $freeMb);
    ?>
      <div class="gauge">
        <div class="k">Opcache · hit rate</div>
        <div class="v"><?= $h(number_format($hit, 2)) ?> <small>%</small></div>
        <div class="bar-line"><div class="fill" style="width: <?= number_format($hit, 1, '.', '') ?>%; background: #10b981"></div></div>
      </div>
      <div class="gauge">
        <div class="k">Opcache · used</div>
        <div class="v"><?= $h(number_format($usedMb, 2)) ?> <small>MB / <?= $h(number_format($oTotal, 0)) ?></small></div>
        <div class="bar-line"><div class="fill" style="width: <?= number_format(($usedMb / $oTotal) * 100, 1, '.', '') ?>%; background: #f59e0b"></div></div>
      </div>
      <div class="gauge">
        <div class="k">Opcache · cached</div>
        <div class="v"><?= $h((string) ($opcache['cached_scripts'] ?? '–')) ?> <small>scripts</small></div>
      </div>
    <?php else: ?>
      <div class="gauge">
        <div class="k">Opcache</div>
        <div class="v">off</div>
      </div>
    <?php endif; ?>
  </div>
</section>

<details>
  <summary>Raw JSON payload</summary>
  <pre><code><?= $h(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
</details>

<footer>
  <span>HTTP <?= $status === 'down' ? '503' : '200' ?> · refresh to re-run</span>
  <span><a href="/heartbeat">JSON</a> · <a href="/docs/#heartbeat">docs</a></span>
</footer>

</div>
</body>
</html>
