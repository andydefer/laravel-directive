<?php

// src/Enums/PermissionMode.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Enum for file and directory permission modes.
 *
 * Common UNIX/Linux permission modes represented as octal values.
 *
 * Permission bits breakdown:
 * - Owner | Group | Others
 * - r=4 (read), w=2 (write), x=1 (execute)
 *
 * @example 755 = rwxr-xr-x (owner: read+write+execute, group/others: read+execute)
 * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode from php-services package instead.
 *             This enum will be removed in version 3.0.0.
 * @see \AndyDefer\PhpServices\Enums\PermissionMode
 */
enum PermissionMode: int
{
    /**
     * Private: owner only has read/write (600)
     * - Owner: read + write
     * - Group: none
     * - Others: none
     * Use for: configuration files with sensitive data
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::PRIVATE instead
     */
    case PRIVATE = 0600;

    /**
     * Read-only for owner only (400)
     * - Owner: read only
     * - Group: none
     * - Others: none
     * Use for: read-only configuration files
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::READ_ONLY instead
     */
    case READ_ONLY = 0400;

    /**
     * Owner read/write, group readable (640)
     * - Owner: read + write
     * - Group: read
     * - Others: none
     * Use for: shared configuration files in development
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::SHARED_CONFIG instead
     */
    case SHARED_CONFIG = 0640;

    /**
     * Owner read/write, group/others read-only (644)
     * - Owner: read + write
     * - Group: read
     * - Others: read
     * Use for: public files, CSS, JS, images
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::PUBLIC_FILE instead
     */
    case PUBLIC_FILE = 0644;

    /**
     * Owner read/write/execute, group/others read/execute (755)
     * - Owner: read + write + execute
     * - Group: read + execute
     * - Others: read + execute
     * Use for: directories, executable scripts
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::DIRECTORY instead
     */
    case DIRECTORY = 0755;

    /**
     * Owner read/write/execute, group read/execute (750)
     * - Owner: read + write + execute
     * - Group: read + execute
     * - Others: none
     * Use for: team directories, shared scripts
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::TEAM_DIRECTORY instead
     */
    case TEAM_DIRECTORY = 0750;

    /**
     * Owner read/write/execute only (700)
     * - Owner: read + write + execute
     * - Group: none
     * - Others: none
     * Use for: private directories, user home folders
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::PRIVATE_DIRECTORY instead
     */
    case PRIVATE_DIRECTORY = 0700;

    /**
     * World writable (dangerous, use with caution) (777)
     * - Owner: read + write + execute
     * - Group: read + write + execute
     * - Others: read + write + execute
     * Use for: temporary directories only, never for production
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::WORLD_WRITABLE instead
     */
    case WORLD_WRITABLE = 0777;

    /**
     * Get the integer value for chmod() function
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::value() instead
     */
    public function value(): int
    {
        return $this->value;
    }

    /**
     * Check if the permission allows read access for owner
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::ownerCanRead() instead
     */
    public function ownerCanRead(): bool
    {
        return ($this->value & 0b100000000) !== 0;
    }

    /**
     * Check if the permission allows write access for owner
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::ownerCanWrite() instead
     */
    public function ownerCanWrite(): bool
    {
        return ($this->value & 0b010000000) !== 0;
    }

    /**
     * Check if the permission allows execute access for owner
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::ownerCanExecute() instead
     */
    public function ownerCanExecute(): bool
    {
        return ($this->value & 0b001000000) !== 0;
    }

    /**
     * Check if the permission allows read access for group
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::groupCanRead() instead
     */
    public function groupCanRead(): bool
    {
        return ($this->value & 0b000100000) !== 0;
    }

    /**
     * Check if the permission allows write access for group
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::groupCanWrite() instead
     */
    public function groupCanWrite(): bool
    {
        return ($this->value & 0b000010000) !== 0;
    }

    /**
     * Check if the permission allows execute access for group
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::groupCanExecute() instead
     */
    public function groupCanExecute(): bool
    {
        return ($this->value & 0b000001000) !== 0;
    }

    /**
     * Check if the permission allows read access for others
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::othersCanRead() instead
     */
    public function othersCanRead(): bool
    {
        return ($this->value & 0b000000100) !== 0;
    }

    /**
     * Check if the permission allows write access for others
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::othersCanWrite() instead
     */
    public function othersCanWrite(): bool
    {
        return ($this->value & 0b000000010) !== 0;
    }

    /**
     * Check if the permission allows execute access for others
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::othersCanExecute() instead
     */
    public function othersCanExecute(): bool
    {
        return ($this->value & 0b000000001) !== 0;
    }

    /**
     * Get formatted octal string representation
     *
     * @param  bool  $withLeadingZero  Include leading zero in output
     * @return string Formatted permission string (e.g., "755" or "0755")
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::toOctalString() instead
     */
    public function toOctalString(bool $withLeadingZero = true): string
    {
        $octal = decoct($this->value);

        return $withLeadingZero ? '0' . $octal : $octal;
    }

    /**
     * Get symbolic notation (e.g., "rwxr-xr-x")
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::toSymbolicNotation() instead
     */
    public function toSymbolicNotation(): string
    {
        $symbols = '';

        // Owner permissions
        $symbols .= $this->ownerCanRead() ? 'r' : '-';
        $symbols .= $this->ownerCanWrite() ? 'w' : '-';
        $symbols .= $this->ownerCanExecute() ? 'x' : '-';

        // Group permissions
        $symbols .= $this->groupCanRead() ? 'r' : '-';
        $symbols .= $this->groupCanWrite() ? 'w' : '-';
        $symbols .= $this->groupCanExecute() ? 'x' : '-';

        // Others permissions
        $symbols .= $this->othersCanRead() ? 'r' : '-';
        $symbols .= $this->othersCanWrite() ? 'w' : '-';
        $symbols .= $this->othersCanExecute() ? 'x' : '-';

        return $symbols;
    }

    /**
     * Create a PermissionMode from an octal value
     *
     * @param  int  $value  Octal permission value (e.g., 0755)
     * @return self|null Returns null if no matching permission mode found
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::fromValue() instead
     */
    public static function fromValue(int $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Create a PermissionMode from symbolic notation
     *
     * @param  string  $notation  Symbolic notation (e.g., "rwxr-xr-x")
     * @return self|null Returns null if invalid notation
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::fromSymbolicNotation() instead
     */
    public static function fromSymbolicNotation(string $notation): ?self
    {
        if (! preg_match('/^[rwx-]{9}$/', $notation)) {
            return null;
        }

        $owner = ($notation[0] === 'r' ? 4 : 0) + ($notation[1] === 'w' ? 2 : 0) + ($notation[2] === 'x' ? 1 : 0);
        $group = ($notation[3] === 'r' ? 4 : 0) + ($notation[4] === 'w' ? 2 : 0) + ($notation[5] === 'x' ? 1 : 0);
        $others = ($notation[6] === 'r' ? 4 : 0) + ($notation[7] === 'w' ? 2 : 0) + ($notation[8] === 'x' ? 1 : 0);

        $value = ($owner << 6) + ($group << 3) + $others;

        return self::tryFrom($value);
    }

    /**
     * Check if this permission mode is secure for files
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::isSecureForFile() instead
     */
    public function isSecureForFile(): bool
    {
        return ! $this->othersCanWrite() && ! $this->groupCanWrite();
    }

    /**
     * Check if this permission mode is secure for directories
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::isSecureForDirectory() instead
     */
    public function isSecureForDirectory(): bool
    {
        return ! $this->othersCanWrite() && ! $this->othersCanExecute();
    }

    /**
     * Get recommended permission for different use cases
     * @deprecated Use AndyDefer\PhpServices\Enums\PermissionMode::getRecommended() instead
     */
    public static function getRecommended(string $useCase): self
    {
        return match ($useCase) {
            'config_secret' => self::PRIVATE,
            'config_public' => self::PUBLIC_FILE,
            'directory_web' => self::DIRECTORY,
            'directory_private' => self::PRIVATE_DIRECTORY,
            'directory_team' => self::TEAM_DIRECTORY,
            'script' => self::DIRECTORY,
            'temporary' => self::WORLD_WRITABLE,
            default => self::PUBLIC_FILE,
        };
    }
}
