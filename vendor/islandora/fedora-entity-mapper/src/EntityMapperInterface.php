<?php

namespace Islandora\EntityMapper;

interface EntityMapperInterface
{
    /**
     * Gets a fedora path given a uuid.
     *
     * @param string $uuid
     * @return string
     */
    public function getFedoraPath(string $uuid): string;

    /**
     * Gets a drupal uuid from a fedora path.
     *
     * @param string $fedora_path
     * @return string
     */
    public function getDrupalUuid(string $fedora_path): string;
}
