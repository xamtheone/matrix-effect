<?php

const CELL_LIFE = 40;
// does not look good without a monospaced font for japanese
//const CHARS = ["ァ","ア","ィ","イ","ゥ","ウ","ェ","エ","ォ","オ","カ","ガ","キ","ギ","ク","グ","ケ","ゲ","コ","ゴ","サ","ザ","シ","ジ","ス","ズ","セ","ゼ","ソ","ゾ","タ","ダ","チ","ヂ","ッ","ツ","ヅ","テ","デ","ト","ド","ナ","ニ","ヌ","ネ","ノ","ハ","バ","パ","ヒ","ビ","ピ","フ","ブ","プ","ヘ","ベ","ペ","ホ","ボ","ポ","マ","ミ","ム","メ","モ","ャ","ヤ","ュ","ユ","ョ","ヨ","ラ","リ","ル","レ","ロ","ヮ","ワ","ヰ","ヱ","ヲ","ン","ヴ","ヵ","ヶ","ヷ","ヸ","ヹ","ヺ"];

define('CHARS', array_merge(range('A', 'z'), range('0', '9')));
class Cell
{
  public function __construct(
    public $char = ' ',
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

$matrixHeight = `tput lines` - 1;
$matrixWidth = `tput cols` - 1;

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
      if ($newMatrix[$h][$w]->char == ' ') {
        $newMatrix[$h][$w] = $cell;
      }

      // old cell is not empty and next row cell is empty, generate filled cell on next row
      if ($cell->char != ' ' && $h < $matrixHeight - 1 && $matrix[$h + 1][$w]->char == ' ') {
        $newMatrix[$h + 1][$w] = new Cell(getRandChar());
      }

      // current cell is not empty, decrease life
      if ($cell->char != ' ') $cell->life--;

      // life is bellow 0, reset all values to defaults
      if ($cell->life < 0) {
        $cell->life = CELL_LIFE;
        $cell->char = ' ';
      }
      // life is not bellow zero and current cell is not empty, random chance of changing char
      elseif ($cell->char != ' ') {
        if (!random_int(0, 9)) $cell->char = getRandChar();
      }
    }
  }

  $matrix = $newMatrix;

  // Rendering
  foreach ($matrix as $h => $row) {
    foreach ($row as $cell) {
      // TODO actually have a real palette for shades of green and clamp values correctly for the original effect
      $color = $cell->life + 100;
      echo "\x1b[38;5;{$color}m";
      echo $cell->char;
    }

    echo "\n";
  }
}