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

class Cell
{
    public function __construct(
        public string $char = VOID,
        public int $life = CELL_LIFE,
    ) {}
}

function getRandChar(): string
{
    try {
        return CHARS[random_int(0, count(CHARS) - 1)];
    } catch (Exception) {
        return CHARS[rand(0, count(CHARS) - 1)];
    }
}

function getMatrix0(int $height, int $width): array
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

function getMatrix(int $height, int $width): array
{
    $matrix = array_fill(0, $height, []);
    for ($i = 0; $i < $height; $i++) {
        $matrix[$i] = array_fill(0, $width, new Cell());
    }

    return $matrix;
}

$matrix = [];
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
    // set cursor to top-left
    echo "\x1b[H";

    $newMatrix = getMatrix($matrixHeight, $matrixWidth);

    // pick a random column to set a value and start the rain
    $col = random_int(0, $matrixWidth - 1);
    $newMatrix[0][$col] = new Cell(getRandChar());

    $processTime = microtime(true);
    foreach ($matrix as $h => $row) {
        /* @var Cell $cell */
        foreach ($row as $w => $cell) {
            // new cell is empty, copy old cell
            if ($newMatrix[$h][$w]->char == VOID) {
                $newMatrix[$h][$w] = $cell;
            }

            // current cell is not empty
            if ($cell->char != VOID) {
                // decrease life
                $cell->life--;

                // life is bellow 0, reset all values to defaults
                if ($cell->life < 0) {
                    $cell->life = CELL_LIFE;
                    $cell->char = VOID;
                }
                // life is not bellow zero and current cell is not empty, random chance of changing char
                elseif (!random_int(0, 9)) {
                    $cell->char = getRandChar();
                }

                // next row cell is empty, generate filled cell on next row
                if ($h < $matrixHeight - 1 && $matrix[$h + 1][$w]->char == VOID) {
                    $newMatrix[$h + 1][$w] = new Cell(getRandChar());
                }
            }
        }
    }
    
    $matrix = $newMatrix;

    $processTime = microtime(true) - $processTime;

    // Rendering
    $renderingTime = microtime(true);
    foreach ($matrix as $h => $row) {
        $renderBuffer = '';
        foreach ($row as $cell) {
            $ci = floor($cell->life / CELL_LIFE * (count(COLOR_RANGE) - 1));
            $color = COLOR_RANGE[$ci];
            $renderBuffer .= "\x1b[38;5;{$color}m$cell->char";
        }

        echo "$renderBuffer\n";
    }
    $renderingTime = microtime(true) - $renderingTime;
    
    echo "\n\x1b[38;5;255m";
    echo "Matrix size: $matrixHeight x $matrixWidth ";
    echo "Process time: " . number_format($processTime, 4) . " ";
    echo "Rendering time: " . number_format($renderingTime, 4) . " ";
    echo "Memory usage: " . number_format(memory_get_usage() / 1024, 2) . "KB ";
    echo "FPS: " . number_format(1 / (microtime(true) - $frameTime), 2) . " ";
}
