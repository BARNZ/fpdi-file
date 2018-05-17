<?php

use setasign\Fpdi\Fpdi;

/**
 * Adapted from http://www.fpdf.org/en/script/script76.php
 * Used to build large PDF files using a file-based buffer instead of default the in-memory buffer used by FPDF.
 *
 * Updated to extend FPDI which then extends FPDF.
 *
 * Requires setasign/fpdi-fpdf
 * composer require setasign/fpdi-fpdf
 */
class FPDI2File extends Fpdi
{
    protected $f;

    public function Open($file = 'temp.pdf')
    {
        if (FPDF_VERSION < '1.8') {
            $this->Error('Version 1.8 or above is required by this extension');
        }

        $this->f = fopen($file, 'wb');
        if (!$this->f) {
            $this->Error('Unable to create output file: ' . $file);
        }

        $this->_putheader();
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    {
        if (!isset($this->images[$file])) {
            $a = getimagesize($file);
            if ($a === false) {
                $this->Error('Missing or incorrect image file: ' . $file);
            }

            $this->images[$file] = array('w' => $a[0], 'h' => $a[1], 'type' => $a[2], 'i' => count($this->images) + 1);
        }
        parent::Image($file, $x, $y, $w, $h, $type, $link);
    }

    public function Output($dest = '', $name = '', $isUTF8 = false)
    {
        if ($this->state < 3) {
            $this->Close();
        }
    }

    public function _endpage()
    {
        parent::_endpage();
        $this->_putstreamobject($this->pages[$this->page]);
        unset($this->pages[$this->page]);
    }

    public function _getoffset()
    {
        return ftell($this->f);
    }

    protected function _put($s, $newLine = true)
    {
        if ($newLine) {
            fwrite($this->f, $s . "\n", strlen($s) + 1);
        } else {
            fwrite($this->f, $s, strlen($s));
        }
    }

    public function _putpages()
    {
        $nb = $this->page;
        for ($n = 1; $n <= $nb; $n++) {
            $this->PageInfo[$n]['n'] = $this->n + $n;
        }

        if ($this->DefOrientation == 'P') {
            $wPt = $this->DefPageSize[0] * $this->k;
            $hPt = $this->DefPageSize[1] * $this->k;
        } else {
            $wPt = $this->DefPageSize[1] * $this->k;
            $hPt = $this->DefPageSize[0] * $this->k;
        }
        //Page objects
        for ($n = 1; $n <= $nb; $n++) {
            $this->_newobj();
            $this->_put('<</Type /Page');
            $this->_put('/Parent 1 0 R');
            if (isset($this->PageInfo[$n]['size'])) {
                $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageInfo[$n]['size'][0], $this->PageInfo[$n]['size'][1]));
            }

            if (isset($this->PageInfo[$n]['rotation'])) {
                $this->_put('/Rotate ' . $this->PageInfo[$n]['rotation']);
            }

            $this->_put('/Resources 2 0 R');
            if (isset($this->PageLinks[$n])) {
                //Links
                $annots = '/Annots [';
                foreach ($this->PageLinks[$n] as $pl) {
                    $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                    $annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';
                    } else {
                        $l = $this->links[$pl[4]];
                        if (isset($this->PageInfo[$l[0]]['size'])) {
                            $h = $this->PageInfo[$l[0]]['size'][1];
                        } else {
                            $h = $hpt;
                        }

                        $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', $this->PageInfo[$l[0]]['n'], $h - $l[1] * $this->k);
                    }
                }
                $this->_put($annots . ']');
            }
            if ($this->WithAlpha) {
                $this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }

            $this->_put('/Contents ' . (2 + $n) . ' 0 R>>');
            $this->_put('endobj');
        }
        //Pages root
        $this->offsets[1] = $this->_getoffset();
        $this->_put('1 0 obj');
        $this->_put('<</Type /Pages');
        $kids = '/Kids [';
        for ($n = 1; $n <= $nb; $n++) {
            $kids .= (2 + $nb + $n) . ' 0 R ';
        }

        $this->_put($kids . ']');
        $this->_put('/Count ' . $nb);
        $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
        $this->_put('>>');
        $this->_put('endobj');
    }

    public function _putheader()
    {
        if ($this->_getoffset() == 0) {
            parent::_putheader();
        }
    }

    public function _enddoc()
    {
        parent::_enddoc();
        fclose($this->f);
    }
}
