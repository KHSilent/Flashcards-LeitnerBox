<?php

namespace App\Services;

use App\Models\Flashcard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SmartFlashcardProcessor
{
    public function process(Flashcard $flashcard): Flashcard
    {
        if (! in_array($flashcard->type, [Flashcard::TYPE_ENGLISH_ACTIVE, Flashcard::TYPE_ENGLISH_PASSIVE], true)) {
            throw ValidationException::withMessages([
                'type' => 'پردازش هوشمند فقط برای کارت‌های انگلیسی فعال و انگلیسی پسیو فعال است.',
            ]);
        }

        $apiKey = config('services.openai.key');

        if (! $apiKey) {
            throw ValidationException::withMessages([
                'openai' => 'OPENAI_API_KEY تنظیم نشده است.',
            ]);
        }

        $flashcard->load('sides');

        $sourceText = $this->sourceText($flashcard);
        $data = $this->generateCardData($flashcard, $sourceText);
        $audioUrl = $this->generateSpeech($flashcard, $data['tts_text'] ?: $data['english_word']);
        $imageUrl = $this->generateImage($flashcard, $data['image_prompt']);
        $sides = $this->sidesFor($flashcard, $data, $audioUrl, $imageUrl);

        $flashcard->sides()->delete();

        foreach ($sides as $side) {
            $flashcard->sides()->create($side);
        }

        return $flashcard->refresh()->load('sides');
    }

    private function sourceText(Flashcard $flashcard): string
    {
        $source = trim((string) ($flashcard->sides->first()?->content ?: $flashcard->title));

        if ($source === '') {
            throw ValidationException::withMessages([
                'content' => 'برای پردازش هوشمند، کارت باید حداقل عنوان یا متن روی اول داشته باشد.',
            ]);
        }

        return $source;
    }

    /**
     * @return array<string, string>
     */
    private function generateCardData(Flashcard $flashcard, string $sourceText): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(90)
            ->acceptJson()
            ->post($this->baseUrl().'/responses', [
                'model' => config('services.openai.text_model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->userPrompt($flashcard->type, $sourceText),
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'smart_flashcard',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'openai' => 'دریافت متن از OpenAI انجام نشد.',
            ]);
        }

        $payload = $response->json();
        $json = $this->extractOutputText($payload);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'openai' => 'خروجی متنی OpenAI قابل خواندن نبود.',
            ]);
        }

        Log::info('flashcard.smart_process.openai_text_usage', [
            'flashcard_id' => $flashcard->id,
            'type' => $flashcard->type,
            'model' => config('services.openai.text_model'),
            'usage' => Arr::get($payload, 'usage'),
        ]);

        return array_map(fn ($value) => trim((string) $value), $data);
    }

    private function generateSpeech(Flashcard $flashcard, string $text): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(90)
            ->withHeaders(['Accept' => 'audio/mpeg'])
            ->post($this->baseUrl().'/audio/speech', [
                'model' => config('services.openai.tts_model'),
                'voice' => config('services.openai.tts_voice'),
                'input' => $text,
                'response_format' => 'mp3',
                'instructions' => 'Pronounce the word clearly in standard American English. No extra words.',
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'openai' => 'ساخت ویس تلفظ انجام نشد.',
            ]);
        }

        $path = $this->storePublicFile('flashcards/ai/audios', 'mp3', $response->body());

        Log::info('flashcard.smart_process.openai_tts_usage', [
            'flashcard_id' => $flashcard->id,
            'model' => config('services.openai.tts_model'),
            'voice' => config('services.openai.tts_voice'),
            'input_characters' => mb_strlen($text),
        ]);

        return $path;
    }

    private function generateImage(Flashcard $flashcard, string $prompt): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->acceptJson()
            ->post($this->baseUrl().'/images/generations', [
                'model' => config('services.openai.image_model'),
                'prompt' => $prompt,
                'size' => '1024x1024',
                'quality' => 'low',
                'output_format' => 'png',
                'n' => 1,
            ]);

        if ($response->failed()) {
            Log::warning('flashcard.smart_process.openai_image_failed', [
                'flashcard_id' => $flashcard->id,
                'model' => config('services.openai.image_model'),
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);

            throw ValidationException::withMessages([
                'openai' => $this->openAiErrorMessage($response, 'ساخت تصویر آموزشی انجام نشد.'),
            ]);
        }

        $payload = $response->json();
        $image = Arr::get($payload, 'data.0.b64_json');

        if (! is_string($image) || $image === '') {
            Log::warning('flashcard.smart_process.openai_image_missing_data', [
                'flashcard_id' => $flashcard->id,
                'model' => config('services.openai.image_model'),
                'body' => $payload,
            ]);

            throw ValidationException::withMessages([
                'openai' => 'خروجی تصویر OpenAI قابل ذخیره نبود.',
            ]);
        }

        $path = $this->storePublicFile('flashcards/ai/images', 'png', base64_decode($image));

        Log::info('flashcard.smart_process.openai_image_usage', [
            'flashcard_id' => $flashcard->id,
            'model' => config('services.openai.image_model'),
            'usage' => Arr::get($payload, 'usage'),
        ]);

        return $path;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<int, array<string, mixed>>
     */
    private function sidesFor(Flashcard $flashcard, array $data, string $audioUrl, string $imageUrl): array
    {
        if ($flashcard->type === Flashcard::TYPE_ENGLISH_ACTIVE) {
            return [
                [
                    'side_number' => 1,
                    'content' => $data['side_1_content'],
                    'images' => [],
                    'audios' => [],
                ],
                [
                    'side_number' => 2,
                    'content' => $data['side_2_content'],
                    'images' => [$imageUrl],
                    'audios' => [$audioUrl],
                ],
            ];
        }

        return [
            [
                'side_number' => 1,
                'content' => $data['side_1_content'],
                'images' => [],
                'audios' => [$audioUrl],
            ],
            [
                'side_number' => 2,
                'content' => $data['side_2_content'],
                'images' => [$imageUrl],
                'audios' => [],
            ],
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.openai.base_url'), '/');
    }

    private function storePublicFile(string $directory, string $extension, string $contents): string
    {
        $path = $directory.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOutputText(array $payload): string
    {
        foreach ((array) Arr::get($payload, 'output', []) as $item) {
            foreach ((array) Arr::get($item, 'content', []) as $content) {
                if (Arr::get($content, 'type') === 'output_text' && is_string(Arr::get($content, 'text'))) {
                    return Arr::get($content, 'text');
                }
            }
        }

        return (string) Arr::get($payload, 'output_text', '');
    }

    private function openAiErrorMessage($response, string $fallback): string
    {
        $message = $response->json('error.message');

        if (is_string($message) && $message !== '') {
            return $fallback.' '.$message;
        }

        return $fallback;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert English vocabulary flashcard writer for Persian-speaking learners at CEFR B2.
Write concise, consistent flashcards. Persian must be used only for the direct meaning/translation of the headword and Persian equivalents of confusing words. All explanations, examples, near-word items, and grammar labels must be in English.
Use American English. Keep examples natural and useful.
For the near-word section, prefer clean B2-useful synonyms, near-synonyms, or close alternatives, such as "large" for "big". Do not list simple inflections or comparatives like "bigger, biggest" there. Put inflections and parts of speech only in the final grammar/forms note.
For the confusing section, include words that learners may genuinely mix up in spelling, meaning, register, or usage. If there are no meaningful confusing words, write "None".
Return only valid JSON matching the schema.
PROMPT;
    }

    private function userPrompt(string $type, string $sourceText): string
    {
        if ($type === Flashcard::TYPE_ENGLISH_ACTIVE) {
            return <<<PROMPT
Create an active-recall English vocabulary flashcard from this Persian meaning:
{$sourceText}

Card requirements:
- Side 1 content: only the Persian meaning/word, cleaned and concise.
- Side 2 content exact structure:
  1. English headword followed by its part of speech in parentheses
  2. American pronunciation written in simple text
  3. Blank line
  4. Useful near words: B2-useful synonyms, near-synonyms, or close alternatives in English. Do not use simple inflections/comparatives here.
  5. Blank line
  6. Commonly confused / similar words: English words learners may mix up, each with Persian equivalent, or "None"
  7. Blank line
  8. One or two B2-level English examples
  9. Blank line
  10. A compact grammar/forms note listing whether it is noun, verb, adjective, adverb, etc., plus common inflections/forms if useful
- TTS text: the English headword only.
- Image prompt: a simple educational anime-style image prompt for the English headword, no text in the image.
PROMPT;
        }

        return <<<PROMPT
Create a passive-recognition English vocabulary flashcard from this English word or phrase:
{$sourceText}

Card requirements:
- Side 1 content exact structure:
  1. English headword
  2. American pronunciation written in simple text
- Side 2 content exact structure:
  1. English headword followed by its part of speech in parentheses
  2. American pronunciation written in simple text
  3. Persian meaning only
  4. Blank line
  5. Useful near words: B2-useful synonyms, near-synonyms, or close alternatives in English. Do not use simple inflections/comparatives here.
  6. Blank line
  7. Commonly confused / similar words: English words learners may mix up, each with Persian equivalent, or "None"
  8. Blank line
  9. One or two B2-level English examples
  10. Blank line
  11. A compact grammar/forms note listing whether it is noun, verb, adjective, adverb, etc., plus common inflections/forms if useful
- TTS text: the English headword only.
- Image prompt: a simple educational anime-style image prompt for the English headword, no text in the image.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'english_word' => ['type' => 'string'],
                'persian_meaning' => ['type' => 'string'],
                'tts_text' => ['type' => 'string'],
                'image_prompt' => ['type' => 'string'],
                'side_1_content' => ['type' => 'string'],
                'side_2_content' => ['type' => 'string'],
            ],
            'required' => [
                'english_word',
                'persian_meaning',
                'tts_text',
                'image_prompt',
                'side_1_content',
                'side_2_content',
            ],
        ];
    }
}
