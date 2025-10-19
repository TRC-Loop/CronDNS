<?php

require_once __DIR__ . '/BaseProvider.php';

class CloudflareProvider extends BaseProvider {
    public function updateIp(string $ip): bool {
        $apiToken = $this->credentials['cfApiToken'] ?? null;
        $zoneId = $this->credentials['cfZoneId'] ?? null;

        if (!$apiToken || !$zoneId) {
            $this->logger->error("Missing credentials for Cloudflare: {$this->domain}");
            return false;
        }

        $apiUrl = "https://api.cloudflare.com/client/v4/zones/$zoneId/dns_records?name={$this->domain}";
        $headers = [
            "Authorization: Bearer $apiToken",
            "Content-Type: application/json"
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $resp = curl_exec($ch);
        if (!$resp) {
            $this->logger->error("Failed Cloudflare DNS fetch for {$this->domain}");
            return false;
        }
        $data = json_decode($resp, true);
        $record = $data['result'][0] ?? null;
        if (!$record) {
            $this->logger->error("No DNS record found for {$this->domain}");
            return false;
        }

        $recordId = $record['id'];
        $updateUrl = "https://api.cloudflare.com/client/v4/zones/$zoneId/dns_records/$recordId";
        $payload = json_encode([
            'type' => $record['type'],
            'name' => $record['name'],
            'content' => $ip,
            'ttl' => 120,
            'proxied' => $record['proxied']
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => $updateUrl,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $payload
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $ok = strpos($resp, '"success":true') !== false;
        if ($ok) $this->logger->info("Cloudflare updated {$this->domain} to $ip");
        else $this->logger->error("Cloudflare update failed for {$this->domain}: $resp");

        return $ok;
    }
}
