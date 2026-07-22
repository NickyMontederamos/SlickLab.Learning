<?php

use PHPUnit\Framework\TestCase;

final class UploadValidationTest extends TestCase
{
    private const ALLOWED = ['png', 'jpg', 'jpeg'];
    private const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    private function validFile(array $overrides = []): array
    {
        return array_merge([
            'name' => 'screenshot.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/phpABC123',
            'error' => UPLOAD_ERR_OK,
            'size' => 500_000,
        ], $overrides);
    }

    public function testValidPngPasses(): void
    {
        $result = csa_validate_upload($this->validFile(), self::ALLOWED, self::MAX_BYTES);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
    }

    public function testExtensionMatchingIsCaseInsensitive(): void
    {
        $result = csa_validate_upload($this->validFile(['name' => 'screenshot.PNG']), self::ALLOWED, self::MAX_BYTES);
        $this->assertTrue($result['ok']);
    }

    public function testDisallowedExtensionIsRejected(): void
    {
        $result = csa_validate_upload($this->validFile(['name' => 'malware.exe']), self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testMissingExtensionIsRejected(): void
    {
        $result = csa_validate_upload($this->validFile(['name' => 'noextension']), self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
    }

    public function testOnlyTheFinalExtensionIsChecked(): void
    {
        // Documented behavior, not a bug: "shell.php.png" is treated as a
        // .png upload here. Closing the multi-extension trick is the job of
        // the endpoint's random-filename + finfo-sniffing + .htaccess layers,
        // not this pure check.
        $result = csa_validate_upload($this->validFile(['name' => 'shell.php.png']), self::ALLOWED, self::MAX_BYTES);
        $this->assertTrue($result['ok']);
    }

    public function testOversizedFileIsRejected(): void
    {
        $result = csa_validate_upload($this->validFile(['size' => self::MAX_BYTES + 1]), self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('too large', $result['error']);
    }

    public function testFileExactlyAtTheSizeCapPasses(): void
    {
        // <=, not < -- exactly at the cap must not be rejected.
        $result = csa_validate_upload($this->validFile(['size' => self::MAX_BYTES]), self::ALLOWED, self::MAX_BYTES);
        $this->assertTrue($result['ok']);
    }

    public function testEmptyFileIsRejected(): void
    {
        $result = csa_validate_upload($this->validFile(['size' => 0]), self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
    }

    public function testPhpUploadErrorCodeIsSurfacedNotSilentlyPassed(): void
    {
        $result = csa_validate_upload($this->validFile(['error' => UPLOAD_ERR_INI_SIZE]), self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('server upload size limit', $result['error']);
    }

    public function testNoFileUploadedIsRejected(): void
    {
        $result = csa_validate_upload(['error' => UPLOAD_ERR_NO_FILE], self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
    }

    public function testMissingErrorKeyDefaultsToNoFileRatherThanThrowing(): void
    {
        // Malformed/absent $_FILES entry -- must not throw, must fail closed.
        $result = csa_validate_upload([], self::ALLOWED, self::MAX_BYTES);
        $this->assertFalse($result['ok']);
    }
}
