#!/usr/bin/env php
<?php
declare(strict_types=1);

// ===== CONFIG =====
mb_internal_encoding('UTF-8');
mb_language('uni');
define('FPS', 32);
$frameUs = intdiv(1_000_000, FPS);
define('MIN_TRAIL', 6);
define('MAX_TRAIL', 18);
define('NEW_STREAM_PROB', 0.02);
define('CHAR_CHANGE_PROB', 0.08);
define('HEAD_BRIGHT_PROB', 0.12);
define('FADE_STEPS', 9); // last step = blank

$digits = str_split('0123456789');
$katakanaStr = 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲンヴガギグゲゴザジズゼゾダヂヅデドバビブベボパピプペポァィゥェォャュョッー';
$katakana = preg_split('//u', $katakanaStr, -1, PREG_SPLIT_NO_EMPTY);
$charset = array_merge($digits, $katakana);

// ===== HELPERS =====
function getTerminalSize(): array {
    $out = @shell_exec('stty size 2>/dev/null');
    if ($out) {
        $p = preg_split('/\s+/', trim($out));
        if (count($p) === 2) return [(int)$p[1], (int)$p[0]]; // cols, rows
    }
    return [
        (int) (@shell_exec('tput cols 2>/dev/null') ?: 80),
        (int) (@shell_exec('tput lines 2>/dev/null') ?: 24)
    ];
}
function hideCursor(): void { echo "\033[?25l"; }
function showCursor(): void { echo "\033[?25h"; }
function clearScreen(): void { echo "\033[2J"; }
function moveCursor(int $r, int $c): void { echo "\033[{$r};{$c}H"; }
function resetAttr(): void { echo "\033[0m"; }
function ansi256(int $n): string { return "\033[38;5;{$n}m"; }
function randChar(array $set): string { return $set[random_int(0, count($set)-1)]; }

// ===== FADE PALETTE =====
$fadePalette = [];
for ($i = 0; $i < FADE_STEPS; $i++) {
    $t = 1.0 - ($i / (FADE_STEPS - 1)); // 1..0
    if ($i === FADE_STEPS - 1) {
        $fadePalette[$i] = ''; // last step = disappear
    } elseif ($t > 0.3) {
        $g = (int)round($t * 5);
        $fadePalette[$i] = ansi256(16 + (36*0) + (6*$g) + 0); // green cube
    } else {
        $grayIdx = 232 + (int)round($t / 0.3 * 5);
        $fadePalette[$i] = ansi256(max(232, min(255, $grayIdx)));
    }
}
$whiteHead = ansi256(15);

// ===== CLEANUP =====
$running = true;
register_shutdown_function(function(){
    resetAttr();
    showCursor();
    echo "\033[?1049l";
    clearScreen();
    moveCursor(1,1);
});
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, fn()=> $GLOBALS['running']=false);
    pcntl_signal(SIGTERM, fn()=> $GLOBALS['running']=false);
}

// ===== INIT =====
[$termCols, $termRows] = getTerminalSize();
$cellWidth = max(array_map('mb_strwidth', $charset));
$logicalCols = max(1, intdiv($termCols, $cellWidth));
$streams = array_fill(0, $logicalCols, null);

echo "\033[?1049h"; // alt buffer
hideCursor();
clearScreen();

// ===== MAIN LOOP =====
while ($running) {
    $frameStart = microtime(true);

    // occasional resize check
    static $tick=0; $tick++;
    if ($tick % 10 === 0) {
        [$termCols, $termRows] = getTerminalSize();
        $logicalColsNew = max(1, intdiv($termCols, $cellWidth));
        if ($logicalColsNew !== count($streams)) {
            $streams = array_pad(array_slice($streams,0,$logicalColsNew), $logicalColsNew, null);
        }
    }

    // update streams
    foreach ($streams as $col => $s) {
        if ($s === null) {
            if (mt_rand()/mt_getrandmax() < NEW_STREAM_PROB) {
                $streams[$col] = [
                    'head'=>random_int(-$termRows,0),
                    'len'=>random_int(MIN_TRAIL, MAX_TRAIL),
                    'chars'=>[]
                ];
            }
            continue;
        }
        $s['head']++;
        if ($s['head']>=1 && $s['head'] <= $termRows) {
            $s['chars'][$s['head']] = ['ch'=>randChar($charset),'age'=>0];
        }
        foreach ($s['chars'] as $r=>&$e) {
            if (mt_rand()/mt_getrandmax() < CHAR_CHANGE_PROB) {
                $e['ch']=randChar($charset);
            }
            $e['age']++;
        }
        unset($e);
        $limit = $s['head'] - $s['len'];
        foreach (array_keys($s['chars']) as $r) {
            if ($r < $limit) unset($s['chars'][$r]);
        }
        if ($s['head'] - $s['len'] > $termRows+2) $s=null;
        $streams[$col]=$s;
    }

    // render
    foreach ($streams as $col => $s) {
        if ($s===null) continue;
        foreach ($s['chars'] as $row => $e) {
            $step = min(FADE_STEPS-1, (int)floor($e['age'] / max(1, $s['len']) * FADE_STEPS));
            $color = $fadePalette[$step];
            $dispCol = $col*$cellWidth+1;
            if ($dispCol+$cellWidth-1>$termCols||$row<1||$row>$termRows) continue;
            moveCursor($row, $dispCol);
            if ($color==='') {
                echo str_repeat(' ', $cellWidth); // erase fully
            } else {
                $headBright = ($e['age']===0 && mt_rand()/mt_getrandmax() < HEAD_BRIGHT_PROB);
                echo ($headBright?$whiteHead:$color) . $e['ch']
                     . str_repeat(' ', $cellWidth - mb_strwidth($e['ch']))
                     . "\033[0m";
            }
        }
    }

    fflush(STDOUT);
    $sleepUs = $frameUs - (int)((microtime(true)-$frameStart)*1_000_000);
    if ($sleepUs>0) usleep($sleepUs);
    if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
}

// ===== END =====
resetAttr();
showCursor();
clearScreen();
echo "\033[?1049l";
moveCursor(1,1);
exit(0);
