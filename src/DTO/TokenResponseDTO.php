<?php

namespace Ihasan\Bkash\DTO;

readonly class TokenResponseDTO extends BaseDTO
{
    public function __construct(
        public string $idToken,
        public string $tokenType,
        public int $expiresIn,
        public ?string $refreshToken = null,
        public string $statusCode = '0000',
        public string $statusMessage = 'Successful'
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            idToken: $data['id_token'] ?? $data['token'] ?? $data['access_token'] ?? '',
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: (int) ($data['expires_in'] ?? 3600),
            refreshToken: $data['refresh_token'] ?? null,
            statusCode: $data['statusCode'] ?? '0000',
            statusMessage: $data['statusMessage'] ?? 'Successful'
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === '0000' && !empty($this->idToken);
    }
}