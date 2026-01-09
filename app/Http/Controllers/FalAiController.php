<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class FalAiController extends Controller
{
    private function generateUrl($path)
    {
        return Storage::cloud()->temporaryUrl($path, Carbon::now()->addHour());
    }

    public function generateVideo(Request $request)
    {
        $this->validate($request, [
            'text_input' => 'required|string',
            'voice'      => 'required|string',
            'prompt'     => 'nullable|string',
            'image'      => 'required|image|mimes:jpeg,png,jpg,webp',
        ]);

        $file = $request->file('image');
        $savedPath = Storage::disk('social_cdn')->putFileAs('fal_images', $file, uniqid() . '.' . $file->getClientOriginalExtension());
        $imageUrl = $this->generateUrl($savedPath);

        $payload = [
            'image_url'  => $imageUrl,
            'text_input' => $request->text_input,
            'voice'      => $request->voice,
            'prompt'     => $request->prompt ?? 'A person speaking clearly and confidently.',
        ];
        $client = new Client();

        try {
            $response = $client->post('https://queue.fal.run/fal-ai/infinitalk/single-text', [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);
            Log::debug('Fal.ai Response', [
                'status_code' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                Log::error('FalAi Queue Submit Error', ['status' => $statusCode, 'response' => $result]);
                return response()->json([
                    'error' => 'Failed to submit job to queue',
                    'details' => $result
                ], 500);
            }

            return response()->json([
                'status'      => 'submitted',
                'request_id'  => $result['request_id'],
                'status_url'  => $result['status_url'],
                'message'     => 'Video generation started. Use request_id to check status.'
            ], 202);

        } catch (\Exception $e) {
            Log::error('FalAi GenerateVideo Network Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Network request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkStatus($request_uuid)
    {
        $client = new Client();

        try {
            $statusUrl = "https://queue.fal.run/fal-ai/infinitalk/requests/{$request_uuid}/status?logs=1";

            $response = $client->get($statusUrl, [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                ],
                'http_errors' => false,
                'timeout'     => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($statusCode !== 200) {
                Log::error('FalAi Check Status Error', [
                    'request_id' => $request_uuid,
                    'status'     => $statusCode,
                    'response'   => $result
                ]);
                return response()->json([
                    'request_id' => $request_uuid,
                    'status'     => 'ERROR',
                    'details'    => $result,
                    'message'    => 'Fal.ai API returned error'
                ], $statusCode);
            }
            
            $status = $result['status'] ?? 'UNKNOWN';
            $responseData = [
                'request_id' => $request_uuid,
                'status'     => $status,
                'details'    => $result
            ];

            switch ($status) {
                case 'IN_QUEUE':
                    $responseData['message'] = 'Задание в очереди';
                    $responseData['queue_position'] = $result['queue_position'] ?? null;
                    break;

                case 'IN_PROGRESS':
                    $responseData['message'] = 'Идет генерация видео';
                    $responseData['logs'] = $result['logs'] ?? [];
                    break;

                case 'COMPLETED':
                    $resultUrl = "https://queue.fal.run/fal-ai/infinitalk/requests/{$request_uuid}";
                    $resultResponse = $client->get($resultUrl, [
                        'headers' => [
                            'Authorization' => 'Key ' . env('FAL_KEY'),
                        ],
                        'http_errors' => false,
                        'timeout' => 30,
                    ]);

                    $resultData = json_decode((string) $resultResponse->getBody(), true);
                    $videoUrl = Arr::get($resultData, 'response.data.video.url')
                        ?? Arr::get($resultData, 'video.url');

                    if ($resultResponse->getStatusCode() === 200 && $videoUrl) {
                        $responseData['message']   = 'Видео готово!';
                        $responseData['video_url'] = $videoUrl;
                        $responseData['raw_result'] = $resultData;
                    } else {
                        $responseData['message'] = 'Видео готово, но не удалось получить результат';
                        $responseData['result_error'] = $resultData;
                    }
                    break;

                case 'FAILED':
                    $responseData['message'] = 'Произошла ошибка при генерации видео';
                    break;

                case 'CANCELLED':
                    $responseData['message'] = 'Генерация видео отменена';
                    break;

                default:
                    $responseData['message'] = 'Неизвестный статус';
                    break;
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('FalAi CheckStatus Network Error', [
                'request_id' => $request_uuid,
                'error'      => $e->getMessage()
            ]);
            return response()->json([
                'error'      => 'Network request failed',
                'message'    => $e->getMessage(),
                'request_id' => $request_uuid
            ], 500);
        }
    }

    public function generatePrompt(Request $request)
    {
        $this->validate($request, [
            'input_concept' => 'required|string',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,webp',
        ]);

        $payload = [
            'input_concept' => $request->input('input_concept'),
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $savedPath = Storage::disk('social_cdn')->putFileAs('fal_prompt_images', $file, uniqid() . '.' . $file->getClientOriginalExtension());
            $imageUrl = $this->generateUrl($savedPath);
            $payload['image_url'] = $imageUrl;
        }

        $client = new Client();

        try {
            $response = $client->post('https://queue.fal.run/fal-ai/video-prompt-generator', [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($statusCode !== 200) {
                return response()->json([
                    'error' => 'Failed to submit prompt generation',
                    'details' => $result,
                ], $statusCode);
            }

            return response()->json([
                'status'     => 'submitted',
                'request_uuid' => $result['request_id'],
                'status_url' => $result['status_url'],
                'message'    => 'Prompt generation started. Use request_id to check status.'
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Network request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkPromptStatus($request_uuid)
    {
        $client = new Client();

        try {
            $statusUrl = "https://queue.fal.run/fal-ai/video-prompt-generator/requests/{$request_uuid}/status?logs=1";

            $response = $client->get($statusUrl, [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                ],
                'http_errors' => false,
                'timeout'     => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($statusCode !== 200) {
                return response()->json([
                    'request_uuid' => $request_uuid,
                    'status'     => 'ERROR',
                    'details'    => $result,
                ], $statusCode);
            }

            $status = $result['status'] ?? 'UNKNOWN';
            $responseData = [
                'request_uuid' => $request_uuid,
                'status'     => $status,
                'details'    => $result
            ];

            if ($status === 'COMPLETED') {
                $resultUrl = "https://queue.fal.run/fal-ai/video-prompt-generator/requests/{$request_uuid}";
                $resultResponse = $client->get($resultUrl, [
                    'headers' => [
                        'Authorization' => 'Key ' . env('FAL_KEY'),
                    ],
                    'http_errors' => false,
                    'timeout' => 30,
                ]);

                $resultData = json_decode((string) $resultResponse->getBody(), true);
                $prompt = $resultData['response']['data']['prompt'] ?? null;

                $responseData['prompt'] = $prompt;
                $responseData['raw'] = $resultData;
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json([
                'error'      => 'Network request failed',
                'message'    => $e->getMessage(),
                'request_uuid' => $request_uuid
            ], 500);
        }
    }

    public function generateVideoTurbo(Request $request)
    {
        $this->validate($request, [
            'prompt' => 'required|string',
            'num_frames' => 'nullable|integer',
            'resolution'  => 'nullable|string',
            'seed' => 'nullable|integer',
        ]);

        $payload = [
            'prompt'     => $request->prompt,
            'num_frames' => $request->num_frames ?? 240,
            'seed'       => $request->seed ?? null,
        ];

        $client = new Client();

        try {
            $response = $client->post('https://queue.fal.run/fal-ai/wan/v2.2-a14b/text-to-video/turbo', [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);
            
            Log::debug('Fal.ai Turbo Response', [
                'status_code' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                return response()->json([
                    'error' => 'Failed to submit Turbo job',
                    'details' => $result
                ], 500);
            }

            return response()->json([
                'status'      => 'submitted',
                'request_uuid'  => $result['request_id'],
                'status_url'  => $result['status_url'],
                'message'     => 'Turbo video generation started. Use request_id to check status.'
            ], 202);

        } catch (\Exception $e) {
            Log::error('FalAi Turbo Network Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Network request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkTurboStatus($request_uuid)
    {
        $client = new Client();

        try {
            $statusUrl = "https://queue.fal.run/fal-ai/wan/v2.2-a14b/text-to-video/turbo/requests/{$request_uuid}";

            $response = $client->get($statusUrl, [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                ],
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            if ($statusCode !== 200) {
                return response()->json([
                    'error' => 'Failed to check Turbo job status',
                    'details' => $result
                ], 500);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('FalAi Turbo Status Network Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Network request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateVideoVeoFast(Request $request)
    {
        $this->validate($request, [
            'prompt'       => 'required|string',
            'aspect_ratio' => 'nullable|string|in:16:9,9:16,1:1',
            'resolution'   => 'nullable|string|in:720p,1080p',
            'generate_audio' => 'nullable|boolean',
        ]);

        $payload = [
            'prompt'         => $request->prompt,
            'aspect_ratio'   => $request->aspect_ratio ?? '16:9',
            'resolution'     => $request->resolution ?? '720p',
            'generate_audio' => $request->generate_audio ?? true,
        ];

        $client = new Client();

        try {
            $response = $client->post('https://queue.fal.run/fal-ai/veo3/fast', [
                'headers' => [
                    'Authorization' => 'Key ' . env('FAL_KEY'),
                    'Content-Type'  => 'application/json',
                ],
                'json'        => $payload,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);

            Log::debug('Fal.ai VeoFast Response', ['status' => $statusCode, 'response' => $result]);

            if ($statusCode !== 200) {
                Log::error('FalAi VeoFast Submit Error', ['status' => $statusCode, 'response' => $result]);
                return response()->json([
                    'error'   => 'Failed to submit VeoFast job',
                    'details' => $result
                ], 500);
            }

            return response()->json([
                'status'     => 'submitted',
                'request_uuid' => $result['request_id'],
                'status_url' => $result['status_url'],
                'message'    => 'VeoFast video generation started. Use request_id to check status.'
            ], 202);

        } catch (\Exception $e) {
            Log::error('FalAi VeoFast Network Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'Network request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkVeoFastStatus($request_uuid)
    {
        $client = new Client();
        $headers = [
            'Authorization' => 'Key ' . env('FAL_KEY'),
            'Accept'        => 'application/json',
        ];

        try {
            $statusUrl = "https://queue.fal.run/fal-ai/veo3/requests/{$request_uuid}/status";
            $statusRes = $client->get($statusUrl, ['headers' => $headers]);
            $statusData = json_decode($statusRes->getBody(), true);

            $status = $statusData['status'] ?? 'UNKNOWN';
            $responseData = [
                'request_uuid' => $request_uuid,
                'status'       => $status,
                'raw'          => $statusData,
            ];

            if ($status === 'COMPLETED') {
                $videoUrl = $statusData['raw']['video']['url']
                    ?? $statusData['raw']['output'][0]['url']
                    ?? null;

                if ($videoUrl) {
                    $responseData['success']   = true;
                    $responseData['video_url'] = $videoUrl;
                    $responseData['message']   = 'Видео готово!';
                } else {
                    $responseData['success'] = false;
                    $responseData['message'] = 'Видео еще в процессе генерации, URL не готов';
                }

                return response()->json($responseData);
            }

            $responseData['success'] = false;
            $responseData['message'] = 'Видео еще в процессе генерации';
            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('FalAi VeoFast Status Error', [
                'request_uuid' => $request_uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error'        => 'Network request failed',
                'message'      => $e->getMessage(),
                'request_uuid' => $request_uuid
            ], 500);
        }
    }
}
