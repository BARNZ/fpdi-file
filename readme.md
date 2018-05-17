# Simple class that extends FPDI/FPDF to build PDF files using a file-based buffer instead of the default the in-memory buffer.

Allows much much larger PDF documents to be built. 

Adapted from http://www.fpdf.org/en/script/script76.php to extend FPDI.

I have used this class to produce a 10k page PDF document with custom page numbering using less than 256MB of php memory.

Requires setasign/fpdi-fpdf:

`composer require setasign/fpdi-fpdf` 

My example usage scenario:
> Need to merge 1000 individual pdfs into a single large pdf and number each page

    $mergeFile = '/tmp/merged.pdf';
    $totalPages = 0;
    $pageNum = 0;

    // Count the total number of pages across all PDFs being merged
    foreach ($this->pdfs as $pdf) {
        $totalPages += (new Fpdi())->setSourceFile($pdf);
    }

    $fpdi = new FPDI2File();
    $fpdi->Open($mergeFile);
    foreach ($this->pdfs as $pdf) {
        $pageCount = $fpdi->setSourceFile($pdf);
        for ($i = 1; $i <= $pageCount; $i++) {
            $pageNum++;

            $template = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($template);
            $fpdi->AddPage(
                $size['width'] > $size['height'] ? 'L' : 'P',
                [$size['width'], $size['height']]
            );
            $fpdi->useTemplate($template);

            // Disable page breaking so we can set numbers at the bottom of the page.
            $fpdi->setAutoPageBreak(false);

            // Add the page numbering.
            $paging = "Page $pageNum of $totalPages";
            $fpdi->SetXY($size['width'] - ceil(strlen($paging) * 2.7), $size['height'] - 10);
            $fpdi->SetFont('Arial', '', 7);
            $fpdi->Write(0, $paging);

            // Add document number
            $fpdi->SetXY(15, $size['height'] - 10);
            $fpdi->SetFont('Arial', '', 7);
            $fpdi->Write(0, 'XXX_YYY');
        }
    }

    $fpdi->Output();