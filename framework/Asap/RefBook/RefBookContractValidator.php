<?php

declare(strict_types=1);

namespace ASAP\RefBook;

use ASAP\RefBook\Model\RefBookClassEntry;
use ASAP\RefBook\Model\RefBookMethodEntry;
use ASAP\RefBook\Model\RefBookScanResult;

/**
 * PUBLIC RefBook contract validator.
 *
 * Role:
 *   Converts a Reflection scan into explicit contract violations without hiding
 *   missing functional metadata behind successful technical reflection.
 */
final class RefBookContractValidator
{
    /**
     * PUBLIC validation entrypoint.
     *
     * @return array<string,mixed> Summary and violation rows.
     */
    public function validate(RefBookScanResult $result): array
    {
        $violations = [];
        foreach ($result->loadErrors() as $error) {
            $violations[] = [
                'scope' => 'load',
                'symbol' => '',
                'method' => '',
                'code' => 'LOAD_ERROR',
                'message' => $error,
            ];
        }

        foreach ($result->classes() as $class) {
            if (!$class->hasMetadata()) {
                $violations[] = $this->classViolation($class, 'CLASS_METADATA_MISSING', 'AsapRefBookClass metadata is missing.');
            }
            foreach ($class->methods() as $method) {
                if (!$method->hasMetadata()) {
                    $violations[] = $this->methodViolation($class, $method, 'METHOD_METADATA_MISSING', 'AsapRefBookMethod metadata is missing.');
                }
            }
        }

        $summary = $result->summary();
        $summary['violations'] = count($violations);

        return [
            'summary' => $summary,
            'violations' => $violations,
        ];
    }

    /**
     * INTERNAL class violation builder.
     *
     * @return array<string,string>
     */
    private function classViolation(RefBookClassEntry $class, string $code, string $message): array
    {
        return [
            'scope' => 'class',
            'symbol' => $class->name(),
            'method' => '',
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * INTERNAL method violation builder.
     *
     * @return array<string,string>
     */
    private function methodViolation(RefBookClassEntry $class, RefBookMethodEntry $method, string $code, string $message): array
    {
        return [
            'scope' => 'method',
            'symbol' => $class->name(),
            'method' => $method->name(),
            'code' => $code,
            'message' => $message,
        ];
    }
}
