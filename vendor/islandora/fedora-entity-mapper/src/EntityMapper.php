<?php

namespace Islandora\EntityMapper;

class EntityMapper implements EntityMapperInterface
{
    /**
     * @inheritDoc
     */
    public function getFedoraPath(string $uuid): string
    {
        if (strlen($uuid) < 8) {
            throw new \InvalidArgumentException(
                "Provided UUID must be at least of length 8 to account for pair-trees",
                400
            );
        }

        $segments = str_split(substr($uuid, 0, 8), 2);
        return implode("/", $segments) . "/$uuid";
    }

    /**
     * @inheritDoc
     */
    public function getDrupalUuid(string $fedora_path): string
    {
        if (empty($fedora_path)) {
            throw new \InvalidArgumentException(
                "Empty string provided fedora path",
                400
            );
        }
        $segments = explode("/", $fedora_path);
        return end($segments);
    }
}
