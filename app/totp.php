<?php
function base32_decode_str(string $b32): string {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
  $bits = '';
  for ($i = 0; $i < strlen($b32); $i++) {
    $val = strpos($alphabet, $b32[$i]);
    if ($val === false) continue;
    $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
  }
  $bytes = '';
  for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
    $bytes .= chr(bindec(substr($bits, $i, 8)));
  }
  return $bytes;
}

function totp_code(string $secret, int $timeSlice = null): string {
  if ($timeSlice === null) $timeSlice = (int)floor(time() / 30);
  $key = base32_decode_str($secret);
  $binTime = pack('N*', 0) . pack('N*', $timeSlice);
  $hash = hash_hmac('sha1', $binTime, $key, true);
  $offset = ord(substr($hash, -1)) & 0x0F;
  $part = substr($hash, $offset, 4);
  $value = unpack('N', $part)[1] & 0x7FFFFFFF;
  $mod = $value % 1000000;
  return str_pad((string)$mod, 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code): bool {
  $code = preg_replace('/\s+/', '', $code);
  if (!preg_match('/^\d{6}$/', $code)) return false;
  $slice = (int)floor(time() / 30);
  for ($i = -1; $i <= 1; $i++) {
    if (hash_equals(totp_code($secret, $slice + $i), $code)) return true;
  }
  return false;
}

function totp_generate_secret(int $length = 16): string {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $out = '';
  for ($i = 0; $i < $length; $i++) {
    $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  }
  return $out;
}

function totp_otpauth_uri(string $account, string $issuer, string $secret): string {
  $label = rawurlencode($issuer . ':' . $account);
  $params = http_build_query([
    'secret' => $secret,
    'issuer' => $issuer,
    'algorithm' => 'SHA1',
    'digits' => 6,
    'period' => 30,
  ]);
  return "otpauth://totp/{$label}?{$params}";
}
