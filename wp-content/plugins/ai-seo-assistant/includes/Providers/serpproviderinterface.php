<?php

namespace AISEOAssistant\Providers;

interface SerpProviderInterface
{
    /**
     * @param string $keyword
     * @param array<string, mixed> $args
     * @return array|\WP_Error
     */
    public function search(string $keyword, array $args = []);
}
