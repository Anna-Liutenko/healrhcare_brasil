<?php

declare(strict_types=1);

namespace Infrastructure;

/**
 * HTMLSanitizer
 *
 * Uses HTMLPurifier when available. If it's not installed in the environment
 * we fall back to a conservative, safe sanitizer that strips tags and
 * removes suspicious attributes.
 */
class HTMLSanitizer
{
    public function sanitize(string $html, array $config): string
    {
        // No debug logging or temporary file writes in production.

        // If HTMLPurifier is available, use it (preferred)
        if (class_exists('HTMLPurifier') && class_exists('HTMLPurifier_Config')) {
            // using HTMLPurifier branch
            $purifierConfig = \HTMLPurifier_Config::createDefault();

            // Allowed tags and attributes. Build HTML.Allowed spec that includes
            // attributes per-tag (e.g. a[href|title|target]) which ensures
            // attributes like target are preserved by HTMLPurifier.
            $allowedTags = $config['allowedTags'] ?? [];
            $allowedAttributesMap = $config['allowedAttributes'] ?? [];

            $allowedSpecs = [];
            foreach ($allowedTags as $tag) {
                $tag = trim((string)$tag);
                $attrs = $allowedAttributesMap[$tag] ?? ($allowedAttributesMap[strtolower($tag)] ?? []);
                if (!empty($attrs) && is_array($attrs)) {
                    $spec = $tag . '[' . implode('|', array_map('trim', $attrs)) . ']';
                } else {
                    $spec = $tag;
                }
                $allowedSpecs[] = $spec;
            }

            if (!empty($allowedSpecs)) {
                $purifierConfig->set('HTML.Allowed', implode(',', $allowedSpecs));
            }

            // Allowed attributes fallback (HTML.Allowed should cover common cases)
            $allowedAttributes = [];
            if (isset($config['allowedAttributes']) && is_array($config['allowedAttributes'])) {
                foreach ($config['allowedAttributes'] as $tag => $attrs) {
                    foreach ($attrs as $attr) {
                        $allowedAttributes[] = "$tag.$attr";
                    }
                }
            }
            if (!empty($allowedAttributes)) {
                $purifierConfig->set('HTML.AllowedAttributes', implode(',', $allowedAttributes));
            }

            // Allowed URI schemes
            $schemes = $config['allowedSchemes'] ?? ['http' => true, 'https' => true, 'mailto' => true];
            $purifierConfig->set('URI.AllowedSchemes', $schemes);

            // Disable external resources in data: or other unsafe schemes
            $purifierConfig->set('URI.DisableExternalResources', false);

            // Remove event handlers and scripts
            $purifierConfig->set('HTML.ForbiddenElements', ['script', 'iframe']);

            $purifier = new \HTMLPurifier($purifierConfig);
            $purified = $purifier->purify($html);

            // Post-process: if HTMLPurifier removed allowed attributes (some
            // environments may behave differently), restore them from the
            // original input when they are explicitly allowed in config.
            if (!empty($config['allowedAttributes']) && is_array($config['allowedAttributes'])) {
                libxml_use_internal_errors(true);
                $origDoc = new \DOMDocument();
                $purDoc = new \DOMDocument();
                // Avoid mb_convert_encoding with HTML-ENTITIES (deprecated).
                // Use an XML encoding declaration hack so DOMDocument treats input as UTF-8.
                $origDoc->loadHTML('<?xml encoding="utf-8" ?>' . '<div>' . $html . '</div>');
                $purDoc->loadHTML('<?xml encoding="utf-8" ?>' . '<div>' . $purified . '</div>');
                $xpathOrig = new \DOMXPath($origDoc);
                $xpathPur = new \DOMXPath($purDoc);

                foreach ($config['allowedAttributes'] as $tag => $attrs) {
                    $tag = strtolower((string)$tag);
                    $origNodes = $xpathOrig->query('//' . $tag);
                    $purNodes = $xpathPur->query('//' . $tag);

                    // iterate in order and restore missing attributes
                    $count = min($origNodes->length, $purNodes->length);
                    for ($i = 0; $i < $count; $i++) {
                        $o = $origNodes->item($i);
                        $p = $purNodes->item($i);
                        if (!$o || !$p) continue;

                        foreach ($attrs as $attrName) {
                            $attrName = strtolower((string)$attrName);
                            if ($o->hasAttribute($attrName) && !$p->hasAttribute($attrName)) {
                                    $val = $o->getAttribute($attrName);
                                    // Neutralize javascript: and data: URIs when restoring
                                    if (in_array($attrName, ['href', 'src'], true)) {
                                        if (preg_match('#^\s*(javascript:|data:)#i', $val)) {
                                            $p->setAttribute($attrName, '#');
                                        } else {
                                            $p->setAttribute($attrName, $val);
                                        }
                                    } else {
                                        $p->setAttribute($attrName, $val);
                                    }
                                }
                        }
                    }
                }

                // Extract innerHTML of wrapper div from purDoc
                $body = $purDoc->getElementsByTagName('div')->item(0);
                $out = '';
                if ($body) {
                    foreach ($body->childNodes as $child) {
                        $out .= $purDoc->saveHTML($child);
                    }
                }

                libxml_clear_errors();
                return $out;
            }

            return $purified;
        }

    // Fallback: use DOMDocument to remove dangerous attributes and preserve allowed tags/attributes.
        $allowedTags = $config['allowedTags'] ?? [];
        $allowedAttributes = $config['allowedAttributes'] ?? [];

        // Normalize allowed tags and attributes to lowercase for robust comparisons
        $allowedTagsNormalized = array_map(fn($t) => strtolower((string)$t), $allowedTags);
        $allowedAttributesNormalized = [];
        if (is_array($allowedAttributes)) {
            foreach ($allowedAttributes as $tag => $attrs) {
                $tagKey = strtolower((string)$tag);
                $allowedAttributesNormalized[$tagKey] = array_map(fn($a) => strtolower((string)$a), (array)$attrs);
            }
        }

    libxml_use_internal_errors(true);
    $doc = new \DOMDocument();
    // Avoid mb_convert_encoding with HTML-ENTITIES (deprecated).
    // Use an XML encoding declaration hack so DOMDocument treats input as UTF-8.
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . '<div>' . $html . '</div>');

        $xpath = new \DOMXPath($doc);

        // remove script and iframe nodes always
        foreach ($xpath->query('//script | //iframe') as $n) {
            $n->parentNode->removeChild($n);
        }

    // remove attributes starting with on* and enforce allowedAttributes
        foreach ($xpath->query('//@*') as $attrNode) {
            $attrName = strtolower($attrNode->nodeName);
            $owner = $attrNode->ownerElement;
            if ($owner === null) {
                continue;
            }

            // if attribute is an event handler, remove
            if (str_starts_with($attrName, 'on')) {
                $owner->removeAttributeNode($attrNode);
                continue;
            }

            $tagName = strtolower($owner->nodeName);
            // check allowed tags/attributes
            if (!empty($allowedTagsNormalized) && !in_array($tagName, $allowedTagsNormalized, true)) {
                // remove element but keep children
                $fragment = $doc->createDocumentFragment();
                while ($owner->firstChild) {
                    $fragment->appendChild($owner->removeChild($owner->firstChild));
                }
                $owner->parentNode->replaceChild($fragment, $owner);
                continue;
            }

            // If allowedAttributes were provided, ensure attribute is allowed for this tag
            if (!empty($allowedAttributesNormalized) && is_array($allowedAttributesNormalized)) {
                $attrsForTag = $allowedAttributesNormalized[$tagName] ?? [];
                $allowed = in_array($attrName, $attrsForTag, true);
                if (!$allowed) {
                    $owner->removeAttributeNode($attrNode);
                    continue;
                }
            }

            // neutralize javascript: and data: in href/src
            if (in_array($attrName, ['href', 'src'], true)) {
                $val = $attrNode->nodeValue ?? '';
                if (preg_match('#^\s*(javascript:|data:)#i', $val)) {
                    $owner->setAttribute($attrName, '#');
                }
            }
        }

        // extract innerHTML of our wrapper div
        $body = $doc->getElementsByTagName('div')->item(0);
        $out = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $out .= $doc->saveHTML($child);
            }
        }

        libxml_clear_errors();

        return $out;
    }
}
