<?php

$matrixHeight = `tput lines` - 2;
$matrixWidth = intval(`tput cols` / 2);
$fps = 32;

define('CELL_LIFE', (int) ($matrixHeight / 1.4));

const CHARS = [
    '  ',
    'ア',
    'イ',
    'ウ',
    'エ',
    'オ',
    'カ',
    'キ',
    'ク',
    'ケ',
    'コ',
    'サ',
    'シ',
    'ス',
    'セ',
    'ソ',
    'タ',
    'チ',
    'ツ',
    'テ',
    'ト',
    'ナ',
    'ニ',
    'ヌ',
    'ネ',
    'ノ',
    'ハ',
    'ヒ',
    'フ',
    'ヘ',
    'ホ',
    'マ',
    'ミ',
    'ム',
    'メ',
    'モ',
    'ヤ',
    'ユ',
    'ヨ',
    'ラ',
    'リ',
    'ル',
    'レ',
    'ロ',
    'ワ',
    'ヰ',
    'ヱ',
    'ヲ',
    'ン',
    '0 ',
    '1 ',
    '2 ',
    '3 ',
    '4 ',
    '5 ',
    '6 ',
    '7 ',
    '8 ',
    '9 ',
];
const VOID = 0;

const COLOR_RANGE = [16, 22, 28, 34, 40, 46, 255];

$colorMapping = [];
for ($i = 0; $i <= CELL_LIFE; $i++) {
    $colorMapping[$i] = COLOR_RANGE[(int)($i / CELL_LIFE * (count(COLOR_RANGE) - 1))];
}

class Cell
{
    public function __construct(
        public int $char = VOID,
        public int $life = CELL_LIFE,
    ) {}
}

function getRandChar(): int
{
    return rand(1, count(CHARS) - 1);
}

function getMatrix(int $height, int $width): array
{
    $matrix = [];
    for ($i = 0; $i < $height; $i++) {
        $matrix[] = [];
        for ($j = 0; $j < $width; $j++) {
            $matrix[$i][] = new Cell();
        }
    }

    return $matrix;
}

$matrix = getMatrix($matrixHeight, $matrixWidth);
$renderingTime = 0;
$elapsedTime = 0;
$avg = array_fill(0, 8, 0.0);
$avgIndex = 0;

// clear screen
echo "\x1b[2J\x1b[H";

/*
 * Main loop
 *
 * In the top row of the old matrix, pick a random column and spawn a character if the cell is empty
 * Create a new matrix from the old matrix.
 * For a given column in the old matrix, if a character exists in a row and the next (old) row is empty,
 * add a character to the next (new) row.
 * Visible cells age at each iteration of the loop and die when their life reaches zero.
 * The resulting matrix is then rendered.
 */
while (true) {
    $frameTime = microtime(true);
    $diff = $frameTime - $elapsedTime;
    $sleepTime = 1 / $fps - $diff;

    if ($sleepTime > 0) {
        usleep($sleepTime * 1_000_000);
    }

    $elapsedTime = microtime(true);
    $processTime = $elapsedTime;

    // pick a random column to set a value and start the rain
    $col = rand(0, $matrixWidth - 1);
    $matrix[0][$col]->char = getRandChar();
    $matrix[0][$col]->life = CELL_LIFE;

    for ($h = $matrixHeight - 1; $h >= 0; $h--) {
        /* @var Cell $cell */
        foreach ($matrix[$h] as $w => $cell) {
            // current cell is not empty
            if ($cell->char != VOID) {
                // decrease life
                $cell->life--;

                // life is bellow 0, reset all values to defaults
                if ($cell->life < 0) {
                    $cell->life = CELL_LIFE;
                    $cell->char = VOID;
                } elseif (!rand(0, 9)) {
                    // life is not bellow zero and current cell is not empty, random chance of changing char
                    $cell->char = getRandChar();
                }
            } elseif ($h > 0 && $matrix[$h - 1][$w]->char != VOID) {
                // Above row cell is not empty and current cell is empty, generate filled cell
                $cell->char = getRandChar();
                $cell->life = CELL_LIFE;
            }
        }
    }

    $endProcess = microtime(true);

    $processTime = $endProcess - $processTime;

    // Rendering
    $renderingTime = $endProcess;

    // set cursor to top-left
    echo "\x1b[H";

    foreach ($matrix as $row) {
        $renderBuffer = '';
        foreach ($row as $cell) {
            $renderBuffer .= "\x1b[38;5;" . $colorMapping[$cell->life] . "m" . CHARS[$cell->char];
        }

        echo "$renderBuffer\n";
    }
    $renderingTime = microtime(true) - $renderingTime;

    $avgIndex = ($avgIndex + 1) % count($avg);
    $avg[$avgIndex] = $processTime + $renderingTime;

    echo "\n\x1b[38;5;255m";
    echo "{$matrixHeight}x$matrixWidth ";
    echo "Process: " . number_format($processTime, 5) . " ";
    echo "Render: " . number_format($renderingTime, 4) . " ";
    echo "Avg total:" . number_format(array_sum($avg) / count($avg), 5) . " ";
    echo "Memory: " . number_format(memory_get_peak_usage() / 1024, 2) . "KB ";
    echo "FPS: " . str_pad(number_format(1 / (microtime(true) - $frameTime), 2), 8, " ", STR_PAD_LEFT) . " ";
}
