<?php
declare(strict_types=1);

namespace Silver\Support;

final class Git
{
    private readonly string $branch;

    public const string MASTER = 'master';
    public const string DEVELOP = 'develop';
    public const string HOTFIX = 'hotfix';
    public const string FEATURE = 'feature';

    public function __construct(\SplFileObject $gitHeadFile)
    {
        $ref = explode('/', $gitHeadFile->current(), 3);
        $this->branch = rtrim($ref[2] ?? '');
    }

    public static function createFromGitRootDir(string $dir): static
    {
        try {
            $gitHeadFile = new \SplFileObject($dir . '/.git/HEAD', 'r');
        } catch (\RuntimeException) {
            throw new \RuntimeException(sprintf('Directory "%s" is not a Git repository.', $dir));
        }

        return new static($gitHeadFile);
    }

    public static function test(): string
    {
        if (is_dir('.git')) {
            $lines = file('.git/HEAD', FILE_USE_INCLUDE_PATH);
            $parts = explode('/', $lines[0] ?? '', 3);
            return trim($parts[2] ?? 'unknown');
        }
        return 'Git not included';
    }

    public function getName(): string
    {
        return $this->branch;
    }

    public function isBasedOnMaster(): bool
    {
        $type = $this->getFlowType();
        return $type === self::HOTFIX || $type === self::MASTER;
    }

    public function isBasedOnDevelop(): bool
    {
        $type = $this->getFlowType();
        return $type === self::FEATURE || $type === self::DEVELOP;
    }

    private function getFlowType(): string
    {
        return explode('/', $this->branch)[0];
    }
}
