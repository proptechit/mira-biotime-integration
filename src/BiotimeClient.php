<?php
class BiotimeClient
{
    private $api_token;
    private $start_time;
    private $end_time;
    private $api_url;

    public function __construct($config)
    {
        $this->api_token = $config['api_token'];
        $this->start_time = $config['start_time'];
        $this->end_time = $config['end_time'];
        $this->api_url = rtrim($config['transactions_url'], '/');
    }

    public function fetchNewPunches()
    {
        $url = $this->api_url;

        $payload = [
            'StartDate' => $this->start_time,
            'EndDate'   => $this->end_time,
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Token: ' . $this->api_token,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 200) {
            throw new Exception("API Request failed with status code: $http_status");
        }

        $data = json_decode($response, true);

        return $data['message'] ?? [];
    }
}
