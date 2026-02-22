<?php

namespace App\Services\Notification\Channels;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    protected string $driver;
    protected string $apiKey;
    protected string $baseUrl;
    protected ?string $sender;

    // Mekari Qontak specific
    protected string $mekariChannelId;
    protected string $mekariTemplateId;

    // Meta WhatsApp Cloud API specific
    protected string $metaPhoneNumberId;
    protected string $metaBusinessAccountId;

    public function __construct()
    {
        // Read from database first, fallback to config
        $this->driver = $this->getSetting('whatsapp_driver') ?? config('notification.whatsapp.driver', 'fonnte');
        $this->apiKey = $this->getSetting('whatsapp_api_key') ?? config('notification.whatsapp.api_key', '');
        $this->sender = $this->getSetting('whatsapp_sender') ?? config('notification.whatsapp.sender');

        // Mekari Qontak specific settings
        $this->mekariChannelId = $this->getSetting('whatsapp_mekari_channel_id') ?? config('notification.whatsapp.mekari.channel_id', '');
        $this->mekariTemplateId = $this->getSetting('whatsapp_mekari_template_id') ?? config('notification.whatsapp.mekari.template_id', '');

        // Meta WhatsApp Cloud API specific settings
        $this->metaPhoneNumberId = $this->getSetting('whatsapp_meta_phone_number_id') ?? config('notification.whatsapp.meta.phone_number_id', '');
        $this->metaBusinessAccountId = $this->getSetting('whatsapp_meta_business_account_id') ?? config('notification.whatsapp.meta.business_account_id', '');

        $this->baseUrl = match ($this->driver) {
            'fonnte' => 'https://api.fonnte.com',
            'wablas' => 'https://pati.wablas.com',
            'dripsender' => 'https://api.dripsender.id',
            'mekari' => 'https://service.qontak.com',
            'meta' => 'https://graph.facebook.com/v21.0',
            'manual' => '',
            default => '',
        };
    }

    /**
     * Get setting value from database
     */
    protected function getSetting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    /**
     * Send WhatsApp message
     */
    public function send(string $phone, string $message, array $options = []): array
    {
        if ($this->driver === 'manual') {
            return $this->generateManualUrl($phone, $message);
        }

        if (empty($this->apiKey)) {
            Log::warning('WhatsApp API key not configured');
            return ['success' => false, 'message' => 'API key not configured'];
        }

        return match ($this->driver) {
            'fonnte' => $this->sendViaFonnte($phone, $message, $options),
            'wablas' => $this->sendViaWablas($phone, $message, $options),
            'dripsender' => $this->sendViaDripsender($phone, $message, $options),
            'mekari' => $this->sendViaMekari($phone, $message, $options),
            'meta' => $this->sendViaMeta($phone, $message, $options),
            default => ['success' => false, 'message' => 'Unknown driver'],
        };
    }

    /**
     * Send via Fonnte API
     * Documentation: https://fonnte.com/api
     */
    protected function sendViaFonnte(string $phone, string $message, array $options = []): array
    {
        try {
            $payload = [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ];

            // Add optional parameters
            if (!empty($options['delay'])) {
                $payload['delay'] = $options['delay'];
            }

            if (!empty($options['schedule'])) {
                $payload['schedule'] = $options['schedule'];
            }

            // Send image if provided
            if (!empty($options['image'])) {
                $payload['url'] = $options['image'];
            }

            // Send document if provided
            if (!empty($options['document'])) {
                $payload['file'] = $options['document'];
                $payload['filename'] = $options['filename'] ?? 'document.pdf';
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->post($this->baseUrl . '/send', $payload);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Message sent via Fonnte',
                    'message_id' => $data['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['reason'] ?? 'Failed to send message',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Fonnte API error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via Wablas API
     * Documentation: https://wablas.com/documentation
     */
    protected function sendViaWablas(string $phone, string $message, array $options = []): array
    {
        try {
            $payload = [
                'phone' => $phone,
                'message' => $message,
            ];

            // Add optional parameters
            if (!empty($options['image'])) {
                $payload['image'] = $options['image'];
            }

            if (!empty($options['document'])) {
                $payload['document'] = $options['document'];
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->post($this->baseUrl . '/api/send-message', $payload);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Message sent via Wablas',
                    'message_id' => $data['data']['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send message',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Wablas API error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via Dripsender API
     * Documentation: https://dripsender.id/docs
     */
    protected function sendViaDripsender(string $phone, string $message, array $options = []): array
    {
        try {
            $payload = [
                'api_key' => $this->apiKey,
                'phone' => $phone,
                'text' => $message,
            ];

            if (!empty($options['image'])) {
                $payload['media_url'] = $options['image'];
            }

            $response = Http::post($this->baseUrl . '/api/v1/send', $payload);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Message sent via Dripsender',
                    'message_id' => $data['message_id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send message',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Dripsender API error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via Mekari Qontak API (WhatsApp Business API)
     * Autentikasi: Bearer token langsung dari Settings → API token → Omnichannel
     * Documentation: https://docs.qontak.com
     */
    protected function sendViaMekari(string $phone, string $message, array $options = []): array
    {
        try {
            if (empty($this->mekariChannelId)) {
                return ['success' => false, 'message' => 'Mekari Channel Integration ID tidak dikonfigurasi'];
            }

            $templateId = $options['template_id'] ?? $this->mekariTemplateId;
            if (empty($templateId)) {
                return ['success' => false, 'message' => 'Mekari Template ID tidak dikonfigurasi'];
            }

            $toName = $options['name'] ?? 'Pelanggan';

            // Jika params eksplisit diberikan, gunakan sebagai multi-parameter template
            // Jika tidak, fallback ke seluruh pesan sebagai {{1}}
            if (!empty($options['params']) && is_array($options['params'])) {
                $bodyParams = [];
                foreach (array_values($options['params']) as $i => $val) {
                    $bodyParams[] = [
                        'key'        => (string) ($i + 1),
                        'value_text' => (string) $val,
                        'value'      => 'param' . ($i + 1),
                    ];
                }
            } else {
                $bodyParams = [
                    ['key' => '1', 'value_text' => $message, 'value' => 'pesan'],
                ];
            }

            $payload = [
                'to_name'                => $toName,
                'to_number'              => $phone,
                'message_template_id'    => $templateId,
                'channel_integration_id' => $this->mekariChannelId,
                'language'               => ['code' => 'id'],
                'parameters'             => ['body' => $bodyParams],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/api/open/v1/broadcasts/whatsapp/direct', $payload);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Pesan terkirim via Mekari Qontak',
                    'message_id' => $data['data']['id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['error_messages'][0] ?? $data['message'] ?? 'Gagal mengirim pesan',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Mekari Qontak API error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via Meta WhatsApp Cloud API
     * Documentation: https://developers.facebook.com/docs/whatsapp/cloud-api
     *
     * Mendukung 2 mode:
     * 1. Template message (wajib untuk memulai percakapan / di luar 24-hour window)
     *    - Kirim options['template_name'] + options['params']
     * 2. Text message (hanya dalam 24-hour customer service window)
     *    - Jika tidak ada template, kirim plain text
     */
    protected function sendViaMeta(string $phone, string $message, array $options = []): array
    {
        try {
            if (empty($this->metaPhoneNumberId)) {
                return ['success' => false, 'message' => 'Meta Phone Number ID tidak dikonfigurasi'];
            }

            $url = $this->baseUrl . '/' . $this->metaPhoneNumberId . '/messages';

            // Determine template name: explicit option > per-type lookup > fallback
            $templateName = $options['template_name']
                ?? $this->getMetaTemplateName($options['notification_type'] ?? '')
                ?? null;

            if ($templateName && !empty($options['params'])) {
                // Template message mode
                $components = [];
                $bodyParams = [];
                foreach (array_values($options['params']) as $val) {
                    $bodyParams[] = [
                        'type' => 'text',
                        'text' => (string) $val,
                    ];
                }
                if (!empty($bodyParams)) {
                    $components[] = [
                        'type' => 'body',
                        'parameters' => $bodyParams,
                    ];
                }

                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $options['language'] ?? 'id'],
                        'components' => $components,
                    ],
                ];
            } else {
                // Plain text message (only works within 24-hour window)
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'body' => $message,
                    ],
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            $data = $response->json();

            if ($response->successful() && !empty($data['messages'])) {
                return [
                    'success' => true,
                    'message' => 'Pesan terkirim via Meta WhatsApp Cloud API',
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'response' => $data,
                ];
            }

            // Parse Meta API error
            $errorMsg = $data['error']['message']
                ?? $data['error']['error_data']['details']
                ?? 'Gagal mengirim pesan';

            return [
                'success' => false,
                'message' => $errorMsg,
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp Cloud API error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Meta template name from database setting for a notification type
     */
    protected function getMetaTemplateName(string $type): ?string
    {
        if (empty($type)) {
            return null;
        }

        $name = $this->getSetting('whatsapp_meta_tpl_' . $type);

        return !empty($name) ? $name : null;
    }

    /**
     * Generate manual WhatsApp URL (wa.me link)
     * For cases where automatic sending is not available
     */
    protected function generateManualUrl(string $phone, string $message): array
    {
        $url = "https://wa.me/{$phone}?text=" . urlencode($message);

        return [
            'success' => true,
            'message' => 'Manual URL generated',
            'url' => $url,
            'manual' => true,
        ];
    }

    /**
     * Send bulk messages
     */
    public function sendBulk(array $recipients, string $message, array $options = []): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recipients as $phone) {
            $result = $this->send($phone, $message, $options);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'phone' => $phone,
                    'error' => $result['message'],
                ];
            }

            // Add delay between messages to avoid rate limiting / spam detection
            $delaySeconds = config('notification.whatsapp.rate_limit.bulk_delay_seconds', 15);
            sleep($delaySeconds);
        }

        return $results;
    }

    /**
     * Check API connection status
     */
    public function checkStatus(): array
    {
        if ($this->driver === 'manual') {
            return ['success' => true, 'status' => 'Manual mode - no API check needed'];
        }

        if (empty($this->apiKey)) {
            return ['success' => false, 'status' => 'API key not configured'];
        }

        try {
            // Mekari: cek dengan hit endpoint profile/me
            if ($this->driver === 'mekari') {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])->get($this->baseUrl . '/api/open/v1/auth/app/login');

                return [
                    'success' => $response->successful(),
                    'status' => $response->successful() ? 'Token valid' : 'Token tidak valid atau expired',
                    'response' => $response->json(),
                ];
            }

            // Meta: cek phone number ID valid
            if ($this->driver === 'meta') {
                if (empty($this->metaPhoneNumberId)) {
                    return ['success' => false, 'status' => 'Phone Number ID tidak dikonfigurasi'];
                }
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])->get($this->baseUrl . '/' . $this->metaPhoneNumberId);

                $data = $response->json();

                return [
                    'success' => $response->successful(),
                    'status' => $response->successful()
                        ? 'Connected - ' . ($data['verified_name'] ?? $data['display_phone_number'] ?? 'OK')
                        : ($data['error']['message'] ?? 'Token tidak valid'),
                    'response' => $data,
                ];
            }

            $endpoint = match ($this->driver) {
                'fonnte' => $this->baseUrl . '/device',
                'wablas' => $this->baseUrl . '/api/device/info',
                'dripsender' => $this->baseUrl . '/api/v1/status',
                default => '',
            };

            if (empty($endpoint)) {
                return ['success' => false, 'status' => 'Unknown driver'];
            }

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->get($endpoint);

            return [
                'success' => $response->successful(),
                'status' => $response->successful() ? 'Connected' : 'Connection failed',
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'status' => $e->getMessage()];
        }
    }

    /**
     * Get available driver options
     */
    public static function getAvailableDrivers(): array
    {
        return [
            'fonnte' => [
                'name' => 'Fonnte',
                'website' => 'https://fonnte.com',
                'description' => 'WhatsApp Gateway populer di Indonesia',
            ],
            'wablas' => [
                'name' => 'Wablas',
                'website' => 'https://wablas.com',
                'description' => 'WhatsApp Business API Gateway',
            ],
            'dripsender' => [
                'name' => 'Dripsender',
                'website' => 'https://dripsender.id',
                'description' => 'WhatsApp Marketing Automation',
            ],
            'mekari' => [
                'name' => 'Mekari Qontak',
                'website' => 'https://qontak.com',
                'description' => 'WhatsApp Business API resmi (Mekari Qontak)',
                'requires_template' => true,
            ],
            'meta' => [
                'name' => 'Meta Cloud API',
                'website' => 'https://developers.facebook.com/docs/whatsapp/cloud-api',
                'description' => 'WhatsApp Business API langsung dari Meta (official)',
                'requires_template' => true,
            ],
            'manual' => [
                'name' => 'Manual (wa.me)',
                'website' => '',
                'description' => 'Generate wa.me links only (no auto send)',
            ],
        ];
    }
}
