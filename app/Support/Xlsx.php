<?php

declare(strict_types=1);

namespace App\Support;

use ZipArchive;

/**
 * Generador mínimo de planillas XLSX (Office Open XML) sin dependencias externas.
 * Una hoja, encabezado en negrita, números como números y formato de moneda.
 */
final class Xlsx
{
    public static function generar(string $titulo, array $columnas, array $filas, ?array $totales = null): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $hoja = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $titulo), 0, 31), ENT_XML1);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $hoja . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        // Estilos: 0 normal, 1 negrita, 2 moneda, 3 cantidad, 4 fecha, 5 moneda negrita
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;$&quot; #,##0.00"/><numFmt numFmtId="165" formatCode="#,##0.###"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            . '</cellXfs></styleSheet>');

        $claves = array_keys($columnas);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $fila = 1;
        $xml .= '<row r="1">';
        foreach ($claves as $i => $k) {
            $xml .= self::celdaTexto(self::col($i) . '1', $columnas[$k][0], 1);
        }
        $xml .= '</row>';
        $todas = $filas;
        if ($totales) {
            $todas[] = $totales + ['__total' => true];
        }
        foreach ($todas as $f) {
            $fila++;
            $esTotal = !empty($f['__total']);
            $xml .= '<row r="' . $fila . '">';
            foreach ($claves as $i => $k) {
                $ref = self::col($i) . $fila;
                $v = $f[$k] ?? null;
                $tipo = $columnas[$k][1] ?? 'texto';
                if ($v === null || $v === '') {
                    continue;
                }
                if (in_array($tipo, ['money', 'num', 'cant', 'entero', 'porc'], true) && is_numeric($v)) {
                    $estilo = $tipo === 'money' || $tipo === 'num' ? ($esTotal ? 5 : 2) : 3;
                    $xml .= '<c r="' . $ref . '" s="' . $estilo . '"><v>' . (float) $v . '</v></c>';
                } elseif (in_array($tipo, ['fecha', 'fecha_hora'], true) && ($t = strtotime((string) $v))) {
                    $serial = 25569 + ($t + (int) date('Z', $t)) / 86400;
                    $xml .= '<c r="' . $ref . '" s="4"><v>' . ($tipo === 'fecha' ? floor($serial) : round($serial, 6)) . '</v></c>';
                } else {
                    $xml .= self::celdaTexto($ref, (string) $v, $esTotal ? 1 : 0);
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $contenido = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $contenido;
    }

    private static function celdaTexto(string $ref, string $texto, int $estilo): string
    {
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);
        return '<c r="' . $ref . '" t="inlineStr" s="' . $estilo . '"><is><t xml:space="preserve">' . htmlspecialchars((string) $texto, ENT_XML1) . '</t></is></c>';
    }

    private static function col(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - $m, 26);
        }
        return $s;
    }
}
