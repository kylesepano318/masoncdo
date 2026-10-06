<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return ['section_type' => 'required|in:hero,lodge_feature,rich_text,image_text,video,full_width_image,historical_document,officers,members_grid,timeline,affiliations,gallery,quote,cta,spacer,celebrations', 'title' => 'nullable|string|max:255', 'subtitle' => 'nullable|string|max:255', 'body' => 'nullable|string|max:100000', 'image' => 'nullable|string|max:1000', 'mobile_image' => 'nullable|string|max:1000', 'settings' => 'nullable|array', 'settings.background' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/', 'settings.text_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/', 'settings.button_url' => 'nullable|string|max:1000|regex:~^(https?://|/(?!/))~', 'settings.background_image' => 'nullable|string|max:1000', 'settings.height' => 'nullable|integer|min:200|max:1200', 'settings.overlay_opacity' => 'nullable|numeric|min:0|max:1', 'settings.alignment' => 'nullable|in:left,center,right', 'settings.style' => 'nullable|in:contained,background', 'settings.items' => 'nullable|array', 'settings.items.*.url' => 'nullable|string|regex:~^(https?://|/(?!/))~', 'display_order' => 'sometimes|integer|min:0', 'is_visible' => 'required|boolean',
            'settings.video_title' => 'nullable|string|max:255',
            'settings.video_url' => ['required_if:section_type,video', 'nullable', 'string', 'max:1000', function ($attribute, $value, $fail) {
                $url = parse_url($value);
                $id = null;
                if (is_array($url) && ($url['scheme'] ?? '') === 'https') {
                    $host = strtolower($url['host'] ?? '');
                    $path = $url['path'] ?? '';
                    if ($host === 'youtu.be') {
                        $id = substr($path, 1);
                    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
                        if ($path === '/watch') {
                            parse_str($url['query'] ?? '', $query);
                            $id = $query['v'] ?? null;
                        } elseif (preg_match('~^/embed/([a-zA-Z0-9_-]{11})$~', $path, $matches)) {
                            $id = $matches[1];
                        }
                    }
                }
                if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_-]{11}$/', $id)) {
                    $fail('Enter a valid HTTPS YouTube video link.');
                }
            }],
        ];
    }
}
