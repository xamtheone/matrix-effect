<?php

$matrixHeight = `tput lines` - 2;
$matrixWidth = intval(`tput cols` / 2);
$fps = 32;

define('CELL_LIFE', (int) ($matrixHeight / 1.4));

const CHARS = [
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
const VOID = '  ';

const COLOR_RANGE = [16, 22, 28, 34, 40, 46, 255];

$colorRangeLastOffset = count(COLOR_RANGE) - 1;

class Cell
{
    public function __construct(
        public string $char = VOID,
        public int $life = CELL_LIFE,
        public string $nextChar = VOID,
    ) {}
}

function getRandChar(): string
{
    return CHARS[rand(0, count(CHARS) - 1)];
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
    $diff = microtime(true) - $elapsedTime;
    $sleepTime = 1 / $fps - $diff;

    if ($sleepTime > 0) {
        usleep($sleepTime * 1_000_000);
    }

    $elapsedTime = microtime(true);

    // pick a random column to set a value and start the rain
    $col = rand(0, $matrixWidth - 1);
    $matrix[0][$col]->nextChar = getRandChar();
    $matrix[0][$col]->life = CELL_LIFE;

    $processTime = microtime(true);
    foreach ($matrix as $h => $row) {
        /* @var Cell $cell */
        foreach ($row as $w => $cell) {
            // current cell is not empty
            if ($cell->char != VOID) {
                // decrease life
                $cell->life--;

                // life is bellow 0, reset all values to defaults
                if ($cell->life < 0) {
                    $cell->life = CELL_LIFE;
                    $cell->nextChar = VOID;
                } // life is not bellow zero and current cell is not empty, random chance of changing char
                elseif (!rand(0, 9)) {
                    $cell->nextChar = getRandChar();
                }

                // next row cell is empty, generate filled cell on next row
                if ($h < $matrixHeight - 1) {
                    $nextRowCell = $matrix[$h + 1][$w];
                    if ($nextRowCell?->char == VOID) {
                        $nextRowCell->nextChar = getRandChar();
                        $nextRowCell->life = CELL_LIFE;
                    }
                }
            }
        }
    }

    foreach ($matrix as $row) {
        foreach ($row as $cell) {
            $cell->char = $cell->nextChar;
        }
    }

    $processTime = microtime(true) - $processTime;

    // Rendering
    $renderingTime = microtime(true);

    // set cursor to top-left
    echo "\x1b[H";

    foreach ($matrix as $row) {
        $renderBuffer = '';
        foreach ($row as $cell) {
            $renderBuffer .= "\x1b[38;5;" . COLOR_RANGE[(int) ($cell->life / CELL_LIFE * $colorRangeLastOffset)] . "m$cell->char";
        }

        echo "$renderBuffer\n";
    }
    $renderingTime = microtime(true) - $renderingTime;

    echo "\n\x1b[38;5;255m";
    echo "{$matrixHeight}x$matrixWidth ";
    echo "Process: " . number_format($processTime, 4) . " ";
    echo "Render: " . number_format($renderingTime, 4) . " ";
    echo "Memory: " . number_format(memory_get_peak_usage() / 1024, 2) . "KB ";
    echo "FPS: " . str_pad(number_format(1 / (microtime(true) - $frameTime), 2), 8, " ", STR_PAD_LEFT) . " ";
}
