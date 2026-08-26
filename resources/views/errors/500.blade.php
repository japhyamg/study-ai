@include('errors.layout', [
    'code' => 500,
    'title' => 'Something went wrong on our end',
    'message' => 'This one is our fault, not yours. The problem has been logged and we are looking into it. Try again in a moment.',
    // Matches the `reference` on the logged exception, so support can find the
    // exact entry from the six characters the user reads out.
    'reference' => app()->bound('error.reference') ? app('error.reference') : null,
])
