<?php

require_once __DIR__ . '/BaseProvider.php';

class NamecheapProvider extends BaseProvider {
    public function updateIp(string $ip): bool {
        $password = $this->credentials['namecheapPassword'] ?? null;
        if (!$password) {
            $this->logger->error("Missing Dynamic DNS password for Namecheap: {$this->domain}");
            return false;
        }

        $parts = explode('.', $this->domain);
        if (count($parts) < 2) {
            $this->logger->error("Invalid domain name for Namecheap: {$this->domain}");
            return false;
        }

        $domain = implode('.', array_slice($parts, -2));
        $host = implode('.', array_slice($parts, 0, -2));

        $url = "https://dynamicdns.park-your-domain.com/update?host={$host}&domain={$domain}&password={$password}&ip={$ip}";

        $context = stream_context_create([
            'http' => ['header' => "User-Agent: CronDNS v1 (https://github.com/TRC-Loop/CronDNS)\r\n"]
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            $this->logger->error("Namecheap update failed for {$this->domain}");
            return false;
        }

        // Correct the XML encoding declaration if it's wrong
        $result = preg_replace(
            '/<\?xml.*encoding=["\'].*["\'].*\?>/i',
            '<?xml version="1.0" encoding="UTF-8"?>',
            $result
        );

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($result);
        if (!$xml) {
            $errors = [];
            foreach (libxml_get_errors() as $err) {
                $errors[] = trim($err->message);
            }
            $this->logger->error("Failed to parse Namecheap response for {$this->domain}: " . implode('; ', $errors));
            return false;
        }

        $errCount = (int) $xml->ErrCount;
        if ($errCount > 0) {
            $errors = [];
            foreach ($xml->errors->children() as $err) {
                $errors[] = (string) $err;
            }
            $this->logger->error("Namecheap returned error for {$this->domain}: " . implode(', ', $errors));
            return false;
        }

        $this->logger->info("Namecheap updated {$this->domain} to $ip successfully");
        return true;
    }
}
