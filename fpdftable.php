<?php

require_once __DIR__.'/fpdf.php';
require_once __DIR__.'/lib/color.php';
require_once __DIR__.'/lib/htmlparser.php';

/**
 * Класс FPDFTable предназначен для создания PDF-документа из html-подобной разметки с использованием FPDF.
 * За основу взят PDFTable (http://www.vanxuan.net/tool/pdftable/).
 * Используются классы:
 *     * HTMLParser (Jose Solorzano)
 *     * FPDF (Olivier PLATHEY)
 * Отличия:
 *     * Исправлены некоторые ошибки
 *     * Реструктурирован и форматирован код
 *     * Добавлена настройка отступов внутри ячеек посредством атрибутов td.hpad и td.vpad
 *     * Добавлена настройка междустрочного интервала внутри ячеек атрибутом td.lh
 *     * Междустрочный интервал теперь не фиксированный для всего документа, а пропорционален размеру шрифта
 *     * Поддержка атрибута td:flex
 *
 * @category Extensions
 * @package  FPDFTable
 * @author   Alexander Kulikov <amkulikov92@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl.html GNU GPL
 * @link     https://github.com/amkulikov/FPDFTable
 */
class FPDFTable extends FPDF
{
    /**
     * Текст разметки для отрисовки на каждой странице сразу после её добавления.
     *
     * @var string
     */
    public $headerTable = '';
    /**
     * Текст разметки для отрисовки на каждой странице перед её закрытием.
     *
     * @var string
     */
    public $footerTable = '';
    /**
     * Базовые горизонтальные отступы внутри ячеек.
     * Могут быть переопределены атрибутом td.hpad
     *
     * @var integer
     */
    public $hCellPadding = 1;
    /**
     * Базовые вертикальные отступы внутри ячеек.
     * Могут быть переопределены атрибутом td.vpad
     *
     * @var integer
     */
    public $vCellPadding = 1;
    /**
     * Базовый междустрочный интвервал внутри ячеек.
     * Может быть переопределён атрибутом td.lh
     *
     * @var integer
     */
    public $spacingLine = 1;

    private $aliasPageNum;
    private $aliasPageCount;
    private $encoding;
    private $left;
    private $right; 
    private $top;
    private $bottom;
    private $width; 
    private $height;
    private $defaultFontFamily;
    private $defaultFontStyle;
    private $defaultFontSize;

    /*
     * PUBLIC METHODS
     */

    /**
     * Конструктор класса FPDFTable
     *
     * @param string $orientation Ориентация страниц документа:
     *                            * P or Portrait - Портретная
     *                            * L or Landscape - Альбомная
     *                            (default = 'P')
     * @param string $unit        Единица измерения докумнта:
     *                            * pt: типографский пункт (point)
     *                            * mm: миллиметр
     *                            * cm: сантиметр
     *                            * in: дюйм
     *                            (default = 'mm')
     * @param string $size        Размер документа. Может быть одним из нижеперечисленных:
     *                            * A3
     *                            * A4
     *                            * A5
     *                            * Letter
     *                            * Legal
     *                            или массивом array(ширина,высота) в единицах измерения $unit
     *                            (default = 'A4')
     * @param string $encoding    Кодировка документа. По умолчанию - Cyrillic 1251.
     *                            (default = 'windows-1251')
     */
    public function FPDFTable(
        $orientation = 'P',
        $unit = 'mm',
        $size = 'A4',
        $encoding = 'windows-1251'
        ) {
        parent::FPDF($orientation, $unit, $size);
        if ($orientation === 'P') {
            $this->SetMargins(20, 10, 10);
        } else {
            $this->SetMargins(10, 20, 10);
        }
        $this->SetAliasPageCount();
        $this->SetAliasPageNum();
        $this->encoding = $encoding;
        $this->_makePageSize();
    }

    /**
     * Назначение псевдонима для подстановки количества страниц.   
     *
     * @param string $alias Псевдоним. (default = 'pc')
     */
    public function SetAliasPageCount($alias = '{pc}') {
        parent::AliasNbPages($alias);
        $this->aliasPageCount = $alias;
    }

    /**
     * Назначение псевдонима для подстановки номера страницы.   
     *
     * @param string $alias Псевдоним. (default = 'pn')
     */
    public function SetAliasPageNum($alias = '{pn}') {
        $this->aliasPageNum = $alias;
    }

    /**
     * Установка header'а и footer'а.
     * Также можно просто переопределить Footer() и Header()
     * @param string $header Отрисовывется сразу после AddPage()
     * @param string $footer Отрисовывается в момент закрытия документа или создания следующей страницы
     */
    public function SetHeaderFooter($header = '', $footer = '') {
        if ($header)
            $this->headerTable = $header;
        if ($footer)
            $this->footerTable = $footer;
    }

    /**
     * FPDF override.
     * Установка отступа слева от края страницы
     * @param integer $margin размер отступа
     */
    public function SetLeftMargin($margin) {
        parent::SetLeftMargin($margin);
        $this->_makePageSize();
    }

    /**
     * FPDF override.
     * Установка отступов от края страницы
     * @param int $left   Левый отступ
     * @param integer $top    Отступ сверху
     * @param integer $right  Отступ справа (по умолчанию = левому)
     * @param integer $bottom Отступ снизу (по умолчанию = верхнему)
     */
    public function SetMargins($left, $top, $right = null, $bottom = null) {
        parent::SetMargins($left, $top, $right);
        $this->bMargin = $bottom ? $bottom : $top;
        $this->_makePageSize();
    }

    /**
     * Установка отступов внутри ячеек
     * @param integer $hPadding Горизонтальные отступы
     * @param integer $vPadding Вертикальные отступы
     */
    public function SetCellPaddings($hPadding = 1, $vPadding = 1) {
        $this->hCellPadding = $hPadding;
        $this->vCellPadding = $vPadding;
    }

    /**
     * FPDF override.
     * Установка отступа справа от края страницы
     * @param integer $margin размер отступа
     */
    public function SetRightMargin($margin) {
        parent::SetRightMargin($margin);
        $this->_makePageSize();
    }

    /**
     * Установка междустрочного интервала.
     *
     * @param integer $linespacing Междустрочный интвервал как пропорция от размера текущего шрифта.
     */
    public function SetSpacing($linespacing = 1) {
        $this->spacingLine = $linespacing;
    }

    /**
     * FPDF override.
     * Этот метод используется для отрисовки верхнего колонтитула страницы.
     * Автоматически вызывается методом AddPage() и не должен вызываться вручную из кода.
     * Отрисовывет содержимое атрибута headerTable, но может быть переопределён для выполнения других действий.
     */
    public function Header() {
        $page_number = $this->PageNo();
        $this->_makePageSize();
        if ($this->headerTable) {
            $this->x = $this->left;
            $this->y = 0;
            $this->htmltable(str_replace($this->aliasPageNum, $this->PageNo(), $this->headerTable), 0);
        }
    }

    /**
     * Вывод разметки на документ.
     * Название метода может вводить в заблуждение - это не HTML.
     * Поддерживаемые теги смотреть в документации.
     * @param  string  &$html     Текст разметки. 
     * @param  integer $multipage Разрешить переносить таблицу, если не умещается на одной странице.
     */
    public function htmltable($html, $multipage = 1) {
        $a = $this->AutoPageBreak;
        $this->SetAutoPageBreak(0, $this->bMargin);
        if(!empty($this->encoding) && mb_strtoupper($this->encoding) !== 'UTF-8') {
            $html = iconv('UTF-8', $this->encoding, $html);
        }
        $HTML = explode("<table", $html);
        $oldMargin = $this->cMargin;
        $this->cMargin = 0;
        $x = $this->x;
        foreach ($HTML as $i=>$table) {  
            $this->x = $x;
            if (strlen($table) < 6) {
                continue;
            }
            $table = '<table' . $table;
            $table = $this->_tableParser($table);
            $table['multipage'] = $multipage;
            $this->_tableColumnWidth($table);
            $this->_tableWidth($table);
            $this->_tableHeight($table);
            $this->_tableWrite($table);
        }
        $this->cMargin = $oldMargin;
        $this->SetAutoPageBreak($a, $this->bMargin);
    }

    /**
     * FPDF override.
     * Этот метод используется для отрисовки нижнего колонтитула страницы.
     * Автоматически вызывается методами AddPage() и Close() и не должен вызываться вручную из кода.
     * Отрисовывет содержимое атрибута footerTable, но может быть переопределён для выполнения других действий.
     */
    public function Footer() {
        if ($this->footerTable) {
            $this->x = $this->left;
            $this->y = $this->bottom;
            $this->htmltable(str_replace($this->aliasPageNum, $this->PageNo(), $this->footerTable), 0);
        }
    }

    /**
     * FPDF override.
     * Установка параметров шрифта. Предварительно шрифт должен быть добавлен через AddFont().
     * @param string  $family  Название шрифта, указанное в AddFont
     * @param string  $style   Модификаторы B,I,U либо их комбинация
     * @param integer $size    Размер шрифта в пунктах
     * @param boolean $default Сделать базовым шрифтом для PDFTable (не будет связан с текущим шрифтом FPDF)
     */
    public function SetFont($family, $style = '', $size = 0, $default = false) {
        parent::SetFont($family, $style, $size);
        if ($default || !$this->defaultFontFamily) {
            $this->defaultFontFamily = $family;
            $this->defaultFontSize = $size;
            $this->defaultFontStyle = $style;
        }
    }


    /*
     * PRIVATE METHODS 
     */

    /**
     * Приведение ширины к числовому виду.
     * Если есть знак %, берёт пропорцию от ширины страницы, иначе возвращает просто intval
     *
     * @param  string $w Строковая запись ширины. Может быть также в процентах
     *
     * @return integer   Числовое выражение ширины
     */
    private function _calWidth($w) {
        $p = strpos($w,'%');
        if ($p !== false) {
            return floatval(substr($w, 0, $p) * $this->width / 100);
        } else {
            return floatval($w);
        }
    }

    /**
     * Получение высоты ячейки, а также попутный расчёт расположения контента внутри неё.
     *
     * @param  array    &$c Ссылка на содержимое ячейки
     *
     * @return integer      Высота ячейки
     */
    private function _cellHeight(&$c) {
        /*
         * Горизонтальные и вертикальные отступы внутри ячейки.
         * При наличии hpad или vpad, используются они, иначе берутся базовые
         * из hCellPadding и vCellPadding.
         */
        $hCellPadding = isset($c['hpad']) ? $c['hpad'] : $this->hCellPadding;
        $vCellPadding = isset($c['vpad']) ? $c['vpad'] : $this->vCellPadding;
        
        /*
         * Междустрочный интервал.
         */
        $spacingLine = isset($c['lh']) ? $c['lh'] : $this->spacingLine;

        $maxw = $c['w0'] - $hCellPadding * 2;
        $h = 0;
        $x = $hCellPadding;

        $countline = 0;

        $maxhline = 0;
        /*
         * $c['hline'] - массив относительных расстояний строк внутри ячейки друг от друга сверху вниз.
         * Т.е. например для обычной ячейки с тремя строками текста итоговый $c['hline'] будет выглядеть примерно так:
         * array(4.53,4.53,4.53)
         * Т.е. в этом случае высота первой строки от верхней границы 4.53, высота второй строки от первой 4.53 и т.д.
         */
        $c['hline'] = array();
        $c['wlinet'] = array(array(0, 0));
        $c['wlines'] = array(0);
        $space = 0;

        foreach ($c['font'] as &$f) {
            $this->_setFontText($f);
            $hl = $this->_getLineHeight();
            if ($maxhline == 0 && $x == $hCellPadding) {
                $maxhline = $hl;
                $h += $maxhline * $spacingLine;
                $c['hline'][] = $hl / 2;
            }
            if (!isset($f['space'])) {
                continue;
            }
            $space = $f['space'];
            foreach ($f['line'] as $i=>&$l) {
                if (isset($l['str']) && is_array($l['str'])) {
                    foreach ($l['str'] as &$t) {
                        /*
                         * $t[0] - строка
                         * $t[1] - ширина строки
                         */
                        if (!is_array($t)) {
                            continue;
                        }
                        /*
                         * Если это первое слово в строке
                         * или строка со следующим словом не потребует переноса.
                         */
                        if ($x == $hCellPadding || $x + $t[1] <= $maxw) {
                            $c['wlinet'][$countline][0] += $t[1];
                            $c['wlinet'][$countline][1]++;
                            $x += $t[1] + (($x > $hCellPadding) ? $space : 0);
                        } else {
                            /*
                             * Автоматический разрыв строки
                             */
                            $hpos = $maxhline * $spacingLine;
                            $c['hline'][] = $hpos;
                            $h += $hpos;
                            $c['wlines'][$countline] = $x - $hCellPadding;
                            $c['autobr'][$countline] = 1;
                            $maxhline = $hl;
                            $countline++;
                            $x = $t[1] + $hCellPadding;
                            $c['wlinet'][$countline] = array($t[1], 1);
                        }
                        $t[2] = $countline;
                    }
                }
                /*
                 * Принудительный перенос строки
                 */
                if ($l == 'br') {
                    $hpos = $maxhline * $spacingLine;
                    $c['hline'][] = $hpos;
                    $h += $hpos;
                    $c['wlines'][$countline] = $x - $hCellPadding;
                    $maxhline = $hl;
                    $countline++;
                    $x = $hCellPadding;
                    $c['wlinet'][$countline] = array(0, 0);
                }
            }
        }
        $c['wlines'][$countline] = $x - $hCellPadding;
        if ($vCellPadding > 0) {
            if (isset($c['hline'][0])) {
                $c['hline'][0] += $vCellPadding;
            }
            $h += $vCellPadding * 2;
        }
        $c['maxh'] = $h;
        return $h;
    }

    private function _cellHorzAlignLine(&$c, $line, $maxw, &$x, &$morespace) {
        $hCellPadding = isset($c['hpad']) ? $c['hpad'] : $this->hCellPadding;
        $morespace = 0;
        $x = $hCellPadding;
        if (!isset($c['wlines'][$line])) {
            return;
        }
        if ($c['a'] == 'C') {
            $x = ($maxw - $c['wlines'][$line]) / 2;
        } elseif ($c['a'] == 'R') {
            $x = $maxw - $c['wlines'][$line] + $hCellPadding;
        } elseif (
            $c['a'] == 'J'
            && $c['wlinet'][$line][1] > 1
            && isset($c['autobr'][$line])
            ) {
            $morespace = ($maxw - $c['wlines'][$line]) / ($c['wlinet'][$line][1] - 1);
        }
        if ($x < $hCellPadding) {
            $x = $hCellPadding;
        }
    }

    private function _checkLimitHeight(&$table, $maxh) {
        if ($maxh + $table['repeatH'] > $this->height) {
            $msg = 'Height of this row is greater than page height!';
            $this->SetFillColor(255, 0, 0);
            $h = $this->bottom - $table['lasty'];
            $this->Rect($this->x, $this->y = $table['lasty'], $table['w'], $h, 'F');
            $this->MultiCell($table['w'], $h, $msg);
            $table['lasty'] += $h;
            return true;
        } else {
            return false;
        }
    }

    private function _drawCellAligned($x0, $y0, &$c) {
        $hCellPaddings = (isset($c['hpad']) ? $c['hpad'] : $this->hCellPadding) * 2;
        $maxh = $c['h0'];
        $maxw = $c['w0'] - $hCellPaddings;
        $curh = $c['maxh'];
        $x = $y = 0;
        if ($c['va'] == 'M') {
            $y = ($maxh - $curh) / 2;
        } elseif ($c['va'] == 'B') {
            $y = $maxh - $curh;
        }
        $curline = 0;
        $morespace = 0;
        $cl = $c['hline'][$curline];
        $this->_cellHorzAlignLine($c, $curline, $maxw, $x, $morespace);
        foreach ($c['font'] as &$f) {
            $this->_setFontText($f);
            if (isset($f['color'])) {
                $color = Color::HEX2RGB($f['color']);
                $this->SetTextColor($color[0], $color[1], $color[2]);
            } else {
                unset($color);
            }
            $hl = $this->_getLineHeight();
            if (!isset($f['space'])) {
                continue;
            }
            $space = $f['space'];
            foreach ($f['line'] as $i=>&$l) {
                if (isset($l['str']) && is_array($l['str'])) {
                    foreach ($l['str'] as &$t) {
                        if ($t[2] != $curline) {
                            $y += $cl;
                            $curline++;
                            $cl = $c['hline'][$curline];
                            $this->_cellHorzAlignLine($c, $curline, $maxw, $x, $morespace);
                        }
                        $this->x = $x + $x0;
                        $this->y = $y + $y0 + $cl;
                        $this->Cell($t[1], 0, $t[0]);
                        $x += $t[1] + $space + $morespace;
                    }
                }
                if ($l == 'br') {
                    $y += $cl;
                    $curline++;
                    $cl = $c['hline'][$curline];
                    $this->_cellHorzAlignLine($c, $curline, $maxw, $x, $morespace);
                }
            }
            if (isset($color)) {
                $this->SetTextColor(0);
            }
        }
    }

    private function _getAlign($v) {
        $pdf_align = array(
            'left'=>'L',
            'center'=>'C',
            'right'=>'R',
            'justify'=>'J'
            );
        $v = strtolower($v);
        return isset($pdf_align[$v]) ? $pdf_align[$v] : 'L';
    }

    /**
     * Возвращает высоту строки при указанном размере шрифта в пользовательских единицах измерения.
     * @param  integer $font_size Размер шрифта (default = current font size)
     * @return int               Высота строки.
     */
    public function _getLineHeight($font_size = 0) {
        /*
         * $this->k - коэф. преобразования единиц измерения из FPDF.
         */
        if ($font_size == 0)
            $font_size = $this->FontSizePt;
        return $font_size / $this->k;
    }

    private function _getResolution($path) {
        $pos = strrpos($path, '.');
        if (!$pos)
            $this->Error('Image file has no extension and no type was specified: '.$path);
        $type = substr($path, $pos + 1);
        $type = strtolower($type);
        if ($type == 'jpeg') {
            $type = 'jpg';
        }
        if ($type != 'jpg') {
            $this->Error('Unsupported image type: '.$path);
        }
        $f = fopen($path, 'r');
        fseek($f, 13, SEEK_SET);
        $info = fread($f, 3);
        fclose($f);
        $iUnit = ord($info{0});
        $iX = ord($info{1}) * 256 + ord($info{2});
        return array($iUnit, $iX);
    }

    private function _getRowHeight(&$table, $i) {
        $maxh = 0;
        for ($j = 0; $j < $table['nc']; $j++) {
            $h = $this->_tableGetHCell($table, $i, $j);
            if ($maxh < $h) {
                $maxh = $h;
            }
        }
        return $maxh;
    }

    private function _getVAlign($v) {
        $pdf_valign = array(
            'top'=>'T',
            'middle'=>'M',
            'bottom'=>'B'
            );
        $v = strtolower($v);
        return isset($pdf_valign[$v]) ? $pdf_valign[$v] : 'T';
    }

    private function _html2text($text) {
        $text = str_replace('&nbsp;', ' ', $text);
        $text = str_replace('&lt;', '<', $text);
        return $text;
    }

    private function _makePageSize() {
        $this->left   = $this->lMargin;
        $this->right  = $this->w - $this->rMargin;
        $this->top    = $this->tMargin;
        $this->bottom = $this->h - $this->bMargin;
        $this->width  = $this->right - $this->left;
        $this->height = $this->bottom - $this->tMargin;
    }

    private function _setFontText(&$f) {
        if (isset($f['size']) && ($f['size'] > 0)) {
            $fontSize = $f['size'];
        } else {
            $fontSize = $this->defaultFontSize;
        }
        if (isset($f['family'])) {
            $fontFamily = $f['family'];
        } else {
            $fontFamily = $this->defaultFontFamily;
        }
        if (isset($f['style'])) {
            $fontStyle = $f['style'];
        } else {
            $fontStyle = $this->defaultFontStyle;
        }
        $this->SetFont($fontFamily, $fontStyle, $fontSize);
        return $fontSize;
    }

    private function _setImage(&$c, &$a) {
        $path = $a['src'];
        if (!is_file($path)) {
            $this->Error('Image is not exists: '.$path);
        } else {
            list($u, $d) = $this->_getResolution($path);
            $c['img'] = $path;
            list($c['w'], $c['h']) = getimagesize($path);
            if (isset($a['width'])) {
                $c['w'] = $a['width'];
            }
            if (isset($a['height'])) {
                $c['h'] = $a['height'];
            }
            $scale = 1;
            if ($u == 1) {
                $scale = 25.4 / $d;
            } elseif ($u == 2) {
                $scale = 10 / $d;
            }
            $c['w'] = intval($c['w'] * $scale);
            $c['h'] = intval($c['h'] * $scale);
        }
    }

    private function _setTextAndSize(&$cell, &$f, $text) {
        $hCellPaddings = (isset($cell['hpad']) ? $cell['hpad'] : $this->hCellPadding) * 2;
        if ($text == '') {
            return;
        }
        $this->_setFontText($f);
        if (!isset($f['line'][0])) {
            $f['line'][0]['min'] = $f['line'][0]['max'] = 0;
        }
        $text = preg_split('/[\s]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $l = &$f['line'][count($f['line']) - 1];
        if ($l == 'br') {
            $f['line'][] = array('min' => 0, 'max' => 0, 'str' => array());
            $l = &$f['line'][count($f['line']) - 1];
        }
        if (!isset($f['space'])) {
            $f['space'] = $this->GetStringWidth(' ');
        }
        $ct = count($text);
        foreach ($text as $item) {
            $s = $this->GetStringWidth($item);
            if ($l['min'] < $s) {
                $l['min'] = $s;
            }
            $l['max'] += $s;
            if ($ct > 1) {
                $l['max'] += $f['space'];
            }
            $l['str'][] = array($item, $s);
        }
        if (isset($cell['nowrap'])) {
            $l['min'] = $l['max'];
        }
        if (!isset($cell['miw']) || $cell['miw'] - $hCellPaddings < $l['min']) {
            $cell['miw'] = $l['min'] + $hCellPaddings;
        }
        if (!isset($cell['maw']) || $cell['maw'] - $hCellPaddings < $l['max']) {
            $cell['maw'] = $l['max'] + $hCellPaddings;
        }
    }


    /**
     * table    Array of (w, h, bc, nr, wc, hr, cells)
     * w        Width of table
     * h        Height of table
     * bc       Number column
     * nr       Number row
     * hr       List of height of each row
     * wc       List of width of each column
     * cells    List of cells of each rows, cells[i][j] is a cell in table
     */
    private function _tableColumnWidth(&$table) {
        $cs = &$table['cells'];
        $nc = $table['nc'];
        $nr = $table['nr'];
        $listspan = array();
        for ($j = 0; $j < $nc; $j++) {
            $wc = &$table['wc'][$j];
            for ($i = 0; $i < $nr; $i++) {
                if (isset($cs[$i][$j]['miw'])) {
                    $c = &$cs[$i][$j];
                    if (isset($c['nowrap'])) {
                        $c['miw'] = $c['maw'];
                    }
                    if (isset($c['w'])) {
                        if ($c['miw'] < $c['w']) {
                            $c['miw'] = $c['w'];
                        } elseif ($c['miw'] > $c['w']) {
                            $hCellPaddings = (isset($c['hpad']) ? $c['hpad'] : $this->hCellPadding) * 2;
                            $c['w'] = $c['miw'] + $hCellPaddings;
                        }
                        if (!isset($wc['w'])) {
                            $wc['w'] = 1;
                        }
                    }
                    if (isset($c['flex']) && $c['flex'] > $wc['flex']) {
                        $wc['flex'] = $c['flex'];
                    }
                    if ($c['maw'] < $c['miw']) {
                        $c['maw'] = $c['miw'];
                    }
                    if (!isset($c['colspan'])) {
                        if ($wc['miw'] < $c['miw']) {
                            $wc['miw'] = $c['miw'];
                        }
                        if ($wc['maw'] < $c['maw']) {
                            $wc['maw'] = $c['maw'];
                        }
                        if (isset($wc['w']) && $wc['w'] < $wc['miw']) {
                            $wc['w'] = $wc['miw'];
                        }
                    } else {
                        $listspan[] = array($i, $j);
                    }
                }
            }
        }

        $wc = &$table['wc'];
        foreach ($listspan as $span) {
            list($i, $j) = $span;
            $c = &$cs[$i][$j];
            $lc = $j + $c['colspan'];
            if ($lc > $nc) {
                $lc = $nc;
            }

            $wis = $wisa = 0;
            $was = $wasa = 0;
            $list = array();
            for ($k = $j; $k < $lc; $k++) {
                $wis += $wc[$k]['miw'];
                $was += $wc[$k]['maw'];
                if (!isset($c['w'])) {
                    $list[] = $k;
                    $wisa += $wc[$k]['miw'];
                    $wasa += $wc[$k]['maw'];
                }
            }
            if ($c['miw'] > $wis) {
                if (!$wis) {
                    for ($k = $j; $k < $lc; $k++) {
                        $wc[$k]['miw'] = $c['miw'] / $c['colspan'];
                    }
                } elseif (!count($list)) {
                    $wi = $c['miw'] - $wis;
                    for ($k = $j; $k < $lc; $k++) {
                        $wc[$k]['miw'] += ($wc[$k]['miw'] / $wis) * $wi;
                    }
                } else {
                    $wi = $c['miw'] - $wis;
                    foreach ($list as $_z2=>$k) {
                        $wc[$k]['miw'] += ($wc[$k]['miw'] / $wisa) * $wi;
                    }
                }
            }
            if ($c['maw'] > $was) {
                if (!$wis) {
                    for ($k = $j; $k < $lc; $k++) {
                        $wc[$k]['maw'] = $c['maw'] / $c['colspan'];
                    }
                } elseif (!count($list)) {
                    $wi = $c['maw'] - $was;
                    for ($k = $j; $k < $lc; $k++) {
                        $wc[$k]['maw'] += ($wc[$k]['maw'] / $was) * $wi;
                    }
                } else {
                    $wi = $c['maw'] - $was;
                    foreach ($list as $_z2=>$k) {
                        $wc[$k]['maw'] += ($wc[$k]['maw'] / $wasa) * $wi;
                    }
                }
            }
        }
    }


    private function _tableDrawBorder(&$table) {
        foreach ($table['listborder'] as $c) {
            list($x, $y, $w, $h, $s) = $c;
            $this->_tableRect($x, $y, $w, $h, $s);
        }

        $table['listborder'] = array();
    }

    private function _tableGetHCell(&$table, $i, $j) {
        $c = &$table['cells'][$i][$j];
        if ($c) {
            if (isset($c['h0'])) {
                return $c['h0'];
            }
            $hr = &$table['hr'];
            $h = $hr[$i];
            if (isset($c['rowspan'])) {
                for ($k = $i + $c['rowspan'] - 1; $k > $i; $k--) {
                    $h += $hr[$k];
                }
            }
            $c['h0'] = $h;
            return $h;
        }
        return 0;
    }

    private function _tableGetWCell(&$table, $i, $j) {
        $c = &$table['cells'][$i][$j];
        if ($c) {
            if (isset($c['x0'])) {
                return array($c['x0'], $c['w0']);
            }
            $x = 0;
            $wc = &$table['wc'];
            for ($k = 0; $k < $j; $k++) {
                $x += $wc[$k];
            }
            $w = $wc[$j];
            if (isset($c['colspan'])) {
                for ($k = $j + $c['colspan'] - 1; $k > $j; $k--) {
                    $w += @$wc[$k];
                }
            }
            $c['x0'] = $x;
            $c['w0'] = $w;
            return array($x, $w);
        }
        return array(0, 0);
    }

    private function _tableHeight(&$table) {
        $cs = &$table['cells'];
        $nc = $table['nc'];
        $nr = $table['nr'];
        $listspan = array();
        for ($i = 0; $i < $nr; $i++) {
            $hr = &$table['hr'][$i];
            for ($j = 0; $j < $nc; $j++) {
                if (isset($cs[$i][$j]['miw'])) {
                    $c = &$cs[$i][$j];
                    $this->_tableGetWCell($table, $i, $j);

                    $ch = $this->_cellHeight($c);
                    $c['ch'] = $ch;

                    if (isset($c['h']) && $c['h'] > $ch) {
                        $ch = $c['h'];
                    }

                    if (isset($c['rowspan'])) {
                        $listspan[] = array($i,$j);
                    } elseif ($hr < $ch) {
                        $hr = $ch;
                    }
                    $c['mih'] = $ch;
                }
            }
        }
        $hr = &$table['hr'];
        foreach ($listspan as $span) {
            list($i, $j) = $span;
            $c = &$cs[$i][$j];
            $lr = $i + $c['rowspan'];
            if ($lr > $nr) {
                $lr = $nr;
            }
            $hs = $hsa = 0;
            $list = array();
            for ($k = $i; $k < $lr; $k++) {
                $hs += $hr[$k];
                if (!isset($c['h'])) {
                    $list[] = $k;
                    $hsa += $hr[$k];
                }
            }
            if ($c['mih'] > $hs) {
                if (!$hs) {
                    for ($k = $i; $k < $lr; $k++) {
                        $hr[$k] = $c['mih'] / $c['rowspan'];
                    }
                } elseif (!count($list)) {
                    $hi = $c['mih'] - $hs;
                    for ($k = $i; $k < $lr; $k++) {
                        $hr[$k] += ($hr[$k] / $hs) * $hi;
                    }
                } else {
                    $hi = $c['mih'] - $hsa;
                    foreach ($list as $k) {
                        $hr[$k] += ($hr[$k] / $hsa) * $hi;
                    }
                }
            }
        }
        $table['repeatH'] = 0;
        if (count($table['repeat'])) {
            foreach ($table['repeat'] as $i) {
                $table['repeatH'] += $hr[$i];
            }
        } else {
            $table['repeat'] = 0;
        }
        $tth = 0;
        foreach ($hr as $v) {
            $tth += $v;
        }
        $table['tth'] = $tth;
    }

    private function _tableParser(&$html) {
        $t = new TreeHTML(new HTMLParser($html), 0);
        $row = $col = -1;
        $table['nc'] = $table['nr'] = 0;
        $table['repeat'] = array();
        $cell = array();
        $fontopen = false;
        $tdopen = false;
        foreach ($t->name as $i=>$element) {
            if ($fontopen && $t->type[$i] == NODE_TYPE_ENDELEMENT
                && (in_array($element, array('table', 'tr', 'td', 'font')))
                ) {
                $fontopen = false;
            }
            if ($tdopen && $t->type[$i] == NODE_TYPE_ENDELEMENT
                && (in_array($element, array('table', 'tr', 'td')))
                && !isset($cell[$row][$col]['miw'])
                ) {
                $c = &$cell[$row][$col];
                $c['miw'] = $c['maw'] = 0;
                $tdopen = false;
            }
            if ($t->type[$i] != NODE_TYPE_ELEMENT 
                && $t->type[$i] != NODE_TYPE_TEXT
                ) {
                continue;
            }

            switch ($element) {
                case 'table':
                    $tdopen = 0;
                    $a  = &$t->attribute[$i];
                    if (isset($a['width'])) {
                        $table['w'] = $this->_calWidth($a['width']);
                    }
                    if (isset($a['height'])) {
                        $table['h'] = intval($a['height']);
                    }
                    if (isset($a['align'])) {
                        $table['a'] = $this->_getAlign(strtolower($a['align']));
                    }

                    $table['border'] = (isset($a['border']) ? $a['border']: 0);

                    if (isset($a['bgcolor'])) {
                        $table['bgcolor'][-1] = $a['bgcolor'];
                    }
                    $table['nobreak'] = isset($a['nobreak']);
                    break;
                case 'tr':
                    $tdopen = 0;
                    $row++;
                    $table['nr']++;
                    $col = -1;
                    $a = &$t->attribute[$i];
                    if (isset($a['repeat'])) {
                        $table['repeat'][] = $row;
                    } else {
                        if (isset($a['pbr'])) {
                            $table['pbr'][$row] = 1;
                        }
                        if (isset($a['knext'])) {
                            $table['knext'][$row] = 1;
                        }
                    }

                    /*
                     * Атрибуты, которые распределяются всем ячейкам строки
                     */
                    $tr_attrs = array(
                        'align',
                        'bgcolor',
                        'border',
                        'color',
                        'colspan',
                        'family',
                        'flex',
                        'height',
                        'hpad',
                        'lh',
                        'rowspan',
                        'size',
                        'style',
                        'valign',
                        'vpad',
                        'width'
                        );
                    foreach ($tr_attrs as $key => $attr) {                   
                        if (isset($a[$attr])) {
                            $table[$attr][$row] = $a[$attr];
                        }
                    }
                    break;
                case 'td':
                    $tdopen = 1;
                    $col++;
                    while(isset($cell[$row][$col])) {
                        $col++;
                    }

                    if ($table['nc'] < $col + 1) {
                        $table['nc'] = $col + 1;
                    }
                    $cell[$row][$col] = array();
                    $c = &$cell[$row][$col];
                    $a = &$t->attribute[$i];

                    /*
                     * Ширина ячейки
                     */
                    if (isset($a['width']))  {
                        $c['w'] = floatval($a['width']);
                    } elseif (isset($table['width'][$row])) {
                        $c['w'] = floatval($table['width'][$row]);
                    }

                    /*
                     * Пропорция ячейки
                     */
                    if (isset($a['flex']))  {
                        $c['flex'] = floatval($a['flex']);
                    } elseif (isset($table['flex'][$row])) {
                        $c['flex'] = floatval($table['flex'][$row]);
                    }

                    /*
                     * Высота ячейки
                     */
                    if (isset($a['height']))  {
                        $c['h'] = floatval($a['height']);
                    } elseif (isset($table['height'][$row])) {
                        $c['h'] = floatval($table['height'][$row]);
                    }

                    /*
                     * Выравнивание текста по горизонтали
                     */
                    if (isset($a['align']))  {
                        $c['a'] = $this->_getAlign($a['align']);
                    } elseif (isset($table['align'][$row])) {
                        $c['a'] = $this->_getAlign($table['align'][$row]);
                    } else {
                        $c['a'] = 'L';
                    }

                    /*
                     * Выравнивание текста по вертикали
                     */
                    if (isset($a['valign']))  {
                        $c['va'] = $this->_getVAlign($a['valign']);
                    } elseif (isset($table['valign'][$row])) {
                        $c['va'] = $this->_getVAlign($table['valign'][$row]);
                    } else {
                        $c['va'] = 'T';
                    }

                    /*
                     * Границы ячейки
                     */
                    if (isset($a['border'])) {
                        $c['border'] = $a['border'];
                    } elseif (isset($table['border'][$row])) {
                        $c['border'] = $table['border'][$row];
                    } else {
                        $c['border'] = $table['border'];
                    }

                    /*
                     * Цвет фона ячейки
                     */
                    if (isset($a['bgcolor'])) {
                        $c['bgcolor'] = $a['bgcolor'];
                    }

                    $cs = $rs = 1;

                    /*
                     * Количество объединяемых столбцов
                     */
                    if (isset($a['colspan']) && $a['colspan'] > 1) {
                        $cs = $c['colspan'] = intval($a['colspan']);
                    } elseif (isset($table['colspan'][$row]) && $table['colspan'][$row] > 1) {
                        $cs = $c['colspan'] = intval($table['colspan'][$row]);
                    }

                    /*
                     * Количество объединяемых строк
                     */
                    if (isset($a['rowspan']) && $a['rowspan'] > 1) {
                        $rs = $c['rowspan'] = intval($a['rowspan']);
                    } elseif (isset($table['rowspan'][$row]) && $table['rowspan'][$row] > 1) {
                        $rs = $c['rowspan'] = intval($table['rowspan'][$row]);
                    }

                    /*
                     * Размер шрифта в ячейке
                     */
                    if (isset($a['size'])) {
                        $c['font'][0]['size'] = $a['size'];
                    } elseif (isset($table['size'][$row])) {
                        $c['font'][0]['size'] = $table['size'][$row];
                    }

                    /*
                     * Шрифт в ячейке
                     */
                    if (isset($a['family'])) {
                        $c['font'][0]['family'] = $a['family'];
                    } elseif (isset($table['family'][$row])) {
                        $c['font'][0]['family'] = $table['family'][$row];
                    }

                    /*
                     * Стили шрифта в ячейке
                     */
                    if (isset($a['style']) || isset($table['style'][$row])) {
                        $styles = isset($a['style']) ? $a['style'] : $table['style'][$row];
                        $styles = explode(",", strtoupper($styles));
                        $c['font'][0]['style'] = '';
                        foreach ($styles AS $style) {
                            $c['font'][0]['style'] .= substr(trim($style), 0, 1);
                        }
                    }

                    /*
                     * Цвет шрифта в ячейке
                     */
                    if (isset($a['color'])) {
                        $c['font'][0]['color'] = $a['color'];
                    } elseif (isset($table['color'][$row])) {
                        $c['font'][0]['color'] = $table['color'][$row];
                    }

                    for ($k = $row; $k < $row + $rs; $k++) {
                        for ($l = $col; $l < $col + $cs; $l++) {
                            if ($k - $row || $l - $col) {
                                $cell[$k][$l] = 0;
                            }
                        }
                    }
                    if (isset($a['nowrap'])) {
                        $c['nowrap'] = 1;
                    }
                    $fontopen = true;
                    if (!isset($c['font'])) {
                        $c['font'][] = array();
                    }

                    /*
                     * Отступы внутри ячеек
                     */
                    if (isset($a['hpad']))   {
                        $c['hpad'] = floatval($a['hpad']);
                    } elseif (isset($table['hpad'][$row])) {
                        $c['hpad'] = floatval($table['hpad'][$row]);
                    }
                    
                    if (isset($a['vpad']))   {
                        $c['vpad'] = floatval($a['vpad']);
                    } elseif (isset($table['vpad'][$row])) {
                        $c['vpad'] = floatval($table['vpad'][$row]);
                    }

                    /*
                     * Междустрочный интервал внутри ячейки
                     */
                    if (isset($a['lh']))   {
                        $c['lh'] = floatval($a['lh']);
                    } elseif (isset($table['lh'][$row])) {
                        $c['lh'] = floatval($table['lh'][$row]);
                    }

                    break;
                case 'Text':
                    $c = &$cell[$row][$col];
                    if (!$fontopen || !isset($c['font'])) {
                        $c['font'][] = array();
                    }
                    $f = &$c['font'][count($c['font']) - 1];
                    $this->_setTextAndSize($c, $f, $this->_html2text($t->value[$i]));
                    break;
                case 'font':
                    $fontopen = true;
                    $a = &$t->attribute[$i];
                    $c = &$cell[$row][$col];
                    $c['font'][] = array();
                    $f = &$c['font'][count($c['font']) - 1];
                    if (isset($a['size'])) {
                        $f['size'] = $a['size'];
                    }
                    if (isset($a['family'])) {
                        $f['family'] = $a['family'];
                    }
                    if (isset($a['style'])) {
                        $styles = explode(",", strtoupper($a['style']));
                        $f['style'] = '';
                        foreach ($styles AS $style) {
                            $f['style'] .= substr(trim($style), 0, 1);
                        }
                    }
                    if (isset($a['color'])) {
                        $f['color'] = $a['color'];
                    }
                    break;
                case 'img':
                    $a = &$t->attribute[$i];
                    if (isset($a['src'])) {
                        $this->_setImage($c, $a);
                    }
                    break;
                case 'br':
                    $c = &$cell[$row][$col];
                    $cn = isset($c['font']) ? count($c['font']) - 1 : 0;
                    $c['font'][$cn]['line'][] = 'br';
                    break;
            }
        }

        $table['cells'] = $cell;
        $table['wc'] = array_pad(
                            array(),
                            $table['nc'],
                            array('miw' => 0, 'maw' => 0, 'flex' => 0)
                            );
        $table['hr'] = array_pad(
                            array(),
                            $table['nr'],
                            0
                            );
        return $table;
    }

    private function _tableRect($x, $y, $w, $h, $type = 1) {
        if (strlen($type) == 4) {
            $x2 = $x + $w;
            $y2 = $y + $h;
            if (intval($type{0})) {
                $this->Line($x, $y, $x2, $y);
            }
            if (intval($type{1})) {
                $this->Line($x2, $y, $x2, $y2);
            }
            if (intval($type{2})) {
                $this->Line($x, $y2, $x2, $y2);
            }
            if (intval($type{3})) {
                $this->Line($x, $y, $x, $y2);
            }
        } elseif (intval($type) === 1) {
            $this->Rect($x, $y, $w, $h);
        } elseif (intval($type) > 1 && intval($type) < 11) {
            $width = $this->LineWidth;
            $this->SetLineWidth($type * $this->LineWidth);
            $this->Rect($x, $y, $w, $h);
            $this->SetLineWidth($width);
        }
    }

    private function _tableWidth(&$table) {
        $wc = &$table['wc'];
        $nc = $table['nc'];
        $a = 0;
        for ($i = 0; $i < $nc; $i++) {
            $a += isset($wc[$i]['w']) ? $wc[$i]['miw'] : $wc[$i]['maw'];
        }
        if ($a > $this->width) {
            $table['w'] = $this->width;
        }
        if (isset($table['w'])) {
            $wis = 0;
            $woWidth = array();
            $wFlex = array();
            $flex = 0;
            for ($i = 0; $i < $nc; $i++) {
                $wis += $wc[$i]['miw'];
                if (!isset($wc[$i]['w'])) {
                    $woWidth[] = $i;
                }
                if (isset($wc[$i]['flex']) && $wc[$i]['flex'] > 0) {
                    $wFlex[] = $i;
                    $flex += $wc[$i]['flex'];
                }
            }
            if ($table['w'] > $wis) {
                if ($flex > 0) {
                    $wi = ($table['w'] - $wis) / $flex;
                    foreach ($wFlex as $k) {
                        $wc[$k]['miw'] += $wi * $wc[$k]['flex'];
                    }
                } elseif (!empty($woWidth)) {
                    $wi = ($table['w'] - $wis) / count($woWidth);
                    foreach ($woWidth as $k) {
                        $wc[$k]['miw'] += $wi;
                    }
                } else {
                    $wi = ($table['w'] - $wis) / $nc;
                    for ($k = 0; $k < $nc; $k++) {
                        $wc[$k]['miw'] += $wi;
                    }
                }
            }
            for ($i = 0; $i < $nc; $i++) {
                $a = $wc[$i]['miw'];
                unset($wc[$i]);
                $wc[$i] = $a;
            }
        } else {
            $table['w'] = $a;
            for ($i = 0; $i < $nc; $i++) {
                $a = isset($wc[$i]['w']) ? $wc[$i]['miw'] : $wc[$i]['maw'];
                unset($wc[$i]);
                $wc[$i] = $a;
            }
        }
        $table['w'] = array_sum($wc);
    }


    private function _tableWrite(&$table) {
        if ($this->CurOrientation == 'P' && $table['w'] > $this->width + 5) {
            $this->AddPage('L');
        }
        if ($this->x === null) {
            $this->x = $this->lMargin;
        }
        if ($this->y === null) {
            $this->y = $this->tMargin;
        }
        $x0 = $this->x;
        $y0 = $this->y;
        if (isset($table['a'])) {
            if ($table['a'] == 'C') {
                $x0 += (($this->right - $x0) - $table['w']) / 2;
            } elseif ($table['a'] == 'R') {
                $x0 = $this->right - $table['w'];
            }
        }
        if (isset($table['nobreak'])
            && $table['nobreak']
            && $table['tth'] + $y0 > $this->bottom
            && $table['multipage']
            ) {
            $this->AddPage($this->CurOrientation);
            $table['lasty'] = $this->y;
        } else {
            $table['lasty'] = $y0;
        }

        $table['listborder'] = array();
        for ($i = 0; $i < $table['nr']; $i++) {
            $this->_tableWriteRow($table, $i, $x0);
        }
        
        $this->_tableDrawBorder($table);
        $this->x = $x0;
    }

    private function _tableWriteRow(&$table, $i, $x0) {
        $maxh = $this->_getRowHeight($table, $i);
        if ($table['multipage']) {
            $newpage = false;
            if ($table['lasty'] + $maxh > $this->bottom) {
                if ($this->_checkLimitHeight($table, $maxh)) {
                    return;
                }
                $newpage = true;
            } elseif (isset($table['pbr'][$i])) {
                $newpage = true;
                unset($table['pbr'][$i]);
            } elseif (isset($table['knext'][$i]) && $i < $table['nr'] - 1) {
                $mrowh = $maxh;
                for ($j = $i + 1; $j < $table['nr']; $j++) {
                    $mrowh += $this->_getRowHeight($table, $j);
                    if (!isset($table['knext'][$j])) {
                        break;
                    }
                    unset($table['knext'][$j]);
                }
                if ($this->_checkLimitHeight($table, $mrowh)) {
                    return;
                }
                $newpage = $table['lasty'] + $mrowh > $this->bottom;
            }
            if ($newpage) {
                $this->_tableDrawBorder($table);
                $this->AddPage($this->CurOrientation);
                $table['lasty'] = $this->y;
                if ($table['repeat']) {
                    foreach ($table['repeat'] as $r) {
                        if ($r == $i) {
                            continue;
                        }
                        $this->_tableWriteRow($table,$r,$x0);
                    }
                }
            }
        }
        $y = $table['lasty'];
        for ($j = 0; $j < $table['nc']; $j++) {
            if (isset($table['cells'][$i][$j]['miw'])) {
                $c = &$table['cells'][$i][$j];
                list($x, $w) = $this->_tableGetWCell($table, $i, $j);
                $h = $this->_tableGetHCell($table, $i, $j);
                $x += $x0;
                
                //Fill
                if (isset($c['bgcolor'])) {
                    $fill = $c['bgcolor'];
                } elseif (isset($table['bgcolor'][$i])) {
                    $fill = $table['bgcolor'][$i];
                } elseif (isset($table['bgcolor'][-1])) {
                    $fill = $table['bgcolor'][-1];
                } else {
                    $fill = 0;
                }

                if ($fill) {
                    $color = Color::HEX2RGB($fill);
                    $this->SetFillColor($color[0], $color[1], $color[2]);
                    $this->Rect($x, $y, $w, $h, 'F');
                }

                //Content
                if (isset($c['img'])) {
                    $this->Image($c['img'], $x, $y, $c['w'], $c['h']);
                } else {
                    $this->_drawCellAligned($x, $y, $c);
                }

                //Border
                if (isset($c['border'])) {
                    $table['listborder'][] = array($x, $y, $w, $h, $c['border']);
                } elseif (isset($table['border']) && $table['border']) {
                    $table['listborder'][] = array($x, $y, $w, $h, $table['border']);
                }
            }
        }
        $table['lasty'] += $table['hr'][$i];
        $this->y = $table['lasty'];
    }
}
?>