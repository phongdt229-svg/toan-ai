<?php

namespace App\Support\Docx;

use DOMElement;
use DOMNode;

/**
 * Đổi công thức Office Math (OMML — thứ Word lưu khi gõ bằng Insert → Equation) sang LaTeX
 * để hiển thị bằng KaTeX như câu hỏi gõ tay ($...$).
 *
 * Chỉ phủ các cấu trúc có trong đề Toán phổ thông (phân số, căn, mũ, hệ, ma trận, tổng/tích phân,
 * hàm, vectơ, gạch trên…). Cấu trúc lạ thì vẫn lấy phần chữ bên trong — mất định dạng còn hơn mất nội dung.
 */
class OmmlToLatex
{
    public const NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /** Ký tự Unicode Word chèn từ bảng ký hiệu → lệnh LaTeX. Khoảng trắng cuối để không dính chữ sau. */
    private const SYMBOLS = [
        '≤' => '\le ', '≥' => '\ge ', '≠' => '\ne ', '±' => '\pm ', '∓' => '\mp ', '×' => '\times ',
        '÷' => '\div ', '·' => '\cdot ', '⋅' => '\cdot ', '∞' => '\infty ', 'π' => '\pi ', 'α' => '\alpha ',
        'β' => '\beta ', 'γ' => '\gamma ', 'Δ' => '\Delta ', '∆' => '\Delta ', 'δ' => '\delta ', 'ε' => '\varepsilon ',
        'θ' => '\theta ', 'λ' => '\lambda ', 'μ' => '\mu ', 'σ' => '\sigma ', 'φ' => '\varphi ', 'ω' => '\omega ',
        'Ω' => '\Omega ', '∈' => '\in ', '∉' => '\notin ', '⊂' => '\subset ', '⊃' => '\supset ', '⊆' => '\subseteq ',
        '∪' => '\cup ', '∩' => '\cap ', '∅' => '\varnothing ', '∀' => '\forall ', '∃' => '\exists ', '→' => '\to ',
        '⇒' => '\Rightarrow ', '⇔' => '\Leftrightarrow ', '←' => '\leftarrow ', '↔' => '\leftrightarrow ',
        '°' => '^{\circ}', '∠' => '\angle ', '△' => '\triangle ', '⊥' => '\perp ', '∥' => '\parallel ',
        '≈' => '\approx ', '≡' => '\equiv ', '∼' => '\sim ', '≅' => '\cong ', 'ℝ' => '\mathbb{R}', 'ℕ' => '\mathbb{N}',
        'ℤ' => '\mathbb{Z}', 'ℚ' => '\mathbb{Q}', 'ℂ' => '\mathbb{C}', '…' => '\ldots ', '′' => "'", '−' => '-', '∗' => '*',
        '%' => '\%', '#' => '\#', '&' => '\&', '$' => '\$', '{' => '\{', '}' => '\}', '\\' => '\backslash ',
    ];

    /** Tên hàm Word để thẳng trong m:fName → lệnh LaTeX có sẵn (chữ đứng, khoảng cách đúng). */
    private const FUNCTIONS = ['sin', 'cos', 'tan', 'cot', 'log', 'ln', 'lim', 'exp', 'max', 'min', 'sup', 'inf', 'arcsin', 'arccos', 'arctan'];

    private const NARY = ['∑' => '\sum', '∏' => '\prod', '∫' => '\int', '∬' => '\iint', '∭' => '\iiint', '∮' => '\oint', '⋃' => '\bigcup', '⋂' => '\bigcap'];

    private const DELIMITERS = ['(' => '(', ')' => ')', '[' => '[', ']' => ']', '{' => '\{', '}' => '\}', '|' => '|', '‖' => '\|', '⟨' => '\langle', '⟩' => '\rangle', '⌊' => '\lfloor', '⌋' => '\rfloor', '⌈' => '\lceil', '⌉' => '\rceil', '' => '.'];

    public function convert(DOMNode $node): string
    {
        return $this->tidy($this->node($node));
    }

    private function node(DOMNode $node): string
    {
        if (! $node instanceof DOMElement) {
            return '';
        }

        if ($node->namespaceURI !== self::NS) {
            // w:r lạc vào trong công thức (hiếm) — vẫn lấy chữ.
            return $node->localName === 't' ? $this->text($node->textContent) : $this->children($node);
        }

        return match ($node->localName) {
            'r' => $this->run($node),
            'f' => $this->fraction($node),
            'sSup' => $this->group($this->part($node, 'e')).'^{'.$this->part($node, 'sup').'}',
            'sSub' => $this->group($this->part($node, 'e')).'_{'.$this->part($node, 'sub').'}',
            'sSubSup' => $this->group($this->part($node, 'e')).'_{'.$this->part($node, 'sub').'}^{'.$this->part($node, 'sup').'}',
            'sPre' => '{}_{'.$this->part($node, 'sub').'}^{'.$this->part($node, 'sup').'}'.$this->group($this->part($node, 'e')),
            'rad' => $this->radical($node),
            'd' => $this->delimiter($node),
            'eqArr' => '\begin{array}{l}'.$this->rows($node).'\end{array}',
            'm' => '\begin{matrix}'.$this->matrix($node).'\end{matrix}',
            'nary' => $this->nary($node),
            'func' => $this->func($node),
            'limLow' => $this->limit($node, '_'),
            'limUpp' => $this->limit($node, '^'),
            'acc' => $this->accent($node),
            'bar' => ($this->prop($node, 'barPr', 'pos') === 'top' ? '\overline{' : '\underline{').$this->part($node, 'e').'}',
            'borderBox' => '\boxed{'.$this->part($node, 'e').'}',
            'groupChr' => $this->groupChr($node),
            // Thuộc tính trình bày, không phải nội dung.
            'rPr', 'ctrlPr', 'fPr', 'radPr', 'dPr', 'naryPr', 'funcPr', 'accPr', 'barPr', 'sSupPr', 'sSubPr',
            'sSubSupPr', 'sPrePr', 'eqArrPr', 'mPr', 'limLowPr', 'limUppPr', 'groupChrPr', 'boxPr', 'borderBoxPr',
            'oMathParaPr', 'phantPr', 'mcs', 'mc' => '',
            default => $this->children($node),
        };
    }

    private function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child);
        }

        return $out;
    }

    /** Nội dung của phần tử con m:{name} đầu tiên (num, den, e, sub, sup, deg…). */
    private function part(DOMElement $node, string $name): string
    {
        $child = $this->child($node, $name);

        return $child ? $this->tidy($this->children($child)) : '';
    }

    private function child(DOMElement $node, string $name): ?DOMElement
    {
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement && $c->namespaceURI === self::NS && $c->localName === $name) {
                return $c;
            }
        }

        return null;
    }

    /** Giá trị m:val của m:{pr}/m:{name}; null nếu không khai. */
    private function prop(DOMElement $node, string $pr, string $name): ?string
    {
        $props = $this->child($node, $pr);
        $el = $props ? $this->child($props, $name) : null;

        return $el?->getAttributeNS(self::NS, 'val');
    }

    /** Cờ bật/tắt kiểu OOXML: có thẻ mà không ghi val nghĩa là BẬT. */
    private function flag(DOMElement $node, string $pr, string $name): bool
    {
        $value = $this->prop($node, $pr, $name);

        return $value !== null && in_array($value, ['', '1', 'on', 'true'], true);
    }

    private function run(DOMElement $node): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === 't') {
                $out .= $this->text($c->textContent);
            }
        }

        return $out;
    }

    private function text(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text) as $ch) {
            $out .= self::SYMBOLS[$ch] ?? $ch;
        }

        return $out;
    }

    private function fraction(DOMElement $node): string
    {
        $num = $this->part($node, 'num');
        $den = $this->part($node, 'den');

        return $this->prop($node, 'fPr', 'type') === 'lin'
            ? $this->group($num).'/'.$this->group($den)
            : '\frac{'.$num.'}{'.$den.'}';
    }

    private function radical(DOMElement $node): string
    {
        $deg = $this->part($node, 'deg');
        $hidden = $this->flag($node, 'radPr', 'degHide');

        return $deg === '' || $hidden
            ? '\sqrt{'.$this->part($node, 'e').'}'
            : '\sqrt['.$deg.']{'.$this->part($node, 'e').'}';
    }

    private function delimiter(DOMElement $node): string
    {
        $beg = $this->prop($node, 'dPr', 'begChr') ?? '(';
        $end = $this->prop($node, 'dPr', 'endChr') ?? ')';
        $sep = $this->prop($node, 'dPr', 'sepChr') ?? '|';

        $items = [];
        $onlyEqArr = true;
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement && $c->namespaceURI === self::NS && $c->localName === 'e') {
                $items[] = $c;
                $onlyEqArr = $onlyEqArr && $this->isOnly($c, 'eqArr');
            }
        }

        // Hệ phương trình: dấu { mở, không có dấu đóng, bên trong là eqArr → cases (cách KaTeX viết hệ).
        if ($beg === '{' && $end === '' && count($items) === 1 && $onlyEqArr) {
            return '\begin{cases}'.$this->rows($this->child($items[0], 'eqArr')).'\end{cases}';
        }
        // Hợp các khoảng / "hoặc": dấu [ mở không đóng.
        if ($beg === '[' && $end === '' && count($items) === 1 && $onlyEqArr) {
            return '\left[\begin{array}{l}'.$this->rows($this->child($items[0], 'eqArr')).'\end{array}\right.';
        }

        $inner = implode($this->text($sep), array_map(fn ($e) => $this->tidy($this->children($e)), $items));

        return '\left'.(self::DELIMITERS[$beg] ?? $beg).$inner.'\right'.(self::DELIMITERS[$end] ?? $end);
    }

    private function isOnly(DOMElement $node, string $name): bool
    {
        $elements = array_filter(iterator_to_array($node->childNodes), fn ($c) => $c instanceof DOMElement && $c->localName !== 'ctrlPr');

        return count($elements) === 1 && reset($elements)->localName === $name;
    }

    private function rows(?DOMElement $node): string
    {
        if (! $node) {
            return '';
        }
        $rows = [];
        foreach ($node->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === 'e') {
                $rows[] = $this->tidy($this->children($c));
            }
        }

        return implode(' \\\\ ', $rows);
    }

    private function matrix(DOMElement $node): string
    {
        $rows = [];
        foreach ($node->childNodes as $row) {
            if ($row instanceof DOMElement && $row->localName === 'mr') {
                $cells = [];
                foreach ($row->childNodes as $c) {
                    if ($c instanceof DOMElement && $c->localName === 'e') {
                        $cells[] = $this->tidy($this->children($c));
                    }
                }
                $rows[] = implode(' & ', $cells);
            }
        }

        return implode(' \\\\ ', $rows);
    }

    private function nary(DOMElement $node): string
    {
        $chr = $this->prop($node, 'naryPr', 'chr') ?? '∫';
        $op = self::NARY[$chr] ?? $this->text($chr);
        $sub = $this->flag($node, 'naryPr', 'subHide') ? '' : $this->part($node, 'sub');
        $sup = $this->flag($node, 'naryPr', 'supHide') ? '' : $this->part($node, 'sup');

        return $op.($sub !== '' ? '_{'.$sub.'}' : '').($sup !== '' ? '^{'.$sup.'}' : '').' '.$this->part($node, 'e');
    }

    private function func(DOMElement $node): string
    {
        $name = $this->part($node, 'fName');
        $plain = trim($name);

        if (in_array($plain, self::FUNCTIONS, true)) {
            $name = '\\'.$plain;
        }

        return $name.' '.$this->part($node, 'e');
    }

    private function limit(DOMElement $node, string $dir): string
    {
        $base = trim($this->part($node, 'e'));
        $base = in_array($base, self::FUNCTIONS, true) ? '\\'.$base : $this->group($base);

        return $base.$dir.'{'.$this->part($node, 'lim').'}';
    }

    private function accent(DOMElement $node): string
    {
        $chr = $this->prop($node, 'accPr', 'chr') ?? "\u{0302}";
        $command = match ($chr) {
            "\u{20D7}", '→', "\u{20D1}" => '\overrightarrow',
            "\u{0305}", '¯', '‾', "\u{0304}" => '\overline',
            "\u{0303}", '~' => '\tilde',
            "\u{0307}" => '\dot',
            "\u{030C}" => '\check',
            default => '\widehat',
        };

        return $command.'{'.$this->part($node, 'e').'}';
    }

    private function groupChr(DOMElement $node): string
    {
        $top = $this->prop($node, 'groupChrPr', 'pos') === 'top';

        return ($top ? '\overbrace{' : '\underbrace{').$this->part($node, 'e').'}';
    }

    /** Bọc ngoặc nhọn khi cần, để x^2 đứng sau (a+b) thành {a+b}^2 chứ không phải b^2. */
    private function group(string $latex): string
    {
        return mb_strlen($latex) <= 1 || preg_match('/^\\\\[a-zA-Z]+$/', $latex) ? $latex : '{'.$latex.'}';
    }

    private function tidy(string $latex): string
    {
        return trim(preg_replace('/ {2,}/', ' ', $latex));
    }
}
