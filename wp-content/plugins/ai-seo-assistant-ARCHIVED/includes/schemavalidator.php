<?php

namespace AISEOAssistant;

class SchemaValidator
{
    /**
     * Validate common schema types presence and JSON validity.
     */
    public function validateHtml(string $html): array
    {
        $issues = [];
        $foundTypes = [];

        if (preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $block) {
                $json = json_decode($block, true);
                if (!is_array($json)) {
                    $issues[] = 'Invalid JSON-LD';
                    continue;
                }
                $types = $this->collectTypes($json);
                $foundTypes = array_merge($foundTypes, $types);
                if (!$this->hasRequiredFields($json, $types)) {
                    $issues[] = 'Schema missing required fields (' . implode(',', $types) . ')';
                }
            }
        } else {
            $issues[] = 'No JSON-LD found';
        }

        $foundTypes = array_unique($foundTypes);
        $required = ['Article','BreadcrumbList','FAQPage','HowTo'];
        foreach ($required as $type) {
            if (!in_array($type, $foundTypes, true)) {
                $issues[] = "Missing $type schema";
            }
        }

        return $issues;
    }

    private function collectTypes($node): array
    {
        $types = [];
        if (is_array($node)) {
            if (isset($node['@type'])) {
                $types[] = is_array($node['@type']) ? implode(',', $node['@type']) : $node['@type'];
            }
            foreach ($node as $v) {
                $types = array_merge($types, $this->collectTypes($v));
            }
        }
        return array_values(array_unique($types));
    }

    private function hasRequiredFields(array $json, array $types): bool
    {
        // minimal heuristic checks
        if (in_array('FAQPage', $types, true) && empty($json['mainEntity'])) {
            return false;
        }
        if (in_array('Article', $types, true) && empty($json['headline'])) {
            return false;
        }
        return true;
    }
}
