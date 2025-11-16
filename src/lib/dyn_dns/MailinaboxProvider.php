<?php

require_once __DIR__ . '/BaseProvider.php';

class MailinaboxProvider extends BaseProvider {
    public function updateIp(string $ip): bool {
        $email = $this->credentials['mabEmail'] ?? null;
        $apiKey = $this->credentials['mabApiKey'] ?? null;
        $force = !empty($this->credentials['mabForce']) && $this->credentials['mabForce'] == '1';

        if (!$email || !$apiKey) {
            $this->logger->error("Missing credentials for Mail‑in‑a‑Box DDNS: {$this->domain}");
            return false;
        }

        $url = "https://{$this->credentials['mabHost']}/admin/dns/update";
        $postFields = http_build_query([
            'force' => $force ? 1 : 0,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "$email:$apiKey",
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $this->logger->error("Mail‑in‑a‑Box /dns/update request failed: {$this->domain}");
            return false;
        }
        curl_close($ch);

        if (strpos($resp, 'updated DNS:') !== false) {
            $this->logger->info("Mail‑in‑a‑Box DDNS updated for {$this->domain}: $resp");
            return true;
        } else {
            $this->logger->error("Mail‑in‑a‑Box DDNS update failed for {$this->domain}: $resp");
            return false;
        }
    }
}
