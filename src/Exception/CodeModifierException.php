<?php

namespace Doctrine\Composer\Exception;

/**
 * Thrown when a patch that is NOT already applied fails to match its target
 * in the file being modified. This is the failure mode that used to be a
 * silent no-op (CodeModifier::callback() just returned false), which let a
 * hook quietly stop patching when the upstream source drifted — e.g. the
 * SQLiteSchemaManager whose search string never matched Doctrine's aligned
 * assignment. It must be raised, never swallowed.
 */
class CodeModifierException extends \RuntimeException
{
    public static function targetNotFound(string $filePath, string $tag, string $search): self
    {
        return new self(sprintf(
            "CodeModifier: patch \"%s\" could not be applied to \"%s\" — its search anchor was not found:\n    %s\n"
            . "The targeted upstream code has most likely changed. This patch is NOT silently skipped; "
            . "update the hook's search string (or its version constraint) to match the installed source.",
            $tag,
            $filePath,
            trim(str_replace("\n", "\n    ", $search))
        ));
    }
}
