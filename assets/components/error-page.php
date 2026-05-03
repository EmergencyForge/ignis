<?php
/**
 * Error page matching the ignis design system.
 *
 * Available variables from ErrorHandler:
 * - $exception: ?Throwable - the exception (null for fatal errors)
 * - $errorMessage: string - the error message
 * - $isDev: bool - whether development mode is active
 */

$exceptionClass = $exception ? get_class($exception) : 'Fatal Error';
$exceptionCode = $exception ? $exception->getCode() : 0;
$exceptionFile = $exception ? $exception->getFile() : ($error['file'] ?? 'unknown');
$exceptionLine = $exception ? $exception->getLine() : ($error['line'] ?? 0);
$trace = $exception ? $exception->getTrace() : [];
$phpVersion = PHP_VERSION;

// Read version info
$versionFile = dirname(__DIR__, 2) . '/storage/version.json';
if (!is_file($versionFile)) {
    // Legacy-Fallback falls Migration noch nicht durchgelaufen ist
    $legacy = dirname(__DIR__, 2) . '/system/updates/version.json';
    if (is_file($legacy)) {
        $versionFile = $legacy;
    }
}
$versionInfo = is_file($versionFile) ? json_decode(file_get_contents($versionFile), true) : null;
$appVersion = $versionInfo['version'] ?? 'unknown';

// Request info
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
$requestUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
$requestHeaders = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headerName = str_replace('_', '-', substr($key, 5));
        $requestHeaders[$headerName] = $value;
    }
}

if (!function_exists('_err_readSource')) {
    /** @return array<int, string> */
    function _err_readSource(string $file, int $line, int $padding = 8): array {
        if (!is_file($file) || !is_readable($file)) return [];
        $lines = @file($file);
        if ($lines === false) return [];
        $start = max(0, $line - $padding - 1);
        $end = min(count($lines), $line + $padding);
        $result = [];
        for ($i = $start; $i < $end; $i++) {
            $result[$i + 1] = rtrim($lines[$i]);
        }
        return $result;
    }

    function _err_shortenPath(string $path): string {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $path = str_replace('\\', '/', $path);
        return str_starts_with($path, $root) ? ltrim(substr($path, strlen($root)), '/') : $path;
    }

    function _err_isVendor(string $file): bool {
        return str_contains(str_replace('\\', '/', $file), '/vendor/');
    }

    function _err_classBasename(string $class): string {
        return basename(str_replace('\\', '/', $class));
    }
}

// Build structured frames
$frames = [];
if ($exception) {
    $frames[] = [
        'file' => $exceptionFile, 'line' => $exceptionLine,
        'class' => '', 'function' => '',
        'is_vendor' => _err_isVendor($exceptionFile),
    ];
    foreach ($trace as $t) {
        $frames[] = [
            'file' => $t['file'] ?? 'unknown', 'line' => $t['line'] ?? 0,
            'class' => $t['class'] ?? '', 'function' => $t['function'] ?? '',
            'is_vendor' => _err_isVendor($t['file'] ?? ''),
        ];
    }
}
$appFrames = array_filter($frames, fn($f) => !$f['is_vendor']);
$vendorFrames = array_filter($frames, fn($f) => $f['is_vendor']);
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(_err_classBasename($exceptionClass)) ?> - Fehler</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '/' ?>assets/fonts/geist/css/all.min.css">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '/' ?>assets/fonts/geist-mono/css/all.min.css">
    <style>
        :root {
            --body-bg: #2b2930;
            --body-bg-darker: #232128;
            --body-bg-lighter: #35323c;
            --main-color: <?= defined('SYSTEM_COLOR') ? SYSTEM_COLOR : '#d10000' ?>;
            --text-title: #fff;
            --text-normal: #bbbac1;
            --text-dimmed: #818189;
            --darkgray: #3d3a44;
            --input-bg: #25242c;
            --font-mono: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        body {
            font-family: 'Geist', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--body-bg);
            color: var(--text-normal);
            min-height: 100vh;
        }

        * { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

        a { color: #7ba3d4; text-decoration: none; }
        a:hover { color: #92b5e0; text-decoration: underline; }

        /* Error Header */
        .err-header {
            background: var(--body-bg-darker);
            border-bottom: 1px solid var(--darkgray);
            padding: 1.25rem 0;
        }
        .err-exception-name {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--text-title);
            margin-bottom: 0.15rem;
        }
        .err-exception-fqcn {
            font-size: 0.78rem;
            color: var(--text-dimmed);
            font-family: var(--font-mono);
            margin-bottom: 0.6rem;
        }
        .err-message {
            font-size: 1rem;
            color: var(--text-normal);
            line-height: 1.5;
        }
        .err-url-bar {
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-mono);
            font-size: 0.82rem;
            color: var(--text-dimmed);
        }

        /* Badges - matching admin.css */
        .err-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75em;
            letter-spacing: 0.02em;
            padding: 0.35em 0.65em;
        }
        .err-badge-danger { background: #b03a3a; color: #fff; }
        .err-badge-primary { background: #4a6fa5; color: #fff; }
        .err-badge-warning { background: #c49a2a; color: #fff; }
        .err-badge-info { background: #2a7f8f; color: #fff; }
        .err-badge-success { background: #3a7d44; color: #fff; }
        .err-badge-dark { background: #2a2830; color: #fff; border: 1px solid var(--darkgray); }
        .err-badge-secondary { background: var(--darkgray); color: #fff; }
        .err-badge-main { background: var(--main-color); color: #fff; }

        /* Tiles - matching .intra__tile */
        .err-tile {
            background-color: var(--body-bg-lighter);
            border-radius: 12px;
            padding: 1rem 1.25rem;
        }

        /* Tabs */
        .err-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid var(--darkgray);
            margin-bottom: 1.25rem;
        }
        .err-tab {
            background: none;
            border: none;
            color: var(--text-dimmed);
            padding: 0.65rem 1rem;
            cursor: pointer;
            font-size: 0.88rem;
            font-family: inherit;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
        }
        .err-tab:hover { color: var(--text-normal); }
        .err-tab.active { color: var(--text-title); border-bottom-color: var(--main-color); }
        .err-tab-content { display: none; }
        .err-tab-content.active { display: block; }

        /* Stack Trace */
        .err-stack-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 1rem;
            min-height: 450px;
        }
        .err-frame-list {
            background: var(--body-bg-lighter);
            border-radius: 12px;
            overflow-y: auto;
            max-height: 550px;
        }
        .err-frame-group {
            padding: 0.5rem 0.85rem;
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--text-dimmed);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: var(--body-bg-darker);
            border-bottom: 1px solid var(--darkgray);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            user-select: none;
        }
        .err-frame-group:first-child { border-radius: 12px 12px 0 0; }
        .err-frame-group:hover { background: var(--body-bg); }
        .err-frame-group .chevron { transition: transform 0.2s; font-size: 0.6rem; }
        .err-frame-group.collapsed .chevron { transform: rotate(-90deg); }
        .err-frame-group-body.collapsed { display: none; }

        .err-frame {
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            cursor: pointer;
            transition: background 0.1s;
        }
        .err-frame:hover { background: rgba(255,255,255,0.04); }
        .err-frame.active {
            background: rgba(var(--main-color-rgb, 209,0,0), 0.1);
            border-left: 3px solid var(--main-color);
        }
        .err-frame-fn {
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--text-title);
            word-break: break-all;
        }
        .err-frame-loc {
            font-size: 0.72rem;
            color: var(--text-dimmed);
            margin-top: 0.1rem;
        }

        /* Source Viewer */
        .err-source {
            background: var(--body-bg-lighter);
            border-radius: 12px;
            overflow: hidden;
        }
        .err-source-header {
            background: var(--body-bg-darker);
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid var(--darkgray);
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--text-dimmed);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .err-source-code {
            overflow-x: auto;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            line-height: 1.7;
        }
        .err-src-line {
            display: flex;
            padding: 0 0.85rem 0 0;
            white-space: pre;
        }
        .err-src-line.highlight { background: rgba(176,58,58,0.15); }
        .err-src-num {
            display: inline-block;
            min-width: 3.5rem;
            padding: 0 0.65rem;
            text-align: right;
            color: rgba(255,255,255,0.2);
            user-select: none;
            flex-shrink: 0;
        }
        .err-src-line.highlight .err-src-num { color: #d46b6b; font-weight: 600; }

        /* Info Tables */
        .err-info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .err-info-table tr { border-bottom: 1px solid rgba(255,255,255,0.04); }
        .err-info-table tr:last-child { border-bottom: none; }
        .err-info-table td { padding: 0.45rem 0.85rem; vertical-align: top; }
        .err-info-table .k {
            color: var(--text-dimmed);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            white-space: nowrap;
            width: 1%;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .err-info-table .v {
            color: var(--text-normal);
            font-family: var(--font-mono);
            font-size: 0.78rem;
            word-break: break-all;
        }

        /* Buttons matching admin.css */
        .err-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            font-family: inherit;
        }
        .err-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.25); text-decoration: none; }
        .err-btn-main { background: var(--main-color); color: #fff; }
        .err-btn-main:hover { color: #fff; }
        .err-btn--secondary { background: var(--darkgray); color: #fff; }
        .err-btn--secondary:hover { background: #4a4752; color: #fff; }
        .err-btn-ghost {
            background: transparent;
            border: none;
            color: var(--text-normal);
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }
        .err-btn-ghost:hover { background: rgba(255,255,255,0.06); color: #fff; transform: none; box-shadow: none; }

        /* Error ID box */
        .err-error-id-box {
            background: var(--body-bg-darker);
            border: 1px dashed var(--darkgray);
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        .err-error-id-label {
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--text-dimmed);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.35rem;
        }
        .err-error-id-value {
            font-family: var(--font-mono);
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-title);
            letter-spacing: 0.12em;
            user-select: all;
        }

        /* Production card */
        .err-prod-card {
            max-width: 520px;
            margin: 3rem auto;
        }
        .err-prod-meta {
            background: var(--body-bg-darker);
            border-radius: 8px;
            border: 1px solid var(--darkgray);
            padding: 0.85rem;
        }
        .err-prod-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.35rem 0;
            font-size: 0.82rem;
        }
        .err-prod-row + .err-prod-row {
            border-top: 1px solid rgba(255,255,255,0.04);
            margin-top: 0.35rem;
            padding-top: 0.7rem;
        }
        .err-prod-label { color: var(--text-dimmed); font-weight: 500; }
        .err-prod-value { color: var(--text-normal); font-family: var(--font-mono); font-size: 0.78rem; }

        /* Responsive */
        @media (max-width: 960px) {
            .err-stack-layout { grid-template-columns: 1fr; }
            .err-frame-list { max-height: 280px; }
        }
    </style>
</head>
<body>
    <div class="err-header">
        <div class="container">
            <?php if ($isDev): ?>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="err-ignis-chip err-badge-danger">500</span>
                    <span class="err-ignis-chip err-badge-primary"><?= htmlspecialchars($requestMethod) ?></span>
                    <span class="err-ignis-chip err-badge-warning">UNHANDLED</span>
                    <span class="err-ignis-chip err-badge-dark">ıgnıs <?= htmlspecialchars($appVersion) ?></span>
                    <span class="err-ignis-chip err-badge-secondary">PHP <?= htmlspecialchars($phpVersion) ?></span>
                    <?php if ($exceptionCode): ?>
                        <span class="err-ignis-chip err-badge-danger">CODE <?= htmlspecialchars((string)$exceptionCode) ?></span>
                    <?php endif; ?>
                </div>
                <div class="err-exception-name"><?= htmlspecialchars(_err_classBasename($exceptionClass)) ?></div>
                <div class="err-exception-fqcn"><?= htmlspecialchars($exceptionClass) ?></div>
                <div class="err-message"><?= htmlspecialchars($errorMessage) ?></div>
                <div class="err-url-bar">
                    <span class="err-ignis-chip err-badge-danger" style="font-size:0.7em"><?= htmlspecialchars($requestMethod) ?></span>
                    <?= htmlspecialchars($requestUrl) ?>
                </div>
                <div class="flex gap-2 mt-3">
                    <button type="button" class="err-btn-ghost" id="copyMarkdownBtn" onclick="copyErrorAsMarkdown()">
                        <i class="fa-brands fa-markdown"></i> Als Markdown kopieren
                    </button>
                    <?php if (!empty($errorId)): ?>
                        <button type="button" class="err-btn-ghost" id="copyDevIdBtn" onclick="copyDevErrorId()">
                            <i class="fa-regular fa-copy"></i> <?= htmlspecialchars($errorId) ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="err-ignis-chip err-badge-danger">500</span>
                    <span class="err-ignis-chip err-badge-dark">ıgnıs <?= htmlspecialchars($appVersion) ?></span>
                </div>
                <div class="err-exception-name">Serverfehler</div>
                <div class="err-message">Es ist ein interner Fehler aufgetreten.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container py-4">
    <?php if (!$isDev): ?>
        <!-- ========== PRODUCTION ========== -->
        <div class="err-prod-ignis-card">
            <div class="err-tile text-center">
                <div class="mb-3">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; color: var(--main-color);"></i>
                </div>
                <h5 class="text-white mb-2">Ein unerwarteter Fehler ist aufgetreten</h5>
                <p class="text-[var(--text-dimmed,#818189)] mb-4" style="font-size:0.88rem;">
                    Der Fehler wurde automatisch protokolliert. Bitte teilen Sie den untenstehenden
                    Fehlercode dem Administrator mit, damit der Fehler identifiziert werden kann.
                </p>

                <?php if (!empty($errorId)): ?>
                    <div class="err-error-id-box mb-4">
                        <div class="err-error-id-label">Fehlercode</div>
                        <div class="err-error-id-value" id="errorIdValue"><?= htmlspecialchars($errorId) ?></div>
                        <button class="err-btn-ghost" onclick="copyErrorId()" id="copyIdBtn" style="margin-top: 0.5rem">
                            <i class="fa-regular fa-copy"></i> Fehlercode kopieren
                        </button>
                    </div>
                <?php endif; ?>

                <div class="err-prod-meta mb-4">
                    <div class="err-prod-row">
                        <span class="err-prod-label">Zeitpunkt</span>
                        <span class="err-prod-value"><?= date('d.m.Y H:i:s') ?></span>
                    </div>
                    <div class="err-prod-row">
                        <span class="err-prod-label">Request</span>
                        <span class="err-prod-value"><?= htmlspecialchars($requestMethod) ?> <?= htmlspecialchars(parse_url($requestUrl, PHP_URL_PATH) ?: '/') ?></span>
                    </div>
                </div>

                <div class="flex gap-2 justify-center">
                    <a href="javascript:history.back()" class="err-btn err-btn-main">
                        <i class="fa-solid fa-arrow-left"></i> Zurück
                    </a>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '/' ?>" class="err-btn err-btn--secondary">
                        <i class="fa-solid fa-house"></i> Startseite
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ========== DEVELOPMENT ========== -->
        <div class="err-tabs">
            <button class="err-tab active" data-tab="trace"><i class="fa-solid fa-layer-group mr-1"></i> Stack Trace</button>
            <button class="err-tab" data-tab="request"><i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Request</button>
            <button class="err-tab" data-tab="app"><i class="fa-solid fa-gear mr-1"></i> App</button>
        </div>

        <!-- Stack Trace -->
        <div class="err-tab-content active" id="tab-trace">
            <?php if (!empty($frames)): ?>
                <div class="err-stack-layout">
                    <div class="err-frame-list">
                        <?php if (!empty($appFrames)): ?>
                            <div class="err-frame-group" onclick="toggleGroup(this)">
                                <span class="chevron"><i class="fa-solid fa-chevron-down" style="font-size:0.55rem"></i></span>
                                <?= count($appFrames) ?> Application Frame<?= count($appFrames) !== 1 ? 's' : '' ?>
                            </div>
                            <div class="err-frame-group-body">
                                <?php foreach ($appFrames as $i => $frame): ?>
                                    <div class="err-frame <?= $i === 0 ? 'active' : '' ?>" onclick="selectFrame(this, <?= $i ?>)">
                                        <div class="err-frame-fn">
                                            <?php if ($frame['class']): ?>
                                                <?= htmlspecialchars($frame['class']) ?>::<?= htmlspecialchars($frame['function']) ?>()
                                            <?php elseif ($frame['function']): ?>
                                                <?= htmlspecialchars($frame['function']) ?>()
                                            <?php else: ?>
                                                <span style="color:#d46b6b">throw</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="err-frame-loc"><?= htmlspecialchars(_err_shortenPath($frame['file'])) ?>:<?= $frame['line'] ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($vendorFrames)): ?>
                            <div class="err-frame-group <?= !empty($appFrames) ? 'collapsed' : '' ?>" onclick="toggleGroup(this)">
                                <span class="chevron"><i class="fa-solid fa-chevron-down" style="font-size:0.55rem"></i></span>
                                <?= count($vendorFrames) ?> Vendor Frame<?= count($vendorFrames) !== 1 ? 's' : '' ?>
                            </div>
                            <div class="err-frame-group-body <?= !empty($appFrames) ? 'collapsed' : '' ?>">
                                <?php foreach ($vendorFrames as $i => $frame): ?>
                                    <div class="err-frame" onclick="selectFrame(this, <?= $i ?>)">
                                        <div class="err-frame-fn">
                                            <?php if ($frame['class']): ?>
                                                <?= htmlspecialchars($frame['class']) ?>::<?= htmlspecialchars($frame['function']) ?>()
                                            <?php elseif ($frame['function']): ?>
                                                <?= htmlspecialchars($frame['function']) ?>()
                                            <?php else: ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </div>
                                        <div class="err-frame-loc"><?= htmlspecialchars(_err_shortenPath($frame['file'])) ?>:<?= $frame['line'] ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="err-source">
                        <div class="err-source-header">
                            <span id="srcPath"><?= htmlspecialchars(_err_shortenPath($exceptionFile)) ?>:<?= $exceptionLine ?></span>
                            <button class="err-btn-ghost" onclick="copySrc()"><i class="fa-regular fa-copy"></i> Copy</button>
                        </div>
                        <div class="err-source-code" id="srcCode">
                            <?php $initSrc = _err_readSource($exceptionFile, $exceptionLine);
                            foreach ($initSrc as $num => $content): ?>
                                <div class="err-src-line <?= $num === $exceptionLine ? 'highlight' : '' ?>">
                                    <span class="err-src-num"><?= $num ?></span>
                                    <span class="err-src-text"><?= htmlspecialchars($content) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <script id="frameSrcData" type="application/json"><?php
                    $srcData = [];
                    foreach ($frames as $i => $frame) {
                        $f = $frame['file']; $l = $frame['line'];
                        if ($f && $f !== 'unknown' && $l > 0) {
                            $srcData[$i] = ['file' => _err_shortenPath($f), 'line' => $l, 'lines' => _err_readSource($f, $l)];
                        }
                    }
                    echo json_encode($srcData, JSON_HEX_TAG | JSON_HEX_AMP);
                ?></script>
            <?php else: ?>
                <div class="err-tile">
                    <pre style="font-family:var(--font-mono);font-size:0.82rem;white-space:pre-wrap;margin:0;color:var(--text-normal)"><?= htmlspecialchars($errorMessage) ?></pre>
                </div>
            <?php endif; ?>
        </div>

        <!-- Request -->
        <div class="err-tab-content" id="tab-request">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div>
                    <div class="err-tile mb-3">
                        <h6 class="text-white mb-3"><i class="fa-solid fa-arrow-down-short-wide mr-1"></i> Headers</h6>
                        <table class="err-info-table">
                            <?php foreach ($requestHeaders as $name => $value): ?>
                                <tr><td class="k"><?= htmlspecialchars($name) ?></td><td class="v"><?= htmlspecialchars($value) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (empty($requestHeaders)): ?>
                                <tr><td class="v" colspan="2" style="color:var(--text-dimmed)">// NO HEADERS</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="err-tile">
                        <h6 class="text-white mb-3"><i class="fa-solid fa-file-lines mr-1"></i> Body</h6>
                        <?php $body = file_get_contents('php://input');
                        if ($body): ?>
                            <pre style="font-family:var(--font-mono);font-size:0.78rem;white-space:pre-wrap;margin:0;color:var(--text-normal)"><?= htmlspecialchars($body) ?></pre>
                        <?php else: ?>
                            <p class="text-center mb-0" style="color:var(--text-dimmed);font-family:var(--font-mono);font-size:0.78rem">// NO REQUEST BODY</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <?php if (!empty($_GET)): ?>
                        <div class="err-tile mb-3">
                            <h6 class="text-white mb-3"><i class="fa-solid fa-question mr-1"></i> Query Parameters</h6>
                            <table class="err-info-table">
                                <?php foreach ($_GET as $key => $value): ?>
                                    <tr><td class="k"><?= htmlspecialchars($key) ?></td><td class="v"><?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_POST)): ?>
                        <div class="err-tile mb-3">
                            <h6 class="text-white mb-3"><i class="fa-solid fa-paper-plane mr-1"></i> POST Data</h6>
                            <table class="err-info-table">
                                <?php foreach ($_POST as $key => $value): ?>
                                    <tr><td class="k"><?= htmlspecialchars($key) ?></td><td class="v"><?= htmlspecialchars(is_array($value) ? json_encode($value) : $value) ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_COOKIE)): ?>
                        <div class="err-tile mb-3">
                            <h6 class="text-white mb-3"><i class="fa-solid fa-cookie-bite mr-1"></i> Cookies</h6>
                            <table class="err-info-table">
                                <?php foreach ($_COOKIE as $key => $value): ?>
                                    <tr><td class="k"><?= htmlspecialchars($key) ?></td><td class="v"><?= htmlspecialchars(is_string($value) ? $value : json_encode($value)) ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="err-tile">
                        <h6 class="text-white mb-3"><i class="fa-solid fa-key mr-1"></i> Session</h6>
                        <table class="err-info-table">
                            <?php if (isset($_SESSION) && !empty($_SESSION)): ?>
                                <?php foreach ($_SESSION as $key => $value): ?>
                                    <tr><td class="k"><?= htmlspecialchars($key) ?></td><td class="v"><?= htmlspecialchars(is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></td></tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td class="v" colspan="2" style="color:var(--text-dimmed)">// NO SESSION DATA</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- App -->
        <div class="err-tab-content" id="tab-app">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div>
                    <div class="err-tile mb-3">
                        <h6 class="text-white mb-3"><i class="fa-solid fa-route mr-1"></i> Routing</h6>
                        <table class="err-info-table">
                            <tr><td class="k">Script</td><td class="v"><?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '-') ?></td></tr>
                            <tr><td class="k">Request URI</td><td class="v"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '-') ?></td></tr>
                            <tr><td class="k">Method</td><td class="v"><?= htmlspecialchars($requestMethod) ?></td></tr>
                        </table>
                    </div>

                    <div class="err-tile">
                        <h6 class="text-white mb-3"><i class="fa-solid fa-server mr-1"></i> Environment</h6>
                        <table class="err-info-table">
                            <tr><td class="k">APP_ENV</td><td class="v"><?= htmlspecialchars($_ENV['APP_ENV'] ?? 'not set') ?></td></tr>
                            <tr><td class="k">PHP</td><td class="v"><?= htmlspecialchars($phpVersion) ?></td></tr>
                            <tr><td class="k">ıgnıs</td><td class="v"><?= htmlspecialchars($appVersion) ?></td></tr>
                            <tr><td class="k">Server</td><td class="v"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '-') ?></td></tr>
                            <tr><td class="k">Doc Root</td><td class="v"><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="err-tile">
                        <h6 class="text-white mb-3"><i class="fa-solid fa-puzzle-piece mr-1"></i> PHP Extensions</h6>
                        <div style="font-family:var(--font-mono);font-size:0.78rem;color:var(--text-dimmed);column-count:2;column-gap:1.5rem">
                            <?php foreach (get_loaded_extensions() as $ext): ?>
                                <div style="padding:0.1rem 0"><?= htmlspecialchars($ext) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>

    <?php if (!$isDev && !empty($errorId)): ?>
    <script>
        function copyErrorId() {
            const id = document.getElementById('errorIdValue').textContent;
            navigator.clipboard.writeText(id).then(() => {
                const btn = document.getElementById('copyIdBtn');
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Kopiert!';
                setTimeout(() => btn.innerHTML = orig, 1500);
            });
        }
    </script>
    <?php endif; ?>

    <?php if ($isDev): ?>
    <?php
    // ========================================================================
    //  Markdown-Report bauen — wird vom "Als Markdown kopieren"-Button genutzt.
    //  Format ist bewusst Issue-/Discord-/Slack-freundlich: Code-Fences für
    //  Trace + Source, Backticks für File-Pfade, Headings als ##/###.
    // ========================================================================
    $_md = [];
    $_md[] = '## ' . _err_classBasename($exceptionClass);
    $_md[] = '';
    $_md[] = '> ' . trim(str_replace("\n", "\n> ", $errorMessage));
    $_md[] = '';

    $_metaRows = [];
    $_metaRows[] = '| Feld | Wert |';
    $_metaRows[] = '|---|---|';
    $_metaRows[] = '| Exception | `' . $exceptionClass . '` |';
    if ($exceptionCode !== null && $exceptionCode !== '' && $exceptionCode !== 0) {
        $_metaRows[] = '| Code | `' . $exceptionCode . '` |';
    }
    if (!empty($errorId)) {
        $_metaRows[] = '| Error-ID | `' . $errorId . '` |';
    }
    $_metaRows[] = '| Zeitpunkt | ' . date('Y-m-d H:i:s') . ' |';
    $_metaRows[] = '| Method | `' . $requestMethod . '` |';
    $_metaRows[] = '| URL | `' . $requestUrl . '` |';
    $_metaRows[] = '| PHP | ' . $phpVersion . ' |';
    $_metaRows[] = '| ıgnıs | ' . $appVersion . ' |';
    if (!empty($_ENV['APP_ENV'])) {
        $_metaRows[] = '| APP_ENV | `' . $_ENV['APP_ENV'] . '` |';
    }
    $_md[] = implode("\n", $_metaRows);
    $_md[] = '';

    // Stack-Trace (kompakte Form)
    if (!empty($frames)) {
        $_md[] = '### Stack Trace';
        $_md[] = '';
        $_md[] = '```';
        foreach ($frames as $i => $f) {
            $_fn = '';
            if (!empty($f['class'])) {
                $_fn = $f['class'] . '::' . ($f['function'] ?? '') . '()';
            } elseif (!empty($f['function'])) {
                $_fn = $f['function'] . '()';
            } else {
                $_fn = '{main}';
            }
            $_loc = ($f['file'] ?? '[internal]') . (!empty($f['line']) ? ':' . $f['line'] : '');
            $_md[] = '#' . $i . ' ' . $_loc . ' — ' . $_fn;
        }
        $_md[] = '```';
        $_md[] = '';
    }

    // Application-Frames hervorgehoben (oft das was wirklich relevant ist)
    if (!empty($appFrames)) {
        $_md[] = '### Application Frames';
        $_md[] = '';
        foreach ($appFrames as $i => $f) {
            $_fn = '';
            if (!empty($f['class'])) {
                $_fn = $f['class'] . '::' . ($f['function'] ?? '') . '()';
            } elseif (!empty($f['function'])) {
                $_fn = $f['function'] . '()';
            }
            $_loc = _err_shortenPath($f['file'] ?? '[internal]') . (!empty($f['line']) ? ':' . $f['line'] : '');
            $_md[] = ($i + 1) . '. `' . $_loc . '` — ' . $_fn;
        }
        $_md[] = '';
    }

    // Request-Daten (nur wenn nicht leer, knapp gehalten)
    if (!empty($_GET) || !empty($_POST)) {
        $_md[] = '### Request Data';
        $_md[] = '';
        if (!empty($_GET)) {
            $_md[] = '**Query:**';
            $_md[] = '```json';
            $_md[] = json_encode($_GET, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $_md[] = '```';
        }
        if (!empty($_POST)) {
            $_md[] = '**POST:**';
            $_md[] = '```json';
            $_md[] = json_encode($_POST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $_md[] = '```';
        }
        $_md[] = '';
    }

    $_markdownReport = implode("\n", $_md);
    ?>
    <script type="application/json" id="errorMarkdownData"><?= json_encode($_markdownReport, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script>
        function copyErrorAsMarkdown() {
            const dataEl = document.getElementById('errorMarkdownData');
            const md = dataEl ? JSON.parse(dataEl.textContent) : '';
            navigator.clipboard.writeText(md).then(() => {
                const btn = document.getElementById('copyMarkdownBtn');
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Markdown kopiert';
                setTimeout(() => btn.innerHTML = orig, 1800);
            }).catch(err => {
                console.error('Clipboard write failed:', err);
                // Fallback: Textarea + execCommand
                const ta = document.createElement('textarea');
                ta.value = md;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                const btn = document.getElementById('copyMarkdownBtn');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Markdown kopiert';
                setTimeout(() => btn.innerHTML = '<i class="fa-brands fa-markdown"></i> Als Markdown kopieren', 1800);
            });
        }
        function copyDevErrorId() {
            const btn = document.getElementById('copyDevIdBtn');
            const id = btn.textContent.trim();
            navigator.clipboard.writeText(id).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Kopiert';
                setTimeout(() => btn.innerHTML = orig, 1500);
            });
        }
    </script>
    <script>
        document.querySelectorAll('.err-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.err-tab').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.err-tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
            });
        });

        const srcDataEl = document.getElementById('frameSrcData');
        const frameSrcData = srcDataEl ? JSON.parse(srcDataEl.textContent) : {};

        function selectFrame(el, idx) {
            document.querySelectorAll('.err-frame').forEach(f => f.classList.remove('active'));
            el.classList.add('active');
            const d = frameSrcData[idx];
            if (!d) return;
            document.getElementById('srcPath').textContent = d.file + ':' + d.line;
            let h = '';
            for (const [n, c] of Object.entries(d.lines)) {
                const num = parseInt(n);
                h += '<div class="err-src-line ' + (num === d.line ? 'highlight' : '') + '">';
                h += '<span class="err-src-num">' + num + '</span>';
                h += '<span class="err-src-text">' + esc(c) + '</span></div>';
            }
            document.getElementById('srcCode').innerHTML = h;
        }

        function toggleGroup(el) {
            el.classList.toggle('collapsed');
            const body = el.nextElementSibling;
            if (body) body.classList.toggle('collapsed');
        }

        function copySrc() {
            const lines = document.querySelectorAll('#srcCode .err-src-text');
            const text = Array.from(lines).map(l => l.textContent).join('\n');
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.querySelector('.err-btn-ghost');
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                setTimeout(() => btn.innerHTML = orig, 1500);
            });
        }

        function esc(s) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(s));
            return d.innerHTML;
        }
    </script>
    <?php endif; ?>
</body>
</html>
