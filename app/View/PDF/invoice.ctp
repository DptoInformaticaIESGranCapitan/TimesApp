<?php
App::import('Vendor','xtcpdf');
 
$pdf = new XTCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
 
$pdf->AddPage();
 
$html = '</pre>
<h1>hello world</h1>
<pre>';
 
echo debug($invoice);
echo debug($taxes);

$pdf->writeHTML($appSettings['companyName'], true, false, true, false, '');
 
$pdf->lastPage();

$name = 1;
 
echo $pdf->Output(WWW_ROOT . 'files' . DS . 'pdf' . DS . $name . '.pdf', 'F');