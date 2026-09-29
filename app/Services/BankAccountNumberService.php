<?php

namespace App\Services;

/**
 * V1.1 §0.28 bank-account security helpers.
 *
 * Full account numbers are sensitive: they are stored only in the encrypted
 * field and are never written to audit records or normal application views.
 */
class BankAccountNumberService
{
    /**
     * Derive the safe display/derived fields for a full account number.
     *
     * @return array{masked_account_number: ?string, encrypted_account_number: ?string, last4: ?string}
     */
    public function protect(?string $accountNumber): array
    {
        if ($accountNumber === null || $accountNumber === '') {
            return [
                'masked_account_number' => null,
                'encrypted_account_number' => null,
                'last4' => null,
            ];
        }

        $trimmed = trim($accountNumber);

        return [
            'masked_account_number' => $this->mask($trimmed),
            'encrypted_account_number' => $trimmed,
            'last4' => $this->last4($trimmed),
        ];
    }

    /**
     * Mask an account number so only the last four characters are visible.
     */
    public function mask(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $length = mb_strlen(trim($accountNumber));

        if ($length <= 4) {
            return trim($accountNumber);
        }

        return str_repeat('•', $length - 4).mb_substr(trim($accountNumber), -4);
    }

    private function last4(string $accountNumber): string
    {
        return mb_substr(trim($accountNumber), -4);
    }
}
