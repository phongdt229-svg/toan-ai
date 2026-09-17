<?php

/*
|--------------------------------------------------------------------------
| AI Tutor
|--------------------------------------------------------------------------
| Key chỉ nằm ở server (.env) — không bao giờ gửi xuống trình duyệt (§29).
*/

return [

    // fake: câu trả lời mẫu, không tốn tiền — dùng cho dev và test.
    // openai: gọi API thật, bắt buộc có OPENAI_API_KEY.
    'provider' => env('AI_PROVIDER', 'fake'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
    ],

    /*
    | Đơn giá USD / 1 triệu token để ƯỚC TÍNH chi phí trên trang admin.
    | Không phải hoá đơn thật — đối chiếu với trang billing của nhà cung cấp.
    */
    'pricing' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'fake' => ['input' => 0, 'output' => 0],
    ],

    /*
    | Số lượt AI mỗi ngày theo gói. Phase 8 sẽ đọc từ package_features ('ai.daily_requests');
    | tới lúc đó dùng bảng này. null = không giới hạn.
    */
    'daily_limits' => [
        'student' => ['free' => 20, 'pro' => 100, 'premium' => 300],
        'teacher' => 60,
    ],

    // Số tin nhắn cũ gửi kèm mỗi lượt chat — giới hạn token, cuộc trò chuyện dài không đội giá.
    'chat_history_messages' => 10,

    // Giới hạn độ dài câu trả lời theo chế độ.
    'max_output_tokens' => [
        'chat' => 700,
        'hint' => 300,
        'explain' => 900,
        'check_answer' => 600,
        'similar_exercise' => 700,
        'analyze_mistake' => 900,
        'generate_questions' => 3500,
        'generate_lesson' => 3500,
        'rewrite' => 1200,
    ],
];
