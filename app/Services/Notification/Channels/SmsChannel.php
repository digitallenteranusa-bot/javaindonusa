<?php

namespace App\Services\Notification\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    protected string $driver;
    protected string $apiKey;
    protected string $baseUrl;
    protected ?string $sender;
    protected ?string $username;
    protected ?string $password;

    public function __construct()
    {
        $this->driver = config('notification.sms.driver', 'zenziva');
        $this->apiKey = config('notification.sms.api_key', '');
        $this->username = config('notification.sms.username');
        $this->password = config('notification.sms.password');
        $this->sender = config('notification.sms.sender');

        $this->baseUrl = match ($this->driver) {
            'zenziva' => 'https://console.zenziva.net',
            'twilio' => 'https://api.twilio.com',
            'nexmo' => 'https://rest.nexmo.com',
            'raja_sms' => 'https://rajasms.id/api',
            'nusasms' => 'https://api.nusasms.com',
            default => '',
        };
    }

    /**
     * Send SMS message
     */
    public function send(string $phone, string $message): array
    {
        if (empty($this->apiKey) && empty($this->username)) {
            Log::warning('SMS credentials not configured');
            return ['success' => false, 'message' => 'SMS credentials not configured'];
        }

        return match ($this->driver) {
            'zenziva' => $this->sendViaZenziva($phone, $message),
            'twilio' => $this->sendViaTwilio($phone, $message),
            'nexmo' => $this->sendViaNexmo($phone, $message),
            'raja_sms' => $this->sendViaRajaSms($phone, $message),
            'nusasms' => $this->sendViaNusaSms($phone, $message),
            default => ['success' => false, 'message' => 'Unknown driver'],
        };
    }

    /**
     * Send via Zenziva
     * Documentation: https://console.zenziva.net/dokumentasi
     */
    protected function sendViaZenziva(string $phone, string $message): array
    {
        try {
            // Zenziva supports both reguler and masking SMS
            $endpoint = config('notification.sms.zenziva_type', 'reguler') === 'masking'
                ? '/masking/api/sendsms'
                : '/reguler/api/sendsms';

            $response = Http::asForm()->post($this->baseUrl . $endpoint, [
                'userkey' => $this->username,
                'passkey' => $this->apiKey,
                'to' => $phone,
                'message' => $message,
            ]);

            $data = $response->json();

            // Zenziva returns status 1 for success
            if ($response->successful() && ($data['status'] ?? 0) == 1) {
                return [
                    'success' => true,
                    'message' => 'SMS sent via Zenziva',
                    'message_id' => $data['messageId'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['text'] ?? 'Failed to send SMS',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Zenziva SMS error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via Twilio
     * Documentation: https://www.twilio.com/docs/sms
     */
    protected function sendViaTwilio(string $phone, string $message): array
    {
        try {
            $sid = $this->username;
            $token = $this->apiKey;

            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("{$this->baseUrl}/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $this->sender,
                    'To' => '+' . ltrim($phone, '+'),
                    'Body' => $message,
                ]);

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS sent via Twilio',
                    'message_id' => $data['sid'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send SMS',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via Nexmo (Vonage)
     * Documentation: https://developer.vonage.com/messaging/sms
     */
    protected function sendViaNexmo(string $phone, string $message): array
    {
        try {
            $response = Http::post($this->baseUrl . '/sms/json', [
                'api_key' => $this->username,
                'api_secret' => $this->apiKey,
                'to' => $phone,
                'from' => $this->sender,
                'text' => $message,
            ]);

            $data = $response->json();
            $status = $data['messages'][0]['status'] ?? '1';

            if ($response->successful() && $status === '0') {
                return [
                    'success' => true,
                    'message' => 'SMS sent via Nexmo',
                    'message_id' => $data['messages'][0]['message-id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['messages'][0]['error-text'] ?? 'Failed to send SMS',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Nexmo SMS error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via RajaSMS
     * Documentation: https://rajasms.id/dokumentasi
     */
    protected function sendViaRajaSms(string $phone, string $message): array
    {
        try {
            $response = Http::post($this->baseUrl . '/sms/send', [
                'apikey' => $this->apiKey,
                'callbackurl' => config('app.url') . '/webhook/sms',
                'datapacket' => [
                    [
                        'number' => $phone,
                        'message' => $message,
                    ]
                ],
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['success'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'SMS sent via RajaSMS',
                    'message_id' => $data['sending_id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send SMS',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('RajaSMS error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send via NusaSMS
     * Documentation: https://www.nusasms.com/api-docs
     */
    protected function sendViaNusaSms(string $phone, string $message): array
    {
        try {
            $response = Http::withHeaders([
                'APIKey' => $this->apiKey,
            ])->post($this->baseUrl . '/api/v3/sendsms', [
                'user' => $this->username,
                'password' => $this->password,
                'SMSText' => $message,
                'GSM' => $phone,
                'output' => 'json',
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? 0) == 1) {
                return [
                    'success' => true,
                    'message' => 'SMS sent via NusaSMS',
                    'message_id' => $data['messageid'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send SMS',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('NusaSMS error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send bulk SMS
     */
    public function sendBulk(array $recipients, string $message): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($recipients as $phone) {
            $result = $this->send($phone, $message);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'phone' => $phone,
                    'error' => $result['message'],
                ];
            }

            // Add delay to avoid rate limiting
            usleep(200000); // 200ms delay
        }

        return $results;
    }

    /**
     * Check balance/quota
     */
    public function checkBalance(): array
    {
        try {
            return match ($this->driver) {
                'zenziva' => $this->checkZenzivaBalance(),
                default => ['success' => false, 'message' => 'Balance check not supported for this driver'],
            };
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function checkZenzivaBalance(): array
    {
        $response = Http::asForm()->post($this->baseUrl . '/api/balance', [
            'userkey' => $this->username,
            'passkey' => $this->apiKey,
        ]);

        $data = $response->json();

        return [
            'success' => $response->successful(),
            'balance' => $data['balance'] ?? 0,
            'response' => $data,
        ];
    }

    /**
     * Get available driver options
     */
    public static function getAvailableDrivers(): array
    {
        return [
            'zenziva' => [
                'name' => 'Zenziva',
                'website' => 'https://zenziva.net',
                'description' => 'SMS Gateway populer di Indonesia (Reguler & Masking)',
            ],
            'twilio' => [
                'name' => 'Twilio',
                'website' => 'https://twilio.com',
                'description' => 'Global SMS provider',
            ],
            'nexmo' => [
                'name' => 'Nexmo (Vonage)',
                'website' => 'https://vonage.com',
                'description' => 'Global communications API',
            ],
            'raja_sms' => [
                'name' => 'RajaSMS',
                'website' => 'https://rajasms.id',
                'description' => 'SMS Gateway Indonesia',
            ],
            'nusasms' => [
                'name' => 'NusaSMS',
                'website' => 'https://nusasms.com',
                'description' => 'SMS Gateway Indonesia',
            ],
        ];
    }
}
