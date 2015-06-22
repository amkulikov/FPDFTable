<?php
require_once './../fpdftable.php';

$pdf = new FPDFTable();
$pdf->SetTitle('Example 2', true);
$pdf->AddFont('TNR', '', 'times.php');
$pdf->AddFont('TNR', 'B', 'times_b.php');
$pdf->SetFont('TNR', '', 12);

$pdf->footerTable = '<table width="100%"><tr><td align="center" style="bold">Страница {pn} из {pc}</td></tr></table>';

$pdf->AddPage();
$html = <<<TABLE
  <table border="0" align="center" width="100%">
    <tr>
      <td flex="1" size="24" align="center">
        Пример #2
        </td>
      </tr>
    </table>
  <table border="1" align="center" width="100%">
    <tr hpad="0" vpad="0" align="center" valign = "middle" size="14" repeat>
      <td rowspan="2" align="left" hpad="2" flex="3">
        Столбец 1 с flex=3
        </td>
      <td rowspan="2" width="30" flex="1">
        Столбец A с flex=1
        </td>
      <td colspan="4">
        BCDE
        </td>
    </tr>
    <tr hpad="0" vpad="0" align="center" valign="middle" repeat>
      <td>
        B
        </td>
      <td>
        C
        </td>
      <td>
        D
        </td>
      <td>
        E
        </td>
    </tr>
TABLE;

for ($i = 0; $i < 100; $i++) { 
  $html .= <<<TABLE
    <tr hpad="0" vpad="0" align="center" size="10">
      <td align="left">
        Строка {$i}
        </td>
      <td>
        {$i}
        </td>
      <td>
        {$i}
        </td>
      <td>
        {$i}
        </td>
      <td>
        {$i}
        </td>
      <td>
        {$i}
        </td>
    </tr>
TABLE;
}
$html .= '</table>';
$pdf->htmltable($html);
$pdf->Output('example_2.pdf', 'I');
?>