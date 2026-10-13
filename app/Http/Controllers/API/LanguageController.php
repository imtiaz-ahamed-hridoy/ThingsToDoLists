<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

class LanguageController extends Controller
{
    /**
     * Get the supported languages for Bokun and GlobalTix.
     */
    public function getSupportedLanguages()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'matched_languages' => [
                    ['name' => 'English', 'code' => 'en'],
                    ['name' => 'Chinese (Simplified)', 'code' => 'zh'],
                    ['name' => 'Japanese', 'code' => 'ja'],
                    ['name' => 'Korean', 'code' => 'ko'],
                    ['name' => 'Thai', 'code' => 'th'],
                ],
                'bokun_rest_languages' => [
                    ['name' => 'French', 'code' => 'fr'],
                    ['name' => 'German', 'code' => 'de'],
                    ['name' => 'Spanish', 'code' => 'es'],
                    ['name' => 'Italian', 'code' => 'it'],
                    ['name' => 'Portuguese', 'code' => 'pt'],
                    ['name' => 'Dutch', 'code' => 'nl'],
                    ['name' => 'Danish', 'code' => 'da'],
                    ['name' => 'Swedish', 'code' => 'sv'],
                    ['name' => 'Norwegian', 'code' => 'no'],
                    ['name' => 'Finnish', 'code' => 'fi'],
                    ['name' => 'Icelandic', 'code' => 'is'],
                    ['name' => 'Polish', 'code' => 'pl'],
                    ['name' => 'Czech', 'code' => 'cs'],
                    ['name' => 'Hungarian', 'code' => 'hu'],
                    ['name' => 'Croatian', 'code' => 'hr'],
                    ['name' => 'Slovenian', 'code' => 'sl'],
                    ['name' => 'Slovak', 'code' => 'sk'],
                    ['name' => 'Romanian', 'code' => 'ro'],
                    ['name' => 'Russian', 'code' => 'ru'],
                    ['name' => 'Turkish', 'code' => 'tr'],
                    ['name' => 'Greek', 'code' => 'el'],
                    ['name' => 'Arabic', 'code' => 'ar'],
                    ['name' => 'Hebrew', 'code' => 'he'],
                    ['name' => 'Chinese (Traditional)', 'code' => 'zh-TW'],
                ],
                'globaltix_rest_languages' => [
                    ['name' => 'Indonesian', 'code' => 'id'],
                    ['name' => 'Malay', 'code' => 'ms'],
                    ['name' => 'Vietnamese', 'code' => 'vi'],
                ]
            ]
        ]);
    }
}
