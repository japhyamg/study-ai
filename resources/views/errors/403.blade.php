@php
    // Only show an abort() message that was deliberately written for a user.
    // Policy denials fall back to framework text like "This action is
    // unauthorized.", which is noise, and a custom exception message could
    // carry internals we do not want on screen.
    $custom = $exception?->getMessage() ?: '';
    $generic = ['This action is unauthorized.', 'Forbidden', 'Unauthorized'];
    $message = in_array($custom, $generic, true) || $custom === ''
        ? 'Your account does not have permission to view this page. If you think it should, ask an administrator at your school.'
        : $custom;
@endphp

@include('errors.layout', [
    'code' => 403,
    'title' => 'You do not have access to this',
    'message' => $message,
])
