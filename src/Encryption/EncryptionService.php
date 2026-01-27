<?php
/**
 * Encryption Service
 *
 * Provides AES-256-CBC encryption for sensitive data like API keys
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Encryption;

/**
 * Handles encryption and decryption of sensitive data
 */
class EncryptionService {
    /**
     * Encryption cipher
     */
    private const CIPHER = 'aes-256-cbc';

    /**
     * The encryption key
     */
    private string $key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->key = $this->getEncryptionKey();
    }

    /**
     * Encrypt a plaintext string
     *
     * @param string $plaintext The string to encrypt
     * @return string The encrypted string (base64 encoded)
     */
    public function encrypt(string $plaintext): string {
        if (empty($plaintext)) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            return '';
        }

        // Prepend IV to encrypted data and base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt an encrypted string
     *
     * @param string $ciphertext The encrypted string (base64 encoded)
     * @return string The decrypted plaintext
     */
    public function decrypt(string $ciphertext): string {
        if (empty($ciphertext)) {
            return '';
        }

        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER);

        // Extract IV and encrypted data
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        if (strlen($iv) !== $iv_length) {
            return '';
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Get the encryption key
     *
     * Uses WordPress authentication keys/salts for key derivation
     *
     * @return string The derived encryption key
     */
    private function getEncryptionKey(): string {
        // First, check for custom key in wp-config.php
        if (defined('WPB_ENCRYPTION_KEY') && !empty(WPB_ENCRYPTION_KEY)) {
            return hash('sha256', WPB_ENCRYPTION_KEY, true);
        }

        // Use WordPress LOGGED_IN_KEY and LOGGED_IN_SALT
        if (defined('LOGGED_IN_KEY') && defined('LOGGED_IN_SALT')) {
            return hash('sha256', LOGGED_IN_KEY . LOGGED_IN_SALT, true);
        }

        // Fallback: use AUTH_KEY and AUTH_SALT
        if (defined('AUTH_KEY') && defined('AUTH_SALT')) {
            return hash('sha256', AUTH_KEY . AUTH_SALT, true);
        }

        // Last resort: generate and store a key (not recommended for production)
        $stored_key = get_option('wpb_encryption_fallback_key');
        if (!$stored_key) {
            $stored_key = wp_generate_password(64, true, true);
            update_option('wpb_encryption_fallback_key', $stored_key);
        }

        return hash('sha256', $stored_key, true);
    }

    /**
     * Verify encryption integrity
     *
     * Tests that encryption/decryption is working correctly
     *
     * @return bool True if encryption is working
     */
    public function verifyIntegrity(): bool {
        $check = get_option('wpb_encryption_check');

        if (!$check) {
            // First run - set the check value
            $test_value = 'wpb_integrity_' . time();
            $encrypted = $this->encrypt($test_value);
            update_option('wpb_encryption_check', $encrypted);
            update_option('wpb_encryption_check_plain', $test_value);
            return true;
        }

        $expected = get_option('wpb_encryption_check_plain');
        $decrypted = $this->decrypt($check);

        return $decrypted === $expected;
    }

    /**
     * Re-encrypt all stored credentials with a new key
     *
     * Useful when changing encryption keys
     *
     * @param string $old_key The old encryption key
     * @return bool True on success
     */
    public function reEncryptCredentials(string $old_key): bool {
        $old_service = new self();

        // Get current encrypted credentials
        $credentials = get_option('wpb_credentials_encrypted', []);

        if (empty($credentials)) {
            return true;
        }

        $fields = ['claude_api_key', 'amazon_access_key', 'amazon_secret_key'];
        $re_encrypted = [];

        foreach ($fields as $field) {
            if (!empty($credentials[$field])) {
                // Decrypt with old key
                $decrypted = $old_service->decrypt($credentials[$field]);
                if (!empty($decrypted)) {
                    // Re-encrypt with new key
                    $re_encrypted[$field] = $this->encrypt($decrypted);
                }
            }
        }

        // Keep non-sensitive fields as-is
        if (isset($credentials['amazon_partner_tag'])) {
            $re_encrypted['amazon_partner_tag'] = $credentials['amazon_partner_tag'];
        }

        update_option('wpb_credentials_encrypted', $re_encrypted);

        // Update integrity check
        delete_option('wpb_encryption_check');
        delete_option('wpb_encryption_check_plain');
        $this->verifyIntegrity();

        return true;
    }

    /**
     * Mask a sensitive string for display
     *
     * @param string $value The value to mask
     * @param int $visible_chars Number of characters to show at start and end
     * @return string The masked string
     */
    public static function mask(string $value, int $visible_chars = 4): string {
        if (empty($value)) {
            return '';
        }

        $length = strlen($value);

        if ($length <= $visible_chars * 2) {
            return str_repeat('*', $length);
        }

        $start = substr($value, 0, $visible_chars);
        $end = substr($value, -$visible_chars);
        $middle = str_repeat('*', min($length - ($visible_chars * 2), 20));

        return $start . $middle . $end;
    }
}
