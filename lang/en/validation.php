<?php

declare(strict_types=1);

/*
 * The framework's validation messages, with Get Sorted's wording for failed uploads: a person should see
 * "We couldn't upload your identity document", never the internal field name (spec 020 follow-up).
 */
$framework = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

return array_replace_recursive($framework, [
    'uploaded' => 'We couldn’t upload your :attribute. Please check your connection and try again.',
    'attributes' => [
        'uploads.id_document' => 'identity document',
        'uploads.proof_of_address' => 'proof of address',
        'uploads.profile_photo' => 'profile photo',
        'uploads.pirb' => 'PIRB certificate',
        'uploads.electrical_registered_person' => 'registration certificate',
        'photoUpload' => 'photo',
    ],
]);
