<?php

namespace App\Http\Requests\Teacher;

use App\Models\Question;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question
            ? $this->user()->can('update', $question)
            : $this->user()->can('create', Question::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'content' => ['required', 'string', 'max:20000'],
            'explanation' => ['nullable', 'string', 'max:20000'],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'points' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'source' => ['nullable', Rule::in(['manual', 'ai'])],
            // Đến từ nút "Sửa" ở trang nháp AI.
            'ai_draft_id' => ['nullable', 'integer'],
            'ai_item_index' => ['nullable', 'integer', 'min:0'],

            // Loại trắc nghiệm.
            'options' => ['nullable', 'array', 'max:10'],
            'options.*.content' => ['required_with:options', 'string', 'max:2000'],
            'correct_options' => ['nullable', 'array'],

            // Đúng/Sai.
            'true_false_value' => ['nullable', Rule::in(['0', '1'])],

            // Điền chỗ trống: mỗi dòng là một chỗ trống, các đáp án cách nhau bằng "|".
            'blanks' => ['nullable', 'array', 'max:10'],
            'blanks.*' => ['nullable', 'string', 'max:500'],

            // Trả lời ngắn: danh sách đáp án chấp nhận, cách nhau bằng "|".
            'accepted' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Mỗi loại câu hỏi có ràng buộc riêng mà `rules()` không diễn đạt được:
     * trắc nghiệm phải có ít nhất 2 lựa chọn và đúng số đáp án đúng cần thiết.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $type = $this->input('type');
            $options = array_values(array_filter(
                $this->input('options', []),
                fn ($o) => filled($o['content'] ?? null),
            ));
            $correct = $this->input('correct_options', []);

            if (in_array($type, Question::CHOICE_TYPES, true)) {
                if (count($options) < 2) {
                    $v->errors()->add('options', 'Câu trắc nghiệm cần ít nhất 2 lựa chọn.');
                }

                if (count($correct) === 0) {
                    $v->errors()->add('correct_options', 'Phải chọn ít nhất một đáp án đúng.');
                } elseif ($type === Question::TYPE_SINGLE_CHOICE && count($correct) > 1) {
                    $v->errors()->add('correct_options', 'Loại "một đáp án đúng" chỉ được chọn một đáp án.');
                }
            }

            if ($type === Question::TYPE_TRUE_FALSE && $this->input('true_false_value') === null) {
                $v->errors()->add('true_false_value', 'Phải chọn đáp án Đúng hoặc Sai.');
            }

            if ($type === Question::TYPE_FILL_BLANK) {
                $blanks = array_filter($this->input('blanks', []), 'filled');
                if (count($blanks) === 0) {
                    $v->errors()->add('blanks', 'Phải nhập đáp án cho ít nhất một chỗ trống.');
                }
            }

            if ($type === Question::TYPE_SHORT_ANSWER && blank($this->input('accepted'))) {
                $v->errors()->add('accepted', 'Phải nhập ít nhất một đáp án chấp nhận được.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'grade_id' => 'lớp',
            'topic_id' => 'chủ đề',
            'type' => 'loại câu hỏi',
            'content' => 'nội dung',
            'explanation' => 'giải thích',
            'difficulty' => 'độ khó',
            'points' => 'điểm',
        ];
    }
}
