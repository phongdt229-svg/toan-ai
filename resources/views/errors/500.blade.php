@extends('errors.layout')

@section('code', 'Lỗi 500')
@section('title', 'Hệ thống đang gặp sự cố')

@section('message')
    <p>Lỗi nằm ở phía chúng tôi, không phải do bạn làm sai. Sự cố đã được ghi lại và đội kỹ thuật sẽ xử lý.</p>
    <p>Bạn thử tải lại sau ít phút; nếu vẫn vậy thì báo giúp chúng tôi qua trang hỗ trợ.</p>
    {{-- Chỉ khi debug bật (APP_DEBUG hoặc cookie của /_diag.php): Laravel thường tự vẽ trang debug riêng,
         nhưng abort(500) hoặc lúc trình vẽ debug cũng hỏng thì rơi về trang này — vẫn phải thấy được lỗi gốc. --}}
    @if (config('app.debug') && isset($exception))
        @php($cause = $exception->getPrevious() ?? $exception)
        <pre style="text-align:left;white-space:pre-wrap;word-break:break-word;font-size:12px;background:#fee2e2;color:#7f1d1d;padding:12px;border-radius:8px;margin-top:16px">{{ get_class($cause) }}: {{ $cause->getMessage() }}
{{ $cause->getFile() }}:{{ $cause->getLine() }}</pre>
    @endif
@endsection

@section('actions')
                <a class="btn primary" href="javascript:location.reload()">Tải lại trang</a>
                <a class="btn ghost" href="/">Về trang chủ</a>
@endsection
