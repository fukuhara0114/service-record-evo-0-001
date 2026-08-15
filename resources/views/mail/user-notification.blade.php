@php
    $displayName = trim((string) ($recipientKanjiName ?: $recipientName));
@endphp
{{ $displayName }} 様

{{ $bodyText }}

---
このメールは Service Record システムから自動送信されています。
