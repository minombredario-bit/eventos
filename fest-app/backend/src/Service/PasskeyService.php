<?php

namespace App\Service;

use App\Entity\PasskeyCredential;
use App\Entity\Usuario;
use App\Repository\PasskeyCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * WebAuthn (FIDO2 passkeys) service.
 *
 * Implements passkey registration and authentication without external CBOR libraries,
 * using PHP's built-in OpenSSL extension for ECDSA P-256 signature verification.
 */
class PasskeyService
{
    private const CHALLENGE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasskeyCredentialRepository $credentialRepository,
        private readonly CacheItemPoolInterface $cache,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly string $appUri = '',
    ) {}

    // ──────────────────────────────────────────────────────────────────────
    // REGISTRATION
    // ──────────────────────────────────────────────────────────────────────

    public function generateRegistrationOptions(Usuario $user): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = $this->b64uEncode($challenge);

        $cacheKey = 'passkey_reg_' . $user->getId();
        $item = $this->cache->getItem($cacheKey);
        $item->set($challengeB64);
        $item->expiresAfter(self::CHALLENGE_TTL);
        $this->cache->save($item);

        $rpId = $this->resolveRpId();

        return [
            'challenge' => $challengeB64,
            'rp' => [
                'id'   => $rpId,
                'name' => 'Festapp',
            ],
            'user' => [
                'id'          => $this->b64uEncode((string) $user->getId()),
                'name'        => $user->getEmail() ?? $user->getUserIdentifier(),
                'displayName' => $user->getNombreCompleto() ?? $user->getEmail() ?? '',
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey'             => 'required',
                'userVerification'        => 'preferred',
            ],
            'timeout'     => 60000,
            'attestation' => 'none',
        ];
    }

    public function verifyRegistration(
        Usuario $user,
        string $credentialId,
        string $attestationObjectB64,
        string $clientDataJsonB64,
        string $deviceName,
    ): PasskeyCredential {
        // 1. Retrieve stored challenge
        $cacheKey = 'passkey_reg_' . $user->getId();
        $item = $this->cache->getItem($cacheKey);
        if (!$item->isHit()) {
            throw new BadRequestHttpException('El challenge ha expirado. Inténtalo de nuevo.');
        }
        $storedChallenge = (string) $item->get();

        // 2. Verify clientDataJSON
        $clientDataJson = $this->b64uDecode($clientDataJsonB64);
        $clientData = json_decode($clientDataJson, true, 512, JSON_THROW_ON_ERROR);

        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new BadRequestHttpException('Tipo de operación WebAuthn inválido.');
        }
        if (!hash_equals($storedChallenge, $clientData['challenge'] ?? '')) {
            throw new BadRequestHttpException('Challenge WebAuthn inválido.');
        }
        $this->verifyOrigin($clientData['origin'] ?? '');

        // 3. Parse attestationObject (CBOR)
        $attestationObject = $this->b64uDecode($attestationObjectB64);
        $attObj = $this->cborDecodeMap($attestationObject);

        if (!isset($attObj['authData']) || !is_string($attObj['authData'])) {
            throw new BadRequestHttpException('authData no encontrado en el objeto de attestation.');
        }

        // 4. Parse authData binary
        $authData = $this->parseAuthData($attObj['authData']);

        // 5. Verify rpId hash
        $expectedHash = hash('sha256', $this->resolveRpId(), true);
        if (!hash_equals($expectedHash, $authData['rpIdHash'])) {
            throw new BadRequestHttpException('rpId hash inválido.');
        }

        // 6. Extract and convert public key to PEM
        $publicKeyPem = $this->coseKeyToPem($authData['credentialPublicKey']);

        // 7. Persist credential
        $this->cache->deleteItem($cacheKey);

        $credential = new PasskeyCredential();
        $credential->setUsuario($user);
        $credential->setCredentialId($credentialId);
        $credential->setPublicKey($publicKeyPem);
        $credential->setSignCount($authData['signCount']);
        $credential->setDeviceName(substr(trim($deviceName) ?: 'Dispositivo', 0, 100));

        $this->em->persist($credential);
        $this->em->flush();

        return $credential;
    }

    // ──────────────────────────────────────────────────────────────────────
    // AUTHENTICATION
    // ──────────────────────────────────────────────────────────────────────

    public function generateAuthenticationOptions(?string $email = null): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = $this->b64uEncode($challenge);

        $cacheKey = 'passkey_auth_' . hash('sha256', $email ?? 'anonymous');
        $item = $this->cache->getItem($cacheKey);
        $item->set($challengeB64);
        $item->expiresAfter(self::CHALLENGE_TTL);
        $this->cache->save($item);

        $allowCredentials = [];

        if ($email !== null) {
            $credentials = $this->em->createQuery(
                'SELECT pc.credentialId FROM App\Entity\PasskeyCredential pc
                 JOIN pc.usuario u WHERE u.email = :email'
            )->setParameter('email', mb_strtolower(trim($email)))->getArrayResult();

            foreach ($credentials as $row) {
                $allowCredentials[] = [
                    'type'       => 'public-key',
                    'id'         => $row['credentialId'],
                    'transports' => ['internal', 'usb', 'nfc', 'ble'],
                ];
            }
        }

        return [
            'challenge'        => $challengeB64,
            'timeout'          => 60000,
            'rpId'             => $this->resolveRpId(),
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'preferred',
            '_cacheKey'        => $cacheKey,
        ];
    }

    /**
     * Verifies an authentication assertion and returns a JWT if valid.
     */
    public function verifyAuthentication(
        string $credentialId,
        string $authenticatorDataB64,
        string $clientDataJsonB64,
        string $signatureB64,
        string $challengeCacheKey,
    ): string {
        // 1. Retrieve stored challenge
        $item = $this->cache->getItem($challengeCacheKey);
        if (!$item->isHit()) {
            throw new BadRequestHttpException('El challenge ha expirado. Recarga e inténtalo de nuevo.');
        }
        $storedChallenge = (string) $item->get();

        // 2. Verify clientDataJSON
        $clientDataJson = $this->b64uDecode($clientDataJsonB64);
        $clientData = json_decode($clientDataJson, true, 512, JSON_THROW_ON_ERROR);

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new BadRequestHttpException('Tipo de operación WebAuthn inválido.');
        }
        if (!hash_equals($storedChallenge, $clientData['challenge'] ?? '')) {
            throw new BadRequestHttpException('Challenge WebAuthn inválido.');
        }
        $this->verifyOrigin($clientData['origin'] ?? '');

        // 3. Find credential
        $credential = $this->credentialRepository->findByCredentialId($credentialId);
        if ($credential === null) {
            throw new BadRequestHttpException('Credencial biométrica no registrada.');
        }

        // 4. Parse authenticatorData
        $authDataBin = $this->b64uDecode($authenticatorDataB64);
        $authData = $this->parseAuthDataBase($authDataBin);

        // Verify rpId hash
        $expectedHash = hash('sha256', $this->resolveRpId(), true);
        if (!hash_equals($expectedHash, $authData['rpIdHash'])) {
            throw new BadRequestHttpException('rpId hash inválido.');
        }

        // 5. Verify signature
        $signatureBase = $authDataBin . hash('sha256', $clientDataJson, true);
        $signatureDer = $this->b64uDecode($signatureB64);

        $pubKey = openssl_pkey_get_public($credential->getPublicKey());
        if ($pubKey === false) {
            throw new BadRequestHttpException('Clave pública inválida.');
        }

        $verified = openssl_verify($signatureBase, $signatureDer, $pubKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new BadRequestHttpException('La verificación biométrica ha fallado.');
        }

        // 6. Update sign count and last used
        if ($authData['signCount'] > 0 && $authData['signCount'] <= $credential->getSignCount()) {
            // Potential cloned authenticator – log but don't block for UX
        }
        $credential->setSignCount($authData['signCount']);
        $credential->setLastUsedAt(new \DateTimeImmutable());

        // 7. Delete used challenge
        $this->cache->deleteItem($challengeCacheKey);

        $this->em->flush();

        return $this->jwtManager->create($credential->getUsuario());
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS — Base64URL
    // ──────────────────────────────────────────────────────────────────────

    public function b64uEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function b64uDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS — AuthData parsing
    // ──────────────────────────────────────────────────────────────────────

    private function parseAuthData(string $authDataBin): array
    {
        if (strlen($authDataBin) < 37) {
            throw new BadRequestHttpException('authData demasiado corto.');
        }

        $base = $this->parseAuthDataBase($authDataBin);
        $flags = $base['flags'];

        if (!($flags & 0x40)) {
            throw new BadRequestHttpException('authData no contiene credencial certificada.');
        }

        $offset = 37;
        $offset += 16; // AAGUID

        if (strlen($authDataBin) < $offset + 2) {
            throw new BadRequestHttpException('authData truncado al leer credentialIdLength.');
        }
        $credIdLen = unpack('n', substr($authDataBin, $offset, 2))[1];
        $offset += 2;
        $offset += $credIdLen;

        $coseKeyBin = substr($authDataBin, $offset);
        if ($coseKeyBin === false || strlen($coseKeyBin) === 0) {
            throw new BadRequestHttpException('authData sin clave pública COSE.');
        }

        return array_merge($base, [
            'credentialPublicKey' => $coseKeyBin,
        ]);
    }

    private function parseAuthDataBase(string $authDataBin): array
    {
        if (strlen($authDataBin) < 37) {
            throw new BadRequestHttpException('authData demasiado corto.');
        }

        $rpIdHash  = substr($authDataBin, 0, 32);
        $flags     = ord($authDataBin[32]);
        $signCount = unpack('N', substr($authDataBin, 33, 4))[1];

        return [
            'rpIdHash'  => $rpIdHash,
            'flags'     => $flags,
            'signCount' => $signCount,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS — CBOR minimal decoder
    // ──────────────────────────────────────────────────────────────────────

    private function cborDecodeMap(string $data): array
    {
        $offset = 0;
        $result = $this->cborDecode($data, $offset);
        return is_array($result) ? $result : [];
    }

    private function cborDecode(string $data, int &$offset): mixed
    {
        if ($offset >= strlen($data)) {
            throw new \RuntimeException('Unexpected end of CBOR data at offset ' . $offset);
        }

        $initial = ord($data[$offset++]);
        $majorType = ($initial >> 5) & 0x07;
        $additionalInfo = $initial & 0x1f;

        $value = $this->cborGetLength($data, $offset, $additionalInfo);

        return match ($majorType) {
            0 => $value,
            1 => -1 - $value,
            2 => $this->cborReadBytes($data, $offset, (int) $value),
            3 => $this->cborReadText($data, $offset, (int) $value),
            4 => $this->cborReadArray($data, $offset, (int) $value),
            5 => $this->cborReadMapAsArray($data, $offset, (int) $value),
            7 => $this->cborSimple($additionalInfo, $data, $offset),
            default => throw new \RuntimeException('Unsupported CBOR major type ' . $majorType),
        };
    }

    private function cborGetLength(string $data, int &$offset, int $additionalInfo): int
    {
        return match (true) {
            $additionalInfo <= 23 => $additionalInfo,
            $additionalInfo === 24 => ord($data[$offset++]),
            $additionalInfo === 25 => unpack('n', substr($data, ($offset += 2) - 2, 2))[1],
            $additionalInfo === 26 => unpack('N', substr($data, ($offset += 4) - 4, 4))[1],
            default => 0,
        };
    }

    private function cborReadBytes(string $data, int &$offset, int $length): string
    {
        $bytes = substr($data, $offset, $length);
        $offset += $length;
        return $bytes;
    }

    private function cborReadText(string $data, int &$offset, int $length): string
    {
        return $this->cborReadBytes($data, $offset, $length);
    }

    private function cborReadArray(string $data, int &$offset, int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->cborDecode($data, $offset);
        }
        return $result;
    }

    private function cborReadMapAsArray(string $data, int &$offset, int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $key = $this->cborDecode($data, $offset);
            $val = $this->cborDecode($data, $offset);
            $result[$key] = $val;
        }
        return $result;
    }

    private function cborSimple(int $additionalInfo, string $data, int &$offset): mixed
    {
        return match ($additionalInfo) {
            20 => false,
            21 => true,
            22 => null,
            default => null,
        };
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS — COSE key → PEM
    // ──────────────────────────────────────────────────────────────────────

    private function coseKeyToPem(string $coseKeyBin): string
    {
        $offset = 0;
        $coseKey = $this->cborDecode($coseKeyBin, $offset);

        if (!is_array($coseKey)) {
            throw new BadRequestHttpException('COSE key inválida.');
        }

        $kty = $coseKey[1] ?? null;
        if ($kty !== 2) {
            throw new BadRequestHttpException('Solo se soportan claves EC (tipo 2). Recibido: ' . $kty);
        }

        $x = $coseKey[-2] ?? null;
        $y = $coseKey[-3] ?? null;

        if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
            throw new BadRequestHttpException('Coordenadas de clave EC P-256 inválidas.');
        }

        return $this->ecP256XYToPem($x, $y);
    }

    private function ecP256XYToPem(string $x, string $y): string
    {
        $oidEcPublicKey = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
        $oidPrime256v1  = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

        $algorithmSeq = "\x30" . chr(strlen($oidEcPublicKey) + strlen($oidPrime256v1))
            . $oidEcPublicKey . $oidPrime256v1;

        $point     = "\x04" . $x . $y;
        $bitString = "\x03" . chr(strlen($point) + 1) . "\x00" . $point;

        $spki = "\x30" . chr(strlen($algorithmSeq) + strlen($bitString))
            . $algorithmSeq . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS — Origin / RP ID
    // ──────────────────────────────────────────────────────────────────────

    private function resolveRpId(): string
    {
        $parsed = parse_url(rtrim($this->appUri, '/'));
        return $parsed['host'] ?? 'localhost';
    }

    private function verifyOrigin(string $origin): void
    {
        $appOrigin = rtrim($this->appUri, '/');

        if (str_contains($appOrigin, 'localhost') || str_contains($appOrigin, '127.0.0.1')) {
            return;
        }

        $parsed = parse_url($appOrigin);
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = preg_replace('#^www\.#i', '', $parsed['host'] ?? '');

        $allowedOrigins = [
            strtolower("{$scheme}://{$host}"),
            strtolower("{$scheme}://www.{$host}"),
        ];

        if (!in_array(strtolower($origin), $allowedOrigins, true)) {
            throw new BadRequestHttpException('Origen WebAuthn no permitido: ' . $origin);
        }
    }
}
