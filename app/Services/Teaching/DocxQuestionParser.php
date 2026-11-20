<?php

namespace App\Services\Teaching;

use App\Models\Question;
use App\Support\Docx\OmmlToLatex;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Tách đề Toán soạn bằng Word (.docx) thành câu hỏi — theo đúng cách giáo viên Việt Nam vẫn gõ đề:
 *
 *   Câu 1: Nội dung… (công thức bằng Insert → Equation)
 *   A. …   B. …   C. …   D. …          (chung một dòng, cách bằng tab, hoặc mỗi đáp án một dòng/ô bảng)
 *   Đáp án: B                           (hoặc gạch chân / tô đỏ đáp án đúng, hoặc đánh dấu *B.)
 *   Lời giải: …                         (các dòng sau đó tới "Câu" kế tiếp là lời giải)
 *   Mức độ: Dễ | Trung bình | Khó · Điểm: 0,5   (tuỳ chọn)
 *
 * Kết quả có cùng dạng dòng với tệp CSV để `QuestionImporter::importRows()` tạo câu hỏi —
 * một đường lưu, một bộ kiểm tra. KHÔNG lưu gì ở đây: giáo viên xem trước rồi mới bấm nhập.
 */
class DocxQuestionParser
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Tối đa câu mỗi tệp — đề thật hiếm khi quá 100 câu; chặn tệp khổng lồ làm treo request. */
    public const MAX_QUESTIONS = 300;

    private const QUESTION_START = '/^\s*(?:Câu|Bài|Question)\s*(\d+)\s*[.:)\-–]?\s*(?:\(([\d.,]+)\s*điểm\)\s*[.:\-–]?)?\s*/iu';

    private const SECTION_HEADING = '/^\s*(?:PHẦN|Phần|I{1,3}V?|VI{0,3})\s*[IVX\d]*\s*[.:\-–]/u';

    private const OPTION_MARKER = '/(?:^|(?<=\s))(\*?)([A-F])\s*[.)](?:\s+|(?=\$)|$)/u';

    private const DIFFICULTY_WORDS = [
        'dễ' => 'easy', 'de' => 'easy', 'nhận biết' => 'easy', 'nb' => 'easy',
        'trung bình' => 'medium', 'tb' => 'medium', 'thông hiểu' => 'medium', 'th' => 'medium',
        'khó' => 'hard', 'kho' => 'hard', 'vận dụng' => 'hard', 'vận dụng cao' => 'hard', 'vd' => 'hard', 'vdc' => 'hard',
    ];

    public function __construct(private readonly OmmlToLatex $omml) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, warnings: array<int, string>}
     *
     * @throws \InvalidArgumentException tệp không phải .docx đọc được
     */
    public function parse(string $path): array
    {
        $lines = $this->lines($path);

        $rows = [];
        $current = null;
        $mode = 'content';
        $preamble = 0;

        foreach ($lines as $line) {
            // marks đi theo từng ký tự của text — cắt đầu dòng thì cắt marks y hệt.
            $lead = mb_strlen($line['text']) - mb_strlen(ltrim($line['text']));
            $text = trim($line['text']);
            $marks = array_slice($line['marks'], $lead);
            if ($text === '') {
                continue;
            }

            if (preg_match(self::QUESTION_START, $text, $m)) {
                if ($current) {
                    $rows[] = $this->finish($current);
                }
                if (count($rows) >= self::MAX_QUESTIONS) {
                    $current = null;
                    break;
                }
                $current = $this->blank((int) $m[1], $m[2] ?? null);
                $mode = 'content';
                $after = mb_substr($text, mb_strlen($m[0]));
                $marks = array_slice($marks, mb_strlen($text) - mb_strlen(ltrim($after)));
                $text = trim($after);
                if ($text === '') {
                    $current['flags'] += $line['flags'];

                    continue;
                }
            }

            if (! $current) {
                $preamble++; // tiêu đề đề thi, thông tin trường lớp… trước "Câu 1"

                continue;
            }

            $current['flags'] += $line['flags'];

            if (preg_match('/^(?:Đáp\s*án(?:\s*đúng)?|ĐA|Answer|Kết\s*quả)\s*[:.\-–]\s*(.*)$/iu', $text, $m)) {
                $current['answer'] = trim($m[1]);
                $mode = 'meta';

                continue;
            }
            // Dấu ":" bắt buộc khi còn chữ phía sau — không thì "Giải hệ phương trình" bị hiểu là dòng lời giải.
            if (preg_match('/^(?:Lời\s*giải(?:\s*chi\s*tiết)?|Hướng\s*dẫn(?:\s*giải)?|Giải\s*thích|Giải)\s*(?:[:.\-–]\s*(.*))?$/iu', $text, $m)) {
                $mode = 'explanation';
                if (trim($m[1] ?? '') !== '') {
                    $current['explanation'][] = trim($m[1]);
                }

                continue;
            }
            if (preg_match('/^(?:Mức\s*độ|Độ\s*khó)\s*[:.\-–]\s*(.+)$/iu', $text, $m)) {
                $current['difficulty'] = self::DIFFICULTY_WORDS[mb_strtolower(trim($m[1], " .\t"))] ?? null;

                continue;
            }
            if (preg_match('/^Điểm\s*[:.\-–]\s*([\d.,]+)/iu', $text, $m)) {
                $current['points'] = $m[1];

                continue;
            }
            if (preg_match(self::SECTION_HEADING, $text) && mb_strtoupper($text) === $text) {
                $mode = 'skip'; // "PHẦN II. TỰ LUẬN" giữa hai câu — không thuộc câu nào

                continue;
            }

            if ($mode === 'explanation') {
                $current['explanation'][] = $text;

                continue;
            }
            if ($mode !== 'content') {
                continue;
            }

            $options = $this->options($text, $marks);
            if ($options !== null) {
                foreach ($options as $option) {
                    $current['options'][] = $option;
                }

                continue;
            }

            // Câu đã có đáp án lựa chọn mà còn dòng chữ thường → nối vào đáp án cuối (đáp án dài xuống dòng).
            if ($current['options']) {
                $last = array_key_last($current['options']);
                $current['options'][$last]['text'] .= ' '.$text;
            } else {
                $current['content'][] = $text;
            }
        }

        if ($current) {
            $rows[] = $this->finish($current);
        }

        $warnings = [];
        if ($rows === [] && $preamble > 0) {
            $warnings[] = 'Không tìm thấy câu hỏi nào. Mỗi câu phải bắt đầu bằng "Câu 1:", "Câu 2."…';
        }
        if (count($rows) >= self::MAX_QUESTIONS) {
            $warnings[] = 'Tệp quá dài — chỉ đọc '.self::MAX_QUESTIONS.' câu đầu, hãy tách phần còn lại sang tệp khác.';
        }

        return ['rows' => $rows, 'warnings' => $warnings];
    }

    // --- Đọc .docx thành các dòng chữ ---------------------------------------------------------

    /**
     * Mỗi đoạn văn (và mỗi lần ngắt dòng trong đoạn) thành một dòng. Công thức → "$latex$".
     * `marks` đánh dấu từng ký tự có gạch chân / tô đỏ / tô nền — cách đề Việt Nam đánh dấu đáp án đúng.
     *
     * @return array<int, array{text: string, marks: array<int, bool>, flags: array<string, true>}>
     */
    private function lines(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \InvalidArgumentException('Máy chủ chưa bật PHP zip — không đọc được tệp Word.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('Tệp không phải .docx hợp lệ. Nếu là .doc (Word 97–2003), hãy mở bằng Word và lưu lại dưới dạng .docx.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || $xml === '') {
            throw new \InvalidArgumentException('Tệp .docx không có nội dung văn bản.');
        }

        $dom = new DOMDocument;
        // LIBXML_NONET: không tải gì từ mạng; không bật LIBXML_NOENT → thực thể ngoài không được mở rộng (XXE).
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE)) {
            throw new \InvalidArgumentException('Không đọc được nội dung tệp Word (tệp hỏng?).');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);
        $body = $xpath->query('/w:document/w:body')->item(0);

        $lines = [];
        // Đoạn văn theo đúng thứ tự xuất hiện, kể cả đoạn nằm trong ô bảng (đáp án hay được kẻ bảng 2×2).
        foreach ($xpath->query('.//w:p', $body) as $paragraph) {
            foreach ($this->paragraphLines($paragraph) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** @return array<int, array{text: string, marks: array<int, bool>, flags: array<string, true>}> */
    private function paragraphLines(DOMElement $paragraph): array
    {
        $lines = [];
        $line = ['text' => '', 'marks' => [], 'flags' => []];

        $append = function (string $text, bool $marked) use (&$line) {
            $line['text'] .= $text;
            foreach (mb_str_split($text) as $_) {
                $line['marks'][] = $marked;
            }
        };

        $walk = function (DOMElement $node) use (&$walk, &$line, &$lines, $append) {
            foreach ($node->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                if ($child->namespaceURI === OmmlToLatex::NS && in_array($child->localName, ['oMath', 'oMathPara'], true)) {
                    $latex = $this->omml->convert($child);
                    $append($latex === '' ? '' : ' $'.$latex.'$ ', $this->formulaMarked($child));

                    continue;
                }

                if ($child->namespaceURI !== self::W) {
                    $walk($child);

                    continue;
                }

                switch ($child->localName) {
                    case 'r':
                        $marked = $this->runMarked($child);
                        foreach ($child->childNodes as $part) {
                            if (! $part instanceof DOMElement) {
                                continue;
                            }
                            match ($part->localName) {
                                't' => $append($part->textContent, $marked),
                                'tab' => $append("\t", false),
                                'br', 'cr' => (function () use (&$line, &$lines) {
                                    $lines[] = $line;
                                    $line = ['text' => '', 'marks' => [], 'flags' => []];
                                })(),
                                'drawing', 'pict' => (function () use (&$line, $append) {
                                    $line['flags']['image'] = true;
                                    $append(' [hình] ', false);
                                })(),
                                'object' => (function () use (&$line, $append) {
                                    // MathType / Equation 3.0 cũ: lưu như ảnh OLE, không có chữ để đọc.
                                    $line['flags']['ole_equation'] = true;
                                    $append(' [công thức] ', false);
                                })(),
                                default => null,
                            };
                        }
                        break;
                    case 'pPr':
                    case 'rPr':
                        break;
                    default:
                        $walk($child); // w:hyperlink, w:ins, w:smartTag… — chữ vẫn nằm trong w:r bên trong
                }
            }
        };

        $walk($paragraph);
        $lines[] = $line;

        return $lines;
    }

    private function runMarked(DOMElement $run): bool
    {
        foreach ($run->childNodes as $props) {
            if (! $props instanceof DOMElement || $props->localName !== 'rPr') {
                continue;
            }
            foreach ($props->childNodes as $p) {
                if (! $p instanceof DOMElement) {
                    continue;
                }
                $val = strtolower($p->getAttributeNS(self::W, 'val'));
                if ($p->localName === 'u' && $val !== 'none' && $val !== '0') {
                    return true;
                }
                if ($p->localName === 'color' && $this->isRed($val)) {
                    return true;
                }
                if ($p->localName === 'highlight' && $val !== 'none' && $val !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /** Công thức tô đỏ / gạch chân (đáp án đúng là một biểu thức) — Word để định dạng trong w:rPr của m:r. */
    private function formulaMarked(DOMElement $math): bool
    {
        foreach ($math->getElementsByTagNameNS(self::W, 'rPr') as $props) {
            if ($this->runMarked($props->parentNode)) {
                return true;
            }
        }

        return false;
    }

    private function isRed(string $hex): bool
    {
        if (! preg_match('/^[0-9a-f]{6}$/', $hex)) {
            return $hex === 'red';
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return $r >= 180 && $g <= 80 && $b <= 80;
    }

    // --- Dựng câu hỏi --------------------------------------------------------------------------

    /**
     * Dòng bắt đầu bằng "A." (hoặc "*A.") → tách mọi đáp án trên dòng; null nếu không phải dòng đáp án.
     *
     * @param  array<int, bool>  $marks
     * @return array<int, array{letter: string, text: string, marked: bool}>|null
     */
    private function options(string $text, array $marks): ?array
    {
        if (! preg_match('/^\*?[A-F]\s*[.)](?:\s+|(?=\$)|$)/u', $text)) {
            return null;
        }

        preg_match_all(self::OPTION_MARKER, $text, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        // Chữ cái phải liền nhau tính từ chữ đầu dòng ("A. … B. …" hoặc "C. … D. …" ở dòng thứ hai)
        // — "điểm B. thuộc…" lẫn trong đáp án không làm vỡ đáp án.
        $markers = [];
        $expected = $found[0][2][0] ?? 'A';
        foreach ($found as $match) {
            if ($match[2][0] !== $expected) {
                continue;
            }
            $markers[] = [
                'star' => $match[1][0] === '*',
                'letter' => $match[2][0],
                'start' => mb_strlen(substr($text, 0, $match[0][1])),
                'end' => mb_strlen(substr($text, 0, $match[0][1] + strlen($match[0][0]))),
            ];
            $expected = chr(ord($expected) + 1);
        }

        $options = [];
        foreach ($markers as $i => $marker) {
            $end = $markers[$i + 1]['start'] ?? mb_strlen($text);
            $body = trim(mb_substr($text, $marker['end'], $end - $marker['end']));
            $letterMarked = $marks[$marker['start'] + ($marker['star'] ? 1 : 0)] ?? false;
            $bodyMarks = array_slice($marks, $marker['end'], $end - $marker['end']);
            $bodyMarked = $bodyMarks !== [] && count(array_filter($bodyMarks)) > count($bodyMarks) / 2;

            $options[] = [
                'letter' => $marker['letter'],
                'text' => $body,
                'marked' => $marker['star'] || $letterMarked || $bodyMarked,
            ];
        }

        return $options;
    }

    private function blank(int $number, ?string $points): array
    {
        return [
            'number' => $number, 'content' => [], 'options' => [], 'answer' => null, 'explanation' => [],
            'difficulty' => null, 'points' => $points, 'flags' => [],
        ];
    }

    /** Câu thô → dòng cùng dạng CSV + cảnh báo / lỗi để hiện ở trang xem trước. */
    private function finish(array $q): array
    {
        $errors = [];
        $warnings = [];

        $options = [];
        foreach ($q['options'] as $option) {
            $options[$option['letter']] = $option; // trùng chữ cái → giữ cái sau (sửa dòng trước)
        }
        ksort($options);

        $row = [
            'number' => $q['number'],
            'content' => $this->html($q['content']),
            'explanation' => $this->html($q['explanation']),
            'difficulty' => $q['difficulty'] ?? 'medium',
            'points' => $q['points'] !== null ? str_replace(',', '.', $q['points']) : '1',
            'topic_id' => '',
            'correct' => '',
            'accepted' => '',
        ];

        $answer = trim((string) $q['answer'], " .\t");

        if ($options !== []) {
            $letters = array_keys($options);
            // "B", "A, C", "A và C", "AC" → danh sách chữ cái; không có dòng Đáp án thì lấy đáp án được gạch chân/tô đỏ.
            $letterList = preg_replace('/\s*(?:,|;|\+|&|và|and)\s*/iu', '', $answer);
            $correct = $answer !== '' && preg_match('/^[A-F]{1,6}$/i', $letterList)
                ? array_values(array_unique(str_split(strtoupper($letterList))))
                : array_keys(array_filter($options, fn ($o) => $o['marked']));

            $i = 0;
            $indexes = [];
            foreach ($options as $letter => $option) {
                $i++;
                $row["option_{$i}"] = $this->html([$option['text']]);
                if (in_array($letter, $correct, true)) {
                    $indexes[] = $i;
                }
            }

            $row['type'] = count($indexes) > 1 ? Question::TYPE_MULTIPLE_CHOICE : Question::TYPE_SINGLE_CHOICE;
            $row['correct'] = implode(',', $indexes);

            if (count($options) < 2) {
                $errors[] = 'chỉ đọc được 1 đáp án lựa chọn';
            }
            if ($indexes === []) {
                $errors[] = 'chưa có đáp án đúng (thêm dòng "Đáp án: B" hoặc gạch chân đáp án đúng)';
            }
            if ($letters !== array_slice(['A', 'B', 'C', 'D', 'E', 'F'], 0, count($letters))) {
                $warnings[] = 'thứ tự đáp án bị thiếu chữ cái ('.implode(', ', $letters).')';
            }
        } elseif (preg_match('/^(Đúng|Sai|Đ|S|True|False)$/iu', $answer)) {
            $row['type'] = Question::TYPE_TRUE_FALSE;
            $row['correct'] = in_array(mb_strtolower($answer), ['đúng', 'đ', 'true'], true) ? 'true' : 'false';
        } elseif ($answer !== '') {
            $row['type'] = Question::TYPE_SHORT_ANSWER;
            $row['accepted'] = implode('|', array_filter(array_map('trim', preg_split('/[|;]/', $answer))));
        } else {
            // Không đáp án, không lựa chọn → tự luận, giáo viên chấm tay.
            $row['type'] = Question::TYPE_ESSAY;
            $warnings[] = 'không có đáp án — nhập thành câu tự luận (chấm tay)';
        }

        if ($q['content'] === []) {
            $errors[] = 'thiếu nội dung câu hỏi';
        }
        if (isset($q['flags']['image'])) {
            $warnings[] = 'có hình ảnh — hệ thống chưa lấy được hình, hãy chèn lại sau khi nhập';
        }
        if (isset($q['flags']['ole_equation'])) {
            $warnings[] = 'có công thức MathType dạng ảnh — trong Word chọn MathType → Convert Equations → "Office Math" rồi tải lại';
        }

        return $row + ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Dòng chữ → HTML an toàn: escape từng dòng (giữ nguyên dấu < trong "x < 3"), nối bằng <br>.
     * Công thức nằm trong $...$ — KaTeX đọc textContent nên &lt; vẫn hiện đúng là <.
     *
     * @param  array<int, string>  $lines
     */
    private function html(array $lines): string
    {
        $lines = array_map(fn ($l) => e(trim(preg_replace('/[ \t]+/u', ' ', $l))), $lines);

        return implode('<br>', array_filter($lines, fn ($l) => $l !== ''));
    }
}
