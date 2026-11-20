<?php

namespace App\Support\Docx;

use ZipArchive;

/**
 * Dựng tệp .docx tối giản (chỉ đoạn văn) — cho tệp mẫu "Tải đề mẫu" và cho test của bộ nhập đề Word.
 *
 * Mỗi đoạn là chuỗi chữ thường, hoặc mảng các mẩu:
 *   'chữ'                              — chữ thường
 *   ['u' => 'chữ']                     — gạch chân (cách đánh dấu đáp án đúng)
 *   ['red' => 'chữ']                   — tô đỏ
 *   ['math' => '<m:f>…</m:f>']         — công thức OMML (nội dung bên trong m:oMath)
 *   ['tab' => true]                    — phím tab
 */
class SimpleDocx
{
    /** @param  array<int, string|array<int, string|array<string, mixed>>>  $paragraphs */
    public static function build(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<w:p>'.self::paragraph(is_array($paragraph) ? $paragraph : [$paragraph]).'</w:p>';
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            .' xmlns:m="'.OmmlToLatex::NS.'"><w:body>'.$body.'</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /** @param  array<int, string|array<string, mixed>>  $parts */
    private static function paragraph(array $parts): string
    {
        $xml = '';
        foreach ($parts as $part) {
            if (is_string($part)) {
                $xml .= self::run($part);
            } elseif (isset($part['math'])) {
                $xml .= '<m:oMath>'.$part['math'].'</m:oMath>';
            } elseif (isset($part['tab'])) {
                $xml .= '<w:r><w:tab/></w:r>';
            } elseif (isset($part['u'])) {
                $xml .= self::run($part['u'], '<w:u w:val="single"/>');
            } elseif (isset($part['red'])) {
                $xml .= self::run($part['red'], '<w:color w:val="FF0000"/>');
            }
        }

        return $xml;
    }

    private static function run(string $text, string $props = ''): string
    {
        return '<w:r>'.($props !== '' ? '<w:rPr>'.$props.'</w:rPr>' : '')
            .'<w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r>';
    }
}
