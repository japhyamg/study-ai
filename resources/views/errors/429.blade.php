@include('errors.layout', [
    'code' => 429,
    'title' => 'Too many requests',
    'message' => 'You have been going a little fast for us. Wait a few seconds and try again.',
])
