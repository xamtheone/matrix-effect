<?php
$matrixHeight = `tput lines` - 1;
$matrixWidth = intval(`tput cols` / 2);

define('CELL_LIFE', intdiv($matrixHeight, 1.4));

const CHARS = [
  "ア","イ","ウ","エ","オ",
  "カ","キ","ク","ケ","コ",
  "サ","シ","ス","セ","ソ",
  "タ","チ","ツ","テ","ト",
  "ナ","ニ","ヌ","ネ","ノ",
  "ハ","ヒ","フ","ヘ","ホ",
  "マ","ミ","ム","メ","モ",
  "ヤ","ユ","ヨ",
  "ラ","リ","ル","レ","ロ",
  "ワ","ヰ","ヱ","ヲ",
  "ン",
  "0 ", "1 ","2 ","3 ","4 ","5 ","6 ","7 ","8 ","9 ",
];
const VOID = "  ";

define('COLOR_RANGE', [16, 22, 28, 34, 40, 46, 255]);
class Cell
{
  public function __construct(
    public $char = VOID,
    public $life = CELL_LIFE,
  ) {}
}

function getRandChar() {
  return CHARS[random_int(0, count(CHARS) - 1)];
}

function getMatrix($height, $width)
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

// clear screen
echo "\x1b[2J\x1b[H";

/*
 * Main loop
 *
 * In the top row of the old matrix, pick a random column and spawn a character if the cell is empty
 * Create a new matrix from the old matrix.
 * For a given column in the old matrix, if a character exists in a row and the next (old) row is empty, add a character to the next (new) row.
 * Visible cells age at each iteration of the loop and die when their life reaches zero.
 * The resulting matrix is then rendered.
 */
while (true) {
  // set cursor to top-left
  echo "\x1b[H";

  usleep(60000);

  // pick a random color to set a value and start the rain
  $col = random_int(0, $matrixWidth-1);
  $matrix[0][$col] = new Cell(getRandChar());

  $newMatrix = getMatrix($matrixHeight, $matrixWidth);

  foreach ($matrix as $h => $row) {
    /* @var Cell $cell */
    foreach ($row as $w => $cell) {
      // new cell is empty, copy old cell
      if ($newMatrix[$h][$w]->char == VOID) {
        $newMatrix[$h][$w] = $cell;
      }

      // old cell is not empty and next row cell is empty, generate filled cell on next row
      if ($cell->char != VOID && $h < $matrixHeight - 1 && $matrix[$h + 1][$w]->char == VOID) {
        $newMatrix[$h + 1][$w] = new Cell(getRandChar());
      }

      // current cell is not empty, decrease life
      if ($cell->char != VOID) $cell->life--;

      // life is bellow 0, reset all values to defaults
      if ($cell->life < 0) {
        $cell->life = CELL_LIFE;
        $cell->char = VOID;
      }
      // life is not bellow zero and current cell is not empty, random chance of changing char
      elseif ($cell->char != VOID) {
        if (!random_int(0, 9)) $cell->char = getRandChar();
      }
    }
  }

  $matrix = $newMatrix;

  // Rendering
  $renderBuffer = '';

  foreach ($matrix as $h => $row) {
    foreach ($row as $cell) {
      $ci = floor($cell->life / CELL_LIFE * (count(COLOR_RANGE) - 1));
      $color = COLOR_RANGE[$ci];
      $renderBuffer .= "\x1b[38;5;{$color}m";
      $renderBuffer .= $cell->char;
    }

    $renderBuffer .= "\n";
  }

  echo $renderBuffer;
}
