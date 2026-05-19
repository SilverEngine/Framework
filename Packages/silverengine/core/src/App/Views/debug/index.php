<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SilverEngine Debug Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, monospace;
            background: #0d1117;
            color: #c9d1d9;
            line-height: 1.6;
        }
        .header {
            background: #161b22;
            border-bottom: 1px solid #30363d;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 { font-size: 1.25rem; color: #58a6ff; font-weight: 600; }
        .header h1 span { color: #8b949e; font-weight: 400; }
        .header .meta { color: #8b949e; font-size: 0.85rem; }
        .header .meta b { color: #3fb950; }

        /* Tabs */
        .tabs {
            background: #161b22;
            border-bottom: 1px solid #30363d;
            padding: 0 2rem;
            display: flex;
            gap: 0;
            overflow-x: auto;
        }
        .tab {
            padding: 0.65rem 1.2rem;
            cursor: pointer;
            color: #8b949e;
            font-size: 0.85rem;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
            white-space: nowrap;
            user-select: none;
        }
        .tab:hover { color: #c9d1d9; }
        .tab.active {
            color: #58a6ff;
            border-bottom-color: #58a6ff;
        }
        .tab .icon { margin-right: 0.4rem; }

        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem 2rem; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.25rem;
        }
        .panel {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            overflow: hidden;
        }
        .panel-title {
            background: #21262d;
            padding: 0.6rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #8b949e;
            border-bottom: 1px solid #30363d;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .panel-body { padding: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        table tr { border-bottom: 1px solid #21262d; }
        table tr:last-child { border-bottom: none; }
        table tr:hover { background: #1c2128; }
        table td { padding: 0.45rem 1rem; vertical-align: top; }
        table td:first-child { color: #8b949e; white-space: nowrap; width: 180px; font-weight: 500; }
        table td:last-child { color: #c9d1d9; word-break: break-all; }
        .badge {
            display: inline-block; padding: 0.1rem 0.45rem; border-radius: 4px;
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-get { background: #238636; color: #fff; }
        .badge-post { background: #1f6feb; color: #fff; }
        .badge-put { background: #9e6a03; color: #fff; }
        .badge-delete { background: #da3633; color: #fff; }
        .badge-any { background: #6e40c9; color: #fff; }
        .badge-patch { background: #388bfd; color: #fff; }
        .val-true { color: #3fb950; }
        .val-false { color: #da3633; }
        .val-num { color: #d2a8ff; }
        .ext-list { color: #8b949e; font-size: 0.8rem; line-height: 1.8; }
        .panel-full { grid-column: 1 / -1; }
        .footer { text-align: center; padding: 2rem; color: #484f58; font-size: 0.8rem; }

        /* Timeline */
        .timeline-bar-wrap { position: relative; height: 22px; background: #21262d; border-radius: 3px; overflow: hidden; }
        .timeline-bar {
            position: absolute; top: 2px; height: 18px; border-radius: 2px;
            min-width: 2px; opacity: 0.9; transition: opacity 0.15s;
        }
        .timeline-bar:hover { opacity: 1; }
        .cat-boot { background: #58a6ff; }
        .cat-kernel { background: #3fb950; }
        .cat-controller { background: #d2a8ff; }
        .cat-view { background: #f0883e; }
        .cat-app { background: #8b949e; }
        .timeline-legend { display: flex; gap: 1rem; padding: 0.5rem 1rem; font-size: 0.75rem; color: #8b949e; }
        .timeline-legend span { display: inline-flex; align-items: center; gap: 0.3rem; }
        .legend-dot { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
        .timeline-mark { position: absolute; top: 0; bottom: 0; width: 1px; background: #f85149; opacity: 0.7; }
        .file-row td { font-size: 0.8rem; padding: 0.3rem 1rem; }
        .file-row td:last-child { color: #8b949e; text-align: right; width: 80px; }
    </style>
</head>
<body>

<div class="header">
    <h1>SilverEngine <span>Debug Panel</span></h1>
    <div class="meta">
        <?php if (defined('APP_START')): ?>
            Page: <b><?= number_format((hrtime(true) - APP_START) / 1e6, 2) ?>ms</b> &middot;
        <?php endif; ?>
        Memory: <b><?= number_format(memory_get_usage() / 1048576, 2) ?> MB</b> &middot;
        PHP <b><?= PHP_VERSION ?></b>
    </div>
</div>

<div class="tabs">
    <div class="tab active" data-tab="overview"><span class="icon">&#9889;</span>Overview</div>
    <div class="tab" data-tab="timeline"><span class="icon">&#9202;</span>Timeline</div>
    <div class="tab" data-tab="routes"><span class="icon">&#128268;</span>Routes</div>
    <div class="tab" data-tab="request"><span class="icon">&#128229;</span>Request</div>
    <div class="tab" data-tab="database"><span class="icon">&#128451;</span>Database</div>
    <div class="tab" data-tab="config"><span class="icon">&#128196;</span>Config</div>
    <div class="tab" data-tab="packages"><span class="icon">&#128230;</span>Packages</div>
    <div class="tab" data-tab="server"><span class="icon">&#128421;</span>Server</div>
</div>

<div class="container">

    <!-- Overview Tab -->
    <div class="tab-content active" id="tab-overview">
        <div class="grid">
            <div class="panel">
                <div class="panel-title"><span class="icon">&#9889;</span> Performance</div>
                <div class="panel-body">
                    <table>
                        <?php foreach ($performance as $k => $v): ?>
                        <tr><td><?= htmlspecialchars($k) ?></td><td class="val-num"><?= htmlspecialchars($v) ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="panel">
                <div class="panel-title"><span class="icon">&#9881;</span> Environment</div>
                <div class="panel-body">
                    <table>
                        <?php foreach ($environment as $k => $v): ?>
                            <?php if ($k === 'Extensions'): ?>
                            <tr><td><?= htmlspecialchars($k) ?></td><td class="ext-list"><?= htmlspecialchars($v) ?></td></tr>
                            <?php else: ?>
                            <tr><td><?= htmlspecialchars($k) ?></td>
                                <td class="<?= $v === 'true' ? 'val-true' : ($v === 'false' ? 'val-false' : '') ?>">
                                    <?= htmlspecialchars($v) ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Tab -->
    <div class="tab-content" id="tab-timeline">
        <?php
            $maxMs = max(1, $totalMs);
            $spans = array_filter($timeline, fn($e) => $e['type'] === 'span');
            $marks = array_filter($timeline, fn($e) => $e['type'] === 'mark');
        ?>
        <!-- Waterfall -->
        <div class="panel panel-full">
            <div class="panel-title"><span class="icon">&#9202;</span> Request Waterfall (<?= number_format($maxMs, 2) ?>ms total)</div>
            <div class="timeline-legend">
                <span><i class="legend-dot cat-boot"></i> Boot</span>
                <span><i class="legend-dot cat-kernel"></i> Kernel</span>
                <span><i class="legend-dot cat-controller"></i> Controller</span>
                <span><i class="legend-dot cat-view"></i> View</span>
            </div>
            <div class="panel-body" style="padding: 0.75rem 1rem;">
                <?php if (empty($spans)): ?>
                    <p style="color:#8b949e; padding:1rem;">No timeline data. DebugTimer not started (APP_DEBUG may be off).</p>
                <?php else: ?>
                    <table style="width:100%;">
                        <?php foreach ($spans as $s): ?>
                        <?php
                            $left = ($s['start_ms'] / $maxMs) * 100;
                            $width = max(0.3, ($s['duration_ms'] / $maxMs) * 100);
                            $cat = $s['category'];
                        ?>
                        <tr>
                            <td style="width:200px;color:#8b949e;font-size:0.8rem;padding:3px 8px;"><?= htmlspecialchars($s['label']) ?></td>
                            <td style="padding:3px 8px;">
                                <div class="timeline-bar-wrap">
                                    <div class="timeline-bar cat-<?= htmlspecialchars($cat) ?>"
                                         style="left:<?= number_format($left, 2) ?>%;width:<?= number_format($width, 2) ?>%;"
                                         title="<?= htmlspecialchars($s['label']) ?>: <?= number_format($s['duration_ms'], 3) ?>ms (<?= number_format($s['start_ms'], 2) ?>–<?= number_format($s['end_ms'], 2) ?>ms)">
                                    </div>
                                    <?php foreach ($marks as $m): ?>
                                    <div class="timeline-mark" style="left:<?= number_format(($m['at_ms'] / $maxMs) * 100, 2) ?>%;"
                                         title="<?= htmlspecialchars($m['label']) ?> @ <?= number_format($m['at_ms'], 2) ?>ms"></div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td style="width:90px;text-align:right;color:#d2a8ff;font-size:0.8rem;padding:3px 8px;white-space:nowrap;">
                                <?= number_format($s['duration_ms'], 3) ?> ms
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Marks -->
        <?php if (!empty($marks)): ?>
        <div class="panel panel-full" style="margin-top:1.25rem;">
            <div class="panel-title"><span class="icon">&#128205;</span> Lifecycle Marks</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($marks as $m): ?>
                    <tr>
                        <td style="width:200px;"><?= htmlspecialchars($m['label']) ?></td>
                        <td style="color:#8b949e;"><?= htmlspecialchars($m['category']) ?></td>
                        <td class="val-num" style="text-align:right;width:100px;"><?= number_format($m['at_ms'], 3) ?> ms</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Included Files -->
        <div class="panel panel-full" style="margin-top:1.25rem;">
            <div class="panel-title"><span class="icon">&#128196;</span> Included Files (<?= count($files) ?>)
                &mdash; <?= number_format(array_sum(array_column($files, 'size')) / 1024, 1) ?> KB total</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($files as $i => $f): ?>
                    <tr class="file-row">
                        <td style="color:#484f58;width:30px;"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($f['path']) ?></td>
                        <td><?= number_format($f['size'] / 1024, 1) ?> KB</td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Routes Tab -->
    <div class="tab-content" id="tab-routes">
        <div class="panel panel-full">
            <div class="panel-title"><span class="icon">&#128268;</span> Registered Routes (<?= count($routes) ?>)</div>
            <div class="panel-body">
                <table>
                    <tr style="border-bottom:1px solid #30363d;">
                        <td style="color:#58a6ff;font-weight:600;width:80px">Method</td>
                        <td style="color:#58a6ff;font-weight:600;">Path</td>
                        <td style="color:#58a6ff;font-weight:600;">Action</td>
                        <td style="color:#58a6ff;font-weight:600;">Name</td>
                        <td style="color:#58a6ff;font-weight:600;">Middleware</td>
                    </tr>
                    <?php foreach ($routes as $r): ?>
                    <tr>
                        <td>
                            <?php $cls = match(strtolower($r['method'])) {
                                'get'=>'badge-get','post'=>'badge-post','put'=>'badge-put',
                                'delete'=>'badge-delete','patch'=>'badge-patch',default=>'badge-any',
                            }; ?>
                            <span class="badge <?= $cls ?>"><?= htmlspecialchars($r['method']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($r['path']) ?></td>
                        <td style="color:#d2a8ff;"><?= htmlspecialchars($r['action']) ?></td>
                        <td style="color:#8b949e;"><?= htmlspecialchars($r['name']) ?></td>
                        <td style="color:#8b949e;"><?= htmlspecialchars($r['middleware']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Request Tab -->
    <div class="tab-content" id="tab-request">
        <div class="panel">
            <div class="panel-title"><span class="icon">&#128229;</span> Current Request</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($request as $k => $v): ?>
                    <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars((string)$v) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Database Tab -->
    <div class="tab-content" id="tab-database">
        <div class="panel">
            <div class="panel-title"><span class="icon">&#128451;</span> Connection</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($database as $k => $v): ?>
                    <tr><td><?= htmlspecialchars($k) ?></td>
                        <td class="<?= $v === 'connected' ? 'val-true' : '' ?>"><?= htmlspecialchars((string)$v) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Config Tab -->
    <div class="tab-content" id="tab-config">
        <div class="panel">
            <div class="panel-title"><span class="icon">&#128196;</span> Configuration</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($config as $k => $v): ?>
                    <tr><td><?= htmlspecialchars($k) ?></td>
                        <td class="<?= $v === 'true' ? 'val-true' : ($v === 'false' ? 'val-false' : '') ?>">
                            <?= htmlspecialchars($v) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Packages Tab -->
    <div class="tab-content" id="tab-packages">
        <div class="panel">
            <div class="panel-title"><span class="icon">&#128230;</span> Composer Packages (<?= count($packages) ?>)</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($packages as $k => $v): ?>
                    <tr><td><?= htmlspecialchars($k) ?></td><td class="val-num"><?= htmlspecialchars($v) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Server Tab -->
    <div class="tab-content" id="tab-server">
        <div class="panel">
            <div class="panel-title"><span class="icon">&#128421;</span> Server Info</div>
            <div class="panel-body">
                <table>
                    <?php foreach ($server as $k => $v): ?>
                    <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="footer">
    SilverEngine Debug Panel &middot; Only available when APP_DEBUG=true
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    const tab = document.querySelector('.tab[data-tab="' + name + '"]');
    const content = document.getElementById('tab-' + name);
    if (tab && content) {
        tab.classList.add('active');
        content.classList.add('active');
    }
}

document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const name = tab.dataset.tab;
        switchTab(name);
        history.replaceState(null, '', '?tab=' + name);
    });
});

// Deep-link: ?tab=config
const params = new URLSearchParams(location.search);
if (params.has('tab')) { switchTab(params.get('tab')); }
</script>
</body>
</html>
