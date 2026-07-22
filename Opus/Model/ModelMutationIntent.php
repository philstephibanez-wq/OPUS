<?php
declare(strict_types=1);

namespace Opus\Model;

/**
 * Supported write intents for OPUS Model validation.
 */
final class ModelMutationIntent implements ModelMutationIntentInterface
{
    public const INSERT = 'insert';
    public const UPDATE = 'update';
    public const DELETE = 'delete';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::INSERT, self::UPDATE, self::DELETE];
    }

    public static function assertSupported(string $intent): string
    {
        $intent = strtolower(trim($intent));
        if (!in_array($intent, self::all(), true)) {
            throw new \InvalidArgumentException('OPUS_MODEL_MUTATION_INTENT_UNSUPPORTED: ' . $intent);
        }

        return $intent;
    }

    public static function requiresPredicate(string $intent): bool
    {
        $intent = self::assertSupported($intent);
        return $intent === self::UPDATE || $intent === self::DELETE;
    }
}
