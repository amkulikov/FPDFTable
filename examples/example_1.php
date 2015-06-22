<?php
require_once './../fpdftable.php';

$pdf = new FPDFTable();
$pdf->SetTitle('Example 1', true);
$pdf->AddFont('Arial', '', 'arial.php');
$pdf->AddFont('Arial', 'B', 'arial_b.php');
$pdf->SetFont('Arial', '', 12);
$pdf->AddPage();

$html = <<<TABLE
  <table border="1" align="center" width="100%">
    <tr hpad="0" vpad="0" size="16">
      <td rowspan="2" align="right" valign="bottom" size="12">
        tr:hpad=0, vpad=0, size=16<br>
        td:rowspan=2, align=right, valign=bottom, size=12
        </td>
      <td hpad="2">
        tr:hpad=0, vpad=0, size=16<br>
        td: hpad=2
        </td>
    <td colspan="2">
      tr:hpad=0, vpad=0, size=16<br>
      td:colspan=2
      </td>
    </tr>
  <tr height="40" bgcolor="#999999">
    <td>
      tr: height=40
      </td>
    <td lh="0.8">
      tr: height=40<br>
      td: lh=0.8
      </td>
    <td>
      tr: height=40
      </td>
    </tr>
  <tr>
    <td color="#ff0000">
      проверка кириллицы
      </td>
    <td>
      <font style="b" size="14">many</font><br>font<br><font style="bold" color="#ff0000" size="14">tags</font><br>test
      </td>
    <td colspan="2" rowspan="2" vpad="3" hpad="5" border="0001">
      td: colspan=2, rowspan=2, vpad=3, hpad=5, border=1000
      </td>
    </tr>
  <tr align="center" valign="middle">
    <td>
      tr: align=center, valign=middle
      </td>
    <td lh="1.5" border="0">
      tr: align=center, valign=middle<br>
      td: lh=1.5, border=0
      </td>
    </tr>
  </table>
TABLE;

$pdf->htmltable($html);
$pdf->Output('example_1.pdf', 'I');
?>